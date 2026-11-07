<?php
/** Real controller/service and SQL; synthetic requests, no provider calls. */
require __DIR__.'/asaas_preferences_controller.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyCardPricing.php';
$start=$GLOBALS['essentialChecks'];
\Asaas\CompanyCardPricingSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.7.3','asaas']);
// Tracking alone must not bypass the required native170 journal schema.
$controller->input->verb='GET';
essentialThrows(fn()=>$run(),'asaas_invoice_edit_schema_required','incomplete tracked upgrade fails closed before rendering settings');
\Asaas\CompanyInvoiceEditSchema::ensure($db,$p);
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);
$pricing=new \Asaas\CompanyCardPricing($db,$p);
$controller->input->verb='GET';$controller->output->status=200;$run();
$page=$pricing->page($a,7,$accounts[$a]);$values=$page['settings'];$values['card_fee_mode']='customer';
$post=['action'=>'save_card_pricing','account_row'=>(string)$accounts[$a],'pricing'=>$values,'pricing_revision'=>$page['revision'],'account_revision'=>$page['account_revision']];
$controller->input->verb='POST';$controller->input->posted=$post;$controller->output->status=200;$run();
essentialCheck($controller->output->status===409&&$pricing->page($a,7,$accounts[$a])===$page,'missing CSRF blocks pricing writes');
$post['company_asaas_preferences_csrf']=$controller->session->data['company_asaas_preferences_csrf'];
$controller->input->posted=$post;$controller->output->status=200;$run();
essentialCheck($controller->output->status===200&&$pricing->page($a,7,$accounts[$a])['settings']['card_fee_mode']==='customer','actual controller saves scoped pricing');
$saved=$pricing->page($a,7,$accounts[$a]);
$controller->output->status=200;$run();
essentialCheck($controller->output->status===409&&$pricing->page($a,7,$accounts[$a])===$saved,'replay rejected without changing pricing');
$post['pricing_revision']=$saved['revision'];$post['pricing']['card_fee_mode']='absorb';
$controller->input->posted=$post;$controller->output->status=200;$run($a,21);
essentialCheck($pricing->page($a,7,$accounts[$a])===$saved,'actor without settings edit cannot overwrite pricing');
$controller->output->status=200;$run($b);
essentialCheck($pricing->page($a,7,$accounts[$a])===$saved&&$pricing->page($b,7,$accounts[$b])['revision']===\Asaas\CompanyPreferences::ZERO,'foreign issuer cannot overwrite pricing');
$controller->input->posted=['action'=>'preview_card_pricing','account_row'=>(string)$accounts[$a],'balance'=>'100.00','installments'=>'1','company_asaas_preferences_csrf'=>$controller->session->data['company_asaas_preferences_csrf']];
$controller->output->status=200;$run();
essentialCheck($controller->output->status===200&&$controller->load->view['card_pricing_preview']['pricing']['charged_cents']===10359&&$pricing->page($a,7,$accounts[$a])===$saved,'actual controller simulates without writing pricing');
$dto=$controller->load->view;
$renderPricing=function(array $dto)use($e):string{extract($dto);$environmentLabel=static fn($value)=>$value;$hidden=function(array $values=[])use($e){foreach($values as $key=>$value)echo '<input name="'.$e($key).'" value="'.$e($value).'">';};ob_start();include APPPATH.'views/admin/settings/native_asaas_card_pricing.php';return ob_get_clean();};
$html=$renderPricing($dto);
essentialCheck(str_contains($html,'Salvar condições desta conta')&&str_contains($html,'103.59')&&str_contains($html,'pricing_revision'),'native view renders saved pricing, revision and simulation');
$dto['can_configure']=false;$html=$renderPricing($dto);
essentialCheck(!str_contains($html,'save_card_pricing')&&str_contains($html,'preview_card_pricing'),'read-only view offers simulation without write controls');
echo 'Card pricing controller/view: '.($GLOBALS['essentialChecks']-$start).' additional checks ('.$db->driver().').'.PHP_EOL;
