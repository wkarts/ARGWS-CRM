<?php
declare(strict_types=1);
/** DDL-only native prerequisites in an owned disposable test prefix. Never imports seed rows. */
final class NativeInstalledSchemaFixture
{
    public static function plan(string$source,string$prefix):array
    {
        if(!preg_match('/^essential_[a-f0-9]{10}_$/D',$prefix))throw new InvalidArgumentException('Disposable fixture prefix required');
        preg_match_all('/CREATE TABLE(?: IF NOT EXISTS)? `tbl([A-Za-z0-9_]+)` \(.*?\) ENGINE=InnoDB[^;]*;/s',$source,$creates,PREG_SET_ORDER);$tables=[];
        foreach($creates as$m){if(isset($tables[$m[1]]))throw new RuntimeException('Duplicate native DDL');$tables[$m[1]]=['create'=>self::prefix($m[0],$prefix),'alters'=>[]];}
        if(!$tables)throw new RuntimeException('Missing native DDL');
        preg_match_all('/ALTER TABLE `tbl([A-Za-z0-9_]+)`\s+([^;]*);/s',$source,$alters,PREG_SET_ORDER);
        foreach($alters as$m){if(!isset($tables[$m[1]])||!preg_match('/^\s*(?:ADD|MODIFY)\s/i',$m[2])||preg_match('/\b(?:INSERT\s+INTO|DELETE\s+FROM|UPDATE\s+`?tbl|DROP|TRUNCATE|RENAME|CHANGE)\b/i',$m[2]))throw new RuntimeException('Unsupported native DDL');$tables[$m[1]]['alters'][]=self::prefix($m[0],$prefix);}
        return$tables;
    }
    private static function prefix(string$sql,string$p):string{return preg_replace('/`tbl([A-Za-z0-9_]+)`/','`'.$p.'$1`',$sql);}
    public static function apply($db,string$prefix,string$source):array
    {
        if($db->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)!=='mysql')throw new RuntimeException('Real disposable MySQL required');
        $database=$db->pdo->query('SELECT DATABASE()')->fetchColumn();if(!is_string($database)||!preg_match('/^issuer_[A-Za-z0-9_]*module_audit$/D',$database))throw new RuntimeException('Dedicated audit schema required');
        $plan=self::plan($source,$prefix);$created=[];
        foreach($plan as$name=>$ddl){if($db->table_exists($prefix.$name))continue;$db->query($ddl['create']);$created[]=$name;unset($db->data_cache['table_names']);}
        // All referenced native indexes must exist before historical FK ALTERs.
        foreach([false,true]as$foreign)foreach($created as$name)foreach($plan[$name]['alters']as$sql)if(str_contains($sql,'FOREIGN KEY')===$foreign)$db->query($sql);
        unset($db->data_cache['table_names']);return$created;
    }
}
