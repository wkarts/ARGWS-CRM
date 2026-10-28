<?php
/** Actual original Invoices::table request, customer and issuer scope. */
require __DIR__.'/native_invoice_table_controller.php';
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$csrf=true;CompanyRequestScope::$contextValid=true;$ci->input->verb='POST';$ci->input->posts=$wire;$ci->output->status=200;
$controller->table((string)$ca);$result=json_decode($ci->output->body,true,512,JSON_THROW_ON_ERROR);
$expected=(new NativeCompanyInvoiceRead($db,$p))->page($a,7,['draw'=>7,'length'=>-1],['customer_id'=>$ca]);
essentialCheck($result['iTotalRecords']===$expected['recordsTotal']&&$result['iTotalDisplayRecords']===$expected['recordsFiltered'],'original invoice table preserves requested customer lineage');
foreach($expected['documents'] as$document)essentialCheck((int)$document['issuer_id']===$a,'scoped invoice belongs to selected issuer');
$ci->output->status=200;$controller->table((string)$cb);essentialCheck($ci->output->status===403,'foreign customer id is denied');
CompanyRequestScope::$issuer=$b;$ci->output->status=200;$controller->table((string)$ca);
$shared=$db->query('SELECT * FROM '.$p.'issuers_client_links WHERE client_id=? AND issuer_id=?',[$ca,$b])->result_array();
if($shared){
    essentialCheck($ci->output->status===200&&!str_contains($ci->output->body,'init_invoice('.$branded.')'),'explicitly shared identity does not disclose owner invoices');
    $db->query('DELETE FROM '.$p.'issuers_client_links WHERE client_id=? AND issuer_id=?',[$ca,$b]);$ci->output->status=200;
    try{$controller->table((string)$ca);essentialCheck($ci->output->status===403,'revoked customer sharing denies the other issuer');}
    finally{$db->insert($p.'issuers_client_links',$shared[0]);}
}else essentialCheck($ci->output->status===403,'other issuer cannot select an unshared customer');
CompanyRequestScope::$issuer=$a;CompanyRequestScope::$csrf=false;$ci->output->status=200;$controller->table((string)$ca);essentialCheck($ci->output->status===403,'customer-scoped invoice table requires native CSRF');
echo "Original invoice table customer scope, A/B and CSRF passed.\n";
