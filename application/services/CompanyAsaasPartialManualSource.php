<?php
declare(strict_types=1);
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyService.php';
/** Closed local-only authority: a partial receipt never mutates or settles the remote charge. */
final class CompanyAsaasPartialManualSource
{
    private ?array $decision=null;
    private const EVENT='company_asaas_partial_manual_recorded';
    public function __construct(private $db,private string $p,private int $origin){IssuerPolicy::id($origin);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array
    {
        $tables=(new \Asaas\CompanyService($this->db,$this->p))->tablesForRun();
        foreach(['asaas_company_refund_requests','asaas_company_recreations','asaas_company_card_payments','asaas_company_manual_settlements'] as $table)if($this->db->table_exists($this->p.$table))$tables[]=$table;
        return array_values(array_unique(array_merge($tables,['company_payment_receipts'])));
    }
    private function identity($db,string $p):void
    {
        if($db!==$this->db||$p!==$this->p)throw new DomainException('asaas_partial_manual_source_denied');
        IssuerTransaction::assertOwned($db,$p,$this->tables());
    }
    private function recorded(int $issuer,int $invoice,int $payment,string $key,string $hash):?array
    {
        $matches=[];
        foreach($this->rows('SELECT actor_id,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event=? FOR UPDATE',[$issuer,$invoice,self::EVENT]) as $row){
            $m=json_decode($row['metadata_json'],true,32,JSON_THROW_ON_ERROR);
            if(($m['request_key']??null)!==$key)continue;
            if(($m['payment_id']??null)!==$payment||($m['input_hash']??null)!==$hash||($m['origin_id']??null)!==$this->origin||($m['actor_id']??null)!==(int)$row['actor_id'])throw new DomainException('asaas_partial_manual_history_conflict');
            $proof=$this->rows('SELECT result_json,evidence_hash,account_revision FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=? AND operation=? FOR UPDATE',[$m['proof_id']??0,$this->origin,'query'])[0]??null;
            if(!$proof||($m['remote_action']??null)!=='none'||($m['proof_hash']??null)!==$proof['evidence_hash']||($m['account_revision']??null)!==$proof['account_revision']||!hash_equals($proof['evidence_hash'],hash('sha256',json_encode(json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))))throw new DomainException('asaas_partial_manual_history_conflict');
            $matches[]=$m;
        }
        if(count($matches)>1)throw new DomainException('asaas_partial_manual_history_conflict');
        return $matches[0]??null;
    }
    public function claim($db,string $p,int $issuer,int $actor,int $invoice,string $key,int $amount,string $hash):void
    {
        $this->review($db,$p,$issuer,$actor,$invoice,$key,$amount,$hash,false);
    }
    /** Read-only eligibility for reservation; never authorizes the native partial writer. */
    public function reviewFull($db,string $p,int $issuer,int $actor,int $invoice,string $key,int $amount,string $hash):array
    {
        $this->review($db,$p,$issuer,$actor,$invoice,$key,$amount,$hash,true);$review=$this->decision;$this->decision=null;return $review;
    }
    private function review($db,string $p,int $issuer,int $actor,int $invoice,string $key,int $amount,string $hash,bool $full):void
    {
        $this->identity($db,$p);$acl=new IssuerResourceAccess($db,$p);$acl->lockFinancialIdentity($issuer,$actor);
        $service=new \Asaas\CompanyService($db,$p);$service->authorize($issuer,$actor,'settle');
        $version=$this->rows('SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
        if(version_compare($version,'1.6.8','<'))throw new DomainException('asaas_partial_manual_module_required');
        [$origin,$meta,$account,$parent]=$service->manualSourceInOwnedTransaction($issuer,$actor,$this->origin);
        if((int)$origin['invoice_id']!==$invoice||!in_array($meta['billing_type'],['PIX','BOLETO'],true))throw new DomainException('asaas_partial_manual_source_denied');
        $service->assertAutomaticAllowed($this->origin); // Bridge/manual-only origins retain their independent consent boundary.
        foreach(['bb_origins','efi_company_origins'] as $table)if($db->table_exists($p.$table)&&$this->rows('SELECT id FROM '.$p.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new DomainException('asaas_company_multiple_providers');
        $existing=$this->rows('SELECT payment_id,input_hash,actor_id FROM '.$p.'company_payment_receipts WHERE request_key=? AND issuer_id=? AND invoice_id=? FOR UPDATE',[$key,$issuer,$invoice])[0]??null;
        if($existing){
            if($full)throw new DomainException('asaas_partial_manual_history_conflict');
            if((int)$existing['actor_id']!==$actor||$existing['input_hash']!==$hash||!$this->recorded($issuer,$invoice,(int)$existing['payment_id'],$key,$hash))throw new DomainException('asaas_partial_manual_history_conflict');
            $this->decision=['replay'=>(int)$existing['payment_id'],'issuer'=>$issuer,'actor'=>$actor,'invoice'=>$invoice,'key'=>$key,'hash'=>$hash];return;
        }
        if($db->table_exists($p.'asaas_company_refund_requests'))\Asaas\CompanyOriginGenerations::postRefundOriginInOwned($db,$p,$issuer,$invoice,$this->origin);
        elseif(count($this->rows('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$invoice]))!==1)throw new DomainException('asaas_partial_manual_history_conflict');
        if(!$full&&$db->table_exists($p.'asaas_company_manual_settlements')&&$this->rows('SELECT origin_id FROM '.$p.'asaas_company_manual_settlements WHERE origin_id=? FOR UPDATE',[$this->origin]))throw new DomainException('asaas_partial_manual_operation_pending');
        if($origin['state']!=='issued'||!in_array((int)$parent['status'],[1,3,4],true))throw new DomainException('asaas_partial_manual_unpaid_required');
        foreach(['asaas_company_remote_operations','asaas_company_refund_requests','asaas_company_card_payments','asaas_company_receipts'] as $table)if($db->table_exists($p.$table)&&$this->rows('SELECT origin_id FROM '.$p.$table.' WHERE origin_id=? FOR UPDATE',[$this->origin]))throw new DomainException('asaas_partial_manual_operation_pending');
        if($this->rows("SELECT event_id FROM {$p}asaas_company_jobs WHERE origin_id=? AND state IN ('pending','processing') FOR UPDATE",[$this->origin]))throw new DomainException('asaas_partial_manual_operation_pending');
        $proof=$this->rows('SELECT * FROM '.$p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$this->origin])[0]??null;
        if(!$proof||$proof['operation']!=='query'||$proof['state']!=='issued'||$proof['account_revision']!==$account['revision']||$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s'))throw new DomainException('asaas_partial_manual_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],hash('sha256',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)))||($safe['id']??null)!==$origin['remote_id']||($safe['deleted']??true)||($safe['adverse']??true)||!in_array($safe['status']??'',['PENDING','OVERDUE'],true)||($safe['payment_date']??'')!==''||($safe['credit_date']??'')!==''||($safe['billing_type']??null)!==$meta['billing_type']||CompanyInvoiceAmounts::minor($safe['value']??'')!==CompanyInvoiceAmounts::minor($parent['total']))throw new DomainException('asaas_partial_manual_unpaid_required');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$origin['remote_id']]) as $event)
            if($event['created_at']>$proof['created_at']||!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE'],true)||in_array($event['state'],['review_required','conflict'],true))throw new DomainException('asaas_partial_manual_event_review');
        foreach($this->rows('SELECT n.id,r.request_key,r.input_hash FROM '.$p.'invoicepaymentrecords n LEFT JOIN '.$p.'company_payment_receipts r ON r.payment_id=n.id AND r.invoice_id=n.invoiceid AND r.issuer_id=? WHERE n.invoiceid=? FOR UPDATE',[$issuer,$invoice]) as $payment)
            if(!$payment['request_key']||!$this->recorded($issuer,$invoice,(int)$payment['id'],$payment['request_key'],$payment['input_hash']))throw new DomainException('asaas_partial_manual_other_funds');
        $balance=(new CompanyInvoiceBalance($db,$p))->inOwned($issuer,$actor,$invoice);
        if($balance['applied_credit']!=='0.00'||$balance['noncash_reductions']!=='0.00'||$balance['received_external_reversed']!=='0.00')throw new DomainException('asaas_partial_manual_other_funds');
        if($amount<=0||($full?$amount!==CompanyInvoiceAmounts::minor($balance['receivable']):$amount>=CompanyInvoiceAmounts::minor($balance['receivable'])))throw new DomainException('asaas_partial_manual_partial_only');
        $this->decision=['replay'=>null,'issuer'=>$issuer,'actor'=>$actor,'invoice'=>$invoice,'key'=>$key,'hash'=>$hash,'proof'=>(int)$proof['id'],'proof_hash'=>$proof['evidence_hash'],'account_revision'=>$account['revision'],'total'=>$parent['total']];
    }
    public function applied($db,string $p,int $issuer,int $actor,int $invoice,string $key,int $payment,string $hash):void
    {
        $this->identity($db,$p);$d=$this->decision;
        if(!$d||[$d['issuer'],$d['actor'],$d['invoice'],$d['key'],$d['hash']]!==[$issuer,$actor,$invoice,$key,$hash])throw new DomainException('asaas_partial_manual_source_denied');
        if($d['replay']!==null){if($d['replay']!==$payment)throw new DomainException('asaas_partial_manual_history_conflict');return;}
        $metadata=['origin_id'=>$this->origin,'actor_id'=>$actor,'request_key'=>$key,'input_hash'=>$hash,'payment_id'=>$payment,'proof_id'=>$d['proof'],'proof_hash'=>$d['proof_hash'],'account_revision'=>$d['account_revision'],'remote_action'=>'none'];
        IssuerStorage::query($db,'INSERT INTO '.$p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,self::EVENT,$issuer,$invoice,gmdate('Y-m-d H:i:s'),json_encode($metadata,JSON_THROW_ON_ERROR)]);
        if($this->recorded($issuer,$invoice,$payment,$key,$hash)!==$metadata)throw new RuntimeException('asaas_partial_manual_write_unconfirmed');
        (new \Asaas\CompanyService($db,$p))->authorize($issuer,$actor,'settle');
    }
}
