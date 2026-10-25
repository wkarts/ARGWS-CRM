<?php
require __DIR__.'/staff_self_endpoint.php';
$db->pdo->sqliteCreateCollation('utf8mb4_unicode_ci',static fn($a,$b)=>strcasecmp($a,$b));
$db->query('INSERT INTO '.$p.'issuers_resource_permissions VALUES(41,12,?,?)',['staff','edit']);$db->query('INSERT INTO '.$p.'issuers_resource_permissions VALUES(41,12,?,?)',['staff','view']);
$ci->input->data=['memberid'=>'14','email'=>'unused-staff@example.test'];essentialCheck(NativeCompanyStaffEndpoints::emailAvailable($ci),'authorized original email validation checks global login uniqueness');
$ci->input->data['email']='synthetic@example.test';essentialCheck(!NativeCompanyStaffEndpoints::emailAvailable($ci),'existing global login email is not duplicated');
CompanyRequestScope::$csrf=false;settingThrows(fn()=>NativeCompanyStaffEndpoints::emailAvailable($ci),'native_staff_csrf_denied','email validation cannot enumerate without CSRF');CompanyRequestScope::$csrf=true;
$ci->input->data['memberid']='999';settingThrows(fn()=>NativeCompanyStaffEndpoints::emailAvailable($ci),'issuer_staff_denied','target must be member of selected issuer');
$ci->input->data=['memberid'=>'14','email'=>['bad']];settingThrows(fn()=>NativeCompanyStaffEndpoints::emailAvailable($ci),'issuer_staff_invalid','array email rejected');
echo "Staff email validation: five actual service/SQLite checks.\n";
