<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
/** Empty owner-bound consent catalog/history. Never adopts ambiguous global rows. */
final class NativeCompanyConsentSchema
{
    private const FIELDS=[
        'company_native_consent_purposes'=>['id'=>'int unsigned','issuer_id'=>'int unsigned','name'=>'varchar(191)','description'=>'longtext','revision'=>'int unsigned','write_nonce'=>'varchar(64)','date_created'=>'varchar(19)','last_updated'=>'varchar(19)'],
        'company_native_consents'=>['id'=>'int unsigned','issuer_id'=>'int unsigned','contact_id'=>'int unsigned','purpose_id'=>'int unsigned','record_json'=>'longtext','nonce'=>'varchar(64)','created_at'=>'varchar(19)','purpose_source'=>'varchar(32)'],
    ];
    private const INDEXES=[
        'company_native_consent_purposes'=>['PRIMARY'=>[0,['id']],'issuer_id'=>[0,['issuer_id','id']]],
        'company_native_consents'=>['PRIMARY'=>[0,['id']],'issuer_id'=>[0,['issuer_id','id']],'nonce'=>[0,['nonce']]],
    ];
    public static function definitions(string $p):array
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
        return [
            'company_native_consent_purposes'=>"CREATE TABLE {$p}company_native_consent_purposes (id INT UNSIGNED NOT NULL AUTO_INCREMENT,issuer_id INT UNSIGNED NOT NULL,name VARCHAR(191) NOT NULL,description LONGTEXT NOT NULL,revision INT UNSIGNED NOT NULL,write_nonce VARCHAR(64) NOT NULL,date_created VARCHAR(19) NOT NULL,last_updated VARCHAR(19) NOT NULL,PRIMARY KEY(id),UNIQUE KEY issuer_id(issuer_id,id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
            'company_native_consents'=>"CREATE TABLE {$p}company_native_consents (id INT UNSIGNED NOT NULL AUTO_INCREMENT,issuer_id INT UNSIGNED NOT NULL,contact_id INT UNSIGNED NOT NULL,purpose_id INT UNSIGNED NOT NULL,record_json LONGTEXT NOT NULL,nonce VARCHAR(64) NOT NULL,created_at VARCHAR(19) NOT NULL,purpose_source VARCHAR(32) NOT NULL,CONSTRAINT {$p}consent_source CHECK (purpose_source IN ('company_consent_purpose','consent_purpose')),PRIMARY KEY(id),UNIQUE KEY issuer_id(issuer_id,id),UNIQUE KEY nonce(nonce),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
        ];
    }
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    /** The caller supplies the complete physical name; table_exists() adds dbprefix again. */
    private static function tableExists($db,string $table):bool
    {
        if(self::sqlite($db))return IssuerStorage::query($db,"SELECT 1 FROM sqlite_master WHERE type='table' AND name=?",[$table])->result_array()!==[];
        return IssuerStorage::query($db,'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$table])->result_array()!==[];
    }
    private static function sqliteDefinitions(string $p):array
    {
        return [
            'company_native_consent_purposes'=>"CREATE TABLE {$p}company_native_consent_purposes (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER NOT NULL,name TEXT NOT NULL,description TEXT NOT NULL,revision INTEGER NOT NULL,write_nonce TEXT NOT NULL,date_created TEXT NOT NULL,last_updated TEXT NOT NULL,UNIQUE(issuer_id,id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id))",
            'company_native_consents'=>"CREATE TABLE {$p}company_native_consents (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER NOT NULL,contact_id INTEGER NOT NULL,purpose_id INTEGER NOT NULL,record_json TEXT NOT NULL,nonce TEXT NOT NULL,created_at TEXT NOT NULL,purpose_source TEXT NOT NULL,CHECK (purpose_source IN ('company_consent_purpose','consent_purpose')),UNIQUE(issuer_id,id),UNIQUE(nonce),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id))",
        ];
    }
    public static function ensure($db,string $p):void
    {
        IssuerTransaction::assertIndependent($db);if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::upgradePurposeSource($db,$p);$defs=self::definitions($p);if(self::sqlite($db))$defs=self::sqliteDefinitions($p);
        // Never 'repair' pre-existing ambiguous data or columns during a migration.
        foreach($defs as$t=>$sql)if(self::tableExists($db,$p.$t))self::validateTable($db,$p,$t);
        foreach($defs as$t=>$sql)if(!self::tableExists($db,$p.$t)){IssuerStorage::query($db,$sql);if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::validateTable($db,$p,$t);}
        self::assertValid($db,$p);
    }
    private static function upgradePurposeSource($db,string$p):void
    {
        self::definitions($p);$t=$p.'company_native_consents';$sqlite=self::sqlite($db);$columns=$sqlite?IssuerStorage::query($db,"PRAGMA table_info($t)")->result_array():IssuerStorage::query($db,'SELECT COLUMN_NAME,COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION',[$t])->result_array();if(!$columns)return;$names=array_column($columns,$sqlite?'name':'COLUMN_NAME');$old=array_slice(array_keys(self::FIELDS['company_native_consents']),0,-1);
        if($names!==$old&&$names!==array_keys(self::FIELDS['company_native_consents']))throw new RuntimeException('native_consent_schema_invalid');$resume=false;if(!$sqlite&&$names!==$old){$defaults=array_column($columns,'COLUMN_DEFAULT','COLUMN_NAME');$resume=in_array($defaults['purpose_source']??null,['company_consent_purpose',"'company_consent_purpose'"],true);}if($names!==$old&&!$resume)return;self::validateTable($db,$p,'company_native_consents',$names===$old,$resume);
        // This predecessor's sole writer accepted only the owned namespace. Verify its
        // immutable purpose name and revision receipt, not merely a coinciding numeric ID.
        foreach(IssuerStorage::query($db,"SELECT c.*,p.name AS expected_name,p.revision AS current_revision FROM $t c LEFT JOIN {$p}company_native_consent_purposes p ON p.issuer_id=c.issuer_id AND p.id=c.purpose_id")->result_array()as$row){$record=json_decode($row['record_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($record)||$row['expected_name']===null||($record['purpose_name']??null)!==$row['expected_name']||!is_int($record['purpose_revision']??null)||$record['purpose_revision']<1||$record['purpose_revision']>(int)$row['current_revision']||($resume&&$row['purpose_source']!=='company_consent_purpose'))throw new RuntimeException('native_consent_purpose_source_unproven');}
        if($names===$old){if($sqlite){$pdo=$db->pdo??$db->conn_id;$pdo->beginTransaction();try{IssuerStorage::query($db,"ALTER TABLE $t RENAME TO {$t}_owned_predecessor");IssuerStorage::query($db,self::sqliteDefinitions($p)['company_native_consents']);IssuerStorage::query($db,"INSERT INTO $t(".implode(',',array_keys(self::FIELDS['company_native_consents'])).") SELECT ".implode(',',$old).",'company_consent_purpose' FROM {$t}_owned_predecessor");IssuerStorage::query($db,"DROP TABLE {$t}_owned_predecessor");$pdo->commit();}catch(Throwable$e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}}else IssuerStorage::query($db,"ALTER TABLE $t ADD purpose_source VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT 'company_consent_purpose',ADD CONSTRAINT {$p}consent_source CHECK (purpose_source IN ('company_consent_purpose','consent_purpose'))");}
        if(!$sqlite)IssuerStorage::query($db,"ALTER TABLE $t ALTER purpose_source DROP DEFAULT");if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
    }
    public static function assertValid($db,string $p):void{foreach(self::definitions($p)as$t=>$_)self::validateTable($db,$p,$t);}
    private static function validateSqlite($db,string $p,string $name,bool$legacy=false):void
    {
        $fields=$legacy?array_slice(self::FIELDS[$name],0,-1,true):self::FIELDS[$name];$t=$p.$name;if($name==='company_native_consents'&&!$legacy){ $sql=IssuerStorage::query($db,"SELECT sql FROM sqlite_master WHERE type='table' AND name=?",[$t])->row_array()['sql']??'';self::sourceConstraint($sql);}$columns=IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array();
        if(array_column($columns,'name')!==array_keys($fields))throw new RuntimeException('native_consent_schema_invalid');
        $pk=self::INDEXES[$name]['PRIMARY'][1];foreach($columns as$c){$type=str_contains($fields[$c['name']],'int')?'INTEGER':'TEXT';$position=array_search($c['name'],$pk,true);if(strtoupper($c['type'])!==$type||(int)$c['notnull']!==1||$c['dflt_value']!==null||(int)$c['pk']!==($position===false?0:$position+1))throw new RuntimeException('native_consent_schema_invalid');}
        $unique=[];foreach(IssuerStorage::query($db,'PRAGMA index_list('.$t.')')->result_array()as$i){if((int)$i['partial']!==0||(int)$i['unique']!==1)throw new RuntimeException('native_consent_schema_invalid');$index=$i['name'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$index))throw new RuntimeException('native_consent_schema_invalid');$cols=IssuerStorage::query($db,'PRAGMA index_info('.$index.')')->result_array();$unique[]=implode(',',array_column($cols,'name'));}
        $expected=[];foreach(self::INDEXES[$name]as$key=>$definition){if($key==='PRIMARY')continue;$expected[]=implode(',',$definition[1]);}sort($unique);sort($expected);if($unique!==$expected)throw new RuntimeException('native_consent_schema_invalid');
        $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array();if(count($fk)!==1||$fk[0]['from']!=='issuer_id'||$fk[0]['table']!==$p.'issuers_registry'||$fk[0]['to']!=='id'||!in_array($fk[0]['on_delete'],['NO ACTION','RESTRICT'],true)||!in_array($fk[0]['on_update'],['NO ACTION','RESTRICT'],true))throw new RuntimeException('native_consent_schema_invalid');
    }
    private static function validateTable($db,string $p,string $name,bool$legacy=false,bool$resume=false):void
    {
        $fields=$legacy?array_slice(self::FIELDS[$name],0,-1,true):self::FIELDS[$name];$t=$p.$name;if(self::sqlite($db)){self::validateSqlite($db,$p,$name,$legacy);return;}if($name==='company_native_consents'&&!$legacy)self::sourceConstraint((string)(IssuerStorage::query($db,'SHOW CREATE TABLE '.$t)->row_array()['Create Table']??''));$columns=IssuerStorage::query($db,"SHOW FULL COLUMNS FROM `{$t}`")->result_array();
        if(array_column($columns,'Field')!==array_keys($fields))throw new RuntimeException('native_consent_schema_invalid');
        foreach($columns as$c){$type=preg_replace('/\bint\(\d+\)/','int',strtolower($c['Type']));$text=str_starts_with($type,'varchar')||$type==='longtext';if($type!==$fields[$c['Field']]||$c['Null']!=='NO'||($c['Default']!==null&&!($resume&&$c['Field']==='purpose_source'&&in_array($c['Default'],['company_consent_purpose',"'company_consent_purpose'"],true)))||$c['Extra']!==($c['Field']==='id'?'auto_increment':'')||$c['Collation']!==($text?'utf8mb4_bin':null))throw new RuntimeException('native_consent_schema_invalid');}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_consent_schema_invalid');
        $actual=[];foreach(IssuerStorage::query($db,"SHOW INDEX FROM `{$t}`")->result_array()as$r){if($r['Sub_part']!==null||$r['Index_type']!=='BTREE')throw new RuntimeException('native_consent_schema_invalid');$actual[$r['Key_name']][0]=(int)$r['Non_unique'];$actual[$r['Key_name']][1][(int)$r['Seq_in_index']-1]=$r['Column_name'];}
        foreach($actual as&$index){ksort($index[1]);}unset($index);$expected=self::INDEXES[$name];ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('native_consent_schema_invalid');
        $fk=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array();if(count($fk)!==1||$fk[0]['COLUMN_NAME']!=='issuer_id'||$fk[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$fk[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fk[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_consent_schema_invalid');
    }
    private static function sourceConstraint(string$sql):void
    {
        if(preg_match('/NOT\s+ENFORCED/i',$sql))throw new RuntimeException('native_consent_schema_invalid');
        $sql=preg_replace('/_(?:utf8mb4|utf8mb3|ascii)/i','',$sql);
        if(!preg_match('/\bCHECK\s*\(/i',$sql,$m,PREG_OFFSET_CAPTURE))throw new RuntimeException('native_consent_schema_invalid');
        $start=$m[0][1]+strlen($m[0][0]);$depth=1;$expression='';for($i=$start;$i<strlen($sql)&&$depth>0;$i++){if($sql[$i]==='(')$depth++;if($sql[$i]===')')$depth--;if($depth>0)$expression.=$sql[$i];}
        if($depth!==0||strtolower(preg_replace('/[\s`()]+/','',$expression))!=="purpose_sourcein'company_consent_purpose','consent_purpose'")throw new RuntimeException('native_consent_schema_invalid');
    }

}
