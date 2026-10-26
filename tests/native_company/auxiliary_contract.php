<?php
/** Real PDO service tests, synthetic companies. SQLite is NOT MySQL DDL proof. */
require dirname(__DIR__).'/settings/company_settings.php';
require_once APPPATH.'services/NativeCompanyAuxiliary.php';
require_once APPPATH.'services/NativeCompanyCatalog.php';
$db->query("UPDATE {$p}issuers_registry SET active=1 WHERE id=82");
$db->query("ALTER TABLE {$p}staff ADD firstname varchar(80)");
$db->query("ALTER TABLE {$p}staff ADD lastname varchar(80)");
$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Staff'");
if($db->driver()==='sqlite'){
    foreach(NativeCompanyAuxiliarySchema::definitions($p)as$table=>$parts){
        $parts=array_values(array_filter($parts,static fn($part)=>!str_starts_with($part,'KEY ')));
        $auto=null;
        foreach(NativeCompanyAuxiliarySchema::fields()[$table]as$name=>[$type])if(str_contains($type,'AUTO_INCREMENT'))$auto=$name;
        foreach($parts as&$part){
            $part=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part);
            $part=preg_replace('/decimal\([0-9]+,[0-9]+\)/','TEXT',$part);
            $part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);
            if($auto!==null&&str_starts_with($part,'`'.$auto.'`'))$part='`'.$auto.'` INTEGER PRIMARY KEY AUTOINCREMENT';
        }unset($part);
        if($auto!==null)$parts=array_values(array_filter($parts,static fn($part)=>$part!=='PRIMARY KEY (`'.$auto.'`)'));
        $db->query('CREATE TABLE '.$p.$table.' ('.implode(',',$parts).')');
    }
}else{NativeCompanyAuxiliarySchema::ensure($db,$p);NativeCompanyAuxiliarySchema::ensure($db,$p);}
require_once APPPATH.'services/NativeCompanyCustomFieldSchema.php';
NativeCompanyCustomFieldSchema::ensure($db,$p);
foreach([41,82]as$issuer)foreach(['items','taxes','announcements','reports']as$feature)foreach(['view','create','edit','delete']as$cap)$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(?,12,?,?)",[$issuer,$feature,$cap]);
$a=new NativeCompanyAuxiliary($db,$p,41,12);$b=new NativeCompanyAuxiliary($db,$p,82,12);$other=new NativeCompanyAuxiliary($db,$p,41,14);
$id=$a->saveTodo(['description'=>'A private <script>test</script>']);
essentialCheck(count($a->todos(false))===1&&$b->todos(false)===[]&&$other->todos(false)===[],'todos bound to issuer AND actor');
settingThrows(fn()=>$b->todo($id),'company_auxiliary_access_denied','cross-company same actor cannot read guessed todo');
settingThrows(fn()=>$other->deleteTodo($id,1),'company_auxiliary_access_denied','different actor cannot delete private todo');
$a->saveTodo(['description'=>'Updated'], $id,1);
settingThrows(fn()=>$a->saveTodo(['description'=>'stale'],$id,1),'company_auxiliary_stale','stale update rejected');
$a->orderTodos([[$id,1,1,2]]);
essentialCheck($a->todoCounts()===[0=>0,1=>1]&&$a->todo($id)['datefinished']!==null,'status, counts and revision reflect committed actual rows');
$bId=$b->saveTodo(['description'=>'B']);
settingThrows(fn()=>$a->orderTodos([[$id,2,0,3],[$bId,1,0,1]]),'company_auxiliary_access_denied','cross-company reorder rejected atomically');
essentialCheck((int)$a->todo($id)['revision']===3&&(int)$a->todo($id)['finished']===1,'failed reorder leaves previous row untouched');
$db->failOn='INSERT INTO '.$p.'issuers_audit';
settingThrows(fn()=>$a->saveTodo(['description'=>'rollback'],$id,3),'issuer_storage_failed','audit failure fails mutation');$db->failOn=null;
essentialCheck($a->todo($id)['description']==='Updated'&&(int)$a->todo($id)['revision']===3,'audit failure rolls back todo update and revision');
$db->trans_begin();settingThrows(fn()=>$a->deleteTodo($id,3),'issuer_outer_transaction_not_allowed','does not join caller transaction');essentialCheck($db->pdo->inTransaction(),'caller transaction preserved');$db->trans_rollback();
$announcement=$a->saveAnnouncement(['name'=>'Only A','message'=>'<p>Visible</p><script>alert(1)</script>','showtostaff'=>'1']);
essentialCheck(count($a->announcements())===1&&$b->announcements()===[],'announcements do not cross companies');
essentialCheck(!str_contains($a->announcement($announcement)['message'],'<script'),'announcement parser removes executable markup');
require_once APPPATH.'services/NativeCompanyAuxiliaryUi.php';
$ui=new NativeCompanyAuxiliaryUi($db,$p,41,12);$otherUi=new NativeCompanyAuxiliaryUi($db,$p,41,14);$hiddenAnnouncement=$a->saveAnnouncement(['name'=>'Hidden A','message'=>'<p>Hidden</p>','showtostaff'=>'0']);
$alerts=$ui->dashboardAnnouncements();essentialCheck(count($alerts)===1&&$alerts[0]['announcementid']===NativeCompanyResourceId::encode('company_announcement',$announcement),'original dashboard alert uses canonical owned announcement identity');
essentialCheck(count($ui->dashboardAnnouncements(1))===1&&count($otherUi->dashboardAnnouncements())===1,'dashboard alert lineage remains bound to the actor dismissal, not just shared company data');
$ui->dismissAnnouncement($alerts[0]['announcementid']);$ui->dismissAnnouncement($alerts[0]['announcementid']);
essentialCheck($ui->dashboardAnnouncements()===[]&&$a->undismissedAnnouncementsCount()===0&&$other->undismissedAnnouncementsCount()===1,'dismissal is idempotent and private to actor');
essentialCheck($a->announcement($hiddenAnnouncement)['showtostaff']==='0','dashboard excludes hidden staff announcements without deleting them');
$catalogA=new NativeCompanyCatalog($db,$p,41,12);$catalogB=new NativeCompanyCatalog($db,$p,82,12);
$tax=$catalogA->saveTax(['name'=>'A tax','taxrate'=>'7.5']);$group=$catalogA->saveGroup(['name'=>'A group']);
$item=$catalogA->saveItem(['description'=>'Owned item','rate'=>'123.4567','tax'=>$tax,'group_id'=>$group]);
essentialCheck($catalogA->item($item)['rate']==='123.4567'||(string)$catalogA->item($item)['rate']==='123.4567','exact decimal preserved');
essentialCheck($catalogB->search('Owned')===[],'catalogue search filters issuer');
settingThrows(fn()=>$catalogB->item($item),'company_catalog_denied','guessed item cannot hydrate foreign catalogue');
settingThrows(fn()=>$catalogB->saveItem(['description'=>'Forbidden tax','rate'=>'1','tax'=>$tax]),'company_catalog_denied','cross-company tax reference rejected');
settingThrows(fn()=>$catalogA->saveTax(['name'=>'Bad|name','taxrate'=>'1']),'company_catalog_invalid','tax delimiter cannot inject another choice');
require_once APPPATH.'services/NativeCompanyCatalogUi.php';
$encoded=NativeCompanyResourceId::encode('company_todo',$id);
essentialCheck($ui->todo($encoded)['todoid']===$encoded,'browser DTO uses canonical namespace without altering stored ID');
settingThrows(fn()=>$ui->todo($id),'company_auxiliary_access_denied','native legacy ID cannot alias owned row');
settingThrows(fn()=>$ui->todo(NativeCompanyResourceId::encode('company_note',$id)),'native_resource_namespace_mismatch','wrong namespace cannot alias todo');
$catalogUi=new NativeCompanyCatalogUi($db,$p,41,12);$encodedItem=NativeCompanyResourceId::encode('company_item',$item);
essentialCheck($catalogUi->item($encodedItem)['itemid']===$encodedItem,'catalogue browser ID remains lineage bound');
settingThrows(fn()=>$catalogUi->item($item),'company_catalog_denied','raw native catalogue ID cannot alias owned row');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12");
settingThrows(fn()=>$a->todo($id),'company_auxiliary_access_denied','revoked membership cannot use retained object');
settingThrows(fn()=>$catalogA->item($item),'company_catalog_denied','revocation checked on catalog retained object');
echo "Native auxiliary PDO isolation and rollback: passed.\n";
