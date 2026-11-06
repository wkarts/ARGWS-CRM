<?php
// Actual native catalogue/profile schemas used by the new creation controls; no synthetic product columns.
require_once APPPATH.'services/NativeCompanyAuxiliarySchema.php';
require_once APPPATH.'services/CompanyClientProfileSchema.php';
foreach(['company_taxes','company_item_groups','company_items']as$table)if(!$db->table_exists($p.$table)){
    $ddl='CREATE TABLE '.$p.$table.'('.implode(',',NativeCompanyAuxiliarySchema::definitions($p)[$table]).') ENGINE=InnoDB';
    if($db->driver()==='sqlite')$ddl=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$ddl);$db->query($ddl);
}
$creditNativeSql=file_get_contents(dirname(__DIR__,2).'/install/database.sql');
foreach(['items','items_groups','taxes']as$table)if(!$db->table_exists($p.$table)){
    if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(.*?\) ENGINE=InnoDB[^;]*;/s',$creditNativeSql,$match))throw new RuntimeException('Native catalogue schema missing');
    $ddl=str_replace('`tbl'.$table.'`','`'.$p.$table.'`',$match[0]);
    $ddl=str_replace('`id` int NOT NULL,','`id` '.($db->driver()==='mysql'?'int NOT NULL AUTO_INCREMENT PRIMARY KEY':'INTEGER PRIMARY KEY AUTOINCREMENT').',',$ddl);
    if($db->driver()==='sqlite')$ddl=preg_replace('/ CHARACTER SET [A-Za-z0-9_]+ COLLATE [A-Za-z0-9_]+/','',$ddl);$db->query($ddl);
}
CompanyClientProfileSchema::ensure($db,$p);unset($db->data_cache['table_names']);

// The original client callback validates each project through CompanyProjects.
// Its canonical reader verifies the complete project/task schema on MySQL;
// a single company_projects table is insufficient even for a read callback.
foreach([CompanyProjectsSchema::class,CompanyProjectExtrasSchema::class,CompanyStandaloneTasksSchema::class] as $schema){
    if($db->driver()==='mysql'){$schema::ensure($db,$p);continue;}
    foreach($schema::definitions($p) as $table=>$parts){
        if($db->table_exists($p.$table))continue;
        $ddl='CREATE TABLE '.$p.$table.'('.implode(',',$parts).') ENGINE=InnoDB';
        $db->query(str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$ddl));
    }
}
unset($db->data_cache['table_names']);
