<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/issuers_core_support.php'; require_once APPPATH.'services/CompanyPurchases.php'; require_once __DIR__.'/purchases_database.php';
if (($argv[1]??null)==='child') {
    $job=json_decode(base64_decode($argv[2],true),true,32,JSON_THROW_ON_ERROR);
    if (!preg_match('/^essential_[a-f0-9]{10}_$/D',$job['prefix'])) { exit(91); }
    $child=new PurchaseTestDb(new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$job['prefix']));
    $s=new CompanyPurchases($child,$job['prefix']);
    if (!empty($job['pause'])) { $child->observer=static function ($sql,$args,$db) use ($job): void { if (str_starts_with($sql,'INSERT INTO '.$job['prefix'].'company_expenses ')) { $db->observer=null; fwrite(STDOUT,"inserted\n"); fflush(STDOUT); fread(STDIN,1); } }; }
    try {
        if ($job['operation']==='quote') { [$s]=CompanyPurchases::forPortal($child,$job['prefix'],$job['secret']); }
        $value=match($job['operation']) {
            'receive'=>$s->receive($job['issuer'],$job['actor'],$job['id'],$job['revision'],$job['input'],$job['key']),
            'document'=>$s->postDocument($job['issuer'],$job['actor'],$job['input'],$job['key']),
            'settle'=>$s->settle($job['issuer'],$job['actor'],$job['id'],$job['revision'],$job['input'],$job['key']),
            'quote'=>$s->quote($job['issuer'],$job['actor'],$job['input'],$job['key']),
        };
        echo json_encode(['ok'=>true,'value'=>$value,'memory'=>ini_get('memory_limit')],JSON_THROW_ON_ERROR).PHP_EOL;
    } catch (Throwable $e) { echo json_encode(['ok'=>false,'error'=>$e->getMessage(),'memory'=>ini_get('memory_limit')],JSON_THROW_ON_ERROR).PHP_EOL; }
    exit;
}
require __DIR__.'/purchases_fixture.php';

function purchaseSpawn(array $command): array
{
    global $p; $pipes=[]; $proc=proc_open([PHP_BINARY,__FILE__,'child',base64_encode(json_encode(['prefix'=>$p]+$command,JSON_THROW_ON_ERROR))],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if (!is_resource($proc)) { throw new RuntimeException('Synthetic child unavailable'); }
    stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false); return [$proc,$pipes];
}
function purchaseCollect(array $worker): array
{
    [$proc,$pipes]=$worker; $stdout=''; $stderr=''; $deadline=microtime(true)+40;
    do { $stdout.=stream_get_contents($pipes[1]); $stderr.=stream_get_contents($pipes[2]); $status=proc_get_status($proc); if (!$status['running']) { break; } usleep(20000); } while (microtime(true)<$deadline);
    if ($status['running']) { proc_terminate($proc,9); throw new RuntimeException('Synthetic purchase worker timeout'); }
    $stdout.=stream_get_contents($pipes[1]); $stderr.=stream_get_contents($pipes[2]); foreach ($pipes as $pipe) { fclose($pipe); } proc_close($proc);
    if ($status['exitcode']!==0||$stderr!=='') { throw new RuntimeException('Synthetic purchase worker: '.$stderr.$stdout); }
    return json_decode(trim($stdout),true,32,JSON_THROW_ON_ERROR);
}
function purchaseConcurrent(array $commands,?callable $whileBlocked=null): array
{
    global $p; $dsn=getenv('ISSUERS_TEST_DSN'); $user=getenv('ISSUERS_TEST_USER'); $pass=getenv('ISSUERS_TEST_PASSWORD');
    $blocker=new PDO($dsn,$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); $observer=new EssentialTestDb(new PDO($dsn,$user,$pass),$p); $thread=(int)$blocker->query('SELECT CONNECTION_ID()')->fetchColumn(); $jobs=[];
    try {
        $blocker->beginTransaction(); $blocker->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE')->fetchAll();
        foreach ($commands as $command) { $jobs[]=purchaseSpawn($command); }
        $deadline=microtime(true)+30; $waiters=0;
        do { $waiters=essentialLockWaiters($observer,$thread); $observer->queries=[]; if ($waiters>=count($commands)) { break; } usleep(25000); } while (microtime(true)<$deadline);
        purchaseCheck($waiters>=count($commands),'independent PHP contenders observed in real database lock inventory');
        if ($whileBlocked!==null) { $whileBlocked($blocker); } $blocker->commit(); $out=[];
        foreach ($jobs as $k=>$job) { $out[]=purchaseCollect($job); unset($jobs[$k]); } return $out;
    } finally {
        if ($blocker->inTransaction()) { $blocker->rollBack(); }
        foreach ($jobs as [$proc,$pipes]) { proc_terminate($proc,9); foreach ($pipes as $pipe) { fclose($pipe); } proc_close($proc); }
    }
}
$x=purchaseOrder($a,12,'race');
$receive=['operation'=>'receive','issuer'=>$a,'actor'=>12,'id'=>$x['order'],'revision'=>1,'input'=>['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'1.000']]],'key'=>$key('race-receipt')];
$out=purchaseConcurrent([$receive,$receive]);
purchaseCheck($out[0]['ok']&&$out[1]['ok']&&$out[0]['value']===$out[1]['value'],'same-key receipt contenders return one persisted receipt');
purchaseCheck($out[0]['memory']==='128M'&&$out[1]['memory']==='128M','independent workers keep the real 128 MiB limit'); $first=$out[0]['value'];
$next=array_replace($receive,['revision'=>2,'input'=>['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'2.000']]],'key'=>$key('receive-winner-1')]); $other=array_replace($next,['key'=>$key('receive-winner-2')]);
$out=purchaseConcurrent([$next,$other]); $win=array_values(array_filter($out,static fn($r)=>$r['ok'])); $lose=array_values(array_filter($out,static fn($r)=>!$r['ok']));
purchaseCheck(count($win)===1&&count($lose)===1&&$lose[0]['error']==='company_purchase_stale','two partial receivers cannot exceed one order revision');
purchaseCheck($svc->get($a,12,'order',$x['order'])['receiving']['quantities'][1]===3000,'physical total equals ordered quantity after contention');
$doc=['operation'=>'document','issuer'=>$a,'actor'=>12,'input'=>['order_id'=>$x['order'],'receipt_ids'=>[$first,$win[0]['value']],'vendor_number'=>'RACE-INVOICE','document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$a]],'key'=>$key('doc-race-1')];
$doc2=$doc; $doc2['key']=$key('doc-race-2'); $doc2['input']['vendor_number']='OTHER-INVOICE';
$out=purchaseConcurrent([$doc,$doc2]); $win=array_values(array_filter($out,static fn($r)=>$r['ok'])); $lose=array_values(array_filter($out,static fn($r)=>!$r['ok']));
purchaseCheck(count($win)===1&&count($lose)===1&&$lose[0]['error']==='company_purchase_receipt_already_billed','competing documents allocate each receipt once');
$payable=$win[0]['value']; purchaseCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_expenses')->row()->n===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_purchase_payables')->row()->n===1,'one canonical cost/AP survives document race');
$position=$svc->get($a,12,'payable',$payable)['position'];
$settle=['operation'=>'settle','issuer'=>$a,'actor'=>12,'id'=>$payable,'revision'=>$position['revision_token'],'input'=>['amount'=>'20.00','event_date'=>$today,'external_reference'=>'Race external declaration','acknowledged'=>1],'key'=>$key('pay-race-1')]; $settle2=array_replace($settle,['key'=>$key('pay-race-2')]);
$out=purchaseConcurrent([$settle,$settle2]); $win=array_values(array_filter($out,static fn($r)=>$r['ok'])); $lose=array_values(array_filter($out,static fn($r)=>!$r['ok']));
purchaseCheck(count($win)===1&&count($lose)===1&&$lose[0]['error']==='company_purchase_stale','same AP revision allows one external settlement, never an overpayment');
purchaseCheck($svc->get($a,12,'payable',$payable)['position']['outstanding_cents']===1303,'AP balance remains exact after racing declarations');
$revoked=array_replace($settle,['revision'=>$svc->get($a,12,'payable',$payable)['position']['revision_token'],'key'=>$key('pay-revoked')]); $revoked['input']['amount']='1.00';
$out=purchaseConcurrent([$revoked],function (PDO $conn) use ($p,$a): void { $conn->exec("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id={$a} AND staff_id=12 AND feature='purchases' AND capability='pay'"); });
purchaseCheck(!$out[0]['ok']&&$out[0]['error']==='company_purchase_denied','pay revocation during observed wait is enforced after acquiring lock');
purchaseCheck($svc->get($a,7,'payable',$payable)['position']['outstanding_cents']===1303,'revoked waiter has no AP effect');
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'purchases','pay')",[$a]);
$pending=$svc->createRequest($a,12,['title'=>'Pending portal race','needed_date'=>$today,'currency'=>'BRL','lines'=>[['description'=>'Portal race','unit'=>'UN','quantity'=>'1.000']]],$key('pending-portal-race')); $invite=$svc->issueInvite($a,12,$pending,$x['supplier'],1,$key('race-invite'));
$q=['operation'=>'quote','issuer'=>$a,'actor'=>12,'secret'=>$invite['secret'],'input'=>['request_id'=>$pending,'supplier_id'=>$x['supplier'],'valid_until'=>$today,'unit_prices'=>['1.00'],'tax_rate'=>'0.00'],'key'=>$key('revoked-portal-quote')];
$out=purchaseConcurrent([$q],function (PDO $conn) use ($p,$invite): void { $conn->exec('UPDATE '.$p.'company_purchase_invites SET revoked=1,revision=revision+1 WHERE id='.(int)$invite['id']); });
purchaseCheck(!$out[0]['ok']&&$out[0]['error']==='company_purchase_portal_denied','invitation revoked while portal quote waits is revalidated from persisted origin');
$crash=purchaseOrder($b,12,'crash'); $r=$svc->receive($b,12,$crash['order'],1,['received_date'=>$today,'lines'=>[['line'=>1,'quantity'=>'3.000']]],$key('crash-receipt'));
$job=['operation'=>'document','issuer'=>$b,'actor'=>12,'input'=>['order_id'=>$crash['order'],'receipt_ids'=>[$r],'vendor_number'=>'CRASH-INVOICE','document_date'=>$today,'due_date'=>$today,'category_id'=>$categories[$b]],'key'=>$key('crash-post'),'pause'=>true];
$worker=purchaseSpawn($job); [$proc,$pipes]=$worker; $line=''; $deadline=microtime(true)+30;
while (!str_contains($line,"inserted\n")&&microtime(true)<$deadline) { $line.=stream_get_contents($pipes[1]); usleep(20000); }
purchaseCheck(str_contains($line,"inserted\n"),'worker paused after actual canonical expense INSERT inside composed transaction');
purchaseCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_expenses WHERE issuer_id=?',[$b])->row()->n===0,'other connection cannot see uncommitted cost before AP exists');
purchaseCheck(proc_terminate($proc,9),'only synthetic worker terminated with SIGKILL'); foreach ($pipes as $pipe) { fclose($pipe); } proc_close($proc);
unset($job['pause']); $retry=purchaseCollect(purchaseSpawn($job)); purchaseCheck($retry['ok'],'retry after process death succeeds without ambiguous partial outcome');
purchaseCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_expenses WHERE issuer_id=?',[$b])->row()->n===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_purchase_payables WHERE issuer_id=?',[$b])->row()->n===1,'crash/retry persists one expense and one AP with original receipts');
echo 'Purchases actual concurrency: '.$GLOBALS['essentialChecks'].' checks; six observed races and one SIGKILL/retry passed on '.$db->pdo->getAttribute(PDO::ATTR_SERVER_VERSION).PHP_EOL;
