<?php
declare(strict_types=1);
namespace BancoBrasil;

/** Validate existing tables without repairing, weakening constraints, or modifying their data. */
final class SchemaValidator
{
    public static function requirements(): array
    {
        $id=['id'=>'bigint unsigned'];
        $scope=['issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned','scope_key'=>'char(64)'];
        $actor=['created_by'=>'bigint unsigned','created_at'=>'datetime'];
        return [
            'bb_connections'=>[
                'columns'=>$id+['issuer_id'=>'bigint unsigned','provider'=>'varchar(32)','account_id'=>'varchar(80)',
                    'environment'=>'varchar(16)','agreement_number'=>'varchar(7)','wallet'=>'varchar(2)','variation'=>'varchar(3)',
                    'issuer_tax_id'=>'varchar(14)','issuer_name'=>'varchar(160)','identity_hash'=>'char(64)',
                    'configuration_hash'=>'char(64)','lifecycle'=>'varchar(24)']+$actor,
                'unique'=>[['identity_hash']],
            ],
            'bb_origins'=>[
                'columns'=>$id+['invoice_id'=>'bigint unsigned']+$scope+['account_id'=>'varchar(80)','environment'=>'varchar(16)',
                    'agreement_number'=>'varchar(7)','provider'=>'varchar(32)','channel'=>'varchar(16)',
                    'remote_id'=>'varchar(64)?','control_reference'=>'varchar(25)','state'=>'varchar(32)',
                    'active_slot'=>'tinyint unsigned?','payload_hash'=>'char(64)','created_at'=>'datetime'],
                'unique'=>[['invoice_id','active_slot'],['scope_key','channel','remote_id'],['scope_key','control_reference']],
            ],
            'bb_batches'=>[
                'columns'=>$id+$scope+['idempotency_key'=>'varchar(128)','payload_hash'=>'char(64)','layout_id'=>'varchar(96)',
                    'layout_source_hash'=>'char(64)','file_sequence'=>'bigint unsigned','content_hash'=>'char(64)',
                    'content'=>'mediumblob','generation_state'=>'varchar(24)','delivery_state'=>'varchar(24)',
                    'financial_state'=>'varchar(24)']+$actor,
                'unique'=>[['scope_key','idempotency_key'],['scope_key','file_sequence']],
            ],
            'bb_return_files'=>[
                'columns'=>$id+$scope+['content_hash'=>'char(64)','layout_id'=>'varchar(96)','record_count'=>'int unsigned',
                    'event_count'=>'int unsigned','process_state'=>'varchar(24)']+$actor,
                'unique'=>[['scope_key','content_hash']],
            ],
            'bb_return_events'=>[
                'columns'=>$id+['return_file_id'=>'bigint unsigned']+$scope+['event_key'=>'char(64)','payload_hash'=>'char(64)',
                    'remote_id'=>'varchar(64)','control_reference'=>'varchar(25)','occurrence'=>'varchar(2)',
                    'observation'=>'varchar(48)','observation_json'=>'text','financial_state'=>'varchar(24)','created_at'=>'datetime'],
                'unique'=>[['scope_key','event_key']],
            ],
            'bb_return_file_events'=>[
                'columns'=>$id+['return_file_id'=>'bigint unsigned','return_event_id'=>'bigint unsigned']+$scope+
                    ['lot_number'=>'int unsigned','record_sequence'=>'int unsigned','created_at'=>'datetime'],
                'unique'=>[['return_file_id','return_event_id'],['return_file_id','lot_number','record_sequence']],
            ],
            'bb_audit'=>[
                'columns'=>$id+['issuer_id'=>'bigint unsigned','connection_id'=>'bigint unsigned?','actor_id'=>'bigint unsigned',
                    'action'=>'varchar(48)','resource_type'=>'varchar(24)','resource_id'=>'bigint unsigned?','created_at'=>'datetime'],
                'unique'=>[],
            ],
        ];
    }
    public static function assertCompatible($db,string $prefix,?array $requirements=null):void
    {
        if(!preg_match('/^[A-Za-z0-9_]*$/D',$prefix))throw new \InvalidArgumentException('bb_table_prefix_invalid');
        foreach($requirements ?? self::requirements() as $name=>$required){
            $table=$prefix.$name;
            $r=$db->query('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
            $meta=$r?$r->row_array():[];
            if(strtoupper((string)($meta['ENGINE']??''))!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::fail();
            $r=$db->query('SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,EXTRA,COLUMN_DEFAULT,CHARACTER_SET_NAME,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table]);
            if(!$r)self::fail();$columns=[];
            foreach($r->result_array() as $column)$columns[$column['COLUMN_NAME']]=$column;
            foreach($required['columns'] as $name=>$definition){
                $nullable=str_ends_with($definition,'?');$expected=rtrim($definition,'?');$column=$columns[$name]??[];
                // Integer display widths are deprecated metadata, not storage width or signedness.
                $actual=preg_replace('/\b(bigint|int|tinyint)\([0-9]+\)/','$1',strtolower((string)($column['COLUMN_TYPE']??'')));
                $extra=strtolower((string)($column['EXTRA']??''));
                if($actual!==$expected||($column['IS_NULLABLE']??'')!==($nullable?'YES':'NO')
                    ||str_contains($extra,'generated')||($name==='id'&&!str_contains($extra,'auto_increment')))self::fail();
                if(preg_match('/^(?:var)?char\(|^text$/',$expected)
                    &&(($column['CHARACTER_SET_NAME']??'')!=='utf8mb4'||($column['COLLATION_NAME']??'')!=='utf8mb4_bin'))self::fail();
            }
            // An unexpected mandatory column without a default could break every subsequent insert.
            foreach($columns as $name=>$column){
                if(!isset($required['columns'][$name])&&$column['IS_NULLABLE']==='NO'&&$column['COLUMN_DEFAULT']===null
                    &&!str_contains(strtolower($column['EXTRA']),'generated')&&!str_contains(strtolower($column['EXTRA']),'auto_increment'))self::fail();
            }
            $r=$db->query('SELECT INDEX_NAME,NON_UNIQUE,SEQ_IN_INDEX,COLUMN_NAME,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$table]);
            if(!$r)self::fail();$indexes=[];
            foreach($r->result_array() as $index){
                $key=$index['INDEX_NAME'];$indexes[$key]['valid']=($indexes[$key]['valid']??true)
                    &&(int)$index['NON_UNIQUE']===0&&$index['SUB_PART']===null&&is_string($index['COLUMN_NAME']);
                $indexes[$key]['columns'][(int)$index['SEQ_IN_INDEX']]=$index['COLUMN_NAME'];
            }
            foreach($indexes as &$index){ksort($index['columns']);$index['columns']=array_values($index['columns']);}unset($index);
            if(!($indexes['PRIMARY']['valid']??false)||$indexes['PRIMARY']['columns']!==['id'])self::fail();
            foreach($required['unique'] as $columns){
                $found=false;foreach($indexes as $index)if($index['valid']&&$index['columns']===$columns){$found=true;break;}
                if(!$found)self::fail();
            }
        }
    }
    private static function fail():void{throw new \RuntimeException('bb_schema_incompatible');}
}
