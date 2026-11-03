<?php
require __DIR__.'/native_file_message.php';
$start=$GLOBALS['essentialChecks'];$table=$p.'company_native_file_mail_requests';
NativeCompanyFileMessageSchema::ensure($db,$p);essentialCheck(true,'valid schema replay preserves receipt rows');
$db->trans_begin();essentialThrows(fn()=>NativeCompanyFileMessageSchema::ensure($db,$p),'issuer_outer_transaction_not_allowed','schema helper refuses caller transaction before DDL');$db->trans_rollback();
if($db->driver()!=='sqlite')throw new RuntimeException('sqlite_metadata_fixture_required');
$schema=$db->query("SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$table])->row()->sql;
$db->query('DELETE FROM '.$table);$db->query('DROP TABLE '.$table);
foreach([
 str_replace('issuer_id INTEGER NOT NULL','issuer_id INTEGER NOT NULL DEFAULT 1',$schema),
 str_replace('request_key TEXT NOT NULL','request_key TEXT',$schema),
 str_replace('UNIQUE(issuer_id,request_key)','UNIQUE(request_key)',$schema),
 str_replace('REFERENCES '.$p.'mail_queue(id)','REFERENCES '.$p.'contacts(id)',$schema),
 str_replace('REFERENCES '.$p.'mail_queue(id)','REFERENCES '.$p.'mail_queue(id) ON DELETE CASCADE',$schema),
]as$sql){$db->query($sql);essentialThrows(fn()=>NativeCompanyFileMessageSchema::assertValid($db,$p),'native_file_mail_schema_invalid','mutated metadata is rejected');$db->query('DROP TABLE '.$table);}
$db->query($schema);NativeCompanyFileMessageSchema::assertValid($db,$p);essentialCheck(true,'exact metadata restored');
echo 'Native file mail schema: '.($GLOBALS['essentialChecks']-$start).' actual SQLite checks passed.'.PHP_EOL;
