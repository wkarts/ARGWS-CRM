<?php
/** Regression: an identity encrypt callback must never persist the token-bearing JSON envelope. */
declare(strict_types=1);
require __DIR__.'/resume_439_support.php';
require_once APPPATH.'services/CompanyConnectApi.php';
resume439Schema(CompanyConnectApiSchema::class);
$profile439=new CompanyConnectApi($db,$p,null,['synthetic'=>['label'=>'Synthetic','url'=>'https://connect.example.invalid']]);
$before439=$db->query('SELECT * FROM '.$p.'company_connect_api_accounts')->result_array();
settingThrows(fn()=>$profile439->save(41,12,0,'synthetic','instance-a','must-never-be-plaintext',static fn(string$v):string=>$v),'company_connect_api_secret_required','identity encryption is rejected before token-bearing JSON persistence');
essentialCheck($db->query('SELECT * FROM '.$p.'company_connect_api_accounts')->result_array()===$before439,'failed encryption creates no account/token row');
settingThrows(fn()=>$profile439->save(41,12,0,'synthetic','instance-a','must-never-be-plaintext',static fn(string$v):string=>'must-never-be-plaintext'),'company_connect_api_secret_required','encrypt callback returning raw token is rejected');
essentialCheck($db->query('SELECT * FROM '.$p.'company_connect_api_accounts')->result_array()===$before439,'raw-token encryption failure is atomic');
$profile439->save(41,12,0,'synthetic','instance-a','synthetic-private-token',$seal439);
essentialCheck($profile439->page(41,12)['token_configured'],'valid sealed envelope is preserved');
$saved439=$db->query('SELECT * FROM '.$p.'company_connect_api_accounts WHERE issuer_id=41')->row_array();
settingThrows(fn()=>$profile439->save(41,12,1,'synthetic','instance-a','rotated-secret',static fn(string$v):string=>$v),'company_connect_api_secret_required','identity encryption is rejected on credential rotation too');
essentialCheck($db->query('SELECT * FROM '.$p.'company_connect_api_accounts WHERE issuer_id=41')->row_array()===$saved439,'failed rotation retains sealed prior credentials and revision');
echo 'Reconstructed439 Connect encryption regression: 7 checks passed ('.$db->driver().').'.PHP_EOL;
