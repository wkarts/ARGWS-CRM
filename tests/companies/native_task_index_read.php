<?php
require __DIR__ . '/native_task_relation_option.php';
require_once APPPATH . 'services/NativeCompanyTaskIndexRead.php';

$db->query('UPDATE ' . $p . 'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=51', [$a]);
$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,51,'projects','view')", [$a]);
$index = new NativeCompanyTaskIndexRead($db, $p);
$records = $index->read($a, 51);
$names = array_column($records, 'name'); sort($names);
essentialCheck($names === ['Project A', 'Standalone A'], 'Secondary index returns only its own authorized sources');
$ids = array_column($records, 'id');
essentialCheck(count(array_unique($ids)) === 2 && in_array($owned, $ids, true) && in_array($standalone, $ids, true), 'Equal numeric task IDs retain distinct source identities');
$other = array_column($index->read($b, 51), 'name'); sort($other);
essentialCheck($other === ['Project B', 'Standalone B'], 'Other company receives only its own task identities');
$primary = $index->read(1, 7);
essentialCheck(array_column($primary, 'name') === ['Legacy only'], 'Principal legacy list excludes historic tasks tied to another company customer');
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE issuer_id=? AND staff_id=51 AND feature='projects'", [$a]);
$start = count($db->queries);
essentialCheck(array_column($index->read($a, 51), 'name') === ['Standalone A'], 'Loss of project access leaves authorized standalone tasks visible');
essentialCheck(!array_filter(array_slice($db->queries, $start), static fn($q) => str_contains($q[0], ' AS label FROM ' . $p . 'company_tasks')), 'Project labels are not hydrated when project access is absent');
$db->query('UPDATE ' . $p . 'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=51', [$a]);
essentialThrows(fn() => $index->read($a, 51), 'native_company_task_denied', 'Revoked membership denies the index');
echo 'Native main task identity reader: ' . $GLOBALS['essentialChecks'] . " cumulative checks passed; full table, modal and HTTP are not yet composed.\n";
