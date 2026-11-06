<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/SchemaValidator.php';
final class InvoiceIssuanceSchema
{
    public static function requirements():array
    {
        return ['bb_invoice_issuances'=>['columns'=>[
            'id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','invoice_id'=>'bigint unsigned',
            'actor_id'=>'bigint unsigned','contract_id'=>'bigint unsigned','origin_id'=>'bigint unsigned',
            'instruction_json'=>'longtext','instruction_hash'=>'char(64)','created_at'=>'datetime',
        ],'unique'=>[['invoice_id'],['origin_id']]]];
    }
    public static function assertValid($db,string $p):void
    {
        SchemaValidator::assertCompatible($db,$p,self::requirements());
        foreach(['origin_id'=>['bb_origins','id'],'contract_id'=>['bb_contract_declarations','id']] as $field=>[$table,$column]){
            $r=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'bb_invoice_issuances',$field]);$rows=$r?$r->result_array():[];
            if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$p.$table||$rows[0]['REFERENCED_COLUMN_NAME']!==$column)throw new \RuntimeException('bb_schema_incompatible');
        }
    }
    public static function upgrade($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $pdo=$db->conn_id??$db->pdo??null;
        if((property_exists($db,'_trans_depth')&&(new \ReflectionProperty($db,'_trans_depth'))->getValue($db)!==0)||($pdo instanceof \PDO&&$pdo->inTransaction()))throw new \RuntimeException('bb_invoice_issuance_outer_transaction_not_allowed');
        $r=$db->query('SELECT @@SESSION.autocommit AS autocommit, @@SESSION.completion_type AS completion_type');$mode=$r?$r->row_array():[];
        if((string)($mode['autocommit']??'')!=='1'||!in_array((string)($mode['completion_type']??''),['0','NO_CHAIN'],true))throw new \RuntimeException('bb_invoice_issuance_transaction_configuration_invalid');
        $r=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.'bb_invoice_issuances']);
        if(!$r)throw new \RuntimeException('bb_invoice_issuance_schema_failed');
        if($r->result_array()){self::assertValid($db,$p);return;}
        if(!$db->query("CREATE TABLE IF NOT EXISTS `{$p}bb_invoice_issuances` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,issuer_id BIGINT UNSIGNED NOT NULL,invoice_id BIGINT UNSIGNED NOT NULL,actor_id BIGINT UNSIGNED NOT NULL,contract_id BIGINT UNSIGNED NOT NULL,origin_id BIGINT UNSIGNED NOT NULL,instruction_json LONGTEXT NOT NULL,instruction_hash CHAR(64) NOT NULL,created_at DATETIME NOT NULL,PRIMARY KEY(id),UNIQUE KEY invoice_issuance(invoice_id),UNIQUE KEY origin_issuance(origin_id),FOREIGN KEY(origin_id) REFERENCES {$p}bb_origins(id),FOREIGN KEY(contract_id) REFERENCES {$p}bb_contract_declarations(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin"))throw new \RuntimeException('bb_invoice_issuance_schema_failed');
        self::assertValid($db,$p);
    }
}
