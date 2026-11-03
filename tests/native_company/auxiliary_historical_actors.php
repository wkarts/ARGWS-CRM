<?php
require __DIR__.'/primary_notes_reminders.php';
$db->query("DELETE FROM {$p}issuers_resource_permissions WHERE staff_id=7");
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE staff_id=7");
$members=$named->members();essentialCheck(in_array('7',array_map('strval',array_column($members,'staffid')),true),'active proven installation master remains a valid native recipient without membership view permission');
$db->query("UPDATE {$p}staff SET admin=0 WHERE staffid=7");
essentialCheck(!in_array('7',array_map('strval',array_column($named->members(),'staffid')),true),'revoked master administrative identity is not offered as recipient');
$db->query("UPDATE {$p}staff SET admin=1 WHERE staffid=7");
$noteId=NativeCompanyResourceId::decode('note',$note)['source_id'];
$db->query("UPDATE {$p}company_notes SET addedfrom=999999 WHERE issuer_id=? AND id=?",[$principal,$noteId]);
$rows=$ui->notes('invoice',42);$found=array_values(array_filter($rows,static fn($r)=>(string)$r['id']===$note));
essentialCheck(count($found)===1&&$found[0]['description']==='Owned note'&&$found[0]['firstname']===''&&$found[0]['lastname']==='','historical note remains visible after author identity is removed, without borrowing another staff name');
echo "Native auxiliary recipient authority and historical author preservation: passed.\n";
