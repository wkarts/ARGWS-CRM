<?php
require __DIR__.'/staff_commands_storage.php';require_once APPPATH.'services/NativeCompanyStaffInviteSchema.php';
NativeCompanyStaffInviteSchema::ensure($db,$p);NativeCompanyStaffInviteSchema::ensure($db,$p);
essentialCheck($db->query('SELECT COUNT(*) n FROM '.$p.'company_native_staff_invites')->row_array()['n']===0,'invitation schema replay does not create credentials or users');
$db->query('DROP TABLE '.$p.'company_native_staff_invites');$db->query('CREATE TABLE '.$p.'company_native_staff_invites(staff_id INTEGER NOT NULL PRIMARY KEY)');
settingThrows(fn()=>NativeCompanyStaffInviteSchema::assertValid($db,$p),'native_staff_invite_schema_invalid','partial schema fails closed');
echo "Staff invitation SQLite schema: two real replay/metadata checks; no invitation issued.\n";
