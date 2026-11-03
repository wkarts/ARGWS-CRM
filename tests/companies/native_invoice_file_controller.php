<?php
require __DIR__.'/native_invoice_table_controller.php';
require_once APPPATH.'services/NativeCompanyInvoiceFiles.php';
if($db->driver()==='mysql'){CompanyInvoiceAttachmentSchema::ensure($db,$p);}else{
 $parts=[];foreach((new ReflectionClass(CompanyInvoiceAttachmentSchema::class))->getConstant('FIELDS') as$name=>$type)$parts[]='`'.$name.'` '.$type.' NOT NULL'.($name==='file_key'?' PRIMARY KEY':'');
 $db->query('CREATE TABLE '.$p.'company_invoice_attachments('.implode(',',$parts).')');
}
if(!$db->table_exists($p.'files')){
 // Native install columns, not a sidecar invented ID; original key is allocated by FileRows.
 $sql=file_get_contents(__DIR__.'/../../install/database.sql');
 if(!preg_match('/CREATE TABLE `tblfiles` \((.*?)\) ENGINE=/s',$sql,$m))throw new RuntimeException('native files schema absent');
 $ddl='CREATE TABLE '.$p.'files ('.$m[1].')';
 if($db->driver()==='sqlite'){$ddl=preg_replace('/`id` int(?:\([^)]*\))? NOT NULL/','`id` INTEGER PRIMARY KEY AUTOINCREMENT',$ddl);$ddl=preg_replace('/,\s*(?:UNIQUE )?KEY [^\n]+/','',$ddl);$ddl=preg_replace("/ COMMENT '[^']*'/",'',$ddl);$ddl=preg_replace('/ COLLATE [a-z0-9_]+| CHARACTER SET [a-z0-9_]+/i','',$ddl);}
 if($db->driver()==='mysql')$ddl=str_replace('`id` int NOT NULL,','`id` int NOT NULL AUTO_INCREMENT PRIMARY KEY,',$ddl).' ENGINE=InnoDB';
 $db->query($ddl);
}
unset($db->data_cache['table_names']);NativeCompanyInvoiceFileSchema::ensure($db,$p);unset($db->data_cache['table_names']);
$files=new NativeCompanyInvoiceFiles($db,$p);$file=$files->addExternal($a,7,$main,hash('sha256','native-file-controller'),'Synthetic evidence','dropbox','https://www.dropbox.com/s/synthetic/document.pdf');
$ci->input->verb='POST';$ci->input->posts=['revision'=>1];CompanyRequestScope::$issuer=$b;$controller->delete_attachment($file['id']);essentialCheck($ci->output->status===403,'same actor foreign native files ID denied before deletion');
CompanyRequestScope::$issuer=$a;$ci->input->verb='GET';$controller->delete_attachment($file['id']);essentialCheck($ci->output->status===403,'native original delete URL cannot mutate through GET');
$ci->input->verb='POST';$ci->input->posts=['revision'=>2];$controller->delete_attachment($file['id']);essentialCheck($ci->output->status===409,'stale file revision denied');
CompanyRequestScope::$csrf=false;$ci->input->posts=['revision'=>1];$controller->delete_attachment($file['id']);essentialCheck($ci->output->status===403,'captured/framework CSRF failure denies native deletion');CompanyRequestScope::$csrf=true;
$ci->input->posts=['revision'=>1,'invoice_id'=>$main];$controller->delete_attachment($file['id']);essentialCheck($ci->output->status===422,'caller cannot supply another parent to authorize attachment');
$ci->input->posts=['revision'=>1];$ci->output->status=200;$controller->delete_attachment($file['id']);
essentialCheck($ci->output->status===200&&json_decode($ci->output->body,true)===true&&$files->page($a,7,$main)===[],'same delete action uses actual native files ID and deactivates scoped projection');
$controller->delete_attachment($file['id']);essentialCheck($ci->output->status===200&&json_decode($ci->output->body,true)===true,'same deletion retry remains idempotent');
essentialCheck(count($db->query('SELECT id FROM '.$p.'files WHERE id=?',[$file['id']])->result_array())===1,'deletion retains native metadata and audit history instead of deleting a global files row');
echo 'Native invoice file deletion: '.$GLOBALS['essentialChecks']." cumulative PASS. Controller boundary double, not HTTP/browser.\n";
