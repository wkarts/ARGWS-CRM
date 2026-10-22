<?php
declare(strict_types=1);
/** Real shared formatter/helper and language catalogs; no database or user data. */
define('BASEPATH', dirname(__DIR__).'/system/');
define('APPPATH', dirname(__DIR__).'/application/');
require APPPATH.'services/StaffIdentityTransaction.php';
$checks=0;
function messageCheck(bool $ok,string $label):void{global $checks;++$checks;if(!$ok)throw new RuntimeException('FAIL: '.$label);}
$privateError=new RuntimeException('SQL private-person@example.invalid secret-token');
$fallback='The profile could not be saved. Reopen the record and try again.';
messageCheck(!function_exists('_l'),'no language runtime in fallback fixture');
messageCheck(StaffIdentityTransaction::publicMessage($privateError)===$fallback,'missing language function has safe generic fallback');
messageCheck(StaffIdentityTransaction::publicMessage(new DomainException('issuer_staff_email_conflict'))===$fallback,'known conflict also has safe fallback without language runtime');
if(true){
    function _l($key){
        if(($GLOBALS['messageMode']??'')==='throw')throw new RuntimeException('private translation detail');
        if(($GLOBALS['messageMode']??'')==='blank')return '';
        return $GLOBALS['messageCatalog'][$key]??$key;
    }
}
function messageCatalog(string $language):array{$lang=[];require APPPATH.'language/'.$language.'/'.$language.'_lang.php';return $lang;}
function log_message($level,$text){$GLOBALS['messageLogs'][]=$text;}
function set_alert($type,$text){$GLOBALS['messageAlerts'][]=[$type,$text];}
require APPPATH.'helpers/staff_access_helper.php';
$keys=['issuer_staff_email_conflict','issuer_staff_email_invalid','issuer_staff_extension_unreviewed','issuer_staff_write_unconfirmed','issuer_staff_native_rejected','issuer_master_protected','issuer_staff_profile_field_denied'];
foreach(['english','portuguese_br'] as $language){
    $catalog=messageCatalog($language);$GLOBALS['messageCatalog']=$catalog;
    foreach($keys as $key){
        messageCheck(isset($catalog[$key])&&is_string($catalog[$key])&&trim($catalog[$key])!=='',$language.' key '.$key.' exists');
        $error=new DomainException($key);
        messageCheck(StaffIdentityTransaction::publicMessage($error)===$catalog[$key],$language.' formatter '.$key);
        $GLOBALS['messageAlerts']=[];
        issuers_staff_atomic_error($error);
        messageCheck($GLOBALS['messageAlerts']===[['warning',$catalog[$key]]],$language.' atomic helper uses shared normalized '.$key);
    }
    messageCheck(StaffIdentityTransaction::publicMessage($privateError)===$catalog['issuer_staff_write_unconfirmed'],$language.' unknown error uses generic key');
    messageCheck(!str_contains(implode(' ',$GLOBALS['messageLogs']),'private-person@example.invalid'),'atomic logs exclude raw error text');
}
$GLOBALS['messageCatalog']=[];
messageCheck(StaffIdentityTransaction::publicMessage($privateError)===$fallback,'missing key falls back instead of leaking key');
$GLOBALS['messageMode']='blank';
messageCheck(StaffIdentityTransaction::publicMessage($privateError)===$fallback,'blank translation safely falls back');
$GLOBALS['messageMode']='throw';
messageCheck(StaffIdentityTransaction::publicMessage($privateError)===$fallback,'translation failure safely falls back');
echo 'Staff identity i18n messages: '.$checks.' checks passed (English/PT-BR, shared atomic formatter, safe fallback).'.PHP_EOL;
