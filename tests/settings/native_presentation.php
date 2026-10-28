<?php
/** Real settings controller/model/view + synthetic PDO; no SMTP or browser claim. */
require __DIR__.'/settings_model_controller.php';
$presentationChecks=$GLOBALS['essentialChecks'];
essentialCheck(CompanySettingsCatalog::prepare('company',['invoice_company_address'=>"Rua A\nSala 2"])['invoice_company_address']==="Rua A\nSala 2",'catalog preserves existing multiline address contract');
essentialCheck(CompanySettingsCatalog::prepare('email',['smtp_password'=>"  synthetic\nsecret  "])['smtp_password']==="  synthetic\nsecret  ",'catalog preserves meaningful secret whitespace outside header fields');
$principalBefore=$db->query("SELECT name,value FROM {$p}options ORDER BY name")->result_array();
foreach([41=>'A',82=>'B'] as $issuer=>$letter) {
    CompanySettingsRuntime::run($db,$p,$issuer,12,function()use($db,$p,$letter,$viewHarness) {
        $controller=new Settings();$GLOBALS['CI']->input->group='email_content';
        $GLOBALS['CI']->input->payload=['settings'=>[
            'email_header'=>'<table style="color:#112233"><tr><td><strong>Empresa '.$letter.'</strong></td></tr></table>',
            'email_signature'=>'<p>Equipe '.$letter.' {companyname}</p><a href="https://synthetic.invalid/'.$letter.'">Atendimento</a><img src="https://synthetic.invalid/tracker"><script>alert(1)</script><a href="javascript:alert(1)" onclick="bad()">inválido</a>',
            'email_footer'=>'<p onmouseover="bad()">Rodapé '.$letter.'</p><form><input name="secret"></form>',
        ]];
        $controller->index();$view=end($GLOBALS['CI']->load->views)[1];
        essentialCheck($view['saved']===true,'company email presentation saves through actual native controller');
        essentialCheck(str_contains($view['values']['email_header'],'<table')&&str_contains($view['values']['email_header'],'<strong>Empresa '.$letter.'</strong>'),'valid complete table and emphasis preserved');
        essentialCheck(str_contains($view['values']['email_signature'],'{companyname}')&&str_contains($view['values']['email_signature'],'href="https://synthetic.invalid/'.$letter.'"'),'merge field and HTTPS link remain intact');
        $all=implode('',$view['values']);
        foreach(['<script','<img','<form','<input','onclick','onmouseover','javascript:'] as $unsafe)essentialCheck(!str_contains($all,$unsafe),'unsafe mail content removed: '.$unsafe);
        $html=$viewHarness->render($view);
        essentialCheck(str_contains($html,'settings[email_signature]')&&str_contains($html,'&lt;p&gt;Equipe '.$letter),'editor renders saved HTML as text, never executable markup');
        essentialCheck(str_contains($html,'synthetic_csrf')&&str_contains($html,'_company_context'),'presentation form retains native CSRF and scoped request token');
        essentialCheck(!str_contains($html,'synthetic-cipher:')&&!str_contains($html,'password-b'),'presentation form never hydrates transport secrets');
        $before=CompanySettingsRuntime::all();
        foreach([
            ['email_header'=>'must-not-write','email_footer'=>str_repeat('x',60001)],
            ['email_header'=>'must-not-write','smtp_password'=>'overpost'],
            ['email_header'=>['invalid']],
            ['email_header'=>"invalid\0text"],
            ['email_header'=>"invalid\xff"],
        ] as $bad) {
            $GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>$bad];$controller->index();
            essentialCheck($GLOBALS['CI']->output->status===422&&CompanySettingsRuntime::all()===$before,'invalid email presentation batch fails before any partial write');
        }
        $GLOBALS['CI']->output->status=200;$db->failOn="INSERT INTO {$p}issuers_audit";
        $GLOBALS['CI']->input->payload=['settings'=>['email_header'=>'new header','email_signature'=>'new signature','email_footer'=>'new footer']];
        $controller->index();$db->failOn=null;
        essentialCheck($GLOBALS['CI']->output->status===503&&CompanySettingsRuntime::all()===$before,'audit failure rolls back every email fragment');
        $GLOBALS['CI']->settings_model->update(['settings'=>['email_footer'=>'<p>model footer '.$letter.'</p><script>alert(1)</script>']]);
        essentialCheck(CompanySettingsRuntime::get('email_footer')==='<p>model footer '.$letter.'</p>','direct isolated model call applies the same safe HTML policy');
        settingThrows(fn()=>$GLOBALS['CI']->settings_model->update(['settings'=>['smtp_host'=>"invalid\r\nhost",'email_header'=>'partial']]),'company_settings_invalid_input','direct model invalid transport fails before partial writes');
        $GLOBALS['CI']->input->group='documents';$GLOBALS['CI']->input->payload=['settings'=>['credit_note_prefix'=>'CR-'.$letter.'-','predefined_terms_credit_note'=>'Crédito '.$letter.' <script>texto literal</script>']];$controller->index();
        $view=end($GLOBALS['CI']->load->views)[1];$html=$viewHarness->render($view);
        essentialCheck($view['saved']&&$view['values']['credit_note_prefix']==='CR-'.$letter.'-','credit numbering and terms configured per company');
        essentialCheck(str_contains($html,'settings[predefined_terms_credit_note]')&&!str_contains($html,'<script>texto literal</script>'),'credit terms remain escaped literal text in view');
        $before=CompanySettingsRuntime::all();
        foreach([['credit_note_prefix'=>str_repeat('x',51),'predefined_terms_credit_note'=>'partial'],['credit_note_prefix'=>"CR\nforged"],['predefined_terms_credit_note'=>str_repeat('x',10001)]] as $bad) {
            $GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>$bad];$controller->index();
            essentialCheck($GLOBALS['CI']->output->status===422&&CompanySettingsRuntime::all()===$before,'invalid credit preferences do not partially mutate company state');
        }
        $GLOBALS['CI']->input->group='email';
        foreach([['smtp_host'=>"smtp.invalid\r\nInjected: x"],['smtp_host'=>'https://smtp.invalid/path'],['smtp_email'=>"a@example.invalid\r\nBcc:other@example.invalid"],['smtp_username'=>"username\nforged"],['smtp_password'=>str_repeat('x',4097)]] as $bad) {
            $GLOBALS['CI']->output->status=200;$GLOBALS['CI']->input->payload=['settings'=>$bad];$controller->index();
            essentialCheck($GLOBALS['CI']->output->status===422&&CompanySettingsRuntime::all()===$before,'invalid SMTP transport fields rejected before any write');
        }
    });
}
foreach([41=>'A',82=>'B'] as $issuer=>$letter) {
    essentialCheck(str_contains($store->get($issuer,'email_header'),'Empresa '.$letter),'switching settings keeps each company presentation');
    essentialCheck($store->get($issuer,'credit_note_prefix')==='CR-'.$letter.'-','credit prefix scoped to immutable company origin');
}
essentialCheck($db->query("SELECT name,value FROM {$p}options ORDER BY name")->result_array()===$principalBefore,'A/B presentation and credit configuration preserve principal legacy options');
CompanySettingsRuntime::run($db,$p,41,14,function(){
    $controller=new Settings();$GLOBALS['CI']->input->group='email_content';$GLOBALS['CI']->input->payload=[];
    settingThrows(fn()=>$controller->index(),'http_403','membership without settings.view cannot hydrate company presentation');
});
settingThrows(fn()=>CompanySettingsCatalog::fields('payment_gateways'),'company_settings_group_unavailable','catalog never enables an unaudited gateway group');
echo 'Company presentation/preferences: '.($GLOBALS['essentialChecks']-$presentationChecks).' added checks, '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
