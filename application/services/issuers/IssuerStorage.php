<?php

declare(strict_types=1);

/** Query boundary: SQL, bindings and identifiers never escape via framework HTML/query logs. */
final class IssuerStorage
{
    /** Exact JSON string comparison across SQLite, MySQL and MariaDB. */
    public static function jsonStringColumn($db,string $column,string $key): string
    {
        if(!preg_match('/^[a-z_][a-z0-9_]*$/D',$column)||!preg_match('/^[a-z_][a-z0-9_]*$/D',$key))throw new InvalidArgumentException('Invalid JSON column or key');
        $connection=$db->pdo??$db->conn_id??null;
        $driver=$connection instanceof PDO?$connection->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($db->dbdriver??'');
        if(!in_array($driver,['sqlite','mysql','mysqli'],true))throw new RuntimeException('issuer_storage_unsupported');
        $value="JSON_EXTRACT(".$column.",'$.".$key."')";
        $type="JSON_TYPE(".$column.",'$.".$key."')='text'";
        if($driver!=='sqlite'){$type="JSON_TYPE(".$value.")='STRING'";$value='BINARY JSON_UNQUOTE('.$value.')';}
        return '(CASE WHEN JSON_VALID('.$column.') THEN (CASE WHEN '.$type.' THEN '.$value.' ELSE NULL END) ELSE NULL END)';
    }
    /** MySQL NULL and MariaDB's unquoted SQL expression NULL; never a missing key or quoted literal. */
    public static function sqlNullDefault(array $column): bool
    {
        return array_key_exists('COLUMN_DEFAULT',$column)&&in_array($column['COLUMN_DEFAULT'],[null,'NULL'],true);
    }
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
