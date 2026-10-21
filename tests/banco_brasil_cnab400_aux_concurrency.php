<?php
/** Two real processes, observable InnoDB waiters; raw import/replay and revocation. */
require_once __DIR__.'/issuers_core_support.php';
require_once __DIR__.'/support/BbPdoDatabase.php';
require_once APP_MODULES_PATH.'banco_brasil/libraries/BancoBrasil/ModuleStore.php';
use BancoBrasil\ModuleStore;
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$w=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$d=new BbPdoDatabase(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')));$s=new ModuleStore($d,$p);
 try{$r=$s->importReturn($s->connection($w['connection'],$w['issuer']),7,base64_decode($w['wire'],true),$w['binding']);echo json_encode($r,JSON_THROW_ON_ERROR);}catch(DomainException$e){if($e->getMessage()!=='bb_auxiliary_import_access_denied')throw$e;echo'denied';}exit(0);
}
require __DIR__.'/banco_brasil_cnab400_aux_mysql.php';
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['same_file','different_file_same_events','revocation']as$case){$raceWire=bb400AuxWire($auxa,['01','10'],[],[$case==='same_file'?21:($case==='revocation'?23:22)]);$before=$auxCounts();$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$a,'connection'=>$connection,'binding'=>$auxbinding,'wire'=>base64_encode($case==='different_file_same_events'&&$i===1?bb400Mutate($raceWire,0,101,'0000022'):$raceWire)],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'aux '.$case.' observes two actual InnoDB waiting writers');
  if($case==='revocation')$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$db->trans_commit();$out=[];
  foreach($workers as[$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','aux '.$case.' worker exits without diagnostic');}
  if($case==='revocation'){essentialCheck($out===['denied','denied']&&$auxCounts()===$before,'both blocked imports respect committed revocation and preserve all financial rows');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');}
  else{$rows=array_map(fn($v)=>json_decode($v,true,16,JSON_THROW_ON_ERROR),$out);essentialCheck($case==='same_file'?($rows[0]['id']===$rows[1]['id']&&count(array_filter($rows,fn($r)=>$r['duplicate']))===1):($rows[0]['id']!==$rows[1]['id']&&!$rows[0]['duplicate']&&!$rows[1]['duplicate']),'concurrent imports converge with correct file identity');$parentRows=$db->query('SELECT COUNT(*) n FROM '.$p.'bb_return_events WHERE remote_id=?',[$auxa->ourNumber($case==='same_file'?'21':'22')])->row()->n;essentialCheck((int)$parentRows===3,'concurrent imports persist one parent and two semantic auxiliaries');foreach(['invoices','invoicepaymentrecords','credits','company_financial_events','company_payment_receipts']as$t)essentialCheck($auxCounts()[$t]===$before[$t],'concurrent raw import never changes '.$t);}
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'CNAB400 auxiliary concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks; three two-waiter races.'.PHP_EOL;
