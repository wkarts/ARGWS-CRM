<?php
/** Real CI3 mysqli driver + two-process lock tests. Dedicated synthetic CI database only. */
declare(strict_types=1);
ob_start();require __DIR__.'/banco_brasil_cnab240.php';require __DIR__.'/banco_brasil_cnab400.php';ob_end_clean();
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ModuleStore.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/ModuleSchema.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/SchemaValidator.php';
require_once __DIR__.'/../modules/banco_brasil/libraries/BancoBrasil/LayoutSchema.php';
define('BASEPATH',dirname(__DIR__).'/system/');
function log_message($level,$message){} // Do not emit test SQL or payloads.
function is_php($version){return version_compare(PHP_VERSION,$version,'>=');}
function show_error($message){throw new RuntimeException('ci_database_error');}
require BASEPATH.'database/DB.php';
function nativeParams():array{return ['dbdriver'=>'mysqli','hostname'=>getenv('MYSQL_HOST')?:'127.0.0.1','port'=>3306,
 'username'=>getenv('MYSQL_USER')?:'root','password'=>getenv('MYSQL_PASSWORD')?:'root','database'=>'bb_test',
 'db_debug'=>false,'pconnect'=>false,'char_set'=>'utf8mb4','dbcollat'=>'utf8mb4_bin','save_queries'=>false,'stricton'=>true];}
mysqli_report(MYSQLI_REPORT_OFF);
$bootstrap=DB(nativeParams(),true);$bootstrap->close();
class BbNativeDatabase extends CI_DB_mysqli_driver {
 public $beforeCommit=null;
 public function trans_commit(){if($this->beforeCommit){$cb=$this->beforeCommit;$this->beforeCommit=null;$cb();}return parent::trans_commit();}
}
function nativeDb():BbNativeDatabase{$db=new BbNativeDatabase(nativeParams());if(!$db->initialize())throw new RuntimeException('mysql_unavailable');return $db;}
function nativeWire(array $c,string $sequence='000010',bool $conflicting=false,string $format='240'):string{
 global $title;$a=\BancoBrasil\ModuleStore::agreement($c);
 if($format==='400'){$wire=bb400Mutate(bb400Fixture($a),0,101,str_pad($sequence,7,'0',STR_PAD_LEFT));return $conflicting?bb400Mutate($wire,1,189,'0000000000001'):$wire;}
 $lines=explode("\r\n",rtrim((new \BancoBrasil\Cnab240Generator())->generate($a,1,'2026-10-18',[$title]),"\r\n"));
 $wire=mutateReturn(returnBytes($a,$lines),0,158,$sequence);
 return $conflicting?mutateReturn($wire,3,108,'000000000000001'):$wire;
}
if(($argv[1]??'')==='worker'){
 $db=nativeDb();$db->query('SET SESSION innodb_lock_wait_timeout=8');
 $store=new \BancoBrasil\ModuleStore($db,'bb_native_');$c=$store->connection(1,1);$format=$argv[4]??'240';$wanted=$format==='400'?\BancoBrasil\LayoutCatalog::CNAB400:\BancoBrasil\Agreement::LAYOUT_ID;
 $binding=0;foreach($store->layoutBindings(1) as $b)if($b['layout_id']===$wanted)$binding=(int)$b['id'];
 echo 'READY:'.$db->query('SELECT CONNECTION_ID() AS id')->row_array()['id']."\n";flush();
 try{$r=$store->importReturn($c,9,nativeWire($c,$argv[2],($argv[3]??'')==='conflict',$format),$binding);echo $r['duplicate']?"DUPLICATE\n":"IMPORTED\n";}
 catch(Throwable $e){echo 'REJECTED:'.$e->getMessage()."\n";}
 exit;
}
$n=0;$db=nativeDb();
foreach(\BancoBrasil\ModuleSchema::statements('bb_native_') as $sql)check($db->query($sql)!==false,'native DDL');
\BancoBrasil\LayoutSchema::upgrade($db,'bb_native_');\BancoBrasil\SchemaValidator::assertCompatible($db,'bb_native_',\BancoBrasil\LayoutSchema::requirements());check(true,'native mysqli schema verified');
$store=new \BancoBrasil\ModuleStore($db,'bb_native_');
$data=['account_id'=>'001:00452-3:000000123873-X','environment'=>'sandbox','agreement_number'=>'1234567','wallet'=>'17','variation'=>'019'];
check($store->createConnection(1,9,['id'=>1,'tax_id'=>'12ABC34501DE35','legal_name'=>'EMPRESA SINTETICA'],$data)===1,'native connection write');
$c=$store->connection(1,1);$binding=$store->registerLayout($c,9,\BancoBrasil\Agreement::LAYOUT_ID);
function waitingChild(string $sequence,string $mode='normal',string $format='240'):array{
 global $db;
 $process=proc_open([PHP_BINARY,__FILE__,'worker',$sequence,$mode,$format],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($process))throw new RuntimeException('worker_unavailable');fclose($pipes[0]);stream_set_timeout($pipes[1],10);
 try{
  $ready=trim((string)fgets($pipes[1]));check(preg_match('/^READY:([1-9][0-9]*)$/D',$ready,$m)===1,'native child connection ready');
  $wait="SELECT 1 AS waiting FROM performance_schema.data_lock_waits w
   JOIN performance_schema.threads requester ON requester.THREAD_ID=w.REQUESTING_THREAD_ID
   JOIN performance_schema.threads blocker ON blocker.THREAD_ID=w.BLOCKING_THREAD_ID
   JOIN performance_schema.data_locks requested ON requested.ENGINE=w.ENGINE AND requested.ENGINE_LOCK_ID=w.REQUESTING_ENGINE_LOCK_ID
   WHERE requester.PROCESSLIST_ID=? AND blocker.PROCESSLIST_ID=CONNECTION_ID() AND w.ENGINE='INNODB'
   AND requested.OBJECT_SCHEMA=DATABASE() AND requested.OBJECT_NAME='bb_native_bb_connections'
   AND requested.INDEX_NAME='PRIMARY' AND requested.LOCK_TYPE='RECORD' AND requested.LOCK_STATUS='WAITING' LIMIT 1";
  $maria=str_contains((string)$db->query('SELECT VERSION() AS version')->row_array()['version'],'MariaDB');
  if($maria){
   $wait="SELECT 1 AS waiting FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX requester ON requester.trx_id=w.requesting_trx_id JOIN information_schema.INNODB_TRX blocker ON blocker.trx_id=w.blocking_trx_id JOIN information_schema.INNODB_LOCKS requested ON requested.lock_id=w.requested_lock_id WHERE requester.trx_mysql_thread_id=? AND blocker.trx_mysql_thread_id=CONNECTION_ID() AND REPLACE(requested.lock_table,CHAR(96),'')=CONCAT(DATABASE(),'.bb_native_bb_connections') AND requested.lock_index='PRIMARY' AND requested.lock_type='RECORD' LIMIT 1";
  }
  $found=false;$deadline=hrtime(true)+5000000000;
  do{if($maria)usleep(150000); // Allow MariaDB's100ms-idle INFORMATION_SCHEMA lock cache to refresh.
   $r=$db->query($wait,[$m[1]]);$found=$r&&$r->row_array();if($found||!proc_get_status($process)['running'])break;usleep(20000);}while(hrtime(true)<$deadline);
  check((bool)$found,'native second worker waits on exact parent connection row');
 }catch(Throwable $e){proc_terminate($process);fclose($pipes[1]);fclose($pipes[2]);proc_close($process);throw $e;}
 return [$process,$pipes];
}
function finishNativeChild(array $child):string{[$p,$pipes]=$child;$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($p)===0,'child exit '.$error);return $out;}
$child=null;$db->beforeCommit=function()use(&$child){$child=waitingChild('000010');};
$store->importReturn($c,9,nativeWire($c),$binding);check(str_contains(finishNativeChild($child),'DUPLICATE'),'same file two workers deduplicated after lock');
$child=null;$db->beforeCommit=function()use(&$child){$child=waitingChild('000012');};
$store->importReturn($c,9,nativeWire($c,'000011'),$binding);check(str_contains(finishNativeChild($child),'IMPORTED'),'repackaged event concurrent import committed');
check((int)$db->query('SELECT COUNT(*) AS n FROM bb_native_bb_return_events')->row_array()['n']===1,'native single semantic event');
check((int)$db->query('SELECT COUNT(*) AS n FROM bb_native_bb_return_file_events')->row_array()['n']===3,'native all three file provenance records');
$child=null;$db->beforeCommit=function()use(&$child){$child=waitingChild('000014','conflict');};
$store->importReturn($c,9,nativeWire($c,'000013'),$binding);check(str_contains(finishNativeChild($child),'REJECTED:bb_return_event_conflict'),'conflicting financial payload concurrent worker rejected');
check((int)$db->query('SELECT COUNT(*) AS n FROM bb_native_bb_return_files')->row_array()['n']===4,'conflicting native file fully rolled back');
check((int)$db->query('SELECT COUNT(*) AS n FROM bb_native_bb_return_file_events')->row_array()['n']===4,'conflicting native provenance fully rolled back');
check($db->query('START TRANSACTION')!==false,'start external raw transaction');
check($db->query("INSERT INTO bb_native_bb_audit(issuer_id,actor_id,action,resource_type,created_at) VALUES(1,9,'outer_marker','test',NOW())")!==false,'caller writes own uncommitted marker');
rejects(fn()=>$store->importReturn($c,9,nativeWire($c),$binding),'native SQL transaction refused');
$observer=nativeDb();
check((int)$db->query("SELECT COUNT(*) AS n FROM bb_native_bb_audit WHERE action='outer_marker'")->row_array()['n']===1,'caller marker not rolled back');
check((int)$observer->query("SELECT COUNT(*) AS n FROM bb_native_bb_audit WHERE action='outer_marker'")->row_array()['n']===0,'caller marker not committed');$observer->close();
$db->query('ROLLBACK');
check($db->trans_begin()===true,'start CI caller transaction');rejects(fn()=>$store->importReturn($c,9,nativeWire($c),$binding),'nested CI refused');
check((new ReflectionProperty($db,'_trans_depth'))->getValue($db)===1,'caller CI depth unchanged');$db->trans_rollback();
$db->query('SET SESSION completion_type=1');rejects(fn()=>$store->importReturn($c,9,nativeWire($c),$binding),'native chained commit refused');$db->query('SET SESSION completion_type=0');
check((string)$db->query('SELECT @@SESSION.autocommit AS a')->row_array()['a']==='1','native autocommit restored');
$binding400=$store->registerLayout($c,9,\BancoBrasil\LayoutCatalog::CNAB400);
$child=null;$db->beforeCommit=function()use(&$child){$child=waitingChild('000030','normal','400');};
$store->importReturn($c,9,nativeWire($c,'000030',false,'400'),$binding400);
check(str_contains(finishNativeChild($child),'DUPLICATE'),'400 duplicate workers serialized by exact connection lock');
$child=null;$db->beforeCommit=function()use(&$child){$child=waitingChild('000032','normal','400');};
$store->importReturn($c,9,nativeWire($c,'000031',false,'400'),$binding400);
check(str_contains(finishNativeChild($child),'IMPORTED'),'400 repackaged worker provenance committed');
$child=null;$db->beforeCommit=function()use(&$child){$child=waitingChild('000034','conflict','400');};
$store->importReturn($c,9,nativeWire($c,'000033',false,'400'),$binding400);
check(str_contains(finishNativeChild($child),'REJECTED:bb_return_event_conflict'),'400 conflicting worker rolls back');
check((int)$db->query('SELECT COUNT(*) AS n FROM bb_native_bb_return_events')->row_array()['n']===2,'one distinct observation per format');
check((int)$db->query('SELECT COUNT(*) AS n FROM bb_native_bb_return_file_events')->row_array()['n']===8,'all valid 240/400 provenance retained');
echo "OK {$n} Banco do Brasil native mysqli and two-process checks\n";
