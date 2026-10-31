<?php
require __DIR__.'/native_consent_controller.php';
require_once APPPATH.'services/CompanySettingsStorage.php';
class NativeGdprInput extends NativeContactHttpInput{public array $query=[];public function get($key){return $this->query[$key]??null;}}
$gdpr->input=new NativeGdprInput();$gdpr->input->query=['page'=>'general'];
$store=new CompanySettingsStorage($db,$p);
$gdpr->input->verb='GET';try{$gdpr->enable();throw new RuntimeException('GET enable accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()>=400,'GET no longer mutates company GDPR policy');}
$gdpr->input->verb='POST';$gdpr->input->body=['_native_company_csrf'=>'synthetic-native-csrf'];$gdpr->enable();
essentialCheck($store->get($a,'enable_gdpr')==='1'&&$store->get($b,'enable_gdpr')==='','enable uses original POST route without B/global write');
$gdpr->input->body=['_native_company_csrf'=>'synthetic-native-csrf','settings'=>['show_gdpr_in_customers_menu'=>'1']];$gdpr->save();
essentialCheck($store->get($a,'show_gdpr_in_customers_menu')==='1'&&$GLOBALS['nativeGdprRedirect'][1]===303,'original native save persists own fields then redirects');
$gdpr->input->body['settings']=['smtp_password'=>'bad'];try{$gdpr->save();throw new RuntimeException('overpost accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===422,'native save rejects unrelated credential fields');}
$gdpr->input->query=['page'=>'../../unsafe'];try{$gdpr->save();throw new RuntimeException('page traversal accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===422,'no dynamic page inclusion from untrusted path');}
require_once APPPATH.'services/NativeCompanyPrivacyRequests.php';
$db->query("CREATE TABLE {$p}gdpr_requests(id INTEGER PRIMARY KEY,clientid INT NOT NULL,contact_id INT NOT NULL,lead_id INT NOT NULL,request_type TEXT,status TEXT,request_date TEXT,request_from TEXT,description TEXT)");
NativeCompanyPrivacySchema::ensure($db,$p);
$db->query("INSERT INTO {$p}gdpr_requests VALUES(501,?,?,0,'account_removal','pending','2026-01-01 00:00:00','Synthetic','Synthetic request')",[$client,$id]);
$db->query("INSERT INTO {$p}company_native_gdpr_request_owners VALUES(501,?,'contact',?,1,?,?)",[$a,$id,hash('sha256','controller-nonce'),hash('sha256','controller-hash')]);
$gdpr->input->verb='GET';$gdpr->input->body=['revision'=>'1','_native_company_csrf'=>'synthetic-native-csrf'];
try{$gdpr->change_removal_request_status('501','refused');throw new RuntimeException('GET status accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()>=400,'status GET does not mutate');}
$gdpr->input->verb='POST';ob_start();try{$gdpr->change_removal_request_status('501','refused');$response=json_decode(ob_get_contents(),true,512,JSON_THROW_ON_ERROR);}finally{ob_end_clean();}
essentialCheck($response===['success'=>true,'revision'=>'2','status'=>'refused'],'native POST returns committed revision');
try{$gdpr->change_removal_request_status('501','removed');throw new RuntimeException('stale status accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()===409,'stale status fails without deletion');}
CompanyRequestScope::$issuer=$b;$gdpr->input->body['revision']='2';try{$gdpr->change_removal_request_status('501','removed');throw new RuntimeException('foreign status accepted');}catch(NativeContactHttpError$e){essentialCheck($e->getCode()>=400,'foreign status cannot mutate request');}CompanyRequestScope::$issuer=$a;
essentialCheck((new NativeCompanyPrivacyRequests($db,$p))->list($a,7)[0]['status']==='refused','foreign and stale attempts preserve request');
echo "Native GDPR original controller POST preferences and removal status isolation passed.\n";
