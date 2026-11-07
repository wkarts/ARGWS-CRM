<?php
if(($argv[1]??'')==='receipt-worker'){
 require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/InvoicePaymentWriter.php';
 if(!defined('FCPATH'))define('FCPATH',dirname(__DIR__,2).'/');require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';
 $p=$argv[2];if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$GLOBALS['essentialPrefix']=$p;
 $db=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);
 echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyProviderReceipt($in['issuer'],7,new \Asaas\CompanyInstallmentReceipt($in['group'],$in['remote'],$in['proof']));exit;
}
require __DIR__.'/asaas_installment_review.php';
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyInstallmentReceipt.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.8.2','asaas']);
$approve=$review()['review_hash'];$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$approve),'issuer_storage_failed','failed group audit rolls every identity back');$db->failOn=null;
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_payment_origins WHERE invoice_id=?',[$invoice])->row()->n===0,'failed binding leaves no group or members');
$bound=$asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$approve);$root=$bound['origin_id'];
essentialCheck($bound['bound']&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'asaas_payment_origins WHERE invoice_id=?',[$invoice])->row()->n===3,'group and two immutable member identities created');
$n=count($calls);essentialCheck($asaas->reviewLegacyInstallments($b,7,$invoice,$accounts[$b],'pay_group_one',$dec,$transport,$approve)===$bound&&count($calls)===$n,'binding retry is local and idempotent');
$first['status']='RECEIVED';$first['paymentDate']=$originalResponse['paymentDate'];$first['creditDate']=$originalResponse['creditDate'];
$second['status']='RECEIVED';$second['paymentDate']=$originalResponse['paymentDate'];$second['creditDate']=$originalResponse['creditDate'];
$receiptTransport=function($env,$method,$path,$payload,$secret)use(&$first,&$second,$transport){if($path==='payments/pay_group_two')return $second;return $transport($env,$method,$path,$payload,$secret);};
$proof=$asaas->observeInstallment($b,7,$root,'pay_group_one',$dec,$receiptTransport);
$source=new \Asaas\CompanyInstallmentReceipt($root,'pay_group_one',$proof);
$db->failOn='INSERT INTO '.$p.'asaas_company_receipts';essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($b,7,$source),'issuer_storage_failed','failed member receipt journal rolls native payment back');$db->failOn=null;
essentialCheck($s->position($b,7,$invoice)['received']==='0.00','failed member receipt preserves native balance');
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$workers=[];$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
try{
 for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'receipt-worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'group'=>$root,'remote'=>'pay_group_one','proof'=>$proof]));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
 if($waiters<2){foreach($workers as [$proc,$pipes]){stream_set_blocking($pipes[2],false);fwrite(STDERR,stream_get_contents($pipes[2]));}}
 essentialCheck($waiters>=2,'both member writers wait on native mutex');$db->trans_commit();$results=[];
 foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','member contenders complete without diagnostics');}
 essentialCheck($results[0]===$results[1]&&ctype_digit($results[0]),'concurrent member receipts share one native payment');$id=(int)$results[0];
}finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}

essentialCheck($id>0&&$s->position($b,7,$invoice)['receivable']==='65.53','first member records only its principal');
essentialCheck($writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_one',$proof))===$id,'member retry preserves canonical payment');
$n=count($calls);essentialThrows(fn()=>$asaas->observeInstallment($a,7,$root,'pay_group_one',$dec,$receiptTransport),'asaas_installment_origin_denied','foreign group read denied');essentialCheck(count($calls)===$n,'foreign group never uses credentials');
$proof2=$asaas->observeInstallment($b,7,$root,'pay_group_two',$dec,$receiptTransport);
$id2=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyInstallmentReceipt($root,'pay_group_two',$proof2));
essentialCheck($id2!==$id&&$s->position($b,7,$invoice)['receivable']==='0.00','last member settles exact remaining native balance');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords WHERE invoiceid=?',[$invoice])->row()->n===2,'two canonical payments preserve separate member identities');
$panel=$asaas->installmentGroupPage($b,7,$root);
essentialCheck(array_column($panel['members'],'payment_id')===[$id,$id2],'group page preserves per-member payment links');
$ordinary=$asaas->overview($b,7);essentialCheck(!in_array($root,array_column($ordinary['origins'],'id')),'group is excluded from ordinary single-charge actions');
$installment_panel=$panel;$installment_groups=$asaas->installmentGroups($b,7);$can_settle=true;
ob_start();include APPPATH.'views/admin/settings/native_asaas_installments.php';$html=ob_get_clean();
essentialCheck(str_contains($html,'pay_group_one')&&str_contains($html,'pay_group_two')&&!str_contains($html,'value="reconcile_installment"'),'paid members retain consultation but no duplicate settlement button');
echo 'Installment binding/receipts: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
