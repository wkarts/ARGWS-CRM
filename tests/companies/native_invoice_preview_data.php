<?php
/** Read-only preview foundation against the real scoped services and native DB fixture. */
require __DIR__.'/invoices.php';
require_once APPPATH.'services/NativeCompanyInvoicePreviewData.php';
$preview=new NativeCompanyInvoicePreviewData($db,$p);
$html=NativeCompanyInvoicePreviewData::addressHtml(['legal_name'=>'Issuer <script>','tax_id'=>'A','address'=>'Road & home','city'=>'City','state'=>'City']);
essentialCheck(str_contains($html,'Issuer &lt;script&gt;')&&str_contains($html,'Road &amp; home')&&substr_count($html,'City')===2&&!str_contains($html,'<script>'),'original preview can render escaped immutable issuer identity without dropping repeated address fields');
$html=NativeCompanyInvoicePreviewData::addressHtml(['name'=>'Recipient <b>','tax_id'=>'T','billing'=>['street'=>'Street & 1','city'=>'Town'] ],true);
essentialCheck(str_contains($html,'Recipient &lt;b&gt;')&&str_contains($html,'Street &amp; 1'),'recipient snapshot is escaped rather than read from a global client');
$before=[];foreach(['invoices','invoicepaymentrecords','issuers_invoice_documents','itemable','item_tax'] as $table)$before[$table]=$db->query('SELECT * FROM '.$p.$table.' ORDER BY '.($table==='issuers_invoice_documents'?'invoice_id':'id'))->result_array();
foreach([[$a,$branded],[$b,$ib]] as [$owner,$invoice]){
    $data=$preview->read($owner,7,$invoice);
    essentialCheck($data['identity']['issuer_id']===$owner&&$data['document']['invoice_id']===$invoice,'preview identity and persisted document match selected issuer');
    essentialCheck($data['lines']===$s->previewItems($owner,7,$invoice)&&$data['balance']===$s->position($owner,7,$invoice)&&$data['payments']===$s->payments($owner,7,$invoice),'preview exposes authorized persisted lines, balance and payments');
}
essentialThrows(fn()=>$preview->read($b,7,$branded),'company_invoice_access_denied','direct cross-company preview denied');
essentialThrows(fn()=>$preview->read($a,999999,$branded),'company_invoice_access_denied','unknown actor denied');
$primary=(new IssuerResourceAccess($db,$p))->primaryId();
essentialThrows(fn()=>$preview->read($primary,7,42),'native_invoice_preview_lineage_unavailable','historical null-owner cannot fall through to named invoice adapter');
$draft=NativeCompanyResourceId::encode('company_invoice_draft',1);
essentialThrows(fn()=>$preview->read($a,7,$draft),'native_invoice_preview_lineage_unavailable','draft requires its separate adapter');
foreach($before as $table=>$rows)essentialCheck($db->query('SELECT * FROM '.$p.$table.' ORDER BY '.($table==='issuers_invoice_documents'?'invoice_id':'id'))->result_array()===$rows,'preview never writes '.$table);
echo 'Native invoice preview data A/B/NULL, permissions and read-only checks passed; native route remains closed.'.PHP_EOL;
