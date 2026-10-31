<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyInvoiceDraftsInput.php';
require_once __DIR__.'/CompanySettingsFormats.php';
require_once __DIR__.'/NativeCompanyTaxRate.php';
/** Original form wire only. Authority, current catalog and durable writes remain outside this parser. */
final class NativeCompanyInvoiceFormInput
{
 public static function date($value,string $format,bool $optional=false):?string
 {
  if($optional&&$value==='')return null;if(!is_string($value)||!isset(CompanySettingsFormats::dates()[$format]))throw new DomainException('native_invoice_form_invalid');$pattern=preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D',$value)?'Y-m-d':CompanySettingsFormats::dates()[$format];$d=DateTimeImmutable::createFromFormat('!'.$pattern,$value,new DateTimeZone('UTC'));if(!$d||$d->format($pattern)!==$value)throw new DomainException('native_invoice_form_invalid');return$d->format('Y-m-d');
 }
 private static function checkbox(array $input,string $key):int{if(!array_key_exists($key,$input))return 0;if(!in_array($input[$key],['on','1',1],true))throw new DomainException('native_invoice_form_invalid');return 1;}
 private static function fields($value,string $type):array
 {
  if(!is_array($value)||array_diff(array_keys($value),[$type])||!is_array($value[$type]??[]))throw new DomainException('native_invoice_form_invalid');return$value[$type]??[];
 }
 public static function create(array $input,string $dateFormat):array
 {return self::parse($input,$dateFormat,false);}
 public static function edit(array $input,string $dateFormat):array
 {return self::parse($input,$dateFormat,true);}
 private static function parse(array $input,string $dateFormat,bool $editing):array
 {
  $allowed=['_native_invoice_id','_native_invoice_revision','_native_invoice_request_key','_native_invoice_number_revision','clientid','project_id','number','date','duedate','currency','clientnote','tags','newitems','custom_fields','subtotal','total','discount_percent','discount_total','discount_type','adjustment','cancel_overdue_reminders','allowed_payment_modes','recurring','repeat_every_custom','repeat_type_custom','recurring_due_mode','cycles','save_as_draft','save_and_send','save_and_record_payment','save_and_send_later','item_select','task_select','task_id','expense_id','description','long_description','quantity','unit','rate','taxname','merge_invoices','invoices_to_merge','cancel_merged_invoices','bill_expenses','items','removed_items'];
  $allowed=array_merge($allowed,array_keys(CompanyInvoiceNativeFields::TEXT),array_keys(CompanyInvoiceNativeFields::INTEGER));
  if($editing)$allowed=array_merge($allowed,['isedit','merge_current_invoice','_native_invoice_tags_revision']);
  if(array_diff(array_keys($input),$allowed))throw new DomainException('native_invoice_form_invalid');
  $lineSources=[];$removed=[];
  if($editing){
   $reference=NativeCompanyResourceId::decode('invoice',$input['_native_invoice_id']??null);
   if($reference['source_kind']!=='invoice'||(string)($input['merge_current_invoice']??'')!==(string)$reference['source_id']||!is_string($input['_native_invoice_revision']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['_native_invoice_revision'])||!is_string($input['_native_invoice_tags_revision']??null)||!preg_match('/^[a-f0-9]{64}$/D',$input['_native_invoice_tags_revision']))throw new DomainException('native_invoice_form_invalid');
   $existing=$input['items']??[];$new=$input['newitems']??[];$removed=$input['removed_items']??[];
   if(!is_array($existing)||!is_array($new)||!is_array($removed)||!array_is_list($removed))throw new DomainException('native_invoice_form_invalid');
   $rows=[];foreach($existing as$row){if(!is_array($row))throw new DomainException('native_invoice_form_invalid');$source=NativeCompanyResourceId::decode('invoice_item',$row['itemid']??null);if($source['source_kind']!=='invoice_item')throw new DomainException('native_invoice_form_invalid');$lineSources[$row['order']??'']=$source['source_id'];unset($row['itemid']);$rows[]=$row;}
   foreach($new as$row){if(!is_array($row)||isset($lineSources[$row['order']??'']))throw new DomainException('native_invoice_form_invalid');$lineSources[$row['order']??'']=null;$rows[]=$row;}
   foreach($removed as&$id){$source=NativeCompanyResourceId::decode('invoice_item',$id);if($source['source_kind']!=='invoice_item')throw new DomainException('native_invoice_form_invalid');$id=$source['source_id'];}unset($id);
   $input['newitems']=$rows;
  }elseif(($input['_native_invoice_id']??null)!==''||($input['_native_invoice_revision']??null)!=='')throw new DomainException('native_invoice_form_invalid');
  if(!is_array($input['newitems']??null)||!$input['newitems']||count($input['newitems'])>100)throw new DomainException('native_invoice_form_invalid');
  // These select separate source writers. Never silently turn billing/merging/recurrence into a manual invoice.
  foreach(array_merge($editing?[]:['items','removed_items'],['task_id','expense_id','merge_invoices','invoices_to_merge','bill_expenses'])as$key)if(!in_array($input[$key]??null,[null,'',[]],true))throw new DomainException('native_invoice_form_source_required');
  self::checkbox($input,'cancel_merged_invoices');
  if(!in_array($input['recurring']??'0',['0',0],true))throw new DomainException('native_invoice_form_recurring_required');
  $actions=[];foreach(['save_as_draft','save_and_send','save_and_record_payment','save_and_send_later']as$key)if(array_key_exists($key,$input)){if($input[$key]!=='true')throw new DomainException('native_invoice_form_invalid');$actions[]=$key;}if(count($actions)>1)throw new DomainException('native_invoice_form_invalid');$action=$actions[0]??'save';
  $items=[];$lineFields=[];$orders=[];
  foreach($input['newitems']as$row){
   if(!is_array($row)||array_diff(array_keys($row),['order','description','long_description','qty','rate','unit','taxname','custom_fields','is_optional','is_selected'])||!is_string($row['order']??null)||!preg_match('/^[1-9][0-9]{0,2}$/D',$row['order'])||isset($orders[$row['order']]))throw new DomainException('native_invoice_form_invalid');$orders[$row['order']]=true;
   $taxes=$row['taxname']??[];if(!is_array($taxes)||!array_is_list($taxes))throw new DomainException('native_invoice_form_invalid');$normalizedTaxes=[];foreach($taxes as$tax){if(!is_string($tax)||substr_count($tax,'|')!==1)throw new DomainException('native_invoice_form_invalid');[$name,$rate]=explode('|',$tax);$normalizedTaxes[]=['name'=>$name,'rate'=>NativeCompanyTaxRate::exact($rate)];}
   $line=array_intersect_key($row,array_flip(['description','long_description','qty','rate','unit','is_optional','is_selected']));$line['taxes']=$normalizedTaxes;$items[(int)$row['order']]=$line;$lineFields[(int)$row['order']]=self::fields($row['custom_fields']??[],'items');
  }
  ksort($items);ksort($lineFields);if(array_keys($items)!==range(1,count($items)))throw new DomainException('native_invoice_form_invalid');
  $percent=$input['discount_percent']??'0';$fixed=$input['discount_total']??'0';$mode=CompanyInvoiceAmounts::minor($percent)>0?'percent':'fixed';$options=['calculation_contract'=>'native_sales_v1','discount_mode'=>$mode,'discount_value'=>$mode==='percent'?$percent:$fixed,'discount_type'=>$input['discount_type']??'','adjustment'=>($input['adjustment']??'')===''?'0':$input['adjustment']];
  $amounts=CompanyInvoiceLines::calculate(array_values($items),$options);
  foreach(['subtotal','total','discount_total']as$key)if(!isset($input[$key])||CompanyInvoiceAmounts::minor($input[$key])!==CompanyInvoiceAmounts::minor($amounts[$key]))throw new DomainException('native_invoice_form_amount_mismatch');
  $fields=array_intersect_key($input,array_flip(array_merge(array_keys(CompanyInvoiceNativeFields::TEXT),array_keys(CompanyInvoiceNativeFields::INTEGER))));foreach(['include_shipping','show_shipping_on_invoice']as$key)$fields[$key]=self::checkbox($input,$key);if(($fields['sale_agent']??null)==='')$fields['sale_agent']='0';
  foreach(['billing_country','shipping_country']as$key)if(($fields[$key]??null)==='')$fields[$key]='0';
  $payload=['client_id'=>$input['clientid']??null,'currency'=>$input['currency']??null,'date'=>self::date($input['date']??null,$dateFormat),'due_date'=>self::date($input['duedate']??'',$dateFormat,true),'items'=>array_values($items),'note'=>$input['clientnote']??'','native_fields'=>$fields,'native_preferences'=>['cancel_overdue_reminders'=>self::checkbox($input,'cancel_overdue_reminders'),'allowed_payment_modes'=>$input['allowed_payment_modes']??[]]]+$options;
  $tags=$input['tags']??'';if(!is_string($tags))throw new DomainException('native_invoice_form_invalid');$tags=$tags===''?[]:array_map('trim',explode(',',$tags));
  $metadata=['invoice_fields'=>self::fields($input['custom_fields']??[],'invoice'),'line_fields'=>array_values($lineFields),'tags'=>$tags];
  $number=$input['number']??null;$revision=$input['_native_invoice_number_revision']??null;if(!is_string($number)||!preg_match('/^[0-9]{1,30}$/D',$number)||!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision)||ltrim($number,'0')==='')throw new DomainException('native_invoice_form_invalid');
  if($editing){ksort($lineSources);$metadata+=['tags_revision'=>$input['_native_invoice_tags_revision'],'line_sources'=>array_values($lineSources),'removed_line_ids'=>$removed];if($action==='save_as_draft')throw new DomainException('native_invoice_form_invalid');}
  elseif($action!=='save_as_draft')$metadata['number_selection']=['next_number'=>ltrim($number,'0'),'revision'=>$revision];
  return['payload'=>CompanyInvoiceDraftsInput::normalize($payload),'metadata'=>$metadata,'action'=>$action,'request_key'=>CompanyInvoiceDraftsInput::key($input['_native_invoice_request_key']??null),'project'=>$input['project_id']??'']+($editing?['invoice_id'=>$reference['source_id'],'revision'=>$input['_native_invoice_revision'],'number'=>ltrim($number,'0')]:[]);
 }
}
