<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/utilities/StrClickable.php';
require_once __DIR__.'/utilities/Str.php';

/** Origin-bound, resumable transfer of published estimate custom values to an invoice. */
final class NativeCompanyEstimateSmartTransfer
{
    public function __construct(private $db,private string $p){}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),['company_sales_documents','invoices','itemable','item_tax','company_invoice_requests'])));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function encode(array $v):string{return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}
    private function snapshot(int $issuer,int $actor,int $estimate):array
    {
        $fields=new NativeCompanyCustomFields($this->db,$this->p);$id=NativeCompanyResourceId::encode('company_estimate',$estimate);
        $from=$fields->definitionsForResource($issuer,$actor,'estimate',$id,['active'=>1],'view');
        $values=$fields->storedInput($issuer,$actor,'estimate',$id);
        $count=$this->rows("SELECT COUNT(*) n FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND source_kind='company_estimate' AND source_id=?",[$issuer,$estimate]);
        if((int)($count[0]['n']??0)!==count($values))throw new DomainException('native_estimate_custom_transfer_denied');
        $to=$fields->definitions($issuer,$actor,'invoice',['active'=>1],'create');$mapped=[];
        $enabled=!defined('CUSTOM_FIELDS_SMART_TRANSFER')||CUSTOM_FIELDS_SMART_TRANSFER;
        if($enabled)foreach($from as$source){$key=(string)$source['id'];if(!array_key_exists($key,$values)||$values[$key]===''||$values[$key]===[])continue;
            $parts=explode('_',(string)$source['slug'],2);if(count($parts)!==2)throw new DomainException('native_estimate_custom_transfer_unmatched');$matches=[];
            foreach($to as$target)if(str_starts_with((string)$target['slug'],'invoice_'.$parts[1])&&$target['type']===$source['type']&&$target['options']===$source['options'])$matches[]=$target;
            $threshold=defined('CUSTOM_FIELD_TRANSFER_SIMILARITY')?(float)CUSTOM_FIELD_TRANSFER_SIMILARITY:85.0;
            if(count($matches)!==1||\app\services\utilities\Str::similarity($source['name'],$matches[0]['name'])*100<$threshold)throw new DomainException('native_estimate_custom_transfer_unmatched');
            $dest=(string)$matches[0]['id'];if(array_key_exists($dest,$mapped))throw new DomainException('native_estimate_custom_transfer_ambiguous');$mapped[$dest]=$values[$key];
        }
        $normalized=$enabled?$fields->validateValues($issuer,$actor,'invoice',$mapped,'create'):[];
        return ['source_definitions'=>$from,'source_values'=>$values,'target_definitions'=>$to,'enabled'=>$enabled,'mapped'=>$mapped,'normalized'=>$normalized];
    }
    public function capture(int $issuer,int $actor,int $estimate):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        if($this->rows("SELECT id FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_estimate_transfer_planned'",[$issuer,$estimate]))throw new DomainException('native_estimate_custom_transfer_conflict');
        $snapshot=$this->snapshot($issuer,$actor,$estimate);$json=self::encode(['issuer'=>$issuer,'actor'=>$actor,'estimate'=>$estimate,'snapshot_hash'=>hash('sha256',self::encode($snapshot)),'target_fields'=>array_keys($snapshot['normalized']),'values_hash'=>hash('sha256',self::encode($snapshot['normalized'])),'enabled'=>$snapshot['enabled']]);
        IssuerStorage::query($this->db,"INSERT INTO {$this->p}issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)",[$actor,'native_estimate_transfer_planned',$issuer,$estimate,gmdate('Y-m-d H:i:s'),$json]);
        $id=(int)$this->db->insert_id();$saved=$this->rows("SELECT actor_id,metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND id=?",[$issuer,$estimate,$id]);
        if(count($saved)!==1||(int)$saved[0]['actor_id']!==$actor||$saved[0]['metadata_json']!==$json||$this->snapshot($issuer,$actor,$estimate)!==$snapshot)throw new RuntimeException('issuer_sales_write_unconfirmed');
    }
    private function plan(int $issuer,int $actor,int $estimate):array
    {
        $rows=$this->rows("SELECT actor_id,metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_estimate_transfer_planned' LIMIT 2",[$issuer,$estimate]);
        if(count($rows)!==1||(int)$rows[0]['actor_id']!==$actor)throw new DomainException('native_estimate_custom_transfer_conflict');
        $plan=json_decode($rows[0]['metadata_json'],true,64,JSON_THROW_ON_ERROR);if(!is_array($plan)||($plan['issuer']??null)!==$issuer||($plan['actor']??null)!==$actor||($plan['estimate']??null)!==$estimate||!is_string($plan['snapshot_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$plan['snapshot_hash'])||!is_array($plan['target_fields']??null)||!array_is_list($plan['target_fields'])||!is_string($plan['values_hash']??null)||!preg_match('/^[a-f0-9]{64}$/D',$plan['values_hash'])||!is_bool($plan['enabled']??null))throw new DomainException('native_estimate_custom_transfer_conflict');return $plan;
    }
    /** Runs inside the canonical invoice writer; without its proof this method cannot write. */
    public function verifyOrTransfer(int $issuer,int $actor,int $estimate,string $key,CompanyInvoices $writer):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$plan=$this->plan($issuer,$actor,$estimate);
        $receipt=$this->rows("SELECT invoice_id FROM {$this->p}company_invoice_requests WHERE request_key=? AND issuer_id=? AND actor_id=?",[$key,$issuer,$actor])[0]??null;
        if(!$receipt){if(!hash_equals($plan['snapshot_hash'],hash('sha256',self::encode($this->snapshot($issuer,$actor,$estimate)))))throw new DomainException('native_estimate_custom_transfer_stale');return;}
        $invoice=(int)$receipt['invoice_id'];$audits=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND actor_id=? AND event='native_estimate_transfer_completed' LIMIT 2",[$issuer,$estimate,$actor]);
        $metadata=self::encode(['issuer'=>$issuer,'actor'=>$actor,'estimate'=>$estimate,'invoice'=>$invoice,'plan_hash'=>hash('sha256',self::encode($plan)),'request_key'=>$key]);
        if($audits){if(count($audits)!==1||$audits[0]['metadata_json']!==$metadata)throw new DomainException('native_estimate_custom_transfer_conflict');$this->verifyInvoiceValues($issuer,$invoice,$plan);return;}
        $snapshot=$this->snapshot($issuer,$actor,$estimate);if(!hash_equals($plan['snapshot_hash'],hash('sha256',self::encode($snapshot))))throw new DomainException('native_estimate_custom_transfer_stale');
        $writer->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'invoice',$invoice);
        if($plan['enabled'])(new NativeCompanyCustomFields($this->db,$this->p))->saveCreatedInOwnedTransaction($issuer,$actor,'invoice',$invoice,$snapshot['mapped'],$writer);
        IssuerStorage::query($this->db,"INSERT INTO {$this->p}issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)",[$actor,'native_estimate_transfer_completed',$issuer,$estimate,gmdate('Y-m-d H:i:s'),$metadata]);
        $audit=(int)$this->db->insert_id();$saved=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE id=? AND issuer_id=? AND subject_id=?",[$audit,$issuer,$estimate]);
        if(count($saved)!==1||$saved[0]['metadata_json']!==$metadata||!hash_equals($plan['snapshot_hash'],hash('sha256',self::encode($this->snapshot($issuer,$actor,$estimate)))))throw new RuntimeException('issuer_sales_write_unconfirmed');$this->verifyInvoiceValues($issuer,$invoice,$plan);
    }
    private function verifyInvoiceValues(int $issuer,int $invoice,array $plan):void
    {
        $values=[];foreach($plan['target_fields'] as$id){$identity=NativeCompanyResourceId::decode('custom_field',$id);if($identity['source_kind']!=='company_custom_field'||array_key_exists($id,$values))throw new DomainException('native_estimate_custom_transfer_conflict');$rows=$this->rows("SELECT value FROM {$this->p}company_custom_field_values WHERE issuer_id=? AND field_source_kind='company_custom_field' AND field_id=? AND source_kind='invoice' AND source_id=?",[$issuer,$identity['source_id'],$invoice]);if(count($rows)!==1)throw new DomainException('native_estimate_custom_transfer_conflict');$values[$id]=(string)$rows[0]['value'];}if(!hash_equals($plan['values_hash'],hash('sha256',self::encode($values))))throw new DomainException('native_estimate_custom_transfer_conflict');
    }
}
