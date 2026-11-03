<?php
require __DIR__.'/reminder_listing_scope.php';
$owned=new NativeCompanyAuxiliary($db,$p,$principal,14);
$from='2026-10-25 00:00:00';$until='2026-10-26 00:00:00';
$rows=$owned->calendarReminders($from,$until,false);
essentialCheck(count($rows)===2,'calendar includes owned creator and recipient rows, not unrelated or historical lineage');
$before=$db->query('SELECT COUNT(*) AS n FROM '.$p.'issuers_audit')->row()->n;
$db->query('UPDATE '.$p.'company_reminders SET isnotified=1 WHERE description=?',['Assigned to other']);
essentialCheck(count($owned->calendarReminders($from,$until,true))===1,'hide notified has a real calendar consumer');
essentialCheck(count($owned->calendarReminders('2026-10-25 14:00:00',$until,false))===1,'calendar half-open range uses real reminder dates');
essentialCheck((new NativeCompanyAuxiliary($db,$p,41,12))->calendarReminders($from,$until,false)===[],'named calendar does not inherit primary reminders');
settingThrows(fn()=>$owned->calendarReminders($until,$from,false),'native_calendar_range_invalid','reversed interval denied');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=14 AND feature='invoices'",[$principal]);
essentialCheck($owned->calendarReminders($from,$until,false)===[],'revoked resource hides private reminder label');
essentialCheck($db->query('SELECT COUNT(*) AS n FROM '.$p.'issuers_audit')->row()->n===$before,'calendar read never creates notifications or audit mutations');
echo "Calendar reminders: 7 real SQLite range/ACL/visibility checks passed.\n";
