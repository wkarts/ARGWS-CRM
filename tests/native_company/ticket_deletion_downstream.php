<?php
declare(strict_types=1);
require __DIR__.'/ticket_deletion.php';
require_once __DIR__.'/../../application/services/CompanyTickets.php';
$db->query('UPDATE tblissuers_staff SET can_view=1 WHERE issuer_id=2 AND staff_id=17');$db->query('UPDATE tblissuers_installation_identity SET master_staff_id=17');
$wrapped=new TicketCreateDb($db,array_merge(CompanyContractsSchema::definitions('tbl'),NativeCompanyTicketReplySchema::definitions('tbl'),NativeCompanyTicketLifecycleSchema::definitions('tbl'),NativeCompanyTicketMergeSchema::definitions('tbl')));
$legacy=new CompanyTickets($wrapped,'tbl');$deny(fn()=>$legacy->get(2,17,$source));$items=$legacy->page(2,17);kc(!in_array($source,array_map('intval',array_column($items['items'],'id')),true),'existing owned API excludes tombstone before pagination');
$deny(fn()=>$legacy->change(2,17,$source,1,17,'open','normal',hash('sha256','api-resurrect')));$deny(fn()=>$legacy->reply(2,17,$source,1,'API resurrection',hash('sha256','api-reply-resurrect')));
// Exact production portal/publication/file guards run against real retained rows. Their outer HTTP/session adapters are tested separately.
require_once __DIR__.'/../../application/services/CompanyOperationPublication.php';require_once __DIR__.'/../../application/services/CompanyOperationFiles.php';
$publication=new CompanyOperationPublication($wrapped,'tbl');$method=new ReflectionMethod(CompanyPortalOperationsStorage::class,'parent');$deny(fn()=>$method->invoke($publication,2,'tickets',$source));
$files=new CompanyOperationFiles($wrapped,'tbl');$method=new ReflectionMethod(CompanyOperationFiles::class,'parent');$deny(fn()=>$method->invoke($files,2,'ticket',$source));
// Correlated predicates must hide only the deleted source, including history where its column is also ticket_id.
$db->query('CREATE TABLE deleted_history(id INTEGER,ticket_id INTEGER,issuer_id INTEGER)');$other=NativeCompanyResourceId::decode('ticket',$contactId)['source_id'];$db->query('INSERT INTO deleted_history VALUES(1,?,2),(2,?,2)',[$source,$other]);
$predicate=NativeCompanyTicketVisibility::predicate($wrapped,'tbl','h.issuer_id','h.ticket_id');$rows=$db->query('SELECT h.id FROM deleted_history h WHERE '.$predicate.' ORDER BY h.id')->result_array();kc(array_column($rows,'id')===[2],'history predicate uses outer alias, not self-correlated deleted ID');
try{NativeCompanyTicketVisibility::predicate($wrapped,'tbl','h.issuer_id','h.id; DROP TABLE x');throw new LogicException('SQL identity accepted');}catch(InvalidArgumentException$e){kc($e->getMessage()==='Invalid SQL identity','SQL identifiers are not caller-controlled expressions');}
$db->query('UPDATE tblmigrations SET version=473');kc(NativeCompanyTicketVisibility::predicate($wrapped,'tbl','t.issuer_id','t.id')==='1=1','explicit historical version retains original behavior');$db->query('UPDATE tblmigrations SET version=474');
$bad=new TicketCreateDb($db);try{NativeCompanyTicketVisibility::enabled($bad,'tbl');throw new LogicException('missing metadata accepted');}catch(RuntimeException$e){kc($e->getMessage()==='issuer_native_ticket_lifecycle_schema_invalid','474 missing metadata fails closed');}
echo "Downstream real SQLite passed: existing CompanyTickets get/page/change/reply, production publication/file parent guards, correlated history predicate, explicit pre474 boundary and missing474 schema rejection. Full portal HTTP/MySQL still pending.\n";
// Real provider source selectors are exercised under a declared issuer transaction; transports stay unused.
require_once __DIR__.'/../../application/services/CompanyAi.php';require_once __DIR__.'/../../application/services/CompanyTicketConnect.php';
$calls=0;$ai=new CompanyAi($wrapped,'tbl',new CompanyAiClient(function()use(&$calls){$calls++;throw new LogicException('must not call provider');}));$connect=new CompanyTicketConnect($wrapped,'tbl',new CompanyConnectApiClient(function()use(&$calls){$calls++;throw new LogicException('must not call provider');}));
IssuerTransaction::run($wrapped,'tbl',array_merge((new NativeCompanyTicketRead($wrapped,'tbl'))->tables(),['company_connect_api_accounts','company_connect_api_requests']),function()use($ai,$connect,$source,$deny){
 foreach([[$ai,'source',[2,17,$source,'summary']],[$connect,'ticket',[2,17,$source]]]as[$object,$method,$args]){
  try{(new ReflectionMethod($object,$method))->invokeArgs($object,$args);throw new LogicException('deleted provider source accepted');}catch(DomainException$e){kc($e->getMessage()==='native_ticket_deleted','actual provider selector rejects deleted source');}
 }
});kc($calls===0,'no provider request or credential-bearing transport');
echo "Actual AI/Connect ticket selectors deny tombstones with zero provider transports. Dispatch integration fixtures remain required; external contracts untouched.\n";
