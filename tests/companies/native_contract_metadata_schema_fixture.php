<?php
/** Evolved disposable parent DDL for SQLite; MySQL executes the production migration helper. */
require_once __DIR__.'/native_contract_index_schema_fixture.php';
require_once APPPATH.'services/CompanyContractMetadataSchema.php';
require_once APPPATH.'services/CompanyProjectsSchema.php';
require_once APPPATH.'services/CompanyProjectExtrasSchema.php';
require_once APPPATH.'services/CompanyStandaloneTasksSchema.php';
if($db->driver()==='sqlite'){
    $due=array_values(array_filter($db->query('PRAGMA table_info(`'.$p.'company_contracts`)')->result_array(),static fn($row)=>$row['name']==='due_date'))[0];
    if((int)$due['notnull']===1){
        $before=$db->query('SELECT * FROM '.$p.'company_contracts ORDER BY id')->result_array();
        $indexes=$db->query("SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name=? AND sql IS NOT NULL",[$p.'company_contracts'])->result_array();
        $parts=array_map(static fn($part)=>str_replace(['`due_date` date NOT NULL',' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin'],['`due_date` date NULL',''],$part),CompanyContractsSchema::definitions($p)['company_contracts']);
        $foreign=(int)$db->pdo->query('PRAGMA foreign_keys')->fetchColumn();$db->pdo->exec('PRAGMA foreign_keys=OFF');
        try{
            $db->query('CREATE TABLE '.$p.'contract_nullable_fixture ('.implode(',',$parts).') ENGINE=InnoDB');
            $db->query('INSERT INTO '.$p.'contract_nullable_fixture SELECT * FROM '.$p.'company_contracts');
            $db->query('DROP TABLE '.$p.'company_contracts');$db->query('ALTER TABLE '.$p.'contract_nullable_fixture RENAME TO '.$p.'company_contracts');
            foreach($indexes as$index)$db->query($index['sql']);
        }finally{$db->pdo->exec('PRAGMA foreign_keys='.$foreign);}
        if($db->query('SELECT * FROM '.$p.'company_contracts ORDER BY id')->result_array()!==$before)throw new RuntimeException('contract_fixture_changed_historical_rows');
    }
}
$contractMetadataMigration=CompanyContractMetadataSchema::ensure($db,$p);
CompanyContractMetadataSchema::ensure($db,$p);
foreach([CompanyProjectsSchema::class,CompanyProjectExtrasSchema::class,CompanyStandaloneTasksSchema::class]as$schema){
    if($db->driver()==='mysql'){$schema::ensure($db,$p);continue;}
    foreach($schema::definitions($p)as$table=>$parts){
        if($db->table_exists($p.$table))continue;
        $parts=array_map(static fn($part)=>str_replace(' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin','',$part),$parts);
        $db->query('CREATE TABLE '.$p.$table.' ('.implode(',',$parts).') ENGINE=InnoDB');
    }
}
unset($db->data_cache['table_names']);
register_shutdown_function(static function()use($db,$p){if($db->driver()==='mysql')$db->query('DROP TABLE IF EXISTS '.$p.CompanyContractMetadataSchema::TABLE);});
