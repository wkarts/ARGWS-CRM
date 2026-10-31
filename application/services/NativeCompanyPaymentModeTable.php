<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyPaymentModes.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
/** Existing native five-column offline configuration table; no gateway instantiation. */
final class NativeCompanyPaymentModeTable
{
 public function __construct(private $db,private string $p){}
 public function response(int$issuer,int$actor,array$input):array
 {
  $request=NativeCompanyDataTableWire::request($input,['id','name','description','active','options']);
  foreach($request['orders']as$order)if($order['field']==='options')throw new DomainException('native_company_table_request_invalid');
  $service=new NativeCompanyPaymentModes($this->db,$this->p);$all=$service->listing($issuer,$actor);$selected=$all;
  if($request['search']!=='')$selected=array_values(array_filter($selected,static function($row)use($request){foreach(['id','name','description','active']as$field)if(mb_stripos((string)$row[$field],$request['search'])!==false)return true;return false;}));
  usort($selected,static function($a,$b)use($request){foreach($request['orders']as$order){$f=$order['field'];$c=in_array($f,['id','active'],true)?((int)$a[$f]<=>(int)$b[$f]):strnatcasecmp($a[$f],$b[$f]);if($c)return$order['direction']==='asc'?$c:-$c;}return(int)$a['id']<=>(int)$b['id'];});
  $filtered=count($selected);if($request['length']!==-1)$selected=array_slice($selected,$request['start'],$request['length']);
  $rows=[];foreach($selected as$dto)$rows[]=(static function($aRow){$aColumns=['id','name','description','active'];require APPPATH.'views/admin/paymentmodes/table_row.php';return$row;})($dto);
  if($service->listing($issuer,$actor)!==$all)throw new DomainException('native_payment_mode_stale');
  return ['draw'=>$request['draw'],'iTotalRecords'=>count($all),'iTotalDisplayRecords'=>$filtered,'aaData'=>$rows];
 }
}
