<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyLegacySalesBoundary.php';
require_once __DIR__.'/SalesNumberPresentation.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Historical sales stay in their native tables, with their original numbers and financial links. */
final class NativeCompanyLegacySales
{
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function type(string $kind):string{return match($kind){'estimates'=>'estimate','proposals'=>'proposal',default=>throw new DomainException('company_sales_invalid')};}
    private function tables(string $kind):array
    {
        self::type($kind);
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit','options',$kind,'currencies','itemable','item_tax'];
        foreach(['leads','invoices','invoicepaymentrecords','sales_activity','proposal_comments','files'] as $table)if($this->db->table_exists($this->prefix.$table))$tables[]=$table;
        return $tables;
    }
    private function authorize(int $issuer,int $actor,string $kind,string $cap='view'):void
    {
        self::type($kind);$acl=new IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$actor);
        if($acl->primaryId()!==$issuer||!$acl->can($issuer,$actor,$kind,'view')||!$acl->can($issuer,$actor,$kind,$cap))throw new DomainException('company_sales_denied');
    }
    private function frame(int $issuer,int $actor,string $kind,int $id):array
    {
        $this->authorize($issuer,$actor,$kind);
        $boundary=NativeCompanyLegacySalesBoundary::sql($this->db,$this->prefix,$kind,'s');
        $row=$this->rows("SELECT s.* FROM {$this->prefix}$kind s WHERE s.id=? AND $boundary FOR UPDATE",[$id])[0]??null;
        if(!$row)throw new DomainException('company_sales_denied');
        $type=self::type($kind);
        $items=$this->rows("SELECT * FROM {$this->prefix}itemable WHERE rel_id=? AND rel_type=? ORDER BY item_order,id FOR UPDATE",[$id,$type]);
        $taxes=$this->rows("SELECT * FROM {$this->prefix}item_tax WHERE rel_id=? AND rel_type=? ORDER BY id FOR UPDATE",[$id,$type]);
        if(count($items)>1000||count($taxes)>5000)throw new DomainException('company_sales_invalid');
        $options=$this->rows("SELECT name,value FROM {$this->prefix}options WHERE name IN ('number_padding_prefixes','proposal_number_prefix','invoice_company_address','invoice_company_city','company_state','invoice_company_postal_code','pdf_format_estimate','pdf_format_proposal','pdf_font','pdf_font_size') ORDER BY name");
        $identity=$this->rows("SELECT legal_name,tax_id FROM {$this->prefix}issuers_registry WHERE id=? AND active=1",[$issuer])[0]??null;
        if(!$identity)throw new DomainException('company_sales_denied');
        $currency=$this->rows("SELECT id,name,symbol FROM {$this->prefix}currencies WHERE id=?",[$row['currency']])[0]??null;
        if(!$currency)throw new DomainException('company_sales_invalid');
        $clientId=$kind==='estimates'?(int)$row['clientid']:($row['rel_type']==='customer'?(int)$row['rel_id']:0);
        $client=$clientId?($this->rows("SELECT userid,company,vat FROM {$this->prefix}clients WHERE userid=?",[$clientId])[0]??null):null;
        if($clientId&&!$client)throw new DomainException('company_sales_denied');
        $invoice=(int)($row[$kind==='estimates'?'invoiceid':'invoice_id']??0);$invoiceRef=null;$payments=[];
        if($invoice>0&&$this->db->table_exists($this->prefix.'invoices')){
            require_once __DIR__.'/ClientIssuerScope.php';
            $guard=\app\services\ClientIssuerScope::legacySql($this->db,$this->prefix,'i','clientid');
            $invoiceRef=$this->rows("SELECT i.id,i.clientid,i.number,i.total FROM {$this->prefix}invoices i WHERE i.id=? AND ($guard)".(IssuerStorage::fieldExists($this->db,'issuer_id',$this->prefix.'invoices')?' AND i.issuer_id IS NULL':''),[$invoice])[0]??null;
            if(!$invoiceRef)throw new DomainException('company_sales_denied');
            if($this->db->table_exists($this->prefix.'invoicepaymentrecords'))$payments=$this->rows("SELECT id,invoiceid,amount,date FROM {$this->prefix}invoicepaymentrecords WHERE invoiceid=? ORDER BY id",[$invoice]);
        }
        $history=$this->db->table_exists($this->prefix.'sales_activity')?$this->rows("SELECT id,description,additional_data,staffid,full_name,date FROM {$this->prefix}sales_activity WHERE rel_id=? AND rel_type=? ORDER BY id DESC LIMIT 200",[$id,$type]):[];
        return compact('row','items','taxes','options','identity','currency','client','invoiceRef','payments','history');
    }
    private static function revision(array $frame):string{return hash('sha256',json_encode($frame,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
    private static function editable(string $kind,array $row):bool
    {
        return (int)($row[$kind==='estimates'?'invoiceid':'invoice_id']??0)===0
            && (int)($row['estimate_id']??0)===0 && empty($row['signature'])
            && in_array((int)$row['status'],$kind==='estimates'?[1,2]:[1,4,5,6],true);
    }
    public function document(int $issuer,int $actor,string $kind,int $id):array
    {
        $id=IssuerPolicy::id($id);
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables($kind),function()use($issuer,$actor,$kind,$id){
            $frame=$this->frame($issuer,$actor,$kind,$id);$row=$frame['row'];$options=array_column($frame['options'],'value','name');$items=[];
            foreach($frame['items']as$item){$taxes=[];foreach($frame['taxes']as$tax)if((int)$tax['itemid']===(int)$item['id'])$taxes[]=['name'=>$tax['taxname'],'rate'=>$tax['taxrate']];$items[]=['id'=>(int)$item['id'],'description'=>$item['description'],'long_description'=>$item['long_description']??'','qty'=>$item['qty'],'rate'=>$item['rate'],'unit'=>$item['unit']??'','taxes'=>$taxes,'is_optional'=>(int)($item['is_optional']??0),'is_selected'=>(int)($item['is_selected']??1)];}
            $padding=(string)($options['number_padding_prefixes']??'6');if(!preg_match('/^(?:[0-9]|1[0-9]|20)$/D',$padding))throw new DomainException('company_sales_invalid');
            $number=$kind==='estimates'?(int)$row['number']:$id;
            $formatted=$kind==='estimates'?($row['formatted_number']??''):'';
            if($formatted==='')$formatted=$kind==='estimates'?SalesNumberPresentation::format($number,$row['number_format'],$row['prefix'],$row['date'],(int)$padding):($options['proposal_number_prefix']??'PROP-').str_pad((string)$id,(int)$padding,'0',STR_PAD_LEFT);
            $status=(int)$row['status'];$invoice=(int)($row[$kind==='estimates'?'invoiceid':'invoice_id']??0);
            $state=$invoice>0?'converted':($status===($kind==='estimates'?4:3)?'accepted':($status===($kind==='estimates'?3:2)?'declined':'open'));
            $address=implode(' · ',array_filter(array_intersect_key($options,array_flip(['invoice_company_address','invoice_company_city','company_state','invoice_company_postal_code'])),static fn($v)=>$v!==''));
            $result=['id'=>(string)$id,'source_id'=>$id,'origin'=>'legacy','kind'=>$kind,'number'=>$number,'formatted_number'=>$formatted,'native_status'=>$status,'state'=>$state,'total'=>(string)$row['total'],'invoice_id'=>$invoice,'created_at'=>$row['datecreated']??$row['date'],'revision_token'=>self::revision($frame),'revision_number'=>0,
                'editable'=>self::editable($kind,$row),'terms'=>$row['terms']??'','legacy_content'=>$kind==='proposals'?($row['content']??''):'','subject'=>$row['subject']??'','reference_no'=>$row['reference_no']??'',
                'payload'=>['client_id'=>(int)($frame['client']['userid']??0),'currency'=>(int)$row['currency'],'date'=>$row['date'],'due_date'=>$row[$kind==='estimates'?'expirydate':'open_till']??'','note'=>$row['clientnote']??'','items'=>$items],
                'context'=>['issuer_id'=>$issuer,'identity'=>$frame['identity']+['address'=>$address],'recipient'=>['name'=>$frame['client']['company']??($row['proposal_to']??''),'tax_id'=>$frame['client']['vat']??''],'currency'=>$frame['currency']],
                'stored_amounts'=>array_intersect_key($row,array_flip(['subtotal','total_tax','discount_total','adjustment','total'])),'pdf_options'=>array_intersect_key($options,array_flip(['pdf_format_estimate','pdf_format_proposal','pdf_font','pdf_font_size'])),'payments'=>$frame['payments'],'history'=>$frame['history']];
            $result['edit_history']=$this->rows("SELECT id,actor_id,created_at,metadata_json FROM {$this->prefix}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_legacy_sales_edited' AND JSON_VALID(metadata_json) AND JSON_EXTRACT(metadata_json,'$.source')=JSON_EXTRACT(?,'$.source') ORDER BY id DESC LIMIT 200",[$issuer,$id,json_encode(['source'=>$kind],JSON_THROW_ON_ERROR)]);
            if(self::revision($this->frame($issuer,$actor,$kind,$id))!==$result['revision_token'])throw new DomainException('company_sales_stale');
            return $result;
        });
    }
    /** Edit descriptive fields of an unsigned, unconverted historical document; item IDs and money stay intact. */
    public function update(int $issuer,int $actor,string $kind,int $id,string $revision,array $input):void
    {
        $fields=$kind==='estimates'?['clientnote','terms','reference_no']:['subject','content'];
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||array_diff(array_keys($input),$fields)||$input===[])throw new DomainException('company_sales_invalid');
        foreach($input as$key=>$value)if(!is_string($value)||strlen($value)>match($key){'subject'=>191,'reference_no'=>100,default=>65535}||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('company_sales_invalid');
        if(isset($input['content'])){require_once __DIR__.'/NativeCompanyContentHtml.php';$input['content']=NativeCompanyContentHtml::purify($input['content']);}
        IssuerTransaction::run($this->db,$this->prefix,$this->tables($kind),function()use($issuer,$actor,$kind,$id,$revision,$input){
            $this->authorize($issuer,$actor,$kind,'edit');$before=$this->frame($issuer,$actor,$kind,$id);
            if(!hash_equals(self::revision($before),$revision))throw new DomainException('company_sales_stale');
            if(!self::editable($kind,$before['row']))throw new DomainException('company_sales_denied');
            $set=implode(',',array_map(static fn($key)=>$key.'=?',array_keys($input)));
            IssuerStorage::query($this->db,"UPDATE {$this->prefix}$kind SET $set WHERE id=?",array_merge(array_values($input),[$id]));
            $expected=$before;$expected['row']=array_replace($expected['row'],$input);
            if($this->frame($issuer,$actor,$kind,$id)!==$expected)throw new RuntimeException('issuer_sales_write_unconfirmed');
            $this->audit($issuer,$actor,$kind,$id,'edited',$revision,['before'=>array_intersect_key($before['row'],$input),'after'=>$input]);
            if($this->frame($issuer,$actor,$kind,$id)!==$expected)throw new RuntimeException('issuer_sales_write_unconfirmed');
            $this->authorize($issuer,$actor,$kind,'edit');
        });
    }
    private function audit(int $issuer,int $actor,string $kind,int $id,string $event,string $revision,array $changes=[]):void
    {
        $meta=json_encode(['source'=>$kind,'revision'=>$revision]+$changes,JSON_THROW_ON_ERROR);$event='native_legacy_sales_'.$event;
        IssuerStorage::query($this->db,"INSERT INTO {$this->prefix}issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)",[$actor,$event,$issuer,$id,gmdate('Y-m-d H:i:s'),$meta]);$audit=(int)$this->db->insert_id();
        if(!$audit||!$this->rows("SELECT id FROM {$this->prefix}issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?",[$audit,$issuer,$actor,$id,$event,$meta]))throw new RuntimeException('issuer_sales_write_unconfirmed');
    }
    public function confirmPdf(int $issuer,int $actor,string $kind,int $id,string $revision):void
    {
        IssuerTransaction::run($this->db,$this->prefix,$this->tables($kind),function()use($issuer,$actor,$kind,$id,$revision){
            if(!hash_equals(self::revision($this->frame($issuer,$actor,$kind,$id)),$revision))throw new DomainException('company_sales_stale');
            $this->audit($issuer,$actor,$kind,$id,'pdf_downloaded',$revision);
            if(!hash_equals(self::revision($this->frame($issuer,$actor,$kind,$id)),$revision))throw new DomainException('company_sales_stale');
        });
    }
}
