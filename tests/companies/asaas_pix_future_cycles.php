<?php
if(($argv[1]??'')==='cycle-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixFutureCycle.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 if(!preg_match('~^/tmp/pixcycle_[A-Za-z0-9]+$~D',$in['marker']))exit(2);
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
 $transport=function($env,$method,$path,$payload,$secret)use($in){
  if($method==='POST'&&$path==='payments'){file_put_contents($in['marker'],"sent\n",FILE_APPEND|LOCK_EX);usleep(100000);return $payload+['id'=>'pay_future_race','status'=>'PENDING','netValue'=>$payload['value'],'paymentDate'=>'','creditDate'=>'','deleted'=>false];}
  if($path==='wallets/')return ['data'=>[['id'=>'wallet-B']],'hasMore'=>false];if($path==='customers/cus_b')return ['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false];if(str_starts_with($path,'pix/automatic/authorizations?'))return ['data'=>[$in['answer']],'hasMore'=>false,'totalCount'=>1];if($path==='pix/automatic/authorizations/'.$in['answer']['id'])return $in['answer'];throw new RuntimeException('unexpected path');};
 try{echo (new \Asaas\CompanyPixFutureCycle($db,$p,$in['issuer'],7,$in['origin'],$in['invoice'],fn()=>'2026-10-09'))->dispatch(fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false,$transport);}catch(DomainException $e){if(!in_array($e->getMessage(),['asaas_company_stale','asaas_pix_cycle_stale'],true))throw $e;echo 'stale';}exit;
}
require __DIR__.'/asaas_pix_future_schedules.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixFutureCycle.php';
$start=$GLOBALS['essentialChecks'];
$child=$recurring->run($b,7,$schedule,'2026-07-19');essentialCheck($child>$review['binding']['cutoff_invoice_id'],'only newly issued canonical occurrence is targeted');
$posts=0;$omit=false;$wrong=false;$timeout=false;
$cycleTransport=function($env,$method,$path,$payload,$secret)use($transport,$mock,&$posts,&$responses,&$omit,&$wrong,&$timeout){
 if($method==='POST'&&$path==='payments'){$posts++;$reply=$mock($env,$method,$path,$payload,$secret);if($timeout)throw new RuntimeException('synthetic lost reply');return $reply;}
 if(str_starts_with($path,'payments?externalReference=')){parse_str(parse_url($path,PHP_URL_QUERY),$query);return ['data'=>array_values(array_filter($responses,fn($r)=>($r['externalReference']??null)===$query['externalReference'])),'hasMore'=>false];}
 if(str_starts_with($path,'pix/automatic/paymentInstructions?')){parse_str(parse_url($path,PHP_URL_QUERY),$query);return ['data'=>[['id'=>'instruction_future','paymentId'=>$query['paymentId'],'authorization'=>['id'=>$wrong?'auth_other':'auth_future_schedule']]],'hasMore'=>false];}
 if(str_starts_with($path,'payments/')){$reply=$mock($env,$method,$path,$payload,$secret);if($omit)unset($reply['pixAutomaticAuthorizationId']);return $reply;}
 return $transport($env,$method,$path,$payload,$secret);
};
$make=fn($i,$clock='2026-08-07')=>new Asaas\CompanyPixFutureCycle($db,$p,$b,7,$origin,$i,fn()=>$clock);
$cycle=$make($child);essentialThrows(fn()=>$make($child,'2026-05-01')->dispatch($dec,$cycleTransport),'asaas_pix_cycle_outside_window','cannot send earlier than window');essentialCheck($posts===0,'window refusal has no POST');
$cycleOrigin=$cycle->dispatch($dec,$cycleTransport);$remote=$db->query('SELECT remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$cycleOrigin])->row()->remote_id;
essentialCheck($posts===1&&$responses[$remote]['pixAutomaticAuthorizationId']==='auth_future_schedule','future POST binds the reviewed authorization');
essentialCheck($cycle->dispatch($dec,$cycleTransport)===$cycleOrigin&&$posts===1,'same cycle retry never repeats POST');
$omit=true;$proof=$cycle->observe($dec,$cycleTransport);essentialCheck($proof>0,'missing authorization id is proven through exact instruction membership');
$responses[$remote]['status']='RECEIVED';$responses[$remote]['paymentDate']='2026-08-19';$responses[$remote]['creditDate']='2026-08-19';$proof=$cycle->observe($dec,$cycleTransport);
$paid=$cycle->settle($proof);essentialCheck($cycle->settle($proof)===$paid,'future payment posts once through native explicit writer');
$wrong=true;essentialThrows(fn()=>$cycle->observe($dec,$cycleTransport),'asaas_pix_payment_conflict','foreign instruction cannot prove authorization');$wrong=false;
// Another invoice is sent once; timeout recovery reads the remote result without repeating creation.
$child2=$recurring->run($b,7,$schedule,'2026-08-19');$cycle2=$make($child2,'2026-09-09');$timeout=true;
essentialThrows(fn()=>$cycle2->dispatch($dec,$cycleTransport),'asaas_company_post_unconfirmed','lost future POST is not a false success');$timeout=false;$before=$posts;
$proof2=$cycle2->observe($dec,$cycleTransport);essentialCheck($proof2>0&&$posts===$before,'unknown future result recovered by exact invoice reference then canonical identity');
$child3=$recurring->run($b,7,$schedule,'2026-09-19');
$marker=tempnam('/tmp','pixcycle_');$workers=[];$in=['issuer'=>$b,'origin'=>$origin,'invoice'=>$child3,'answer'=>$answer,'marker'=>$marker];
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'cycle-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($in,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'both future cycle workers wait on real native lock');$db->trans_commit();
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err===''&&(ctype_digit($out)||$out==='stale'),'concurrent cycle worker returns origin or stale result: '.$err);}
 essentialCheck(file_get_contents($marker)==="sent\n",'two processes issue exactly one future payment');
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_company_proofs p JOIN '.$p.'asaas_payment_origins o ON o.id=p.origin_id WHERE o.invoice_id=? AND p.operation=?',[$child3,'pix_future'])->row()->n===1,'cycle proof and invoice origin committed once');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}unlink($marker);}
// Withdrawal while the provider handles a send must not erase the remote result or authorize a retry.
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyPixRevocation.php';
$child4=$recurring->run($b,7,$schedule,'2026-10-19');$cycle4=$make($child4,'2026-11-10');
$withdrawDuringPost=function($env,$method,$path,$payload,$secret)use($cycleTransport,$db,$p,$portal,$sessions,$b,$invoice,$terms){$reply=$cycleTransport($env,$method,$path,$payload,$secret);if($method==='POST'&&$path==='payments')(new Asaas\CompanyPixRevocation($db,$p))->withdraw($portal,$sessions[$b],$b,$invoice,$terms['revision'],true);return $reply;};
essentialThrows(fn()=>$cycle4->dispatch($dec,$withdrawDuringPost),'asaas_company_post_unconfirmed','withdrawal during POST prevents false financial success');
$before=$posts;essentialCheck($cycle4->observe($dec,$cycleTransport)>0&&$posts===$before,'already sent result remains recoverable after payer withdrawal');
essentialThrows(fn()=>$cycle4->dispatch($dec,$cycleTransport),'asaas_pix_consent_revoked','withdrawn mandate cannot repeat future send');
$bound=$future->status($b,7,$origin);essentialCheck($bound['revision']===$review['revision']&&!$bound['stopped'],'bound status is scoped and immutable');
essentialThrows(fn()=>$future->status($a,7,$origin),'asaas_pix_schedule_denied','known foreign binding denied');
essentialThrows(fn()=>$future->stop($b,7,$origin,$bound['revision'],false),'asaas_company_confirmation_required','stop needs explicit confirmation');
essentialThrows(fn()=>$future->stop($b,7,$origin,str_repeat('0',64),true),'asaas_pix_schedule_stale','stop cannot use stale binding');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$future->stop($b,7,$origin,$bound['revision'],true),'issuer_storage_failed','stop audit rolls back atomically');$db->failOn=null;
essentialCheck(!$future->status($b,7,$origin)['stopped'],'failed stop leaves previous policy intact');
$future->stop($b,7,$origin,$bound['revision'],true);$future->stop($b,7,$origin,$bound['revision'],true);essentialCheck($future->status($b,7,$origin)['stopped'],'stop is durable and idempotent');
essentialThrows(fn()=>$make($child3,'2026-10-09')->dispatch($dec,$cycleTransport),'asaas_pix_consent_revoked','withdrawal and stop prevent further sends');
$before=$posts;essentialCheck($cycle2->observe($dec,$cycleTransport)>0&&$posts===$before,'historical recovery remains available after stop without mutation');
echo 'Future Pix cycles: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; synthetic API only.'.PHP_EOL;
