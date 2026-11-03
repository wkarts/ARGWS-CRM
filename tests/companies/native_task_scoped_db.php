<?php
require __DIR__.'/native_task_index_controller.php';
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=92',[$a]);
$scope=new NativeCompanyTaskScopedDb($db,$p);$acl=new IssuerResourceAccess($scope,$p);
essentialCheck($acl->can($a,92,'tasks','view'),'Ordinary issuer member initially has tasks view');
$before=count($db->queries);for($n=0;$n<25;$n++)essentialCheck($acl->can($a,92,'tasks','view'),'Repeated scoped checks keep same authority');
essentialCheck(count($db->queries)===$before,'Repeated access facts do not issue identical SQL during this one read');
$lock='SELECT id FROM '.$p.'issuers_registry WHERE id=? FOR UPDATE';$before=count($db->queries);$scope->query($lock,[$a])->result_array();$scope->query($lock,[$a])->result_array();essentialCheck(count($db->queries)===$before+2,'Locking selects always execute on the underlying connection');
essentialThrows(fn()=>$scope->query('DELETE FROM '.$p.'company_standalone_tasks WHERE issuer_id=?',[$a]),'native_task_index_read_only','Read scope cannot execute a task mutation');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=92',[$a]);
essentialThrows(fn()=>$scope->confirm(),'native_task_index_changed','Membership revocation is detected before scoped results may be emitted');
$fresh=new NativeCompanyTaskScopedDb($db,$p);essentialCheck(!(new IssuerResourceAccess($fresh,$p))->can($a,92,'tasks','view'),'Fresh operation does not inherit the earlier authority cache');
$db->query('UPDATE '.$p.'issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=92',[$a]);
$db->trans_begin();try{essentialThrows(fn()=>new NativeCompanyTaskScopedDb($db,$p),'issuer_outer_transaction_not_allowed','Reader refuses an existing caller transaction');}finally{$db->trans_rollback();}
echo 'Scoped task ACL observations: '.$GLOBALS['essentialChecks']." cumulative checks passed; no HTTP/MySQL assertion.\n";
