<?php

declare(strict_types=1);
require_once __DIR__.'/CompanySales.php';
require_once __DIR__.'/NativeCompanySalesCatalogLookup.php';

/** The canonical sales snapshot owns ordered lines. No position is accepted as a catalogue or invoice-item identity. */
final class NativeCompanySalesEditorItems
{
    private function __construct(private $db,private string $p,private int $issuer,private int $actor,private string $kind,private string $parent,private string $revision,private array $items,private array $values){}
    public static function load($db,string $p,int $issuer,int $actor,string $kind,string $parent=''):self
    {
        $items=[];$values=[];$revision='';
        if($parent!==''){
            $type=$kind==='estimates'?'estimate':'proposal';$ref=NativeCompanyResourceId::decode($type,$parent);
            if($ref['source_kind']!=='company_'.$type)throw new DomainException('native_sales_editor_denied');
            $review=(new CompanySales($db,$p))->review($issuer,$actor,$ref['source_id'],'edit');
            if($review['kind']!==$kind)throw new DomainException('native_sales_editor_denied');
            $revision=$review['source_revision'];$items=$review['payload']['items'];$values=$review['payload']['native_item_fields']['values']??array_fill(0,count($items),[]);
        }
        $result=new self($db,$p,$issuer,$actor,$kind,$parent,$revision,$items,$values);$result->assertCurrent();
        $definitions=$result->definitions();$known=array_fill_keys(array_map(static fn($d)=>(string)$d['id'],$definitions),true);
        foreach($values as$line)if(array_diff_key($line,$known))throw new DomainException('native_sales_item_fields_hidden');
        return$result;
    }
    public function assertCurrent():void
    {new NativeCompanySalesCatalogLookup($this->db,$this->p,$this->issuer,$this->actor,$this->kind,$this->parent,$this->revision);}
    public function assertContext($db,string $p,int $issuer,int $actor):void
    {if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('native_sales_editor_mismatch');$this->assertCurrent();}
    public function definitions():array
    {$this->assertCurrent();return(new NativeCompanyCustomFields($this->db,$this->p))->salesItemDefinitions($this->issuer,$this->actor,$this->kind,$this->parent===''?'create':'edit',['active'=>1]);}
    public function rows():array
    {
        $this->assertCurrent();$out=[];
        foreach($this->items as$position=>$item){$taxes=[];foreach($item['taxes']as$tax)$taxes[]=['taxname'=>$tax['name'].'|'.$tax['rate'],'taxrate'=>$tax['rate']];$out[]=$item+['native_position'=>$position,'taxname'=>$taxes,'long_description'=>'','unit'=>'','is_optional'=>0,'is_selected'=>1];}
        return$out;
    }
    public function assertItem(array $item):void
    {foreach($this->rows()as$row)if($row===$item)return;throw new DomainException('native_sales_editor_mismatch');}
    public function values(array $item):array
    {
        $this->assertItem($item);$values=$this->values[$item['native_position']]??[];$out=[];
        // A newly required definition must remain editable on an older document.
        // Validate only the values that exist while rendering; the writer enforces all required fields.
        foreach($this->definitions()as$definition){
            $id=(string)$definition['id'];if(!array_key_exists($id,$values))continue;
            $definition['required']=0;$out[$id]=NativeCompanyCustomFields::value($definition,$values[$id]);
        }
        $this->assertCurrent();return$out;
    }
    public function taxDropdown(string $name,array $item):string
    {
        $this->assertItem($item);$html=(new NativeCompanyCatalogUi($this->db,$this->p,$this->issuer,$this->actor))->taxDropdown($name,array_column($item['taxname'],'taxname'),$item['taxname']);$this->assertCurrent();return$html;
    }
    public function __serialize():array{throw new LogicException('native_sales_editor_not_serializable');}
    public function __unserialize(array $data):void{throw new LogicException('native_sales_editor_not_serializable');}
}
