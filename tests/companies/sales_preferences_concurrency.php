<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
/** Real native sessions prove settings/issuance snapshot coherence and revocation under the shared lock. */
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanySales.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:'))exit(2);
function preferenceRaceDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=preferenceRaceDb($p);$GLOBALS['essentialPrefix']=$p;
 try{if($input['operation']==='settings'){(new CompanySettingsStorage($db,$p))->updateBatch($input['issuer'],7,CompanySettingsCatalog::prepare('sales_documents',['estimate_prefix'=>'NEW-','predefined_terms_estimate'=>'new terms']));echo 'updated';}else echo (new CompanySales($db,$p))->create($input['issuer'],7,'estimates',$input['payload'],$input['key']);}
 catch(DomainException $error){if(!in_array($error->getMessage(),['company_sales_denied','company_settings_access_denied'],true))throw $error;echo 'denied';}exit;
}
require __DIR__.'/sales.php';$raceStart=$GLOBALS['essentialChecks'];$observer=preferenceRaceDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
foreach(['coherence','same-request','revocation'] as $mode){
 $settings->updateBatch($b,7,['estimate_prefix'=>'OLD-','predefined_terms_estimate'=>'old terms']);$payload=array_replace($salesInput,['client_id'=>$cb]);$key=hash('sha256','prefs-native-'.$mode);$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}company_sales_documents")->row()->n;$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['operation'=>$mode!=='same-request'&&$i===0?'settings':'issue','issuer'=>$b,'payload'=>$payload,'key'=>$key],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiters=essentialLockWaiters($observer,$blocker);if($waiters>=2)break;usleep(20000);}while(microtime(true)<$deadline);essentialCheck($waiters>=2,'two preference/issuance sessions wait on observed native lock: '.$mode);if($mode==='revocation')$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$error==='','native preference worker exits without diagnostics: '.$mode);}
  if($mode==='revocation'){essentialCheck($out===['denied','denied']&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_sales_documents")->row()->n===$before,'revocation denies settings and document effects atomically');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");essentialCheck($settings->get($b,'estimate_prefix')==='OLD-'&&$settings->get($b,'predefined_terms_estimate')==='old terms','revoked batch leaves both settings unchanged');}
  else{$id=(int)$out[1];$dto=$sales->document($b,7,$id);$prefs=$dto['context']['sales_preferences'];essentialCheck(in_array([$prefs['prefix'],$prefs['terms']],[['OLD-','old terms'],['NEW-','new terms']],true),'issued context cannot mix fields across concurrent settings batch');essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}company_sales_documents")->row()->n===$before+1,'one issued document remains after native contention');if($mode==='same-request')essentialCheck($out[0]===$out[1]&&ctype_digit($out[0]),'concurrent identical requests preserve one immutable preference snapshot');else essentialCheck($out[0]==='updated','coherent preferences update actually commits');}
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();foreach($workers as [$proc,$pipes])if(is_resource($proc)){if(proc_get_status($proc)['running'])proc_terminate($proc);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($proc);}}
}
echo 'Sales preference native coherence: '.($GLOBALS['essentialChecks']-$raceStart).' added / '.$GLOBALS['essentialChecks'].' cumulative actual DB checks. No external transport.'.PHP_EOL;
