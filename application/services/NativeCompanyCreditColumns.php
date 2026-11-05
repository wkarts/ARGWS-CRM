<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';

/** Credit-note fields retain their own source namespace and authorized definitions. */
final class NativeCompanyCreditColumns
{
    private NativeCompanyCustomFields $fields;
    private array $definitions;
    private bool $primary;
    public function __construct(private $db, private string $p, private int $issuer, private int $actor)
    {
        $this->fields=new NativeCompanyCustomFields($db,$p);
        $this->definitions=$this->fields->definitions($issuer,$actor,'credit_note',['active'=>1]);
        $this->primary=(new IssuerResourceAccess($db,$p))->primaryId()===$issuer;
    }
    public function definitions(bool $tableOnly=false): array
    {
        $this->recheck();
        return $tableOnly?array_values(array_filter($this->definitions,static fn($d)=>(int)$d['show_on_table']===1)):$this->definitions;
    }
    public function recheck(): void
    {
        if ($this->fields->definitions($this->issuer,$this->actor,'credit_note',['active'=>1])!==$this->definitions||((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$this->issuer)!==$this->primary) throw new DomainException('native_credit_columns_changed');
    }
    public function expression($fieldId): array
    {
        $definition=null;
        foreach ($this->definitions as $row) if ((string)$row['id']===(string)$fieldId) { $definition=$row; break; }
        if (!$definition) throw new DomainException('native_credit_filter_invalid');
        $params=[$this->issuer,$definition['field_source_kind'],$definition['source_field_id'],'credit_note'];
        $sql='(SELECT cv.value FROM '.$this->p.'company_custom_field_values cv WHERE cv.issuer_id=? AND cv.field_source_kind=? AND cv.field_id=? AND cv.source_kind=? AND cv.source_id=c.id)';
        if ($this->primary&&$definition['field_source_kind']==='custom_field') {
            $sql='COALESCE('.$sql.',(SELECT lv.value FROM '.$this->p.'customfieldsvalues lv WHERE lv.fieldid=? AND lv.fieldto=? AND lv.relid=c.id))';
            $params[]=$definition['source_field_id']; $params[]='credit_note';
        }
        return ['sql'=>$sql,'params'=>$params,'definition'=>$definition];
    }
    public function values(int $id): array
    {
        $values=$this->fields->values($this->issuer,$this->actor,'credit_note',$id);
        $this->recheck(); return $values;
    }
}
