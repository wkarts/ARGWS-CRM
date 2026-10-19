<?php
/** Production services against native table definitions in a disposable PDO database. */
require_once dirname(__DIR__).'/issuers_core_support.php';
require_once APPPATH.'services/CompanyInvoices.php';
require_once APPPATH.'services/CompanyClients.php';
register_shutdown_function('essentialCleanup');
$f=essentialFixture('1.0.2',1);$db=$f->db;$p=$f->prefix;
IssuerCore::reconcile($db,$p);IssuerCore::bindInstallationMaster($db,$p,7);
$db->query("DROP TABLE {$p}issuers_invoice_documents");
foreach(['invoices','clients'] as $table)$db->query('DROP TABLE '.$p.$table);
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['clients','invoices','itemable','item_tax','invoicepaymentrecords','credits','currencies','countries'] as $name){
    preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('native fixture missing '.$name);
    $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);
    $key=$name==='clients'?'userid':($name==='countries'?'country_id':'id');
    $sql=str_replace('`'.$key.'` int NOT NULL,','`'.$key.'` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
    if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}
    $db->query($sql);
}
IssuerCore::invalidate($db);IssuerFinancialSchema::ensure($db,$p);
if($db->driver()==='mysql'){
    if(!class_exists('CI_Migration')){#[AllowDynamicProperties] class CI_Migration {}}
    require_once APPPATH.'migrations/408_version_408.php';$m=new Migration_Version_408();$m->db=$db;$m->up();$m->up();
}else{
    $db->dbdriver='sqlite';
    $db->query("CREATE TABLE {$p}company_invoice_requests(request_key varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,actor_id int NOT NULL,input_hash varchar(64) NOT NULL,invoice_id int NOT NULL,created_at datetime NOT NULL)");
    $db->query("CREATE TABLE {$p}company_payment_receipts(request_key varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,actor_id int NOT NULL,input_hash varchar(64) NOT NULL,invoice_id int NOT NULL,payment_id int NOT NULL,created_at datetime NOT NULL)");
}
require_once APPPATH.'services/CompanyBankComponentsSchema.php';
if($db->driver()==='mysql')CompanyBankComponentsSchema::ensure($db,$p);else{$parts=[];foreach(CompanyBankComponentsSchema::fields() as $name=>$type)$parts[]='`'.$name.'` '.str_replace(' NULL','',$type).(str_ends_with($type,' NULL')?' NULL':' NOT NULL');$parts[]='PRIMARY KEY(request_key)';$parts[]='UNIQUE(payment_id)';$db->query('CREATE TABLE '.$p.'company_bank_receipt_components('.implode(',',$parts).')');}
$db->query("INSERT INTO {$p}currencies(id,symbol,name,decimal_separator,thousand_separator,placement,isdefault) VALUES(1,'R$','BRL',',','.','before',1)");
require_once APPPATH.'services/CompanyCurrencyUnitSchema.php';
require_once APPPATH.'services/CompanyFinancialJournalSchema.php';
if($db->driver()==='mysql'){
    CompanyCurrencyUnitSchema::ensure($db,$p);CompanyFinancialJournalSchema::ensure($db,$p);
}else{
    $db->query('CREATE TABLE '.$p.'company_currency_units(currency_id int PRIMARY KEY,iso_code varchar(3) NOT NULL,basis varchar(48) NOT NULL,recorded_at datetime NOT NULL,source_hash varchar(64) NOT NULL)');
    $parts=[];foreach(CompanyFinancialJournalSchema::fields() as $name=>$type)$parts[]='`'.$name.'` '.str_replace(' NULL','',$type).(str_ends_with($type,' NULL')?' NULL':' NOT NULL');$parts[]='PRIMARY KEY(evidence_key)';$parts[]='UNIQUE(issuer_id,issuer_sequence)';
    $db->query('CREATE TABLE '.$p.'company_financial_events('.implode(',',$parts).')');
}

$db->query("INSERT INTO {$p}clients(userid,company,datecreated,active) VALUES(9999,'Synthetic untouched legacy',?,1)",[date('Y-m-d H:i:s')]);
$db->query("INSERT INTO {$p}invoices(id,clientid,number,datecreated,date,currency,subtotal,total,hash,discount_type,include_shipping) VALUES(42,9999,999,?,'2026-10-19',1,7,7,?,'after_tax',0)",[date('Y-m-d H:i:s'),str_repeat('a',32)]);
$legacyBefore=$db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array();
$registry=new IssuerRegistry($db,$p);$a=$registry->saveIssuer(7,['legal_name'=>'Invoice A','tax_id'=>'12345678000195']);$b=$registry->saveIssuer(7,['legal_name'=>'Invoice B','tax_id'=>'52998224725']);
$settings=new CompanySettingsStorage($db,$p);
foreach([$a,$b] as $issuer)$settings->saveIdentity($issuer,7,['legal_name'=>'Invoice '.($issuer===$a?'A':'B'),'tax_id'=>$issuer===$a?'12345678000195':'52998224725','profile'=>['invoice_company_address'=>'Synthetic street','invoice_company_city'=>'Synthetic city','company_state'=>'BA','invoice_company_country_code'=>'BR','invoice_company_postal_code'=>'00000-000','invoice_company_phonenumber'=>'']]);
$customers=new CompanyClients($db,$p);$ca=$customers->create($a,7,['company'=>'Private A','vat'=>'11144477735']);$cb=$customers->create($b,7,['company'=>'Private B']);
$s=new CompanyInvoices($db,$p);
$input=['client_id'=>$ca,'currency'=>1,'date'=>'2026-10-19','due_date'=>'2026-11-19','items'=>[['description'=>'Synthetic service <script>','qty'=>'2.00','rate'=>'50.25','taxname'=>'Synthetic tax','taxrate'=>'5.00']],'note'=>'Synthetic note'];
$key=str_repeat('a',64);$id=$s->create($a,7,$input,$key);
essentialCheck($s->create($a,7,$input,$key)===$id,'invoice retry returns same persisted ID');
$doc=$s->document($a,7,$id)->data();
essentialCheck($doc['totals']['subtotal']==='100.50'&&$doc['totals']['total_tax']==='5.03'&&$doc['totals']['total']==='105.53','integer item and tax rounding reconciles native document DTO');
essentialCheck($doc['recipient']['name']==='Private A'&&$doc['identity']['legal_name']==='Invoice A','canonical legal/customer snapshots captured atomically');
essentialCheck(count($s->page($a,7)['items'])===1&&$s->page($b,7)['items']===[],'invoice list isolates A/B');
essentialThrows(fn()=>$s->document($b,7,$id),'issuer_document_scope_mismatch','direct invoice ID from other company denied');
essentialThrows(fn()=>$s->create($b,7,$input,str_repeat('b',64)),'company_invoice_recipient_denied','cannot invoice another private customer');
$customers->setSharing($a,7,$ca,[$b]);$other=$input;$other['items'][0]['rate']='1.00';$ib=$s->create($b,7,$other,str_repeat('b',64));
essentialCheck($s->document($b,7,$ib)->data()['identity']['legal_name']==='Invoice B','sharing identity permits an independent B invoice without copying A finances');
$customers->setSharing($a,7,$ca,[]);
essentialCheck($s->document($b,7,$ib)->data()['recipient']['name']==='Private A','revoking directory link preserves historical B invoice snapshot');
$old=$s->revision($a,7,$id);$edit=$input;$edit['items'][0]['rate']='40.00';$s->change($a,7,$id,$old,$edit);
essentialThrows(fn()=>$s->change($a,7,$id,$old,$input),'company_invoice_stale','old editor cannot overwrite current amount');
$dto=$s->document($a,7,$id);$d=$dto->data();essentialCheck($d['number']===$doc['number']&&$d['totals']['total']==='84.00','editing preserves sequence and recalculates exact totals');
$settings->saveIdentity($a,7,['legal_name'=>'Renamed current A','tax_id'=>'12345678000195','profile'=>['invoice_company_address'=>'Changed current address']]);
essentialCheck($s->document($a,7,$id)->data()['identity']['legal_name']==='Invoice A','current company edit cannot rewrite issued identity');
$writer=new \app\services\InvoicePaymentWriter($db);$payment=['amount'=>'20.00','date'=>'2026-10-19','reference'=>'synthetic-receipt-1','note'=>'Synthetic evidence'];
$pid=$writer->recordCompanyManual($a,7,$id,$payment,str_repeat('c',64));
essentialCheck($writer->recordCompanyManual($a,7,$id,$payment,str_repeat('c',64))===$pid,'manual receipt retry is durable and idempotent');
essentialCheck($s->document($a,7,$id)->data()['status']===3&&count($s->payments($a,7,$id))===1,'partial receipt updates own invoice after confirmed transaction');
essentialThrows(fn()=>$writer->recordCompanyManual($b,7,$id,$payment,str_repeat('d',64)),'company_payment_access_denied','cross-company payment cannot target same numeric ID');
essentialThrows(fn()=>$writer->recordCompanyManual($a,7,$id,$payment,str_repeat('d',64)),'company_payment_reference_conflict','fresh key does not duplicate same external evidence');
$excess=$payment;$excess['amount']='64.01';$excess['reference']='excess';essentialThrows(fn()=>$writer->recordCompanyManual($a,7,$id,$excess,str_repeat('d',64)),'company_payment_exceeds_balance','locked balance prevents overpayment');
$db->failOn='INSERT INTO '.$p.'issuers_audit';$last=$payment;$last['amount']='64.00';$last['reference']='final';
essentialThrows(fn()=>$writer->recordCompanyManual($a,7,$id,$last,str_repeat('e',64)),'issuer_storage_failed','payment audit failure rolls back status receipt and amount');$db->failOn=null;
essentialCheck(count($s->payments($a,7,$id))===1&&$s->document($a,7,$id)->data()['status']===3,'failed final receipt leaves partial state unchanged');
$writer->recordCompanyManual($a,7,$id,$last,str_repeat('e',64));essentialCheck($s->document($a,7,$id)->data()['status']===2,'final exact receipt settles local invoice');
essentialThrows(fn()=>$s->change($a,7,$id,$s->revision($a,7,$id)),'company_invoice_immutable','paid invoice cannot be locally cancelled');
$rev=$s->revision($b,7,$ib);$s->change($b,7,$ib,$rev);$s->change($b,7,$ib,$rev);
essentialCheck($s->document($b,7,$ib)->data()['status']===5,'unbound invoice cancellation preserves document and idempotent retry');
$before=(int)$db->query("SELECT COUNT(*) n FROM {$p}invoices")->row()->n;$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$s->create($b,7,array_replace($input,['client_id'=>$cb]),str_repeat('f',64)),'issuer_storage_failed','creation audit failure rolls back invoice snapshot sequence and items');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}invoices")->row()->n===$before,'no orphan invoice after failed creation');
if(!class_exists('TCPDF'))require_once APPPATH.'vendor/tecnickcom/tcpdf/tcpdf.php';require_once APPPATH.'libraries/pdf/Issuer_invoice_pdf.php';
$pdf=(new Issuer_invoice_pdf($dto))->prepare()->Output('','S');essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'actual standalone TCPDF renders authorized native DTO');
echo 'Named-company invoice/payment/PDF: '.$GLOBALS['essentialChecks'].' checks passed ('.$db->driver().'). No provider calls.'.PHP_EOL;

$db->query("CREATE TABLE {$p}bb_origins(id int PRIMARY KEY,invoice_id int NOT NULL)");unset($db->data_cache['table_names']);
$bound=$s->create($b,7,array_replace($input,['client_id'=>$cb]),str_repeat('1',64));$db->query("INSERT INTO {$p}bb_origins VALUES(1,?)",[$bound]);
essentialThrows(fn()=>$s->change($b,7,$bound,$s->revision($b,7,$bound)),'company_invoice_provider_review_required','local cancellation cannot stand in for bank cancellation');
essentialThrows(fn()=>$writer->recordCompanyManual($b,7,$bound,$payment,str_repeat('2',64)),'company_payment_provider_review_required','observed bank origin requires canonical reconciliation instead of manual duplicate');
$db->query("INSERT INTO {$p}staff(staffid,active,admin) VALUES(12,1,0)");$db->query("INSERT INTO {$p}issuers_staff(issuer_id,staff_id,can_view) VALUES(?,12,1)",[$a]);
foreach(['view','create'] as $cap)$db->query("INSERT INTO {$p}issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,12,'invoices',?)",[$a,$cap]);
essentialCheck($s->document($a,12,$id)->data()['invoice_id']===$id,'invoice resource read grant works independently of global admin');
essentialThrows(fn()=>$s->create($a,12,$input,str_repeat('3',64)),'company_invoice_recipient_denied','invoice create alone cannot infer private directory access');
essentialThrows(fn()=>$writer->recordCompanyManual($a,12,$id,$payment,str_repeat('4',64)),'company_payment_access_denied','invoice view does not imply payment create');
$db->query("UPDATE {$p}issuers_staff SET can_view=0 WHERE issuer_id=? AND staff_id=12",[$a]);
essentialThrows(fn()=>$s->document($a,12,$id),'company_invoice_access_denied','revocation invalidates direct PDF/data access');
$db->query("DROP TABLE {$p}bb_origins");unset($db->data_cache['table_names']);
echo 'Financial origin/ACL boundaries: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;

essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'all named-company operations preserve original NULL legacy invoice byte values');
essentialThrows(fn()=>$s->document($a,7,42),'issuer_document_scope_mismatch','named-company document never adopts a NULL legacy invoice');
$s->confirmPdfDownload($a,7,$id);
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}issuers_audit WHERE event='company_invoice_pdf_downloaded' AND subject_id=?",[$id])->row()->n===1,'PDF authorization and download provenance commit together');
echo 'Invoice legacy/PDF provenance: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;

$untrustedSource=new class implements CompanyReceiptSource {
    public function assertSchema($db,string $prefix):void { throw new LogicException('untrusted adapter must not run'); }
    public function tables():array { return []; }
    public function claim($db,string $prefix,int $issuer,int $actor):array { return []; }
    public function applied($db,string $prefix,int $issuer,int $actor,int $payment):void {}
};
essentialThrows(fn()=>$writer->recordCompanyProviderReceipt($a,7,$untrustedSource),'company_receipt_source_denied','an arbitrary provider adapter cannot grant positive-company payment authority');

// Positive A/B amounts, immutable snapshots, retries and rollback for the expanded editor.
$expanded=['client_id'=>$ca,'currency'=>1,'date'=>'2026-10-19','due_date'=>'2026-11-19',
 'items'=>[['description'=>'Expanded amounts','qty'=>'1.00','rate'=>'100.00','taxes'=>[['name'=>'ISS','rate'=>'5.00'],['name'=>'Levy','rate'=>'2.50']]]],
 'discount_mode'=>'percent','discount_value'=>'10.00','discount_type'=>'before_tax','adjustment'=>'-0.01','note'=>'Synthetic'];
foreach([[$a,$ca],[$b,$cb]] as [$owner,$customer]){
 $expanded['client_id']=$customer;$expandedKey=hash('sha256','expanded-'.$owner);$expandedId=$s->create($owner,7,$expanded,$expandedKey);
 $expandedDoc=$s->document($owner,7,$expandedId)->data();
 essentialCheck($expandedDoc['totals']===['subtotal'=>'100.00','total_tax'=>'6.75','discount_total'=>'10.00','adjustment'=>'-0.01','total'=>'96.74'],'A/B DTO/PDF amounts reconcile multiple taxes and before-tax discount');
 essentialCheck(count($expandedDoc['items'][0]['taxes'])===2,'both taxes persist and are visible to the authorized document reader');
 essentialCheck($s->adjustments($owner,7,$expandedId)['discount_value']==='10.00','editor reload preserves percentage input');
 essentialCheck($s->create($owner,7,$expanded,$expandedKey)===$expandedId,'expanded monetary retry does not consume sequence');
 $divergent=$expanded;$divergent['adjustment']='0.01';
 essentialThrows(fn()=>$s->create($owner,7,$divergent,$expandedKey),'company_invoice_request_conflict','same request with different adjustment conflicts');
 $prior=$s->revision($owner,7,$expandedId);$divergent['discount_mode']='fixed';$divergent['discount_type']='after_tax';
 $db->failOn='INSERT INTO '.$p.'issuers_audit';
 essentialThrows(fn()=>$s->change($owner,7,$expandedId,$prior,$divergent),'issuer_storage_failed','expanded update rolls back amounts and both taxes if audit fails');$db->failOn=null;
 essentialCheck($s->document($owner,7,$expandedId)->data()['totals']===$expandedDoc['totals'],'failed update leaves totals intact');
 $s->change($owner,7,$expandedId,$prior,$divergent);
 essentialCheck($s->document($owner,7,$expandedId)->data()['totals']['total']==='97.51','positive edit recalculates after-tax fixed discount in A/B');
 essentialThrows(fn()=>$s->change($owner,7,$expandedId,$prior,$expanded),'company_invoice_stale','discount editor uses optimistic concurrency');
 $writer->recordCompanyManual($owner,7,$expandedId,['amount'=>'97.51','date'=>'2026-10-19','reference'=>'expanded-'.$owner,'note'=>'Synthetic'],hash('sha256','expanded-payment-'.$owner));
 essentialCheck($s->document($owner,7,$expandedId)->data()['status']===2,'canonical writer settles discounted adjusted amount');
 essentialThrows(fn()=>$s->change($owner,7,$expandedId,$s->revision($owner,7,$expandedId),$expanded),'company_invoice_immutable','discount cannot be changed after a financial movement');
}
// A durable pre-417 receipt must survive software upgrade without a second invoice.
$legacyInput=$input;$legacyInput['client_id']=$ca;$legacyKey=hash('sha256','pre417');$legacyId=$s->create($a,7,$legacyInput,$legacyKey);
$oldPayload=['client_id'=>$ca,'date'=>'2026-10-19','due_date'=>'2026-11-19','currency'=>1,
 'items'=>[['description'=>'Synthetic service <script>','qty'=>'2.00','rate'=>'50.25','taxrate'=>'5.00','taxname'=>'Synthetic tax']],
 'note'=>'Synthetic note','subtotal'=>'100.50','tax'=>'5.03','total'=>'105.53'];
$db->query("UPDATE {$p}company_invoice_requests SET input_hash=? WHERE request_key=?",[hash('sha256',json_encode($oldPayload,JSON_THROW_ON_ERROR)),$legacyKey]);
essentialCheck($s->create($a,7,$legacyInput,$legacyKey)===$legacyId,'pre-upgrade request hash remains retryable without recreating the invoice');
essentialThrows(fn()=>$s->adjustments($b,7,$legacyId),'company_invoice_access_denied','adjustment reader rejects cross-company ID');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'expanded operations preserve historical NULL rows');
echo 'Expanded invoice amounts/retry/A+B/rollback: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;

// Owned branding is captured only for a newly issued document, never rewritten retrospectively.
$assets=new CompanyAssets($db,$p);$image=imagecreatetruecolor(2,2);ob_start();imagepng($image);$imageBytes=ob_get_clean();unset($image);
$emptyAssets=$assets->view($a,7);$assets->save($a,7,'logo',$imageBytes,$emptyAssets['revision']);$assets->save($a,7,'signature',$imageBytes,$assets->view($a,7)['revision']);
$branded=$s->create($a,7,$legacyInput,hash('sha256','branded-invoice'));$brandDto=$s->document($a,7,$branded);$brandData=$brandDto->data();
essentialCheck(str_starts_with($brandData['identity']['logo_inline'],'data:image/png;base64,')&&$brandData['identity']['signature_inline']!=='' ,'new invoice snapshots own logo and signature');
$assets->save($a,7,'logo',null,$assets->view($a,7)['revision']);$assets->save($a,7,'signature',null,$assets->view($a,7)['revision']);
essentialCheck($s->document($a,7,$branded)->data()['identity']===$brandData['identity'],'removing current branding cannot rewrite issued image snapshots');
essentialCheck($s->document($a,7,$legacyId)->data()['identity']['logo_inline']==='','prior unbranded invoice never inherits later branding');
$brandPdf=(new Issuer_invoice_pdf($brandDto))->prepare()->Output('','S');essentialCheck(str_starts_with($brandPdf,'%PDF-'),'PDF actually renders raster logo and signature');
echo 'Owned branding snapshots/PDF: '.$GLOBALS['essentialChecks'].' cumulative checks passed.'.PHP_EOL;
