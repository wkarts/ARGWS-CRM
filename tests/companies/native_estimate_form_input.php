<?php
require dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/NativeCompanyEstimateFormInput.php';
$start=$GLOBALS['essentialChecks'];$project=NativeCompanyResourceId::encode('company_project',23);
$wire=['_native_estimate_id'=>'','_native_estimate_revision'=>'','_native_estimate_request_key'=>hash('sha256','estimate-request'),
 '_native_estimate_number_revision'=>hash('sha256','sequence-revision'),'number'=>'00042','clientid'=>'42','project_id'=>$project,'estimate_request_id'=>'7',
 'currency'=>'1','date'=>'31/01/2026','expirydate'=>'10/02/2026','status'=>'1','reference_no'=>'REF-2026','sale_agent'=>'12',
 'newitems'=>['7'=>['order'=>'1','description'=>'Serviço','long_description'=>'Detalhes','qty'=>'2','rate'=>'50','unit'=>'hora','taxname'=>[],
  'custom_fields'=>['items'=>['14'=>'linha']]]],
 'discount_percent'=>'10','discount_total'=>'10.00','discount_type'=>'before_tax','adjustment'=>'','subtotal'=>'100.00','total'=>'90.00',
 'tags'=>'importante, projeto','custom_fields'=>['estimate'=>['15'=>'observação']],'clientnote'=>'Nota','terms'=>'Condições',
 'adminnote'=>'Interno','billing_street'=>'Rua 1','billing_city'=>'Salvador','billing_state'=>'BA','billing_zip'=>'40000000','billing_country'=>'1',
 'shipping_street'=>'Rua 2','shipping_city'=>'Salvador','shipping_state'=>'BA','shipping_zip'=>'40000001','shipping_country'=>'1',
 'include_shipping'=>'on','show_shipping_on_estimate'=>'on','show_quantity_as'=>'2'];
$parsed=NativeCompanyEstimateFormInput::create($wire,'d/m/Y|%d/%m/%Y');
essentialCheck($parsed['payload']['date']==='2026-01-31'&&$parsed['payload']['due_date']==='2026-02-10','localized dates preserve exact calendar days');
essentialCheck($parsed['payload']['native_fields']['terms']==='Condições'&&$parsed['payload']['native_fields']['adminnote']==='Interno'&&$parsed['payload']['native_fields']['billing_city']==='Salvador','original text and billing fields retained');
essentialCheck($parsed['payload']['native_fields']['show_shipping_on_invoice']===1&&$parsed['payload']['native_fields']['include_shipping']===1&&$parsed['payload']['native_fields']['show_quantity_as']===2,'original display/shipping flags retained for canonical mapping');
essentialCheck($parsed['metadata']['status']==='1'&&$parsed['metadata']['reference_no']==='REF-2026'&&$parsed['metadata']['project']===$project&&$parsed['metadata']['estimate_request_id']===7,'status, external reference and associations not silently discarded');
essentialCheck($parsed['metadata']['estimate_fields']===['15'=>'observação']&&$parsed['metadata']['line_fields']===[['14'=>'linha']]&&$parsed['metadata']['tags']===['importante','projeto'],'custom fields, line fields and tags kept separately for ownership validation');
essentialCheck($parsed['metadata']['number_selection']===['next_number'=>'42','revision'=>$wire['_native_estimate_number_revision']],'server-bound sequence selection retained explicitly');
essentialCheck($parsed['action']==='save'&&$parsed['request_key']===$wire['_native_estimate_request_key'],'save request identity retained');
$sent=$wire;$sent['save_and_send']='true';essentialCheck(NativeCompanyEstimateFormInput::create($sent,'d/m/Y|%d/%m/%Y')['action']==='save_and_send','send request is preserved, never silently converted to save');
$optional=$wire;$optional['expirydate']='';essentialCheck(NativeCompanyEstimateFormInput::create($optional,'d/m/Y|%d/%m/%Y')['payload']['due_date']===null,'optional expiration remains empty rather than invented');
$bad=$wire;$bad['issuer_id']='82';essentialThrows(fn()=>NativeCompanyEstimateFormInput::create($bad,'d/m/Y|%d/%m/%Y'),'native_estimate_form_invalid','wire cannot select issuer');
$bad=$wire;$bad['total']='99.99';essentialThrows(fn()=>NativeCompanyEstimateFormInput::create($bad,'d/m/Y|%d/%m/%Y'),'native_estimate_form_amount_mismatch','browser totals are checked against arithmetic');
$bad=$wire;$bad['show_shipping_on_invoice']='1';essentialThrows(fn()=>NativeCompanyEstimateFormInput::create($bad,'d/m/Y|%d/%m/%Y'),'native_estimate_form_invalid','invoice-only field cannot shadow original estimate checkbox');
$bad=$wire;$bad['newitems']['7']['order']='2';essentialThrows(fn()=>NativeCompanyEstimateFormInput::create($bad,'d/m/Y|%d/%m/%Y'),'native_estimate_form_invalid','ambiguous item ordering rejected');
$bad=$wire;$bad['save_and_send']='true';$bad['save_and_send_later']='true';essentialThrows(fn()=>NativeCompanyEstimateFormInput::create($bad,'d/m/Y|%d/%m/%Y'),'native_estimate_form_invalid','conflicting actions rejected');
$bad=$wire;$bad['items']=['legacy'=>1];essentialThrows(fn()=>NativeCompanyEstimateFormInput::create($bad,'d/m/Y|%d/%m/%Y'),'native_estimate_form_source_required','old-line sources cannot be silently discarded');
echo 'Native estimate complete create wire parser: '.($GLOBALS['essentialChecks']-$start)." checks; references remain untrusted until owner-validated by writer.\n";
