<?php
require __DIR__.'/native_invoice_draft_creation_tags.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommands.php';
$field=NativeCompanyResourceId::encode('company_custom_field',$db->query("SELECT id FROM {$p}company_custom_fields WHERE issuer_id=? AND slug='draft_line_metadata'",[$a])->row_array()['id']);
NativeCompanyInvoiceCommandSchema::ensure($db,$p);$commands=new NativeCompanyInvoiceCommands($db,$p);
$metadata=['invoice_fields'=>[],'line_fields'=>[[$field=>'Complete submitted line metadata'],[]],'tags'=>['Complete submitted tag']];$key=hash('sha256','complete-native-command');
$beforeInvoices=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
$result=$commands->createDraft($a,12,$draftInput,$metadata,$key);
essentialCheck($result['draft_id']>0&&$result['revision']===1&&count($result['line_ids'])===2,'complete create command returns real draft and line identities');
essentialCheck($fields->values($a,12,'items',$result['line_ids'][0])[$field]==='Complete submitted line metadata','complete command stores actual submitted metadata');
essentialCheck(array_column($tags->read($a,12,'invoice',$result['canonical_id'])['tags'],'name')===['Complete submitted tag'],'complete command stores actual submitted native tags');
essentialCheck($commands->createDraft($a,12,$draftInput,$metadata,$key)===$result,'exact full-submission replay returns original durable result');
$changed=$metadata;$changed['line_fields'][0][$field]='Divergent same key';essentialThrows(fn()=>$commands->createDraft($a,12,$draftInput,$changed,$key),'native_invoice_command_conflict','financial payload unchanged but metadata divergence conflicts');
$badTags=$metadata;$badTags['tags']=['duplicate','DUPLICATE'];$failedKey=hash('sha256','complete-native-command-failure');
essentialThrows(fn()=>$commands->createDraft($a,12,$draftInput,$badTags,$failedKey),'native_tags_invalid','late tag validation fails complete submission');
essentialCheck(!$db->query('SELECT request_key FROM '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=?',[$failedKey])->row_array()&&!$db->query('SELECT id FROM '.$p.'company_invoice_drafts WHERE origin_key=?',[$failedKey])->row_array(),'late metadata failure rolls back command receipt and draft together');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$beforeInvoices,'save private command never issues or changes financial invoices');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$a]);essentialThrows(fn()=>$commands->createDraft($a,12,$draftInput,$metadata,$key),'native_custom_field_denied','revocation denies even exact command replay');
echo 'Complete native draft submission: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$db->query("UPDATE {$p}issuers_staff SET can_view=1 WHERE issuer_id=? AND staff_id=12",[$a]);
essentialThrows(fn()=>$commands->createDraft($a,7,$draftInput,$metadata,$key),'native_invoice_command_conflict','same body/key cannot be replayed by another actor');
$reordered=['tags'=>$metadata['tags'],'line_fields'=>$metadata['line_fields'],'invoice_fields'=>$metadata['invoice_fields']];essentialCheck($commands->createDraft($a,12,$draftInput,$reordered,$key)===$result,'associative metadata order is not a different submission');
$db->query('UPDATE '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' SET result_hash=? WHERE request_key=?',[str_repeat('0',64),$key]);
essentialThrows(fn()=>$commands->createDraft($a,12,$draftInput,$metadata,$key),'issuer_native_invoice_command_unconfirmed','corrupt completion receipt never falls through to creating another draft');
echo 'Complete command actor/order/corruption guards: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
if($db->driver()==='sqlite'){
 $finalKey=hash('sha256','complete-native-command-no-ack');$draftsBefore=$db->query('SELECT * FROM '.$p.'company_invoice_drafts ORDER BY id')->result_array();
 $db->query('CREATE TRIGGER ignore_native_command_ack BEFORE UPDATE ON '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' BEGIN SELECT RAISE(IGNORE); END');
 essentialThrows(fn()=>$commands->createDraft($a,12,$draftInput,$metadata,$finalKey),'issuer_native_invoice_command_unconfirmed','ignored completion UPDATE cannot commit a pending native command');
 essentialCheck($db->query('SELECT * FROM '.$p.'company_invoice_drafts ORDER BY id')->result_array()===$draftsBefore&&!$db->query('SELECT request_key FROM '.$p.NativeCompanyInvoiceCommandSchema::TABLE.' WHERE request_key=?',[$finalKey])->row_array(),'acknowledgement failure rolls back draft and request row together');$db->query('DROP TRIGGER ignore_native_command_ack');
}
echo 'Complete command atomic acknowledgement: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
