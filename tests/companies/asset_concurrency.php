<?php
require_once dirname(__DIR__).'/issuers_core_support.php';require_once APPPATH.'services/CompanyAssets.php';
if(!str_starts_with(getenv('ISSUERS_TEST_DSN')?:'','mysql:')){fwrite(STDERR,"An isolated MySQL DSN is required.\n");exit(2);}
function assetDb(string $p):EssentialTestDb{return new EssentialTestDb(new PDO(getenv('ISSUERS_TEST_DSN'),getenv('ISSUERS_TEST_USER'),getenv('ISSUERS_TEST_PASSWORD')),$p);}
if(($argv[1]??'')==='worker'){
 $p=$argv[2]??'';if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$p))exit(2);$input=json_decode(stream_get_contents(STDIN),true,16,JSON_THROW_ON_ERROR);$db=assetDb($p);$GLOBALS['essentialPrefix']=$p;
 try{(new CompanyAssets($db,$p))->save($input['issuer'],12,'signature',base64_decode($input['bytes']),$input['revision']);echo 'saved';}
 catch(DomainException $e){if(!in_array($e->getMessage(),['company_asset_access_denied','company_asset_stale'],true))throw $e;echo $e->getMessage();}exit(0);
}
require dirname(__DIR__).'/settings/company_assets.php';$observer=assetDb($p);$blocker=(int)$db->query('SELECT CONNECTION_ID() id')->row()->id;
$newImage=imagecreatetruecolor(4,4);imagefill($newImage,0,0,imagecolorallocate($newImage,220,80,50));ob_start();imagepng($newImage);$changedBytes=ob_get_clean();unset($newImage);
foreach([false,true] as $revoke){
 $revision=$service->view($a,12)['revision'];$db->trans_begin();$db->query("SELECT id FROM {$p}issuers_lock WHERE id=1 FOR UPDATE");$workers=[];
 try{
  for($i=0;$i<2;$i++){$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'worker',$p],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fwrite($pipes[0],json_encode(['issuer'=>$a,'revision'=>$revision,'bytes'=>base64_encode($changedBytes)],JSON_THROW_ON_ERROR));fclose($pipes[0]);$workers[]=[$proc,$pipes];}
  $deadline=microtime(true)+15;do{$waiting=essentialLockWaiters($observer,$blocker);if($waiting>=2)break;usleep(20000);}while(microtime(true)<$deadline);
  essentialCheck($waiting>=2,'two raster uploads observed waiting on real row lock');
  if($revoke)$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$a]);$db->trans_commit();$out=[];
  foreach($workers as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);essentialCheck(proc_close($proc)===0&&$err==='','asset worker completes without diagnostics or image leakage');}
  sort($out);essentialCheck($out===($revoke?['company_asset_access_denied','company_asset_access_denied']:['company_asset_stale','saved']),'one writer wins; revocation during lock wait denies both uploads');
 }finally{if($db->pdo->inTransaction())$db->trans_rollback();}
}
echo 'Company raster assets concurrency: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
