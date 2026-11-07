<?php
if(($argv[1]??'')==='recreate-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyService.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
 $transport=function($env,$method,$path,$payload,$secret)use($db,$p,$in){if($env!=='sandbox'||$secret!=='key-B')throw new RuntimeException('unexpected worker scope');if($path==='wallets/')return ['data'=>[['id'=>'wallet-B']],'hasMore'=>false];if($path==='customers/cus_b')return ['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false];if($path==='payments'&&$method==='POST'){$db->query('INSERT INTO '.$p.'recreate_calls(reference_key) VALUES(?)',[$payload['externalReference']]);throw new RuntimeException('synthetic unknown POST');}if(str_starts_with($path,'payments?externalReference='))return ['data'=>[],'hasMore'=>false,'totalCount'=>0];if(str_starts_with($path,'payments/')&&$method==='GET')return $in['payments'][substr($path,9)];throw new RuntimeException('unexpected worker request');};
 $dec=fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false;echo json_encode((new \Asaas\CompanyService($db,$p))->recoverInstallmentRecreation($in['issuer'],7,$in['group'],$in['key'],$dec,$transport),JSON_THROW_ON_ERROR);exit;
}
require __DIR__.'/asaas_installment_review.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['2.0.2','asaas']);
$root=$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$review()['review_hash'])['origin_id'];
$payments=['pay_group_one'=>$first,'pay_group_two'=>$second];$posts=0;$lost=true;$discover=false;$created=[];$remoteGroup=[];$duringPost=null;
$recreateTransport=function($env,$method,$path,$payload,$secret)use(&$payments,&$posts,&$lost,&$discover,&$created,&$remoteGroup,&$duringPost,$mock){
 essentialCheck($env==='sandbox'&&$secret==='key-B','recreation uses exact company and sandbox credentials');
 if($path==='payments'&&$method==='POST'){
  $posts++;essentialCheck($payload['totalValue']==='105.53'&&$payload['installmentCount']===2&&!isset($payload['value'])&&!isset($payload['installmentValue']),'documented totalValue mode preserves exact group amount');
  $created=[];foreach([1,2] as $n){$template=$payments['pay_group_one'];$created[]=array_replace($template,['id'=>'pay_successor_'.$n,'installment'=>'grp_successor','installmentNumber'=>$n,'value'=>$n===1?'52.76':'52.77','netValue'=>$n===1?'51.76':'51.77','dueDate'=>$n===1?$payload['dueDate']:'2027-02-05','externalReference'=>$payload['externalReference'],'deleted'=>false]);}$remoteGroup=['id'=>'grp_successor','customer'=>'cus_b','billingType'=>'PIX','installmentCount'=>2,'value'=>'105.53','deleted'=>false];
  if($duringPost){$f=$duringPost;$duringPost=null;$f();}if($lost)throw new RuntimeException('synthetic unknown POST result');return $created[0];
 }
 if(str_starts_with($path,'payments?externalReference='))return ['data'=>$discover?$created:[],'hasMore'=>false,'totalCount'=>$discover?count($created):0];
 if($path==='installments/grp_successor')return $remoteGroup;
 if($path==='installments/grp_successor/payments?limit=100&offset=0')return ['data'=>$created,'hasMore'=>false,'totalCount'=>count($created)];
 if(str_starts_with($path,'payments/')){$id=substr($path,9);if(isset($payments[$id])){if($method==='DELETE'){$payments[$id]['deleted']=true;return ['id'=>$id,'deleted'=>true];}return $payments[$id];}foreach($created as $member)if($member['id']===$id)return $member;throw new RuntimeException('unknown synthetic payment');}
 return $mock($env,$method,$path,$payload,$secret);
};
essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($b,7,$root,2,'2027-01-05',$dec,$recreateTransport),'asaas_installment_recreation_deleted_required','all previous members must be canonically deleted before successor review');
foreach(array_keys($payments) as $remote)$asaas->removeInstallment($b,7,$root,$remote,hash('sha256','delete-'.$remote),$dec,$recreateTransport);
$n=$posts;essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($a,7,$root,2,'2027-01-05',$dec,$recreateTransport),'asaas_installment_origin_denied','foreign group rejected');essentialCheck($posts===$n,'foreign action cannot dispatch');
$reviewNew=$asaas->reviewInstallmentRecreation($b,7,$root,2,'2027-01-05',$dec,$recreateTransport);$key=$reviewNew['key'];
$execute=fn()=>$asaas->recreateInstallmentGroup($b,7,$root,2,'2027-01-05',$key,true,$dec,$recreateTransport);
essentialThrows(fn()=>$asaas->recreateInstallmentGroup($b,7,$root,2,'2027-01-05',$key,false,$dec,$recreateTransport),'asaas_company_confirmation_required','recreation requires explicit confirmation');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($execute,'issuer_storage_failed','intent audit failure prevents POST');$db->failOn=null;essentialCheck($posts===0,'no remote creation before durable intent');
$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? ORDER BY id',[$invoice])->result_array();
$duringPost=function()use($asaas,$b,$root,$key,$dec,$recreateTransport){essentialCheck(!$asaas->recoverInstallmentRecreation($b,7,$root,$key,$dec,$recreateTransport)['complete'],'concurrent in-flight recovery cannot repeat creation');};
$result=$execute();essentialCheck(!$result['complete']&&$posts===1,'lost response stays uncertain with a single POST');essentialCheck(!$execute()['complete']&&$posts===1,'retry never repeats POST');
$created[0]['status']='RECEIVED';$created[0]['paymentDate']=$originalResponse['paymentDate'];$created[0]['creditDate']=$originalResponse['creditDate'];
$discover=true;$savedReference=$created[0]['externalReference'];$created[0]['externalReference']='foreign';essentialCheck(!$asaas->recoverInstallmentRecreation($b,7,$root,$key,$dec,$recreateTransport)['complete'],'foreign remote reference cannot bind the successor');$created[0]['externalReference']=$savedReference;
$db->failOn='INSERT INTO '.$p.'asaas_company_origin_meta';essentialThrows(fn()=>$asaas->recoverInstallmentRecreation($b,7,$root,$key,$dec,$recreateTransport),'issuer_storage_failed','late binding failure rolls back predecessor retirement and new identities');$db->failOn=null;
essentialCheck($before===$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? ORDER BY id',[$invoice])->result_array()&&$posts===1,'binding failure preserves old active group without another POST');
$result=$asaas->recoverInstallmentRecreation($b,7,$root,$key,$dec,$recreateTransport);$next=$result['origin_id'];essentialCheck($result['complete']&&$next!==$root&&$posts===1,'canonical lookup atomically binds new group without retrying POST');
$active=$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND active_slot=1',[$invoice])->result_array();essentialCheck(array_column($active,'id')==[$next],'only successor is active');
$old=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id<=? AND invoice_id=? ORDER BY id',[end($before)['id'],$invoice])->result_array();$before[0]['active_slot']=null;essentialCheck($old===$before,'retired group preserves every original field except active selection');
essentialCheck($execute()['origin_id']===$next&&$posts===1,'completed replay resolves same successor locally');
essentialCheck($s->position($b,7,$invoice)['received']==='0.00','received remote member can bind without applying automatic local receipt');
$panel=$asaas->installmentGroupPage($b,7,$next);essentialCheck(array_column($panel['members'],'value')===['52.76','52.77'],'provider rounding retained exactly without float reconstruction');
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';$created[0]['status']='RECEIVED';$created[0]['paymentDate']=$originalResponse['paymentDate'];$created[0]['creditDate']=$originalResponse['creditDate'];
$proof=$asaas->observeInstallment($b,7,$next,'pay_successor_1',$dec,$recreateTransport);$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($next,'pay_successor_1',$proof));
essentialCheck($s->position($b,7,$invoice)['received']==='52.76','successor receipt uses immutable nonce linkage and exact member amount');
$rows=$db->query("SELECT * FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='group_next'",[$root])->result_array();$link=$rows[0];$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$link['id']]);
essentialThrows(fn()=>$asaas->observeInstallment($b,7,$next,'pay_successor_2',$dec,$recreateTransport),'asaas_installment_edit_history_conflict','broken predecessor evidence blocks current operations');$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$link['evidence_hash'],$link['id']]);
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefunds.php';\Asaas\CompanyRefundSchema::ensure($db,$p,true);$refunds=new \Asaas\CompanyRefunds($db,$p);$refunds->setGrant($b,7,$accounts[$b],7,str_repeat('0',64),true);$paidOrigin=(int)$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND remote_id=?',[$invoice,'pay_successor_1'])->row()->id;
$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$link['id']]);
essentialThrows(fn()=>$refunds->prepare($b,7,$paidOrigin,'52.76','Synthetic successor refund',hash('sha256','successor-refund'),$dec,$recreateTransport),'asaas_installment_edit_history_conflict','refund authority validates the successor chain, not only member identity');
essentialCheck(!$db->query('SELECT request_key FROM '.$p.'asaas_company_refund_requests WHERE origin_id=?',[$paidOrigin])->result_array(),'invalid succession cannot reserve a refund intention');
$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$link['evidence_hash'],$link['id']]);
$refund=$refunds->prepare($b,7,$paidOrigin,'52.76','Synthetic successor refund',hash('sha256','successor-refund'),$dec,$recreateTransport);essentialCheck($refund['source']['external_reference']===$created[0]['externalReference']&&$refund['source']['invoice_hash']===$hash,'refund distinguishes native invoice identity from unique remote successor reference');
$wrongSource=$refund['source'];$wrongSource['external_reference']='forged';essentialThrows(fn()=>\Asaas\CompanyRefundContract::normalize($created[0],$wrongSource),'asaas_refund_response_conflict','remote reference cannot diverge from immutable successor identity');
essentialThrows(fn()=>$asaas->reviewInstallmentRecreation($b,7,$next,2,'2027-03-05',$dec,$recreateTransport),'asaas_installment_recreation_unpaid_required','paid group cannot be recreated as unpaid');
// The successor's unique reference also survives the canonical full-refund writer.
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/ConfirmedFullRefund.php';CompanyAsaasRefundSchema::ensure($db,$p,true);
$refundKey=hash('sha256','successor-refund');$refundPosts=0;
$successorRefundTransport=function($env,$method,$path,$payload,$secret)use(&$created,&$refundPosts,$recreateTransport){
 if($method==='POST'&&$path==='payments/pay_successor_1/refund'){$refundPosts++;$created[0]['status']='REFUND_IN_PROGRESS';$created[0]['refunds']=[['dateCreated'=>'2026-11-01 12:00:00','status'=>'PENDING','value'=>$payload['value'],'description'=>$payload['description'],'refundedSplits'=>[]]];return $created[0];}
 return $recreateTransport($env,$method,$path,$payload,$secret);
};
$refunds->dispatch($b,7,$refundKey,$dec,$successorRefundTransport);$refunds->dispatch($b,7,$refundKey,$dec,$successorRefundTransport);essentialCheck($refundPosts===1,'successor refund dispatch is idempotent');
$created[0]['status']='REFUNDED';$created[0]['refunds'][0]['status']='DONE';$observed=$refunds->refresh($b,7,$refundKey,$dec,$successorRefundTransport);
$declaration=['external_refund_confirmed'=>'1','full_cash_return_confirmed'=>'1','returned_amount'=>'52.76','date'=>gmdate('Y-m-d'),'reference'=>'synthetic-successor-return','reason'=>'Independent synthetic proof','independent_evidence_hash'=>hash('sha256','synthetic successor evidence')];
$refundSource=new \Asaas\ConfirmedFullRefund($db,$p,$refundKey,$observed['observation_id'],$declaration);$nativeBefore=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array();
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$writer->recordCompanyAsaasFullRefund($b,7,$refundSource),'issuer_storage_failed','successor refund audit failure rolls back financial reversal');$db->failOn=null;
$refundPosting=$writer->recordCompanyAsaasFullRefund($b,7,$refundSource);essentialCheck($writer->recordCompanyAsaasFullRefund($b,7,$refundSource)===$refundPosting,'successor refund writer replays same posting');
$position=$s->position($b,7,$invoice);essentialCheck($position['received_original']==='52.76'&&$position['received']==='0.00'&&$position['receivable']==='105.53','successor full refund reopens exact principal only');
essentialCheck($nativeBefore===$db->query('SELECT * FROM '.$p.'invoicepaymentrecords WHERE invoiceid=? ORDER BY id',[$invoice])->result_array(),'successor native receipt remains byte-identical after refund');
// Independent processes compete for a previously reserved group creation.
$raceInvoice=$s->create($b,7,array_replace($input,['client_id'=>$customerB]),hash('sha256','recreate-group-race'));$raceHash=$db->query('SELECT hash FROM '.$p.'invoices WHERE id=?',[$raceInvoice])->row()->hash;
$raceGroup=['id'=>'grp_recreate_race','customer'=>'cus_b','billingType'=>'PIX','installmentCount'=>2,'value'=>'105.53','deleted'=>false];$racePayments=[];
foreach([1,2] as $n)$racePayments['pay_race_'.$n]=array_replace($first,['id'=>'pay_race_'.$n,'externalReference'=>$raceHash,'installment'=>'grp_recreate_race','installmentNumber'=>$n,'value'=>$n===1?'40.00':'65.53','netValue'=>$n===1?'39.00':'64.53','deleted'=>false]);
$walletCount=0;$stopAfterReserve=false;
$raceTransport=function($env,$method,$path,$payload,$secret)use(&$racePayments,$raceGroup,&$walletCount,&$stopAfterReserve,$mock){if($path==='wallets/'&&$stopAfterReserve&&++$walletCount===3)throw new RuntimeException('synthetic stop after plan');if($path==='installments/grp_recreate_race')return $raceGroup;if($path==='installments/grp_recreate_race/payments')return ['data'=>array_values($racePayments),'hasMore'=>false,'totalCount'=>2];if(str_starts_with($path,'payments/')){$id=substr($path,9);if($method==='DELETE'){$racePayments[$id]['deleted']=true;return ['id'=>$id,'deleted'=>true];}return $racePayments[$id];}return $mock($env,$method,$path,$payload,$secret);};
$approval=$asaas->reviewLegacyInstallments($b,7,$raceInvoice,$accounts[$b],'pay_race_1',$dec,$raceTransport)['review_hash'];$raceRoot=$asaas->reviewLegacyInstallments($b,7,$raceInvoice,$accounts[$b],'pay_race_1',$dec,$raceTransport,$approval)['origin_id'];
foreach(array_keys($racePayments) as $remote)$asaas->removeInstallment($b,7,$raceRoot,$remote,hash('sha256','race-remove-'.$remote),$dec,$raceTransport);
$racePlan=$asaas->reviewInstallmentRecreation($b,7,$raceRoot,2,'2027-01-05',$dec,$raceTransport);$stopAfterReserve=true;
essentialThrows(fn()=>$asaas->recreateInstallmentGroup($b,7,$raceRoot,2,'2027-01-05',$racePlan['key'],true,$dec,$raceTransport),'asaas_company_transport_unconfirmed','failure after durable plan leaves unsent recoverable intent');
$db->query('CREATE TABLE '.$p.'recreate_calls(id INT AUTO_INCREMENT PRIMARY KEY,reference_key CHAR(64) NOT NULL) ENGINE=InnoDB');register_shutdown_function(fn()=>$db->query('DROP TABLE IF EXISTS '.$p.'recreate_calls'));
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'recreate-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'group'=>$raceRoot,'key'=>$racePlan['key'],'payments'=>$racePayments]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $until=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiters>=2,'two recreation processes wait on native InnoDB mutex');$db->trans_commit();
 foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','recreation contenders finish without leaking errors');essentialCheck(json_decode($out,true,32,JSON_THROW_ON_ERROR)['complete']===false,'unknown provider outcome remains unknown');}
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'recreate_calls')->row()->n===1,'exactly one group POST between concurrent processes');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();}
echo 'Installment recreation: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks, simulated provider.'.PHP_EOL;
