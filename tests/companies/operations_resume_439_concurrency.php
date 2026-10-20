<?php
/** Rebuilt concurrency proof. Two real child processes, observed InnoDB lock waits. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyStandaloneBilling.php';
require_once APPPATH.'services/CompanyTaskTimeBilling.php';
require_once APPPATH.'services/CompanyTaskTimeBatch.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')) { fwrite(STDERR,"An isolated MySQL DSN is required.\n"); exit(2); }
function operationsResumeConnection(string $prefix):EssentialTestDb {
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker') {
    $p=$argv[2]??'';
    if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
    $in=json_decode(stream_get_contents(STDIN),true,32,JSON_THROW_ON_ERROR);
    $GLOBALS['essentialPrefix']=$p; $db=operationsResumeConnection($p);
    try {
        $invoice=match($in['kind']) {
            'standalone'=>(new CompanyStandaloneBilling($db,$p))->convert($in['issuer'],39,$in['task'],$in['origin']),
            'selected'=>(new CompanyTaskTimeBilling($db,$p))->convert($in['issuer'],39,$in['origin']),
            'batch'=>(new CompanyTaskTimeBatch($db,$p))->convert($in['issuer'],39,$in['origin']),
            default=>throw new RuntimeException('unknown recovery test kind')
        };
        echo 'invoice:'.$invoice;
    } catch(DomainException $e) {
        if(!in_array($e->getMessage(),['company_standalone_billing_denied','company_time_billing_denied','company_time_batch_denied','company_task_billing_denied','company_standalone_billing_stale'],true)) throw $e;
        echo $e->getMessage();
    }
    exit(0);
}
require __DIR__.'/operations_resume_439_billing.php';
$db->query('INSERT INTO '.$p.'staff(staffid,active,admin) VALUES(39,1,0)');
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,39,1)',[$a]);
foreach(['tasks'=>['view','edit'],'projects'=>['view'],'invoices'=>['view','create'],'customers'=>['view']] as $feature=>$caps)
    foreach($caps as $cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,39,?,?)',[$a,$feature,$cap]);
$observer=operationsResumeConnection($p);
$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$cases=[['standalone','same_origin'],['standalone','revoked'],['standalone','source_changed'],['selected','same_origin'],['selected','revoked'],['batch','same_origin'],['batch','revoked']];
foreach($cases as [$kind,$scenario]) {
    $db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=39',[$a]);
    $label='race-'.$kind.'-'.$scenario;
    if($kind==='standalone') {
        [$task]=$resumeMakeStandalone($a,$label);
        $origin=$resumeWhole->prepare($a,39,$task,4,$resumeProfile,$resumeKey($label.'prepare'));
    } else {
        $project=$ps->create($a,7,array_replace($pi,['name'=>'Recovery race '.$label]),$resumeKey($label.'project'));
        $data=array_replace($taskInput,['project_id'=>$project,'name'=>'Recovery race timer','checklist'=>[]]);
        $task=$ts->create($a,7,$data,$resumeKey($label.'task'));
        $ts->activity($a,7,$task,1,'start',$resumeKey($label.'start'));
        $db->query('UPDATE '.$p.'company_task_timers SET started_at=? WHERE issuer_id=? AND task_id=?',[date('Y-m-d H:i:s',time()-1800),$a,$task]);
        $ts->activity($a,7,$task,2,'stop',$resumeKey($label.'stop'));
        $timer=(int)$db->query('SELECT id FROM '.$p.'company_task_timers WHERE issuer_id=? AND task_id=?',[$a,$task])->row()->id;
        if($kind==='selected') {
            $data=array_replace(array_diff_key($resumeProfile,['client_id'=>true]),['selection_mode'=>'selected_timers','timer_ids'=>[$timer],'line_mode'=>'single_line']);
            $origin=$resumeTimes->prepare($a,39,$project,1,$data,$resumeKey($label.'prepare'));
        } else {
            $data=array_replace($resumeProfile,['source_kind'=>'projects','timers'=>[['id'=>$timer,'task_revision'=>3,'project_revision'=>1]],'line_mode'=>'single_line','rate_source'=>'explicit']);
            $origin=$resumeBatch->prepare($a,39,$data,$resumeKey($label.'prepare'));
        }
    }
    $beforeInvoices=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
    $beforeJournal=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_financial_events')->row()->n;
    $beforeCash=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();
    $workers=[]; $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');
    try {
        for($i=0;$i<2;$i++) {
            $pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            fwrite($pipes[0],json_encode(['kind'=>$kind,'issuer'=>$a,'task'=>$task,'origin'=>$origin],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];
        }
        $deadline=microtime(true)+15;
        do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);
        essentialCheck($waiting>=2,'two real '.$kind.' conversion writers observed waiting: '.$scenario);
        if($scenario==='revoked')$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=39',[$a]);
        if($scenario==='source_changed')$db->query('UPDATE '.$p.'company_standalone_tasks SET description=? WHERE issuer_id=? AND id=?',['Changed while blocked',$a,$task]);
        $db->trans_commit();$results=[];
        foreach($workers as [$proc,$pipes]) {
            $results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            essentialCheck(proc_close($proc)===0&&$error==='','real '.$kind.' conversion worker completes without diagnostics: '.$scenario);
        }
        if($scenario==='same_origin') {
            essentialCheck($results[0]===$results[1]&&str_starts_with($results[0],'invoice:')&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeInvoices+1,'simultaneous '.$kind.' retry commits exactly one canonical invoice');
            essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_financial_events')->row()->n===$beforeJournal+1,'simultaneous '.$kind.' retry commits one financial journal row');
        } else {
            $reason=$scenario==='source_changed'?'company_standalone_billing_stale':match($kind){'standalone'=>'company_standalone_billing_denied','selected'=>'company_task_billing_denied',default=>'company_time_batch_denied'};
            essentialCheck($results===[$reason,$reason]&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeInvoices,'both '.$kind.' writers observe current veto: '.$scenario);
            essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_financial_events')->row()->n===$beforeJournal,'veto commits no financial journal row: '.$kind.' '.$scenario);
        }
        essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$beforeCash,'concurrent '.$kind.' billing never invents cash');
    } finally {
        if($db->pdo->inTransaction())$db->trans_rollback();
        foreach($workers as [$proc,$pipes])if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}
    }
}
echo 'Rebuilt recovery439 InnoDB standalone/selected/batch idempotency/revocation/source races: '.$GLOBALS['essentialChecks'].' cumulative checks passed across '.count($cases).' scenarios.'.PHP_EOL;
