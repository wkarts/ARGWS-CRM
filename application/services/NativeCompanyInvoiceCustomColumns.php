<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Authorized field catalogue and fixed SQL projection for native invoice table columns. */
final class NativeCompanyInvoiceCustomColumns
{
    private NativeCompanyCustomFields $fields;
    private array $definitions;
    private bool $primary;
    public function __construct(private $db,private string $prefix,private int $issuer,private int $actor)
    {
        $this->fields=new NativeCompanyCustomFields($db,$prefix);
        $this->definitions=$this->fields->definitions($issuer,$actor,'invoice',['active'=>1]);
        $this->primary=(new IssuerResourceAccess($db,$prefix))->primaryId()===$issuer;
    }
    public function definitions(bool $tableOnly=false):array
    {
        $this->recheck();return $tableOnly?array_values(array_filter($this->definitions,static fn($d)=>(int)$d['show_on_table']===1)):$this->definitions;
    }
    public function recheck():void
    {
        if($this->fields->definitions($this->issuer,$this->actor,'invoice',['active'=>1])!==$this->definitions
            ||((new IssuerResourceAccess($this->db,$this->prefix))->primaryId()===$this->issuer)!==$this->primary)throw new DomainException('native_company_invoice_columns_changed');
    }
    /** Alias i is server-owned. Every provenance key is bound, never a submitted field name. */
    public function expression($fieldId,string $sourceKind='invoice'):array
    {
        if(!in_array($sourceKind,['invoice','company_invoice_draft'],true))throw new DomainException('native_custom_field_denied');
        $sourceColumn=$sourceKind==='invoice'?'i.id':'i.source_id';
        $definition=null;foreach($this->definitions as$d)if((string)$d['id']===(string)$fieldId){$definition=$d;break;}
        if(!$definition)throw new DomainException('native_custom_field_denied');
        $params=[$this->issuer,$definition['field_source_kind'],$definition['source_field_id'],$sourceKind];
        $sql='(SELECT cv.value FROM '.$this->prefix.'company_custom_field_values cv WHERE cv.issuer_id=? AND cv.field_source_kind=? AND cv.field_id=? AND cv.source_kind=? AND cv.source_id='.$sourceColumn.')';
        if($sourceKind==='invoice'&&$this->primary&&$definition['field_source_kind']==='custom_field'){
            $sql='COALESCE('.$sql.',(SELECT lv.value FROM '.$this->prefix.'customfieldsvalues lv WHERE lv.fieldid=? AND lv.fieldto=? AND lv.relid=i.id))';
            $params[]=$definition['source_field_id'];$params[]='invoice';
        }
        return ['sql'=>$sql,'params'=>$params,'definition'=>$definition];
    }
    /** Preserve the canonical service's value policy and explicit source namespace. */
    public function values($invoiceId):array
    {
        $values=$this->fields->values($this->issuer,$this->actor,'invoice',$invoiceId);$this->recheck();return$values;
    }
}
