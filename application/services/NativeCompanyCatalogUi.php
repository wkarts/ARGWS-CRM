<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCatalog.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyCatalogLegacy.php';

final class NativeCompanyCatalogUi
{
    private NativeCompanyCatalog $owned;
    private ?NativeCompanyCatalogLegacy $legacy=null;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private $ci=null){$this->owned=new NativeCompanyCatalog($db,$p,$issuer,$actor);if(NativeCompanyAuxiliaryLegacy::eligible($db,$p,$issuer,$actor)){
            foreach(['company_items','company_taxes','company_item_groups']as$table)if(IssuerStorage::query($db,'SELECT id FROM '.$p.$table.' WHERE issuer_id=? LIMIT 1',[$issuer])->row_array())throw new DomainException('company_catalog_primary_owned_migration_required');
            $this->legacy=new NativeCompanyCatalogLegacy($db,$p,$issuer,$actor);
        }}
    private function source(string $type,$id):array{$ref=NativeCompanyResourceId::decode($type,$id);if($ref['source_kind']===$type&&$this->legacy===null)throw new DomainException('company_catalog_denied');return$ref;}
    private static function sortRows(array $rows,string $field):array{usort($rows,static fn($a,$b)=>strcmp((string)$a[$field],(string)$b[$field])?:strcmp((string)$a['id'],(string)$b['id']));return$rows;}
    private static function id(string $type,$id):int
    {
        $row=NativeCompanyResourceId::decode($type,$id);if($row['source_kind']!=='company_'.$type)throw new DomainException('company_catalog_denied');return$row['source_id'];
    }
    private static function encoded(string $type,$id):string{return NativeCompanyResourceId::encode('company_'.$type,$id);}
    private static function project(string $type,array $row):array
    {
        $row['source_id']=$row['id'];$row['source_kind']='company_'.$type;$row['id']=self::encoded($type,$row['id']);
        if($type==='item'){
            $row['itemid']=$row['id'];foreach(['tax','tax2','taxid','taxid_2']as$key)if(isset($row[$key])&&$row[$key]!=='0'&&$row[$key]!==0)$row[$key]=self::encoded('tax',$row[$key]);
            if(!empty($row['group_id']))$row['group_id']=self::encoded('item_group',$row['group_id']);
        }
        return$row;
    }
    public function allowed(string $feature,string $cap):bool{return$this->owned->allowed($feature,$cap);}
    public function currencies():array{return$this->owned->currencies();}
    public function currency($id):array{return$this->owned->currency($id);}
    public function baseCurrency():array{return$this->owned->baseCurrency();}
    public function taxes():array{return self::sortRows(array_merge($this->legacy?->taxes()??[],array_map(static fn($r)=>self::project('tax',$r),$this->owned->taxes())),'name');}
    public function groups():array{return self::sortRows(array_merge($this->legacy?->groups()??[],array_map(static fn($r)=>self::project('item_group',$r),$this->owned->groups())),'name');}
    public function item($id):array{$ref=$this->source('item',$id);return$ref['source_kind']==='item'?$this->legacy->item($ref['source_id']):self::project('item',$this->owned->item($ref['source_id']));}
    public function search(string $q):array{$rows=$this->owned->search($q);foreach($rows as&$r){$r['id']=self::encoded('item',$r['id']);$r['source_kind']='company_item';}unset($r);return array_slice(self::sortRows(array_merge($this->legacy?->search($q)??[],$rows),'name'),0,100);}
    public function itemTable(int $start,int $length,string $q=''):array{
        if($this->legacy===null){$result=$this->owned->itemTable($start,$length,$q);$result['rows']=array_map(static fn($r)=>self::project('item',$r),$result['rows']);return$result;}
        if($start<0||$start>100000||$length<1||$length>100)throw new DomainException('company_catalog_invalid');
        $out=['total'=>0,'filtered'=>0,'rows'=>[]];
        foreach([$this->legacy,$this->owned] as$source){$first=true;for($offset=0;$offset<$start+$length;$offset+=100){$page=$source->itemTable($offset,min(100,$start+$length-$offset),$q);if($first){$out['total']+=$page['total'];$out['filtered']+=$page['filtered'];$first=false;}foreach($page['rows']as$row)$out['rows'][]=$source===$this->owned?self::project('item',$row):$row;if(count($page['rows'])<min(100,$start+$length-$offset))break;}}
        $out['rows']=array_slice(self::sortRows($out['rows'],'description'),$start,$length);return$out;
    }
    /** Native get/get_grouped readers, no new UI or implicit issuer selection. */
    public function items():array
    {
        $rows=[];for($offset=0;;$offset+=100){$page=$this->itemTable($offset,100);foreach($page['rows']as$row)$rows[]=$row;if(count($rows)>=$page['filtered'])break;if(!$page['rows'])throw new RuntimeException('issuer_catalog_read_inconsistent');}
        return$rows;
    }
    public function groupedItems():array
    {
        $groups=[];foreach($this->items()as$row){$key=empty($row['group_id'])?0:$row['group_id'];$groups[$key][]=$row;}return$groups;
    }
    public function validateItemInput(array $input):array
    {
        if($this->legacy!==null)return$this->legacy->validateItemInput($input);
        foreach(['tax'=>'tax','tax2'=>'tax','group_id'=>'item_group']as$key=>$type)if(isset($input[$key])&&$input[$key]!==''&&$input[$key]!==0&&$input[$key]!=='0')$input[$key]=self::id($type,$input[$key]);
        $result=$this->owned->validateItemInput($input);foreach(['tax'=>'tax','tax2'=>'tax','group_id'=>'item_group']as$key=>$type)if($result[$key]!==null)$result[$key]=self::encoded($type,$result[$key]);return$result;
    }
    public function saveItem(array $input,$id=null,$revision=null):string
    {
        if($this->legacy!==null&&($id===null||$id===''||$this->source('item',$id)['source_kind']==='item'))return(string)$this->legacy->saveItem($this->ci,$input,$id,$revision);
        foreach(['tax'=>'tax','tax2'=>'tax','group_id'=>'item_group']as$key=>$type)if(isset($input[$key])&&$input[$key]!==''&&$input[$key]!==0&&$input[$key]!=='0')$input[$key]=self::id($type,$input[$key]);
        return self::encoded('item',$this->owned->saveItem($input,$id===null||$id===''?null:self::id('item',$id),$revision));
    }
    public function saveGroup(array $input,$id=null,$revision=null):string{if($this->legacy!==null&&($id===null||$id===''))return(string)$this->legacy->createSimple($this->ci,'group',$input);if($id!==null&&$id!==''&&$this->source('item_group',$id)['source_kind']==='item_group'){$this->legacy->mutateSimple($this->ci,'group',$id,$revision,$input);return(string)$id;}return self::encoded('item_group',$this->owned->saveGroup($input,$id===null||$id===''?null:self::id('item_group',$id),$revision));}
    public function saveTax(array $input,$id=null,$revision=null):string{if($this->legacy!==null&&($id===null||$id===''))return(string)$this->legacy->createSimple($this->ci,'tax',$input);if($id!==null&&$id!==''&&$this->source('tax',$id)['source_kind']==='tax'){$this->legacy->mutateSimple($this->ci,'tax',$id,$revision,$input);return(string)$id;}return self::encoded('tax',$this->owned->saveTax($input,$id===null||$id===''?null:self::id('tax',$id),$revision));}
    public function delete(string $kind,$id,$revision):void{if($kind==='item'){$this->deleteItems([(string)$id=>$revision]);return;}$type=$kind==='group'?'item_group':$kind;if($kind!=='item'&&$this->source($type,$id)['source_kind']===$type){$this->legacy->mutateSimple($this->ci,$kind,$id,$revision,null);return;}$this->owned->delete($kind,self::id($type,$id),$revision);}
    public function deleteItems(array $revisions):void{
        if(!$revisions||count($revisions)>200)throw new DomainException('company_catalog_invalid');$sources=[];
        foreach($revisions as$id=>$revision)$sources[]=['ref'=>$this->source('item',$id),'revision'=>$revision];
        $tables=array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit','company_items'],(new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),$this->legacy?->itemTables()??[])));
        IssuerTransaction::run($this->db,$this->p,$tables,function()use($sources):void{foreach($sources as$row){$ref=$row['ref'];if($ref['source_kind']==='item')$this->legacy->deleteItemInOwned($this->ci,$ref['source_id'],$row['revision']);else$this->owned->deleteItemInOwned($ref['source_id'],$row['revision']);}});
    }
    public function copyItem($id,$revision):string{if($this->source('item',$id)['source_kind']==='item')return(string)$this->legacy->copyItem($this->ci,$id,$revision);return self::encoded('item',$this->owned->copyItem(self::id('item',$id),$revision));}
    /** Native default_tax wire format remains name|taxrate, never catalogue IDs. */
    public function validateTaxSelections(array $selections):array
    {
        if(!array_is_list($selections)||count($selections)>1000)throw new DomainException('company_catalog_invalid_tax_selection');
        $catalog=[];foreach($this->taxes()as$row){$fields=NativeCompanyCatalog::simpleFields(['name'=>(string)$row['name'],'taxrate'=>(string)$row['taxrate']],true);$key=$fields['name'].'|'.$fields['taxrate'];$catalog[$key]=($catalog[$key]??0)+1;}
        $out=[];$seen=[];foreach($selections as$value){
            if(!is_string($value)||strlen($value)>220||substr_count($value,'|')!==1)throw new DomainException('company_catalog_invalid_tax_selection');
            [$name,$rate]=explode('|',$value,2);$fields=NativeCompanyCatalog::simpleFields(['name'=>$name,'taxrate'=>$rate],true);$canonical=$fields['name'].'|'.$fields['taxrate'];
            if(($catalog[$canonical]??0)!==1||isset($seen[$canonical]))throw new DomainException('company_catalog_invalid_tax_selection');
            $seen[$canonical]=true;$out[]=$canonical;
        }
        return$out;
    }
    public function taxDropdown(string $name,$selected,array $ownedHistoricalTaxes=[]):string{foreach($this->legacy?->taxes()??[]as$tax)$ownedHistoricalTaxes[]=['taxname'=>$tax['name'],'taxrate'=>$tax['taxrate']];return$this->owned->taxDropdown($name,$selected,$ownedHistoricalTaxes);}
}
