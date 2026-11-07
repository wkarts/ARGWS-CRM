<?php
require __DIR__.'/asaas_positive.php';
require_once FCPATH.'modules/asaas/libraries/Asaas/CompanyInvoiceEdits.php';
require_once APPPATH.'services/CompanyAsaasInvoiceEditSource.php';
require_once APPPATH.'services/NativeCompanyCustomFieldSchema.php';
require_once APPPATH.'services/NativeCompanyWorkTagsSchema.php';
require_once __DIR__.'/../support/NativeInstalledSchemaFixture.php';
$db->queries=[];$editStart=$GLOBALS['essentialChecks'];
$plan=NativeInstalledSchemaFixture::plan(file_get_contents(dirname(__DIR__,2).'/install/database.sql'),$p);
foreach(['tags','taggables','customfields','customfieldsvalues'] as $table)if(!$db->table_exists($p.$table)){$db->query($plan[$table]['create']);foreach($plan[$table]['alters'] as $sql)$db->query($sql);}
NativeCompanyInvoiceCommandSchema::ensure($db,$p);NativeCompanyCustomFieldSchema::ensure($db,$p);NativeCompanyWorkTagsSchema::ensure($db,$p);
\Asaas\CompanyOriginPolicy::ensure($db,$p,true);\Asaas\CompanyInvoiceEditSchema::ensure($db,$p);$db->query("UPDATE {$p}modules SET installed_version='1.7.0' WHERE module_name='asaas'");
[$invoice,$origin,$proof,$remote]=asaasFreshCredit($b,$customerB,'cus_b','native-edit','PENDING');
$responses[$remote]['paymentDate']='';$responses[$remote]['creditDate']='';$asaas->observe($b,7,$origin,$dec,$mock);
$originalOrigin=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array();$originalMeta=$db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$origin])->row_array();
$edits=new \Asaas\CompanyInvoiceEdits($db,$p);$puts=0;$loseResponse=false;$failLocal=false;
$editTransport=function($environment,$method,$path,$payload,$secret)use($mock,&$responses,&$puts,&$loseResponse,&$failLocal,$db,$p){
 if($method==='PUT'){$puts++;$id=substr($path,9);$responses[$id]=array_replace($responses[$id],$payload);if($failLocal)$db->failOn='UPDATE '.$p.'invoices SET date=';if($loseResponse)throw new RuntimeException('simulated committed PUT with lost response');return $responses[$id];}
 return $mock($environment,$method,$path,$payload,$secret);
};
foreach(['uncertain','rollback'] as $stage){
 $revision=$s->revision($b,7,$invoice);$payload=array_replace($input,['client_id'=>$customerB,'due_date'=>$stage==='uncertain'?'2026-12-05':'2026-12-15']);$payload['items'][0]['rate']=$stage==='uncertain'?'170.00':'190.00';
 $lines=$db->query("SELECT id FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice])->result_array();
 $metadata=['invoice_fields'=>[],'line_fields'=>array_fill(0,count($lines),[]),'tags'=>[],'tags_revision'=>(new NativeCompanyResourceTags($db,$p))->read($b,7,'invoice',$invoice)['revision'],'line_sources'=>array_map('intval',array_column($lines,'id')),'removed_line_ids'=>[]];
 $key=hash('sha256','native-edit-'.$stage);$before=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->row_array();$beforePuts=$puts;
 $loseResponse=$stage==='uncertain';$failLocal=$stage==='rollback';
 $db->lastQueryFailure=null;$expected=$loseResponse?'asaas_invoice_edit_remote_unconfirmed':'issuer_storage_failed';$caught=null;
 try{$edits->update($b,7,$invoice,$revision,$payload,$metadata,$key,$dec,$editTransport);}catch(Throwable $error){$caught=$error->getMessage();if($caught!==$expected)fwrite(STDERR,json_encode($db->lastQueryFailure).PHP_EOL.$error->getTraceAsString().PHP_EOL);}
 essentialCheck($caught===$expected,'uncertain PUT or failed local transaction never reports successful edit: '.($caught??'no exception'));
 $loseResponse=false;$failLocal=false;$db->failOn=null;
 essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->row_array()===$before,'preview and failed completion leave invoice bytes unchanged');
 essentialCheck($puts===$beforePuts+1,'one remote PUT was dispatched');
 essentialThrows(fn()=>$asaas->observe($b,7,$origin,$dec,$mock),'asaas_invoice_edit_pending','ordinary provider query cannot cross pending snapshot revision');
 $db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");
 essentialThrows(fn()=>$edits->recover($b,7,$key,$dec,$editTransport),'asaas_company_access_denied','revoked actor cannot recover remote edit');
 $db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
 essentialThrows(fn()=>$edits->recover($a,7,$key,$dec,$editTransport),'asaas_invoice_edit_command_conflict','another company cannot recover command');
 $result=$edits->recover($b,7,$key,$dec,$editTransport);
 essentialCheck($result['invoice_id']===$invoice&&$puts===$beforePuts+1,'GET recovery commits native form without repeating PUT');
 essentialCheck($edits->update($b,7,$invoice,$revision,$payload,$metadata,$key,$dec,$editTransport)===$result&&$puts===$beforePuts+1,'full edit command replay returns same native receipt');
 $after=$db->query('SELECT total,duedate FROM '.$p.'invoices WHERE id=?',[$invoice])->row_array();essentialCheck($after['duedate']===$payload['due_date']&&$after['total']===$responses[$remote]['value'],'native amount and due date match confirmed provider state');
 essentialCheck($db->query('SELECT * FROM '.$p.'asaas_company_origin_meta WHERE origin_id=?',[$origin])->row_array()===$originalMeta,'original origin metadata remains byte-identical');
 $asaas->observe($b,7,$origin,$dec,$mock);
}
$journal=$db->query("SELECT * FROM {$p}asaas_company_invoice_edits WHERE origin_id=? ORDER BY sequence_no",[$origin])->result_array();
$db->query("UPDATE {$p}asaas_company_invoice_edits SET result_hash=? WHERE request_key=?",[str_repeat('0',64),$key]);
essentialThrows(fn()=>$asaas->observe($b,7,$origin,$dec,$mock),'asaas_invoice_edit_history_conflict','corrupt confirmation blocks ordinary settlement source');
$db->query("UPDATE {$p}asaas_company_invoice_edits SET result_hash=? WHERE request_key=?",[$journal[1]['result_hash'],$key]);
// Race two independent sessions on a durable, already reviewed edit intent.
$revision=$s->revision($b,7,$invoice);$payload['due_date']='2026-12-20';$payload['items'][0]['rate']='200.00';
$lines=$db->query("SELECT id FROM {$p}itemable WHERE rel_id=? AND rel_type='invoice' ORDER BY item_order,id",[$invoice])->result_array();
$metadata['line_sources']=array_map('intval',array_column($lines,'id'));$metadata['tags_revision']=(new NativeCompanyResourceTags($db,$p))->read($b,7,'invoice',$invoice)['revision'];
$payload=CompanyInvoiceDraftsInput::normalize($payload);$key=hash('sha256','native-edit-race');$request=compact('invoice','revision','payload','metadata','key');$edits->prepare($b,7,$origin,$request);
$observer=new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;$evidence='/tmp/asaas-remote-test-'.bin2hex(random_bytes(10)).'.jsonl';$workers=[];
$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");
try{
 for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/asaas_remote_concurrency.php','worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['operation'=>'edit','issuer'=>$b,'origin'=>$origin,'key'=>$key,'evidence'=>$evidence],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
 $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two edit workers contend on actual financial lock');$db->trans_commit();$outputs=[];
 foreach($workers as [$proc,$pipes]){$outputs[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($proc);if($exit!==0)fwrite(STDERR,end($outputs).$error);essentialCheck($exit===0&&$error==='','edit worker exits without diagnostics');}
 essentialCheck(count(is_file($evidence)?file($evidence,FILE_IGNORE_NEW_LINES):[])===1,'concurrent recovery dispatches precisely one PUT');
 essentialCheck(in_array('edited',$outputs,true),'one concurrent recovery applies native edit');
 essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE subject_id=? AND event='company_asaas_invoice_edited'",[$invoice])->row()->n===3,'one audit per revision after concurrent edit');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();if(is_file($evidence))unlink($evidence);}
$finalSnapshot=json_decode($db->query("SELECT after_json FROM {$p}asaas_company_invoice_edits WHERE request_key=?",[$key])->row()->after_json,true,32,JSON_THROW_ON_ERROR);$responses[$remote]['value']=$finalSnapshot['total'];$responses[$remote]['dueDate']=$finalSnapshot['duedate'];
$afterOrigin=$db->query('SELECT * FROM '.$p.'asaas_payment_origins WHERE id=?',[$origin])->row_array();foreach(['invoice_id','invoice_hash','invoice_total','invoice_due_date','remote_id','issuer_id','account_id','environment'] as $field)essentialCheck($afterOrigin[$field]===$originalOrigin[$field],'immutable original field preserved: '.$field);
require_once APPPATH.'services/CompanyAsaasInvoiceCancellation.php';require_once APPPATH.'services/CompanyAsaasInvoiceRecreation.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRefunds.php';
\Asaas\CompanyOriginGenerations::ensure($db,$p);\Asaas\CompanyRecreationSchema::ensure($db,$p);\Asaas\CompanyPreferencesSchema::ensure($db,$p);\Asaas\CompanyRefundSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$cycleTransport=function($env,$method,$path,$body,$secret)use($mock,&$responses){if($method==='DELETE'){$id=substr($path,9);$responses[$id]['deleted']=true;return ['id'=>$id,'deleted'=>true];}return $mock($env,$method,$path,$body,$secret);};
(new CompanyAsaasInvoiceCancellation($db,$p))->cancel($b,7,$invoice,$s->revision($b,7,$invoice),$dec,$cycleTransport);
(new CompanyAsaasInvoiceRecreation($db,$p))->reopen($b,7,$invoice,$s->revision($b,7,$invoice),$dec,$cycleTransport);
$current=$db->query("SELECT * FROM {$p}asaas_payment_origins WHERE invoice_id=? AND active_slot=1",[$invoice])->row_array();
essentialCheck((int)$current['id']!==$origin&&$current['invoice_total']===$finalSnapshot['total']&&$current['invoice_due_date']===$finalSnapshot['duedate'],'recreation inherits effective edited snapshot without changing predecessor');
$origin=(int)$current['id'];$remote=$current['remote_id'];
IssuerTransaction::run($db,$p,(new \Asaas\CompanyRefunds($db,$p))->tables(),fn()=>\Asaas\CompanyOriginGenerations::postRefundOriginInOwned($db,$p,$b,$invoice,$origin));
essentialCheck(true,'generation validator accepts edited predecessor followed by matching successor');
$responses[$remote]['status']='RECEIVED';$responses[$remote]['paymentDate']='2026-11-06';$responses[$remote]['creditDate']='2026-11-06';$responses[$remote]['netValue']=$responses[$remote]['value'];$received=$asaas->observe($b,7,$origin,$dec,$mock);
$payment=$writer->recordCompanyProviderReceipt($b,7,new \Asaas\CompanyReceivedReceipt($origin,$received));essentialCheck($payment>0,'ordinary settlement consumes verified effective snapshot after edits');
echo 'Asaas invoice edits: '.($GLOBALS['essentialChecks']-$editStart).' additional SQL checks passed; provider simulated.'.PHP_EOL;
