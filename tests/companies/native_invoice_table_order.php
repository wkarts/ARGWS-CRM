<?php
require __DIR__.'/native_invoice_table_controller.php';
require_once APPPATH.'services/NativeCompanyTableOrder.php';
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');preg_match('/CREATE TABLE `tbluser_meta` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('Native user_meta schema missing');
$sql=str_replace('`tbluser_meta`','`'.$p.'user_meta`',$match[0]);$sql=str_replace('`umeta_id` bigint UNSIGNED NOT NULL,','`umeta_id` '.($db->driver()==='mysql'?'bigint UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);if($db->driver()==='sqlite')$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$db->query($sql);
$ci->input->posts=$wire+['last_order_identifier'=>'invoices'];$ci->input->verb='POST';$ci->output->status=200;CompanyRequestScope::$issuer=$a;
$aOrder=new NativeCompanyTableOrder($db,$p,$a,7);$bOrder=new NativeCompanyTableOrder($db,$p,$b,7);
$controller->table();essentialCheck($ci->output->status===200&&$aOrder->read('invoices')==='','disabled native setting causes no preference write');
$GLOBALS['nativeTableFixtureOptions']['save_last_order_for_tables']='1';$controller->table();essentialCheck($ci->output->status===200&&$aOrder->read('invoices')==='[[0,"desc"]]'&&$bOrder->read('invoices')==='','successful exact native table request stores only issuer/actor ordering');
$ci->input->posts['order']=[['column'=>1,'dir'=>'asc']];CompanyRequestScope::$csrf=false;$controller->table();CompanyRequestScope::$csrf=true;
essentialCheck($aOrder->read('invoices')==='[[0,"desc"]]','failed CSRF cannot persist order preference');
$ci->input->posts['last_order_identifier']='projects';$controller->table();essentialCheck($ci->output->status===422&&$aOrder->read('invoices')==='[[0,"desc"]]','invoice wire cannot persist another resource identifier');
echo 'Native invoice table-order HTTP boundary double: '.$GLOBALS['essentialChecks']." cumulative PASS.\n";
