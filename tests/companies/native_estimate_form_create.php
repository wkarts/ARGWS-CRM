<?php
// Real original-wire parser and existing sales writer; no route admission asserted.
require __DIR__.'/native_estimate_persisted_fields.php';
require_once APPPATH.'services/NativeCompanyEstimateForm.php';$start=$GLOBALS['essentialChecks'];
$form=new NativeCompanyEstimateForm($db,$p);$sequence=new NativeCompanySalesSequencePreferences($db,$p);$selection=$sequence->readForCreation($a,7,'estimates');
$wire=['_native_estimate_id'=>'','_native_estimate_revision'=>'','_native_estimate_request_key'=>hash('sha256','native-estimate-form-create'),
 '_native_estimate_number_revision'=>$selection['revision'],'number'=>str_pad($selection['next_number'],6,'0',STR_PAD_LEFT),'clientid'=>(string)$ca,'project_id'=>'',
 'currency'=>'1','date'=>'2026-01-31','expirydate'=>'','status'=>'1','reference_no'=>'REF-LOCAL','sale_agent'=>'',
 'newitems'=>['8'=>['order'=>'1','description'=>'Produto original','long_description'=>'Detalhes','qty'=>'2','rate'=>'50','unit'=>'hora','taxname'=>[]]],
 'discount_percent'=>'0','discount_total'=>'0.00','discount_type'=>'','adjustment'=>'','subtotal'=>'100.00','total'=>'100.00',
 'tags'=>'','clientnote'=>'Nota original','terms'=>'Termos originais','adminnote'=>'Privado','billing_street'=>'Rua teste','show_quantity_as'=>'1'];
$created=$form->create($a,7,$wire);$id=$created['source_id'];$doc=$sales->document($a,7,$id);
essentialCheck($created['action']==='save'&&$doc['number']===(string)$selection['next_number']||$created['action']==='save'&&(int)$doc['number']===(int)$selection['next_number'],'original wire uses selected real number');
essentialCheck($doc['payload']['native_fields']['terms']==='Termos originais'&&$doc['payload']['native_fields']['adminnote']==='Privado'&&$doc['payload']['native_reference']==='REF-LOCAL'&&$doc['reference_no']==='REF-LOCAL'&&$doc['payload']['due_date']===null,'form preserves typed native fields and empty expiration');
essentialCheck($form->create($a,7,$wire)===$created,'identical original form retry returns same receipt even after sequence advances');
$sales->decide($a,7,$id,'accepted');$portal=$portalSales->document($sessionA,$a,$id);
essentialCheck($portal['payload']['native_reference']==='REF-LOCAL'&&!isset($portal['payload']['native_fields']['adminnote']),'public estimate retains reference but not internal note');
$pdf=CompanySalesPdf::prepare($portal)->Output('','S');essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'reference-bearing estimate renders PDF');
$invoice=$sales->convert($a,7,$id);essentialCheck($invoice>0&&$sales->document($a,7,$id)['reference_no']==='REF-LOCAL','invoice conversion retains estimate reference without injecting unsupported invoice field');
essentialCheck($form->create($a,7,$wire)===$created,'original create replay remains stable after conversion');
$stale=$wire;$stale['_native_estimate_request_key']=hash('sha256','native-estimate-form-stale');essentialThrows(fn()=>$form->create($a,7,$stale),'native_sales_sequence_stale','another request cannot reuse a stale selected number');
$before=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_sales_documents')->row()->n;
foreach(['status','tags','estimate_request_id','custom_fields','save_and_send'] as $field){
 $bad=$wire;$bad['_native_estimate_request_key']=hash('sha256','native-estimate-unsupported-'.$field);
 $bad[$field]=match($field){'status'=>'2','tags'=>'new tag','estimate_request_id'=>'8','custom_fields'=>['estimate'=>['15'=>'value']],'save_and_send'=>'true'};
 $expected=$field==='save_and_send'?'native_estimate_form_mail_required':($field==='tags'?'native_estimate_form_tags_unavailable':($field==='custom_fields'?'native_estimate_form_fields_unavailable':'native_estimate_form_feature_required'));
 essentialThrows(fn()=>$form->create($a,7,$bad),$expected,'unsupported original feature is never discarded: '.$field);
}
essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_sales_documents')->row()->n===$before,'unsupported commands cause no document effects');
$bad=$wire;$bad['_native_estimate_request_key']=hash('sha256','native-estimate-form-foreign');$bad['clientid']=(string)$cb;essentialThrows(fn()=>$form->create($a,7,$bad),'native_sales_sequence_stale','stale check precedes foreign write');
$fresh=$sequence->readForCreation($a,7,'estimates');$bad['_native_estimate_number_revision']=$fresh['revision'];$bad['number']=$fresh['next_number'];essentialThrows(fn()=>$form->create($a,7,$bad),'company_sales_recipient_denied','foreign client rejected with current sequence');
// A later veto must roll back document, sequence, audit and all metadata.
$fresh=$sequence->readForCreation($a,7,'estimates');$count=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_sales_documents')->row()->n;$calls=0;$guard=function()use(&$calls){if(++$calls===2)throw new DomainException('synthetic_guard_rejected');};
essentialThrows(fn()=>$sales->create($a,7,'estimates',array_replace($salesInput,['client_id'=>$ca]),hash('sha256','native-estimate-form-veto'),$fresh,$guard),'synthetic_guard_rejected','late originating form veto aborts transaction');
essentialCheck($sequence->readForCreation($a,7,'estimates')===$fresh&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_sales_documents')->row()->n===$count,'late veto restores number and document');
echo 'Native estimate original form create compositor: '.($GLOBALS['essentialChecks']-$start)." checks. Unsupported features and HTTP remain closed.\n";
