<?php
require __DIR__.'/banco_brasil_api16_components_mysql.php';$balance=new CompanyInvoiceBalance($db,$p);
require_once APPPATH.'services/CompanyContacts.php';
require_once APPPATH.'services/CompanyPortalInvoices.php';
require_once APPPATH.'services/CompanyContactInvoiceAccess.php';
$native=file_get_contents(dirname(__DIR__).'/install/database.sql');
foreach(['contacts','contact_permissions'] as $name){if($db->table_exists($p.$name))continue;
    preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('native contact fixture missing');
    $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
    if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);
}
if($db->driver()==='mysql'){
    require_once APPPATH.'migrations/407_version_407.php';$m=new Migration_Version_407();$m->db=$db;$m->up();$m->up();
}else{
    $db->query("CREATE TABLE {$p}company_portal_tokens(token_hash varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,contact_id int NOT NULL,client_id int NOT NULL,identity_hash varchar(64) NOT NULL,expires_at datetime NOT NULL,created_at datetime NOT NULL)");
}
unset($db->data_cache['table_names']);
$contacts=new CompanyContacts($db,$p);$access=new CompanyContactInvoiceAccess($db,$p);$portal=new CompanyPortal($db,$p,str_repeat('synthetic-',4));$portalInvoices=new CompanyPortalInvoices($db,$p,$portal);$portalPerson=['firstname'=>'Synthetic','lastname'=>'Components','email'=>'components@example.invalid'];$portalContact=$contacts->save($a,7,$ca,$portalPerson);$portalPassword=password_hash('synthetic-components-password',PASSWORD_DEFAULT);$db->query('UPDATE '.$p.'contacts SET password=? WHERE id=?',[$portalPassword,$portalContact]);$portalSession=$portal->login($a,$portalPerson['email'],'synthetic-components-password','password_verify');$portalPermission=$access->page($a,7,$ca)[$portalContact];$access->set($a,7,$ca,$portalContact,$portalPermission['revision'],'1');
$portalDto=$portalInvoices->document($portalSession,$a,$api16['invoice']);essentialCheck($portalDto->data()['position']['received']==='92.53'&&$portalDto->data()['position']['noncash_reductions']==='13.00','native contact permission1 reads API16 cash/noncash without impersonating staff');$renderer=new Issuer_invoice_pdf($portalDto);$html=$renderer->html();essentialCheck(str_contains($html,'Principal recebido')&&str_contains($html,'Desconto na liquidação')&&str_contains($html,'BRL 92,53'),'real PDF renderer distinguishes received principal from noncash settlement reductions');$bytes=$renderer->prepare()->Output('','S');essentialCheck(str_starts_with($bytes,'%PDF-'),'authorized component position renders actual TCPDF bytes');$portalInvoices->confirmDownload($portalSession,$a,$api16['invoice'],hash('sha256',$bytes));
$otherConnection=clone $db;$foreignAuthority=new CompanyPortalInvoices($otherConnection,$p,$portal);essentialThrows(fn()=>IssuerTransaction::run($db,$p,array_merge((new CompanyInvoiceBalance($db,$p))->tables(),$foreignAuthority->balanceTables()),fn()=>$balance->portalInOwned($foreignAuthority,$portalSession,$a,$api16['invoice'])),'company_portal_invoice_unavailable','final portal authority from another connection cannot authorize coincident numeric IDs');
$portalPermission=$access->page($a,7,$ca)[$portalContact];$access->set($a,7,$ca,$portalContact,$portalPermission['revision'],'0');essentialThrows(fn()=>$portalInvoices->document($portalSession,$a,$api16['invoice']),'company_portal_invoice_unavailable','revoking native invoice permission blocks component PDF DTO');essentialThrows(fn()=>$portalInvoices->confirmDownload($portalSession,$a,$api16['invoice'],hash('sha256',$bytes)),'company_portal_invoice_unavailable','revocation after PDF render blocks audited delivery');
echo 'API16 component native portal/PDF: '.$GLOBALS['essentialChecks'].' cumulative actual DB checks; no provider operation or bank homologation.'.PHP_EOL;
