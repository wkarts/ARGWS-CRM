<?php
if(($argv[1]??'')==='edit-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyService.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;$db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 $transport=function($env,$method,$path,$payload,$secret)use($db,$p){if($env!=='sandbox'||$secret!=='key-B')throw new RuntimeException('unexpected synthetic scope');if($path==='wallets/')return ['data'=>[['id'=>'wallet-B']],'hasMore'=>false];if($path==='customers/cus_b')return ['id'=>'cus_b','cpfCnpj'=>'12345678909','deleted'=>false];if($path!=='payments/pay_group_two')throw new RuntimeException('unexpected synthetic remote');$remote=json_decode($db->query('SELECT response_json FROM '.$p.'edit_remote WHERE id=1')->row()->response_json,true,32,JSON_THROW_ON_ERROR);if($method==='PUT'){$db->query('INSERT INTO '.$p.'edit_calls(remote_id) VALUES(?)',[$remote['id']]);$remote['dueDate']=$payload['dueDate'];$remote['value']=$payload['value'];$db->query('UPDATE '.$p.'edit_remote SET response_json=? WHERE id=1',[json_encode($remote,JSON_THROW_ON_ERROR)]);}return $remote;};
 $dec=fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false;
 echo json_encode((new \Asaas\CompanyService($db,$p))->resumeInstallmentEdit($in['issuer'],7,$in['group'],$in['key'],$dec,$transport),JSON_THROW_ON_ERROR);exit;
}
require __DIR__.'/asaas_installment_review.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['2.0.1','asaas']);
$root=$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$review()['review_hash'])['origin_id'];
$payments=['pay_group_one'=>$first,'pay_group_two'=>$second];$puts=[];$lost=false;$duringPut=null;$readFailed=false;
$editTransport=function($env,$method,$path,$payload,$secret)use(&$payments,&$puts,&$lost,&$duringPut,&$readFailed,$mock){
 essentialCheck($env==='sandbox'&&$secret==='key-B','edit stays in selected company sandbox account');
 if(str_starts_with($path,'payments/')){$id=substr($path,9);if(!isset($payments[$id]))throw new RuntimeException('unknown synthetic member');
  if($method==='GET'){if($readFailed)throw new RuntimeException('synthetic GET timeout');return $payments[$id];}
  if($method==='PUT'){$puts[]=$id;$payments[$id]['value']=$payload['value'];$payments[$id]['netValue']=CompanyInvoiceAmounts::decimal(CompanyInvoiceAmounts::minor($payload['value'])-100);$payments[$id]['dueDate']=$payload['dueDate'];if($duringPut){$f=$duringPut;$duringPut=null;$f();}if($lost)throw new RuntimeException('synthetic lost PUT response');return $payments[$id];}
 }return $mock($env,$method,$path,$payload,$secret);
};
$changes=['pay_group_one'=>['value'=>'45.00','due_date'=>'2026-12-10'],'pay_group_two'=>['value'=>'60.53','due_date'=>'2026-12-28']];
$reviewEdit=fn()=>$asaas->reviewInstallmentEdit($b,7,$root,$changes,$dec,$editTransport);
$n=count($puts);essentialThrows(fn()=>$asaas->reviewInstallmentEdit($a,7,$root,$changes,$dec,$editTransport),'asaas_installment_origin_denied','foreign root rejected');essentialCheck(count($puts)===$n,'foreign read cannot dispatch');
$bad=$changes;$bad['pay_group_two']['value']='60.54';essentialThrows(fn()=>$asaas->reviewInstallmentEdit($b,7,$root,$bad,$dec,$editTransport),'asaas_installment_edit_total_changed','reallocation cannot alter emitted invoice or group total');
$plan=$reviewEdit();$before=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? ORDER BY id',[$invoice])->result_array();
$execute=fn()=>$asaas->executeInstallmentEdit($b,7,$root,$changes,$plan['key'],true,$dec,$editTransport);
essentialThrows(fn()=>$asaas->executeInstallmentEdit($b,7,$root,$changes,$plan['key'],false,$dec,$editTransport),'asaas_company_confirmation_required','explicit confirmation required');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows($execute,'issuer_storage_failed','approval audit failure rolls plan back before PUT');$db->failOn=null;essentialCheck($puts===[],'no remote dispatch before durable reviewed plan');
$duringPut=function()use($asaas,$b,$root,$db,$p){essentialThrows(fn()=>$asaas->installmentGroupPage($b,7,$root),'asaas_installment_edit_pending','partial remote operation blocks ordinary group actions');$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');};
essentialThrows($execute,'asaas_company_access_denied','revoked actor stops processing remaining members');essentialCheck($puts===['pay_group_one'],'revocation during first PUT prevents second PUT');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');
$lost=true;$result=$asaas->resumeInstallmentEdit($b,7,$root,$plan['key'],$dec,$editTransport);essentialCheck($result['complete']&&$puts===['pay_group_one','pay_group_two'],'resume recovers first by GET and sends second once despite lost response');
$panel=$asaas->installmentGroupPage($b,7,$root);essentialCheck(array_column($panel['members'],'value')===['45.00','60.53']&&array_column($panel['members'],'due_date')===['2026-12-10','2026-12-28'],'effective allocations and dates are shown after complete canonical confirmation');
essentialCheck($asaas->resumeInstallmentEdit($b,7,$root,$plan['key'],$dec,$editTransport)['complete']&&count($puts)===2,'completed retry is local and sends nothing');
essentialCheck($before===$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? ORDER BY id',[$invoice])->result_array(),'origin identities unchanged byte for byte');
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';
$payments['pay_group_one']['status']='RECEIVED';$payments['pay_group_one']['paymentDate']=$originalResponse['paymentDate'];$payments['pay_group_one']['creditDate']=$originalResponse['creditDate'];
$proof=$asaas->observeInstallment($b,7,$root,'pay_group_one',$dec,$editTransport);$id=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_one',$proof));
essentialCheck($s->position($b,7,$invoice)['received']==='45.00','receipt uses approved effective allocation, not stale original nominal');
$changes['pay_group_one']['due_date']='2026-12-11';essentialThrows(fn()=>$asaas->reviewInstallmentEdit($b,7,$root,$changes,$dec,$editTransport),'asaas_installment_edit_financial_history','received member cannot be edited again');
$done=$db->query("SELECT * FROM {$p}asaas_company_proofs WHERE origin_id=? AND operation='parcel_edit_done'",[$root])->row_array();$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[str_repeat('0',64),$done['id']]);
essentialThrows(fn()=>$asaas->installmentGroupPage($b,7,$root),'asaas_installment_edit_history_conflict','tampered edit trail cannot replace original binding');$db->query('UPDATE '.$p.'asaas_company_proofs SET evidence_hash=? WHERE id=?',[$done['evidence_hash'],$done['id']]);
// Two separate PHP processes resume one durable plan and compete for its single send claim.
$changes=['pay_group_two'=>['value'=>'60.53','due_date'=>'2027-01-05']];$next=$asaas->reviewInstallmentEdit($b,7,$root,$changes,$dec,$editTransport);$walletReads=0;
$prepareTransport=function($env,$method,$path,$payload,$secret)use(&$walletReads,$editTransport){if($path==='wallets/'&&++$walletReads===2)throw new RuntimeException('synthetic stop after durable plan');return $editTransport($env,$method,$path,$payload,$secret);};
essentialThrows(fn()=>$asaas->executeInstallmentEdit($b,7,$root,$changes,$next['key'],true,$dec,$prepareTransport),'asaas_company_transport_unconfirmed','transport failure after reservation leaves resumable plan');
$db->query('CREATE TABLE '.$p.'edit_remote(id INT PRIMARY KEY,response_json LONGTEXT NOT NULL) ENGINE=InnoDB');$db->query('CREATE TABLE '.$p.'edit_calls(id INT AUTO_INCREMENT PRIMARY KEY,remote_id VARCHAR(64) NOT NULL) ENGINE=InnoDB');$db->query('INSERT INTO '.$p.'edit_remote VALUES(1,?)',[json_encode($payments['pay_group_two'],JSON_THROW_ON_ERROR)]);
register_shutdown_function(function()use($db,$p){$db->query('DROP TABLE IF EXISTS '.$p.'edit_calls');$db->query('DROP TABLE IF EXISTS '.$p.'edit_remote');});
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'edit-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'group'=>$root,'key'=>$next['key']]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $until=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiters>=2,'two edit processes wait on the actual native mutex');$db->trans_commit();
 foreach($workers as [$proc,$pipes]){$result=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','concurrent edit workers finish without secret diagnostics');essentialCheck(json_decode($result,true,32,JSON_THROW_ON_ERROR)['key']===$next['key'],'both workers retain the same approved plan');}
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'edit_calls')->row()->n===1,'exactly one PUT across real concurrent workers');
 essentialCheck($asaas->installmentGroupPage($b,7,$root)['members'][1]['due_date']==='2027-01-05','concurrent completion applies the reviewed date');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();}
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefunds.php';
\Asaas\CompanyRefundSchema::ensure($db,$p,true);$refunds=new \Asaas\CompanyRefunds($db,$p);$refunds->setGrant($b,7,$accounts[$b],7,str_repeat('0',64),true);
$origin=(int)$db->query('SELECT id FROM '.$p.'asaas_payment_origins WHERE invoice_id=? AND remote_id=?',[$invoice,'pay_group_one'])->row()->id;
$refund=$refunds->prepare($b,7,$origin,'45.00','Synthetic edited member',hash('sha256','edited-member-refund'),$dec,$editTransport);
essentialCheck($refund['source']['nominal']==='45.00'&&count($refund['source']['installment_identity']['edits'])===1,'refund freezes the edited nominal and its immutable approval evidence');
$historicalBefore=$refund['source']['installment_identity'];
essentialCheck($refunds->page($b,7,$origin)['requests'][0]['source']['installment_identity']===$historicalBefore,'later sibling edit does not replace refunded member identity');
echo 'Installment edits: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; simulated provider.'.PHP_EOL;
