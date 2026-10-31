<?php
require __DIR__.'/auxiliary_contract.php';
if($db->driver()!=='sqlite'){echo "Ignored-insert trigger proof requires SQLite.\n";return;}
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=41 AND staff_id=12");
// Identical preceding source values and insert ID must not impersonate a successful INSERT.
$a=new NativeCompanyAuxiliary($db,$p,41,12);
$id=$a->saveTodo(['description'=>'Identical source']);
$db->query("CREATE TRIGGER ignore_todo BEFORE INSERT ON {$p}company_todos BEGIN SELECT RAISE(IGNORE); END");
$count=$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit")->row_array()['n'];
settingThrows(fn()=>$a->saveTodo(['description'=>'Identical source']),'issuer_native_insert_unconfirmed','ignored source insert cannot return old row as newly created');
essentialCheck($count===$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit")->row_array()['n'],'ignored source cannot append a false creation audit');
$db->query('DROP TRIGGER ignore_todo');
$c=new NativeCompanyCatalog($db,$p,41,12);$id=$c->saveItem(['description'=>'Same catalogue item','rate'=>'1']);
$db->query("CREATE TRIGGER ignore_item BEFORE INSERT ON {$p}company_items BEGIN SELECT RAISE(IGNORE); END");
settingThrows(fn()=>$c->saveItem(['description'=>'Same catalogue item','rate'=>'1']),'issuer_native_insert_unconfirmed','identical old item cannot substitute for ignored creation');
$db->query('DROP TRIGGER ignore_item');
echo "Ignored native source inserts require one affected row: passed.\n";
