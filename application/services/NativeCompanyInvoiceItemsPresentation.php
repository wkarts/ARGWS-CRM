<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoices.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyTaxRate.php';
/** Authorized issued-line data for the original App_items_table renderer; no alternate markup. */
final class NativeCompanyInvoiceItemsPresentation
{
    private function __construct(private array $document,private string $revision,private array $definitions,private array $values,private array $lines){}
    public static function load($db,string $prefix,int $issuer,int $actor,int $invoice):self
    {
        $service=new CompanyInvoices($db,$prefix);$before=$service->revision($issuer,$actor,$invoice);
        $document=$service->document($issuer,$actor,$invoice)->data();$lines=$service->previewItems($issuer,$actor,$invoice);
        $fields=new NativeCompanyCustomFields($db,$prefix);
        $definitions=$fields->invoiceItemDefinitions($issuer,$actor,$invoice,['active'=>1]);$values=[];
        foreach($lines as $item)$values[$item['id']]=$fields->values($issuer,$actor,'items',$item['id']);
        $definitions=array_values(array_filter($definitions,static function($definition)use($values){foreach($values as $row)if(($row[(string)$definition['id']]??'')!=='')return true;return false;}));
        if(!hash_equals($before,$service->revision($issuer,$actor,$invoice)))throw new DomainException('company_invoice_stale');
        return new self($document,$before,$definitions,$values,$lines);
    }
    public function transaction():object
    {
        $items=[];
        foreach($this->lines as $item){$taxes=[];foreach($item['taxes']as$tax)$taxes[]=['taxname'=>$tax['name'].'|'.$tax['rate'],'taxrate'=>$tax['rate']];
            $items[]=['id'=>$item['id'],'description'=>$item['description'],'long_description'=>($item['long_description']??''),'qty'=>$item['qty'],'rate'=>$item['rate'],'unit'=>$item['unit']??'','taxes'=>$taxes,'is_optional'=>$item['is_optional']??0,'is_selected'=>$item['is_selected']??1];}
        return(object)['id'=>$this->document['invoice_id'],'items'=>$items,'currency_name'=>$this->document['currency'],'show_quantity_as'=>$this->document['quantity_mode'],'date'=>$this->document['date'],'status'=>$this->document['status'],'native_items_presentation'=>$this];
    }
    public function assertTransaction(object $transaction):void
    {
        foreach(get_object_vars($this->transaction()) as $key=>$value)if(!property_exists($transaction,$key)||$transaction->$key!==$value)throw new DomainException('native_invoice_items_projection_mismatch');
    }
    public function definitions():array{return$this->definitions;}
    public function value(int $item,$field):string{return$this->values[$item][(string)$field]??'';}
    public function amount(int $item):string{foreach($this->lines as$row)if($row['id']===$item)return CompanyInvoiceAmounts::calculate([array_intersect_key($row,array_flip(['description','qty','rate','taxes']))])['subtotal'];throw new DomainException('native_invoice_items_projection_mismatch');}
    public function attributes():string{return ' data-native-invoice-id="'.$this->document['invoice_id'].'" data-native-invoice-revision="'.$this->revision.'"';}
    public function __serialize():array{throw new LogicException('native_invoice_items_not_serializable');}
    public function __unserialize(array $data):void{throw new LogicException('native_invoice_items_not_serializable');}
}
