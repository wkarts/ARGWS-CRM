<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceBalance.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';

/** Immutable issue evidence and current invoice position are deliberately separate. No mutation authority. */
final class CompanyBillingInvoiceLifecycle
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','invoices','issuers_invoice_documents','company_invoice_requests','currencies','company_currency_units'],(new CompanyInvoiceBalance($this->db,$this->p))->tables())));}
    public function inOwned($issuer,$actor,$invoice,string $key,int $receiptActor,array $originalPayload):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$receiptActor=IssuerPolicy::id($receiptActor);if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_invoice_invalid_input');IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);if(!$acl->can($issuer,$actor,'invoices','view'))throw new DomainException('company_invoice_access_denied');
        $row=IssuerStorage::query($this->db,'SELECT i.id,i.clientid,i.currency,i.date,i.duedate,i.status,r.actor_id,r.input_hash,d.formatted_number FROM '.$this->p.'invoices i JOIN '.$this->p.'company_invoice_requests r ON r.invoice_id=i.id AND r.issuer_id=i.issuer_id JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? AND r.request_key=? FOR UPDATE',[$invoice,$issuer,$key])->row_array();
        $amounts=CompanyInvoiceAmounts::calculate($originalPayload['items']??[],array_intersect_key($originalPayload,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));$original=array_merge($amounts,['client_id'=>IssuerPolicy::id($originalPayload['client_id']??null),'date'=>$originalPayload['date']??null,'due_date'=>$originalPayload['due_date']??null,'currency'=>IssuerPolicy::id($originalPayload['currency']??null),'note'=>$originalPayload['note']??null]);if(!$row||(int)$row['actor_id']!==$receiptActor||(int)$row['clientid']!==$original['client_id']||(int)$row['currency']!==$original['currency']||!hash_equals($row['input_hash'],hash('sha256',json_encode($original,JSON_THROW_ON_ERROR))))throw new RuntimeException('issuer_billing_invoice_unconfirmed');
        $position=(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,$invoice);$unit=(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($row['currency']);if(!$acl->can($issuer,$actor,'invoices','view'))throw new DomainException('company_invoice_access_denied');$status=(int)$row['status'];
        return ['invoice_id'=>$invoice,'formatted_number'=>$row['formatted_number'],'status'=>$status,'date'=>$row['date'],'due_date'=>$row['duedate'],'currency'=>$unit['iso_code'],'currency_basis'=>$unit['basis'],'total'=>$position['total'],'original_total'=>$amounts['total'],'amount_changed'=>$position['total']!==$amounts['total'],'received'=>$position['received'],'applied_credit'=>$position['applied_credit'],'noncash_reductions'=>$position['noncash_reductions'],'receivable'=>$position['receivable'],'collectible_balance'=>in_array($status,[5,6],true)?'0.00':$position['receivable'],'binding_basis'=>'immutable_issue_receipt_current_owned_position'];
    }
}
