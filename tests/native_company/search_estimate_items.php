<?php
/** Actual sales writer/reader and SQLite; private helper seam avoids unrelated search schemas. */
require __DIR__.'/../companies/sales.php';
require_once APPPATH.'services/NativeCompanySearch.php';
$payload=$salesInput;$payload['client_id']=$ca;$payload['items']=[['description'=>'Search%line A','long_description'=>'only detail marker','qty'=>'1.00','rate'=>'8.00','taxes'=>[]]];
$idA=$sales->create($a,7,'estimates',$payload,hash('sha256','native-search-estimate-a'));
$payload['client_id']=$cb;$payload['items'][0]['description']='Search%line B';$idB=$sales->create($b,7,'estimates',$payload,hash('sha256','native-search-estimate-b'));
$reader=new NativeCompanySearch($db,$p);$method=new ReflectionMethod($reader,'estimateItems');
$before=$db->query("SELECT COUNT(*) AS n FROM {$p}issuers_audit")->row()->n;
$rA=$method->invoke($reader,$a,7,'Search%line','%Search!%line%',false);$rB=$method->invoke($reader,$b,7,'Search%line','%Search!%line%',false);
essentialCheck(count($rA)===1&&$rA[0]['secondary_label']==='Search%line A'&&$rA[0]['source_id']===$idA,'A estimate line matches actual owned writer');
essentialCheck(count($rB)===1&&$rB[0]['secondary_label']==='Search%line B'&&$rB[0]['source_id']===$idB,'B excludes A matching line');
essentialCheck($rA[0]['label']===$sales->document($a,7,$idA)['formatted_number'],'frozen canonical estimate number matches native reader');
essentialCheck(count($method->invoke($reader,$a,7,'detail marker','%detail marker%',false))===1,'long description is searchable');
essentialCheck((string)$db->query("SELECT COUNT(*) AS n FROM {$p}issuers_audit")->row()->n===(string)$before,'search reader emits no audit or mutation');
$db->query("UPDATE {$p}company_sales_documents SET input_hash=? WHERE id=?",[str_repeat('0',64),$idA]);
essentialThrows(fn()=>$method->invoke($reader,$a,7,'Search%line','%Search!%line%',false),'issuer_sales_write_unconfirmed','corrupt canonical sales payload not returned');
echo "Native estimate-item search: real writer/reader, A/B, frozen format, detail matching, zero audit and corrupted source rejection passed.\n";
