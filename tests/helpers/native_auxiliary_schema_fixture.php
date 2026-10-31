<?php
/** Canonical auxiliary schema for focused fixtures. Production MySQL DDL is unchanged. */
require_once APPPATH.'services/NativeCompanyAuxiliarySchema.php';
$auxiliaryFixtureCreated=[];
if($db->driver()==='mysql'){
    foreach(array_keys(NativeCompanyAuxiliarySchema::fields())as$table)if(!$db->table_exists($p.$table))$auxiliaryFixtureCreated[]=$table;
    NativeCompanyAuxiliarySchema::ensure($db,$p);
}else{
    foreach(NativeCompanyAuxiliarySchema::definitions($p)as$table=>$parts){
        if($db->table_exists($p.$table))continue;
        $auto=null;foreach(NativeCompanyAuxiliarySchema::fields()[$table]as$name=>[$type])if(str_contains($type,'AUTO_INCREMENT'))$auto=$name;
        $parts=array_values(array_filter($parts,static fn($part)=>!str_starts_with($part,'KEY ')&&($auto===null||$part!=='PRIMARY KEY (`'.$auto.'`)')));
        foreach($parts as&$part){
            $part=str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part);
            $part=preg_replace('/UNIQUE KEY `[^`]+`/','UNIQUE',$part);
            if($auto!==null&&str_starts_with($part,'`'.$auto.'`'))$part='`'.$auto.'` INTEGER PRIMARY KEY AUTOINCREMENT';
        }unset($part);
        $db->query('CREATE TABLE '.$p.$table.' ('.implode(',',$parts).')');
    }
}
unset($db->data_cache['table_names']);
register_shutdown_function(static function()use($db,$p,$auxiliaryFixtureCreated){
    if($db->driver()==='mysql')foreach(array_reverse($auxiliaryFixtureCreated)as$table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);
});
