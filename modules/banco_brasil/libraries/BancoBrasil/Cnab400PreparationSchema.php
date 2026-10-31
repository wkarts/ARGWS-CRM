<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';

/** Additive private invoice preparations. A draft does not reserve a number or issue a title. */
final class Cnab400PreparationSchema
{
    public static function requirements():array
    {
        return ['bb_cnab_preparations'=>['columns'=>[
            'id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned',
            'layout_binding_id'=>'bigint unsigned','invoice_id'=>'bigint unsigned','scope_key'=>'char(64)',
            'request_key'=>'char(64)','request_hash'=>'char(64)','source_hash'=>'char(64)',
            'source_json'=>'longtext','origin_id'=>'bigint unsigned?','batch_id'=>'bigint unsigned?',
            'approval_json'=>'longtext?','approval_hash'=>'char(64)?','approved_by'=>'bigint unsigned?','approved_at'=>'datetime?','created_by'=>'bigint unsigned','created_at'=>'datetime',
        ],'unique'=>[['issuer_id','request_key'],['origin_id'],['batch_id']]]];
    }
    public static function upgrade($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $pdo=$db->conn_id??$db->pdo??null;
        if((property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0)
            ||($pdo instanceof \PDO&&$pdo->inTransaction()))throw new \RuntimeException('bb400_preparation_outer_transaction_not_allowed');
        $mode=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');
        $state=$mode?$mode->row_array():[];
        if((string)($state['autocommit']??'')!=='1'||!in_array((string)($state['completion_type']??''),['0','NO_CHAIN'],true))throw new \RuntimeException('bb400_preparation_transaction_configuration_invalid');
        $existing=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'bb_cnab_preparations']);
        if(!$existing)throw new \RuntimeException('bb400_preparation_schema_failed');
        if($existing->result_array()!==[]){SchemaValidator::assertCompatible($db,$p,self::requirements());return;}
        if(!$db->query("CREATE TABLE IF NOT EXISTS `{$p}bb_cnab_preparations` (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            issuer_id BIGINT UNSIGNED NOT NULL,connection_id BIGINT UNSIGNED NOT NULL,
            layout_binding_id BIGINT UNSIGNED NOT NULL,invoice_id BIGINT UNSIGNED NOT NULL,
            scope_key CHAR(64) NOT NULL,request_key CHAR(64) NOT NULL,request_hash CHAR(64) NOT NULL,
            source_hash CHAR(64) NOT NULL,source_json LONGTEXT NOT NULL,
            origin_id BIGINT UNSIGNED DEFAULT NULL,batch_id BIGINT UNSIGNED DEFAULT NULL,
            approval_json LONGTEXT DEFAULT NULL,approval_hash CHAR(64) DEFAULT NULL,approved_by BIGINT UNSIGNED DEFAULT NULL,approved_at DATETIME DEFAULT NULL,
            created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,
            PRIMARY KEY(id),UNIQUE KEY bb_cnab400_preparation_request(issuer_id,request_key),
            UNIQUE KEY bb_cnab400_preparation_origin(origin_id),UNIQUE KEY bb_cnab400_preparation_batch(batch_id),
            KEY bb_cnab400_preparation_invoice(issuer_id,invoice_id,id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb400_preparation_schema_failed');
        SchemaValidator::assertCompatible($db,$p,self::requirements());
    }
}
