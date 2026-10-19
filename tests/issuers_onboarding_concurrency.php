<?php
/** Two real MySQL writers; no SQLite fallback and no external provider. */
require __DIR__.'/issuers_core_support.php';
require_once APPPATH.'services/CompanyOnboarding.php';
require_once APPPATH.'services/issuers/IssuerMasterResolution.php';
if(!class_exists('CI_Migration')){#[AllowDynamicProperties] class CI_Migration {}}
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function raceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
$catalog=['settings'=>['view','edit']];
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)){exit(2);}
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=raceConnection($p);
    try{
        if($input['operation']==='master'){
            (new IssuerMasterResolution($db,$p))->complete(7,$input['code'],'synthetic-confirmation',static fn($password,$hash)=>password_verify($password,$hash));
            echo 'master';
        }else{echo (new CompanyOnboarding($db,$p,$catalog))->complete(7,$input['key'],$input['revision']);}
    }catch(DomainException $error){if(in_array($error->getMessage(),['issuer_access_denied','issuer_master_candidate_denied'],true)){echo 'denied';}else{throw $error;}}
    exit(0);
}
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$p=$f->prefix;$db=$f->db;
$db->query("ALTER TABLE {$p}staff ADD password varchar(255) DEFAULT NULL");
$db->query("UPDATE {$p}staff SET password=? WHERE staffid=7",[password_hash('synthetic-confirmation',PASSWORD_DEFAULT)]);
IssuerCore::reconcile($db,$p);
require_once APPPATH.'migrations/405_version_405.php';$migration=new Migration_Version_405();$migration->db=$db;$migration->up();$migration->up();
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
            $waiters=(int)$observer->query('SELECT COUNT(DISTINCT w.REQUESTING_THREAD_ID) n FROM performance_schema.data_lock_waits w JOIN performance_schema.threads b ON b.THREAD_ID=w.BLOCKING_THREAD_ID WHERE b.PROCESSLIST_ID=?',[$blocker])->row()->n;
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
$master=new IssuerMasterResolution($db,$p);$code=$master->authorizeDesignation(7,'Authorized synthetic concurrent designation validation');
essentialCheck(race(['operation'=>'master','code'=>$code])===['master','master'],'same authorization completion serializes idempotently');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='installation_master_designated'")->row()->n===1,'one durable designation audit');
$s=new CompanyOnboarding($db,$p,$catalog);
function readyDraft(string $key,string $tax): array
{
    global $s;
    $d=$s->begin(7,$key);
    foreach([1=>['legal_name'=>'Synthetic concurrent company','tax_id'=>$tax],2=>['dateformat'=>'Y-m-d|%Y-%m-%d','time_format'=>'24'],3=>['staff'=>[]],4=>['client_visibility'=>'private']] as $step=>$input){$d=$s->advance(7,$d['draft_key'],$d['revision'],$step,$input);}
    return $d;
}
$d=readyDraft(str_repeat('a',64),'52998224725');$output=race(['operation'=>'company','key'=>$d['draft_key'],'revision'=>$d['revision']]);
essentialCheck($output[0]===$output[1]&&ctype_digit($output[0]),'concurrent completion returns same company ID');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_registry WHERE tax_id='52998224725'")->row()->n===1,'one company under concurrent completion');
$d=readyDraft(str_repeat('b',64),'12345678000195');
essentialCheck(race(['operation'=>'company','key'=>$d['draft_key'],'revision'=>$d['revision']],fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'revocation while writers wait is revalidated under lock');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_registry WHERE tax_id='12345678000195'")->row()->n===0,'revoked master creates no company');
echo 'Master/onboarding concurrency: '.$GLOBALS['essentialChecks'].' checks passed, two MySQL connections and observed row-lock waits.'.PHP_EOL;
