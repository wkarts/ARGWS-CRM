<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCatalogUi.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanySales.php';

/** Live catalogue authority bound to one original sales editor and its exact revision. */
final class NativeCompanySalesCatalogLookup
{
    public function __construct(private $db,private string $p,private int $i,private int $a,private string $kind,private string $document,private string $revision)
    {
        if(!in_array($kind,['estimates','proposals'],true)||($document===''&&$revision!==''))throw new DomainException('native_sales_editor_invalid');
        $this->assertCurrent();
    }
    public function assertCurrent():void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($this->i,$this->a,$this->kind,'view')||!$acl->can($this->i,$this->a,$this->kind,$this->document===''?'create':'edit'))throw new DomainException('native_sales_editor_denied');
        if($this->document==='')return;
        $type=$this->kind==='estimates'?'estimate':'proposal';$ref=NativeCompanyResourceId::decode($type,$this->document);
        if($ref['source_kind']!=='company_'.$type)throw new DomainException('native_sales_editor_denied');
        $row=(new CompanySales($this->db,$this->p))->document($this->i,$this->a,$ref['source_id']);
        if($row['kind']!==$this->kind||$row['state']!=='open'||!preg_match('/^[a-f0-9]{64}$/D',$this->revision)||!hash_equals($row['revision_token'],$this->revision))throw new DomainException('company_sales_stale');
    }
    public function assertContext($db,string $p,int $issuer,int $actor):void
    {if($db!==$this->db||$p!==$this->p||$issuer!==$this->i||$actor!==$this->a)throw new DomainException('native_sales_editor_mismatch');$this->assertCurrent();}
    public function kind():string{return$this->kind;}
    public function parent():?string{return$this->document===''?null:$this->document;}
    public function item($id):array
    {$this->assertCurrent();$row=(new NativeCompanyCatalogUi($this->db,$this->p,$this->i,$this->a))->itemForSalesEditor($this->kind,$id);$this->assertCurrent();return$row;}
    public function search(string $query):array
    {$this->assertCurrent();$rows=(new NativeCompanyCatalogUi($this->db,$this->p,$this->i,$this->a))->searchForSalesEditor($this->kind,$query);$this->assertCurrent();return$rows;}
    public function fields($id):array
    {return(new NativeCompanyCustomFields($this->db,$this->p))->salesCatalogItem($this->i,$this->a,$id,$this);}
    public function __serialize():array{throw new LogicException('native_sales_editor_not_serializable');}
    public function __unserialize(array $data):void{throw new LogicException('native_sales_editor_not_serializable');}
}
