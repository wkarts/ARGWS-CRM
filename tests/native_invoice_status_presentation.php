<?php
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__).'/system/');define('APPPATH',dirname(__DIR__).'/application/');
function get_instance(){throw new RuntimeException('status formatting attempted model/application hydration');}
function _l($key){return 'Synthetic <'.$key.'>';}
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
require APPPATH.'helpers/invoices_helper.php';
$checks=0;$expected=[1=>['danger','invoice_status_unpaid'],2=>['success','invoice_status_paid'],3=>['warning','invoice_status_not_paid_completely'],4=>['warning','invoice_status_overdue'],5=>['default','invoice_status_cancelled'],6=>['default','invoice_status_draft']];
foreach($expected as$status=>[$css,$key])foreach([$status,(string)$status]as$value){
 if(get_invoice_status_label($value)!==$css||format_invoice_status($value,'extra')!=='<span class="label label-'.$css.' extra s-status invoice-status-'.$status.'">'.e(_l($key)).'</span>'||format_invoice_status($value,'',false)!==e(_l($key)))throw new RuntimeException('native status markup changed');++$checks;
}
if(get_invoice_status_label('not_sent')!=='default'||get_invoice_status_label('unknown')!==''||format_invoice_status('unknown','',false)!==e(_l('invoice_status_draft')))throw new RuntimeException('native unknown presentation changed');++$checks;
if(class_exists('Invoices_model',false))throw new RuntimeException('financial model hydrated');++$checks;
$native=file_get_contents(APPPATH.'models/Invoices_model.php');foreach(['UNPAID'=>1,'PAID'=>2,'PARTIALLY'=>3,'OVERDUE'=>4,'CANCELLED'=>5,'DRAFT'=>6]as$name=>$id){if(!preg_match('/public const STATUS_'.$name.' = '.$id.';/',$native))throw new RuntimeException('native status contract drift');++$checks;}
echo "Shared native invoice status text/classes/DOM/no-model: {$checks} checks PASS.\n";
