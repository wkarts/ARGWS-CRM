<?php
/** Real canonical invoice + item metadata writer: late failures, source race and resumable receipt checks. */
require __DIR__.'/native_sales_original_form.php';
$transferStart=$GLOBALS['essentialChecks'];
foreach(['estimates'=>'estimate','proposals'=>'proposal']as$kind=>$type){
    $form=$originalForms[$kind];$new=function()use($form,$kind,$type,$a,$ca,$originalHeaders,$originalLineField,$originalChoiceField,$sales){$data=$form->data($a,7,'',$ca);$wire=originalSalesWire($kind,$data,$ca,$originalHeaders[$type],$originalLineField,$originalChoiceField);$saved=$form->save($a,7,$wire);$sales->decide($a,7,$saved['source_id'],'accepted');return$saved;};
    $source=$new();$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$db->failOn="SET state='converted',invoice_id";
    try{essentialThrows(fn()=>$sales->convert($a,7,$source['source_id']),'issuer_storage_failed',$kind.' source finalization failure occurs after the canonical invoice receipt');}finally{$db->failOn=null;}
    $key=hash('sha256','company-sales-invoice:'.$a.':'.$source['source_id']);$invoice=(int)$db->query('SELECT invoice_id FROM '.$p.'company_invoice_requests WHERE request_key=?',[$key])->row()->invoice_id;$lines=$db->query("SELECT id FROM {$p}itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id",[$invoice])->result_array();
    essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$before+1&&$sales->document($a,7,$source['source_id'])['state']==='converting'&&count($lines)===2,$kind.' failed finalization leaves exactly one invoice and both real line identities for recovery');
    $field=NativeCompanyResourceId::decode('custom_field',$originalLineField)['source_id'];$db->query("UPDATE {$p}company_custom_field_values SET value=? WHERE issuer_id=? AND source_kind='invoice_item' AND source_id=? AND field_id=?",['Tampered destination',$a,$lines[0]['id'],$field]);
    essentialThrows(fn()=>$sales->convert($a,7,$source['source_id']),'native_sales_item_transfer_conflict',$kind.' recovery refuses altered destination line metadata');
    $db->query("UPDATE {$p}company_custom_field_values SET value=? WHERE issuer_id=? AND source_kind='invoice_item' AND source_id=? AND field_id=?",['Private line value',$a,$lines[0]['id'],$field]);
    essentialCheck($sales->convert($a,7,$source['source_id'])===$invoice&&$sales->convert($a,7,$source['source_id'])===$invoice,$kind.' recovery and committed replay preserve the exact invoice');
    essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_sales_item_transfer_completed'",[$a,$source['source_id']])->row()->n===1,$kind.' recovery never duplicates the line transfer commitment');
    if($db->driver()==='sqlite'){
        $race=$new();$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;
        $db->query("CREATE TRIGGER {$p}sales_item_source_race AFTER INSERT ON {$p}company_custom_field_values WHEN NEW.source_kind='invoice_item' BEGIN UPDATE {$p}company_sales_documents SET input_hash='".str_repeat('0',64)."' WHERE issuer_id=NEW.issuer_id AND id=".(int)$race['source_id']."; END");
        try{essentialThrows(fn()=>$sales->convert($a,7,$race['source_id']),'issuer_sales_write_unconfirmed',$kind.' source mutation during real line-value insert vetoes the invoice transaction');}finally{$db->query('DROP TRIGGER '.$p.'sales_item_source_race');}
        essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$before&&$sales->document($a,7,$race['source_id'])['state']==='converting'&&(int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='native_sales_item_transfer_completed'",[$a,$race['source_id']])->row()->n===0,$kind.' race rolls back source tamper, invoice, fields and transfer audit together');
        essentialCheck($sales->convert($a,7,$race['source_id'])>0,$kind.' same retained claim recovers after the concurrent-write fault is removed');
    }
}
echo 'Original Sales canonical item transfer failure/recovery: '.($GLOBALS['essentialChecks']-$transferStart).' added checks ('.$db->driver().').'.PHP_EOL;
