<?php
require __DIR__.'/native_invoice_table_controller.php';
require_once APPPATH.'services/NativeCompanyInvoiceSequencePreferences.php';
$sequence=new NativeCompanyInvoiceSequencePreferences($db,$p);$next=$sequence->readForInvoiceCreation($a,7)['next_number'];
$wire=['number'=>str_pad($next,6,'0',STR_PAD_LEFT),'isedit'=>'false','original_number'=>'false','date'=>'2026-10-25','synthetic_framework_csrf'=>'synthetic'];
CompanyRequestScope::$issuer=$a;$ci->input->verb='POST';$ci->input->posts=$wire;$ci->output->status=200;
$before=[];foreach(['invoices','issuers_sequences','issuers_audit']as$table)$before[$table]=$db->query('SELECT * FROM '.$p.$table)->result_array();
$controller->validate_invoice_number();essentialCheck($ci->output->status===200&&$ci->output->body==='true','native number endpoint validates the real allocator, preserving leading-zero input');
$ci->input->posts['number']='999999';$controller->validate_invoice_number();essentialCheck($ci->output->body==='false','native number endpoint cannot override allocator');
$ci->input->posts=$wire+['issuer_id'=>$b];$controller->validate_invoice_number();essentialCheck($ci->output->status===422,'posted issuer cannot change validation context');
$ci->input->posts=$wire;foreach(['csrf','contextValid']as$flag){CompanyRequestScope::${$flag}=false;$controller->validate_invoice_number();essentialCheck($ci->output->status===403,'number validation requires live native authority');CompanyRequestScope::${$flag}=true;}
$ci->input->verb='GET';$controller->validate_invoice_number();essentialCheck($ci->output->status===403,'number validation GET does not fall through to legacy');$ci->input->verb='POST';
$ci->input->posts=$wire;$ci->input->posts['date']='2026-02-30';$controller->validate_invoice_number();essentialCheck($ci->output->status===422,'invalid calendar date is rejected');
$ci->input->posts=$wire;$ci->input->posts['isedit']='true';$ci->input->posts['original_number']=$wire['number'];$controller->validate_invoice_number();essentialCheck($ci->output->status!==200,'forged isedit and original_number without persisted parent cannot bypass validation');
foreach($before as$table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$rows,'remote validation leaves '.$table.' unchanged');
echo 'Native invoice number controller: '.$GLOBALS['essentialChecks']." cumulative PASS; boundary double, not HTTP proof.\n";
