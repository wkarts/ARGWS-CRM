<?php
declare(strict_types=1);
require_once __DIR__.'/../../application/services/NativeCompanyHeaderSchema.php';
require_once __DIR__.'/../../application/services/NativeCompanyKnowledgeSchema.php';
function assertExplicitFkIndexes(array $definitions):void {
    foreach($definitions as $table=>$parts){
        $indexes=[];$foreign=[];
        foreach($parts as $definition){
            if(preg_match('/^(?:UNIQUE )?KEY `[^`]+` \(([^)]+)\)$/D',$definition,$m))$indexes[]=explode(',',str_replace('`','',$m[1]));
            if(preg_match('/^`([^`]+)` .*PRIMARY KEY/',$definition,$m))$indexes[]=[$m[1]];
            if(preg_match('/^FOREIGN KEY \(([^)]+)\)/',$definition,$m))$foreign[]=explode(',',str_replace('`','',$m[1]));
        }
        foreach($foreign as $columns){$covered=false;foreach($indexes as $index)if(array_slice($index,0,count($columns))===$columns)$covered=true;
            if(!$covered)throw new RuntimeException('Missing explicit left-prefix FK index: '.$table);
        }
    }
}
$all=NativeCompanyHeaderSchema::definitions('tbl')+NativeCompanyKnowledgeSchema::definitions('tbl');assertExplicitFkIndexes($all);
$bad=$all;$bad[NativeCompanyHeaderSchema::NOTIFICATIONS]=array_values(array_filter($bad[NativeCompanyHeaderSchema::NOTIFICATIONS],static fn($v)=>$v!=='KEY `notification_contact_actor` (`contact_actor_id`)'));
try{assertExplicitFkIndexes($bad);throw new LogicException('FK automatic-index regression not caught');}catch(RuntimeException $e){if(!str_starts_with($e->getMessage(),'Missing explicit'))throw $e;}
// Types are tied to actual versioned parent DDL, not inferred from SQLite affinity.
$seed=file_get_contents(__DIR__.'/../../install/database.sql');
foreach(['contacts'=>'id','staff'=>'staffid'] as $table=>$column){if(!preg_match('/CREATE TABLE `tbl'.preg_quote($table,'/').'` \(\s*`'.preg_quote($column,'/').'` int NOT NULL/s',$seed))throw new RuntimeException('Native parent signed int changed');}
foreach($all as $table=>$parts)foreach($parts as $part)if(preg_match('/^FOREIGN KEY \(`([^`]+)`\) REFERENCES `tbl(contacts|staff)`/',$part,$m)){
    $matched=false;foreach($parts as $definition)if(preg_match('/^`'.preg_quote($m[1],'/').'` int (?:NOT NULL|NULL)$/D',$definition))$matched=true;
    if(!$matched)throw new RuntimeException('Signed parent/child type mismatch');
}
echo "Header/knowledge FK left-prefix indexes and signed native parent types passed; MySQL metadata execution still required\n";
