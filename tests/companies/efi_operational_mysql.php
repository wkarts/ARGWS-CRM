<?php
/** Real CI3/MySQL schema + canonical writer; provider responses are synthetic, never fetched. */
declare(strict_types=1);
require __DIR__.'/legacy_financial_module_fixture.php';
class CI_Migration { public $db; }
require APPPATH.'migrations/473_version_473.php';
require APPPATH.'services/InvoicePaymentWriter.php';
require APP_MODULES_PATH.'gerencianet_gateway/services/EfiChargeLedger.php';
require APP_MODULES_PATH.'gerencianet_gateway/services/EfiChargeIntent.php';
if (($argv[1]??'')==='intent-worker') {
 $prefix=$argv[2]??'';
 if(!preg_match('/^efi_receipt_[a-f0-9]{16}_$/D',$prefix))throw new RuntimeException('fixture prefix invalid');
 $GLOBALS['moduleReaderPrefix']=$prefix;$db->set_dbprefix($prefix);
 $db->query('SET SESSION innodb_lock_wait_timeout=10');
 echo 'READY:'.$db->query('SELECT CONNECTION_ID() AS id')->row()->id."\n";flush();
 try {
  $invoice=(new EfiChargeLedger($db,$prefix))->invoice(26,'race_hash');
  $permit=(new EfiChargeIntent($db,$prefix))->reserve($argv[3],['client_id'=>'synthetic_client','sandbox'=>true],$invoice);
  echo $permit['dispatch']?"DISPATCH\n":"READ_ONLY\n";
 }catch(Throwable $error){echo "REVIEW\n";}
 exit;
}
$prefix='efi_receipt_'.bin2hex(random_bytes(8)).'_';$GLOBALS['moduleReaderPrefix']=$prefix;$db->set_dbprefix($prefix);
$created=[];$checks=0;$children=[];
function efiRealCheck($ok,$name){++$GLOBALS['checks'];if(!$ok)throw new RuntimeException($name);}
function efiRealReject($run,$name){try{$run();}catch(Throwable $error){efiRealCheck(true,$name);return;}throw new RuntimeException('Expected rejection: '.$name);}
try {
 $definitions=[
  'invoices'=>'id INT PRIMARY KEY,issuer_id INT NULL,hash VARCHAR(64),total DECIMAL(15,2),status INT,currency INT',
  'currencies'=>'id INT PRIMARY KEY,name VARCHAR(3)',
  'invoicepaymentrecords'=>'id INT PRIMARY KEY AUTO_INCREMENT,invoiceid INT,amount DECIMAL(15,2),paymentmode VARCHAR(40),paymentmethod VARCHAR(40),transactionid VARCHAR(255),date DATE,daterecorded DATETIME',
  'credits'=>'id INT PRIMARY KEY AUTO_INCREMENT,invoice_id INT,amount DECIMAL(15,2)',
 ];
 foreach($definitions as $suffix=>$sql){$table=$prefix.$suffix;$db->query("CREATE TABLE `{$table}` ({$sql}) ENGINE=InnoDB");$created[]=$table;}
 $migration=new Migration_Version_473();$migration->db=$db;$migration->up();$created[]=$prefix.'module_version_adoptions';$created[]=$prefix.'efi_charge_bindings';$created[]=$prefix.'efi_charge_intents';foreach(['accounts','origins','proofs','receipts'] as $suffix)$created[]=$prefix.'efi_company_'.$suffix;
 $db->query("INSERT INTO {$prefix}currencies VALUES (1,'BRL')");
 $db->query("INSERT INTO {$prefix}invoices VALUES (17,NULL,'synthetic_hash',10.00,1,1),(18,9,'tenant_hash',22.00,1,1)");
 $ledger=new EfiChargeLedger($db,$prefix);$invoice=$ledger->invoice(17,'synthetic_hash');$options=['client_id'=>'synthetic_client','sandbox'=>true];
 $one=str_repeat('a',26);$two=str_repeat('b',26);
 $ledger->bind('pix',$one,$options,$invoice);$ledger->bind('pix',$two,$options,$invoice);
 $paid=fn($id)=>['status'=>'CONCLUIDA','txid'=>$id,'valor'=>['original'=>'10.00'],'pix'=>[['txid'=>$id,'valor'=>'10.00','endToEndId'=>str_repeat('E',32)]]];
 // Another connection holding the invoice advisory lock blocks a different charge too.
 $lock='efi:'.substr(hash('sha256',$db->database.':'.$prefix.':17'),0,58);
 $peer=DB(['dbdriver'=>'pdo','subdriver'=>'mysql','dsn'=>getenv('ISSUERS_TEST_DSN'),'database'=>$db->database,
     'username'=>getenv('ISSUERS_TEST_USER'),'password'=>getenv('ISSUERS_TEST_PASSWORD'),'dbprefix'=>$prefix,
     'db_debug'=>false,'pconnect'=>false,'char_set'=>'utf8mb4','dbcollat'=>'utf8mb4_bin'],true);
 efiRealCheck((int)$peer->query('SELECT GET_LOCK(?,0) AS acquired',[$lock])->row()->acquired===1,'second connection holds invoice lock');
 $duringBusy=0;
 efiRealReject(fn()=>$ledger->settle('pix',$two,$options,$invoice,$paid($two),function()use(&$duringBusy){++$duringBusy;return false;}),'different charge contends on same invoice lock');
 efiRealCheck($duringBusy===0,'lock contender never invokes payment writer');
 $peer->query('SELECT RELEASE_LOCK(?)',[$lock]);$peer->close();
 $writes=0;$write=function(array $data)use($db,&$writes){++$writes;$data['date']='2026-10-24';return (new \app\services\InvoicePaymentWriter($db))->record($data['invoiceid'],$data,fn()=>true);};
 // Fail the native receipt INSERT: actual writer must roll back, binding remains unpaid.
 $trigger=$prefix.'fail_receipt';$db->query("CREATE TRIGGER `{$trigger}` BEFORE INSERT ON {$prefix}invoicepaymentrecords FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'");
 efiRealReject(fn()=>$ledger->settle('pix',$one,$options,$invoice,$paid($one),$write),'native receipt SQL failure');
 efiRealCheck((int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}invoicepaymentrecords")->row()->n===0,'writer rollback no receipt');
 efiRealCheck($ledger->bound('pix',$one,$options,$invoice)['payment_id']===null,'failed writer no binding marker');
 $db->query("DROP TRIGGER `{$trigger}`");
 // Native receipt commits before tracker. A tracker fault is recovered on replay, not duplicated.
 $trigger=$prefix.'fail_tracking';$db->query("CREATE TRIGGER `{$trigger}` BEFORE UPDATE ON {$prefix}efi_charge_bindings FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic tracker failure'");
 efiRealReject(fn()=>$ledger->settle('pix',$one,$options,$invoice,$paid($one),$write),'tracking SQL failure after native commit');
 efiRealCheck((int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}invoicepaymentrecords")->row()->n===1,'one committed native receipt');
 $db->query("DROP TRIGGER `{$trigger}`");$before=$writes;
 $id=$ledger->settle('pix',$one,$options,$invoice,$paid($one),$write);
 efiRealCheck($id>0&&$writes===$before,'replay tracks without second native write');
 // A different bound charge for the same invoice is rejected by the real balance lock/writer.
 efiRealReject(fn()=>$ledger->settle('pix',$two,$options,$invoice,$paid($two),$write),'second charge cannot overpay');
 efiRealCheck((int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}invoicepaymentrecords")->row()->n===1,'two charge attempts leave one receipt');
 efiRealCheck($ledger->bound('pix',$two,$options,$invoice)['payment_id']===null,'rejected second charge has no payment marker');
 efiRealCheck($db->query("SELECT total FROM {$prefix}invoices WHERE id=18")->row()->total==='22.00','foreign tenant sentinel unchanged');
 $foreign=$invoice;$foreign['id']=18;$foreign['hash']='tenant_hash';efiRealReject(fn()=>$ledger->bind('pix',str_repeat('c',26),$options,$foreign),'foreign invoice binding denied');
 // Intent creation uses durable commits, not shared mutable fixture state.
 $db->query("INSERT INTO {$prefix}invoices VALUES (19,NULL,'unknown_hash',10.00,1,1),(20,NULL,'draft_hash',10.00,6,1),(21,NULL,'partial_hash',10.00,3,1),(22,NULL,'raw_hash',10.00,1,1),(23,NULL,'outer_hash',10.00,1,1),(25,NULL,'bound_hash',10.00,1,1),(26,NULL,'race_hash',10.00,1,1)");
 $intent=new EfiChargeIntent($db,$prefix);$new=$ledger->invoice(19,'unknown_hash');
 $permit=$intent->reserve('pix',$options,$new);
 efiRealCheck($permit['dispatch']&&!$db->conn_id->inTransaction(),'dispatch only after real commit');
 $intent->uncertain($permit);
 efiRealReject(fn()=>$intent->reserve('pix',$options,$new),'unknown never rePOSTs');
 efiRealReject(fn()=>$intent->reserve('card',$options,$new),'modality switch does not create second intent');
 efiRealReject(fn()=>$intent->reserve('pix',$options,$ledger->invoice(20,'draft_hash')),'draft cannot reserve');
 $db->query("INSERT INTO {$prefix}credits(invoice_id,amount) VALUES(21,0.01)");
 efiRealReject(fn()=>$intent->reserve('pix',$options,$ledger->invoice(21,'partial_hash')),'credited invoice cannot be charged in full');
 $db->conn_id->beginTransaction();
 try{efiRealReject(fn()=>$intent->reserve('pix',$options,$ledger->invoice(22,'raw_hash')),'raw transaction denied before remote permission');}
 finally{$db->conn_id->rollBack();}
 $db->trans_begin();
 try{efiRealReject(fn()=>$intent->reserve('pix',$options,$ledger->invoice(23,'outer_hash')),'outer CI transaction denied');}
 finally{$db->trans_rollback();}
 $new=$ledger->invoice(25,'bound_hash');$permit=$intent->reserve('pix',$options,$new);
 $intent->complete($permit,str_repeat('d',26),$options,$new);
 efiRealCheck($intent->reserve('pix',$options,$new)['dispatch']===false,'bound replay GET-only');
 // Both real processes reach the same locked invoice before either can reserve.
 $db->trans_begin();$db->query("SELECT id FROM {$prefix}invoices WHERE id=26 FOR UPDATE");
 $ids=[];
 foreach(['pix','card'] as $kind){
  $proc=proc_open([PHP_BINARY,__FILE__,'intent-worker',$prefix,$kind],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  if(!is_resource($proc))throw new RuntimeException('intent child unavailable');fclose($pipes[0]);stream_set_timeout($pipes[1],15);
  $children[]=[$proc,$pipes];$line=trim((string)fgets($pipes[1]));
  if(!preg_match('/^READY:([1-9][0-9]*)$/D',$line,$match))throw new RuntimeException('intent child not ready');$ids[]=(int)$match[1];
 }
 $deadline=microtime(true)+8;$waiting=0;
 do {
  $waiting=(int)$db->query('SELECT COUNT(DISTINCT requester.PROCESSLIST_ID) AS n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads requester ON requester.THREAD_ID=w.REQUESTING_THREAD_ID WHERE requester.PROCESSLIST_ID IN (?,?)',$ids)->row()->n;
  if($waiting===2)break;usleep(50000);
 }while(microtime(true)<$deadline);
 efiRealCheck($waiting===2,'both actual writers contend at invoice row lock');$db->trans_commit();
 $outcomes=[];
 foreach($children as [$proc,$pipes]){$outcomes[]=trim(stream_get_contents($pipes[1]));$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);efiRealCheck(proc_close($proc)===0&&$stderr==='','child exits without warnings');}
 $children=[];sort($outcomes);efiRealCheck($outcomes===['DISPATCH','REVIEW'],'two modalities yield exactly one remote-dispatch permission');
 efiRealCheck((int)$db->query("SELECT COUNT(*) AS n FROM {$prefix}efi_charge_intents WHERE invoice_id=26")->row()->n===1,'one durable invoice intent after concurrent attempts');
 echo "Efí real MySQL/canonical writer: {$checks} checks PASS; provider transport synthetic.\n";
} finally {if($db->conn_id->inTransaction())$db->trans_rollback();foreach($children as [$proc,$pipes]){proc_terminate($proc);foreach([1,2] as $fd)if(is_resource($pipes[$fd]))fclose($pipes[$fd]);proc_close($proc);}foreach(array_reverse($created) as $table)$db->query("DROP TABLE IF EXISTS `{$table}`");}
