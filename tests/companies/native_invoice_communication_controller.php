<?php
require __DIR__.'/native_invoice_table_controller.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommunicationState.php';
NativeCompanyInvoiceCommandSchema::ensure($db,$p);
$svc=new NativeCompanyInvoiceCommunicationState($db,$p);
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$csrf=true;$ci->input->verb='POST';$controller=new Invoices();
foreach(['resume_overdue_reminders','pause_overdue_reminders','mark_as_sent']as$action){
 $state=$svc->state($a,7,$branded);$ci->input->posts=['revision'=>$state['revision'],'request_key'=>hash('sha256','native-http-'.$action)];$ci->output->status=200;
 $controller->$action($branded);$response=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
 essentialCheck($ci->output->status===200&&$response['success']===true&&str_contains($response['redirect'],'/invoices/list_invoices/'.$branded),'original '.$action.' commits scoped command and native redirect');
 $controller->$action($branded);$response=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);essentialCheck($response['replayed'],'HTTP request replay keeps durable command');
}
$rows=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$ci->input->verb='GET';$ci->output->status=200;$controller->pause_overdue_reminders($branded);essentialCheck($ci->output->status===403,'GET cannot mutate metadata');
$ci->input->verb='POST';CompanyRequestScope::$csrf=false;$controller->pause_overdue_reminders($branded);essentialCheck($ci->output->status===403,'CSRF denied before mutation');CompanyRequestScope::$csrf=true;
$ci->input->posts['issuer_id']=$b;$controller->pause_overdue_reminders($branded);essentialCheck($ci->output->status===422,'issuer injection rejected');unset($ci->input->posts['issuer_id']);
CompanyRequestScope::$issuer=$b;$controller->pause_overdue_reminders($branded);essentialCheck($ci->output->status===403,'cross-company same URL denied');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$rows,'all negative requests preserve invoices');
CompanyRequestScope::$issuer=$primary;
$legacyBefore=$db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->row_array();
$legacyState=$svc->state($primary,7,42);$ci->input->posts=['revision'=>$legacyState['revision'],'request_key'=>hash('sha256','principal-metadata')];$ci->output->status=200;
$controller->pause_overdue_reminders(42);essentialCheck($ci->output->status===200,'primary original NULL invoice uses the same scoped action');
$legacyAfter=$db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->row_array();unset($legacyBefore['cancel_overdue_reminders'],$legacyAfter['cancel_overdue_reminders']);essentialCheck($legacyBefore===$legacyAfter&&$legacyAfter['issuer_id']===null,'primary action does not adopt or renumber historical invoice');
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');
try{$ci->output->status=200;$controller->pause_overdue_reminders(42);essentialCheck($ci->output->status===403,'revoked staff cannot replay a previously committed command');}finally{$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
echo 'Original invoice communication controllers: '.$GLOBALS['essentialChecks']." cumulative checks passed; real SQLite, request boundary double.\n";
