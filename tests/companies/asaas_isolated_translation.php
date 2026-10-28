<?php
/** Fresh-process isolated view render: no synthetic _l, hooks, App or optional modules. */
declare(strict_types=1);
define('BASEPATH',dirname(__DIR__,2).'/system/');define('APPPATH',dirname(__DIR__,2).'/application/');
require BASEPATH.'helpers/url_helper.php';require BASEPATH.'helpers/form_helper.php';
require APPPATH.'services/CompanyUiTranslation.php';
require dirname(__DIR__,2).'/modules/asaas/libraries/Asaas/CompanyPreferences.php';
function base_url($path=''){return 'http://127.0.0.1:48000/'.$path;}
function isolatedCheck(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);$GLOBALS['isolatedChecks']++;}
$GLOBALS['isolatedChecks']=0;
isolatedCheck(!function_exists('_l')&&!function_exists('hooks'),'legacy translation and hooks remain absent');
$render=static function(string $locale,bool $ready=true):string{
    $translate=CompanyUiTranslation::translator($locale);
    $e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
    $environmentLabel=static fn($value)=>$translate($value==='sandbox'?'asaas_company_environment_sandbox':'asaas_company_environment_production');
    $zero=str_repeat('0',64);$profile=['label'=>'<literal label>','description'=>'Fatura {invoice_number}','allow_pix'=>'1','allow_boleto'=>'1','allow_card'=>'1','revision'=>$zero];
    $preferences=['ready'=>$ready,'active_environment'=>null,'revision'=>$zero,'profiles'=>['production'=>$profile,'sandbox'=>$profile]];
    $preferences_active_tab=null;$accounts=[];$can_configure=true;$action_url='http://127.0.0.1:48000/admin/settings/asaas';$preferences_csrf='synthetic-csrf';
    $hidden=static function(array $values=[]):void{foreach($values as $key=>$value)echo '<input type="hidden" name="'.htmlspecialchars($key,ENT_QUOTES).'" value="'.htmlspecialchars((string)$value,ENT_QUOTES).'">';};
    ob_start();include APPPATH.'views/admin/companies/asaas_preferences.php';return ob_get_clean();
};
$pt=$render('portuguese_br');$en=$render('english');
isolatedCheck(str_contains($pt,'Preferências por ambiente')&&str_contains($pt,'Homologação')&&str_contains($pt,'Nenhum selecionado'),'real empty-account preferences render PT-BR in a clean process');
isolatedCheck(str_contains($en,'Preferences by environment')&&str_contains($en,'Production')&&str_contains($en,'Sandbox'),'same view uses the actual English catalog');
isolatedCheck(substr_count($pt,'value="save_profile"')===2&&str_contains($pt,'&lt;literal label&gt;')&&!str_contains($pt,'<literal label>'),'native forms and escaping survive isolated translation');
isolatedCheck(!str_contains($render('portuguese_br',false),'<form'),'unready profile stays fail-closed');
$translator=CompanyUiTranslation::translator();
isolatedCheck($translator('asaas_company_preferences_save','Homologação')==='Salvar preferências de Homologação','catalog placeholders are formatted');
isolatedCheck($translator('missing_synthetic_key')==='missing_synthetic_key','missing key remains visible instead of false translation');
try{CompanyUiTranslation::translator('../untrusted');throw new RuntimeException('unsafe locale accepted');}catch(InvalidArgumentException $expected){$GLOBALS['isolatedChecks']++;}
isolatedCheck(!function_exists('_l')&&!function_exists('hooks')&&!class_exists('App',false),'render never hydrates legacy helpers or application');
echo 'Isolated Asaas translation: '.$GLOBALS['isolatedChecks'].' checks passed; actual catalogs/view, no legacy helper/provider.'.PHP_EOL;
