<?php
if(($argv[1]??'')==='worker-race'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixFutureWorker.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);if(!preg_match('~^/tmp/pixworker_[A-Za-z0-9]+$~D',$in['marker']))exit(2);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $transport=function($env,$method,$path,$payload,$secret)use($in){if($method==='POST'&&$path==='payments'){file_put_contents($in['marker'],"sent\n",FILE_APPEND|LOCK_EX);throw new RuntimeException('synthetic lost reply');}if($path==='wallets/')return ['data'=>[['id'=>'wallet-B']],'hasMore'=>false];if($path==='customers/cus_b')return ['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false];if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>[$in['answer']],'hasMore'=>false,'totalCount'=>1];if($path==='pix/automatic/authorizations/'.$in['answer']['id'])return $in['answer'];throw new RuntimeException('unexpected path');};
 echo json_encode((new Asaas\CompanyPixFutureWorker($db,$p,fn()=>'2026-10-09'))->tick(fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false,$transport,1),JSON_THROW_ON_ERROR);exit;
}
require __DIR__.'/asaas_pix_future_schedules.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixFutureWorker.php';
Asaas\CompanyPixFutureWorkerSchema::ensure($db,$p,true);unset($db->data_cache['table_names']);
$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.9','asaas']);
$start=$GLOBALS['essentialChecks'];$worker=new Asaas\CompanyPixFutureWorker($db,$p,fn()=>'2026-08-07');
$child=$recurring->run($b,7,$schedule,'2026-07-19');$posts=0;$workerCalls=0;$during=null;
$workerTransport=function($env,$method,$path,$payload,$secret)use($transport,$mock,&$responses,&$posts,&$workerCalls,&$during){$workerCalls++;if(str_starts_with($path,'payments?externalReference=')){parse_str(parse_url($path,PHP_URL_QUERY),$query);return ['data'=>array_values(array_filter($responses,fn($r)=>($r['externalReference']??null)===$query['externalReference'])),'hasMore'=>false];}if($method==='POST'&&$path==='payments'){$posts++;$result=$mock($env,$method,$path,$payload,$secret);if($during){$fn=$during;$during=null;$fn();}return $result;}if(str_starts_with($path,'payments/'))return $mock($env,$method,$path,$payload,$secret);return $transport($env,$method,$path,$payload,$secret);};
$configure=fn($revision,$enabled,$confirm=true)=>$worker->configure($b,7,$origin,$review['revision'],$revision,$enabled,$confirm);
essentialCheck($worker->tick($dec,$workerTransport)===[]&&$workerCalls===0,'prepared binding does not activate automatic financial work');
essentialThrows(fn()=>$configure(str_repeat('0',64),true,false),'asaas_company_confirmation_required','worker requires separate explicit approval');
essentialThrows(fn()=>$worker->configure($a,7,$origin,$review['revision'],str_repeat('0',64),true,true),'asaas_pix_reservation_denied','foreign origin cannot be activated');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$configure(str_repeat('0',64),true),'issuer_storage_failed','activation and audit are atomic');$db->failOn=null;
essentialCheck($worker->page($b,7,$origin)===null,'audit rollback leaves no worker');
$configure(str_repeat('0',64),true);$policy=$worker->page($b,7,$origin);
essentialThrows(fn()=>$configure(str_repeat('0',64),false),'asaas_company_stale','old policy cannot overwrite a newer activation');
$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');$before=$workerCalls;$denied=$worker->tick($dec,$workerTransport);essentialCheck($denied[0]['state']==='authority_or_identity_review'&&$workerCalls===$before,'persisted worker actor must remain active before HTTP');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
$db->query('UPDATE '.$p.'asaas_company_pix_future_workers SET next_attempt=? WHERE root_origin=?',['2000-01-01 00:00:00',$origin]);
$result=$worker->tick($dec,$workerTransport);essentialCheck($posts===1&&$result[0]['state']==='payment_observed','activated worker sends one canonical occurrence and observes pending');
essentialCheck($worker->tick($dec,$workerTransport)===[],'bounded next attempt prevents busy retry');
$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND channel=?',[$child,'api'])->row()->remote_id;
$responses[$remote]['status']='RECEIVED';$responses[$remote]['paymentDate']='2026-08-19';$responses[$remote]['creditDate']='2026-08-19';
$wake=fn()=>$db->query('UPDATE '.$p.'asaas_company_pix_future_workers SET next_attempt=? WHERE root_origin=?',['2000-01-01 00:00:00',$origin]);
$wake();$result=$worker->tick($dec,$workerTransport);essentialCheck($posts===1&&$result[0]['state']==='native_receipt_confirmed','worker posts verified receipt through native writer without another POST');
$payments=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$child])->result_array();$wake();$result=$worker->tick($dec,$workerTransport);essentialCheck(count($payments)===1&&$result[0]['state']==='idle'&&$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$child])->result_array()===$payments,'settled occurrence is not posted again');
$configure($policy['revision'],false);$before=$workerCalls;$wake();essentialCheck($worker->tick($dec,$workerTransport)===[]&&$workerCalls===$before,'disabled worker makes no remote call');
// The next month tests policy revocation while HTTP is in flight.
$child2=$recurring->run($b,7,$schedule,'2026-08-19');$worker=new Asaas\CompanyPixFutureWorker($db,$p,fn()=>'2026-09-09');
$policy=$worker->page($b,7,$origin);$worker->configure($b,7,$origin,$review['revision'],$policy['revision'],true,true);$policy=$worker->page($b,7,$origin);
$during=fn()=>$worker->configure($b,7,$origin,$review['revision'],$policy['revision'],false,true);
$result=$worker->tick($dec,$workerTransport);essentialCheck($result[0]['state']==='result_unconfirmed'&&$posts===2,'disable during POST invalidates lease and retains unknown result');
essentialCheck((int)$worker->page($b,7,$origin)['enabled']===0,'completion cannot overwrite concurrent disabling');
essentialCheck(!$db->query('SELECT id FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$child2])->row(),'revoked automation does not post financial receipt');
// Re-enable may observe the recorded result, but must not issue again.
$policy=$worker->page($b,7,$origin);$worker->configure($b,7,$origin,$review['revision'],$policy['revision'],true,true);
$result=$worker->tick($dec,$workerTransport);essentialCheck($posts===2&&$result[0]['state']==='payment_observed','reactivation recovers durable origin without repeating POST');
$child3=$recurring->run($b,7,$schedule,'2026-09-19');$wake();
$marker=tempnam('/tmp','pixworker_');$workers=[];$in=['answer'=>$answer,'marker'=>$marker];
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker-race',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two cron processes wait on real native lock');$db->trans_commit();$worked=0;
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','cron race process exits without error: '.$err);$worked+=count(json_decode($out,true,32,JSON_THROW_ON_ERROR));}
 essentialCheck($worked===1&&file_get_contents($marker)==="sent\n",'one lease and one POST across simultaneous cron workers');
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND channel=?',[$child3,'api'])->row()->n===1,'uncertain automatic cycle preserves one durable origin');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}unlink($marker);}
echo 'Future Pix worker: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; synthetic provider only.'.PHP_EOL;
