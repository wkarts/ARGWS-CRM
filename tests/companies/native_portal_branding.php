<?php
require __DIR__.'/native_portal_scope.php';
require APPPATH.'services/CompanyAssets.php';
$start=$GLOBALS['essentialChecks'];$image=imagecreatetruecolor(2,2);imagefill($image,0,0,imagecolorallocate($image,10,30,90));ob_start();imagepng($image);$bytes=ob_get_clean();unset($image);$inline=CompanyAssets::normalize($bytes);
$assets=new CompanyAssets($db,$p);foreach(['logo','signature']as$kind){$current=$assets->view($a,7);$assets->save($a,7,$kind,$bytes,$current['revision']);}
$ci->router->class='authentication';$ci->router->method='login';$ci->input->post=[];$ci->input->get=['issuer_id'=>(string)$a];$ci->input->verb='GET';PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);
$out=$assets->brandingForPortal($ci,$a);essentialCheck(array_keys($out)===['logo','logo_dark']&&$out['logo']===$inline&&!isset($out['signature']),'sealed public branding projects only validated logo assets, never signature');
essentialThrows(fn()=>$assets->brandingForPortal($ci,$b),'company_asset_access_denied','issuer B cannot be requested through issuer A seal');essentialThrows(fn()=>$assets->brandingForPortal(clone $ci,$a),'company_asset_access_denied','copied request object cannot authorize logo access');
$db->query('UPDATE '.$p.'issuers_registry SET active=0 WHERE id=?',[$a]);essentialThrows(fn()=>$assets->brandingForPortal($ci,$a),'company_asset_access_denied','deactivated issuer invalidates public logo read');$db->query('UPDATE '.$p.'issuers_registry SET active=1 WHERE id=?',[$a]);
$root=sys_get_temp_dir().'/native-branding-'.bin2hex(random_bytes(6));mkdir($root,0700);define('COMPANY_FILES_FOLDER',$root.'/');file_put_contents($root.'/historical.png',$bytes);
try{
 $db->query('INSERT INTO '.$p.'options(name,value) VALUES(?,?)',['company_logo','historical.png']);$ci->input->get=['issuer_id'=>(string)$b];PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);essentialCheck($assets->brandingForPortal($ci,$b)['logo']==='','named empty branding never inherits principal historical filename');
 NativePortalIdentity::clear($ci->session);$ci->input->get=[];PortalRequestScope::prepare($ci);PortalRequestScope::resolve($ci);$principal=PortalRequestScope::issuerId();essentialCheck($assets->brandingForPortal($ci,$principal)['logo']===$inline,'persisted principal retains canonical historical raster logo');
 $db->query('UPDATE '.$p.'options SET value=? WHERE name=?',['../historical.png','company_logo']);essentialThrows(fn()=>$assets->brandingForPortal($ci,$principal),'company_asset_corrupt','historical path cannot escape native company upload root');
}finally{unlink($root.'/historical.png');rmdir($root);}
echo 'Native portal branding: '.($GLOBALS['essentialChecks']-$start).' actual image/SQLite checks passed.'.PHP_EOL;
