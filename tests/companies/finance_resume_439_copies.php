<?php
/** Recreated after recovery: actual canonical invoice-copy writer in a disposable database. */
register_shutdown_function(static function():void{if(isset($GLOBALS['db'],$GLOBALS['p'])){$GLOBALS['db']->query('DROP TABLE IF EXISTS '.$GLOBALS['p'].'company_invoice_copy_requests');unset($GLOBALS['db']->data_cache['table_names']);}});
require __DIR__.'/invoices.php';
require_once APPPATH.'services/CompanyInvoiceCopies.php';
if($db->driver()==='mysql')CompanyInvoiceCopiesSchema::ensure($db,$p);else foreach(CompanyInvoiceCopiesSchema::definitions($p) as $name=>$parts){$sql='CREATE TABLE '.$p.$name.'('.implode(',',$parts).')';$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$db->query($sql);}
unset($db->data_cache['table_names']);
$copies=new CompanyInvoiceCopies($db,$p);$keyFor=fn(string $tag)=>hash('sha256','resume439-copy-'.$tag);$snapshot=$db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array();
foreach([[$a,$ca],[$b,$cb]] as [$owner,$client]){
 $payload=array_replace($input,['client_id'=>$client,'note'=>'Source copy '.$owner,'items'=>[['description'=>'Full copy metadata','long_description'=>'Preserved long text','unit'=>'kg','qty'=>'2.00','rate'=>'5.00','taxes'=>[['name'=>'ISS','rate'=>'5.00']]]]]);
 $source=$s->create($owner,7,$payload,$keyFor('source-'.$owner));$view=$copies->preview($owner,7,$source);
 essentialCheck($view['items'][0]['long_description']==='Preserved long text'&&$view['items'][0]['unit']==='kg','copy preview preserves native line metadata');
 $review=['source_revision'=>$view['revision'],'date'=>'2026-10-20','due_date'=>'2026-11-20','note'=>'Explicit new issue','confirm_amounts'=>'1'];
 $before=$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n;
 $created=$copies->create($owner,7,$source,$review,$keyFor('issue-'.$owner));$again=$copies->create($owner,7,$source,$review,$keyFor('issue-'.$owner));
 essentialCheck($created===$again&&$created['invoice_id']!==$source,'same copy input/key replays a single new invoice');
 $target=$s->document($owner,7,$created['invoice_id'])->data();$sourceDto=$s->document($owner,7,$source)->data();
 essentialCheck($target['number']!==$sourceDto['number']&&$target['totals']===$sourceDto['totals'],'copy uses independent number with exact existing monetary calculation');
 essentialCheck($target['status']===1&&(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoicepaymentrecords')->row()->n===(int)$before,'copy does not copy payment history or settle new invoice');
 essentialCheck($copies->receipt($owner,7,$keyFor('issue-'.$owner))['invoice_id']===$created['invoice_id'],'copy durable receipt revalidates canonical destination');
 essentialThrows(fn()=>$copies->preview($owner===$a?$b:$a,7,$source),'company_invoice_copy_unavailable','cross-company source ID denied before line hydration');
 essentialThrows(fn()=>$copies->preview($owner,7,42),'company_invoice_copy_unavailable','NULL legacy source is never adopted');
 essentialThrows(fn()=>$copies->create($owner,7,$source,array_replace($review,['note'=>'Changed']),$keyFor('issue-'.$owner)),'company_invoice_copy_conflict','copy idempotency key cannot change review');
 $stale=$review;$stale['source_revision']=str_repeat('0',64);essentialThrows(fn()=>$copies->create($owner,7,$source,$stale,$keyFor('stale-'.$owner)),'company_invoice_copy_stale','stale review cannot issue another invoice');
 $beforeInvoices=(int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n;$db->failOn='INSERT INTO '.$p.'issuers_audit';
 essentialThrows(fn()=>$copies->create($owner,7,$source,$review,$keyFor('rollback-'.$owner)),'issuer_storage_failed','late audit failure rolls back copy and source receipt');$db->failOn=null;
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'invoices')->row()->n===$beforeInvoices,'failed copy leaves no orphan invoice');
 essentialCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.'company_invoice_copy_requests WHERE request_key=?',[$keyFor('rollback-'.$owner)])->row()->n===0,'failed copy leaves no request adoption');
}
essentialCheck($snapshot===$db->query('SELECT * FROM '.$p.'invoices WHERE id=42')->result_array(),'copy flows preserve NULL legacy bytes');
echo 'Recreated439 canonical copies: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No provider calls.'.PHP_EOL;
