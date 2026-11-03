<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCatalogUi.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanyInvoiceDrafts.php';
/** Concrete read authority for the ORIGINAL invoice editor's catalogue requests. */
final class NativeCompanyInvoiceCatalogLookup
{
    public function __construct(private $db,private string $p,private int $i,private int $a,private string $invoice,private string $revision)
    {if($invoice===''&&$revision!=='')throw new DomainException('company_invoice_stale');$this->assertCurrent();}
    public function assertCurrent():void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($this->i,$this->a,'invoices','view')||!$acl->can($this->i,$this->a,'invoices',$this->invoice===''?'create':'edit'))throw new DomainException('company_invoice_access_denied');
        if($this->invoice==='')return;
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->i,$this->a,'invoice',$this->invoice,'edit');
        if($ref['source_kind']==='company_invoice_draft'){$doc=(new CompanyInvoiceDrafts($this->db,$this->p))->document($this->i,$this->a,$ref['source_id']);if($doc['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');$expected=CompanyInvoiceDraftsInput::revision($this->revision);if($doc['revision']!==$expected)throw new DomainException('company_invoice_draft_stale');return;}
        else $actual=(new CompanyInvoices($this->db,$this->p))->revision($this->i,$this->a,$ref['source_id']);
        if(!preg_match('/^[a-f0-9]{64}$/D',$this->revision)||!hash_equals($this->revision,$actual))throw new DomainException('company_invoice_stale');
    }
    public function assertContext($db,string $p,int $i,int $a):void{if($db!==$this->db||$p!==$this->p||$i!==$this->i||$a!==$this->a)throw new DomainException('native_invoice_editor_mismatch');$this->assertCurrent();}
    public function parent(){return$this->invoice===''?null:$this->invoice;}
    public function item($id):array{$this->assertCurrent();$row=(new NativeCompanyCatalogUi($this->db,$this->p,$this->i,$this->a))->itemForInvoiceEditor($id);$this->assertCurrent();return$row;}
    public function search(string $q):array{$this->assertCurrent();$rows=(new NativeCompanyCatalogUi($this->db,$this->p,$this->i,$this->a))->searchForInvoiceEditor($q);$this->assertCurrent();return$rows;}
    public function fields($id):array{return(new NativeCompanyCustomFields($this->db,$this->p))->invoiceCatalogItem($this->i,$this->a,$id,$this);}
    public function __serialize():array{throw new LogicException('native_invoice_editor_not_serializable');}
}
