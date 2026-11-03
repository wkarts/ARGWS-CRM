<?php
declare(strict_types=1);
/** Explicit SQLite metadata double. Never used with MySQL or claimed as engine/schema evidence. */
final class DeclaredMySqlMetadata
{
    public static function parse(array $parts):array
    {
        $m=['columns'=>[],'indexes'=>[],'foreign'=>[],'rules'=>[],'engine'=>[['ENGINE'=>'InnoDB']]];
        foreach($parts as $part){
            if(preg_match('/^`([^`]+)` (varchar\([0-9]+\)|bigint unsigned|int unsigned|int|mediumtext|text|datetime)(.*)$/D',$part,$v)){
                preg_match('/COLLATE ([a-z0-9_]+)/',$v[3],$c);
                $m['columns'][]=['COLUMN_NAME'=>$v[1],'COLUMN_TYPE'=>$v[2],'IS_NULLABLE'=>str_contains($v[3],'NOT NULL')?'NO':'YES','COLUMN_DEFAULT'=>null,'COLLATION_NAME'=>$c[1]??null,'EXTRA'=>str_contains($v[3],'AUTO_INCREMENT')?'auto_increment':''];
                if(str_contains($v[3],'PRIMARY KEY'))$m['indexes'][]=['INDEX_NAME'=>'PRIMARY','COLUMN_NAME'=>$v[1],'NON_UNIQUE'=>0,'SUB_PART'=>null];
            }elseif(preg_match('/^PRIMARY KEY \(([^)]+)\)$/D',$part,$v)){
                foreach(explode(',',str_replace('`','',$v[1]))as$col)$m['indexes'][]=['INDEX_NAME'=>'PRIMARY','COLUMN_NAME'=>$col,'NON_UNIQUE'=>0,'SUB_PART'=>null];
            }elseif(preg_match('/^(UNIQUE )?KEY `([^`]+)` \(([^)]+)\)$/D',$part,$v)){
                foreach(explode(',',str_replace('`','',$v[3])) as $col)$m['indexes'][]=['INDEX_NAME'=>$v[2],'COLUMN_NAME'=>$col,'NON_UNIQUE'=>$v[1]===''?1:0,'SUB_PART'=>null];
            }elseif(preg_match('/^(?:CONSTRAINT `[A-Za-z0-9_]{1,64}` )?FOREIGN KEY \(([^)]+)\) REFERENCES `([^`]+)` \(([^)]+)\)$/D',$part,$v)){
                $n='fk'.count($m['rules']);$cols=explode(',',str_replace('`','',$v[1]));$refs=explode(',',str_replace('`','',$v[3]));
                foreach($cols as $i=>$col)$m['foreign'][]=['CONSTRAINT_NAME'=>$n,'COLUMN_NAME'=>$col,'REFERENCED_TABLE_NAME'=>$v[2],'REFERENCED_COLUMN_NAME'=>$refs[$i]];
                $m['rules'][]=['UPDATE_RULE'=>'RESTRICT','DELETE_RULE'=>'RESTRICT'];
            }
        }return $m;
    }
}
