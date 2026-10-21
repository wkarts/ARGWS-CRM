<?php
/** Native disposable credit tables used by the existing canonical reversal writer. */
require_once APPPATH.'services/CompanyCreditSchema.php';
foreach(['creditnotes','creditnote_refunds'] as $name){
 preg_match('/CREATE TABLE `tbl'.preg_quote($name,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$native,$m);if(!$m)throw new RuntimeException('native fixture missing '.$name);
 $sql=str_replace('`tbl'.$name.'`','`'.$p.$name.'`',$m[0]);$sql=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$sql);
 if($db->driver()==='sqlite'){$sql=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$sql);$sql=preg_replace('/enum\([^)]*\)/','varchar(50)',$sql);}$db->query($sql);
}
if($db->driver()==='mysql'){CompanyCreditSchema::ensure($db,$p);CompanyCreditSchema::ensure($db,$p);}else{
 $db->query("ALTER TABLE {$p}creditnotes ADD issuer_id int unsigned NULL DEFAULT NULL");
 $db->query("CREATE TABLE {$p}company_credit_documents(credit_id int PRIMARY KEY,issuer_id int NOT NULL,actor_id int NOT NULL,request_key varchar(64) UNIQUE,input_hash varchar(64),number int,identity_json text,recipient_json text,items_json text,created_at datetime,UNIQUE(issuer_id,number))");
 $db->query("CREATE TABLE {$p}company_credit_entries(request_key varchar(64) PRIMARY KEY,issuer_id int NOT NULL,actor_id int,kind varchar(20),credit_id int NULL,invoice_id int NULL,native_id int,parent_key varchar(64) NULL,amount decimal(15,2),input_hash varchar(64),reference_hash varchar(64),reference varchar(191),book_date date,reason text,created_at datetime,UNIQUE(issuer_id,kind,reference_hash))");
}
unset($db->data_cache['table_names']);
