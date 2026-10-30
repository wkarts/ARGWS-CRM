<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';

/** Additive opt-in Microsoft document-mail credentials, one-use states and immutable queue bindings. */
final class CompanyMicrosoftMailOAuthSchema
{
    public static function definitions():array{return [
        'company_microsoft_mail_accounts'=>['issuer_id'=>'int unsigned','tenant_id'=>'varchar(36)','client_id'=>'varchar(255)','client_secret_cipher'=>'text','sender_email'=>'varchar(191)','config_revision'=>'int unsigned','epoch'=>'int unsigned','token_version'=>'int unsigned','status'=>'varchar(24)','connected_by'=>'int','subject_hash'=>'varchar(64)','nonce_hash'=>'varchar(64)','refresh_cipher'=>'text','access_cipher'=>'text','access_expires_at'=>'datetime','lease_key'=>'varchar(64)','lease_expires_at'=>'datetime','created_at'=>'datetime','updated_at'=>'datetime'],
        'company_microsoft_mail_states'=>['state_hash'=>'varchar(64)','issuer_id'=>'int unsigned','actor_id'=>'int','config_revision'=>'int unsigned','nonce_hash'=>'varchar(64)','redirect_uri'=>'varchar(1000)','verifier_cipher'=>'text','status'=>'varchar(24)','expires_at'=>'datetime','created_at'=>'datetime','consumed_at'=>'datetime'],
        'company_mail_transport_queue_bindings'=>['queue_id'=>'int','issuer_id'=>'int unsigned','actor_id'=>'int','source_kind'=>'varchar(16)','request_key'=>'varchar(64)','provider_kind'=>'varchar(16)','configuration_hash'=>'varchar(64)','epoch'=>'int unsigned','created_at'=>'datetime'],
    ];}
    private static function collation(string $field,string $type):?string{return preg_match('/^(varchar|text)/',$type)?(in_array($field,['state_hash','subject_hash','nonce_hash','tenant_id','configuration_hash','lease_key','request_key'],true)?'ascii_bin':'utf8mb4_bin'):null;}
    public static function present($db,string $p):bool
    {
        $any=false;foreach(array_keys(self::definitions()) as $table)if($db->table_exists($p.$table))$any=true;
        if($any)self::assertValid($db,$p);return $any;
    }
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        foreach(self::definitions() as $table=>$fields){$parts=[];foreach($fields as $field=>$type){$coll=self::collation($field,$type);$parts[]='`'.$field.'` '.$type.($coll?' CHARACTER SET '.($coll==='ascii_bin'?'ascii':'utf8mb4').' COLLATE '.$coll:'').' NOT NULL';}
            $pk=$table==='company_microsoft_mail_accounts'?'issuer_id':($table==='company_microsoft_mail_states'?'state_hash':'queue_id');$parts[]='PRIMARY KEY (`'.$pk.'`)';
            $parts[]=($table==='company_mail_transport_queue_bindings'?'CONSTRAINT `'.$p.'ms_policy_owner_fk` ':'').'FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)';
            if($table==='company_microsoft_mail_states'){$parts[]='KEY `microsoft_mail_state_owner` (`issuer_id`,`actor_id`,`created_at`)';$parts[]='FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'company_microsoft_mail_accounts` (`issuer_id`)';}
            if($table==='company_mail_transport_queue_bindings'){$parts[]='UNIQUE KEY `mail_transport_queue_request` (`issuer_id`,`source_kind`,`request_key`)';$parts[]='CONSTRAINT `'.$p.'ms_policy_queue_fk` FOREIGN KEY (`queue_id`) REFERENCES `'.$p.'mail_queue` (`id`) ON DELETE CASCADE';}
            IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.$table.'` ('.implode(',',$parts).') ENGINE=InnoDB');
        }self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$conn=$db->pdo??$db->conn_id??null;if($conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')return;
        foreach(self::definitions() as $table=>$fields){$cols=[];foreach(IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE,COLUMN_DEFAULT,COLLATION_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->result_array()as$r)$cols[$r['COLUMN_NAME']]=$r;
            foreach($fields as$field=>$type){$r=$cols[$field]??[];$actual=preg_replace('/^(int|tinyint)\([0-9]+\)/','$1',strtolower($r['COLUMN_TYPE']??''));$coll=self::collation($field,$type);if($actual!==$type||($r['IS_NULLABLE']??'')!=='NO'||!IssuerStorage::sqlNullDefault($r)||($coll!==null&&($r['COLLATION_NAME']??'')!==$coll))throw new RuntimeException('issuer_microsoft_mail_schema_invalid');}
            $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$p.$table])->row()->ENGINE??'';if(strtoupper($engine)!=='INNODB')throw new RuntimeException('issuer_microsoft_mail_schema_invalid');
            $pk=$table==='company_microsoft_mail_accounts'?'issuer_id':($table==='company_microsoft_mail_states'?'state_hash':'queue_id');$key=IssuerStorage::query($db,"SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME='PRIMARY' ORDER BY SEQ_IN_INDEX",[$p.$table])->result_array();if(array_column($key,'COLUMN_NAME')!==[$pk]||array_filter($key,fn($r)=>(int)$r['NON_UNIQUE']!==0||$r['SUB_PART']!==null))throw new RuntimeException('issuer_microsoft_mail_schema_invalid');
            if($table!=='company_microsoft_mail_accounts'){
                $index=$table==='company_microsoft_mail_states'?'microsoft_mail_state_owner':'mail_transport_queue_request';$expected=$table==='company_microsoft_mail_states'?['issuer_id','actor_id','created_at']:['issuer_id','source_kind','request_key'];
                $keys=IssuerStorage::query($db,'SELECT COLUMN_NAME,NON_UNIQUE,SUB_PART FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=? ORDER BY SEQ_IN_INDEX',[$p.$table,$index])->result_array();
                if(array_column($keys,'COLUMN_NAME')!==$expected||array_filter($keys,fn($r)=>$r['SUB_PART']!==null||(int)$r['NON_UNIQUE']!==($table==='company_microsoft_mail_states'?1:0)))throw new RuntimeException('issuer_microsoft_mail_schema_invalid');
            }
            $fks=IssuerStorage::query($db,'SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_SCHEMA=DATABASE()',[$p.$table])->result_array();if(!array_filter($fks,fn($r)=>$r['COLUMN_NAME']==='issuer_id'&&$r['REFERENCED_TABLE_NAME']===$p.'issuers_registry'&&$r['REFERENCED_COLUMN_NAME']==='id'))throw new RuntimeException('issuer_microsoft_mail_schema_invalid');
            foreach($table==='company_microsoft_mail_accounts'?[]:[$table==='company_microsoft_mail_states'?['issuer_id','company_microsoft_mail_accounts','issuer_id']:['queue_id','mail_queue','id']]as[$field,$parent,$parentField])if(!array_filter($fks,fn($r)=>$r['COLUMN_NAME']===$field&&$r['REFERENCED_TABLE_NAME']===$p.$parent&&$r['REFERENCED_COLUMN_NAME']===$parentField))throw new RuntimeException('issuer_microsoft_mail_schema_invalid');
            if($table==='company_mail_transport_queue_bindings'){
                $rules=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,r.DELETE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.TABLE_NAME=k.TABLE_NAME AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=?',[$p.$table])->result_array();
                if(!array_filter($rules,fn($r)=>$r['COLUMN_NAME']==='queue_id'&&$r['REFERENCED_TABLE_NAME']===$p.'mail_queue'&&$r['DELETE_RULE']==='CASCADE'))throw new RuntimeException('issuer_microsoft_mail_schema_invalid');
            }
        }
    }
}
