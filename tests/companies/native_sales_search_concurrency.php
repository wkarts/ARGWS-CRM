<?php
/** Two actual database sessions: no simulated locking or browser/provider calls. */
if(PHP_SAPI!=='cli')exit(2);
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanySales.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"Disposable MySQL/MariaDB DSN required.\n");exit(2);}
function searchRaceDb(string $prefix):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);}
if(($argv[1]??'')==='worker'){
    $prefix=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix))exit(2);
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=searchRaceDb($prefix);$GLOBALS['essentialPrefix']=$prefix;
    try {echo json_encode(['rows'=>(new CompanySales($db,$prefix))->relationOptions($input['issuer'],7,'estimates',$input['search'])],JSON_THROW_ON_ERROR);}
    catch(DomainException $error){echo json_encode(['error'=>$error->getMessage()],JSON_THROW_ON_ERROR);}exit;
}
require __DIR__.'/sales.php';$start=$GLOBALS['essentialChecks'];$observer=searchRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$settings->mutate($a,7,'update','estimate_prefix','CONC-');$settings->mutate($a,7,'update','estimate_number_format','2');
$payload=array_replace($salesInput,['client_id'=>$ca,'date'=>'2026-01-31','due_date'=>'2026-02-10']);$id=$sales->create($a,7,'estimates',$payload,hash('sha256','native-search-race'));
function searchRace(int $issuer,string $search,callable $beforeRelease):array
{
 global $db,$p,$observer,$blocker;
 $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$pipes=[];$process=null;
 try{
  $process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($process))throw new RuntimeException('worker unavailable');
  fwrite($pipes[0],json_encode(['issuer'=>$issuer,'search'=>$search],JSON_THROW_ON_ERROR));fclose($pipes[0]);
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=1)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiters>=1,'real search session observed waiting on native lock');
  $beforeRelease();$db->trans_commit();$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  essentialCheck(proc_close($process)===0&&$err==='','search worker completed without diagnostics');$process=null;
  return json_decode($out,true,16,JSON_THROW_ON_ERROR);
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();if(is_resource($process)){if(proc_get_status($process)['running'])proc_terminate($process);foreach($pipes as$pipe)if(is_resource($pipe))fclose($pipe);proc_close($process);}}
}
$result=searchRace($a,'CONC-2027',function()use($db,$p,$id){
    $row=$db->query('SELECT payload_json FROM '.$p.'company_sales_documents WHERE id=?',[$id])->row_array();$payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);
    $payload['date']='2027-01-31';$payload['due_date']='2027-02-10';$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
    $hash=hash('sha256',json_encode(['estimates',$payload],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    $db->query('UPDATE '.$p.'company_sales_documents SET payload_json=?,input_hash=? WHERE id=?',[$json,$hash,$id]);
});
essentialCheck(count($result['rows']??[])===1&&(int)$result['rows'][0]['id']===$id&&str_starts_with($result['rows'][0]['name'],'CONC-2027/'),'search sees validated committed date after waiting, not earlier state');
$result=searchRace($a,'CONC-2027',fn()=>$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7'));
$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
essentialCheck(($result['error']??'')==='company_sales_denied','revocation while waiting denies search before private hydration');
echo 'Native sales search real contention: '.($GLOBALS['essentialChecks']-$start)." added checks. No external transport.\n";
