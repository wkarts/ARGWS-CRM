<?php
require __DIR__.'/native_contacts.php';
require APPPATH.'services/NativeCompanyContactRetention.php';
$start=$GLOBALS['essentialChecks'];$client=101;$input=$values;$input['email']='delete-scoped@example.invalid';$input['is_primary']=0;$input['password']='synthetic-delete-password';
$subject=$native->save($a,7,$client,$input,fn($s)=>password_hash($s,PASSWORD_DEFAULT));$before=$native->get($a,7,$client,$subject);
essentialThrows(fn()=>$native->delete($b,7,$client,$subject,$before['revision']),'company_client_access_denied','foreign company cannot delete owned contact');
essentialThrows(fn()=>$native->delete($a,7,$client,$subject,str_repeat('0',64)),'company_client_stale','delete requires exact contact revision');
$db->query("CREATE TABLE {$p}company_native_consents(id INTEGER PRIMARY KEY,contact_id INT)");$db->query("INSERT INTO {$p}company_native_consents VALUES(1,?)",[$subject]);
essentialThrows(fn()=>$native->delete($a,7,$client,$subject,$before['revision']),'company_contact_history_retained','consent authorship blocks destructive delete');
$db->query("DELETE FROM {$p}company_native_consents");
$db->query("CREATE TABLE {$p}company_project_discussion_requests(actor_id INT,author_type TEXT)");$db->query("INSERT INTO {$p}company_project_discussion_requests VALUES(?,'contact')",[$subject]);
essentialThrows(fn()=>$native->delete($a,7,$client,$subject,$before['revision']),'company_contact_history_retained','polymorphic contact actor request remains protected');
$db->query("UPDATE {$p}company_project_discussion_requests SET author_type='staff'");
$db->query("CREATE TRIGGER {$p}ignore_contact_delete_audit BEFORE INSERT ON {$p}issuers_audit BEGIN SELECT RAISE(IGNORE); END");
essentialThrows(fn()=>$native->delete($a,7,$client,$subject,$before['revision']),'issuer_client_write_unconfirmed','delete requires durable audit confirmation');essentialCheck($native->get($a,7,$client,$subject)===$before,'audit failure rolls contact and permissions back');
$db->query("DROP TRIGGER {$p}ignore_contact_delete_audit");$native->delete($a,7,$client,$subject,$before['revision']);essentialThrows(fn()=>$native->get($a,7,$client,$subject),'company_client_access_denied','unreferenced owned contact delete completes');
essentialCheck((int)$native->get($b,7,202,$nb)['contact']['active']===1,'foreign issuer contact unchanged');
echo 'Native contact deletion/retention: '.($GLOBALS['essentialChecks']-$start).' checks passed.'.PHP_EOL;
