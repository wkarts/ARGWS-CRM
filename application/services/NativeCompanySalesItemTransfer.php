<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanyInvoices.php';

/** Transfers position-bound private sales values only to lines attested by the canonical invoice writer. */
final class NativeCompanySalesItemTransfer
{
    public function __construct(private $db,private string $p){}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),['company_sales_documents','invoices','itemable','item_tax','company_invoice_requests'])));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function encode(array $value):string{return json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    public function verifyOrTransfer(int $issuer,int $actor,int $sales,string $key,CompanyInvoices $writer):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $source=$this->rows('SELECT kind,state,payload_json,input_hash,conversion_actor_id FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$sales])[0]??null;
        if(!$source||!in_array($source['kind'],['estimates','proposals'],true)||!in_array($source['state'],['converting','converted'],true)||(int)$source['conversion_actor_id']!==$actor)throw new DomainException('native_sales_item_transfer_denied');
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach([[$source['kind'],'view'],[$source['kind'],'edit'],['invoices','view'],['invoices','create']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('native_sales_item_transfer_denied');
        $payload=json_decode($source['payload_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($payload)||!hash_equals($source['input_hash'],hash('sha256',self::encode([$source['kind'],$payload])))||!isset($payload['native_item_fields']))throw new DomainException('native_sales_item_transfer_denied');
        $frame=$payload['native_item_fields'];$fields=new NativeCompanyCustomFields($this->db,$this->p);
        $receipt=$this->rows('SELECT invoice_id FROM '.$this->p.'company_invoice_requests WHERE issuer_id=? AND actor_id=? AND request_key=?',[$issuer,$actor,$key])[0]??null;
        $audits=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event='native_sales_item_transfer_completed' LIMIT 2",[$issuer,$actor,$sales]);
        if(!$receipt&&$audits)throw new DomainException('native_sales_item_transfer_conflict');
        if($audits){
            if(count($audits)!==1)throw new DomainException('native_sales_item_transfer_conflict');
            $metadata=json_decode($audits[0]['metadata_json'],true,32,JSON_THROW_ON_ERROR);
            if(!is_array($metadata)||($metadata['invoice']??null)!==(int)$receipt['invoice_id']||($metadata['request_key']??null)!==$key||($metadata['source_hash']??null)!==$source['input_hash']||($metadata['frame_hash']??null)!==hash('sha256',self::encode($frame)))throw new DomainException('native_sales_item_transfer_conflict');
            $this->verify($issuer,(int)$receipt['invoice_id'],$metadata['lines']??[]);$this->confirmSource($issuer,$actor,$sales,$source);return;
        }
        $definitions=$fields->salesItemDefinitions($issuer,$actor,$source['kind'],'edit',['active'=>1]);
        if(!hash_equals($frame['definitions_hash'],hash('sha256',self::encode($definitions))))throw new DomainException('native_sales_item_fields_stale');
        $normalized=[];foreach($frame['values']as$values)$normalized[]=$fields->validateSalesItemValues($issuer,$actor,$source['kind'],'edit',$values);
        if(!$receipt){$this->confirmSource($issuer,$actor,$sales,$source);return;}
        $invoice=(int)$receipt['invoice_id'];$lines=$writer->createdLineIdsInOwned($issuer,$actor,$invoice,$key);
        if(count($lines)!==count($frame['values']))throw new DomainException('native_sales_item_transfer_conflict');
        $record=[];
        foreach($lines as$position=>$line){
            $fields->saveCreatedInOwnedTransaction($issuer,$actor,'items',$line['itemable_id'],$frame['values'][$position],$writer);
            $values=$normalized[$position];ksort($values,SORT_STRING);
            $record[]=['id'=>$line['itemable_id'],'fields'=>array_keys($values),'values_hash'=>hash('sha256',self::encode($values))];
        }
        $metadata=['invoice'=>$invoice,'request_key'=>$key,'source_hash'=>$source['input_hash'],'frame_hash'=>hash('sha256',self::encode($frame)),'lines'=>$record];$json=self::encode($metadata);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$sales,'native_sales_item_transfer_completed',$json,gmdate('Y-m-d H:i:s')]);
        $audit=(int)$this->db->insert_id();$saved=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=?',[$audit,$issuer,$actor,$sales]);
        if(count($saved)!==1||$saved[0]['metadata_json']!==$json||$fields->salesItemDefinitions($issuer,$actor,$source['kind'],'edit',['active'=>1])!==$definitions)throw new RuntimeException('issuer_sales_write_unconfirmed');
        $this->verify($issuer,$invoice,$record);$this->confirmSource($issuer,$actor,$sales,$source);
    }
    private function confirmSource(int $issuer,int $actor,int $sales,array $source):void
    {
        $current=$this->rows('SELECT kind,state,payload_json,input_hash,conversion_actor_id FROM '.$this->p.'company_sales_documents WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$sales]);
        if(count($current)!==1||$current[0]!==$source)throw new RuntimeException('issuer_sales_write_unconfirmed');
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach([[$source['kind'],'view'],[$source['kind'],'edit'],['invoices','view'],['invoices','create']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('native_sales_item_transfer_denied');
    }
    private function verify(int $issuer,int $invoice,array $lines):void
    {
        $actual=$this->rows("SELECT id FROM {$this->p}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id",[$invoice]);
        if(array_map('intval',array_column($actual,'id'))!==array_column($lines,'id'))throw new DomainException('native_sales_item_transfer_conflict');
        foreach($lines as$line){
            if(!is_array($line['fields']??null)||!array_is_list($line['fields'])||!is_string($line['values_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$line['values_hash']))throw new DomainException('native_sales_item_transfer_conflict');
            $stored=$this->rows("SELECT field_source_kind,field_id,value FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='invoice_item' AND source_id=? ORDER BY field_source_kind,field_id",[$issuer,$line['id']]);$values=[];
            foreach($stored as$row){$id=NativeCompanyResourceId::encode($row['field_source_kind'],$row['field_id']);$values[$id]=(string)$row['value'];}
            ksort($values,SORT_STRING);
            if(array_keys($values)!==$line['fields']||!hash_equals($line['values_hash'],hash('sha256',self::encode($values))))throw new DomainException('native_sales_item_transfer_conflict');
        }
    }
}
