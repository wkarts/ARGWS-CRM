<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Actual native settings controller/model/view: exact four formats, isolated private atomic batch. */
require __DIR__.'/settings_model_controller.php';$start=$GLOBALS['essentialChecks'];$legacy=$db->query("SELECT name,value FROM {$p}options ORDER BY name")->result_array();
foreach([41=>'LETTER-LANDSCAPE',82=>'A4-LANDSCAPE'] as $owner=>$choice){
 CompanySettingsRuntime::run($db,$p,$owner,12,function()use($choice,$viewHarness,$db,$p){
  $controller=new Settings();$GLOBALS['CI']->input->group='sales_pdf';$GLOBALS['CI']->input->payload=[];$controller->index();$v=end($GLOBALS['CI']->load->views)[1];
  essentialCheck($v['values']===['pdf_format_estimate'=>'A4-PORTRAIT','pdf_format_proposal'=>'A4-PORTRAIT'],'absent company formats use product defaults, never principal');
  $input=['pdf_format_estimate'=>$choice,'pdf_format_proposal'=>'LETTER-PORTRAIT'];$GLOBALS['CI']->input->payload=['settings'=>$input];$controller->index();$v=end($GLOBALS['CI']->load->views)[1];$html=$viewHarness->render($v);
  essentialCheck($v['saved']&&$v['values']===$input,'A/B own format batch persists through actual controller and model');
  essentialCheck(substr_count($html,'value="LETTER-LANDSCAPE"')===2&&str_contains($html,'settings?group=sales_pdf')&&str_contains($html,'_company_context'),'GUI exposes exactly native options and sealed navigation/context');
  essentialCheck(!str_contains($html,'synthetic-cipher:')&&!str_contains($html,'password-b'),'PDF settings GUI contains no SMTP credentials');
  $before=CompanySettingsRuntime::all();foreach([['pdf_format_estimate'=>'A3','pdf_format_proposal'=>'A4-PORTRAIT'],['pdf_format_proposal'=>'letter-portrait'],['pdf_format_estimate'=>['A4-PORTRAIT']],['pdf_format_proposal'=>"A4-PORTRAIT\r\n"],['pdf_format_invoice'=>'LETTER-LANDSCAPE'],['smtp_password'=>'forged'],['pdf_format_estimate'=>'']] as $bad){$GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>$bad];$controller->index();essentialCheck($GLOBALS['CI']->output->status===422&&CompanySettingsRuntime::all()===$before,'unknown, malformed, unsafe or other native PDF fields fail before any effect');}
  $db->failOn='INSERT INTO '.$p.'issuers_audit';$GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>['pdf_format_estimate'=>'A4-PORTRAIT','pdf_format_proposal'=>'A4-LANDSCAPE']];$controller->index();$db->failOn=null;essentialCheck($GLOBALS['CI']->output->status===503&&CompanySettingsRuntime::all()===$before,'late audit failure rolls back both private settings');
  settingThrows(fn()=>$GLOBALS['CI']->settings_model->update(['settings'=>['pdf_format_estimate'=>'custom-file','smtp_host'=>'partial']]),'company_settings_invalid_input','direct model shares exact format validation');essentialCheck(CompanySettingsRuntime::all()===$before,'direct invalid batch has no adjacent effects');
 });
}
essentialCheck($db->query("SELECT name,value FROM {$p}options ORDER BY name")->result_array()===$legacy,'A/B settings preserve principal and infrastructure options');
CompanySettingsRuntime::run($db,$p,41,14,function(){settingThrows(fn()=>(new Settings())->index(),'http_403','no settings view denies PDF group before hydration');});
echo 'Sales PDF settings: '.($GLOBALS['essentialChecks']-$start).' added / '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
