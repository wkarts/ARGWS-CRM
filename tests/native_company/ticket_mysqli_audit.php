<?php
/** PDO-backed real writes with the mysqli insert_id read behavior explicitly reproduced. */
require __DIR__.'/ticket_create.php';
$wrapped->resetInsertIdAfterRead=true;
$before=(int)$db->query('SELECT COUNT(*) n FROM tblcompany_tickets')->row_array()['n'];
$created=$service->createNative(2,17,$input,hash('sha256','mysqli-audit-readback'));
kc((int)$db->query('SELECT COUNT(*) n FROM tblcompany_tickets')->row_array()['n']===$before+1,'Audit readback preserves successful creation when SELECT resets mysqli insert_id');
kc($service->createNative(2,17,$input,hash('sha256','mysqli-audit-readback'))===$created,'Same real command retries without a second ticket');
echo "Ticket audit mysqli behavior: creation, exact readback and durable replay passed.\n";
