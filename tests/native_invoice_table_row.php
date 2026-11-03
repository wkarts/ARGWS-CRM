<?php
// Explicit presentation fixture, not framework/HTTP integration.
define('BASEPATH',__DIR__);
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function _l($v){return $v;}
function admin_url($p){return '/admin/'.$p;}
function site_url($p){return '/'.$p;}
function app_format_money($v,$c){return $c.' '.$v;}
function _d($v){return $v;}
function format_invoice_status($s){return '<span class="status">'.(int)$s.'</span>';}
$checks=0;function rowCheck($ok,$message){global$checks;if(!$ok)throw new RuntimeException($message);++$checks;}
$aRow=['id'=>17,'total'=>'123.00','total_tax'=>'3.00','currency_name'=>'BRL','year'=>'2026','date'=>'2026-10-25','duedate'=>'2026-11-25','hash'=>str_repeat('a',32),'recurring'=>1,'clientid'=>5,'company'=>'Client <private>','deleted_customer_name'=>'','project_id'=>7,'project_name'=>'Project <script>','cf'=>'escaped custom'];
$formattedNumber='INV-<17>';$canEditInvoice=true;$invoiceTagsHtml='<span class="label label-tag">Trusted escaped tag</span>';$invoiceStatus=1;$customFieldsColumns=['cf'];$clientid='';$project_id='';
$path=__DIR__.'/../application/views/admin/invoices/table_row.php';$row=require $path;
rowCheck(count($row)===12,'ten native cells plus custom and DT_RowClass');
rowCheck(str_contains($row[0],'init_invoice(17)')&&str_contains($row[0],'/admin/invoices/list_invoices/17'),'same native list/preview interaction');
rowCheck(str_contains($row[0],'/invoice/17/'.str_repeat('a',32))&&str_contains($row[0],'/admin/invoices/invoice/17'),'same public/edit URLs, no company aliases');
rowCheck(str_contains($row[0],'INV-&lt;17&gt;')&&!str_contains($row[0],'INV-<17>'),'number escaped');
rowCheck(str_contains($row[5],'Client &lt;private&gt;')&&str_contains($row[6],'Project &lt;script&gt;'),'related labels escaped');
rowCheck($row[7]===$invoiceTagsHtml&&$row[10]==='escaped custom'&&$row['DT_RowClass']==='has-row-options','trusted tags and custom values use same slots');
$canEditInvoice=false;$clientid='5';$aRow['deleted_customer_name']='Deleted <customer>';$row=require$path;
rowCheck(!str_contains($row[0],'/admin/invoices/invoice/17')&&!str_contains($row[0],'init_invoice('),'edit ACL and scoped new-tab branch preserved');
rowCheck($row[5]==='Deleted &lt;customer&gt;','historical deleted customer remains literal');
echo "Shared native invoice row presentation: {$checks} checks PASS.\n";
