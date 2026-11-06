<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCustomFields.php';

/** Credit-note fields retain their own source namespace and authorized definitions. */
final class NativeCompanyCreditColumns
{
    private NativeCompanyCustomFields $fields;
    private array $definitions;
    private bool $primary;
    private bool $administrative;
    public function __construct(private $db, private string $p, private int $issuer, private int $actor)
    {
        $this->fields=new NativeCompanyCustomFields($db,$p);
        $this->definitions=$this->fields->definitions($issuer,$actor,'credit_note',['active'=>1]);
        $this->primary=(new IssuerResourceAccess($db,$p))->primaryId()===$issuer;
        $this->administrative=(new IssuerResourceAccess($db,$p))->administrative($issuer,$actor);
    }
    public function definitions(bool $tableOnly=false): array
    {
        $this->recheck();
        return $tableOnly?array_values(array_filter($this->definitions,static fn($d)=>(int)$d['show_on_table']===1)):$this->definitions;
    }
    public function recheck(): void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if ($this->fields->definitions($this->issuer,$this->actor,'credit_note',['active'=>1])!==$this->definitions||($acl->primaryId()===$this->issuer)!==$this->primary||$acl->administrative($this->issuer,$this->actor)!==$this->administrative) throw new DomainException('native_credit_columns_changed');
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
        if(!$this->administrative){
            $connection=$this->db->pdo??$this->db->conn_id??null;$sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
            // A later definition change cannot reveal a value captured as administrative.
            $private=$sqlite?"EXISTS(SELECT 1 FROM json_each(d.items_json,'$.native_snapshot.custom_fields') cf_private WHERE json_extract(cf_private.value,'$.id')=? AND json_extract(cf_private.value,'$.only_admin')=1)":"JSON_CONTAINS(JSON_EXTRACT(d.items_json,'$.native_snapshot.custom_fields'),JSON_OBJECT('id',?,'only_admin',1))";
            $sql="(CASE WHEN ".$private." THEN '' ELSE ".$sql.' END)';$params=array_merge([(string)$definition['id']],$params);
        }
        return ['sql'=>$sql,'params'=>$params,'definition'=>$definition];
    }
    public function values(int $id,?array $document=null): array
    {
        $values=$this->fields->values($this->issuer,$this->actor,'credit_note',$id);
        if(isset($document['metadata']))$values=array_intersect_key($values,array_fill_keys(array_column($document['metadata']['custom_fields'],'id'),true));
        $this->recheck(); return $values;
    }
}
