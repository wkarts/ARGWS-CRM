<?php
/** Actual historical/owned sales readers, exact monetary projection and original table wire. */
require __DIR__.'/native_sales_pipeline.php';
require __DIR__.'/native_invoice_table_schema_fixture.php';
require_once APPPATH.'services/NativeCompanySalesProjection.php';
require_once APPPATH.'services/NativeCompanySalesTableFactory.php';
require_once APPPATH.'services/NativeCompanySalesRows.php';
CompanySalesMutationsSchema::ensure($db,$p);
$sourceBefore=$db->query('SELECT * FROM '.$p.'company_sales_documents ORDER BY id')->result_array();
$invoicesBefore=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();
NativeCompanySalesProjectionSchema::ensure($db,$p);NativeCompanySalesProjectionSchema::ensure($db,$p);
$projection=new NativeCompanySalesProjection($db,$p);$projection->backfill();$projection->backfill();
essentialCheck($db->query('SELECT * FROM '.$p.'company_sales_documents ORDER BY id')->result_array()===$sourceBefore
    &&$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$invoicesBefore,'projection migration/replay never rewrites source, numbers, or issued invoice history');
$checks=$GLOBALS['essentialChecks'];$key=static fn($name)=>hash('sha256','native-sales-table:'.$name);
$builder=static fn($id,$type,$operator,$value)=>['match_type'=>'and','rules'=>[['id'=>$id,'type'=>$type,'operator'=>$operator,'value'=>$value,'has_dynamic_value'=>false]]];
$reader=new NativeCompanySalesDirectory($db,$p);$presenter=new NativeCompanySalesRows($db,$p);
foreach([[$a,$ca],[$b,$cb],[$principal,$principalClient]] as [$owner,$client])foreach(['estimates','proposals'] as $kind){
    $payload=array_replace($salesInput,['client_id'=>$client,'note'=>'Table literal '.$owner.' '.$kind,
        'items'=>[['description'=>'First precise line','qty'=>'3.00','rate'=>'0.01','taxes'=>[['name'=>'Tax five','rate'=>'5.00']]],
            ['description'=>'Main line','qty'=>'1.00','rate'=>'100.01','taxes'=>[['name'=>'Tax five','rate'=>'5.00']]],
            ['description'=>'Optional omitted','qty'=>'1.00','rate'=>'900.00','is_optional'=>1,'is_selected'=>0,'taxes'=>[]]],
        'discount_mode'=>'percent','discount_value'=>'10.00','discount_type'=>'before_tax','adjustment'=>'-0.01']);
    $id=$sales->create($owner,7,$kind,$payload,$key('create-'.$owner.$kind));$type=$kind==='estimates'?'estimate':'proposal';
    $canonical=NativeCompanyResourceId::encode('company_'.$type,$id);
    $derived=$db->query('SELECT * FROM '.$p.'company_sales_projection WHERE id=?',[$id])->row_array();
    $money=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment'])));
    essentialCheck(CompanyInvoiceAmounts::minor((string)$derived['subtotal'])===CompanyInvoiceAmounts::minor($money['subtotal'])
        &&CompanyInvoiceAmounts::minor((string)$derived['total_tax'])===CompanyInvoiceAmounts::minor($money['tax']),'atomic create projects exact selected-line subtotal/tax for '.$kind.' company '.$owner);
    $page=$reader->query($owner,7,$kind,['search'=>$payload['note'],'builder'=>$builder('subtotal','NumberRule','equal',$money['subtotal'])]);
    essentialCheck(array_column($page['items'],'id')===[$canonical],'source filters and public-text search apply before hydration/pagination');
    $data=$presenter->data($owner,7,$kind,$page['items'][0],[]);
    essentialCheck(count($data)===($kind==='estimates'?11:10)&&str_contains($data[0],$canonical),'original positional row has exact native column count and source-aware link');
    essentialCheck(str_contains($data[$kind==='estimates'?10:9],$type.'_status_'.($kind==='estimates'?'draft':'open')),'status appears in final native column, never in project/tags column');
    $review=$sales->review($owner,7,$id,'edit');$edit=array_replace($payload,['note'=>'Edited '.$payload['note']]);$edit['items'][1]['rate']='150.02';
    $intent=['source_revision'=>$review['source_revision'],'reason'=>'Synthetic exact table edit','edit_confirmed'=>'1'];
    $sales->edit($owner,7,$id,$edit,$intent,$key('edit-'.$owner.$kind));
    $updated=$db->query('SELECT * FROM '.$p.'company_sales_projection WHERE id=?',[$id])->row_array();
    essentialCheck($updated['payload_hash']!==$derived['payload_hash']&&CompanyInvoiceAmounts::minor((string)$updated['subtotal'])===15005,'reviewed edit changes exact derived amount in the same transaction');
    essentialCheck($sales->edit($owner,7,$id,$edit,$intent,$key('edit-'.$owner.$kind))===$id
        &&$db->query('SELECT * FROM '.$p.'company_sales_projection WHERE id=?',[$id])->row_array()===$updated,'idempotent edit retry cannot duplicate or drift projection');
    $copyReview=$sales->review($owner,7,$id,'copy');
    $copy=$sales->copy($owner,7,$id,$copyReview['payload'],['source_revision'=>$copyReview['source_revision'],
        'destination_revision'=>$copyReview['destination_revision'],'reason'=>'Synthetic source-aware copy','copy_confirmed'=>'1'],$key('copy-'.$owner.$kind));
    essentialCheck((int)$db->query('SELECT issuer_id FROM '.$p.'company_sales_projection WHERE id=?',[$copy])->row_array()['issuer_id']===$owner,'reviewed copy creates its own projection and owner');
    $sales->decide($owner,7,$id,'accepted');$invoice=$sales->convert($owner,7,$id);
    essentialCheck($reader->document($owner,7,$kind,$canonical)['invoice_id']==$invoice
        &&$db->query('SELECT * FROM '.$p.'company_sales_projection WHERE id=?',[$id])->row_array()===$updated,'acceptance and canonical conversion preserve exact source projection');
    $foreign=$owner===$a?$b:$a;
    essentialThrows(fn()=>$reader->document($foreign,7,$kind,$canonical),'company_sales_denied','foreign direct canonical sales ID never hydrates');
}
// A failed projection write rolls back the same audited edit, including its revision.
$id=$sales->create($b,7,'estimates',array_replace($salesInput,['client_id'=>$cb]),$key('rollback-source'));
$review=$sales->review($b,7,$id,'edit');$prior=$sales->document($b,7,$id);
$db->failOn='UPDATE '.$p.'company_sales_projection';
try{essentialThrows(fn()=>$sales->edit($b,7,$id,array_replace($review['payload'],['note'=>'Must roll back']),
    ['source_revision'=>$review['source_revision'],'reason'=>'Synthetic projection late failure','edit_confirmed'=>'1'],$key('rollback-edit')),
    'issuer_storage_failed','projection failure vetoes source transaction');}finally{$db->failOn=null;}
essentialCheck($sales->document($b,7,$id)===$prior,'projection failure leaves the exact original source and revision');
$db->query('UPDATE '.$p.'company_sales_projection SET subtotal=subtotal+1 WHERE id=?',[$id]);
essentialThrows(fn()=>$reader->query($b,7,'estimates',['search'=>'No row can match this']),
    'issuer_sales_projection_unconfirmed','off-page amount tamper is rejected before filtered counts');
$projection->backfill();
$db->query('UPDATE '.$p.'company_sales_projection SET issuer_id=? WHERE id=?',[$a,$id]);
foreach([$a,$b] as $owner)essentialThrows(fn()=>$reader->query($owner,7,'estimates',['search'=>'No row can match this']),
    'issuer_sales_projection_unconfirmed','moved projection fails both source and destination owner frame');
$db->query('UPDATE '.$p.'company_sales_projection SET issuer_id=? WHERE id=?',[$b,$id]);$projection->backfill();
// Partial backfill repairs must roll back if a later source cannot be proven.
$first=(int)$sourceBefore[0]['id'];$db->query('UPDATE '.$p.'company_sales_projection SET total_tax=total_tax+1 WHERE id=?',[$first]);
$corrupt=$db->query('SELECT * FROM '.$p.'company_sales_projection WHERE id=?',[$first])->row_array();
$original=$db->query('SELECT payload_json FROM '.$p.'company_sales_documents WHERE id=?',[$id])->row_array()['payload_json'];
$db->query('UPDATE '.$p.'company_sales_documents SET payload_json=? WHERE id=?',['{}',$id]);
try{essentialThrows(fn()=>$projection->backfill(),'issuer_sales_projection_unconfirmed','unproven source aborts whole migration backfill');}
finally{$db->query('UPDATE '.$p.'company_sales_documents SET payload_json=? WHERE id=?',[$original,$id]);}
essentialCheck($db->query('SELECT * FROM '.$p.'company_sales_projection WHERE id=?',[$first])->row_array()===$corrupt,'failed backfill rolls back previously repaired projection');$projection->backfill();
if($db->driver()==='sqlite'){
    $db->query('DROP INDEX '.$p.'sales_projection_owner');
    essentialThrows(fn()=>NativeCompanySalesProjectionSchema::assertValid($db,$p),'native_sales_projection_schema_invalid','owner lookup index is required on SQLite');
    NativeCompanySalesProjectionSchema::ensure($db,$p);
}
$factory=new NativeCompanySalesTableFactory($db,$p);
foreach(['estimates'=>12,'proposals'=>11] as $kind=>$count){
    $table=$factory->make($a,7,$kind);essentialCheck($table->id()===$kind&&count($table->rules())===$count,'original filter catalogue retained for '.$kind);
    essentialThrows(fn()=>$table->normalizeBuilder($builder('unknown','TextRule','contains','anything')),'native_sales_filter_invalid','unknown filter cannot reach SQL');
}
$totals=$reader->totals($principal,7,['years'=>['2026'],'currency'=>'1']);
essentialCheck(count($totals['totals'])===5&&$totals['_currency']==1,'estimate summary retains five status totals in one selected currency');
essentialThrows(fn()=>$reader->totals($a,7,['currency'=>[]]),'issuer_invalid_id','malformed currency does not coerce into a summary');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array()===$legacyBefore,'sales table, filters, projection and summary preserve historical invoice exactly');
echo 'Original sales table/projection/filter/writer: '.($GLOBALS['essentialChecks']-$checks).' additional checks passed ('.$db->driver().').'.PHP_EOL;

// Render the real overview, without loading the ambient Estimates helper.
require_once APPPATH.'services/NativeCompanySalesManage.php';
if(!function_exists('e')){function e($value){return htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}}
essentialCheck(!function_exists('estimate_status_color_class'),'scoped overview test does not hide a missing legacy status helper');
$data=(new NativeCompanySalesManage($db,$p))->data($a,7,'estimates');
$html=(static function(array $data){extract($data);ob_start();try{include APPPATH.'views/admin/estimates/quick_stats.php';return ob_get_clean();}catch(Throwable $error){ob_end_clean();throw$error;}})($data);
essentialCheck(substr_count($html,'<button type="button"')===5,'real overview renders all five scoped status filters');
foreach([1=>'default',2=>'info',3=>'danger',4=>'success',5=>'warning']as$status=>$color)essentialCheck(str_contains($html,'text-'.$color)&&NativeCompanySalesTable::statusColor('estimates',$status)===$color,'original status color preserved for '.$status);
essentialThrows(fn()=>NativeCompanySalesTable::statusColor('estimates',99),'company_sales_invalid','unknown status is not silently assigned a valid color');
echo 'Scoped original Sales quick statistics:8 checks passed; no legacy helper injected.'.PHP_EOL;
