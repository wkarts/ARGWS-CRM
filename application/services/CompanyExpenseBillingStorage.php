<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySalesBase.php';require_once __DIR__.'/CompanyBillingInvoiceLifecycle.php';
require_once __DIR__.'/CompanyExpenses.php';
require_once __DIR__.'/CompanyExpenseBillingSchema.php';

/** Internal source locks and fingerprints. No ambient company, task billing or transport authority. */
abstract class CompanyExpenseBillingStorage extends CompanySalesBase
{
    public function tables():array{return array_values(array_unique(array_merge((new CompanyExpenses($this->db,$this->prefix))->tables(),(new CompanyBillingInvoiceLifecycle($this->db,$this->prefix))->tables(),['company_expense_billing_origins','company_expense_billing_links','currencies','company_currency_units','options','company_invoice_requests','invoices','issuers_invoice_documents'])));}
    protected function billingAuth(int $issuer,int $actor,bool $invoice=true):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);foreach([['expenses','view'],['expenses','edit'],['customers','view']] as [$f,$c])if(!$acl->can($issuer,$actor,$f,$c))throw new DomainException('company_expense_billing_denied');if($invoice)foreach(['view','create'] as $c)if(!$acl->can($issuer,$actor,'invoices',$c))throw new DomainException('company_expense_billing_denied');
    }
    protected function billingTx(int $issuer,int $actor,callable $work,array $extraTables=[])
    {
        CompanyExpenseBillingSchema::assertValid($this->db,$this->prefix);return IssuerTransaction::run($this->db,$this->prefix,array_values(array_unique(array_merge($this->tables(),$extraTables))),function()use($issuer,$actor,$work){(new IssuerResourceAccess($this->db,$this->prefix))->lockFinancialIdentity($issuer,$actor);$this->billingAuth($issuer,$actor);$result=$work();$this->billingAuth($issuer,$actor);return $result;});
    }
    protected function expense(int $issuer,int $id):array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_expenses WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_expense_billing_denied');return $r;
    }
    protected function references(int $issuer,int $actor,array $r):void
    {
        if((int)$r['client_id']<1||!$this->rows('SELECT c.userid FROM '.$this->prefix.'clients c JOIN '.$this->prefix.'issuers_clients o ON o.client_id=c.userid WHERE c.userid=? AND c.active=1 AND o.owner_issuer_id=? FOR UPDATE',[$r['client_id'],$issuer]))throw new DomainException('company_expense_billing_reference_denied');
        if((int)$r['project_id']!==0){if(!(new IssuerResourceAccess($this->db,$this->prefix))->can($issuer,$actor,'projects','view')||!$this->rows('SELECT id FROM '.$this->prefix.'company_projects WHERE id=? AND issuer_id=? AND client_id=? AND status=? FOR UPDATE',[$r['project_id'],$issuer,$r['client_id'],'active']))throw new DomainException('company_expense_billing_reference_denied');}
        if($this->db->table_exists($this->prefix.'asaas_company_fee_expenses')&&$this->rows('SELECT origin_id FROM '.$this->prefix.'asaas_company_fee_expenses WHERE issuer_id=? AND expense_id=? FOR UPDATE',[$issuer,$r['id']]))throw new DomainException('company_expense_provider_source_immutable');
    }
    protected function link(int $issuer,int $id):?array{return $this->rows('SELECT * FROM '.$this->prefix.'company_expense_billing_links WHERE expense_id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;}
    protected function origin(int $issuer,int $id,int $origin):array
    {
        $r=$this->rows('SELECT * FROM '.$this->prefix.'company_expense_billing_origins WHERE id=? AND issuer_id=? AND expense_id=? FOR UPDATE',[$origin,$issuer,$id])[0]??null;
        if(!$r||!hash_equals($r['source_hash'],self::hash($this->decode($r['source_json'])))||!hash_equals($r['origin_hash'],self::hash($this->decode($r['context_json'])))||!hash_equals($r['payload_hash'],self::hash($this->decode($r['payload_json']))))throw new RuntimeException('issuer_expense_billing_unconfirmed');
        return $r;
    }
    /** A finalized link must still point at the one durable canonical receipt, including reader/retry paths. */
    protected function confirmedInvoice(int $issuer,int $id,array $link):void
    {
        $o=$this->origin($issuer,$id,(int)$link['origin_id']);$receipt=$this->rows('SELECT r.invoice_id,r.actor_id FROM '.$this->prefix.'company_invoice_requests r JOIN '.$this->prefix.'invoices i ON i.id=r.invoice_id AND i.issuer_id=r.issuer_id WHERE r.request_key=? AND r.issuer_id=? FOR UPDATE',[self::canonicalKey($issuer,$id),$issuer])[0]??null;
        if(!$receipt||(int)$receipt['invoice_id']!==(int)$link['invoice_id']||(int)$receipt['actor_id']!==(int)$link['conversion_actor_id'])throw new RuntimeException('issuer_expense_billing_unconfirmed');$this->assertOriginOrReceipt($issuer,(int)$receipt['actor_id'],$this->decode($o['payload_json']),$o['origin_hash'],$this->decode($o['context_json']),self::canonicalKey($issuer,$id));
    }
    protected static function canonicalKey(int $issuer,int $id):string{return hash('sha256','company-expense-invoice:'.$issuer.':'.$id);}
    protected function confirmLink(int $issuer,int $id,array $expected):void{$r=$this->link($issuer,$id);foreach($expected as $k=>$v)if(!$r||(string)$r[$k]!== (string)$v)throw new RuntimeException('issuer_expense_billing_unconfirmed');}
    protected function billingAudit(int $issuer,int $actor,int $id,string $event,int $origin,?int $invoice=null):void
    {
        $json=self::encode(['origin_id'=>$origin,'invoice_id'=>$invoice]);$this->insert('issuers_audit',['issuer_id'=>$issuer,'actor_id'=>$actor,'subject_id'=>$id,'event'=>$event,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>$json]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->prefix.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$issuer,$actor,$id,$event,$json]))throw new RuntimeException('issuer_expense_billing_unconfirmed');
    }
    protected function guard(int $issuer,int $actor,int $id,int $origin):array
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,$this->tables());$this->billingAuth($issuer,$actor);$r=$this->expense($issuer,$id);$link=$this->link($issuer,$id);if(!$link||!in_array($link['state'],['converting','converted'],true)||(int)$link['origin_id']!==$origin||(int)$link['conversion_actor_id']!==$actor)throw new DomainException('company_expense_billing_conflict');$o=$this->origin($issuer,$id,$origin);if($r['state']!=='recorded'||!hash_equals($o['source_hash'],self::hash($r)))throw new DomainException('company_expense_billing_stale');$this->references($issuer,$actor,$r);$payload=$this->decode($o['payload_json']);$this->assertOriginOrReceipt($issuer,$actor,$payload,$o['origin_hash'],$this->decode($o['context_json']),self::canonicalKey($issuer,$id));return $o;
    }
}
