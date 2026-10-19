<?php
/** Two real MySQL writers; no SQLite fallback and no external provider. */
require dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyClients.php';
if(!class_exists('CI_Migration')){#[AllowDynamicProperties] class CI_Migration {}}
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function raceConnection(string $prefix): EssentialTestDb
{
    return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);
}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p)){exit(2);}
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=raceConnection($p);
    try{
        echo (new CompanyClients($db,$p))->create($input['issuer'],7,['company'=>'Synthetic concurrent customer'],$input['key']);
    }catch(DomainException $error){if($error->getMessage()==='company_client_access_denied'){echo 'denied';}else{throw $error;}}
    exit(0);
}
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$p=$f->prefix;$db=$f->db;
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$db->query("DROP TABLE {$p}clients");
$db->query("CREATE TABLE {$p}clients(userid int NOT NULL AUTO_INCREMENT PRIMARY KEY,company varchar(191),vat varchar(50),datecreated datetime NOT NULL,addedfrom int NOT NULL DEFAULT 0,active int NOT NULL DEFAULT 1) ENGINE=InnoDB");
require_once APPPATH.'migrations/406_version_406.php';$migration=new Migration_Version_406();$migration->db=$db;$migration->up();$migration->up();
$issuer=(new IssuerResourceAccess($db,$p))->primaryId();
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
$key=str_repeat('a',64);$out=race(['issuer'=>$issuer,'key'=>$key]);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent receipt returns same customer');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}clients")->row()->n===1,'only one customer after concurrent retry');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_client_created'")->row()->n===1,'single create audit after concurrent retry');
essentialCheck(race(['issuer'=>$issuer,'key'=>str_repeat('b',64)],fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'revocation while customer writers wait is honored');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}clients")->row()->n===1,'no customer after revocation race');
echo 'Customer directory concurrency: '.$GLOBALS['essentialChecks'].' checks passed, real MySQL row lock waits.'.PHP_EOL;
