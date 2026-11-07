<?php
require __DIR__.'/asaas_preferences_controller.php';
$start=$GLOBALS['essentialChecks'];\Asaas\CompanyOriginPolicy::ensure($db,$p,true);\Asaas\CompanyInvoiceEditSchema::ensure($db,$p);$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.7','asaas']);
$controller->input->verb='GET';$controller->output->status=200;$run();
$csrf=$controller->session->data['company_asaas_preferences_csrf'];
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins')->result_array();
foreach([
 ['action'=>'open_installment_recreation','group_origin'=>1],
 ['action'=>'review_installment_recreation','group_origin'=>1],
 ['action'=>'execute_installment_recreation','group_origin'=>1],
 ['action'=>'recover_installment_recreation','group_origin'=>1],
 ['action'=>'review_installment_recreation','group_origin'=>1,'installment_count'=>[],'due_date'=>'2027-01-05','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'execute_installment_recreation','group_origin'=>1,'installment_count'=>'2','due_date'=>'2027-01-05','review_hash'=>[],'confirm'=>'1','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'execute_installment_recreation','group_origin'=>1,'installment_count'=>'2','due_date'=>'2027-01-05','review_hash'=>str_repeat('a',64),'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'recover_installment_recreation','group_origin'=>1,'review_hash'=>str_repeat('a',64),'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'open_installment_edit','group_origin'=>1],
 ['action'=>'review_installment_edit','group_origin'=>1],
 ['action'=>'execute_installment_edit','group_origin'=>1],
 ['action'=>'resume_installment_edit','group_origin'=>1],
 ['action'=>'review_installment_edit','group_origin'=>1,'changes'=>'invalid','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'execute_installment_edit','group_origin'=>1,'changes'=>[],'review_hash'=>[],'confirm'=>'1','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'execute_installment_edit','group_origin'=>1,'changes'=>[],'review_hash'=>str_repeat('a',64),'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'resume_installment_edit','group_origin'=>1,'review_hash'=>str_repeat('a',64),'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'account_report','account_row'=>1],
 ['action'=>'export_account_report','account_row'=>1,'account_revision'=>[],'report_kind'=>'balance','report_filters'=>[],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'automatic_pix_monitor','account_row'=>1],
 ['action'=>'automatic_pix_monitor','account_row'=>1,'account_revision'=>[],'pix_filters'=>[],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'export_payment_report','account_row'=>1],
 ['action'=>'export_payment_report','account_row'=>1,'account_revision'=>[],'remote_filters'=>[],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'review_installment_batch_removal','group_origin'=>1],
 ['action'=>'remove_installment_batch','group_origin'=>1],
 ['action'=>'remove_installment_batch','group_origin'=>1,'review_hash'=>str_repeat('a',64),'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'remove_installment_batch','group_origin'=>1,'confirm'=>'1','review_hash'=>['invalid'],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'remove_installment_batch','group_origin'=>1,'confirm'=>'1','review_hash'=>'invalid','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'remove_installment','group_origin'=>1,'remote_id'=>'pay_synthetic'],
 ['action'=>'refresh_installment_removal','group_origin'=>1,'remote_id'=>'pay_synthetic'],
 ['action'=>'remove_installment','group_origin'=>1,'remote_id'=>'pay_synthetic','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'remove_installment','group_origin'=>1,'remote_id'=>['bad'],'confirm'=>'1','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'refresh_installment_removal','group_origin'=>1,'remote_id'=>['bad'],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'reconcile_installment','group_origin'=>1,'remote_id'=>'pay_synthetic'],
 ['action'=>'reconcile_installment','group_origin'=>1,'remote_id'=>'pay_synthetic','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'confirm_legacy_installments','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$a],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'preview_legacy_installments','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$a]],
 ['action'=>'preview_legacy_installments','remote_id'=>['bad'],'invoice_id'=>1,'account_row'=>$accounts[$a],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'preview_legacy_installments','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$b],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'preview_legacy_binding','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$a]],
 ['action'=>'confirm_legacy_binding','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$a],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'confirm_legacy_binding','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$a],'confirm'=>'1','review_hash'=>'forged','company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'preview_legacy_binding','remote_id'=>['pay_synthetic'],'invoice_id'=>1,'account_row'=>$accounts[$a],'company_asaas_preferences_csrf'=>$csrf],
 ['action'=>'preview_legacy_binding','remote_id'=>'pay_synthetic','invoice_id'=>1,'account_row'=>$accounts[$b],'company_asaas_preferences_csrf'=>$csrf],
] as $case=>$post){$controller->input->verb='POST';$controller->input->posted=$post;$controller->output->status=200;$run();essentialCheck($controller->output->status===(!isset($post['company_asaas_preferences_csrf'])?409:422)&&$db->query('SELECT * FROM '.$p.'asaas_payment_origins')->result_array()===$before,'controller guard case '.$case.' status '.$controller->output->status);}
$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,22,?,?)',[$a,'invoices','view']);
$controller->input->verb='POST';$controller->input->posted=['action'=>'export_payment_report','account_row'=>999999,'account_revision'=>str_repeat('a',64),'remote_filters'=>[],'company_asaas_preferences_csrf'=>$csrf];$controller->output->status=200;$run($a,22);
essentialCheck($controller->output->status===403&&!$controller->load->view['can_export_asaas_reports'],'controller denies report export for settings admin without company report capabilities before account/network');
echo 'Legacy binding controller: '.($GLOBALS['essentialChecks']-$start).' checks ('.$db->driver().'); no provider calls.'.PHP_EOL;
