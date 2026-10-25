<?php
require __DIR__.'/../settings/company_settings.php';
require_once BASEPATH.'core/Model.php';
class CompanyRequestScope{
 public static bool $native=true;public static int $issuer=41;
 public static function nativeFor($ci){return self::$native&&$ci===$GLOBALS['CI'];}
 public static function context(){return new class{function issuerId(){return CompanyRequestScope::$issuer;}function staffId(){return 12;}};}
}
require APPPATH.'core/App_Model.php';
class NativeActualModel extends App_Model{}
$store->mutate(41,12,'update','default_timezone','America/Sao_Paulo');
$trace=count(hooks()->trace);$before=date_default_timezone_get();
CompanySettingsRuntime::run($db,$p,41,12,function()use($db,$p){
 $db->trans_begin();
 try{$model=new NativeActualModel();essentialCheck($db->pdo->inTransaction(),'actual App_Model preserves existing owned connection/transaction');essentialCheck(date_default_timezone_get()==='America/Sao_Paulo','native model reads exact scoped timezone');}
 finally{$db->trans_rollback();}
 CompanyRequestScope::$issuer=82;
 try{new NativeActualModel();throw new RuntimeException('cross-scope accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='native_model_settings_scope_mismatch','model cannot hydrate under mismatched settings owner');}finally{CompanyRequestScope::$issuer=41;}
});
essentialCheck(count(hooks()->trace)===$trace,'no global model hooks dispatched by sealed native branch');
try{new NativeActualModel();throw new RuntimeException('missing scope accepted');}catch(LogicException$e){essentialCheck($e->getMessage()==='native_model_settings_scope_required','lease required before model setup');}
date_default_timezone_set($before);
echo "Actual native App_Model preserves PDO transaction and scoped timezone without global hydration/hooks.\n";
