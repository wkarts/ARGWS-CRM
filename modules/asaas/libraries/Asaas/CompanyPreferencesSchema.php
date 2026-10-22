<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanySchema.php';

/** Native155: additive configuration and immutable issuance evidence; no account or origin backfill. */
final class CompanyPreferencesSchema
{
    public const FIELDS = [
        'preferences'=>['issuer_id'=>'int unsigned','active_environment'=>'varchar(16)?','revision'=>'char(64)'],
        'profiles'=>['issuer_id'=>'int unsigned','environment'=>'varchar(16)','label'=>'varchar(191)','description'=>'varchar(500)','allow_pix'=>'tinyint','allow_boleto'=>'tinyint','allow_card'=>'tinyint','revision'=>'char(64)'],
        'origin_profiles'=>['origin_id'=>'int','issuer_id'=>'int unsigned','account_row'=>'int','snapshot_json'=>'longtext','snapshot_hash'=>'char(64)','created_at'=>'datetime'],
    ];
    private const PRIMARY = ['preferences'=>['issuer_id'],'profiles'=>['issuer_id','environment'],'origin_profiles'=>['origin_id']];
    private const FKS = [
        'preferences'=>['issuer_id'=>['issuers_registry','id']],
        'profiles'=>['issuer_id'=>['issuers_registry','id']],
        'origin_profiles'=>['origin_id'=>['asaas_payment_origins','id'],'issuer_id'=>['issuers_registry','id'],'account_row'=>['asaas_company_accounts','id']],
    ];
    public static function tables():array { return array_map(static fn($name)=>'asaas_company_'.$name,array_keys(self::FIELDS)); }
    public static function ensure($db,string $prefix,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('asaas_company_prefix_invalid');
        $sqlite=CompanySchema::sqlite($db);
        foreach(self::FIELDS as $suffix=>$fields){
            $table=$prefix.'asaas_company_'.$suffix;
            if($create){
                $parts=[];
                foreach($fields as $name=>$definition){$nullable=str_ends_with($definition,'?');$type=rtrim($definition,'?');$parts[]='`'.$name.'` '.$type.(!$sqlite&&preg_match('/char|text/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL');}
                $parts[]='PRIMARY KEY (`'.implode('`,`',self::PRIMARY[$suffix]).'`)';
                foreach(self::FKS[$suffix] as $column=>[$target,$key])$parts[]='FOREIGN KEY (`'.$column.'`) REFERENCES `'.$prefix.$target.'` (`'.$key.'`)';
                \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
            }
            if($sqlite){
                $columns=\IssuerStorage::query($db,'PRAGMA table_info(`'.$table.'`)')->result_array();
                if(array_column($columns,'name')!==array_keys($fields))self::invalid();
                $pk=[];
                foreach($columns as $column){$definition=$fields[$column['name']];if(strtolower($column['type'])!==rtrim($definition,'?')||(int)$column['notnull']!==(str_ends_with($definition,'?')?0:1)||$column['dflt_value']!==null)self::invalid();if($column['pk'])$pk[(int)$column['pk']]=$column['name'];}
                ksort($pk);if(array_values($pk)!==self::PRIMARY[$suffix])self::invalid();
                $foreign=\IssuerStorage::query($db,'PRAGMA foreign_key_list(`'.$table.'`)')->result_array();
                foreach(self::FKS[$suffix] as $column=>[$target,$key]){ $matches=array_values(array_filter($foreign,static fn($row)=>$row['from']===$column));if(count($matches)!==1||$matches[0]['table']!==$prefix.$target||$matches[0]['to']!==$key||$matches[0]['on_delete']!=='NO ACTION'||$matches[0]['on_update']!=='NO ACTION')self::invalid(); }
                continue;
            }
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')self::invalid();
            $columns=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array() as $row)$columns[$row['COLUMN_NAME']]=$row;
            if(count($columns)!==count($fields))self::invalid();
            foreach($fields as $name=>$definition){$type=rtrim($definition,'?');$row=$columns[$name]??[];if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($row['COLUMN_TYPE']??''))!==$type||($row['IS_NULLABLE']??'')!==(str_ends_with($definition,'?')?'YES':'NO')||!\IssuerStorage::sqlNullDefault($row)||($row['EXTRA']??'')!==''||(preg_match('/char|text/',$type)&&($row['COLLATION_NAME']??'')!=='utf8mb4_bin'))self::invalid();}
            $primary=\IssuerStorage::query($db,"SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$table])->result_array();
            if(array_column($primary,'COLUMN_NAME')!==self::PRIMARY[$suffix])self::invalid();foreach($primary as $row)if((int)$row['NON_UNIQUE']!==0||$row['SUB_PART']!==null)self::invalid();
            foreach(self::FKS[$suffix] as $column=>[$target,$key]){
                $fk=\IssuerStorage::query($db,'SELECT k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.COLUMN_NAME=? AND k.REFERENCED_TABLE_SCHEMA=DATABASE()',[$table,$column])->result_array();
                if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$prefix.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key||!in_array($fk[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::invalid();
            }
        }
    }
    private static function invalid():never { throw new \RuntimeException('issuer_asaas_preferences_schema_invalid'); }
}
