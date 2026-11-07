<?php
require __DIR__.'/asaas_pix_reservations.php';
$start=$GLOBALS['essentialChecks'];$db->query('UPDATE '.$p.'modules SET installed_version=? WHERE module_name=?',['1.9.4','asaas']);
$autoOrigin=$failedReserve();$raw=$db->query('SELECT result_json FROM '.$p.'asaas_company_proofs WHERE origin_id=? AND operation=?',[$autoOrigin,'pix_reserve'])->row()->result_json;$autoSource=json_decode($raw,true);
$canonical=['id'=>'auth_discovered','customerId'=>'cus_b','contractId'=>$autoSource['contract_id'],'frequency'=>'MONTHLY','startDate'=>$autoSource['start_date'],'status'=>'CREATED','immediateQrCode'=>['conciliationIdentifier'=>'discovered_conciliation','expirationDate'=>'2030-01-01T12:00:00-03:00'],'payload'=>'000201synthetic-never-a-real-code','encodedImage'=>''];
$listing=['data'=>[$canonical],'hasMore'=>false,'totalCount'=>1];$requests=[];$revoke=false;
$autoTransport=function($env,$method,$path,$payload,$secret)use(&$requests,&$listing,&$canonical,&$revoke,$db,$p,$mock){$requests[]=[$method,$path];if(str_starts_with($path,'pix/automatic/authorizations?')){if($revoke)$db->query('UPDATE '.$p.'staff SET active=0 WHERE staffid=7');return $listing;}if($path==='pix/automatic/authorizations/auth_discovered')return $canonical;return $mock($env,$method,$path,$payload,$secret);};
$automatic=fn()=>$asaas->recoverPixAuthorization($b,7,$autoOrigin,'',$dec,$autoTransport);
$listing=['data'=>[],'hasMore'=>false,'totalCount'=>0];essentialThrows($automatic,'asaas_pix_recovery_not_found','missing contract leaves outcome unknown, not resend');
$listing=['data'=>[$canonical,array_replace($canonical,['id'=>'auth_duplicate'])],'hasMore'=>false,'totalCount'=>2];essentialThrows($automatic,'asaas_pix_recovery_conflict','two matching contracts require review');
$listing=['data'=>[$canonical],'hasMore'=>false,'totalCount'=>1];$revoke=true;essentialThrows($automatic,'asaas_company_access_denied','revocation during discovery blocks detail lookup');$db->query('UPDATE '.$p.'staff SET active=1 WHERE staffid=7');$revoke=false;
$requests=[];$out=$automatic();essentialCheck($out['remote_id']==='auth_discovered','automatic recovery confirms discovered ID with canonical detail');essentialCheck(in_array(['GET','pix/automatic/authorizations?limit=50&offset=0'],$requests,true)&&in_array(['GET','pix/automatic/authorizations/auth_discovered'],$requests,true),'discovery is followed by exact-ID query');
$requests=[];$again=$automatic();essentialCheck($again===$out&&!array_filter($requests,fn($r)=>str_contains($r[1],'authorizations?')),'known immutable identity bypasses list on subsequent recovery');
essentialCheck(!array_filter($requests,fn($r)=>$r[0]!=='GET'),'no mutation transport during recovery');
essentialCheck($db->query('SELECT state,remote_id FROM '.$p.'asaas_payment_origins WHERE id=?',[$autoOrigin])->row_array()===['state'=>'reserved','remote_id'=>null],'recovery does not release exclusion or claim financial remote ID');
echo 'Automatic Pix recovery: '.($GLOBALS['essentialChecks']-$start).' additional MySQL checks; provider simulated.'.PHP_EOL;
