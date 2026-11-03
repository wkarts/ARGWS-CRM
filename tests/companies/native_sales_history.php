<?php
/** Historical and issuer-owned sales share the original routes without sharing IDs or money. */
require __DIR__.'/sales.php';
require_once APPPATH.'services/NativeCompanySalesDirectory.php';
require_once APPPATH.'services/NativeCompanyLegacySales.php';
require_once APPPATH.'services/NativeCompanyLegacySalesPdf.php';
foreach(['estimates','proposals','leads','sales_activity'] as $name){
 if($db->table_exists($p.$name))continue;
 preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);
 if(!$match)throw new RuntimeException('Missing native table '.$name);
 $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);
 $sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
 if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}
 $db->query($sql);
}
unset($db->data_cache['table_names']);
$stamp='2026-01-02 10:00:00';$historicalId=$principalSale;
$db->query("INSERT INTO {$p}estimates(id,clientid,number,prefix,formatted_number,hash,datecreated,date,currency,subtotal,total_tax,total,discount_type,include_shipping,status,addedfrom,clientnote,terms,reference_no) VALUES(?,9999,713,'OLD-','OLD-000713',?,?,'2026-01-02',1,100.01,0,100.01,'after_tax',0,1,7,'Original note','Original terms','Original reference')",[$historicalId,str_repeat('f',32),$stamp]);
$db->query("INSERT INTO {$p}estimates(id,clientid,number,prefix,hash,datecreated,date,currency,subtotal,total,discount_type,include_shipping,status,addedfrom) VALUES(9001,?,714,'PRIVATE-',?,?,'2026-01-03',1,7,7,'after_tax',0,1,7)",[$ca,str_repeat('b',32),$stamp]);
$db->query("INSERT INTO {$p}proposals(id,subject,content,addedfrom,datecreated,total,subtotal,total_tax,discount_percent,discount_total,discount_type,currency,date,rel_id,rel_type,hash,proposal_to,status) VALUES(?, 'Historical proposal','<p>Historic <strong>content</strong></p>',7,?,100.01,100.01,0,0,0,'after_tax',1,'2026-01-02',9999,'customer',?,'Historical recipient',1)",[$historicalId,$stamp,str_repeat('e',32)]);
$db->query("INSERT INTO {$p}itemable(rel_id,rel_type,description,long_description,qty,rate,unit,item_order) VALUES(?,'estimate','Historical item','Native description',1,100.01,'un',1)",[$historicalId]);
$legacyItems=$db->query("SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='estimate'",[$historicalId])->result_array();
$directory=new NativeCompanySalesDirectory($db,$p);$legacySales=new NativeCompanyLegacySales($db,$p);
$primaryRows=$directory->query($principal,7,'estimates');$ids=array_column($primaryRows['items'],'id');$canonical=NativeCompanyResourceId::encode('company_estimate',$principalSale);
essentialCheck(in_array((string)$historicalId,$ids,true)&&in_array($canonical,$ids,true)&&!in_array('9001',$ids,true),'principal sees historical and own estimates with colliding storage IDs, excludes another company customer');
$historical=$directory->document($principal,7,'estimates',$historicalId);$owned=$directory->document($principal,7,'estimates',$canonical);
essentialCheck($historical['origin']==='legacy'&&$historical['formatted_number']==='OLD-000713'&&$historical['payload']['items'][0]['id']===(int)$legacyItems[0]['id']&&$owned['origin']==='owned','details retain exact historical number and item IDs and select canonical owned source');
foreach([$a,$b] as $issuer){essentialThrows(fn()=>$directory->document($issuer,7,'estimates',$historicalId),'company_sales_denied','secondary cannot adopt legacy numeric ID');foreach($directory->query($issuer,7,'estimates')['items'] as $row)essentialCheck($row['origin']==='owned','secondary directory contains only owned records');}
essentialThrows(fn()=>$directory->document($principal,12,'estimates',$historicalId),'company_sales_denied','restricted actor cannot read primary sales');
essentialThrows(fn()=>$directory->document($principal,7,'proposals',$canonical),'native_resource_namespace_mismatch','typed estimate cannot select proposal with same underlying number');
$search=$directory->query($principal,7,'estimates',['search'=>'Original note','length'=>1]);essentialCheck($search['filtered']===1&&$search['items'][0]['id']===(string)$historicalId,'historical search is applied before pagination');
essentialCheck($directory->query($principal,7,'estimates',['search'=>'%_'])['items']===[],'historical search escapes wildcard input');
$first=$directory->query($principal,7,'estimates',['length'=>1]);$second=$directory->query($principal,7,'estimates',['length'=>1,'after'=>$first['next']]);essentialCheck(count($second['items'])===1&&$first['items'][0]['id']!==$second['items'][0]['id'],'source-aware cursor does not drop colliding historical and owned IDs');
$before=$db->query("SELECT * FROM {$p}estimates WHERE id=?",[$historicalId])->row_array();
$legacySales->update($principal,7,'estimates',$historicalId,$historical['revision_token'],['clientnote'=>'Reviewed note','reference_no'=>'Reviewed']);
$editAudit=$legacySales->document($principal,7,'estimates',$historicalId)['edit_history'];$change=json_decode($editAudit[0]['metadata_json'],true);essentialCheck($change['before']['clientnote']==='Original note'&&$change['after']['clientnote']==='Reviewed note','Historical descriptive edit retains exact before/after in its own source-bound audit');
$after=$db->query("SELECT * FROM {$p}estimates WHERE id=?",[$historicalId])->row_array();$expected=array_replace($before,['clientnote'=>'Reviewed note','reference_no'=>'Reviewed']);essentialCheck($after===$expected&&$db->query("SELECT * FROM {$p}itemable WHERE rel_id=? AND rel_type='estimate'",[$historicalId])->result_array()===$legacyItems,'permitted historical edit changes only submitted descriptive fields, never item IDs or stored totals');
essentialThrows(fn()=>$legacySales->update($principal,7,'estimates',$historicalId,$historical['revision_token'],['terms'=>'stale']),'company_sales_stale','stale editor cannot overwrite history');
$fresh=$legacySales->document($principal,7,'estimates',$historicalId);$db->failOn='INSERT INTO '.$p.'issuers_audit';essentialThrows(fn()=>$legacySales->update($principal,7,'estimates',$historicalId,$fresh['revision_token'],['terms'=>'rollback']),'issuer_storage_failed','failed historical audit rolls back edit');$db->failOn=null;essentialCheck($db->query("SELECT * FROM {$p}estimates WHERE id=?",[$historicalId])->row_array()===$after,'audit failure leaves full original row intact');
$proposal=$directory->document($principal,7,'proposals',$historicalId);$legacySales->update($principal,7,'proposals',$historicalId,$proposal['revision_token'],['content'=>'<p>Allowed <strong>text</strong><script>alert(1)</script></p>']);$proposal=$directory->document($principal,7,'proposals',$historicalId);essentialCheck(str_contains($proposal['legacy_content'],'<strong>text</strong>')&&!str_contains($proposal['legacy_content'],'<script>'),'proposal content uses bundled purifier while preserving supported formatting');
$db->query("UPDATE {$p}estimates SET invoiceid=42,status=4 WHERE id=?",[$historicalId]);$linked=$directory->document($principal,7,'estimates',$historicalId);essentialCheck($linked['invoice_id']===42&&!$linked['editable'],'converted historical estimate retains original linked invoice and cannot be edited');essentialThrows(fn()=>$legacySales->update($principal,7,'estimates',$historicalId,$linked['revision_token'],['terms'=>'illegal']),'company_sales_denied','converted history remains immutable');
$pdf=NativeCompanyLegacySalesPdf::prepare($linked)->Output('','S');$legacySales->confirmPdf($principal,7,'estimates',$historicalId,$linked['revision_token']);essentialCheck(str_starts_with($pdf,'%PDF-')&&strlen($pdf)>1000,'real TCPDF renders historical document with stored values after authorized read');
essentialCheck($db->query("SELECT * FROM {$p}invoices WHERE id=42")->result_array()===$legacyBefore,'history list edit and PDF never rewrite linked native invoice');
echo 'Native sales history/collision/edit/PDF/isolation: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().').'.PHP_EOL;
