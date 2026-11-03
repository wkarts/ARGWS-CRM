<?php
require __DIR__.'/invoice_drafts.php';
$db->query('UPDATE '.$p.'invoices SET cancel_overdue_reminders=0 WHERE id=? AND issuer_id=?',[$cancelled,$b]);
$current=$copy->preview($b,7,$cancelled);$review=['source_revision'=>$current['revision'],'date'=>'2026-12-15','due_date'=>'2027-01-15','note'=>$current['note'],'confirm_amounts'=>'1'];$key=hash('sha256','native-preview-copy-preferences');
$before=$db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array();$id=$drafts->copyInvoice($b,7,$cancelled,$review,$key);$document=$drafts->document($b,7,$id);
essentialCheck($document['state']==='open'&&$document['invoice_id']===null&&$document['payload']['native_preferences']['cancel_overdue_reminders']===0,'copy is an unnumbered draft preserving source reminder preference');
essentialCheck($document['payload']['items']===$current['items']&&$drafts->copyInvoice($b,7,$cancelled,$review,$key)===$id,'copy retains exact source items and replay');
essentialThrows(fn()=>$drafts->copyInvoice($a,7,$cancelled,$review,hash('sha256','foreign-preview-copy')),'company_invoice_copy_unavailable','foreign company cannot copy source');
essentialCheck($db->query('SELECT * FROM '.$p.'invoices ORDER BY id')->result_array()===$before,'draft copy neither issues an invoice nor mutates the source');
require_once APPPATH.'services/NativeCompanyPaymentModes.php';
if(!$db->table_exists($p.'payment_modes')){preg_match('/CREATE TABLE `tblpayment_modes` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);$sql=str_replace('`tblpayment_modes`','`'.$p.'payment_modes`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$db->query($sql);}
if(!$db->table_exists($p.'expenses'))$db->query('CREATE TABLE '.$p.'expenses(id INT NOT NULL PRIMARY KEY)'.($db->driver()==='mysql'?' ENGINE=InnoDB':''));unset($db->data_cache['table_names']);NativeCompanyPaymentModesSchema::ensure($db,$p);
$modes=new NativeCompanyPaymentModes($db,$p);$mode=$modes->save($b,7,['name'=>'Synthetic copy mode','description'=>'','active'=>1,'show_on_pdf'=>1,'selected_by_default'=>0,'invoices_only'=>1,'expenses_only'=>0]);
$db->query('UPDATE '.$p.'invoices SET allowed_payment_modes=? WHERE id=? AND issuer_id=?',[serialize([$mode['id']]),$cancelled,$b]);$source=$copy->preview($b,7,$cancelled);$review['source_revision']=$source['revision'];
$withMode=$drafts->copyInvoice($b,7,$cancelled,$review,hash('sha256','preview-copy-with-mode'));$payload=$drafts->document($b,7,$withMode)['payload'];
essentialCheck($payload['native_preferences']['allowed_payment_modes']===[$mode['id']],'copied draft retains authorized source payment mode');
echo 'Draft copy preference, source isolation and replay verified; original copy action remains closed.'.PHP_EOL;
