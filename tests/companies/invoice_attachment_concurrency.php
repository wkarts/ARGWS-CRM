<?php
/** Real PDO connections and observed waits; private uploads/downloads do not outlive revocation. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoiceAttachments.php';
require_once APPPATH.'services/CompanyPortalInvoices.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function fileConnection(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=fileConnection($p);$GLOBALS['essentialPrefix']=$p;$files=new CompanyInvoiceAttachments($db,$p);
    try{
        if($input['operation']==='upload')echo $files->upload($input['issuer'],7,$input['invoice'],$input['key'],'concurrent.png',base64_decode($input['content'],true));
        else{$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$row=$files->portalDownload(new CompanyPortalInvoices($db,$p,$portal),$input['session'],$input['issuer'],$input['invoice'],$input['key']);echo $row['content_hash'];}
    }catch(DomainException $error){if(!in_array($error->getMessage(),['company_portal_invoice_unavailable','company_invoice_attachment_denied'],true))throw $error;echo 'denied';}exit(0);
}
require __DIR__.'/invoice_attachments.php';
$observer=fileConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
function fileRace(array $input,?callable $beforeRelease=null):array
{
    global $db,$observer,$p,$blocker;$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
    try{
        for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode($input,JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'both attachment connections observed waiting on owner lock');if($beforeRelease)$beforeRelease();$db->trans_commit();$outputs=[];
        foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err==='','file worker completed without emitting content or diagnostics');$outputs[]=$out;}return $outputs;
    }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
$key=hash('sha256','file-upload-race');$request=['operation'=>'upload','issuer'=>$a,'invoice'=>$ia,'key'=>$key,'content'=>base64_encode($picture)];$out=fileRace($request);essentialCheck($out===[$key,$key],'concurrent upload retry returns one file key');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_invoice_attachments WHERE file_key=?",[$key])->row()->n===1,'one private blob after two writers');
$request['key']=hash('sha256','file-upload-revoke');essentialCheck(fileRace($request,fn()=>$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7"))===['denied','denied'],'staff revocation while upload waits denies both writes');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
$publicKey=hash('sha256','file-portal-race');$files->upload($a,7,$ia,$publicKey,'authorized.pdf',$pdf,true);$request=['operation'=>'portal','issuer'=>$a,'invoice'=>$ia,'key'=>$publicKey,'session'=>$sessionA];$hash=hash('sha256',$pdf);essentialCheck(fileRace($request)===[$hash,$hash],'both currently authorized portal downloads succeed under real locks');
essentialCheck(fileRace($request,fn()=>$db->query("DELETE FROM {$p}contact_permissions WHERE userid=? AND permission_id=1",[$contactA]))===['denied','denied'],'native contact revocation while waiting prevents both file deliveries');
echo 'Invoice file upload/portal revocation concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks passed with actual row locks.'.PHP_EOL;
