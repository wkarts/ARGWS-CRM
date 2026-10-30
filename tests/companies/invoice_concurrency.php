<?php
/** Named-company invoice/receipt races, actual MySQL connections and observed lock waits. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoices.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function raceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)){exit(2);}
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=raceConnection($p);$GLOBALS['essentialPrefix']=$p;
    try{
        if($input['operation']==='create')echo (new CompanyInvoices($db,$p))->create($input['issuer'],7,$input['invoice'],$input['key']);
        elseif($input['operation']==='update'){(new CompanyInvoices($db,$p))->change($input['issuer'],7,$input['invoice_id'],$input['revision'],$input['invoice']);echo 'updated';}
        else echo (new \app\services\InvoicePaymentWriter($db))->recordCompanyManual($input['issuer'],7,$input['invoice_id'],$input['payment'],$input['key']);
    }catch(DomainException $error){if(in_array($error->getMessage(),['company_invoice_stale','company_invoice_access_denied','company_payment_access_denied','company_payment_exceeds_balance','company_payment_invoice_not_eligible'],true)){echo 'denied';}else{throw $error;}}
    exit(0);
}
require __DIR__.'/invoices.php';
$observer=raceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function race(array $input,?callable $beforeRelease=null): array
{
    global $db,$observer,$p,$blocker;
    $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){
            $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process)){throw new RuntimeException('worker unavailable');}
            fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        $deadline=microtime(true)+15;$waiters=0;
        do{
            $waiters=essentialLockWaiters($observer,$blocker);
            if($waiters>=2){break;}usleep(20000);
        }while(microtime(true)<$deadline);
        essentialCheck($waiters>=2,'both real connections observed waiting on installation row lock');
        if($beforeRelease){$beforeRelease();}
        $db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){
            $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            essentialCheck(proc_close($process)===0&&$err==='','worker completes without diagnostic leakage');$outputs[]=$out;
        }
        return $outputs;
    }finally{if($db->pdo->inTransaction()){$db->trans_rollback();}}
}
$out=race(['operation'=>'create','issuer'=>$a,'invoice'=>$input,'key'=>str_repeat('5',64)]);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent invoice retry returns one invoice ID and one number');
$new=(int)$out[0];$receipt=['amount'=>'105.53','date'=>'2026-10-19','reference'=>'concurrent-evidence','note'=>'Synthetic race evidence'];
$out=race(['operation'=>'payment','issuer'=>$a,'invoice_id'=>$new,'payment'=>$receipt,'key'=>str_repeat('6',64)]);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent receipt retry inserts once despite paid status on retry');
essentialCheck(count($s->payments($a,7,$new))===1&&$s->document($a,7,$new)->data()['status']===2,'exactly one durable payment and paid state');
$revoked=$s->create($a,7,$input,str_repeat('7',64));
essentialCheck(race(['operation'=>'payment','issuer'=>$a,'invoice_id'=>$revoked,'payment'=>$receipt,'key'=>str_repeat('8',64)],fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'revocation during lock wait blocks both positive-company payments');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoicepaymentrecords WHERE invoiceid=?",[$revoked])->row()->n===0,'no orphan receipt after revocation race');
echo 'Named-company invoice/payment concurrency: '.$GLOBALS['essentialChecks'].' checks passed, actual MySQL row locks.'.PHP_EOL;

$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
$editable=$s->create($a,7,$input,str_repeat('9',64));$editRevision=$s->revision($a,7,$editable);
$adjusted=$input;$adjusted['discount_mode']='percent';$adjusted['discount_value']='10';$adjusted['discount_type']='before_tax';$adjusted['adjustment']='-0.01';
$out=race(['operation'=>'update','issuer'=>$a,'invoice_id'=>$editable,'revision'=>$editRevision,'invoice'=>$adjusted]);sort($out);
essentialCheck($out===['denied','updated'],'concurrent monetary editors have one winner and one stale revision');
essentialCheck($s->document($a,7,$editable)->data()['totals']['total']==='94.96','winning before-tax discount, rounded tax and adjustment persist together');
$editRevision=$s->revision($a,7,$editable);
essentialCheck(race(['operation'=>'update','issuer'=>$a,'invoice_id'=>$editable,'revision'=>$editRevision,'invoice'=>$input],fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'revocation while waiting on lock denies both monetary updates');
essentialCheck($db->query("SELECT total FROM {$p}invoices WHERE id=?",[$editable])->row()->total==='94.96','revoked editors leave financial amount unchanged');
echo 'Expanded monetary edits/concurrency/revocation: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
