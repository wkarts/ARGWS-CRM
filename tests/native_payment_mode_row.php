<?php
/** Original native renderer extraction parity, with revision additions only for owned DTOs. */
define('BASEPATH',__DIR__);define('APPPATH',dirname(__DIR__).'/application/');
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}function admin_url($v=''){return '/admin/'.$v;}function _l($v){return$v;}
function process_text_content_for_display($v){return nl2br(e($v));}function _attributes_to_string($values){$out='';foreach($values as$k=>$v)$out.=$k.'="'.$v.'" ';return$out;}
$aColumns=['id','name','description','active'];$aRow=['id'=>1,'name'=>'Synthetic <name>','description'=>'Synthetic <description>','active'=>1,'expenses_only'=>0,'invoices_only'=>0,'show_on_pdf'=>1,'selected_by_default'=>0];
$source=file_get_contents(__DIR__.'/support/NativePaymentModeTableOriginal.php');
if(!is_string($source))throw new RuntimeException('baseline unavailable');$start=strpos($source,'    $row = [];');$end=strpos($source,"    \$output['aaData'][] = \$row;",$start);if($start===false||$end===false)throw new RuntimeException('baseline row unavailable');
eval(substr($source,$start,$end-$start));$baseline=$row;require APPPATH.'views/admin/paymentmodes/table_row.php';if($row!==$baseline)throw new RuntimeException('legacy row changed');
$aRow['revision']=str_repeat('a',64);require APPPATH.'views/admin/paymentmodes/table_row.php';if(count($row)!==5||substr_count(implode('',$row),'data-revision=')!==5)throw new RuntimeException('missing mutation revision');
if(str_contains(implode('',$row),'<name>')||str_contains(implode('',$row),'<description>'))throw new RuntimeException('unescaped synthetic content');
echo "Native payment mode legacy row byte parity, five revision controls, escaping: PASS.\n";
