<?php
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/InvoicePaymentWriter.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function creditRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=creditRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{(new \app\services\InvoicePaymentWriter($db))->moveCompanyCredit($input['issuer'],12,'refund',$input['data'],$input['key']);echo 'saved';}
 catch(DomainException $e){if(!in_array($e->getMessage(),['company_credit_access_denied','company_credit_exceeds_balance'],true))throw $e;echo $e->getMessage();}exit(0);
}
require __DIR__.'/credits_operations.php';$observer=creditRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)",[$b]);
foreach(['view','edit'] as $cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'credit_notes',?)",[$b,$cap]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'payments','create')",[$b]);
foreach(['same-key','balance','revoked'] as $case){
 $own=$writer->issueCompanyCredit($b,7,array_replace($ci,['client_id'=>$cb]),$ck('raceissue-'.$case));$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$b]);$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($i=0;$i<2;$i++){$suffix=$case==='same-key'?'same':$i;$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'key'=>$ck('race-'.$case.$suffix),'data'=>['credit_id'=>$own,'amount'=>'70.00','date'=>'2026-10-19','reference'=>'race-'.$case.$suffix,'reason'=>'Synthetic concurrency']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'both credit writers observed waiting on actual InnoDB row lock');
  if($case==='revoked')$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$b]);$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','credit worker exits without financial diagnostics');}
  sort($out);$expected=$case==='same-key'?['saved','saved']:($case==='balance'?['company_credit_exceeds_balance','saved']:['company_credit_access_denied','company_credit_access_denied']);essentialCheck($out===$expected,'same receipt converges; balance loser and revoked waiters fail closed');
  essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_credit_entries WHERE credit_id=?",[$own])->row()->n===($case==='revoked'?0:1),'race commits one immutable entry or none after revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Company credit/refund InnoDB lock/idempotency/balance/revocation: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
