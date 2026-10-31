<?php
/** Real scoped dashboard aggregates; no HTTP transport or provider network. */
require __DIR__.'/native_portal_files_scope.php';
require_once APPPATH.'services/NativePortalHome.php';
$start=$GLOBALS['essentialChecks'];
if($db->driver()==='mysql')CompanyCurrencyUnitSchema::ensure($db,$p);else $db->query('CREATE TABLE '.$p.'company_currency_units(currency_id int PRIMARY KEY,iso_code varchar(3) NOT NULL,basis varchar(48) NOT NULL,recorded_at datetime NOT NULL,source_hash varchar(64) NOT NULL)');
$currencyName=$db->query('SELECT name FROM '.$p.'currencies WHERE id=1')->row()->name;$captureTime='2026-01-01 00:00:00';$captureBasis=CompanyCurrencyUnits::AT_ISSUE;$currencyHash=hash('sha256',json_encode([1,$currencyName,$captureBasis,$captureTime],JSON_THROW_ON_ERROR));$db->query('INSERT INTO '.$p.'company_currency_units(currency_id,iso_code,basis,recorded_at,source_hash) VALUES(1,?,?,?,?)',[$currencyName,$captureBasis,$captureTime,$currencyHash]);
$db->query('CREATE TABLE '.$p.'contact_permissions(userid INT NOT NULL,permission_id INT NOT NULL)');
foreach([901,902,903]as$contact)foreach([1,4,6]as$permission)$db->query('INSERT INTO '.$p.'contact_permissions(userid,permission_id) VALUES(?,?)',[$contact,$permission]);
foreach(['status INT','date TEXT','currency INT']as$column)$db->query('ALTER TABLE '.$p.'invoices ADD '.$column);
$db->query('CREATE TABLE '.$p.'projects(id INT PRIMARY KEY,clientid INT,status INT)');
$db->query('CREATE TABLE '.$p.'company_projects(id INT PRIMARY KEY,issuer_id INT,client_id INT,status TEXT)');
$db->query('CREATE TABLE '.$p.'company_portal_projects(issuer_id INT,project_id INT,visible INT)');
$db->query('CREATE TABLE '.$p.'company_project_native_metadata(issuer_id INT,project_id INT,workflow_status INT)');
unset($db->data_cache['table_names']);
foreach([[201,99991,50,null,1,'2026-02-01',1],[202,$nativeA,100,41,2,'2026-03-01',1],[203,$nativeA,999,82,1,'2026-03-01',1],[204,$nativeB,200,82,1,'2026-03-01',1],[205,$nativeA,777,41,1,'2026-03-01',1]]as[$id,$client,$amount,$issuer,$status,$date,$currency]){
 $db->query('INSERT INTO '.$p.'invoices(id,clientid,total,paymentmode,issuer_id,status,date,currency) VALUES(?,?,?,?,?,?,?,?)',[$id,$client,$amount,'',$issuer,$status,$date,$currency]);
 if(in_array($id,[202,203,204],true))$db->query('INSERT INTO '.$p.'issuers_invoice_documents(invoice_id,issuer_id,series,number,prefix,formatted_number,legal_identity_json,recipient_identity_json,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$id,$issuer,'A',$id,'','A-'.$id,'{}','{}','2026-01-01 00:00:00']);
}
foreach([[301,41,$nativeA,'active',1,2],[302,41,$nativeA,'active',0,1],[303,82,$nativeB,'completed',1,4],[304,$primary,99991,'cancelled',1,5]]as[$id,$issuer,$client,$status,$visible,$phase]){
 $db->query('INSERT INTO '.$p.'company_projects(id,issuer_id,client_id,status) VALUES(?,?,?,?)',[$id,$issuer,$client,$status]);
 $db->query('INSERT INTO '.$p.'company_portal_projects(issuer_id,project_id,visible) VALUES(?,?,?)',[$issuer,$id,$visible]);
 $db->query('INSERT INTO '.$p.'company_project_native_metadata(issuer_id,project_id,workflow_status) VALUES(?,?,?)',[$issuer,$id,$phase]);
}
$db->query('INSERT INTO '.$p.'projects(id,clientid,status) VALUES(401,99991,2),(402,?,4)',[$nativeA]);
$home=new NativePortalHome($db,$p,$portal);
$aHome=$home->snapshot($binding,41);essentialCheck($aHome['permissions']===['invoices'=>true,'projects'=>true]&&$aHome['invoice_counts'][2]===1&&array_sum($aHome['invoice_counts'])===1,'A counts only its own invoice with persisted document');
essentialCheck($aHome['invoice_chart']['2026'][1][2][3]==='100'&&$aHome['project_owned']===['in_progress'=>1]&&$aHome['project_legacy']===[],'A chart and published project phases are derived from its own sources');
$db->query('UPDATE '.$p.'currencies SET name=? WHERE id=1',['USD']);essentialThrows(fn()=>$home->snapshot($binding,41),'issuer_portal_home_currency_invalid','changed live currency cannot relabel captured issued amounts');$db->query('UPDATE '.$p.'currencies SET name=? WHERE id=1',[$currencyName]);
$bHome=$home->snapshot($bindingB,82);essentialCheck($bHome['invoice_counts'][1]===1&&$bHome['project_owned']===['completed'=>1]&&$bHome['project_legacy']===[],'B cannot see A invoice or projects');
$principalHome=$home->snapshot($primaryNativeBinding,$primary);essentialCheck($principalHome['invoice_counts'][1]===1&&$principalHome['project_legacy']===[2=>1]&&$principalHome['project_owned']===['cancelled'=>1],'principal combines its historical and explicitly owned data only');
essentialThrows(fn()=>$home->snapshot($binding,82),'native_portal_home_denied','A binding never grants B dashboard');

// Exercise the canonical controller and the actual perfex home view through
// real prepare/resolve; only HTTP transport and the framework loader are seams.
class NativeHomeControllerFixture extends Clients{public function layout($notInThemeViewFiles=false){if($this->view!=='home')throw new LogicException('unexpected home view');extract($this->data);include APPPATH.'views/themes/perfex/views/home.php';}}
$homeController=(new ReflectionClass(NativeHomeControllerFixture::class))->newInstanceWithoutConstructor();foreach(get_object_vars($ci)as$key=>$value)$homeController->$key=$value;
(new NativePortalIdentity($db,$p,str_repeat('synthetic-',4)))->establish($homeController->session,41,$binding);$homeController->router->class='clients';$homeController->router->method='index';$homeController->input->verb='GET';$homeController->input->get=['issuer_id'=>'41'];$homeController->input->post=[];$homeController->data=[];
essentialCheck(PortalRequestScope::prepare($homeController)&&PortalRequestScope::resolve($homeController),'canonical home route resolves the current company and contact');
$menu=NativePortalPresentation::menu(['allow_registration'=>'0'],$homeController);essentialCheck(isset($menu['home'],$menu['files'],$menu['invoices'],$menu['proposals'],$menu['projects'],$menu['profile'],$menu['company'])&&!isset($menu['tickets']),'original menu advertises completed contact-bound routes and never advertises a route without its grant');
$db->query('UPDATE '.$p.'contacts SET firstname=? WHERE id=901',['<img src=x onerror=alert(1)>']);ob_start();$homeController->index();$html=ob_get_clean();
if(($snapshotPath=getenv('NATIVE_PORTAL_HOME_TEST_HTML'))!==false&&$snapshotPath!=='')file_put_contents($snapshotPath,$html);
essentialCheck(str_contains($html,'section-client-dashboard')&&str_contains($html,'client-home-chart')&&str_contains($html,'clients/invoices/2?issuer_id=41'),'original dashboard renders scoped chart and canonical actions');
essentialCheck(!str_contains($html,'clients/projects')&&!str_contains($html,'clients/statement'),'dashboard emits no affordance to a route rejected by its own native guard');
essentialCheck(str_contains($html,'&lt;img src=x onerror=alert(1)&gt;')&&!str_contains($html,'<img src=x onerror=alert(1)>'),'contact name is escaped in the original home view');
essentialCheck(!str_contains($html,'999')&&!str_contains($html,'777')&&!str_contains($html,'"200"'),'home HTML never includes foreign or unconfirmed invoice aggregates');
$db->query('UPDATE '.$p.'contacts SET firstname=? WHERE id=901',['Synthetic']);
$homeController->input->get=[];essentialCheck(PortalRequestScope::prepare($homeController)&&PortalRequestScope::resolve($homeController)&&PortalRequestScope::issuerId()===41,'direct home URL without selector uses the A session binding');ob_start();$homeController->index();$directHome=ob_get_clean();essentialCheck(str_contains($directHome,'clients/invoices/2?issuer_id=41')&&!str_contains($directHome,'"200"'),'direct home never hydrates another company or legacy global data');
$blockedPortalRoutes=['statement'];foreach($blockedPortalRoutes as$blocked){$homeController->router->method=$blocked;essentialCheck(PortalRequestScope::prepare($homeController),'unadapted canonical '.$blocked.' route enters the scope guard');essentialThrows(fn()=>PortalRequestScope::resolve($homeController),'native_portal_route_unavailable','unadapted '.$blocked.' destination cannot hydrate legacy global models');}
$homeController->router->method='index';NativePortalIdentity::clear($homeController->session);$homeController->input->get=['issuer_id'=>'41'];essentialCheck(PortalRequestScope::prepare($homeController)&&PortalRequestScope::resolve($homeController),'anonymous home retains explicit selected company');
try{$homeController->index();throw new RuntimeException('anonymous home not redirected');}catch(NativePortalRedirect$e){essentialCheck($e->status===303&&str_contains($e->url,'authentication/login?issuer_id=41'),'anonymous home redirects to its selected company login');}

$db->query('DELETE FROM '.$p.'contact_permissions WHERE userid=? AND permission_id=1',[901]);$noInvoice=$home->snapshot($binding,41);essentialCheck(!$noInvoice['permissions']['invoices']&&array_sum($noInvoice['invoice_counts'])===0&&$noInvoice['invoice_chart']===[],'revoked invoice permission omits all financial projection');
$db->query('UPDATE '.$p.'contacts SET email_verified_at=NULL WHERE id=?',[901]);essentialThrows(fn()=>$home->snapshot($binding,41),'native_portal_verification_required','unverified contact cannot read home data');
echo 'Native portal home source: '.($GLOBALS['essentialChecks']-$start).' actual SQLite checks passed.'.PHP_EOL;
