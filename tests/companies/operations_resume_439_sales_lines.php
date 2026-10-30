<?php
/** Rebuilt regression for the actual shared form fields, decimal amounts, literal PDF and native conversion. */
require __DIR__.'/sales.php';
require_once APPPATH.'services/CompanySalesPdf.php';
$resumeSalesKey=static fn(string $label):string=>hash('sha256','recovery439-sales-lines-'.$label);
$resumeBase=['client_id'=>$ca,'currency'=>1,'date'=>'2026-10-19','due_date'=>'2026-11-19','note'=>'Shared native form','discount_mode'=>'fixed','discount_value'=>'0.00','discount_type'=>'after_tax','adjustment'=>'0.00'];
$resumeItems=[
 ['description'=>'Required literal <script>','qty'=>'2.00','rate'=>'50.00','taxes'=>[['name'=>'Tax','rate'=>'10.00']],'long_description'=>"Complemento literal <b>não HTML</b>\nOutra linha",'unit'=>'hora <b>','is_optional'=>'0','is_selected'=>'1'],
 ['description'=>'Chosen optional','qty'=>'1.00','rate'=>'20.00','taxes'=>[['name'=>'Tax','rate'=>'5.00']],'long_description'=>'Detalhe escolhido','unit'=>'UN','is_optional'=>'1','is_selected'=>'1'],
 ['description'=>'NEVER-RENDER-OPTIONAL','qty'=>'1.00','rate'=>'999.00','taxes'=>[['name'=>'Ignored','rate'=>'99.00']],'long_description'=>'NEVER-RENDER-OPTIONAL-DETAIL','unit'=>'NO','is_optional'=>'1','is_selected'=>'0'],
];
// These four fields are always posted by invoice_item_fields.php, including empty/default values.
$resumeForm=$resumeBase+['items'=>$resumeItems];
$resumeNormalized=CompanySalesBase::input($resumeForm);
essentialCheck($resumeNormalized['items'][0]['long_description']===$resumeItems[0]['long_description']&&$resumeNormalized['items'][0]['unit']==='hora <b>','shared sales form preserves native literal details and unit');
$resumeLegacy=array_replace($resumeBase,['items'=>[['description'=>'Legacy default line','qty'=>'1.00','rate'=>'10.00','taxes'=>[]]]]);
$resumeWithFormDefaults=$resumeLegacy;
$resumeWithFormDefaults['items'][0]+=['long_description'=>'','unit'=>'','is_optional'=>'0','is_selected'=>'1'];
essentialCheck(CompanySalesBase::input($resumeLegacy)===CompanySalesBase::input($resumeWithFormDefaults),'empty/default form fields do not change historical normalized request hash');
foreach([[$a,$ca],[$b,$cb]] as [$owner,$client])foreach(['estimates','proposals'] as $kind){
 $payload=array_replace($resumeForm,['client_id'=>$client]);$key=$resumeSalesKey($owner.'-'.$kind);$id=$sales->create($owner,7,$kind,$payload,$key);
 essentialCheck($sales->create($owner,7,$kind,$payload,$key)===$id,'metadata sales retry remains one immutable document for '.$kind.' owner'.$owner);
 $document=$sales->document($owner,7,$id);
 essentialCheck(CompanyInvoiceAmounts::minor((string)$document['total'])===13100&&count($document['payload']['items'])===3,'optional-unselected line persists but does not enter sales totals');
 essentialCheck($document['payload']['items'][0]['long_description']===$resumeItems[0]['long_description']&&$document['payload']['items'][0]['unit']==='hora <b>','own sales snapshot retains literal extended item fields');
 $pdf=CompanySalesPdf::prepare($document)->Output('','S');
 essentialCheck(str_starts_with($pdf,'%PDF-'),'real TCPDF renders extended sales lines');
 $temporary=tempnam(sys_get_temp_dir(),'crm-sales-line-');file_put_contents($temporary,$pdf);$command=['pdftotext','-layout',$temporary,'-'];$pipes=[];$process=proc_open($command,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);fclose($pipes[0]);$text=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);unlink($temporary);$text=preg_replace('/\s+/u',' ',$text);
 essentialCheck($exit===0&&$error===''&&str_contains($text,'Complemento literal <b>não HTML</b>')&&str_contains($text,'hora <b>'),'independent Poppler observes literal details/unit, not interpreted HTML');
 essentialCheck(!str_contains($text,'NEVER-RENDER-OPTIONAL')&&str_contains($text,'131.00'),'unselected optional content is absent from real PDF and total remains131.00');
 $beforePayments=$db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array();$sales->decide($owner,7,$id,'accepted');$invoice=$sales->convert($owner,7,$id);
 essentialCheck($sales->convert($owner,7,$id)===$invoice,'extended sales conversion still reuses the canonical invoice receipt');
 $dto=$s->document($owner,7,$invoice)->data();
 essentialCheck($dto['totals']['subtotal']==='120.00'&&$dto['totals']['total_tax']==='11.00'&&$dto['totals']['total']==='131.00'&&count($dto['items'])===2,'canonical invoice DTO excludes only unselected optional lines and reconciles cents');
 $native=$db->query("SELECT long_description,unit,is_optional,is_selected FROM ".$p."itemable WHERE rel_type='invoice' AND rel_id=? ORDER BY item_order,id",[$invoice])->result_array();
 essentialCheck(count($native)===3&&$native[0]['long_description']===$resumeItems[0]['long_description']&&$native[0]['unit']==='hora <b>'&&(int)$native[2]['is_optional']===1&&(int)$native[2]['is_selected']===0,'canonical native items preserve all choices and metadata without dropping unselected evidence');
 essentialCheck($db->query('SELECT * FROM '.$p.'invoicepaymentrecords ORDER BY id')->result_array()===$beforePayments,'sales conversion remains receivable-only with original payment ledger');
 essentialThrows(fn()=>$sales->document($owner===$a?$b:$a,7,$id),'company_sales_denied','metadata-bearing sales remain private across companies');
}
foreach([['unknown_fiscal_code'=>'1234'],['long_description'=>null],['unit'=>str_repeat('á',41)],['is_optional'=>0,'is_selected'=>0],['is_optional'=>'yes']] as $bad){$payload=$resumeLegacy;$payload['items'][0]=array_replace($payload['items'][0],$bad);essentialThrows(fn()=>CompanySalesBase::input($payload),'company_invoice_invalid_input','strict shared line contract rejects unknown fiscal fields and malformed metadata');}
$allOptional=$resumeBase+['items'=>[$resumeItems[2]]];essentialThrows(fn()=>CompanySalesBase::input($allOptional),'company_invoice_invalid_input','document cannot consist only of unselected optional lines');
$defaultsKey=$resumeSalesKey('legacy-defaults');$defaultsId=$sales->create($a,7,'proposals',$resumeLegacy,$defaultsKey);essentialCheck($sales->create($a,7,'proposals',$resumeWithFormDefaults,$defaultsKey)===$defaultsId,'pre-extension request retries through actual form defaults without another document');
echo 'Rebuilt recovery439 sales shared-form lines/optional totals/literal PDF/conversion/A+B/default compatibility: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
