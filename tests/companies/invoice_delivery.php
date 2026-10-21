<?php
/** Native company invoice notifications, real PDO queue, templates and no external transport. */
require __DIR__.'/invoices.php';
require_once APPPATH.'services/CompanyInvoiceDelivery.php';
foreach(['contacts','contact_permissions','mail_queue','emailtemplates'] as $table){
    preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('native fixture missing '.$table);
    $sql=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);$key=$table==='emailtemplates'?'emailtemplateid':'id';
    $sql=str_replace('`'.$key.'` int NOT NULL,','`'.$key.'` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
    if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}
    $db->query($sql);
}
unset($db->data_cache['table_names']);
if($db->driver()==='mysql'){
    CompanyMailQueueSchema::ensure($db,$p);CompanyEmailTemplateSchema::ensure($db,$p);
    require_once APPPATH.'migrations/422_version_422.php';$m=new Migration_Version_422();$m->db=$db;$m->up();$m->up();
}else{
    $db->query("ALTER TABLE {$p}mail_queue ADD issuer_id int unsigned");$db->query("ALTER TABLE {$p}emailtemplates ADD issuer_id int unsigned");
    $db->query("CREATE TABLE {$p}company_invoice_deliveries(request_key varchar(64) PRIMARY KEY,issuer_id int unsigned NOT NULL,actor_id int NOT NULL,invoice_id int NOT NULL,contact_id int NOT NULL,revision varchar(64) NOT NULL,recipient_hash varchar(64) NOT NULL,queue_id int NOT NULL UNIQUE,created_at datetime NOT NULL)");
}
CompanyEmailTemplates::reconcile($db,$p);
foreach([$a=>'a',$b=>'b'] as $issuer=>$letter)foreach(['mail_engine'=>'codeigniter','email_protocol'=>'smtp','smtp_host'=>'smtp-'.$letter.'.invalid','smtp_port'=>'587','smtp_email'=>$letter.'@example.invalid','smtp_password'=>'encrypted-'.$letter,'email_signature'=>'Only '.$letter] as $key=>$value)$settings->mutate($issuer,7,'update',$key,$value,null);
foreach([101=>$ca,202=>$cb] as $contact=>$client){$db->query("INSERT INTO {$p}contacts(id,userid,firstname,lastname,email,phonenumber,datecreated,active) VALUES(?,?,'Synthetic','Contact','same@example.invalid','',?,1)",[$contact,$client,date('Y-m-d H:i:s')]);$db->query("INSERT INTO {$p}contact_permissions(userid,permission_id) VALUES(?,1)",[$contact]);}
$delivery=new CompanyInvoiceDelivery($db,$p);$decrypt=fn($value)=>'synthetic:'.$value;$base='https://crm.example.invalid';
$ia=$s->create($a,7,$input,hash('sha256','delivery-a'));$ib=$s->create($b,7,array_replace($input,['client_id'=>$cb]),hash('sha256','delivery-b'));
$revisionA=$s->revision($a,7,$ia);$revisionB=$s->revision($b,7,$ib);$keyA=hash('sha256','delivery-queue-a');$keyB=hash('sha256','delivery-queue-b');
essentialCheck(array_column($delivery->state($a,7,$ia)['recipients'],'id')==[101]&&array_column($delivery->state($b,7,$ib)['recipients'],'id')==[202],'A/B same-email private contacts stay separated');
$qid=$delivery->queue($a,7,$ia,101,$revisionA,$keyA,$base,$decrypt);
essentialCheck($delivery->queue($a,7,$ia,101,$revisionA,$keyA,$base,$decrypt)===$qid,'double submission returns same persisted queue receipt');
$qb=$delivery->queue($b,7,$ib,202,$revisionB,$keyB,$base,$decrypt);
$queued=$db->query("SELECT * FROM {$p}mail_queue ORDER BY id")->result_array();
essentialCheck(count($queued)===2&&array_map('intval',array_column($queued,'issuer_id'))===[$a,$b],'one atomic queue row per explicit origin');
foreach([$a=>$queued[0],$b=>$queued[1]] as $issuer=>$mail){
    essentialCheck(str_contains($mail['message'],'/company_portal/index/'.$issuer)&&!str_contains($mail['message'],'#token=')&&!str_contains($mail['message'],'/invoice/'),'only authenticated own-company portal URL is sent');
    essentialCheck(str_contains($mail['message'],'Only '.($issuer===$a?'a':'b')),'email uses only issuer signature and template');
    $delivery->assertDispatch((object)$mail,$issuer===$a?$keyA:$keyB);
}
essentialThrows(fn()=>$delivery->queue($b,7,$ia,202,$revisionA,hash('sha256','cross'),$base,$decrypt),'company_invoice_delivery_denied','cross-company direct invoice cannot queue');
essentialThrows(fn()=>$delivery->queue($a,7,$ia,202,$revisionA,hash('sha256','cross-contact'),$base,$decrypt),'company_invoice_delivery_denied','matching email never authorizes another company contact');
essentialThrows(fn()=>$delivery->queue($b,7,$ib,202,$revisionB,$keyA,$base,$decrypt),'company_invoice_delivery_conflict','request-key replay across origins conflicts');
$customers->setSharing($a,7,$ca,[$b]);$shared=$s->create($b,7,$input,hash('sha256','delivery-shared'));
essentialThrows(fn()=>$delivery->queue($b,7,$shared,101,$s->revision($b,7,$shared),hash('sha256','shared-contact'),$base,$decrypt),'company_invoice_delivery_denied','shared identity never exposes owner contact');
$db->query("INSERT INTO {$p}contact_permissions(userid,permission_id) VALUES(101,1)");
essentialThrows(fn()=>$delivery->assertDispatch((object)$queued[0],$keyA),'company_invoice_delivery_denied','ambiguous duplicate native contact permission fails closed like the portal');
$db->query("DELETE FROM {$p}contact_permissions WHERE userid=101");
essentialThrows(fn()=>$delivery->assertDispatch((object)$queued[0],$keyA),'company_invoice_delivery_denied','revoked portal permission blocks worker before transport');
$db->query("INSERT INTO {$p}contact_permissions(userid,permission_id) VALUES(101,1)");
$db->query("UPDATE {$p}contacts SET email='changed@example.invalid' WHERE id=101");
essentialThrows(fn()=>$delivery->assertDispatch((object)$queued[0],$keyA),'company_invoice_delivery_denied','recipient edit never redirects old queued notification');
$db->query("UPDATE {$p}contacts SET email='same@example.invalid' WHERE id=101");
$forged=(object)$queued[0];$forged->bcc='other@example.invalid';essentialThrows(fn()=>$delivery->assertDispatch($forged,$keyA),'company_invoice_delivery_denied','queue cannot acquire extra recipient');
$db->query("UPDATE {$p}staff SET active=0 WHERE staffid=7");essentialThrows(fn()=>$delivery->assertDispatch((object)$queued[0],$keyA),'company_invoice_delivery_denied','staff revocation applies to pending notifications');$db->query("UPDATE {$p}staff SET active=1 WHERE staffid=7");
$edit=$input;$edit['items'][0]['rate']='45.00';$s->change($a,7,$ia,$revisionA,$edit);
essentialThrows(fn()=>$delivery->assertDispatch((object)$queued[0],$keyA),'company_invoice_stale','outdated invoice status or monetary revision blocks sending');
essentialThrows(fn()=>$delivery->queue($a,7,$ia,101,$revisionA,hash('sha256','stale-delivery'),$base,$decrypt),'company_invoice_stale','old sender page cannot notify a newer invoice');
$before=count($queued);$db->failOn='INSERT INTO '.$p.'issuers_audit';
essentialThrows(fn()=>$delivery->queue($b,7,$ib,202,$revisionB,hash('sha256','delivery-audit-fail'),$base,$decrypt),'issuer_storage_failed','late audit failure rolls back queue and receipt');$db->failOn=null;
essentialCheck((int)$db->query("SELECT COUNT(*) n FROM {$p}mail_queue")->row()->n===$before&&(int)$db->query("SELECT COUNT(*) n FROM {$p}company_invoice_deliveries")->row()->n===$before,'no orphan queued message or receipt after failed audit');
$db->query("DELETE FROM {$p}mail_queue WHERE id=?",[$qb]);essentialCheck($delivery->state($b,7,$ib)['history'][0]['status']==='archived','queue cleanup preserves receipt without claiming delivery');
essentialCheck($delivery->queue($b,7,$ib,202,$revisionB,$keyB,$base,$decrypt)===$qb,'retry after queue cleanup cannot send a duplicate');
if($db->driver()==='mysql'){
    $db->query("ALTER TABLE {$p}company_invoice_deliveries ALTER COLUMN contact_id SET DEFAULT 0");essentialThrows(fn()=>CompanyInvoiceDeliverySchema::ensure($db,$p),'issuer_invoice_delivery_schema_invalid','default zero receipt metadata is rejected');
    $db->query("ALTER TABLE {$p}company_invoice_deliveries ALTER COLUMN contact_id DROP DEFAULT");CompanyInvoiceDeliverySchema::ensure($db,$p);
    $db->query("ALTER TABLE {$p}company_invoice_deliveries DROP INDEX delivery_queue");$db->query("ALTER TABLE {$p}company_invoice_deliveries ADD INDEX delivery_queue(queue_id)");essentialThrows(fn()=>CompanyInvoiceDeliverySchema::ensure($db,$p),'issuer_invoice_delivery_schema_invalid','non-unique queue receipt index is rejected');
    $db->query("ALTER TABLE {$p}company_invoice_deliveries DROP INDEX delivery_queue");$db->query("ALTER TABLE {$p}company_invoice_deliveries ADD UNIQUE INDEX delivery_queue(queue_id)");CompanyInvoiceDeliverySchema::ensure($db,$p);
}
echo 'Company invoice notification/queue revalidation: '.$GLOBALS['essentialChecks'].' cumulative checks passed ('.$db->driver().'). No SMTP delivery.'.PHP_EOL;
