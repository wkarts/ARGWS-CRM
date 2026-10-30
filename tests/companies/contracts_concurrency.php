<?php
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyContracts.php';require_once APPPATH.'services/CompanyTickets.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function contractRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=contractRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{if($input['domain']==='contract')(new CompanyContracts($db,$p))->transition($input['issuer'],12,$input['id'],$input['revision'],'activate',$input['key']);else (new CompanyTickets($db,$p))->reply($input['issuer'],12,$input['id'],$input['revision'],'Concurrent synthetic reply',$input['key']);echo 'saved';}
 catch(DomainException $e){if(!in_array($e->getMessage(),['company_operation_access_denied','company_operation_stale'],true))throw $e;echo $e->getMessage();}exit(0);
}
require __DIR__.'/contracts_operations.php';$observer=contractRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['contract','ticket'] as $domain)foreach(['same-key','stale','revoked'] as $case){
 $db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$b]);
 if($domain==='contract'){$id=$contracts->create($b,7,array_replace($contractInput,['client_id'=>$cb]),hash('sha256','contract-race-draft-'.$case));$revision=1;$before=(int)$db->query("SELECT next_number FROM {$p}company_contract_counters WHERE issuer_id=?",[$b])->row()->next_number;}
 else{$id=$ticketB;$row=$tickets->get($b,12,$id);$revision=(int)$row['revision'];$before=count($row['replies']);}
 $db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['domain'=>$domain,'issuer'=>$b,'id'=>$id,'revision'=>$revision,'key'=>hash('sha256','race-'.$domain.'-'.$case.($case==='same-key'?'same':$i))],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiting>=2,'both contract/ticket writers observed waiting on actual InnoDB lock');
  if($case==='revoked')$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$b]);$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','worker completes without data-bearing diagnostics');}
  sort($out);$expected=$case==='same-key'?['saved','saved']:($case==='stale'?['company_operation_stale','saved']:['company_operation_access_denied','company_operation_access_denied']);essentialCheck($out===$expected,'receipt converges stale loses and revoked waiting operation fails closed');
  $after=$domain==='contract'?(int)$db->query("SELECT next_number FROM {$p}company_contract_counters WHERE issuer_id=?",[$b])->row()->next_number:count($tickets->get($b,7,$id)['replies']);essentialCheck($after===$before+($case==='revoked'?0:1),'exactly one counter/reply advancement or zero after revocation');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Contracts/tickets actual MySQL locks/replay/revision/revocation: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
