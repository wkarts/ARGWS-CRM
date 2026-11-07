<?php
/** Actual native controller rejects stale/foreign inputs before any provider call. */
require __DIR__.'/asaas_card_settings.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixInboxSchema.php';
\Asaas\CompanyPixInboxSchema::ensure($db,$p,true);
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInvoiceEditSchema.php';\Asaas\CompanyInvoiceEditSchema::ensure($db,$p);\Asaas\CompanyOriginPolicy::ensure($db,$p,true);\Asaas\CompanyOriginGenerations::ensure($db,$p,true);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.0','asaas']);
$start=$GLOBALS['essentialChecks'];$controller->input->verb='POST';
foreach(['pix_worker','pix_observe'] as $action){
 $post=['action'=>$action,'account_row'=>'1','event_id'=>'1','account_revision'=>str_repeat('a',64),'worker_revision'=>str_repeat('0',64),'enabled'=>'1'];
 $controller->input->posted=$post;$controller->output->status=200;$run();essentialCheck($controller->output->status===409,$action.' requires scoped CSRF');
 $post['company_asaas_preferences_csrf']=$controller->session->data['company_asaas_preferences_csrf'];
 $post['account_revision']=[];$controller->input->posted=$post;$controller->output->status=200;$run();essentialCheck($controller->output->status===409,$action.' rejects unusable revision as stale');
}
foreach(['pix_receipt_worker','reserve_pix','observe_pix_initial','settle_pix_initial'] as $action){
 $controller->input->posted=['action'=>$action];$controller->output->status=200;$run();essentialCheck($controller->output->status===409,$action.' requires scoped CSRF before command or transport');
}
echo 'Pix controller: '.($GLOBALS['essentialChecks']-$start).' additional guards; no provider calls.'.PHP_EOL;
