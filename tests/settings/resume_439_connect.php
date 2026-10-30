<?php
/** Reconstructed439 Connect profile/restart proof; no real WhatsApp request. */
declare(strict_types=1);
require __DIR__.'/resume_439_support.php';
require_once APPPATH.'services/CompanyConnectInstanceOperations.php';
resume439Schema(CompanyConnectApiSchema::class);
resume439Schema(CompanyConnectInstanceOperationsSchema::class);
$start=$GLOBALS['essentialChecks'];$calls=[];$failRestart=false;$differentIdentity=false;$during=null;
$endpoints=['synthetic'=>['label'=>'Synthetic transport','url'=>'https://connect.example.invalid']];
$client439=new CompanyConnectApiClient(function($url,$method,$payload,$token)use(&$calls,&$failRestart,&$differentIdentity,&$during,$db){
    essentialCheck(!$db->pdo->inTransaction(),'Connect credential-bearing network is outside SQL transaction');
    $calls[]=[$url,$method,$payload,$token];
    if($during){$fn=$during;$during=null;$fn();}
    $instance=str_contains($url,'instance-b')?'instance-b':'instance-a';
    if(str_contains($url,'connectionState'))return['instance'=>['instanceName'=>$instance,'state'=>'open']];
    if(str_contains($url,'fetchInstances'))return[['id'=>$differentIdentity?'00000000-0000-4000-8000-000000000099':'00000000-0000-4000-8000-000000000041','name'=>$instance,'connectionStatus'=>'open','ownerJid'=>'5571999999999:1@s.whatsapp.net']];
    if(str_contains($url,'restart')){if($failRestart)throw new RuntimeException('synthetic secret provider error');return['instance'=>['instanceName'=>$instance,'status'=>'connecting']];}
    throw new RuntimeException('unexpected synthetic path');
});
$profile439=new CompanyConnectApi($db,$p,$client439,$endpoints);
foreach([41=>'a',82=>'b']as$owner=>$suffix){$profile439->save($owner,12,0,'synthetic','instance-'.$suffix,'private-token-'.$suffix,$seal439);essentialCheck($profile439->page($owner,12)['status']==='configured','own profile is configured, not assumed connected');}
essentialCheck(!str_contains(json_encode($profile439->page(41,12)),'private-token'),'profile DTO does not expose token');
settingThrows(fn()=>$profile439->save(82,12,1,'synthetic','instance-a','private-token-b',$seal439),'company_connect_api_conflict','provider instance cannot belong to A and B');
settingThrows(fn()=>$profile439->save(41,13,1,'synthetic','instance-a','attacker',$seal439),'company_connect_api_denied','global admin has no inferred connector authority');
settingThrows(fn()=>$profile439->save(41,12,0,'synthetic','instance-a','changed',$seal439),'company_connect_api_stale','configuration uses optimistic revision');
$profile439->check(41,12,1,$open439);$profile439->check(82,12,1,$open439);
essentialCheck($profile439->page(41,12)['status']==='active'&&$profile439->page(82,12)['status']==='active','A/B independently verified through state plus immutable identity');
essentialCheck($calls[0][1]==='GET'&&$calls[1][1]==='GET'&&$calls[0][3]==='private-token-a'&&$calls[2][3]==='private-token-b','credentials reach only their own state/identity requests');
$stored439=$db->query('SELECT * FROM '.$p.'company_connect_api_accounts WHERE issuer_id=41')->row_array();$stored439['endpoint']=$endpoints['synthetic'];
essentialCheck(CompanyConnectApi::open($open439,$stored439['token_cipher'],$stored439)==='private-token-a','encrypted envelope opens only against exact owner/endpoint/instance');
$forged439=$stored439;$forged439['issuer_id']=82;settingThrows(fn()=>CompanyConnectApi::open($open439,$stored439['token_cipher'],$forged439),'company_connect_api_secret_required','ciphertext cannot be rebound across companies');
$restart439=new CompanyConnectInstanceOperations($db,$p,$client439,$endpoints);$key439=hash('sha256','resume439-connect-a');
essentialCheck($restart439->restart(41,12,1,$key439,$open439)==='accepted','explicit restart records provider acceptance');$before439=count($calls);
essentialCheck($restart439->restart(41,12,1,$key439,$open439)==='accepted'&&count($calls)===$before439,'accepted restart retry never resends POST');
settingThrows(fn()=>$restart439->restart(82,12,1,$key439,$open439),'company_connect_api_conflict','restart request key cannot cross company');
$failRestart=true;$uncertain439=hash('sha256','resume439-connect-uncertain');settingThrows(fn()=>$restart439->restart(41,12,1,$uncertain439,$open439),'company_connect_api_unavailable','post-send interruption exposes sanitized uncertainty');$before439=count($calls);
essentialCheck($restart439->restart(41,12,1,$uncertain439,$open439)==='uncertain'&&count($calls)===$before439,'uncertain restart is never blindly retried');
$failRestart=false;essentialCheck($restart439->observe(41,12,$uncertain439,$open439)==='open','safe recovery performs only authenticated observation');
$observed439=$db->query('SELECT * FROM '.$p.'company_connect_instance_operations WHERE request_key=?',[$uncertain439])->row_array();essentialCheck($observed439['status']==='uncertain'&&$observed439['observation_hash']!=='','observation preserves uncertainty and has durable evidence');
$differentIdentity=true;settingThrows(fn()=>$profile439->check(41,12,1,$open439),'company_connect_api_identity_changed','remote identity drift closes account instead of rebinding');
essentialCheck($profile439->page(41,12)['status']==='review'&&$profile439->page(82,12)['status']==='active','identity drift blocks only own company');$differentIdentity=false;
$before439=count($calls);settingThrows(fn()=>$restart439->restart(41,12,1,hash('sha256','resume439-closed'),$open439),'company_connect_api_connection_required','review account cannot send new restart');essentialCheck(count($calls)===$before439,'review block occurs before provider calls');
$before439=$db->query('SELECT * FROM '.$p.'company_connect_api_accounts WHERE issuer_id=82')->row_array();$db->failOn='INSERT INTO '.$p.'issuers_audit';settingThrows(fn()=>$profile439->revoke(82,12,1),'issuer_storage_failed','audit failure rolls back revoke');$db->failOn=null;essentialCheck($db->query('SELECT * FROM '.$p.'company_connect_api_accounts WHERE issuer_id=82')->row_array()===$before439,'failed audit preserves previous B profile');
$profile439->revoke(82,12,1);essentialCheck($profile439->page(82,12)['status']==='revoked'&&!$profile439->page(82,12)['token_configured'],'revoke erases usable own token');
$profile439->save(82,12,2,'synthetic','instance-b','rotated-private-token-b',$seal439);$during=fn()=>$profile439->revoke(82,12,3);$before439=count($calls);settingThrows(fn()=>$profile439->check(82,12,3,$open439),'company_connect_api_connection_required','revocation during state GET prevents next credential-bearing identity read');essentialCheck(count($calls)===$before439+1&&str_contains($calls[array_key_last($calls)][0],'connectionState'),'grant/config revalidation runs before the second remote read');
foreach(['http://connect.invalid','https://user:pass@connect.invalid','https://connect.invalid/a/../b','https://connect.invalid?x=1']as$url)settingThrows(fn()=>CompanyConnectApiClient::endpoints(['bad'=>['label'=>'Bad','url'=>$url]]),'company_connect_api_endpoint_invalid','infrastructure endpoint rejects unsafe URL '.$url);
echo 'Reconstructed439 Connect profile/restart: '.($GLOBALS['essentialChecks']-$start).' additional checks passed ('.$db->driver().'); simulated transport only.'.PHP_EOL;
