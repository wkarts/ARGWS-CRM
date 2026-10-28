<?php
require __DIR__ . '/native_task_ticket_form.php';
require_once APPPATH . 'services/NativeCompanyTaskIndexProjection.php';
$projection = new NativeCompanyTaskIndexProjection($db, $p);
$records = $projection->read($a, 7);
$kinds = array_column($records, 'source_kind');
essentialCheck(in_array('company_task', $kinds, true) && in_array('company_standalone_task', $kinds, true), 'Task projection composes project and standalone owned sources');
$named = array_column($records, null, 'id');
essentialCheck(isset($named[$new]) && $named[$new]['row']['name'] === 'Updated ticket-linked task', 'Ticket-linked task resolves only its owned parent and actual data');
essentialCheck($projection->readOne($a,7,$new)===$named[$new], 'Single-task projection matches authorized table facts without hydrating other tasks');
essentialThrows(fn()=>$projection->readOne($b,7,$new),'native_company_task_denied','Single-task projection denies another company');
foreach ($records as $record) essentialCheck((int) $record['row']['issuer_id'] === $a && $record['reference']['canonical_id'] === $record['id'], 'Every projected row retains the exact issuer and canonical identity');
$other = $projection->read($b, 7);
foreach ($other as $record) essentialCheck((int) $record['row']['issuer_id'] === $b && !isset($named[$record['id']]), 'Second company cannot project identities from the first company');
essentialThrows(fn() => $projection->read(1, 7), 'native_task_main_legacy_projection_required', 'Secondary-only reader refuses to omit principal legacy tasks');
essentialCheck((new IssuerResourceAccess($db, $p))->can($a, 92, 'tasks', 'view'), 'Ordinary secondary actor initially has task view');
$db->query('UPDATE ' . $p . 'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=92', [$a]);
essentialThrows(fn() => $projection->read($a, 92), 'native_company_task_denied', 'Revoked issuer membership blocks the projection');
echo 'Scoped main task source projection: ' . $GLOBALS['essentialChecks'] . " cumulative checks passed; table/rendering/HTTP pending.\n";
