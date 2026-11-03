<?php
// Additional real native table definitions required by complete invoice table search/render.
require_once APPPATH.'services/NativeCompanyCustomFields.php';
require_once APPPATH.'services/NativeCompanyWorkTagsSchema.php';
require_once APPPATH.'services/CompanyInvoiceProjectSchema.php';
require_once APPPATH.'services/CompanyProjects.php';
require_once APPPATH.'services/NativeCompanyFilterSchema.php';
NativeCompanyFilterSchema::ensure($db,$p);
NativeCompanyCustomFieldSchema::ensure($db,$p);NativeCompanyWorkTagsSchema::ensure($db,$p);CompanyInvoiceProjectSchema::ensure($db,$p);
if(!$db->table_exists($p.'company_projects')){
 $parts=CompanyProjectsSchema::definitions($p)['company_projects'];$sql='CREATE TABLE '.$p.'company_projects('.implode(',',$parts).') ENGINE=InnoDB';
 if($db->driver()==='sqlite')$sql=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$sql);$db->query($sql);
}
$native=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['projects','tags','taggables','customfields','customfieldsvalues','payment_modes','filters','filter_defaults']as$name){
 if($db->table_exists($p.$name))continue;
 preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match);if(!$match)throw new RuntimeException('fixture definition missing');
 $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$match[0]);
 $sql=preg_replace_callback('/`id` (int(?: UNSIGNED)?) NOT NULL,/',static fn($m)=>'`id` '.($db->driver()==='mysql'?$m[1].' NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
 if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);
}

foreach(['firstname','lastname']as$field)if(!$db->field_exists($field,$p.'staff'))$db->query('ALTER TABLE '.$p.'staff ADD '.$field." VARCHAR(50) NOT NULL DEFAULT ''");

// DDL above invalidates CodeIgniter table-name metadata for dependent integration suites.
unset($db->data_cache['table_names']);
// Native table includes private drafts only after the additive projection is complete.
if($db->table_exists($p.'company_invoice_drafts')){
    require_once APPPATH.'services/NativeCompanyInvoiceDraftProjection.php';
    NativeCompanyInvoiceDraftProjectionSchema::ensure($db,$p);
    (new NativeCompanyInvoiceDraftProjection($db,$p))->backfill();
}
