<?php
/** Actual sales writer/review/portal/PDF/conversion against disposable storage. */
require __DIR__.'/sales_preferences.php';
CompanySalesMutationsSchema::ensure($db,$p);$start=$GLOBALS['essentialChecks'];
$settings->mutate($a,7,'update','predefined_clientnote_invoice','Nota padrão da fatura');
$fields=['adminnote'=>'SIGILO-INTERNO','terms'=>'Termos informados no orçamento','billing_street'=>'Rua de faturamento','billing_city'=>'Salvador',
 'billing_state'=>'BA','billing_zip'=>'40000-000','billing_country'=>0,'shipping_street'=>'Rua de entrega','shipping_city'=>'Salvador',
 'shipping_state'=>'BA','shipping_zip'=>'40000-001','shipping_country'=>0,'include_shipping'=>1,'show_shipping_on_invoice'=>1,'show_quantity_as'=>2,'sale_agent'=>0];
$fields=CompanyInvoiceNativeFields::input($fields);
$payload=array_replace($salesInput,['client_id'=>$ca,'due_date'=>null,'native_fields'=>$fields]);$key=hash('sha256','estimate-native-field-creation');
$id=$sales->create($a,7,'estimates',$payload,$key);$initial=$sales->document($a,7,$id);
essentialCheck($initial['payload']['due_date']===null&&$initial['payload']['native_fields']===$fields,'created quote persists optional date and all typed native fields');
essentialCheck($initial['terms']===$fields['terms'],'individual estimate terms override defaults in staff DTO');
essentialCheck($sales->create($a,7,'estimates',$payload,$key)===$id,'same source request replays without a second document');
essentialThrows(fn()=>$sales->document($b,7,$id),'company_sales_denied','other company cannot hydrate native fields');
$bad=$payload;$bad['native_fields']['sale_agent']=12;essentialThrows(fn()=>$sales->create($a,7,'estimates',$bad,hash('sha256','estimate-native-foreign-agent')),'native_estimate_agent_denied','foreign/ungranted staff cannot be assigned');
$bad=$payload;$bad['native_fields']['billing_country']=2147483647;essentialThrows(fn()=>$sales->create($a,7,'estimates',$bad,hash('sha256','estimate-native-invalid-country')),'native_estimate_country_denied','unknown country cannot be stored');
$bad=$payload;$bad['native_fields']['other_company']='82';essentialThrows(fn()=>$sales->create($a,7,'estimates',$bad,hash('sha256','estimate-native-unknown')),'company_invoice_native_fields_invalid','undeclared field denied');
$bad=$payload;essentialThrows(fn()=>$sales->create($a,7,'proposals',$bad,hash('sha256','proposal-native-fields-not-ready')),'company_sales_invalid','estimate fields cannot open unreviewed proposal semantics');
$review=$sales->review($a,7,$id,'edit');$edited=$payload;$edited['native_fields']['terms']='Termos revisados';$edited['native_fields']['billing_city']='Feira de Santana';
$sales->edit($a,7,$id,$edited,['source_revision'=>$review['source_revision'],'reason'=>'Revisão sintética','edit_confirmed'=>'1'],hash('sha256','estimate-native-fields-edit'));
$after=$sales->document($a,7,$id);essentialCheck($after['terms']==='Termos revisados'&&$after['payload']['native_fields']['billing_city']==='Feira de Santana','reviewed edit persists exact new fields');
$history=$db->query('SELECT before_payload_json,after_payload_json FROM '.$p.'company_sales_revisions WHERE issuer_id=? AND document_id=?',[$a,$id])->row_array();
essentialCheck(str_contains($history['before_payload_json'],'Termos informados no orçamento')&&str_contains($history['after_payload_json'],'Termos revisados'),'reviewed history preserves old and new values');
$review=$sales->review($a,7,$id,'copy');$copy=$sales->copy($a,7,$id,$review['payload'],['source_revision'=>$review['source_revision'],'destination_revision'=>$review['destination_revision'],'reason'=>'Cópia sintética','copy_confirmed'=>'1'],hash('sha256','estimate-native-fields-copy'));
essentialCheck($sales->document($a,7,$copy)['payload']['native_fields']===$edited['native_fields'],'reviewed copy preserves native fields');
$sales->decide($a,7,$id,'accepted');$portal=$portalSales->document($sessionA,$a,$id);
essentialCheck(!isset($portal['payload']['native_fields']['adminnote'],$portal['payload']['native_fields']['sale_agent'])&&$portal['payload']['native_fields']['terms']==='Termos revisados','portal retains public fields but strips administrative note and staff identity');
$pdf=CompanySalesPdf::prepare($portal)->Output('','S');essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'actual portal PDF renders accepted native payload');
$evidence=getenv('CRM_ESTIMATE_PDF_EVIDENCE');if(is_string($evidence)&&$evidence!==''){if(!preg_match('~^/tmp/native-estimate-[A-Za-z0-9_-]+\\.pdf$~D',$evidence))throw new RuntimeException('Invalid synthetic evidence path');file_put_contents($evidence,$pdf);}
$invoice=$sales->convert($a,7,$id);$row=$db->query('SELECT duedate,terms,clientnote,adminnote,billing_street,billing_city,shipping_street,include_shipping,show_shipping_on_invoice FROM '.$p.'invoices WHERE issuer_id=? AND id=?',[$a,$invoice])->row_array();
essentialCheck($row['duedate']===null&&$row['terms']==='Termos fatura A'&&$row['clientnote']==='Nota padrão da fatura'&&$row['adminnote']===''&&$row['billing_city']==='Feira de Santana'&&$row['shipping_street']==='Rua de entrega'&&(int)$row['include_shipping']===1&&(int)$row['show_shipping_on_invoice']===1,'conversion transfers billing/shipping and uses the invoice own terms and client note while clearing admin note');
essentialCheck($sales->convert($a,7,$id)===$invoice,'conversion replay does not issue again');
// An interrupted conversion resumes using frozen invoice defaults, never current settings.
$late=$sales->create($a,7,'estimates',$payload,hash('sha256','estimate-native-late-conversion'));$sales->decide($a,7,$late,'accepted');
$beforeCount=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$db->failOn="SET state='converted',invoice_id";
essentialThrows(fn()=>$sales->convert($a,7,$late),'issuer_storage_failed','late source-finalization failure retains committed invoice receipt');$db->failOn=null;
$settings->mutate($a,7,'update','predefined_clientnote_invoice','Alterada depois da emissão');$settings->updateBatch($a,7,['predefined_terms_invoice'=>'Termos alterados depois da emissão']);
$resumed=$sales->convert($a,7,$late);$lateRow=$db->query('SELECT terms,clientnote,adminnote FROM '.$p.'invoices WHERE issuer_id=? AND id=?',[$a,$resumed])->row_array();
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeCount+1&&$lateRow['terms']==='Termos fatura A'&&$lateRow['clientnote']==='Nota padrão da fatura'&&$lateRow['adminnote']==='','recovery reuses original receipt and frozen invoice defaults after settings change');
echo 'Native estimate fields persistence/edit/portal/PDF/conversion: '.($GLOBALS['essentialChecks']-$start)." added checks.\n";
