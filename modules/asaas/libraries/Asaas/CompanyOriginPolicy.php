<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanySchema.php';
/** Immutable opt-in policy for integration origins. No historical backfill or policy removal. */
final class CompanyOriginPolicy
{
    public const TABLE='asaas_company_origin_policy';
    public static function tables($db,string $p):array{if($db->table_exists($p.self::TABLE))return [self::TABLE];$row=\IssuerStorage::query($db,'SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row_array();if(version_compare($row['installed_version']??'0','1.5.7','>='))throw new \RuntimeException('asaas_origin_policy_schema_required');return [];}
    public static function ensure($db,string $p,bool $create=false):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');$t=$p.self::TABLE;$sqlite=CompanySchema::sqlite($db);
        if($create){\IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS '.$t.' (origin_id int NOT NULL PRIMARY KEY,issuer_id int unsigned NOT NULL,origin_reference char(64) NOT NULL,mode varchar(24) NOT NULL,created_at datetime NOT NULL,UNIQUE(origin_reference))'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));if(isset($db->data_cache['table_names']))unset($db->data_cache['table_names']);}
        if(!$db->table_exists($t))throw new \RuntimeException('asaas_origin_policy_schema_required');
        if($sqlite){$cols=\IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array();if(array_column($cols,'name')!==['origin_id','issuer_id','origin_reference','mode','created_at'])throw new \RuntimeException('asaas_origin_policy_schema_invalid');return;}
        $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('asaas_origin_policy_schema_invalid');
        $fields=['origin_id'=>'int','issuer_id'=>'int unsigned','origin_reference'=>'char(64)','mode'=>'varchar(24)','created_at'=>'datetime'];$cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->result_array() as $c)$cols[$c['COLUMN_NAME']]=$c;
        if(count($cols)!==count($fields))throw new \RuntimeException('asaas_origin_policy_schema_invalid');foreach($fields as $name=>$type){$c=$cols[$name]??[];if(preg_replace('/^int\([0-9]+\)/','int',strtolower($c['COLUMN_TYPE']??''))!==$type||($c['IS_NULLABLE']??'')!=='NO'||!\IssuerStorage::sqlNullDefault($c)||(str_contains($type,'char')&&($c['COLLATION_NAME']??'')!=='utf8mb4_bin'))throw new \RuntimeException('asaas_origin_policy_schema_invalid');}
        $keys=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('asaas_origin_policy_schema_invalid');$keys[$r['INDEX_NAME']][]=$r;}foreach(['PRIMARY'=>'origin_id','origin_reference'=>'origin_reference'] as $key=>$field)if(count($keys[$key]??[])!==1||$keys[$key][0]['COLUMN_NAME']!==$field||(int)$keys[$key][0]['NON_UNIQUE']!==0)throw new \RuntimeException('asaas_origin_policy_schema_invalid');
    }
    public static function requireReady($db,string $p):void
    {
        $row=\IssuerStorage::query($db,'SELECT installed_version FROM '.$p.'modules WHERE module_name=?',['asaas'])->row_array();if(!preg_match('/^[0-9]\.[0-9]\.[0-9]$/D',$row['installed_version']??'')||version_compare($row['installed_version'],'1.5.7','<'))throw new \DomainException('asaas_module_157_required');self::ensure($db,$p);
    }
    public static function isManual($db,string $p,int $origin):bool
    {
        if(!self::tables($db,$p))return false;self::ensure($db,$p);$row=\IssuerStorage::query($db,'SELECT mode FROM '.$p.self::TABLE.' WHERE origin_id=?',[$origin])->row_array();if($row&&$row['mode']!=='manual_only')throw new \DomainException('asaas_origin_policy_invalid');return (bool)$row;
    }
    public static function assertAutomaticAllowed($db,string $p,int $origin):void
    {if(self::isManual($db,$p,$origin))throw new \DomainException('asaas_origin_manual_reconciliation_required');}
    public static function assertManual($db,string $p,int $origin,int $issuer,string $reference):void
    {
        \IssuerTransaction::assertOwned($db,$p,[self::TABLE]);$row=\IssuerStorage::query($db,'SELECT * FROM '.$p.self::TABLE.' WHERE origin_id=? FOR UPDATE',[$origin])->row_array();if(!$row||(int)$row['issuer_id']!==$issuer||$row['mode']!=='manual_only'||!hash_equals($row['origin_reference'],$reference))throw new \DomainException('asaas_origin_policy_conflict');
    }
    public static function markManual($db,string $p,int $origin,int $issuer,string $reference):void
    {
        \IssuerTransaction::assertOwned($db,$p,[self::TABLE]);if(!preg_match('/^[a-f0-9]{64}$/D',$reference))throw new \DomainException('asaas_origin_reference_invalid');
        \IssuerStorage::query($db,'INSERT INTO '.$p.self::TABLE.' (origin_id,issuer_id,origin_reference,mode,created_at) VALUES(?,?,?,?,?)',[$origin,$issuer,$reference,'manual_only',gmdate('Y-m-d H:i:s')]);self::assertManual($db,$p,$origin,$issuer,$reference);
    }
}
