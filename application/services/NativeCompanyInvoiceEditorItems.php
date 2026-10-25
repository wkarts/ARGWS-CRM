<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceDrafts.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Typed, live-authorized rows for the original invoice editor. IDs are persisted identities, never positions. */
final class NativeCompanyInvoiceEditorItems
{
    private function __construct(private $db,private string $p,private int $issuer,private int $actor,private $parent,private $revision,private array $items){}
    public static function load($db,string $p,int $issuer,int $actor,$parent=null):self
    {
        $fields=new NativeCompanyCustomFields($db,$p);$fields->invoiceEditorItemDefinitions($issuer,$actor,$parent,['active'=>1]);$items=[];$revision=null;
        if($parent!==null){
            $ref=NativeCompanyResourceId::decode('invoice',$parent);$parent=$ref['canonical_id'];
            if($ref['source_kind']==='company_invoice_draft'){
                $drafts=new CompanyInvoiceDrafts($db,$p);
                [$revision,$items]=IssuerTransaction::run($db,$p,$drafts->tables(),function()use($db,$p,$issuer,$actor,$ref,$drafts){
                    $draft=$drafts->documentInOwned($issuer,$actor,$ref['source_id']);if($draft['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');
                    $rows=array_values(array_filter((new NativeCompanyInvoiceDraftLines($db,$p))->assertInOwned($ref['source_id']),static fn($r)=>$r['draft_revision']===$draft['revision']));$items=[];
                    foreach($rows as$row)$items[]=['id'=>NativeCompanyResourceId::encode('company_invoice_draft_line',$row['id'])]+json_decode($row['line_json'],true,32,JSON_THROW_ON_ERROR);
                    return[$draft['revision'],$items];
                });
            }else{
                $writer=new CompanyInvoices($db,$p);$revision=$writer->revision($issuer,$actor,$ref['source_id']);$items=$writer->previewItems($issuer,$actor,$ref['source_id']);
            }
        }
        $result=new self($db,$p,$issuer,$actor,$parent,$revision,$items);$result->assertCurrent();return$result;
    }
    public function assertCurrent():void
    {
        (new NativeCompanyCustomFields($this->db,$this->p))->invoiceEditorItemDefinitions($this->issuer,$this->actor,$this->parent,['active'=>1]);
        if($this->parent===null)return;
        $ref=NativeCompanyResourceId::decode('invoice',$this->parent);
        if($ref['source_kind']==='company_invoice_draft'){$row=(new CompanyInvoiceDrafts($this->db,$this->p))->document($this->issuer,$this->actor,$ref['source_id']);if($row['state']!=='open'||$row['revision']!==$this->revision)throw new DomainException('company_invoice_draft_stale');}
        elseif(!hash_equals($this->revision,(new CompanyInvoices($this->db,$this->p))->revision($this->issuer,$this->actor,$ref['source_id'])))throw new DomainException('company_invoice_stale');
    }
    public function assertContext($db,string $p,int $issuer,int $actor):void
    {if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('native_invoice_editor_mismatch');$this->assertCurrent();}
    public function definitions():array{$this->assertCurrent();return(new NativeCompanyCustomFields($this->db,$this->p))->invoiceEditorItemDefinitions($this->issuer,$this->actor,$this->parent,['active'=>1]);}
    public function rows():array
    {
        $this->assertCurrent();$out=[];foreach($this->items as$item){$taxes=[];foreach($item['taxes']as$tax)$taxes[]=['taxname'=>$tax['name'].'|'.$tax['rate'],'taxrate'=>$tax['rate']];$out[]=$item+['taxname'=>$taxes,'long_description'=>'','unit'=>''];}return$out;
    }
    public function assertItem(array $item):void
    {foreach($this->rows()as$row)if($row===$item)return;throw new DomainException('native_invoice_editor_mismatch');}
    public function taxDropdown(string $name,array $item):string
    {
        $this->assertItem($item);require_once __DIR__.'/NativeCompanyCatalogUi.php';
        $html=(new NativeCompanyCatalogUi($this->db,$this->p,$this->issuer,$this->actor))->taxDropdown($name,array_column($item['taxname'],'taxname'),$item['taxname']);
        $this->assertCurrent();return$html;
    }
    public function __serialize():array{throw new LogicException('native_invoice_editor_not_serializable');}
    public function __unserialize(array $data):void{throw new LogicException('native_invoice_editor_not_serializable');}
}
