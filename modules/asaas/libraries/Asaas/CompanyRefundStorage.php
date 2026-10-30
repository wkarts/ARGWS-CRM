<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';require_once __DIR__.'/CompanyRefundSchema.php';require_once __DIR__.'/CompanyRefundContract.php';

abstract class CompanyRefundStorage
{
    protected CompanyService $service;
    public function __construct(protected $db,protected string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_refund_prefix_invalid');$this->service=new CompanyService($db,$p);}
    public function tables():array
    {
        $t=array_merge($this->service->tablesForRun(),array_keys(CompanyRefundSchema::definitions()));
        foreach(['bb_origins','asaas_company_remote_operations'] as $table)if($this->db->table_exists($this->p.$table))$t[]=$table;
        return array_values(array_unique($t));
    }
    protected function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    protected function auth(int $issuer,int $actor,bool $write):void
    {
        $this->service->module();$version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.5.2','<'))throw new \DomainException('asaas_refund_module_required');if($write)$this->service->authorize($issuer,$actor,'refund');
        elseif(!(new \IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'invoices','view'))throw new \DomainException('asaas_refund_access_denied');
    }
    protected function run(int $issuer,int $actor,callable $work,bool $write=true)
    {
        CompanySchema::ensure($this->db,$this->p,false);CompanyRefundSchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$work,$write){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->auth($issuer,$actor,$write);$r=$work();$this->auth($issuer,$actor,$write);return $r;});
    }
    protected static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_refund_invalid_input');}
    protected function requestRow(int $issuer,string $key):array
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'asaas_company_refund_requests WHERE request_key=? AND issuer_id=? FOR UPDATE',[$key,$issuer])[0]??null;if(!$row)throw new \DomainException('asaas_refund_access_denied');
        foreach(['source','input','baseline'] as $field){$data=json_decode($row[$field.'_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($data)||!hash_equals($row[$field.'_hash'],CompanyRefundContract::hash($data)))throw new \RuntimeException('issuer_asaas_refund_intent_corrupt');}
        $source=json_decode($row['source_json'],true,32,JSON_THROW_ON_ERROR);$input=json_decode($row['input_json'],true,16,JSON_THROW_ON_ERROR);
        foreach(['issuer_id','origin_id','account_row'] as $field)if((int)($source[$field]??0)!==(int)$row[$field])throw new \RuntimeException('issuer_asaas_refund_intent_corrupt');
        if((int)($input['actor_id']??0)!==(int)$row['actor_id']||($input['amount']??null)!==CompanyRefundContract::money((string)$row['amount'])||($input['request_key']??null)!==$row['request_key']||($input['account_revision']??null)!==$row['account_revision']||($input['grant_revision']??null)!==$row['grant_revision'])throw new \RuntimeException('issuer_asaas_refund_intent_corrupt');
        return $row;
    }
    protected function account(int $issuer,int $account):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'asaas_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$issuer])[0]??null;
        if(!$r||(int)$r['enabled']!==1||$r['verified_at']===null)throw new \DomainException('asaas_refund_account_unverified');return $r;
    }
    protected function grant(int $issuer,int $actor,int $account,?string $revision=null):array
    {
        $this->auth($issuer,$actor,true);$r=$this->rows('SELECT * FROM '.$this->p.'asaas_company_refund_grants WHERE account_row=? AND actor_id=? AND issuer_id=? FOR UPDATE',[$account,$actor,$issuer])[0]??null;
        if(!$r||(int)$r['enabled']!==1||$revision!==null&&!hash_equals($revision,$r['revision']))throw new \DomainException('asaas_refund_grant_required');return $r;
    }
    protected function source(int $issuer,int $actor,int $origin,bool $write=true):array
    {
        [$row,$meta,$account,$parent]=$this->service->refundSourceInOwnedTransaction($issuer,$actor,$origin,$write);
        if(!is_string($row['remote_id'])||!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$row['remote_id']))throw new \DomainException('asaas_refund_remote_origin_required');
        $receipt=$this->rows('SELECT r.payment_id,r.canonical_key,r.evidence_hash,p.amount,p.paymentmode,p.invoiceid FROM '.$this->p.'asaas_company_receipts r JOIN '.$this->p.'invoicepaymentrecords p ON p.id=r.payment_id WHERE r.origin_id=? AND r.issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;
        if($receipt&&((int)$receipt['invoiceid']!==(int)$row['invoice_id']||$receipt['paymentmode']!=='asaas_company_received'))throw new \DomainException('asaas_refund_receipt_review');
        $source=['issuer_id'=>$issuer,'origin_id'=>$origin,'account_row'=>(int)$account['id'],'account_id'=>$account['account_id'],'environment'=>$account['environment'],'remote_id'=>$row['remote_id'],'invoice_id'=>(int)$row['invoice_id'],'invoice_hash'=>$parent['hash'],'nominal'=>\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($parent['total'])),'currency'=>'BRL','currency_id'=>(int)$parent['currency'],'due_date'=>$parent['duedate'],'customer_id'=>$meta['customer_id'],'billing_type'=>$meta['billing_type'],'origin_snapshot_hash'=>$meta['snapshot_hash'],'payment_id'=>$receipt?(int)$receipt['payment_id']:null,'payment_nominal'=>$receipt?\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($receipt['amount'])):null,'receipt_key'=>$receipt?$receipt['canonical_key']:null,'receipt_evidence_hash'=>$receipt?$receipt['evidence_hash']:null];
        return [$source,$account];
    }
    protected function assertIntentCurrent(int $issuer,int $actor,array $request,bool $original=true):array
    {
        [$source,$account]=$this->source($issuer,$actor,(int)$request['origin_id']);$this->grant($issuer,$actor,(int)$account['id']);
        if(!hash_equals($request['source_hash'],CompanyRefundContract::hash($source))||!hash_equals($request['account_revision'],$account['revision']))throw new \DomainException('asaas_refund_source_changed');
        if($original)$this->grant($issuer,(int)$request['actor_id'],(int)$account['id'],$request['grant_revision']);return [$source,$account];
    }
    protected function audit(int $issuer,int $actor,int $origin,string $event,array $metadata):void
    {
        $json=CompanyRefundContract::encode($metadata);\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$origin,$event,$json,gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();
        if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$id,$issuer,$actor,$origin,$event,$json]))throw new \RuntimeException('issuer_asaas_refund_audit_unconfirmed');
    }
    protected function publicRequest(array $row):array
    {
        $out=array_intersect_key($row,array_flip(['request_key','issuer_id','origin_id','actor_id','account_row','amount','state','last_code','sent_at','created_at','updated_at']));$out['amount']=CompanyRefundContract::money((string)$row['amount']);$out['source']=json_decode($row['source_json'],true,32,JSON_THROW_ON_ERROR);$out['reason']=json_decode($row['input_json'],true,16,JSON_THROW_ON_ERROR)['reason'];$out['observation']=null;
        if($row['latest_observation']!==null){$o=$this->rows('SELECT * FROM '.$this->p.'asaas_company_refund_observations WHERE id=? AND request_key=? AND issuer_id=? AND origin_id=?',[$row['latest_observation'],$row['request_key'],$row['issuer_id'],$row['origin_id']])[0]??null;
            if(!$o||$o['source_hash']!==$row['source_hash']||$o['account_revision']!==$row['account_revision'])throw new \RuntimeException('issuer_asaas_refund_evidence_corrupt');$safe=json_decode($o['result_json'],true,32,JSON_THROW_ON_ERROR);if(!hash_equals($o['evidence_hash'],CompanyRefundContract::hash($safe)))throw new \RuntimeException('issuer_asaas_refund_evidence_corrupt');$out['observation']=$safe;$out['observed_at']=$o['created_at'];$out['observed_operation']=$o['operation'];$out['observation_id']=(int)$o['id'];}
        $source=$out['source'];$binding=$this->rows('SELECT o.invoice_id,o.remote_id,o.account_id,o.environment,m.account_row,i.hash FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id JOIN '.$this->p.'invoices i ON i.id=o.invoice_id AND i.issuer_id=o.issuer_id WHERE o.id=? AND o.issuer_id=? AND o.provider=? AND o.channel=?',[$row['origin_id'],$row['issuer_id'],'asaas','api'])[0]??null;
        foreach(['invoice_id','remote_id','account_id','environment','account_row'] as $field)if(!$binding||(string)$binding[$field]!== (string)($source[$field]??null))throw new \RuntimeException('issuer_asaas_refund_intent_corrupt');
        if($binding['hash']!==($source['invoice_hash']??null))throw new \RuntimeException('issuer_asaas_refund_intent_corrupt');
        if($source['payment_id']!==null){$receipt=$this->rows('SELECT r.payment_id,r.canonical_key,r.evidence_hash,p.invoiceid,p.amount,p.paymentmode FROM '.$this->p.'asaas_company_receipts r JOIN '.$this->p.'invoicepaymentrecords p ON p.id=r.payment_id WHERE r.origin_id=? AND r.issuer_id=?',[$row['origin_id'],$row['issuer_id']])[0]??null;if(!$receipt||(int)$receipt['payment_id']!==(int)$source['payment_id']||(int)$receipt['invoiceid']!==(int)$source['invoice_id']||$receipt['paymentmode']!=='asaas_company_received'||$receipt['canonical_key']!==$source['receipt_key']||$receipt['evidence_hash']!==$source['receipt_evidence_hash']||\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($receipt['amount']))!==$source['payment_nominal'])throw new \RuntimeException('issuer_asaas_refund_evidence_corrupt');}
        $out['financial_state']='not_posted';$out['financial_block']='partial_refund_identity_contract_required';if(($out['observation']['done_total']??null)===($out['source']['nominal']??null)&&($out['observation']['status']??null)==='REFUNDED')$out['financial_block']='full_refund_canonical_writer_required';if($source['payment_id']!==null&&$this->db->table_exists($this->p.'company_asaas_refund_postings')){$posted=$this->rows('SELECT request_key,payload_json,payload_hash FROM '.$this->p.'company_asaas_refund_postings WHERE issuer_id=? AND invoice_id=? AND payment_id=? AND origin_id=?',[$row['issuer_id'],$source['invoice_id'],$source['payment_id'],$row['origin_id']])[0]??null;if($posted){$declared=json_decode($posted['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!hash_equals($posted['payload_hash'],hash('sha256',$posted['payload_json']))||($declared['refund_request_key']??null)!==$row['request_key'])throw new \RuntimeException('issuer_asaas_refund_evidence_corrupt');$out['financial_state']='external_full_declared';$out['financial_block']=null;$out['financial_declaration']=['key'=>$posted['request_key'],'date'=>$declared['book_date'],'reference'=>$declared['external_reference'],'amount'=>$declared['returned_paid'],'independent_evidence_hash'=>$declared['independent_evidence_hash']];}}return $out;
    }
    protected function insert(string $table,array $data):void{\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.$table.'(`'.implode('`,`',array_keys($data)).'`) VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));}
}
