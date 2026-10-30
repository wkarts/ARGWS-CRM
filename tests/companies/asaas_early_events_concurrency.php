<?php
/** Actual two-process recovery of old early inbox events, persisted policy and lease. */
require_once dirname(__DIR__).'/issuers_core_support.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function earlyDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
$p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$db=earlyDb($p);if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyReconciliation.php';$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
$transport=function($env,$method,$path,$payload,$secret)use($in,$db){if($db->pdo->inTransaction())throw new RuntimeException('HTTP under transaction');file_put_contents($in['wire'],"GET\n",FILE_APPEND|LOCK_EX);if($path==='wallets/')return ['data'=>[['id'=>'wallet-A']],'hasMore'=>false];if(str_starts_with($path,'customers/'))return ['id'=>'cus_a','cpfCnpj'=>'11144477735','deleted'=>false];return $in['response'];};
echo json_encode((new \Asaas\CompanyReconciliation($db,$p))->runOnce(fn($v)=>base64_decode(substr($v,10),true),$transport,1),JSON_THROW_ON_ERROR);exit(0);
}
require __DIR__.'/asaas_early_events.php';$observer=earlyDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same_old_event','revoked_old_event'] as $scenario){
$case=$earlyCase($a,$ca,'cus_a','race-'.$scenario);$db->query('DELETE FROM '.$p.'asaas_company_jobs WHERE origin_id=?',[$case['origin']]);$wire=tempnam(sys_get_temp_dir(),'early-asaas-wire-');$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['wire'=>$wire,'response'=>$responses[$case['remote']]],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
$deadline=microtime(true)+20;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'two independent early-event workers observed waiting on actual financial lock: '.$scenario);
if($scenario==='revoked_old_event')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$db->trans_commit();$results=[];foreach($workers as [$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$stderr==='','early-event worker process finishes without diagnostics: '.$scenario);$results[]=json_decode($stdout,true,16,JSON_THROW_ON_ERROR);}
$rows=$db->query('SELECT * FROM '.$p.'asaas_company_jobs WHERE origin_id=?',[$case['origin']])->result_array();$payments=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$case['invoice']])->row()->n;
if($scenario==='same_old_event'){essentialCheck(array_sum(array_column($results,'completed'))===1&&count($rows)===1&&$rows[0]['state']==='completed'&&$payments===1,'two old-event repairs converge on one persisted job, lease and canonical cash receipt');essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_receipts WHERE origin_id=?',[$case['origin']])->row()->n===1,'repaired source has one provider receipt after real contention');}
else{essentialCheck(array_sum(array_column($results,'review'))===1&&count($rows)===1&&$rows[0]['state']==='review_required'&&$payments===0,'native revocation while waiting blocks both contenders and preserves review');essentialCheck(file_get_contents($wire)==='','revoked repair performs no provider requests');}
}finally{if($db->pdo->inTransaction())$db->trans_rollback();$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');unlink($wire);}}
echo 'Asaas early-event real InnoDB repair/dedupe/revocation races: '.$GLOBALS['essentialChecks'].' cumulative checks ('.$db->driver().'). Provider transport simulated.'.PHP_EOL;
