<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCatalogUi.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanyInvoiceAmounts.php';

/** Issuing a credit authorizes a catalogue selection, never mutation of an emitted document. */
final class NativeCompanyCreditCatalogLookup
{
    public function __construct(private $db,private string $p,private int $issuer,private int $actor){$this->assertCurrent();}
    public function assertCurrent():void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($this->issuer,$this->actor,'credit_notes','view')||!$acl->can($this->issuer,$this->actor,'credit_notes','create'))throw new DomainException('native_credit_editor_denied');
    }
    public function assertContext($db,string $p,int $issuer,int $actor):void
    {if($db!==$this->db||$p!==$this->p||$issuer!==$this->issuer||$actor!==$this->actor)throw new DomainException('native_credit_editor_denied');$this->assertCurrent();}
    private function catalog():NativeCompanyCatalogUi{return new NativeCompanyCatalogUi($this->db,$this->p,$this->issuer,$this->actor);}
    public function item($id):array{$this->assertCurrent();$row=$this->catalog()->itemForSalesEditor('credit_notes',$id);$this->assertCurrent();return$row;}
    public function search(string $query):array{$this->assertCurrent();$rows=$this->catalog()->searchForSalesEditor('credit_notes',$query);$this->assertCurrent();return$rows;}
    public function choices():array{$this->assertCurrent();$rows=$this->catalog()->salesEditorChoices('credit_notes');$this->assertCurrent();return$rows;}
    public function fields($id):array{return(new NativeCompanyCustomFields($this->db,$this->p))->creditCatalogItem($this->issuer,$this->actor,$id,$this);}
    private static function exact($value):string
    {
        if(!is_string($value)&&!is_int($value))throw new DomainException('native_credit_catalog_precision_conflict');
        $text=(string)$value;
        if(!preg_match('/^(0|[1-9][0-9]{0,12})(?:\.([0-9]{1,4}))?$/D',$text,$parts)||strlen(rtrim($parts[2]??'','0'))>2)throw new DomainException('native_credit_catalog_precision_conflict');
        return CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor($parts[1].'.'.str_pad(substr($parts[2]??'',0,2),2,'0')));
    }
    public function selected($id,$currency):array
    {
        $currency=IssuerPolicy::id($currency);$catalog=$this->catalog();$catalog->currency($currency);$row=$this->item($id);$fields=$this->fields($id);
        $rate=self::exact($row['rate_currency_'.$currency]??$row['rate']);$taxes=[];
        foreach([['taxname','taxrate'],['taxname_2','taxrate_2']]as[$name,$tax])if($row[$tax]!==null)$taxes[]=['name'=>(string)$row[$name],'rate'=>self::exact($row[$tax])];
        // The current credit ledger requires positive two-decimal amounts. Never round a catalogue value silently.
        CompanyInvoiceAmounts::calculate([['description'=>$row['description'],'qty'=>'1.00','rate'=>$rate,'taxes'=>$taxes]]);
        $values=$fields['values'];foreach($fields['definitions']as$definition){$key=(string)$definition['id'];if(isset($values[$key])&&in_array($definition['type'],['multiselect','checkbox'],true))$values[$key]=$values[$key]===''?[]:explode(', ',$values[$key]);}
        if($this->item($id)!==$row)throw new DomainException('native_credit_editor_stale');$this->assertCurrent();
        return['id'=>$row['id'],'description'=>$row['description'],'long_description'=>$row['long_description'],'unit'=>$row['unit'],'rate'=>$rate,'taxes'=>$taxes,
            'custom_fields'=>['definitions_hash'=>hash('sha256',json_encode($fields['definitions'],JSON_THROW_ON_ERROR)),'values'=>$values]];
    }
    public function __serialize():array{throw new LogicException('native_credit_editor_not_serializable');}
    public function __unserialize(array $data):void{throw new LogicException('native_credit_editor_not_serializable');}
}
