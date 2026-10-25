<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once dirname(__DIR__,4).'/application/services/issuers/IssuerTransaction.php';
require_once __DIR__.'/Transaction.php';
final class ApiOriginPolicy
{
    public static function ensure($db,string $p,bool $create=true):void
    {
        if($create)\IssuerTransaction::assertIndependent($db);
        Transaction::quiet($db,static function()use($db,$p,$create):void{
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $table=$p.'bb_api_origin_policy';
        $meta=$db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if($create&&$meta&&$meta->row_array()===[]&&!$db->query("CREATE TABLE IF NOT EXISTS `{$table}` (
            origin_id BIGINT UNSIGNED NOT NULL,issuer_id BIGINT UNSIGNED NOT NULL,
            origin_reference CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            mode VARCHAR(24) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,created_at DATETIME NOT NULL,
            PRIMARY KEY(origin_id),UNIQUE KEY origin_reference(origin_reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb_api_origin_policy_schema_failed');
        if($create&&$meta&&$meta->row_array()===[])$meta=$db->query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
        if(!$meta||strtoupper($meta->row_array()['ENGINE']??'')!=='INNODB')throw new \RuntimeException('bb_api_origin_policy_schema_invalid');
        $result=$db->query('SHOW FULL COLUMNS FROM `'.$table.'`');if(!$result)throw new \RuntimeException('bb_api_origin_policy_schema_invalid');
        $cols=[];foreach($result->result_array() as $row)$cols[$row['Field']]=$row;
        foreach(['origin_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','origin_reference'=>'char(64)','mode'=>'varchar(24)','created_at'=>'datetime'] as $field=>$type){
            $row=$cols[$field]??[];if(preg_replace('/^(bigint|int|tinyint)\\([0-9]+\\)/','$1',strtolower($row['Type']??''))!==$type||($row['Null']??'')!=='NO'||($row['Extra']??'')!==''||!array_key_exists('Default',$row)||($row['Default']!==null&&strtoupper(trim((string)$row['Default']))!=='NULL'))throw new \RuntimeException('bb_api_origin_policy_schema_invalid');
        }
        if(($cols['origin_reference']['Collation']??'')!=='ascii_bin'||($cols['mode']['Collation']??'')!=='ascii_bin')throw new \RuntimeException('bb_api_origin_policy_schema_invalid');
        $keys=[];$result=$db->query('SHOW INDEX FROM `'.$table.'`');if(!$result)throw new \RuntimeException('bb_api_origin_policy_schema_invalid');
        foreach($result->result_array() as $row){if($row['Sub_part']!==null)throw new \RuntimeException('bb_api_origin_policy_schema_invalid');$keys[$row['Key_name']][(int)$row['Seq_in_index']]=[$row['Column_name'],(int)$row['Non_unique']];}
        foreach($keys as &$key)ksort($key);unset($key);
        if(array_values($keys['PRIMARY']??[])!==[['origin_id',0]]||array_values($keys['origin_reference']??[])!==[['origin_reference',0]])throw new \RuntimeException('bb_api_origin_policy_schema_invalid');
        });
    }
    public static function guard($db,string $p,int $issuer,int $origin,?string $reference,?callable $guard,array $tables=[]):void
    {
        \IssuerTransaction::assertOwned($db,$p,array_merge(['bb_api_origin_policy'],$tables));
        $row=\IssuerStorage::query($db,'SELECT * FROM '.$p.'bb_api_origin_policy WHERE origin_id=? FOR UPDATE',[$origin])->row_array();
        if($row){
            if(!$guard||$reference===null||(int)$row['issuer_id']!==$issuer||$row['mode']!=='bridge_manual'||!hash_equals($row['origin_reference'],$reference))throw new \DomainException('bb_api_bridge_guard_required');
        }elseif($reference!==null)throw new \DomainException('bb_api_bridge_origin_required');
        if($guard)$guard();
    }
    public static function mark($db,string $p,int $issuer,int $origin,string $reference):void
    {
        \IssuerTransaction::assertOwned($db,$p,['bb_api_origin_policy']);
        if(!preg_match('/^[a-f0-9]{64}$/D',$reference))throw new \DomainException('bb_api_bridge_reference_invalid');
        \IssuerStorage::query($db,'INSERT INTO '.$p.'bb_api_origin_policy(origin_id,issuer_id,origin_reference,mode,created_at) VALUES(?,?,?,?,?)',[$origin,$issuer,$reference,'bridge_manual',gmdate('Y-m-d H:i:s')]);
    }
}
