<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerPolicy.php';

/** Exact native invoice URL authority. A bearer is neither a staff member nor a contact. */
final class NativeCompanyPublicInvoice
{
    private int $issuer;
    private int $client;
    public function __construct(private $db, private string $p, private int $invoice, private string $hash)
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/D',$p) || $invoice < 1 || !preg_match('/^[a-f0-9]{32}$/D',$hash)) {
            throw new DomainException('native_public_invoice_unavailable');
        }
        $identity=$this->readIdentity();
        $this->issuer=$identity['issuer'];$this->client=$identity['client'];
    }
    public function issuerId():int{return $this->issuer;}
    public function invoiceId():int{return $this->invoice;}
    public function balanceTables():array{return ['issuers_registry','issuers_clients','clients','invoices','issuers_invoice_documents'];}
    private function readIdentity():array
    {
        $rows=IssuerStorage::query($this->db,'SELECT i.id,i.issuer_id,i.clientid,i.hash,i.status FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_registry r ON r.id=i.issuer_id AND r.active=1 JOIN '.$this->p.'clients c ON c.userid=i.clientid AND c.active=1 JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid AND o.owner_issuer_id=i.issuer_id JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=?',[$this->invoice])->result_array();
        if(count($rows)!==1)throw new DomainException('native_public_invoice_unavailable');
        $r=$rows[0];
        if(!is_string($r['hash'])||!hash_equals($this->hash,$r['hash'])||!in_array((int)$r['status'],[1,2,3,4,5],true))throw new DomainException('native_public_invoice_unavailable');
        return ['invoice'=>IssuerPolicy::id($r['id']),'issuer'=>IssuerPolicy::id($r['issuer_id']),'client'=>IssuerPolicy::id($r['clientid'])];
    }
    /** No arbitrary session/grant is accepted; empty context marks this concrete URL authority. */
    public function assertIdentity(int $issuer,int $invoice,$db,string $prefix):array
    {
        if($db!==$this->db||$prefix!==$this->p||$issuer!==$this->issuer||$invoice!==$this->invoice)throw new DomainException('native_public_invoice_unavailable');
        $identity=$this->readIdentity();
        if($identity!==['invoice'=>$this->invoice,'issuer'=>$this->issuer,'client'=>$this->client])throw new DomainException('native_public_invoice_unavailable');
        return $identity;
    }
    public function balanceIdentityInOwnedTransaction(array $context,int $issuer,int $invoice,$db,string $prefix):array
    {
        if($context!==[])throw new DomainException('native_public_invoice_unavailable');
        IssuerTransaction::assertOwned($db,$prefix,$this->balanceTables());
        return $this->assertIdentity($issuer,$invoice,$db,$prefix);
    }
    public function document():InvoiceIssuerDocumentData
    {
        require_once __DIR__.'/issuers/InvoiceIssuerDocuments.php';
        require_once __DIR__.'/issuers/InvoiceIssuerDocumentData.php';
        $this->assertIdentity($this->issuer,$this->invoice,$this->db,$this->p);
        $documents=new InvoiceIssuerDocuments($this->db,$this->p,0,fn($issuer,$actor,$cap)=>$actor===0&&$issuer===$this->issuer&&in_array($cap,['view_invoices','render_invoices'],true),$this,[]);
        return InvoiceIssuerDocumentData::fromInvoice($documents,$this->invoice,$this->issuer);
    }
}
