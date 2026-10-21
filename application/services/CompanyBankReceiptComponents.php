<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyBankComponentsSchema.php';require_once __DIR__.'/CompanyBankComponentsData.php';
require_once __DIR__.'/CompanyCurrencyUnits.php';require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** Component receipts compose with the canonical writer. They never settle an invoice by themselves. */
final class CompanyBankReceiptComponents
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');}
    public function tables():array{return ['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','invoices','issuers_invoice_documents','company_currency_units','invoicepaymentrecords','company_payment_receipts','company_bank_receipt_components','issuers_audit'];}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function boundary(int $issuer,int $actor,bool $write):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());CompanyBankComponentsSchema::assertValid($this->db,$this->p);$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'invoices','view')||($write&&!$acl->can($issuer,$actor,'payments','create')))throw new DomainException('company_bank_components_access_denied');
    }
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_bank_components_invalid');}
    private static function json(array $data):string{return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}
    private function invoice(int $issuer,int $invoice):array
    {
        $row=$this->rows('SELECT i.id,i.total,i.currency,i.status FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;if(!$row)throw new DomainException('company_bank_components_access_denied');$unit=(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($row['currency']);$row['currency_iso']=$unit['iso_code'];$row['currency_basis']=$unit['basis'];return $row;
    }
    /** Confirms the native receipt's immutable original input; this channel does not support raw reversals. */
    private function nativeReceipt(int $issuer,int $invoice,int $payment,string $key,string $mode):array
    {
        $receipt=$this->rows('SELECT p.invoiceid,p.amount,p.paymentmode,p.paymentmethod,p.date,p.transactionid,p.note,r.input_hash FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'company_payment_receipts r ON r.payment_id=p.id AND r.invoice_id=p.invoiceid WHERE p.id=? AND p.invoiceid=? AND r.issuer_id=? AND r.request_key=? FOR UPDATE',[$payment,$invoice,$issuer,$key]);if(count($receipt)!==1)throw new DomainException('company_bank_components_receipt_review');$row=$receipt[0];if($row['paymentmode']!==$mode)throw new DomainException('company_bank_components_receipt_review');$payload=['invoiceid'=>(int)$row['invoiceid'],'amount'=>CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor((string)$row['amount'])),'paymentmode'=>$row['paymentmode'],'paymentmethod'=>$row['paymentmethod'],'date'=>$row['date'],'transactionid'=>$row['transactionid'],'note'=>$row['note']];if(!hash_equals($row['input_hash'],hash('sha256',json_encode($payload,JSON_THROW_ON_ERROR))))throw new DomainException('company_bank_components_receipt_review');return $row;
    }
    private function decode(int $issuer,int $invoice,array $row,array $parent):CompanyBankComponentsData
    {
        try{$data=json_decode($row['payload_json'],true,16,JSON_THROW_ON_ERROR);if(!is_array($data))throw new DomainException();$dto=CompanyBankComponentsData::fromCaptured($data);}catch(Throwable $e){throw new DomainException('company_bank_components_receipt_review');}
        if((int)$row['issuer_id']!==$issuer||(int)$row['invoice_id']!==$invoice||$row['source_type']!==$dto->sourceType()||$row['currency']!==$data['currency']||$parent['currency_iso']!==$data['currency']||$parent['currency_basis']!==$row['currency_basis']||CompanyInvoiceAmounts::minor((string)$parent['total'])!==CompanyInvoiceAmounts::minor($data['nominal'])||!hash_equals($row['payload_hash'],hash('sha256',$row['payload_json']))||$row['payload_json']!==self::json($dto->data())||!hash_equals($row['payload_hash'],$dto->fingerprint())||!hash_equals($row['evidence_hash'],$data['evidence_hash']))throw new DomainException('company_bank_components_receipt_review');
        foreach(['cash_principal','principal_extinguished','discount','abatement','charges_aggregate','paid','net_observed'] as $field)if($row[$field]===null||CompanyInvoiceAmounts::minor((string)$row[$field])!==CompanyInvoiceAmounts::minor($data[$field]))throw new DomainException('company_bank_components_receipt_review');$header=[$row['request_key'],$issuer,$invoice,(int)$row['payment_id'],$row['source_type'],$row['currency'],$row['currency_basis'],$row['payload_hash'],(int)$row['created_by'],$row['created_at']];if(!hash_equals($row['record_hash'],hash('sha256',self::json($header))))throw new DomainException('company_bank_components_receipt_review');$receipt=$this->nativeReceipt($issuer,$invoice,(int)$row['payment_id'],$row['request_key'],$dto->paymentMode());if(CompanyInvoiceAmounts::minor((string)$receipt['amount'])!==CompanyInvoiceAmounts::minor($dto->cashPrincipal()))throw new DomainException('company_bank_components_receipt_review');return $dto;
    }
    public function capturedInOwned($issuer,$actor,$invoice,string $key):?CompanyBankComponentsData
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);self::key($key);$this->boundary($issuer,$actor,true);$parent=$this->invoice($issuer,$invoice);$row=$this->rows('SELECT * FROM '.$this->p.'company_bank_receipt_components WHERE request_key=? FOR UPDATE',[$key])[0]??null;return $row?$this->decode($issuer,$invoice,$row,$parent):null;
    }
    /** Use before the native write, with the common exact balance helper's locked current balance. */
    public function prepareInOwned($issuer,$actor,$invoice,string $key,CompanyBankComponentsData $dto,int $lockedBalance):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);self::key($key);$this->boundary($issuer,$actor,true);$parent=$this->invoice($issuer,$invoice);$data=$dto->data();if($parent['currency_iso']!==$data['currency']||CompanyInvoiceAmounts::minor((string)$parent['total'])!==CompanyInvoiceAmounts::minor($data['nominal'])||CompanyInvoiceAmounts::minor($data['balance_before'])!==$lockedBalance)throw new DomainException('company_bank_components_balance_invalid');if($this->rows('SELECT request_key FROM '.$this->p.'company_bank_receipt_components WHERE request_key=?',[$key]))throw new DomainException('company_bank_components_receipt_conflict');
    }
    /** Called only by the exact allowlisted source writer, after native receipt/status persistence. */
    public function recordInOwned($issuer,$actor,$invoice,$payment,string $key,CompanyBankComponentsData $dto):void
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$payment=IssuerPolicy::id($payment);self::key($key);$this->boundary($issuer,$actor,true);$parent=$this->invoice($issuer,$invoice);$data=$dto->data();$existing=$this->rows('SELECT * FROM '.$this->p.'company_bank_receipt_components WHERE request_key=? FOR UPDATE',[$key])[0]??null;if($existing){$old=$this->decode($issuer,$invoice,$existing,$parent);if((int)$existing['payment_id']!==$payment||!hash_equals($old->fingerprint(),$dto->fingerprint()))throw new DomainException('company_bank_components_receipt_conflict');return;}
        $receipt=$this->nativeReceipt($issuer,$invoice,$payment,$key,$dto->paymentMode());if($parent['currency_iso']!==$data['currency']||CompanyInvoiceAmounts::minor((string)$parent['total'])!==CompanyInvoiceAmounts::minor($data['nominal'])||CompanyInvoiceAmounts::minor((string)$receipt['amount'])!==CompanyInvoiceAmounts::minor($dto->cashPrincipal())||(int)$parent['status']!==($dto->remaining()==='0.00'?2:3))throw new DomainException('company_bank_components_receipt_review');
        $now=gmdate('Y-m-d H:i:s');$fields=['request_key'=>$key,'issuer_id'=>$issuer,'invoice_id'=>$invoice,'payment_id'=>$payment,'source_type'=>$dto->sourceType(),'currency'=>$data['currency'],'currency_basis'=>$parent['currency_basis']];foreach(['cash_principal','principal_extinguished','discount','abatement','charges_aggregate','paid','net_observed'] as $field)$fields[$field]=$data[$field];$fields+=['payload_json'=>self::json($data),'payload_hash'=>$dto->fingerprint(),'evidence_hash'=>$data['evidence_hash'],'created_by'=>$actor,'created_at'=>$now];$fields['record_hash']=hash('sha256',self::json([$key,$issuer,$invoice,$payment,$fields['source_type'],$fields['currency'],$fields['currency_basis'],$fields['payload_hash'],$actor,$now]));IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_bank_receipt_components (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$saved=$this->rows('SELECT * FROM '.$this->p.'company_bank_receipt_components WHERE request_key=? AND issuer_id=?',[$key,$issuer])[0]??null;if(!$saved||!hash_equals($this->decode($issuer,$invoice,$saved,$parent)->fingerprint(),$dto->fingerprint()))throw new RuntimeException('issuer_bank_components_write_unconfirmed');$meta=self::json(['request_key'=>$key,'invoice_id'=>$invoice,'payment_id'=>$payment,'payload_hash'=>$dto->fingerprint(),'evidence_hash'=>$data['evidence_hash']]);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'company_bank_components_recorded',$issuer,$invoice,$now,$meta]);$audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND metadata_json=?',[$audit,$issuer,$meta]))throw new RuntimeException('issuer_bank_components_write_unconfirmed');
    }
    /** Invoice readers already authorize invoices/view; no new privilege is granted by this helper. */
    public function forInvoiceInOwned($issuer,$actor,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$invoice=IssuerPolicy::id($invoice);$this->boundary($issuer,$actor,false);$parent=$this->invoice($issuer,$invoice);$rows=$this->rows('SELECT * FROM '.$this->p.'company_bank_receipt_components WHERE issuer_id=? AND invoice_id=? ORDER BY payment_id FOR UPDATE',[$issuer,$invoice]);$result=[];foreach($rows as $row)$result[(int)$row['payment_id']]=$this->decode($issuer,$invoice,$row,$parent)->data();return $result;
    }
    /** Final native portal authority; contacts are never treated as staff or passed as arbitrary grants. */
    public function forPortalInOwned(CompanyPortalInvoices $portal,array $session,$issuer,$invoice):array
    {
        $issuer=IssuerPolicy::id($issuer);$invoice=IssuerPolicy::id($invoice);IssuerTransaction::assertOwned($this->db,$this->p,array_values(array_unique(array_merge($this->tables(),$portal->balanceTables()))));CompanyBankComponentsSchema::assertValid($this->db,$this->p);$identity=$portal->balanceIdentityInOwnedTransaction($session,$issuer,$invoice,$this->db,$this->p);$parent=$this->invoice($issuer,$invoice);$rows=$this->rows('SELECT * FROM '.$this->p.'company_bank_receipt_components WHERE issuer_id=? AND invoice_id=? ORDER BY payment_id FOR UPDATE',[$issuer,$invoice]);$result=[];foreach($rows as $row)$result[(int)$row['payment_id']]=$this->decode($issuer,$invoice,$row,$parent)->data();if($identity!==$portal->balanceIdentityInOwnedTransaction($session,$issuer,$invoice,$this->db,$this->p))throw new DomainException('company_portal_invoice_unavailable');return $result;
    }
    public function reductionsInOwned($issuer,$actor,$invoice):int
    {
        $sum=0;foreach($this->forInvoiceInOwned($issuer,$actor,$invoice) as $data){$reduction=CompanyInvoiceAmounts::minor($data['discount'])+CompanyInvoiceAmounts::minor($data['abatement']);if($sum>CompanyInvoiceAmounts::MAX-$reduction)throw new DomainException('company_bank_components_receipt_review');$sum+=$reduction;}return $sum;
    }
}
