<?php
if(($argv[1]??'')==='removal-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';
 if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');
 require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyService.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 $transport=function($env,$method,$path,$payload,$secret)use($db,$p,$in){
  if($env!=='sandbox'||$payload!==null)throw new RuntimeException('unexpected worker transport');
  if($method==='GET'&&$path==='wallets/')return ['data'=>[['id'=>'wallet-B']],'hasMore'=>false];
  if($method==='GET'&&$path==='customers/cus_b')return ['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false];
  if($path!=='payments/'.$in['payment']['id'])throw new RuntimeException('unexpected member');
  if($method==='GET')return $in['payment'];
  if($method==='DELETE'){$db->query('INSERT INTO '.$p.'installment_delete_calls(remote_id) VALUES(?)',[$in['payment']['id']]);throw new RuntimeException('synthetic lost DELETE response');}
  throw new RuntimeException('unexpected method');
 };
 $dec=fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false;
 echo json_encode((new \Asaas\CompanyService($db,$p))->removeInstallment($in['issuer'],7,$in['group'],$in['payment']['id'],$in['key'],$dec,$transport),JSON_THROW_ON_ERROR);exit;
}
require __DIR__.'/asaas_installment_review.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.3','asaas']);
$bound=$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$review()['review_hash']);$root=$bound['origin_id'];
$first['status']='RECEIVED';$first['paymentDate']=$originalResponse['paymentDate'];$first['creditDate']=$originalResponse['creditDate'];
$deletes=0;$lost=true;$inFlight=null;
$remote=function($env,$method,$path,$payload,$secret)use(&$first,&$second,&$deletes,&$lost,&$inFlight,$transport){
 if($method==='DELETE'){essentialCheck($path==='payments/pay_group_two'&&$payload===null,'DELETE targets only selected member with no body');$deletes++;$second['deleted']=true;if($inFlight){$fn=$inFlight;$inFlight=null;$fn();}if($lost)throw new RuntimeException('synthetic lost response');return ['id'=>$second['id'],'deleted'=>true];}
 if($path==='payments/pay_group_two')return $second;return $transport($env,$method,$path,$payload,$secret);
};
$proof=$asaas->observeInstallment($b,7,$root,'pay_group_one',$dec,$remote);
$payment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_one',$proof));
$before=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array();
$bindings=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id IN (SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=?) ORDER BY origin_id',[$invoice])->result_array();
$key=hash('sha256','remove-group-member');$remove=fn()=>$asaas->removeInstallment($b,7,$root,'pay_group_two',$key,$dec,$remote);
$n=count($calls);essentialThrows(fn()=>$asaas->removeInstallment($a,7,$root,'pay_group_two',$key,$dec,$remote),'asaas_installment_origin_denied','foreign company cannot delete member');essentialCheck($deletes===0&&count($calls)===$n,'foreign company rejected before HTTP');
essentialThrows(fn()=>$asaas->removeInstallment($b,7,$root,'pay_group_one',$key,$dec,$remote),'asaas_company_remote_financial_history','paid member cannot be deleted');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($remove,'issuer_storage_failed','failed intention audit prevents DELETE');$db->failOn=null;essentialCheck($deletes===0,'no dispatch before durable intent');
$inFlight=function()use($asaas,$b,$root,$dec,$remote,$writer,$remove,$db,$p){essentialCheck($remove()['state']==='sending','concurrent retry observes sending without a second DELETE');$proof=$asaas->observeInstallment($b,7,$root,'pay_group_two',$dec,$remote);essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_two',$proof)),'asaas_installment_removal_pending','receipt racing in-flight deletion is denied');$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');};
$result=$remove();essentialCheck($result['state']==='uncertain'&&$deletes===1,'lost DELETE response preserves uncertain intent');
essentialThrows($remove,'asaas_company_access_denied','revoked actor cannot inspect or dispatch');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
essentialCheck($remove()['state']==='uncertain'&&$deletes===1,'retry never repeats DELETE');
essentialThrows(fn()=>$asaas->removeInstallment($b,7,$root,'pay_group_two',hash('sha256','other-key'),$dec,$remote),'asaas_company_remote_request_conflict','different idempotency key cannot repeat DELETE');
$origin=(int)$result['origin_id'];$now=gmdate('Y-m-d H:i:s');
$db->query('INSERT INTO '.$p.'asaas_company_events(account_row,event_id,event_type,remote_id,payload_hash,state,created_at) VALUES(?,?,?,?,?,?,?)',[$accounts[$b],'evt_member_deleted','PAYMENT_DELETED','pay_group_two',hash('sha256','synthetic-deleted'),'review_required',$now]);
$db->query("UPDATE {$p}asaas_payment_origins SET state='review_required',last_code='company_adverse_event' WHERE id=?",[$origin]);
$confirmed=$asaas->refreshInstallmentRemoval($b,7,$root,'pay_group_two',$dec,$remote);essentialCheck($confirmed['state']==='deleted'&&$deletes===1,'canonical GET confirms deletion after adverse webhook without dispatch');
essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->result_array()===$before&&$s->position($b,7,$invoice)['receivable']==='65.53','sibling receipt and remaining native balance preserved');
essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id IN (SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=?) ORDER BY origin_id',[$invoice])->result_array()===$bindings,'original binding snapshots never rewritten');
$second['deleted']=false;essentialCheck($asaas->refreshInstallmentRemoval($b,7,$root,'pay_group_two',$dec,$remote)['state']==='review_required','canonical contradiction withdraws confirmation');$second['deleted']=true;
$db->query('UPDATE '.$p.'asaas_company_events SET event_type=? WHERE event_id=?',['PAYMENT_REFUNDED','evt_member_deleted']);
essentialThrows(fn()=>$asaas->refreshInstallmentRemoval($b,7,$root,'pay_group_two',$dec,$remote),'asaas_installment_binding_conflict','refund webhook is never reinterpreted as deletion');
// Independent processes contend on the actual native mutex before any provider dispatch.
$raceInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','installment-delete-race'));
$raceHash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$raceInvoice])->row()->hash;
$group['id']='grp_race';$first=array_replace($first,['id'=>'pay_race_one','externalReference'=>$raceHash,'installment'=>'grp_race','status'=>'PENDING','paymentDate'=>'','creditDate'=>'','deleted'=>false]);
$second=array_replace($second,['id'=>'pay_race_two','externalReference'=>$raceHash,'installment'=>'grp_race','status'=>'PENDING','paymentDate'=>'','creditDate'=>'','deleted'=>false]);
$raceTransport=function($env,$method,$path,$payload,$secret)use(&$group,&$first,&$second,$mock){
 if($method!=='GET'||$payload!==null)throw new RuntimeException('race review must be read only');
 if($path==='payments/pay_race_one')return $first;
 if($path==='installments/grp_race')return $group;
 if($path==='installments/grp_race/payments')return ['data'=>[$first,$second],'hasMore'=>false,'totalCount'=>2];
 return $mock($env,$method,$path,$payload,$secret);
};
$approval=$asaas->reviewLegacyInstallments($b,7,$raceInvoice,$accounts[$b],'pay_race_one',$dec,$raceTransport)['review_hash'];
$raceRoot=$asaas->reviewLegacyInstallments($b,7,$raceInvoice,$accounts[$b],'pay_race_one',$dec,$raceTransport,$approval)['origin_id'];
$db->query('CREATE TABLE '.$p.'installment_delete_calls(id INT AUTO_INCREMENT PRIMARY KEY,remote_id VARCHAR(64) NOT NULL) ENGINE=InnoDB');
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];
$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'removal-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'group'=>$raceRoot,'payment'=>$first,'key'=>hash('sha256','race-delete')],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
 essentialCheck($waiters>=2,'both DELETE contenders wait on actual native mutex');$db->trans_commit();$results=[];
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','independent deletion contender completes without diagnostics');$results[]=json_decode($out,true,16,JSON_THROW_ON_ERROR);}
 essentialCheck(count(array_unique(array_column($results,'origin_id')))===1,'workers preserve one member intention');
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'installment_delete_calls')->row()->n===1,'two actual processes dispatch only one DELETE');
 essentialCheck($s->position($b,7,$raceInvoice)['received']==='0.00','deletion contenders never write a receipt');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
echo 'Installment removal: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; all provider calls simulated.'.PHP_EOL;
