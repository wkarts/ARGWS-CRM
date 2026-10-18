<?php

declare(strict_types=1);

/** Query boundary: SQL, bindings and identifiers never escape via framework HTML/query logs. */
final class IssuerStorage
{
    public static function query($db,string $sql,array $params=[])
    {
        return self::run($db,static fn()=>$db->query($sql,$params));
    }
    public static function fieldExists($db,string $field,string $table): bool
    {
        // A missing field is a legitimate fail-closed result, not a query failure.
        return (bool) self::run($db,static fn()=>[$db->field_exists($field,$table)])[0];
    }
    private static function run($db,callable $operation)
    {
        $flags=[];
        foreach(['db_debug','save_queries'] as $flag) { if(property_exists($db,$flag)) { $flags[$flag]=$db->$flag; $db->$flag=false; } }
        try {
            $result=$operation();
            if($result===false) { throw new RuntimeException('issuer_storage_failed'); }
            return $result;
        } catch(Throwable $error) { throw new RuntimeException('issuer_storage_failed'); }
        finally { foreach($flags as $flag=>$value) { $db->$flag=$value; } }
    }
}
