<?php
/** Additional disposable fixture tables are removed before the base fixture's FK cleanup. */
register_shutdown_function(static function():void{if(!isset($GLOBALS['db'],$GLOBALS['p'])||!preg_match('/^essential_[a-f0-9]{10}_$/D',$GLOBALS['p']))return;foreach(['company_contract_files','company_ticket_files'] as $suffix){$db=$GLOBALS['db'];if($db->table_exists($GLOBALS['p'].$suffix))$db->query('DROP TABLE '.$GLOBALS['p'].$suffix);}});
require __DIR__.'/contracts_operations.php';require_once APPPATH.'services/CompanyOperationFiles.php';
if($db->driver()==='mysql'){CompanyOperationFilesSchema::ensure($db,$p);CompanyOperationFilesSchema::ensure($db,$p);}else foreach(CompanyOperationFilesSchema::definitions($p) as $name=>$parts)$db->query(preg_replace('/ CHARACTER SET [a-z0-9_]+ COLLATE [a-z0-9_]+/','','CREATE TABLE `'.$p.$name.'` ('.implode(',',$parts).') ENGINE=InnoDB'));
unset($db->data_cache['table_names']);
