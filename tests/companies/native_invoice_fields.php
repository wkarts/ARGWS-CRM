<?php
/** Native presentation fields through canonical issue/edit/copy/draft writers. No new monetary algorithm. */
require __DIR__.'/invoice_drafts.php';
$nativeInput = $legacyInput;
$nativeInput['client_id'] = $ca;
$nativeInput['native_fields'] = [
    'adminnote'=>'Internal native note', 'terms'=>"Native terms <script>alert(1)</script>\nSecond line",
    'billing_street'=>'Native billing street', 'billing_city'=>'Native billing city', 'billing_state'=>'BA', 'billing_zip'=>'00000-000', 'billing_country'=>0,
    'shipping_street'=>'Native shipping street', 'shipping_city'=>'Native shipping city', 'shipping_state'=>'BA', 'shipping_zip'=>'00000-001', 'shipping_country'=>0,
    'include_shipping'=>1, 'show_shipping_on_invoice'=>1, 'show_quantity_as'=>2,
];
$nativeKey = hash('sha256', 'native-fields-invoice');
$nativeId = $s->create($a, 7, $nativeInput, $nativeKey);
essentialCheck($s->create($a, 7, $nativeInput, $nativeKey) === $nativeId, 'native fields preserve exact canonical request retry');
$changedNative = $nativeInput; $changedNative['native_fields']['adminnote'] = 'Different command';
essentialThrows(fn()=>$s->create($a,7,$changedNative,$nativeKey), 'company_invoice_request_conflict', 'same request cannot silently discard changed native metadata');
$nativeRow = $db->query('SELECT * FROM '.$p.'invoices WHERE id=?', [$nativeId])->row();
essentialCheck(CompanyInvoiceNativeFields::fromRecord((array)$nativeRow) === CompanyInvoiceNativeFields::input($nativeInput['native_fields']), 'all admitted native columns read back exactly');
$nativeDto = $s->document($a, 7, $nativeId);
$nativeHtml = (new Issuer_invoice_pdf($nativeDto))->html();
essentialCheck($nativeDto->data()['terms'] === $nativeInput['native_fields']['terms'] && $nativeDto->data()['quantity_mode'] === 2, 'authorized PDF DTO uses document terms and quantity mode');
essentialCheck(str_contains($nativeHtml, '&lt;script&gt;') && !str_contains($nativeHtml, '<script>') && !str_contains($nativeHtml, 'Internal native note'), 'PDF escapes document terms and never exposes staff-only adminnote');
$nativeSnapshot = $nativeDto->data()['identity'];
$nativeRevision = $s->revision($a, 7, $nativeId);
$editNative = $nativeInput; $editNative['native_fields']['terms'] = 'Changed document terms';
$db->failOn = 'INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$s->change($a,7,$nativeId,$nativeRevision,$editNative), 'issuer_storage_failed', 'late audit failure rolls back all native field updates');
$db->failOn = null;
essentialCheck($s->document($a,7,$nativeId)->data()['terms'] === $nativeInput['native_fields']['terms'], 'failed edit did not change terms');
$s->change($a,7,$nativeId,$nativeRevision,$editNative);
essentialCheck($s->document($a,7,$nativeId)->data()['identity'] === $nativeSnapshot, 'document edit does not rewrite captured issuer identity');
essentialThrows(fn()=>$s->change($a,7,$nativeId,$nativeRevision,$nativeInput), 'company_invoice_stale', 'native fields participate in optimistic revision');
foreach ([['issuer_id'=>$b], ['total'=>'1.00'], ['status'=>2], ['paymentmode'=>'bank'], ['show_quantity_as'=>0], ['billing_country'=>999999999], ['adminnote'=>['bad']]] as $fields) {
    $bad = $nativeInput; $bad['native_fields'] = $fields;
    essentialThrows(fn()=>$s->create($a,7,$bad,hash('sha256',json_encode($fields))), 'company_invoice_native_fields_invalid', 'native metadata cannot smuggle ownership, financial state or invalid fields');
}
$copyPreview = $copy->preview($a,7,$nativeId);
$review = ['source_revision'=>$copyPreview['revision'], 'date'=>'2026-12-15', 'due_date'=>'2027-01-15', 'note'=>'Native copied note', 'confirm_amounts'=>'1'];
$copyKey = hash('sha256','native-fields-copy');
$copied = $copy->create($a,7,$nativeId,$review,$copyKey);
essentialCheck($copy->create($a,7,$nativeId,$review,$copyKey) === $copied, 'native copy receipt remains idempotent');
$copyRow = $db->query('SELECT * FROM '.$p.'invoices WHERE id=?', [$copied['invoice_id']])->row();
essentialCheck(CompanyInvoiceNativeFields::fromRecord((array)$copyRow) === CompanyInvoiceNativeFields::input($editNative['native_fields']), 'new copy preserves reviewed native fields');
$draftNativeId = $drafts->create($a,7,$nativeInput,hash('sha256','native-fields-draft'));
essentialCheck($drafts->document($a,7,$draftNativeId)['payload']['native_fields'] === CompanyInvoiceNativeFields::input($nativeInput['native_fields']), 'private draft keeps full native field payload');
$draftNativeInvoice = $drafts->issue($a,7,$draftNativeId,1,hash('sha256','native-fields-draft-issue'));
essentialCheck($s->document($a,7,$draftNativeInvoice)->data()['terms'] === $nativeInput['native_fields']['terms'], 'explicit draft issue enters the same canonical native field writer');
$copyDraftId = $drafts->copyInvoice($a,7,$nativeId,$review,hash('sha256','native-fields-copy-draft'));
essentialCheck($drafts->document($a,7,$copyDraftId)['payload']['native_fields'] === CompanyInvoiceNativeFields::input($editNative['native_fields']), 'copy to private draft also preserves native presentation fields');
if($db->driver()==='mysql'){
    $trigger=$p.'native_fields_late_mutation';
    $beforeTrigger=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
    $db->query("CREATE TRIGGER {$trigger} AFTER INSERT ON {$p}issuers_audit FOR EACH ROW BEGIN IF NEW.event='company_invoice_created' THEN UPDATE {$p}invoices SET adminnote='Synthetic late trigger mutation' WHERE id=NEW.subject_id; END IF; END");
    try{
        essentialThrows(fn()=>$s->create($a,7,$nativeInput,hash('sha256','native-fields-late-trigger')), 'issuer_invoice_write_unconfirmed', 'final native readback detects late audit trigger mutation');
        essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$beforeTrigger, 'late metadata mutation rolls back complete canonical creation');
    }finally{$db->query('DROP TRIGGER '.$trigger);}
}
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array() === $legacyBefore, 'all native metadata operations leave NULL historical invoice intact');
echo 'Native invoice metadata issue/edit/draft/copy/rollback/retry: '.$GLOBALS['essentialChecks']." cumulative checks.\n";
