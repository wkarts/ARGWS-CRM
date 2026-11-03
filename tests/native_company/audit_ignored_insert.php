<?php
require __DIR__.'/primary_auxiliary.php';
if($db->driver()!=='sqlite'){echo "SQLite ignored-insert audit proof: skipped (real MySQL rollback covered by auxiliary contract).\n";return;}
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
$a=new NativeCompanyAuxiliary($db,$p,41,12);$id=$a->saveTodo(['description'=>'same']);$a->saveTodo(['description'=>'same'],$id,1);$snapshot=$a->todo($id);
$db->query("CREATE TRIGGER ignore_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$a->saveTodo(['description'=>'same'],$id,2),'issuer_auxiliary_audit_unconfirmed','ignored audit insert cannot reuse immediately preceding same-event receipt');
essentialCheck($a->todo($id)===$snapshot,'ignored audit rolls back row revision as well as data');$db->query('DROP TRIGGER ignore_audit');
$catalog=new NativeCompanyCatalog($db,$p,41,12);$item=$catalog->saveItem(['description'=>'same item','rate'=>'1']);$catalog->saveItem(['description'=>'same item','rate'=>'1'],$item,1);$snapshot=$catalog->item($item);
$db->query("CREATE TRIGGER ignore_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$catalog->saveItem(['description'=>'same item','rate'=>'1'],$item,2),'issuer_catalog_write_unconfirmed','catalog repeated same operation cannot reuse old audit ID');
essentialCheck($catalog->item($item)===$snapshot,'catalog mutation rolls back ignored audit');$db->query('DROP TRIGGER ignore_audit');
$db->query("INSERT INTO {$p}todos(todoid,staffid,description,finished,item_order,dateadded,datefinished) VALUES(4,7,'same legacy',0,0,'2020-01-01 00:00:00',NULL)");
$ui=new NativeCompanyAuxiliaryUi($db,$p,$principal,7);$old=$ui->todo('4');$ui->saveTodo(['description'=>'same legacy'],'4',$old['revision']);$old=$ui->todo('4');
$db->query("CREATE TRIGGER ignore_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$ui->saveTodo(['description'=>'same legacy'],'4',$old['revision']),'issuer_auxiliary_audit_unconfirmed','legacy unchanged update also requires its own fresh audit receipt');
essentialCheck($ui->todo('4')===$old,'legacy unchanged operation stays unchanged after audit rejection');$db->query('DROP TRIGGER ignore_audit');
echo "Ignored audit inserts cannot reuse same-second receipts: passed.\n";
