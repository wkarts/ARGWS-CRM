<?php
/** Actual independent connections: duplicate notification and revocation while waiting on lock. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoiceDelivery.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function deliveryConnection(string $prefix):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);
    $input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=deliveryConnection($p);$GLOBALS['essentialPrefix']=$p;
    try{echo (new CompanyInvoiceDelivery($db,$p))->queue($input['issuer'],7,$input['invoice'],101,$input['revision'],$input['key'],'https://crm.example.invalid',fn($value)=>'synthetic:'.$value);}
    catch(DomainException $error){if($error->getMessage()!=='company_invoice_delivery_denied')throw $error;echo 'denied';}exit(0);
}
require __DIR__.'/invoice_delivery_worker.php';
$observer=deliveryConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function deliveryRace(array $input,?callable $beforeRelease=null):array
{
    global $db,$observer,$p,$blocker;$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
        essentialCheck($waiters>=2,'two notification writers observed waiting on real installation lock');if($beforeRelease)$beforeRelease();$db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','notification workers complete without diagnostics');$outputs[]=$out;}return $outputs;
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
$invoice=$s->create($a,7,$input,hash('sha256','notification-race-invoice'));$revision=$s->revision($a,7,$invoice);$key=hash('sha256','notification-race');
$raceInput=['issuer'=>$a,'invoice'=>$invoice,'revision'=>$revision,'key'=>$key];$out=deliveryRace($raceInput);
essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent notification retry returns one queue ID');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_invoice_deliveries WHERE invoice_id=?",[$invoice])->row()->n===1,'only one durable notification receipt after race');
$raceInput['key']=hash('sha256','notification-revoke-contact');
essentialCheck(deliveryRace($raceInput,fn()=>$db->query("DELETE FROM {$p}contact_permissions WHERE userid=101"))===['denied','denied'],'contact permission revocation while writers wait denies both enqueues');
$db->query("INSERT INTO {$p}contact_permissions(userid,permission_id) VALUES(101,1)");$raceInput['key']=hash('sha256','notification-revoke-staff');
essentialCheck(deliveryRace($raceInput,fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'staff revocation under lock denies both notification writers');
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_invoice_deliveries WHERE invoice_id=?",[$invoice])->row()->n===1,'revocation leaves the one earlier valid receipt intact');
echo 'Company invoice notification concurrency/revocation: '.$GLOBALS['essentialChecks'].' cumulative checks passed. Actual database locks; no external SMTP.'.PHP_EOL;
