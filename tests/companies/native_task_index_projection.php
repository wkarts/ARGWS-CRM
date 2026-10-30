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
// Restore real historical table shapes now consumed by the principal union.
foreach(['tasks','task_assigned','task_followers','taskstimers','tags','taggables','customfields','customfieldsvalues']as$table)if(!$db->table_exists($p.$table)){
    $seed=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tbl'.$table.'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$seed,$ddl);if(!$ddl)throw new RuntimeException('Missing native table definition');
    $sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$ddl[0]);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [a-zA-Z0-9_]+ COLLATE [a-zA-Z0-9_]+/','',$sql);$db->query($sql);
}
unset($db->data_cache['table_names']);$legacyId=NativeCompanyResourceId::decode('task',$new)['source_id'];
$db->query('INSERT INTO '.$p.'tasks(id,name,description,priority,dateadded,startdate,status,addedfrom,rel_type,rel_id,billable,billed) VALUES(?,?,?,2,?,?,1,7,NULL,NULL,1,0)',[$legacyId,'Preserved primary task','Actual historical values','2020-01-01 12:00:00','2020-01-01']);
$primaryProjection=$projection->read(1,7);
essentialCheck(array_column($primaryProjection,'id')===[(string)$legacyId]&&$primaryProjection[0]['source_kind']==='task'&&$primaryProjection[0]['row']['name']==='Preserved primary task'&&$primaryProjection[0]['fields']['start_date']==='2020-01-01','Principal projection preserves historical ID/date and excludes secondary task with coincident numeric ID');
essentialCheck($projection->read($a,7)===$records,'Introducing a coincident primary ID cannot change the secondary projection');

essentialCheck((new IssuerResourceAccess($db, $p))->can($a, 92, 'tasks', 'view'), 'Ordinary secondary actor initially has task view');
$db->query('UPDATE ' . $p . 'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=92', [$a]);
essentialThrows(fn() => $projection->read($a, 92), 'native_company_task_denied', 'Revoked issuer membership blocks the projection');
echo 'Scoped main task source projection: ' . $GLOBALS['essentialChecks'] . " cumulative checks passed; table/rendering/HTTP pending.\n";
// A cadeia de fixtures continua no mesmo processo; as asserções acima já consumiram o trace SQL e não precisam retê-lo para os filhos.
$db->queries=[];
