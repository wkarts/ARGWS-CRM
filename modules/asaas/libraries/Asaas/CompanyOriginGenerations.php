<?php
declare(strict_types=1);
namespace Asaas;
/** Current selection is separate from the immutable remote identity and its history. */
final class CompanyOriginGenerations
{
    public static function predicate($db,string $p,string $alias=''):string
    {
        if($alias!==''&&!preg_match('/^[a-z_]+\.$/D',$alias))throw new \InvalidArgumentException('asaas_origin_alias_invalid');
        return $db->field_exists('active_slot',$p.'asaas_payment_origins')?' AND '.$alias.'active_slot=1':'';
    }
    public static function assertActive(array $row):void
    {
        if(array_key_exists('active_slot',$row)&&$row['active_slot']!==1&&$row['active_slot']!=='1')throw new \DomainException('asaas_company_origin_retired');
    }
    /** MySQL native upgrade. Add replacement uniqueness before removing the old constraint. */
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $connection=$db->pdo??$db->conn_id??null;
        if($create&&(($connection instanceof \PDO&&$connection->inTransaction())||(property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)>0)))throw new \RuntimeException('asaas_generation_outer_transaction_denied');
        $t=$p.'asaas_payment_origins';
        if($create&&!$db->field_exists('active_slot',$t)){\IssuerStorage::query($db,'ALTER TABLE `'.$t.'` ADD COLUMN active_slot TINYINT UNSIGNED NULL DEFAULT 1');if(isset($db->data_cache['field_names'][$t]))unset($db->data_cache['field_names'][$t]);}
        $col=\IssuerStorage::query($db,'SELECT COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$t,'active_slot'])->row_array();
        if(preg_replace('/tinyint\([0-9]+\)/','tinyint',strtolower($col['COLUMN_TYPE']??''))!=='tinyint unsigned'||($col['IS_NULLABLE']??'')!=='YES'||(string)($col['COLUMN_DEFAULT']??'')!=='1')throw new \RuntimeException('asaas_generation_schema_invalid');
        $indexes=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$t])->result_array() as $row)$indexes[$row['INDEX_NAME']][]=$row;
        $name='asaas_origin_active_uq';
        if($create&&!isset($indexes[$name])){\IssuerStorage::query($db,'ALTER TABLE `'.$t.'` ADD UNIQUE KEY `'.$name.'` (invoice_id,active_slot)');self::ensure($db,$p,true);return;}
        $active=$indexes[$name]??[];
        if(array_column($active,'COLUMN_NAME')!==['invoice_id','active_slot']||array_filter($active,fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))throw new \RuntimeException('asaas_generation_schema_invalid');
        foreach($indexes as $index=>$rows)if(array_column($rows,'COLUMN_NAME')===['invoice_id']&&(int)$rows[0]['NON_UNIQUE']===0){
            if(!$create||!preg_match('/^[A-Za-z0-9_]+$/D',$index))throw new \RuntimeException('asaas_generation_schema_invalid');
            \IssuerStorage::query($db,'ALTER TABLE `'.$t.'` DROP INDEX `'.$index.'`');
        }
        if(\IssuerStorage::query($db,'SELECT id FROM `'.$t.'` WHERE active_slot IS NOT NULL AND active_slot<>1 LIMIT 1')->row_array())throw new \RuntimeException('asaas_generation_schema_invalid');
    }
}
