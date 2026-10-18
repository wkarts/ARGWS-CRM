<?php
/** Real company ACL and staff-access storage on synthetic SQLite/MySQL fixtures. */
require __DIR__.'/issuers_registry.php';
require_once __DIR__.'/../application/services/issuers/IssuerStaffAccess.php';
require_once __DIR__.'/../application/services/issuers/IssuerAccessSchema.php';
$baseline=$checks;
foreach(IssuerAccessSchema::statements($prefix) as $sql) {
    if($db->driver==='sqlite') { $sql=str_replace(' CHARACTER SET ascii COLLATE ascii_bin','',$sql);$sql=preg_replace('/ ENGINE=InnoDB.*$/s','',$sql); }
    $db->query($sql);
}
$db->query("ALTER TABLE {$p}audit ADD metadata_json longtext DEFAULT NULL");
$acl=new IssuerResourceAccess($db,$prefix);
$catalog=['banco_brasil'=>['view','manage','import'],'pige_bridge'=>['view','edit'],'customers'=>['view','edit']];
$admin=new IssuerStaffAccess($db,$prefix,1,$catalog);
$regular=new IssuerStaffAccess($db,$prefix,3,$catalog);
same(false,$acl->isMaster(1),'ID1/admin alone is not installation master');
same(false,$acl->can($a,1,'banco_brasil','manage'),'old generic integration grant does not imply resource grant');
$db->query("INSERT INTO {$p}installation_identity(id,master_staff_id,primary_issuer_id,source,resolved_at) VALUES(1,1,?,'synthetic','2026-10-18 00:00:00')",[$a]);
$admin->save(3,[$a=>'1'],$a,[$a=>['banco_brasil'=>['view','manage'],'pige_bridge'=>['view']]]);
same(true,$acl->can($a,3,'banco_brasil','manage'),'explicit company resource permission');
same(false,$acl->can($a,3,'banco_brasil','import'),'resource capabilities independent');
same(false,$acl->can($b,3,'banco_brasil','manage'),'permission never crosses companies');
same(false,$acl->can($a,3,'pige_bridge','edit'),'permission never crosses resources');
same($a,$r->defaultForStaff(3),'default is authorized membership');
same(0,(int)$db->query("SELECT can_manage_integrations FROM {$p}staff WHERE issuer_id=? AND staff_id=3",[$a])->result_array()[0]['can_manage_integrations'],'native resource assignment adds no generic integration grant');
foreach([
 fn()=>$admin->prepare(3,[$a=>'1'],$b,[]),
 fn()=>$admin->prepare(3,[$a=>'1'],$a,[$b=>['banco_brasil'=>['view']]]),
 fn()=>$admin->prepare(3,[$a=>'1'],$a,[$a=>['unknown'=>['view']]]),
 fn()=>$admin->prepare(3,[$a=>'1'],$a,[$a=>['banco_brasil'=>['unknown']]]),
 fn()=>$admin->prepare(3,[$a=>['view']],null,[]),
 fn()=>$admin->prepare(3,[$a=>'1'],null,[$a=>['banco_brasil'=>['VIEW']]]),
] as $index=>$invalid) { rejects($invalid,$index===0?'issuer_invalid_default':'issuer_invalid_permission'); }
rejects(fn()=>$regular->save(3,[$b=>'1'],$b,[]),'issuer_access_denied');
$db->query("UPDATE {$prefix}staff SET admin=1 WHERE staffid=4");
same(false,$acl->can($a,4,'banco_brasil','manage'),'ordinary administrator has no implicit resource permission');
$admin->save(4,[$a=>'1'],null,[]);
same(false,$acl->can($a,4,'banco_brasil','manage'),'ordinary admin membership is not resource permission');
$db->query("UPDATE {$prefix}staff SET active=0 WHERE staffid=3");
same(false,$acl->can($a,3,'banco_brasil','manage'),'deactivated staff denied immediately');
$db->query("UPDATE {$prefix}staff SET active=1 WHERE staffid=3");
$db->query("UPDATE {$p}registry SET active=0 WHERE id=?",[$a]);
same(false,$acl->can($a,3,'banco_brasil','manage'),'deactivated company denied immediately');
$db->query("UPDATE {$p}registry SET active=1 WHERE id=?",[$a]);
$before=$admin->read(3);
$db->failOn="INSERT INTO {$p}audit";
try { $admin->save(3,[$b=>'1'],$b,[$b=>['banco_brasil'=>['import']]]);throw new LogicException('failure missing'); } catch(RuntimeException $error) { same('issuer_storage_failed',$error->getMessage(),'audit failure injected'); }
$db->failOn=null;
same($before,$admin->read(3),'failed complete replacement restores membership/default/resources');
$db->trans_begin();
try { $admin->save(3,[],null);throw new LogicException('outer transaction accepted'); } catch(RuntimeException $error) { same('issuer_outer_transaction_not_allowed',$error->getMessage(),'outer transaction denied'); }
same(true,$pdo->inTransaction(),'caller transaction not rolled back');$db->trans_rollback();
$admin->save(3,[],null);
same(false,$acl->can($a,3,'banco_brasil','manage'),'revocation removes resource access');
same(null,$r->defaultForStaff(3),'revocation clears default');
same([],$acl->rowsForStaff(3),'revocation deletes child permission rows before membership');
$admin->save(3,[$b=>'1'],$b,[$b=>['pige_bridge'=>['edit']]]);
$regular->saveOwnDefault(null);same(null,$r->defaultForStaff(3),'own profile can clear default');
$regular->saveOwnDefault($b);same($b,$r->defaultForStaff(3),'own profile chooses existing membership');
rejects(fn()=>$regular->saveOwnDefault($a),'issuer_invalid_default');
$db->query("UPDATE {$p}installation_identity SET master_staff_id=4 WHERE id=1");
same(true,$acl->isMaster(4),'explicit installation identity, not id1');
same(true,$acl->can($b,4,'future_module','future_capability'),'master covers future registered resources');
same(true,$r->canAccess($b,4),'registry identity available to explicit master');
same(3,count($r->available(4)),'master can see all active companies');
rejects(fn()=>$r->saveIssuer(1,['legal_name'=>'Denied future','tax_id'=>syntheticCnpj('123450000009')]),'issuer_access_denied');
$db->query("INSERT INTO {$p}registry(legal_name,tax_id,active,settings_json,created_by,created_at) VALUES('Future synthetic',?,1,'{}',4,'2026-10-18 00:00:00')",[syntheticCnpj('123450000009')]);$new=(int)$db->insert_id();
same(true,$acl->can($new,4,'banco_brasil','manage'),'master covers future company without grant copying');
same(false,$acl->can($new,1,'banco_brasil','manage'),'ordinary admin does not acquire future company');
$master=new IssuerStaffAccess($db,$prefix,4,$catalog);$master->saveOwnDefault($new);
same($new,$r->defaultForStaff(4),'master default persists with explicit FK membership row');
rejects(fn()=>$admin->save(4,[$b=>'1'],$b,[]),'issuer_access_denied');
rejects(fn()=>$master->save(4,[$b=>'1'],$b,[]),'issuer_master_access_automatic');
$db->query("UPDATE {$prefix}staff SET admin=0 WHERE staffid=4");
same(false,$acl->isMaster(4),'master must remain administrator');
same(false,$acl->can($new,4,'banco_brasil','manage'),'master demotion cannot retain implicit resource access');
$db->query("UPDATE {$prefix}staff SET admin=1,active=0 WHERE staffid=4");
same(false,$acl->can($new,4,'banco_brasil','manage'),'inactive master denied');
$db->query("UPDATE {$prefix}staff SET active=1 WHERE staffid=4");
foreach([0,'0','01',null,false] as $invalid) { same(false,$acl->can($invalid,4,'banco_brasil','manage'),'invalid company never authorized for master'); }
foreach(['','7VIEW','a b','a/b',str_repeat('a',101)] as $invalid) { same(false,$acl->can($a,4,$invalid,'view'),'invalid resource never authorized'); }
same(5000,(int)$pdo->query("SELECT amount FROM {$prefix}invoices WHERE id=1")->fetchColumn(),'issuer access never transfers finance');
rejects(fn()=>IssuerPolicy::assertOperationalScope($new),'issuer_rollout_blocked');
echo 'Issuer staff/resource access: '.($checks-$baseline).' additional checks passed ('.$db->driver.").\n";
