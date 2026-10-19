<?php
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyAssets.php';
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$primary=(new IssuerResourceAccess($db,$p))->primaryId();$registry=new IssuerRegistry($db,$p);
$a=$registry->saveIssuer(7,['legal_name'=>'Asset A','tax_id'=>'12345678000195']);$b=$registry->saveIssuer(7,['legal_name'=>'Asset B','tax_id'=>'52998224725']);
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(12,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)",[$a]);
foreach(['view','edit'] as $cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'settings',?)",[$a,$cap]);
$service=new CompanyAssets($db,$p);$image=imagecreatetruecolor(3,2);ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);
$original=$service->view($a,12);essentialCheck($original['assets']===[],'new company never inherits principal or another logo');
$before=$db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=?",[$a])->row()->settings_json;
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$service->save($a,12,'logo',$bytes,$original['revision']),'issuer_storage_failed','audit failure rolls back image');$db->failOn=null;
essentialCheck($db->query("SELECT settings_json FROM {$p}issuers_registry WHERE id=?",[$a])->row()->settings_json===$before,'no orphan blob after failed save');
foreach([[$a,12],[$b,7],[$primary,7]] as [$owner,$actor]){
 $revision=$service->view($owner,$actor)['revision'];$service->save($owner,$actor,'logo',$bytes.'<script>metadata</script>',$revision);
 $current=$service->view($owner,$actor);$clean=base64_decode(explode(',',$current['assets']['logo'],2)[1]);
 essentialCheck(!str_contains($clean,'script')&&str_starts_with($clean,"\x89PNG"),'A/B/principal upload is decoded and reencoded without appended payload');
 essentialThrows(fn()=>$service->save($owner,$actor,'signature',$bytes,$revision),'company_asset_stale','stale upload cannot overwrite newer assets');
 $service->save($owner,$actor,'signature',$bytes,$current['revision']);
 essentialCheck(count($service->view($owner,$actor)['assets'])===2,'positive own-company logo and signature stored independently');
}
essentialThrows(fn()=>$service->view($b,12),'company_asset_access_denied','foreign read denied before asset hydration');
essentialThrows(fn()=>$service->save($b,12,'logo',$bytes,$service->view($b,7)['revision']),'company_asset_access_denied','foreign mutation denied');
essentialThrows(fn()=>$service->save($a,12,'../logo',$bytes,$service->view($a,12)['revision']),'company_asset_invalid_kind','kind cannot become filesystem path');
foreach(['<svg xmlns="http://www.w3.org/2000/svg"></svg>','GIF89a',str_repeat('x',524289)] as $bad)essentialThrows(fn()=>CompanyAssets::normalize($bad),'company_asset_invalid_image','non-raster or oversized content denied');
$huge=imagecreatetruecolor(1600,1600);ob_start();imagepng($huge);$oversized=ob_get_clean();imagedestroy($huge);
essentialThrows(fn()=>CompanyAssets::normalize($oversized),'company_asset_invalid_image','compressed image pixel limit enforced before decode');
$state=$service->view($a,12);$service->save($a,12,'logo',null,$state['revision']);
essentialCheck(!isset($service->view($a,12)['assets']['logo'])&&isset($service->view($b,7)['assets']['logo']),'removing A asset never removes B or principal');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$a]);
essentialThrows(fn()=>$service->view($a,12),'company_asset_access_denied','membership revocation takes effect on next preview/download');
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$a]);
$legacy=essentialLegacy($f);$service->save($a,12,'logo',$bytes,$service->view($a,12)['revision']);essentialCheck(essentialLegacy($f)===$legacy,'private image never changes global legacy options/uploads');
essentialCheck(str_starts_with($service->download($a,12,'logo',$service->view($a,12)['revision']),"\x89PNG"),'download returns authorized normalized raster bytes');
$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$service->download($a,12,'logo',$service->view($a,12)['revision']),'issuer_storage_failed','failed download audit releases no bytes');$db->failOn=null;
echo 'Company raster assets: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().').'.PHP_EOL;
