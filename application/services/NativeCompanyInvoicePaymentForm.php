<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoicePreviewData.php';
require_once __DIR__.'/NativeCompanyPaymentModes.php';
/** Scoped data for the existing invoice payment form; does not admit its route. */
final class NativeCompanyInvoicePaymentForm
{
    public function __construct(private $db,private string $prefix){}
    public function read(int $issuer,int $actor,$invoice):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->prefix);
        if(!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'payments','create'))throw new DomainException('company_payment_access_denied');
        $preview=(new NativeCompanyInvoicePreviewData($this->db,$this->prefix))->read($issuer,$actor,$invoice);
        $id=$preview['identity']['invoice_id'];
        $row=IssuerStorage::query($this->db,'SELECT allowed_payment_modes FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$id,$issuer])->row_array();
        if(!$row||!is_string($row['allowed_payment_modes']))throw new DomainException('native_payment_mode_unavailable');
        $allowed=@unserialize($row['allowed_payment_modes'],['allowed_classes'=>false]);if(!is_array($allowed)||!array_is_list($allowed))throw new DomainException('native_payment_mode_unavailable');
        foreach($allowed as$value)if(!is_string($value)&&!is_int($value))throw new DomainException('native_payment_mode_unavailable');
        $allowed=array_map('strval',$allowed);$modes=array_values(array_filter((new NativeCompanyPaymentModes($this->db,$this->prefix))->listing($issuer,$actor,'invoices',false),static fn($mode)=>in_array($mode['id'],$allowed,true)));
        $after=IssuerStorage::query($this->db,'SELECT allowed_payment_modes FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=?',[$id,$issuer])->row_array();
        if($after!==$row||!$acl->can($issuer,$actor,'invoices','view')||!$acl->can($issuer,$actor,'payments','create'))throw new DomainException('company_payment_access_denied');
        return ['invoice'=>$preview['document'],'revision'=>$preview['revision'],'balance'=>$preview['balance'],'payments'=>$preview['payments'],'payment_modes'=>$modes,'request_key'=>bin2hex(random_bytes(32))];
    }
}
