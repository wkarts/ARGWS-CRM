<?php
/** Recreated439 copy race: two real connections, observed row lock, revocation before release. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyInvoiceCopies.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function resumeCopyRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$data=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=resumeCopyRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{$r=(new CompanyInvoiceCopies($db,$p))->create($data['issuer'],12,$data['source'],$data['review'],$data['key']);echo $r['invoice_id'];}catch(DomainException $e){if($e->getMessage()!=='company_invoice_copy_access_denied')throw$e;echo'denied';}exit(0);
}
require __DIR__.'/finance_resume_439_copies.php';
$observer=resumeCopyRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$db->query('INSERT INTO '.$p.'issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)',[$b]);foreach([['invoices','view'],['invoices','create'],['customers','view']] as [$feature,$cap])$db->query('INSERT INTO '.$p.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,?,?)',[$b,$feature,$cap]);
foreach(['same','revoked'] as $case){
 $db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12',[$b]);$source=$s->create($b,7,array_replace($input,['client_id'=>$cb]),$keyFor('race-source-'.$case));$v=$copies->preview($b,12,$source);$review=['source_revision'=>$v['revision'],'date'=>'2026-10-20','due_date'=>'2026-11-20','note'=>'Concurrent copy','confirm_amounts'=>'1'];$key=$keyFor('race-'.$case);$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
 $db->trans_begin();$db->query('SELECT id FROM '.$p.'issuers_lock WHERE id=1 FOR UPDATE');$workers=[];
 try{
  for($n=0;$n<2;$n++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('copy worker unavailable');fwrite($pipes[0],json_encode(['issuer'=>$b,'source'=>$source,'review'=>$review,'key'=>$key],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $until=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$until);essentialCheck($waiting>=2,'both copy workers observed waiting on actual InnoDB installation lock');
  if($case==='revoked')$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12',[$b]);$db->trans_commit();$results=[];
  foreach($workers as [$proc,$pipes]){$results[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','copy worker exits cleanly');}
  if($case==='same')essentialCheck($results[0]===$results[1]&&ctype_digit($results[0]),'two identical copy requests converge to one canonical invoice');else essentialCheck($results===['denied','denied'],'revoked staff cannot issue either waiting copy');
  essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$before+($case==='same'?1:0),'copy races leave exactly one invoice or zero after revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Recreated439 copies/InnoDB duplicate/revocation races: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
