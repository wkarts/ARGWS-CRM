<?php
/** Real PDO legacy/owned coexistence: IDs and historical rows are not adopted or renumbered. */
require __DIR__.'/auxiliary_contract.php';
$auto=$db->driver()==='sqlite'?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT NOT NULL AUTO_INCREMENT PRIMARY KEY';
$db->query("CREATE TABLE {$p}todos(todoid {$auto},staffid INT,description TEXT,finished INT,item_order INT,dateadded DATETIME,datefinished DATETIME NULL)");
$db->query("INSERT INTO {$p}todos(todoid,staffid,description,finished,item_order,dateadded,datefinished) VALUES(1,7,'Historical<br />line',0,1,'2020-01-01 00:00:00',NULL),(2,14,'Other actor',0,0,'2020-01-01 00:00:00',NULL)");
$principal=(new IssuerResourceAccess($db,$p))->primaryId();$owned=new NativeCompanyAuxiliary($db,$p,$principal,7);$id=$owned->saveTodo(['description'=>'Owned principal']);$browser=NativeCompanyResourceId::encode('company_todo',$id);$ui=new NativeCompanyAuxiliaryUi($db,$p,$principal,7);
$rows=$ui->todos(false);essentialCheck(count($rows)===2&&$ui->todoCounts()===[0=>2,1=>0],'principal combines historical and owned private rows only');
essentialCheck($rows[0]['source_kind']==='company_todo'&&$rows[1]['source_kind']==='todo'&&$rows[1]['todoid']==='1','overlapping source IDs retain separate canonical browser identities');
$old=$ui->todo('1');essentialCheck($old['description']==="Historical\nline"&&strlen($old['revision'])===64,'legacy native breaks normalized only in escaped DTO, snapshot revision retained');
settingThrows(fn()=>new NativeCompanyAuxiliaryLegacy($db,$p,41,7),'company_auxiliary_access_denied','master cannot mistake named company for historical principal');
$ui->saveTodo(['description'=>'Edited history'],'1',$old['revision']);
settingThrows(fn()=>$ui->saveTodo(['description'=>'stale'],'1',$old['revision']),'company_auxiliary_stale','legacy row fingerprint rejects stale update');
$old=$ui->todo('1');$ownedBefore=$ui->todo($browser);
settingThrows(fn()=>$ui->orderTodos([['1',3,1,$old['revision']],[$browser,2,1,999]]),'company_auxiliary_stale','mixed legacy/owned reorder rolls back if owned revision stale');
essentialCheck($ui->todo('1')['revision']===$old['revision']&&$ui->todo($browser)['revision']===$ownedBefore['revision'],'failed mixed reorder changes neither storage lineage');
$result=$ui->orderTodos([['1',3,1,$old['revision']],[$browser,2,1,$ownedBefore['revision']]]);
essentialCheck(strlen($result[1])===64&&$ui->todoCounts()===[0=>0,1=>2],'mixed reorder commits both lineages and publishes their exact revisions');
$db->failOn='INSERT INTO '.$p.'issuers_audit';settingThrows(fn()=>$ui->deleteTodo('1',$result[1]),'issuer_storage_failed','audit failure rolls back legacy deletion');$db->failOn=null;
essentialCheck($ui->todo('1')['revision']===$result[1],'legacy deletion is atomic with issuer audit');
$ui->deleteTodo('1',$result[1]);essentialCheck($ui->todoCounts()===[0=>0,1=>1]&&(int)$db->query("SELECT COUNT(*) n FROM {$p}todos WHERE staffid=14")->row()->n===1,'deletion preserves other actor history and owned row');
echo "Primary todo legacy/owned lineage and atomic CAS: passed.\n";
