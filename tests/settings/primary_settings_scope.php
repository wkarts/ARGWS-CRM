<?php
require __DIR__.'/../native_context.php';
// Real middleware, ACL, context token and runtime; no legacy provider/model execution.
$session=new ContextSession();$session->data=['staff_user_id'=>7,'staff_logged_in'=>true];
foreach(['email','invoices'] as $group){
    $ci=contextEnvironment($f,$session,'settings','index');$ci->input->group=$group;
    CompanyRequestScope::beforeHydration($ci);
    essentialCheck(CompanyRequestScope::context()->isPrimary()&&!CompanyRequestScope::nativeFor($ci),'principal '.$group.' reaches its original form');
    essentialCheck(CompanySettingsRuntime::active()&&CompanySettingsRuntime::isPrimary(),'legacy form retains bound runtime and ACL');
    $token=CompanyRequestScope::token();CompanyRequestScope::finish();
    $ci=contextEnvironment($f,$session,'settings','index','POST');$ci->input->group=$group;$_POST=['_company_context'=>'obsolete'];
    expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci),409);
    $ci=contextEnvironment($f,$session,'settings','index','POST');$ci->input->group=$group;$_POST=['_company_context'=>$token];
    CompanyRequestScope::beforeHydration($ci);
    essentialCheck(!CompanyRequestScope::nativeFor($ci)&&CompanySettingsRuntime::isPrimary(),'valid primary POST retains original writer');
    // Skip constructor gateway discovery only; execute the original update method
    // against real disposable storage and the already bound request runtime.
    $model=(new ReflectionClass(Settings_model::class))->newInstanceWithoutConstructor();
    $key=$group==='email'?'smtp_host':'next_invoice_number';$value=$group==='email'?'primary.smtp.invalid':'42';
    $otherBefore=$db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=54')->row_array();
    essentialCheck($model->update(['settings'=>[$key=>$value]])>0,'primary '.$group.' original save succeeds');
    essentialCheck(CompanySettingsRuntime::get($key)===$value,'primary original option persisted');
    essentialCheck($db->query('SELECT settings_json FROM '.$p.'issuers_registry WHERE id=54')->row_array()===$otherBefore,'primary writer preserves secondary configuration');
    CompanyRequestScope::finish();
}
echo "Real primary Settings dispatch retains runtime, ACL and stale context rejection.\n";
$ci=contextEnvironment($f,$session,'banco_brasil','index');$ci->router->module='banco_brasil';
CompanyRequestScope::beforeHydration($ci);$bbToken=CompanyRequestScope::token();$bbCsrf=CompanyRequestScope::nativeCsrfToken($ci);CompanyRequestScope::finish();
foreach(['GET','POST'] as $verb){
    $ci=contextEnvironment($f,$session,'banco_brasil','save_channel_preferences',$verb);$ci->router->module='banco_brasil';
    if($verb==='GET'){expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci),403);continue;}
    $_POST=['_company_context'=>$bbToken,'_native_company_csrf'=>$bbCsrf];CompanyRequestScope::beforeHydration($ci);
    essentialCheck(CompanyRequestScope::nativeFor($ci),'BB preference POST admitted only in sealed native context');CompanyRequestScope::finish();
}
$ci=contextEnvironment($f,$session,'banco_brasil','save_channel_preferences','POST');$ci->router->module='banco_brasil';$_POST=['_company_context'=>'obsolete','_native_company_csrf'=>$bbCsrf];expectStatus(fn()=>CompanyRequestScope::beforeHydration($ci),409);
echo "BB actual middleware: GET rejected, sealed POST admitted, stale context rejected.\n";
