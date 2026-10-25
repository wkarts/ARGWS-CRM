<?php
require __DIR__.'/../settings/company_settings.php';require_once APPPATH.'services/NativeCompanyStaffRead.php';
foreach(['firstname'=>'TEXT','lastname'=>'TEXT','email'=>'TEXT','phonenumber'=>'TEXT','password'=>'TEXT','role'=>'INT','is_not_staff'=>'INT','hourly_rate'=>'DECIMAL(15,2)','facebook'=>'TEXT','linkedin'=>'TEXT','skype'=>'TEXT','default_language'=>'TEXT','email_signature'=>'TEXT','direction'=>'TEXT','profile_image'=>'TEXT','last_login'=>'TEXT','last_activity'=>'TEXT','last_password_change'=>'TEXT','datecreated'=>'TEXT','two_factor_auth_enabled'=>'INT']as$field=>$type)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field.' '.$type);
$db->query("UPDATE {$p}staff SET firstname='Synthetic',lastname='Person',email='synthetic@example.test',password='private-hash',role=0,is_not_staff=0,hourly_rate=15.25,two_factor_auth_enabled=0");
$r=new NativeCompanyStaffRead($db,$p);$row=$r->member(41,7,12);essentialCheck($row['member']->firstname==='Synthetic'&&(string)$row['member']->hourly_rate==='15.25','native staff fields projected from actual identity');
essentialCheck(!isset($row['member']->password)&&!str_contains(json_encode($row),'private-hash'),'password hash never enters staff form DTO');
settingThrows(fn()=>$r->member(82,7,14),'issuer_staff_denied','foreign unlinked identity denied');
$db->query("INSERT INTO {$p}issuers_resource_permissions VALUES(41,12,'staff','view')");$row=$r->member(41,12,14);essentialCheck($row['access']['profile_editable']===true,'exclusive identity policy comes from existing service');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=41 AND staff_id=12");settingThrows(fn()=>$r->member(41,12,14),'issuer_staff_denied','revoked actor cannot hydrate profile');
echo "Native staff form reader: five actual SQLite identity/ownership/secret/ACL checks passed.\n";
