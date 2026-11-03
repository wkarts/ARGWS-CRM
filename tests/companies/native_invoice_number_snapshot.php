<?php
require __DIR__.'/invoices.php';
require_once APPPATH.'services/SalesNumberPresentation.php';
require_once APPPATH.'services/CompanySettingsCatalog.php';
$settings=new CompanySettingsStorage($db,$p);$numberInput=$input;$numberInput['client_id']=$ca;
$legacyNative=$db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->row_array();
$captured=[];
foreach([1,2,3,4]as$format){
 foreach(['invoice_number_format'=>(string)$format,'number_padding_prefixes'=>'4','invoice_prefix'=>'TEST-']as$name=>$value)$settings->mutate($a,7,'update',$name,$value);
 $key=hash('sha256','number-snapshot-'.$format);$invoice=$s->create($a,7,$numberInput,$key);
 $row=$db->query('SELECT * FROM '.$p.'invoices WHERE id=?',[$invoice])->row_array();$snapshot=$db->query('SELECT * FROM '.$p.'issuers_invoice_documents WHERE invoice_id=?',[$invoice])->row_array();
 $identity=json_decode($snapshot['legal_identity_json'],true,32,JSON_THROW_ON_ERROR);$expected=SalesNumberPresentation::format($snapshot['number'],$format,'TEST-',$numberInput['date'],4);
 essentialCheck($snapshot['formatted_number']===$expected&&$row['formatted_number']===$expected&&(int)$row['number_format']===$format,'actual issue captures native format '.$format.' and uses original formatter');
 essentialCheck($identity['padding']===4&&$identity['number_format']===$format&&$identity['number_date']===$numberInput['date'],'number preferences and numbering date become immutable snapshot inputs');
 $captured[$invoice]=$snapshot;
 $settings->mutate($a,7,'update','invoice_number_format','1');$settings->mutate($a,7,'update','number_padding_prefixes','0');$settings->mutate($a,7,'update','invoice_prefix','CHANGED-');
 essentialCheck($s->create($a,7,$numberInput,$key)===$invoice&&$s->document($a,7,$invoice)->data()['number']===$expected,'option changes and retry cannot rewrite an already issued label');
}
$zero=$s->create($a,7,$numberInput,hash('sha256','zero-padding-native'));
$zeroDoc=$s->document($a,7,$zero)->data();$zeroRow=$db->query('SELECT number FROM '.$p.'invoices WHERE id=?',[$zero])->row_array();essentialCheck($zeroDoc['number']==='CHANGED-'.$zeroRow['number'],'native zero padding remains zero, not fallback six');
foreach($captured as$invoice=>$snapshot)essentialCheck($db->query('SELECT * FROM '.$p.'issuers_invoice_documents WHERE invoice_id=?',[$invoice])->row_array()===$snapshot,'all previously captured documents remain byte-for-byte unchanged');
$badKey=hash('sha256','invalid-format-native');$settings->mutate($a,7,'update','invoice_number_format','9');$before=$db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array();
essentialThrows(fn()=>$s->create($a,7,$numberInput,$badKey),'company_invoice_number_preferences_invalid','unknown format cannot silently fall back or consume a number');
essentialCheck($db->query('SELECT * FROM '.$p.'issuers_sequences ORDER BY issuer_id,series')->result_array()===$before,'invalid preferences roll back invoice creation and sequence');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->row_array()===$legacyNative,'principal historical native NULL invoice remains untouched');
echo 'Native invoice number snapshot: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
// Historical snapshot fixture uses the exact former series format, without new optional profile fields.
$historical=reset($captured);$identity=json_decode($historical['legal_identity_json'],true,32,JSON_THROW_ON_ERROR);unset($identity['number_format'],$identity['number_date']);$historical['legal_identity_json']=json_encode($identity,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$historical['formatted_number']=$identity['prefix'].$historical['series'].'-'.str_pad($historical['number'],$identity['padding'],'0',STR_PAD_LEFT);
$decode=new ReflectionMethod(InvoiceIssuerDocuments::class,'decode');$decoded=$decode->invoke(null,$historical,(int)$historical['invoice_id'],$a);
essentialCheck($decoded['formatted_number']===$historical['formatted_number']&&!array_key_exists('number_format',$decoded['identity']),'preexisting snapshot without format retains original prefix-series-padding decoder');
$settings->mutate($a,7,'update','invoice_number_format','2');$existing=array_key_first($captured);$changed=$numberInput;$changed['date']='2027-01-01';$changed['due_date']='2027-02-01';$s->change($a,7,$existing,$s->revision($a,7,$existing),$changed);
essentialCheck($s->document($a,7,$existing)->data()['number']===$captured[$existing]['formatted_number'],'changing invoice business date cannot recalculate the immutable numbering date');
echo 'Historical numbering snapshot compatibility: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
$formatIdentity=json_decode(reset($captured)['legal_identity_json'],true,32,JSON_THROW_ON_ERROR);
$fiscal=CompanyInvoicePresentation::fiscalIdentity($formatIdentity);essentialCheck(!isset($fiscal['padding'],$fiscal['number_format'],$fiscal['number_date'])&&$fiscal['prefix']===$formatIdentity['prefix']&&$fiscal['tax_id']===$formatIdentity['tax_id'],'fiscal comparison excludes only validated number presentation, preserving prefix and tax identity');
foreach([['padding','4'],['number_format',9],['number_date','2026-02-30']]as[$field,$value]){$broken=$formatIdentity;$broken[$field]=$value;essentialThrows(fn()=>CompanyInvoicePresentation::fiscalIdentity($broken),'issuer_document_invalid_profile','invalid '.$field.' cannot be erased before origin comparison');}
$changedTax=$formatIdentity;$changedTax['tax_id']='52998224725';essentialCheck(CompanyInvoicePresentation::fiscalIdentity($changedTax)!==$fiscal,'valid but different taxpayer remains a different fiscal origin');
$changedPrefix=$formatIdentity;$changedPrefix['prefix']='OTHER-';essentialCheck(CompanyInvoicePresentation::fiscalIdentity($changedPrefix)!==$fiscal,'presentation extension does not suppress explicit fiscal prefix difference');
$settings->mutate($a,7,'update','invoice_prefix','');$settings->mutate($a,7,'update','invoice_number_format','1');$settings->mutate($a,7,'update','number_padding_prefixes','6');$empty=$s->create($a,7,$numberInput,hash('sha256','explicit-empty-prefix'));$emptyRow=$db->query('SELECT number FROM '.$p.'invoices WHERE id=?',[$empty])->row_array();essentialCheck($s->document($a,7,$empty)->data()['number']===str_pad((string)$emptyRow['number'],6,'0',STR_PAD_LEFT),'explicit empty invoice prefix is not replaced by public default');
essentialCheck(CompanySettingsCatalog::defaultValue('documents','invoice_prefix')==='INV-','public missing-setting default matches native installation without reading another issuer');
echo 'Number preference validation/default/fiscal invariants: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
