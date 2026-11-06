<?php
/** Real native dependency DDL for the additive Lead fields; no legacy rows are adopted. */
foreach(['countries'=>'country_id','currencies'=>'id','customfields'=>'id','customfieldsvalues'=>'id','tags'=>'id','taggables'=>null]as$table=>$pk){
    if($db->table_exists($p.$table))continue;
    if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$match))throw new RuntimeException('Original dependency table missing: '.$table);
    $ddl=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);if($pk!==null)$ddl=str_replace('`'.$pk.'` int NOT NULL,','`'.$pk.'` int NOT NULL AUTO_INCREMENT PRIMARY KEY,',$ddl);$db->query($ddl);
}
if(!$db->query('SELECT id FROM '.$p.'currencies')->result_array())$db->query("INSERT INTO {$p}currencies(id,symbol,name,decimal_separator,thousand_separator,placement,isdefault) VALUES(1,'R$','BRL',',','.','before',1),(2,'€','EUR',',','.','before',0)");
if(!$db->query('SELECT country_id FROM '.$p.'countries')->result_array())$db->query("INSERT INTO {$p}countries(country_id,short_name,long_name,iso2) VALUES(30,'Brazil','Federative Republic of Brazil','BR'),(74,'France','French Republic','FR')");
NativeCompanyCustomFieldSchema::ensure($db,$p);NativeCompanyWorkTagsSchema::ensure($db,$p);CompanyLeadMetadataSchema::ensure($db,$p);CompanyLeadMetadataSchema::ensure($db,$p);
unset($db->data_cache['table_names']);
