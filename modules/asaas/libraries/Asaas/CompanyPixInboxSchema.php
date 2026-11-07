<?php

declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanySchema.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';

/** Account-bound Pix event inbox, observation jobs and explicit read-only workers. No financial backfill. */
final class CompanyPixInboxSchema
{
    public static function definitions():array
    {
        return [
            'asaas_company_pix_events'=>['id'=>'int','issuer_id'=>'int unsigned','account_row'=>'int','environment'=>'varchar(16)','wallet'=>'varchar(128)','event_id'=>'varchar(128)','event_type'=>'varchar(64)','resource_kind'=>'varchar(16)','remote_id'=>'varchar(128)','authorization_id'=>'varchar(128)?','payment_id'=>'varchar(64)?','remote_status'=>'varchar(32)?','payload_hash'=>'char(64)','state'=>'varchar(32)','created_at'=>'datetime'],
            'asaas_company_pix_event_jobs'=>['event_id'=>'int','issuer_id'=>'int unsigned','account_row'=>'int','state'=>'varchar(32)','attempts'=>'int','lease_key'=>'char(64)?','lease_until'=>'datetime?','next_attempt'=>'datetime','last_code'=>'varchar(64)','updated_at'=>'datetime'],
            'asaas_company_pix_observations'=>['id'=>'int','event_id'=>'int','issuer_id'=>'int unsigned','account_row'=>'int','actor_id'=>'int','account_revision'=>'char(64)','result_json'=>'longtext','evidence_hash'=>'char(64)','created_at'=>'datetime'],
            'asaas_company_pix_workers'=>['account_row'=>'int','issuer_id'=>'int unsigned','actor_id'=>'int','enabled'=>'tinyint','revision'=>'char(64)','next_check'=>'datetime','updated_at'=>'datetime'],
        ];
    }
    public static function indexes(string $table):array
    {
        return match($table){
            'asaas_company_pix_events'=>['PRIMARY'=>[['id'],0],'pix_event_unique'=>[['account_row','event_id'],0],'pix_event_owner'=>[['issuer_id','account_row','id'],1]],
            'asaas_company_pix_event_jobs'=>['PRIMARY'=>[['event_id'],0],'pix_job_due'=>[['account_row','state','next_attempt'],1]],
            'asaas_company_pix_observations'=>['PRIMARY'=>[['id'],0],'pix_observation_scope'=>[['issuer_id','account_row','event_id','id'],1]],
            'asaas_company_pix_workers'=>['PRIMARY'=>[['account_row'],0],'pix_worker_due'=>[['enabled','next_check'],1]],
            default=>throw new \InvalidArgumentException('asaas_pix_inbox_table_invalid'),
        };
    }
    private static function foreign(string $table):array
    {
        $out=['issuer_id'=>['issuers_registry','id'],'account_row'=>['asaas_company_accounts','id']];
        if(in_array($table,['asaas_company_pix_event_jobs','asaas_company_pix_observations'],true))$out['event_id']=['asaas_company_pix_events','id'];
        return $out;
    }
    public static function present($db,string $p):bool
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_pix_inbox_prefix_invalid');
        $present=[];foreach(self::definitions() as $t=>$f)$present[]=$db->table_exists($p.$t);
        if(!in_array(true,$present,true))return false;
        self::ensure($db,$p,false);return true;
    }
    public static function ensure($db,string $p,bool $create=true):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('asaas_pix_inbox_prefix_invalid');
        if($create)\IssuerTransaction::assertIndependent($db);
        $sqlite=CompanySchema::sqlite($db);
        foreach(self::definitions() as $table=>$fields){
            if($create){$parts=[];foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$parts[]='`'.$name.'` '.($name==='id'&&$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':$type.(!$sqlite&&preg_match('/char|text/',$type)?' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin':'').($nullable?' NULL':' NOT NULL').($name==='id'?' AUTO_INCREMENT':''));}
                foreach(self::indexes($table) as $name=>[$cols,$non])if(!$sqlite)$parts[]=($name==='PRIMARY'?'PRIMARY KEY':($non?'KEY':'UNIQUE KEY').' `'.$name.'`').' (`'.implode('`,`',$cols).'`)';elseif($name==='PRIMARY'&&!isset($fields['id']))$parts[]='PRIMARY KEY (`'.implode('`,`',$cols).'`)';
                foreach(self::foreign($table) as $col=>[$target,$key])$parts[]='FOREIGN KEY (`'.$col.'`) REFERENCES `'.$p.$target.'` (`'.$key.'`)';
                \IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).')'.($sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin'));
                if($sqlite)foreach(self::indexes($table) as $name=>[$cols,$non])if($name!=='PRIMARY')\IssuerStorage::query($db,'CREATE '.($non?'':'UNIQUE ').'INDEX IF NOT EXISTS `'.$p.$table.'_'.$name.'` ON `'.$p.$table.'` (`'.implode('`,`',$cols).'`)');
            }
            if($sqlite){$cols=\IssuerStorage::query($db,'PRAGMA table_info(`'.$p.$table.'`)')->result_array();if(array_column($cols,'name')!==array_keys($fields))throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');continue;}
            $meta=\IssuerStorage::query($db,'SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row_array();
            if(strtoupper($meta['ENGINE']??'')!=='INNODB'||($meta['TABLE_COLLATION']??'')!=='utf8mb4_bin')throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');
            $cols=[];foreach(\IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME,EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array() as $r)$cols[$r['COLUMN_NAME']]=$r;
            if(count($cols)!==count($fields))throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');
            foreach($fields as $name=>$type){$nullable=str_ends_with($type,'?');$type=rtrim($type,'?');$r=$cols[$name]??[];if(preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''))!==$type||($r['IS_NULLABLE']??'')!==($nullable?'YES':'NO')||!\IssuerStorage::sqlNullDefault($r)||(preg_match('/char|text/',$type)&&($r['COLLATION_NAME']??'')!=='utf8mb4_bin')||($name==='id'&&($r['EXTRA']??'')!=='auto_increment'))throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');}
            $indexes=[];foreach(\IssuerStorage::query($db,'SELECT INDEX_NAME,COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY INDEX_NAME,SEQ_IN_INDEX',[$p.$table])->result_array() as $r){if($r['SUB_PART']!==null)throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');$indexes[$r['INDEX_NAME']]['columns'][]=$r['COLUMN_NAME'];$indexes[$r['INDEX_NAME']]['non_unique']=(int)$r['NON_UNIQUE'];}
            foreach(self::indexes($table) as $name=>[$cols,$non])if(($indexes[$name]['columns']??[])!==$cols||($indexes[$name]['non_unique']??null)!==$non)throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');
            foreach(self::foreign($table) as $col=>[$target,$key]){$fk=\IssuerStorage::query($db,'SELECT REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.$table,$col])->result_array();if(count($fk)!==1||$fk[0]['REFERENCED_TABLE_NAME']!==$p.$target||$fk[0]['REFERENCED_COLUMN_NAME']!==$key)throw new \RuntimeException('issuer_asaas_pix_inbox_schema_invalid');}
        }
    }
}
