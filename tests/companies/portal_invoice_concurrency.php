<?php
/** Real readers blocked inside native DTO construction; concurrent financial permission revocation denies delivery. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyPortalInvoices.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function portalInvoiceConnection(string $prefix):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$prefix);}
if(($argv[1]??'')==='worker'){
    $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=portalInvoiceConnection($p);$GLOBALS['essentialPrefix']=$p;
    $documents=new CompanyPortalInvoices($db,$p,new CompanyPortal($db,$p,str_repeat('synthetic-',4)));
    try{$dto=$documents->document($input['session'],$input['issuer'],$input['invoice']);$documents->confirmDownload($input['session'],$input['issuer'],$input['invoice'],str_repeat('a',64));echo 'delivered';}
    catch(DomainException $error){if(!in_array($error->getMessage(),['company_portal_invoice_unavailable','issuer_access_denied'],true))throw $error;echo 'denied';}exit(0);
}
require __DIR__.'/portal_invoices.php';
$sessionA=$portal->login($a,$person['email'],'changed-password','password_verify');$observer=portalInvoiceConnection($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_portal_invoice_pdf_downloaded'")->row()->n;
$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
try{
    for($i=0;$i<2;$i++){$pipes=[];$process=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('worker unavailable');fwrite($pipes[0],json_encode(['session'=>$sessionA,'issuer'=>$a,'invoice'=>$id],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$process,$pipes];}
    $deadline=microtime(true)+15;$waiters=0;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);
    essentialCheck($waiters>=2,'two portal PDF readers observed blocked in native document row lock');
    $db->query("DELETE FROM {$p}contact_permissions WHERE userid=? AND permission_id=1",[$contactA]);$db->trans_commit();
    foreach($workers as [$process,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($process)===0&&$err===''&&$out==='denied','waiting PDF reader rechecks revoked native permission before returning data');}
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_portal_invoice_pdf_downloaded'")->row()->n===$before,'revoked readers release no PDF bytes or successful delivery receipt');
}finally{if($db->pdo->inTransaction())$db->trans_rollback();}
echo 'Private portal PDF revocation race: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks.'.PHP_EOL;
