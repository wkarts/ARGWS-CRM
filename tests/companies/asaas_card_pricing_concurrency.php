<?php
/** MySQL lock contention proves CAS and permission revalidation; no external traffic. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyCardPricing.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function pricingDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
 $in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);$db=pricingDb($p);$GLOBALS['essentialPrefix']=$p;
 try{(new \Asaas\CompanyCardPricing($db,$p))->save($in['issuer'],7,$in['account'],$in['values'],$in['revision'],$in['account_revision']);echo 'saved';}
 catch(DomainException $e){if(!in_array($e->getMessage(),['asaas_company_access_denied','asaas_company_preferences_stale'],true))throw $e;echo $e->getMessage();}exit;
}
require __DIR__.'/asaas_card_pricing.php';
$observer=pricingDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['retry','revocation'] as $mode){
 $page=$pricing->page($a,7,$accounts[$a]);$workers=[];
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$a,'account'=>$accounts[$a],'values'=>$page['settings'],'revision'=>$page['revision'],'account_revision'=>$page['account_revision']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=2,'both pricing writers wait on observed native mutex');
  if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
  $db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','pricing contenders terminate without diagnostics');}
  sort($out);essentialCheck($out===($mode==='retry'?['asaas_company_preferences_stale','saved']:['asaas_company_access_denied','asaas_company_access_denied']),'only one stale revision can save; revoked contenders cannot save');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");}
}
echo 'Card pricing concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks; actual MySQL locks, no provider.'.PHP_EOL;
