<?php
// Native production staffid is AUTO_INCREMENT. Only this fixture opts into its real SQLite equivalent.
define('ESSENTIAL_NATIVE_STAFF_AUTOINCREMENT',true);
require __DIR__.'/staff_form_transaction.php';
TestPdo::registerSqliteCollation($db->pdo,'utf8mb4_unicode_ci',static fn($a,$b)=>strcasecmp($a,$b));
$db->query("UPDATE {$p}staff SET email='staff'||staffid||'@example.test'");
$db->query('CREATE UNIQUE INDEX staff_email_actual_unique ON '.$p.'staff(LOWER(TRIM(email)) COLLATE utf8mb4_unicode_ci)');
$roleRevision=$roles->page(41,7)['revision'];$createForm=new NativeCompanyStaffForm($db,$p,41,7,[$wire],$role,$roleRevision,[$field=>'New staff field'],null,null,['value'=>'12.00','revision'=>0]);
$profile=['firstname'=>'Created','lastname'=>'Native','email'=>'created.native@example.test','password'=>'synthetic-created-123','phonenumber'=>'000','email_signature'=>'<p>Native signature</p>','default_language'=>'portuguese_br','direction'=>'ltr','is_not_staff'=>'0'];$request=hash('sha256','native-staff-created');
$id=$service->createNative(41,7,$profile,['tasks'=>['view']],true,$request,$createForm);
essentialCheck($id>14&&(new NativeCompanyStaffRead($db,$p))->member(41,7,$id)['member']->firstname==='Created','native real autoincrement INSERT returned persisted identity');
essentialCheck($custom->values(41,7,'staff',$id)===[$field=>'New staff field']&&$createForm->state($id)['role']===$role,'new identity owns its role department and native custom field values');
essentialCheck($service->createNative(41,7,$profile,['tasks'=>['view']],true,$request,$createForm)===$id,'creation replay returns same identity without replaying participants');
settingThrows(fn()=>IssuerTransaction::run($db,$p,$createForm->tables(),fn()=>$createForm->assertCreatedInOwned($db,$p,41,7,'staff',$id)),'native_custom_field_creation_denied','new-ID proof expires after committed attempt');
$badForm=new NativeCompanyStaffForm($db,$p,41,7,[$wire],$role,$roles->page(41,7)['revision'],[$field=>'Rollback']);$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'staff')->row()->n;
$snapshot=function()use($db,$p){$out=[];foreach(['staff','issuers_staff','issuers_staff_defaults','company_staff_commands','company_department_members','company_custom_field_values','issuers_registry','issuers_audit']as$t){$rows=$db->query('SELECT * FROM '.$p.$t)->result_array();usort($rows,fn($a,$b)=>strcmp(json_encode($a),json_encode($b)));$out[$t]=$rows;}return$out;};$allBefore=$snapshot();
$db->query('CREATE TRIGGER reject_staff_custom BEFORE INSERT ON '.$p.'company_custom_field_values BEGIN SELECT RAISE(IGNORE); END');
settingThrows(fn()=>$service->createNative(41,7,array_replace($profile,['email'=>'rollback.native@example.test']),[],true,hash('sha256','native-create-rollback'),$badForm),'issuer_storage_failed','late custom-field INSERT failure aborts full creation');$db->query('DROP TRIGGER reject_staff_custom');
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'staff')->row()->n===$before&&$snapshot()===$allBefore,'late failure leaves no identity membership role assignment or default orphan');
$db->query('CREATE TRIGGER ignore_staff_insert BEFORE INSERT ON '.$p.'staff BEGIN SELECT RAISE(IGNORE); END');
settingThrows(fn()=>$service->createNative(41,7,array_replace($profile,['email'=>'ignored.native@example.test']),[],false,hash('sha256','native-ignore-insert'),$badForm),'issuer_staff_write_unconfirmed','ignored identity INSERT cannot reuse lastInsertId as new-ID proof');$db->query('DROP TRIGGER ignore_staff_insert');
essentialCheck($snapshot()===$allBefore,'ignored source INSERT leaves every participant unchanged');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=41 AND staff_id=12 AND feature='staff' AND capability='edit'");
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'staff','create'),(41,12,'tasks','view')");
$limitedForm=new NativeCompanyStaffForm($db,$p,41,12,[$wire],$role,$roles->page(41,7)['revision'],[$field=>'Limited creator']);
$limited=$service->createNative(41,12,array_replace($profile,['email'=>'limited.native@example.test']),['tasks'=>['view']],false,hash('sha256','limited-native-create'),$limitedForm);
essentialCheck($limited>14&&$custom->values(41,7,'staff',$limited)===[$field=>'Limited creator'],'create-only actor commits complete new native form without being promoted to edit');
echo "Native complete staff creation: nine actual SQLite checks; no HTTP/SMTP.\n";

essentialCheck((new NativeCompanyStaffRates($db,$p))->forStaff(41,7,$id)['hourly_rate']==='12.00','creation persists membership wage in same native transaction');
