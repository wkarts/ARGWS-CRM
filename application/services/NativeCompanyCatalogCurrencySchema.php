<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerPolicy.php';

/** Explicit installation-maintenance step; never run by an item save or a GET. */
final class NativeCompanyCatalogCurrencySchema
{
    private static function prefix(string $prefix):void
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');}
    private static function driver($db):string
    {$connection=$db->conn_id??$db->pdo??null;return $connection instanceof PDO?$connection->getAttribute(PDO::ATTR_DRIVER_NAME):(string)($db->dbdriver??'');}
    private static function columns($db,string $prefix):array
    {
        $driver=self::driver($db);$out=[];
        if($driver==='sqlite'){
            foreach(IssuerStorage::query($db,'PRAGMA table_xinfo('.$prefix.'items)')->result_array() as$row)$out[$row['name']]=['type'=>strtolower((string)$row['type']),'nullable'=>(int)$row['notnull']===0,'extra'=>(int)($row['hidden']??0)!==0||(int)$row['pk']!==0,'default'=>$row['dflt_value']];
        }elseif(in_array($driver,['mysql','mysqli'],true)){
            foreach(IssuerStorage::query($db,'SHOW FULL COLUMNS FROM '.$prefix.'items')->result_array() as$row)$out[$row['Field']]=['type'=>strtolower((string)$row['Type']),'nullable'=>$row['Null']==='YES','extra'=>(string)$row['Extra']!=='','default'=>$row['Default']];
        }else throw new RuntimeException('issuer_storage_unsupported');
        if(!$out)throw new RuntimeException('native_catalog_currency_schema_invalid');return$out;
    }
    private static function valid(array $column):bool
    {return preg_match('/^decimal\(15,([0-9]|1[0-5])\)$/D',$column['type'])===1&&$column['nullable']&&!$column['extra']&&$column['default']===null;}
    /** Native persisted storage precision, not an unreviewed bootstrap hook. */
    public static function installedPrecision($db,string $prefix):int
    {
        self::prefix($prefix);$column=self::columns($db,$prefix)['rate']??null;
        if(!$column||$column['extra']||!preg_match('/^decimal\(15,([0-9]|1[0-5])\)$/D',$column['type'],$match))throw new RuntimeException('native_catalog_currency_schema_invalid');
        return(int)$match[1];
    }
    public static function currencyIds($db,string $prefix):array
    {
        self::prefix($prefix);$ids=[];
        foreach(IssuerStorage::query($db,'SELECT id FROM '.$prefix.'currencies ORDER BY id')->result_array()as$row){$id=IssuerPolicy::id($row['id']);if(isset($ids[$id]))throw new RuntimeException('native_catalog_currency_schema_invalid');$ids[$id]=$id;}
        if(!$ids)throw new RuntimeException('native_catalog_currency_schema_invalid');return array_values($ids);
    }
    public static function ensure($db,string $prefix,int $decimalPlaces):void
    {
        self::prefix($prefix);if($decimalPlaces<0||$decimalPlaces>15)throw new DomainException('native_catalog_currency_precision_invalid');
        IssuerTransaction::assertIndependent($db);$ids=self::currencyIds($db,$prefix);$columns=self::columns($db,$prefix);
        // Validate the entire installed set before any DDL. Never alter/drop a historical column.
        foreach($ids as$id){$name='rate_currency_'.$id;if(isset($columns[$name])&&!self::valid($columns[$name]))throw new RuntimeException('native_catalog_currency_schema_invalid');}
        foreach($ids as$id){$name='rate_currency_'.$id;if(!isset($columns[$name])){IssuerTransaction::assertIndependent($db);IssuerStorage::query($db,'ALTER TABLE '.$prefix.'items ADD COLUMN '.$name.' DECIMAL(15,'.$decimalPlaces.') NULL');}}
        self::assertReady($db,$prefix,self::currencyIds($db,$prefix));
    }
    /** SELECT-only precondition for native item model calls, before entering their owned transaction. */
    public static function assertReady($db,string $prefix,array $currencyIds):void
    {
        self::prefix($prefix);$installed=array_fill_keys(self::currencyIds($db,$prefix),true);$columns=self::columns($db,$prefix);
        foreach($currencyIds as$id){$id=IssuerPolicy::id($id);if(!isset($installed[$id])||!isset($columns['rate_currency_'.$id])||!self::valid($columns['rate_currency_'.$id]))throw new RuntimeException('native_catalog_currency_schema_invalid');}
    }
}
