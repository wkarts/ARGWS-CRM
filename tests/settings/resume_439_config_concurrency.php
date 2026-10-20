<?php
/** Real independently connected writers wait on the native mutex; late grant revocation must win. */
declare(strict_types=1);
if(($argv[1]??'')==='worker'){
    require dirname(__DIR__).'/issuers_core_support.php';
    require_once APPPATH.'services/CompanyConnectApi.php';require_once APPPATH.'services/CompanyPusherProfile.php';require_once APPPATH.'services/CompanyMicrosoftMailOAuth.php';
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$mode=$argv[3]??'';$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$GLOBALS['essentialPrefix']=$p;
    $seal=static fn($v)=>'sealed:'.base64_encode($v);$open=static fn($v)=>base64_decode(substr($v,7),true);
    try{
        if($mode==='connect')(new CompanyConnectApi($db,$p,null,['synthetic'=>['label'=>'Synthetic','url'=>'https://connect.example.invalid']]))->save(41,12,0,'synthetic','instance-a','synthetic-secret',$seal);
        elseif($mode==='pusher')(new CompanyPusherProfile($db,$p))->save(41,12,0,'100041','app-key-41','sa1','synthetic-secret',$seal);
        elseif($mode==='microsoft')(new CompanyMicrosoftMailOAuth($db,$p))->save(41,12,0,'11111111-1111-4111-8111-111111111111','22222222-2222-4222-8222-222222222222','synthetic-secret',$seal,$open);
        else exit(2);
        echo'applied';
    }catch(DomainException$e){if(!in_array($e->getMessage(),['company_connect_api_denied','company_pusher_denied','company_microsoft_mail_access_denied'],true))throw$e;echo'denied';}exit;
}
require __DIR__.'/resume_439_support.php';
if($db->driver()!=='mysql'){fwrite(STDERR,"Real MariaDB/MySQL required for config concurrency.\n");exit(2);}
require_once APPPATH.'services/CompanyConnectApi.php';require_once APPPATH.'services/CompanyPusherProfile.php';require_once APPPATH.'services/CompanyMicrosoftMailOAuth.php';require_once APPPATH.'services/CompanyProjectsSchema.php';
if(!$db->table_exists($p.'mail_queue'))$db->query('CREATE TABLE '.$p.'mail_queue(id int NOT NULL PRIMARY KEY) ENGINE=InnoDB');
resume439Schema(CompanyProjectsSchema::class);resume439Schema(CompanyPusherSchema::class);resume439Schema(CompanyConnectApiSchema::class);resume439Schema(CompanyMicrosoftMailOAuthSchema::class);$store->mutate(41,7,'update','smtp_email','a@example.invalid',null);
$start439=$GLOBALS['essentialChecks'];$observer439=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker439=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$spawn439=static function(string$mode)use($p):array{$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p,$mode],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('worker setup failed');fclose($pipes[0]);return[$proc,$pipes];};
$join439=static function(array$worker):string{[$proc,$pipes]=$worker;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','independent config worker has no diagnostics');return$out;};
foreach(['connect'=>['connect-api-connector','configure','company_connect_api_accounts'],'pusher'=>['pusher','configure','company_pusher_profiles'],'microsoft'=>['settings','edit','company_microsoft_mail_accounts']]as$mode=>[$feature,$cap,$table]){
    $before439=$db->query('SELECT * FROM '.$p.$table)->result_array();$audit439=$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array();
    $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers439=[$spawn439($mode),$spawn439($mode)];
    try{$deadline439=microtime(true)+15;do{$waiters439=essentialLockWaiters($observer439,$blocker439);if($waiters439>=2)break;usleep(20000);}while(microtime(true)<$deadline439);essentialCheck($waiters439>=2,'two real '.$mode.' writers observed waiting on the native mutex');
        $db->query('DELETE FROM '.$p.'issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature=? AND capability=?',[$feature,$cap]);$db->trans_commit();
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
    essentialCheck(array_map($join439,$workers439)===['denied','denied'],'late '.$mode.' grant revocation wins after lock wait');essentialCheck($db->query('SELECT * FROM '.$p.$table)->result_array()===$before439&&$db->query('SELECT * FROM '.$p.'issuers_audit ORDER BY id')->result_array()===$audit439,'denied '.$mode.' writers persist neither credential nor success audit');
    $db->query('INSERT INTO '.$p.'issuers_resource_permissions VALUES(41,12,?,?)',[$feature,$cap]);
}
echo 'Reconstructed439 config native races: '.($GLOBALS['essentialChecks']-$start439).' added checks / 3 scenarios / 6 observed waiting writers; no provider traffic.'.PHP_EOL;
