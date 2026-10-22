<?php
/** Two independent native DB sessions contend for one durable remote mutation. Provider is simulated. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APP_MODULES_PATH.'asaas/libraries/Asaas/CompanyRemoteOperations.php';
require_once __DIR__.'/asaas_job_transport_fixture.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function asaasRemoteConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$in=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);if(!preg_match('~^/tmp/asaas-remote-test-[a-f0-9]{20}\.jsonl$~D',$in['evidence']??''))exit(2);$db=asaasRemoteConnection($p);$GLOBALS['essentialPrefix']=$p;
    $transport=function($environment,$method,$path,$payload,$secret)use($db,$p,$in){
        if($db->pdo->inTransaction())throw new LogicException('fixture HTTP under transaction');$letter=str_contains($secret,'key-A')?'A':'B';
        if($path==='wallets/')return ['data'=>[['id'=>'wallet-'.$letter]],'hasMore'=>false];
        if(str_starts_with($path,'customers/'))return ['id'=>substr($path,10),'cpfCnpj'=>$letter==='A'?'11144477735':'12345678909','deleted'=>false];
        $remote=substr($path,9);$row=$db->query('SELECT o.*,m.customer_id,m.billing_type,m.account_row,m.snapshot_json AS invoice_snapshot_json FROM '.$p.'asaas_payment_origins o JOIN '.$p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.remote_id=? AND o.account_id=? AND o.environment=?',[$remote,'wallet-'.$letter,$environment])->row_array();if(!$row)throw new LogicException('fixture identity missing');
        if($method==='DELETE'){$state=$db->query('SELECT state FROM '.$p.'asaas_company_remote_operations WHERE origin_id=?',[$row['id']])->row()->state;if($state!=='sending')throw new LogicException('delete without durable sending intention');file_put_contents($in['evidence'],$remote."\n",FILE_APPEND|LOCK_EX);return ['id'=>$remote,'deleted'=>true];}
        $deleted=in_array($remote,is_file($in['evidence'])?file($in['evidence'],FILE_IGNORE_NEW_LINES):[],true);
        return asaasOriginDescriptionFixture($db,$p,$row)+['id'=>$remote,'customer'=>$row['customer_id'],'billingType'=>$row['billing_type'],'externalReference'=>$row['invoice_hash'],'value'=>$row['invoice_total'],'dueDate'=>$row['invoice_due_date'],'status'=>'PENDING','netValue'=>$row['invoice_total'],'paymentDate'=>'','creditDate'=>'','deleted'=>$deleted];
    };
    try{echo json_encode((new \Asaas\CompanyRemoteOperations($db,$p))->remove($in['issuer'],7,$in['origin'],$in['key'],fn($v)=>str_starts_with($v,'synthetic:')?base64_decode(substr($v,10),true):false,$transport),JSON_THROW_ON_ERROR);}catch(DomainException $e){if($e->getMessage()!=='asaas_company_access_denied')throw $e;echo 'denied';}exit(0);
}
require __DIR__.'/asaas_remote_operations.php';$observer=asaasRemoteConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['normal','revocation'] as $mode){
    [$raceInvoice,$raceOrigin]=asaasUnpaid($b,$customerB,'cus_b','real-race-'.$mode);$evidence='/tmp/asaas-remote-test-'.bin2hex(random_bytes(10)).'.jsonl';$key=hash('sha256','real-race-'.$mode);$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$b,'origin'=>$raceOrigin,'key'=>$key,'evidence'=>$evidence],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two remote removal workers observed waiting on actual native transaction lock');
        if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$out=[];
        foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','independent remote removal worker completes without unexpected diagnostics');}
        if($mode==='normal'){essentialCheck(count(is_file($evidence)?file($evidence,FILE_IGNORE_NEW_LINES):[])===1,'two actual workers emit precisely one DELETE after one durable intention');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_company_remote_operations WHERE origin_id=?",[$raceOrigin])->row()->n===1,'one remote operation ledger survives contention');$confirmTransport=function($environment,$method,$path,$payload,$secret)use($mock){$result=$mock($environment,$method,$path,$payload,$secret);if($method==='GET'&&str_starts_with($path,'payments/'))$result['deleted']=true;return $result;};essentialCheck($removal->refresh($b,7,$raceOrigin,$dec,$confirmTransport)['state']==='deleted','canonical GET confirms deletion after concurrent requests');}
        else{essentialCheck($out===['denied','denied']&&!is_file($evidence),'revocation under transaction lock denies both requests before external deletion');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}asaas_company_remote_operations WHERE origin_id=?",[$raceOrigin])->row()->n===0,'revoked contenders leave no unauthorized durable intention');}
        essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$raceInvoice])->row()->n===0,'remote-operation contention creates no nominal receipt');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();if(is_file($evidence))unlink($evidence);}
}
echo 'Company Asaas durable remote removal concurrency: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks; provider simulated.'.PHP_EOL;
