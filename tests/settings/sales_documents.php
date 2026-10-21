<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Actual isolated Settings controller/model/view, native private batch/audit and no external transport. */
require __DIR__.'/settings_model_controller.php';$salesSettingsStart=$GLOBALS['essentialChecks'];$legacyOptions=$db->query("SELECT name,value FROM {$p}options ORDER BY name")->result_array();
foreach([41=>'A',82=>'B'] as $owner=>$letter){
 CompanySettingsRuntime::run($db,$p,$owner,12,function()use($letter,$viewHarness,$db,$p){
  $controller=new Settings();$GLOBALS['CI']->input->group='sales_documents';$GLOBALS['CI']->input->payload=[];$controller->index();$view=end($GLOBALS['CI']->load->views)[1];
  essentialCheck($view['values']['estimate_prefix']==='ORC-'&&$view['values']['proposal_number_prefix']==='PROP-'&&$view['values']['predefined_terms_estimate']===''&&$view['values']['predefined_terms_proposal']==='','new preferences show product defaults without reading A or principal');
  $values=['estimate_prefix'=>$letter.'-ORC-','proposal_number_prefix'=>$letter.'-PROP-','predefined_terms_estimate'=>'Condições '.$letter."\n<script>texto literal</script>",'predefined_terms_proposal'=>'Proposta '.$letter];$GLOBALS['CI']->input->payload=['settings'=>$values];$controller->index();$view=end($GLOBALS['CI']->load->views)[1];$html=$viewHarness->render($view);
  essentialCheck($view['saved']&&$view['values']==$values,'A/B native controller saves own complete sales preference batch');
  essentialCheck(str_contains($html,'settings[estimate_prefix]')&&str_contains($html,'settings[proposal_number_prefix]')&&str_contains($html,'settings[predefined_terms_proposal]'),'actual isolated view exposes precisely adapted sales fields');
  essentialCheck(str_contains($html,'&lt;script&gt;texto literal&lt;/script&gt;')&&!str_contains($html,'<script>texto literal</script>'),'terms remain escaped literal text');
  essentialCheck(str_contains($html,'synthetic_csrf')&&str_contains($html,'_company_context')&&str_contains($html,'maxlength="50"'),'sales form retains native CSRF/context and prefix byte limit');
  essentialCheck(str_contains($html,'settings?group=sales_documents')&&!str_contains($html,'password-b')&&!str_contains($html,'synthetic-cipher:'),'private navigation contains no SMTP/provider secrets');
  $before=CompanySettingsRuntime::all();
  foreach([['estimate_prefix'=>str_repeat('a',51),'proposal_number_prefix'=>'partial'],['proposal_number_prefix'=>"P\r\nInjected"],['predefined_terms_proposal'=>str_repeat('a',10001),'estimate_prefix'=>'partial'],['estimate_prefix'=>['bad']],['predefined_terms_estimate'=>"bad\0"],['smtp_host'=>'forged-host','estimate_prefix'=>'partial'],['unknown_integration_secret'=>'forged']] as $bad){$GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>$bad];$controller->index();essentialCheck($GLOBALS['CI']->output->status===422&&CompanySettingsRuntime::all()===$before,'invalid/overposted sales batch fails before partial mutation');}
  $GLOBALS['CI']->output->status=200;$db->failOn='INSERT INTO '.$p.'issuers_audit';$GLOBALS['CI']->input->payload=['settings'=>['estimate_prefix'=>'never','predefined_terms_estimate'=>'partial']];$controller->index();$db->failOn=null;essentialCheck($GLOBALS['CI']->output->status===503&&CompanySettingsRuntime::all()===$before,'late audit failure rolls back all sales preferences');
  settingThrows(fn()=>$GLOBALS['CI']->settings_model->update(['settings'=>['proposal_number_prefix'=>str_repeat('a',51),'smtp_host'=>'partial']]),'company_settings_invalid_input','direct model calls share prefix policy before effects');essentialCheck(CompanySettingsRuntime::all()===$before,'invalid direct model sales update never writes adjacent private setting');
 });
}
foreach([41=>'A',82=>'B'] as $owner=>$letter)essentialCheck($store->get($owner,'estimate_prefix')===$letter.'-ORC-'&&$store->get($owner,'predefined_terms_proposal')==='Proposta '.$letter,'A/B read-after-write uses explicit origin without shared cache');
essentialCheck($db->query("SELECT name,value FROM {$p}options ORDER BY name")->result_array()===$legacyOptions,'new group never mutates global infrastructure or principal preferences');
CompanySettingsRuntime::run($db,$p,41,14,function(){settingThrows(fn()=>(new Settings())->index(),'http_403','no settings.view prevents group hydration');});
echo 'Sales document settings: '.($GLOBALS['essentialChecks']-$salesSettingsStart).' added / '.$GLOBALS['essentialChecks'].' cumulative passed ('.$db->driver().'). No SMTP/provider network.'.PHP_EOL;
