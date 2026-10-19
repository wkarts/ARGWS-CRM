<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Transaction.php';

/** Additive operational ledgers. Published 101–104 remain unchanged. */
final class OriginationSchema
{
    public static function statements(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('bb_prefix_invalid');
        $engine=' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
        return [
            "CREATE TABLE IF NOT EXISTS {$p}bb_number_reservations (bank_namespace CHAR(64) NOT NULL PRIMARY KEY,issuer_id BIGINT UNSIGNED NOT NULL,beneficiary_tax_id VARCHAR(14) NOT NULL,first_number BIGINT UNSIGNED NOT NULL,last_number BIGINT UNSIGNED NOT NULL,next_number BIGINT UNSIGNED NOT NULL,first_file INT UNSIGNED NOT NULL,next_file INT UNSIGNED NOT NULL)".$engine,
            "CREATE TABLE IF NOT EXISTS {$p}bb_contract_declarations (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,issuer_id BIGINT UNSIGNED NOT NULL,connection_id BIGINT UNSIGNED NOT NULL,layout_binding_id BIGINT UNSIGNED NOT NULL,declaration_hash CHAR(64) NOT NULL,reference VARCHAR(191) NOT NULL,declaration_json TEXT NOT NULL,created_by BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY declaration_identity(declaration_hash),FOREIGN KEY(connection_id) REFERENCES {$p}bb_connections(id),FOREIGN KEY(layout_binding_id) REFERENCES {$p}bb_layout_bindings(id))".$engine,
            "CREATE TABLE IF NOT EXISTS {$p}bb_origin_snapshots (origin_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,layout_binding_id BIGINT UNSIGNED NOT NULL,contract_id BIGINT UNSIGNED NOT NULL,snapshot_json LONGTEXT NOT NULL,FOREIGN KEY(origin_id) REFERENCES {$p}bb_origins(id),FOREIGN KEY(layout_binding_id) REFERENCES {$p}bb_layout_bindings(id),FOREIGN KEY(contract_id) REFERENCES {$p}bb_contract_declarations(id))".$engine,
            "CREATE TABLE IF NOT EXISTS {$p}bb_batch_origins (batch_id BIGINT UNSIGNED NOT NULL,origin_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(batch_id,origin_id),UNIQUE KEY origin_batch(origin_id),FOREIGN KEY(batch_id) REFERENCES {$p}bb_batches(id),FOREIGN KEY(origin_id) REFERENCES {$p}bb_origins(id))".$engine,
            "CREATE TABLE IF NOT EXISTS {$p}bb_batch_actions (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,batch_id BIGINT UNSIGNED NOT NULL,issuer_id BIGINT UNSIGNED NOT NULL,actor_id BIGINT UNSIGNED NOT NULL,action VARCHAR(32) NOT NULL,reference VARCHAR(191) NOT NULL,created_at DATETIME NOT NULL,UNIQUE KEY batch_action(batch_id,action),FOREIGN KEY(batch_id) REFERENCES {$p}bb_batches(id))".$engine,
        ];
    }
    public static function assertValid($db,string $prefix):void { self::ensure($db,$prefix,false); }
    public static function ensure($db,string $prefix,bool $create=true):void
    {
        Transaction::quiet($db,static function()use($db,$prefix,$create):void{
            if($create)foreach(self::statements($prefix) as $sql)if(!$db->query($sql))throw new \RuntimeException('bb_origination_schema_failed');
            $requirements=[
                'bb_number_reservations'=>['bank_namespace'=>'char(64)','issuer_id'=>'bigint unsigned','beneficiary_tax_id'=>'varchar(14)','first_number'=>'bigint unsigned','last_number'=>'bigint unsigned','next_number'=>'bigint unsigned','first_file'=>'int unsigned','next_file'=>'int unsigned'],
                'bb_contract_declarations'=>['id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned','layout_binding_id'=>'bigint unsigned','declaration_hash'=>'char(64)','reference'=>'varchar(191)','declaration_json'=>'text','created_by'=>'bigint unsigned','created_at'=>'datetime'],
                'bb_origin_snapshots'=>['origin_id'=>'bigint unsigned','layout_binding_id'=>'bigint unsigned','contract_id'=>'bigint unsigned','snapshot_json'=>'longtext'],
                'bb_batch_origins'=>['batch_id'=>'bigint unsigned','origin_id'=>'bigint unsigned'],
                'bb_batch_actions'=>['id'=>'bigint unsigned','batch_id'=>'bigint unsigned','issuer_id'=>'bigint unsigned','actor_id'=>'bigint unsigned','action'=>'varchar(32)','reference'=>'varchar(191)','created_at'=>'datetime'],
            ];
            foreach($requirements as $suffix=>$fields){
                $table=$prefix.$suffix;$engine=$db->query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();
                if(strtoupper($engine[0]['ENGINE']??'')!=='INNODB'||($engine[0]['TABLE_COLLATION']??null)!=='utf8mb4_bin')throw new \RuntimeException('bb_origination_schema_invalid');
                $rows=$db->query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array();$columns=[];foreach($rows as $row)$columns[$row['COLUMN_NAME']]=$row;
                foreach($fields as $name=>$type){$c=$columns[$name]??[];if(preg_replace('/^(bigint|int)\([0-9]+\)/','$1',strtolower($c['COLUMN_TYPE']??''))!==$type||($c['IS_NULLABLE']??null)!=='NO'||(preg_match('/char|text/',$type)&&($c['COLLATION_NAME']??null)!=='utf8mb4_bin'))throw new \RuntimeException('bb_origination_schema_invalid');}
                $expected=$suffix==='bb_number_reservations'?['bank_namespace']:($suffix==='bb_origin_snapshots'?['origin_id']:($suffix==='bb_batch_origins'?['batch_id','origin_id']:['id']));
                $keys=$db->query('SELECT INDEX_NAME,NON_UNIQUE,COLUMN_NAME,SEQ_IN_INDEX,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table])->result_array();$indexes=[];foreach($keys as $key){if($key['SUB_PART']!==null)throw new \RuntimeException('bb_origination_schema_invalid');if((int)$key['NON_UNIQUE']===0)$indexes[$key['INDEX_NAME']][]=$key['COLUMN_NAME'];}
                if(($indexes['PRIMARY']??null)!==$expected||($suffix==='bb_contract_declarations'&&($indexes['declaration_identity']??null)!==['declaration_hash'])||($suffix==='bb_batch_origins'&&($indexes['origin_batch']??null)!==['origin_id'])||($suffix==='bb_batch_actions'&&($indexes['batch_action']??null)!==['batch_id','action']))throw new \RuntimeException('bb_origination_schema_invalid');
                if(in_array($suffix,['bb_contract_declarations','bb_batch_actions'],true)&&($columns['id']['EXTRA']??'')!=='auto_increment')throw new \RuntimeException('bb_origination_schema_invalid');
            }
            foreach(['bb_contract_declarations'=>['connection_id'=>['bb_connections','id'],'layout_binding_id'=>['bb_layout_bindings','id']],'bb_origin_snapshots'=>['origin_id'=>['bb_origins','id'],'layout_binding_id'=>['bb_layout_bindings','id'],'contract_id'=>['bb_contract_declarations','id']],'bb_batch_origins'=>['batch_id'=>['bb_batches','id'],'origin_id'=>['bb_origins','id']],'bb_batch_actions'=>['batch_id'=>['bb_batches','id']]] as $suffix=>$fields)foreach($fields as $column=>[$target,$key]){
                $rows=$db->query('SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$prefix.$suffix,$column])->result_array();
                if(count($rows)!==1||$rows[0]['REFERENCED_TABLE_NAME']!==$prefix.$target||$rows[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('bb_origination_schema_invalid');
            }
        });
    }
}
