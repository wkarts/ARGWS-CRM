<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once dirname(__DIR__,4).'/application/services/issuers/IssuerTransaction.php';
require_once __DIR__.'/Transaction.php';require_once __DIR__.'/SchemaValidator.php';
/** Additive contract evidence. Runtime asserts only; the native module migration owns creation. */
final class ApiCnabContractSchema
{
    public static function requirements():array{return ['bb_api_cnab_contracts'=>['columns'=>['id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','origin_id'=>'bigint unsigned','profile_id'=>'bigint unsigned','connection_id'=>'bigint unsigned','layout_binding_id'=>'bigint unsigned','declared_operation_id'=>'bigint unsigned','contract_version'=>'varchar(64)','policy'=>'varchar(16)','number20'=>'varchar(20)','number17'=>'varchar(17)','scope_key'=>'char(64)','api_profile_hash'=>'char(64)','origin_payload_hash'=>'char(64)','layout_profile_hash'=>'char(64)','api_stage'=>'varchar(16)','definition_json'=>'text','definition_hash'=>'char(64)','reference'=>'varchar(191)','reason'=>'text','created_by'=>'bigint unsigned','created_at'=>'datetime','record_hash'=>'char(64)'],'unique'=>[['origin_id'],['definition_hash']]]];}
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');if($create)\IssuerTransaction::assertIndependent($db);Transaction::quiet($db,static function()use($db,$p,$create):void{
            $fks=['origin_id'=>'bb_origins','profile_id'=>'bb_api_profiles','connection_id'=>'bb_connections','layout_binding_id'=>'bb_layout_bindings','declared_operation_id'=>'bb_api_operations'];
            if($create){$parts=[];foreach(self::requirements()['bb_api_cnab_contracts']['columns']as$name=>$type)$parts[]='`'.$name.'` '.$type.' NOT NULL'.($name==='id'?' AUTO_INCREMENT':'');$parts[]='PRIMARY KEY(id)';$parts[]='UNIQUE KEY origin_contract(origin_id)';$parts[]='UNIQUE KEY contract_definition(definition_hash)';foreach($fks as$name=>$target)$parts[]='FOREIGN KEY(`'.$name.'`) REFERENCES `'.$p.$target.'`(id)';if(!$db->query('CREATE TABLE IF NOT EXISTS `'.$p.'bb_api_cnab_contracts`('.implode(',',$parts).') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'))throw new \RuntimeException('bb_api_cnab_schema_failed');}
            SchemaValidator::assertCompatible($db,$p,self::requirements());foreach($fks as$column=>$target){$rows=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.'bb_api_cnab_contracts',$column])->result_array();if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$p.$target||$rows[0]['REFERENCED_COLUMN_NAME']!=='id')throw new \RuntimeException('bb_api_cnab_schema_invalid');}
        });
    }
}
