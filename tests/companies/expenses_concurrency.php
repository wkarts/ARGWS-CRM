<?php
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyExpenses.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function expenseConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=expenseConnection($p);$GLOBALS['essentialPrefix']=$p;$svc=new CompanyExpenses($db,$p);
    try{if($input['op']==='create')echo 'saved:'.$svc->create($input['issuer'],12,$input['input'],$input['key']);else{$svc->change($input['issuer'],12,$input['id'],$input['revision'],$input['key'],$input['input']);echo 'updated';}}
    catch(DomainException $e){if(!in_array($e->getMessage(),['company_expense_denied','company_expense_stale'],true))throw $e;echo $e->getMessage();}exit(0);
}
require __DIR__.'/expenses_files.php';$observer=expenseConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$b]);foreach(['edit','delete'] as $cap)$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$b,'expenses',$cap]);
$workerInput=array_replace($input,['category_id'=>$cb,'client_id'=>0,'project_id'=>0]);
foreach(['retry','edit','revocation'] as $scenario){
    $baseline=count($svc->page($b,7)['items']);$parent=$svc->get($b,12,$ib);$db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
    try{
        for($i=0;$i<2;$i++){$payload=['issuer'=>$b,'input'=>$workerInput,'op'=>$scenario==='edit'?'update':'create','key'=>$key('race-'.$scenario.($scenario==='retry'?'':$i))];if($scenario==='edit'){$payload+=['id'=>$ib,'revision'=>$parent['revision_token']];$payload['input']['amount']=$i?'12.00':'11.00';}
            $pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode($payload,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $deadline=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'both expense writers observed waiting on actual InnoDB lock');
        if($scenario==='revocation')$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12',[$b]);$db->trans_commit();$results=[];
        foreach($workers as [$process,$pipes]){$results[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','concurrent writer finishes without diagnostics');}
        sort($results);if($scenario==='retry')essentialCheck($results[0]===$results[1]&&str_starts_with($results[0],'saved:')&&count($svc->page($b,7)['items'])===$baseline+1,'concurrent create retries share durable single record and number');
        elseif($scenario==='edit')essentialCheck($results===['company_expense_stale','updated']&&(int)$svc->get($b,7,$ib)['revision']===(int)$parent['revision']+1,'one stale editor loses without overwriting winning monetary revision');
        else essentialCheck($results===['company_expense_denied','company_expense_denied']&&count($svc->page($b,7)['items'])===$baseline,'membership revoked during lock wait prevents both committed expenses');
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Company expense concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks passed; actual InnoDB. No provider calls.'.PHP_EOL;
