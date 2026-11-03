<?php
require __DIR__.'/invoice_delivery.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommunicationState.php';
$svc=new NativeCompanyInvoiceCommunicationState($db,$p);$start=$GLOBALS['essentialChecks'];
$db->query("UPDATE {$p}emailtemplates SET active=0 WHERE slug='invoice-overdue-notice'");
$before=$db->query("SELECT * FROM {$p}emailtemplates ORDER BY emailtemplateid")->result_array();
essentialCheck($svc->reminderSources($a,7)['enabled']===false,'no active owned template is not a reminder source');
essentialCheck($db->query("SELECT * FROM {$p}emailtemplates ORDER BY emailtemplateid")->result_array()===$before,'capability read never seeds or mutates templates');
$db->query("UPDATE {$p}emailtemplates SET active=1 WHERE slug='invoice-overdue-notice' AND issuer_id=?",[$a]);
$state=$svc->reminderSources($a,7);essentialCheck($state['enabled']&&$state['email_template_active']&&!$state['delivery_ready'],'actual active template source is not SMTP or cron readiness');
essentialCheck(!$svc->reminderSources($b,7)['enabled'],'A active template cannot enable B');
$db->query("UPDATE {$p}emailtemplates SET active=1 WHERE slug='invoice-overdue-notice' AND issuer_id IS NULL");
essentialCheck(!$svc->reminderSources($b,7)['enabled'],'principal template cannot enable B');
essentialCheck($state['sms']===['supported'=>false,'reason'=>'issuer_sms_adapter_required'],'unsupported SMS does not read global credentials or invent a source');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");essentialThrows(fn()=>$svc->reminderSources($a,7),'native_invoice_communication_denied','revoked actor cannot inspect source capability');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
echo 'Native reminder source capability: '.($GLOBALS['essentialChecks']-$start)." actual SQLite checks PASS; no SMTP/SMS/cron claim.\n";
