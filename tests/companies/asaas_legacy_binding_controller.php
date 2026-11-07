<?php
require __DIR__.'/asaas_preferences_controller.php';
$start=$GLOBALS['essentialChecks'];\Asaas\CompanyOriginPolicy::ensure($db,$p,true);\Asaas\CompanyInvoiceEditSchema::ensure($db,$p);$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.7','asaas']);
$controller->input->verb='GET';$controller->output->status=200;$run();
$csrf=$controller->session->data['company_asaas_preferences_csrf'];
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins')->result_array();
foreach([
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
echo 'Legacy binding controller: '.($GLOBALS['essentialChecks']-$start).' checks ('.$db->driver().'); no provider calls.'.PHP_EOL;
