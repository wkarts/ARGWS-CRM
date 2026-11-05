<?php
/** Real Invoices controller and actual recurrence renderer, with the existing CI boundary double. */
require __DIR__.'/invoice_recurring_modes_controller.php';
if(!function_exists('_l')){function _l($key){return$key;}}
if(!function_exists('get_option')){function get_option($key){return'0';}}
if(!function_exists('app_format_money')){function app_format_money($value,$currency){return$currency.' '.$value;}}
if(!function_exists('_d')){function _d($value){return$value;}}
$GLOBALS['CI']->security=new class{function get_csrf_token_name(){return'framework_csrf';}};
CompanyRequestScope::$issuer=$a;
$reset();$controller->recurring();$loaded=end($GLOBALS['CI']->load->views);
essentialCheck($outputHttp->status===200&&$loaded[0]==='admin/invoices/recurring/list'&&$loaded[1]['native_recurring']&&count($loaded[1]['invoices_years'])>0,'canonical recurring index selects original directory and owned filter catalogue');
$columns=[];for($n=0;$n<8;$n++)$columns[]=['data'=>$n,'name'=>'','searchable'=>'true','orderable'=>'true','search'=>['value'=>'','regex'=>'false']];
$wire=['draw'=>'9','start'=>'0','length'=>'-1','search'=>['value'=>'','regex'=>'false'],'columns'=>$columns,'order'=>[['column'=>'7','dir'=>'asc']],'last_order_identifier'=>'recurring-invoices','framework_csrf'=>'synthetic'];
$reset(['recurring'=>'1'],$wire);$controller->table();$reply=json_decode($outputHttp->body,true,512,JSON_THROW_ON_ERROR);
essentialCheck($outputHttp->status===200&&$reply['draw']===9&&$reply['iTotalRecords']>0&&count($reply['aaData'][0])===9,'actual table endpoint returns original eight columns plus DataTables row class');
essentialCheck(str_contains($reply['aaData'][0][0],'init_recurring_schedule(')&&!str_contains($reply['aaData'][0][0],'init_invoice('),'schedule identifier is never sent to the invoice preview');
$reset(['recurring'=>'1'],$wire+['issuer_id'=>$b]);$controller->table();essentialCheck($outputHttp->status===422,'forged issuer query is rejected by real table adapter');
$reset(['recurring'=>'bad'],$wire);$controller->table();essentialCheck($outputHttp->status===422,'unsupported recurring selector is rejected explicitly');
$reset(['recurring'=>'1'],$wire);$inputHttp->posts['_native_company_csrf']='bad';$controller->table();essentialCheck($outputHttp->status===403,'table cannot read financial data with invalid native CSRF');
$reset();$controller->recurring_preview($sid);$fragment=end($GLOBALS['CI']->load->views)[1];$rendered=$renderer->render($fragment);
essentialCheck($outputHttp->status===200&&$fragment['native_recurring_fragment']===true&&str_contains($rendered,'id="invoice-schedule-detail"')&&!str_contains($rendered,'id="wrapper"'),'split preview renders the real canonical detail without a nested application shell');
essentialCheck(str_contains($rendered,'name="action" value="pause"')&&str_contains($rendered,'invoices/list_invoices/')&&!str_contains($rendered,'name="action" value="create"'),'fragment preserves actual controls and linked invoices without embedding an unrelated create form');
$reset();$inputHttp->verb='HEAD';$controller->recurring_preview($sid);essentialCheck($outputHttp->status===200&&$outputHttp->body===''&&$GLOBALS['CI']->load->views===[],'HEAD validates the schedule without rendering document bytes');
CompanyRequestScope::$issuer=$b;$reset();$controller->recurring_preview($sid);essentialCheck($outputHttp->status===403&&$GLOBALS['CI']->load->views===[],'old company schedule preview is denied after company switch');
$reset(['recurring'=>'1'],$wire);$controller->table();$other=json_decode($outputHttp->body,true,512,JSON_THROW_ON_ERROR);essentialCheck(!str_contains(json_encode($other['aaData']),'init_recurring_schedule('.$sid.')'),'B table does not retain A schedule');
// A private draft can be explicitly reviewed with another date before issuance.
$datedSchedule=$ir->create($a,7,$baseInput,array_replace($draftSpec,['cycles'=>1]),$mk('directory-reviewed-date'));
$datedDraft=$ir->run($a,7,$datedSchedule,'2026-01-01');$dated=$drafts->document($a,7,$datedDraft);
$reviewed=array_replace($dated['payload'],['date'=>'2026-02-15','due_date'=>'2026-02-20']);
$drafts->change($a,7,$datedDraft,$dated['revision'],$reviewed,$mk('directory-reviewed-date-save'));
$drafts->issue($a,7,$datedDraft,$dated['revision']+1,$mk('directory-reviewed-date-issue'));
$datePage=(new NativeCompanyInvoiceRecurringDirectory($db,$p))->table($a,7,array_diff_key($wire,['framework_csrf'=>true]));$datedRow=array_column($datePage['rows'],null,'id')[$datedSchedule];
essentialCheck($datedRow['last_date']==='2026-02-15','last child column uses actual issued invoice date after explicit private draft review, not scheduled occurrence date');
essentialCheck(!array_key_exists('canonical_invoice_date',$ir->document($a,7,$datedSchedule)['runs'][0]),'directory date enrichment does not change the published document reader DTO');
echo 'Native recurrence routes/table/preview: '.$GLOBALS['essentialChecks']." cumulative PASS. Controller boundary doubles, not real HTTP/browser.\n";
