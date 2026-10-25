<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerStorage.php';
/** Empty owner-bound consent catalog/history. Never adopts ambiguous global rows. */
final class NativeCompanyConsentSchema
{
    private const FIELDS=[
        'company_native_consent_purposes'=>['id'=>'int unsigned','issuer_id'=>'int unsigned','name'=>'varchar(191)','description'=>'longtext','revision'=>'int unsigned','write_nonce'=>'varchar(64)','date_created'=>'varchar(19)','last_updated'=>'varchar(19)'],
        'company_native_consents'=>['id'=>'int unsigned','issuer_id'=>'int unsigned','contact_id'=>'int unsigned','purpose_id'=>'int unsigned','record_json'=>'longtext','nonce'=>'varchar(64)','created_at'=>'varchar(19)'],
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
            'company_native_consents'=>"CREATE TABLE {$p}company_native_consents (id INT UNSIGNED NOT NULL AUTO_INCREMENT,issuer_id INT UNSIGNED NOT NULL,contact_id INT UNSIGNED NOT NULL,purpose_id INT UNSIGNED NOT NULL,record_json LONGTEXT NOT NULL,nonce VARCHAR(64) NOT NULL,created_at VARCHAR(19) NOT NULL,PRIMARY KEY(id),UNIQUE KEY issuer_id(issuer_id,id),UNIQUE KEY nonce(nonce),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin",
        ];
    }
    private static function sqlite($db):bool{$c=$db->pdo??$db->conn_id??null;return$c instanceof PDO&&$c->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';}
    private static function sqliteDefinitions(string $p):array
    {
        return [
            'company_native_consent_purposes'=>"CREATE TABLE {$p}company_native_consent_purposes (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER NOT NULL,name TEXT NOT NULL,description TEXT NOT NULL,revision INTEGER NOT NULL,write_nonce TEXT NOT NULL,date_created TEXT NOT NULL,last_updated TEXT NOT NULL,UNIQUE(issuer_id,id),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id))",
            'company_native_consents'=>"CREATE TABLE {$p}company_native_consents (id INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,issuer_id INTEGER NOT NULL,contact_id INTEGER NOT NULL,purpose_id INTEGER NOT NULL,record_json TEXT NOT NULL,nonce TEXT NOT NULL,created_at TEXT NOT NULL,UNIQUE(issuer_id,id),UNIQUE(nonce),FOREIGN KEY(issuer_id) REFERENCES {$p}issuers_registry(id))",
        ];
    }
    public static function ensure($db,string $p):void
    {
        $defs=self::definitions($p);if(self::sqlite($db))$defs=self::sqliteDefinitions($p);
        // Never 'repair' pre-existing ambiguous data or columns during a migration.
        foreach($defs as$t=>$sql)if($db->table_exists($p.$t))self::validateTable($db,$p,$t);
        foreach($defs as$t=>$sql)if(!$db->table_exists($p.$t)){IssuerStorage::query($db,$sql);if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);self::validateTable($db,$p,$t);}
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void{foreach(self::definitions($p)as$t=>$_)self::validateTable($db,$p,$t);}
    private static function validateSqlite($db,string $p,string $name):void
    {
        $t=$p.$name;$columns=IssuerStorage::query($db,'PRAGMA table_info('.$t.')')->result_array();
        if(array_column($columns,'name')!==array_keys(self::FIELDS[$name]))throw new RuntimeException('native_consent_schema_invalid');
        $pk=self::INDEXES[$name]['PRIMARY'][1];foreach($columns as$c){$type=str_contains(self::FIELDS[$name][$c['name']],'int')?'INTEGER':'TEXT';$position=array_search($c['name'],$pk,true);if(strtoupper($c['type'])!==$type||(int)$c['notnull']!==1||$c['dflt_value']!==null||(int)$c['pk']!==($position===false?0:$position+1))throw new RuntimeException('native_consent_schema_invalid');}
        $unique=[];foreach(IssuerStorage::query($db,'PRAGMA index_list('.$t.')')->result_array()as$i){if((int)$i['partial']!==0||(int)$i['unique']!==1)throw new RuntimeException('native_consent_schema_invalid');$index=$i['name'];if(!preg_match('/^[A-Za-z0-9_]+$/D',$index))throw new RuntimeException('native_consent_schema_invalid');$cols=IssuerStorage::query($db,'PRAGMA index_info('.$index.')')->result_array();$unique[]=implode(',',array_column($cols,'name'));}
        $expected=[];foreach(self::INDEXES[$name]as$key=>$definition){if($key==='PRIMARY')continue;$expected[]=implode(',',$definition[1]);}sort($unique);sort($expected);if($unique!==$expected)throw new RuntimeException('native_consent_schema_invalid');
        $fk=IssuerStorage::query($db,'PRAGMA foreign_key_list('.$t.')')->result_array();if(count($fk)!==1||$fk[0]['from']!=='issuer_id'||$fk[0]['table']!==$p.'issuers_registry'||$fk[0]['to']!=='id'||!in_array($fk[0]['on_delete'],['NO ACTION','RESTRICT'],true)||!in_array($fk[0]['on_update'],['NO ACTION','RESTRICT'],true))throw new RuntimeException('native_consent_schema_invalid');
    }
    private static function validateTable($db,string $p,string $name):void
    {
        $t=$p.$name;if(self::sqlite($db)){self::validateSqlite($db,$p,$name);return;}$columns=IssuerStorage::query($db,"SHOW FULL COLUMNS FROM `{$t}`")->result_array();
        if(array_column($columns,'Field')!==array_keys(self::FIELDS[$name]))throw new RuntimeException('native_consent_schema_invalid');
        foreach($columns as$c){$type=preg_replace('/\bint\(\d+\)/','int',strtolower($c['Type']));$text=str_starts_with($type,'varchar')||$type==='longtext';if($type!==self::FIELDS[$name][$c['Field']]||$c['Null']!=='NO'||$c['Default']!==null||$c['Extra']!==($c['Field']==='id'?'auto_increment':'')||$c['Collation']!==($text?'utf8mb4_bin':null))throw new RuntimeException('native_consent_schema_invalid');}
        $engine=IssuerStorage::query($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])->row_array();if(strtoupper($engine['ENGINE']??'')!=='INNODB')throw new RuntimeException('native_consent_schema_invalid');
        $actual=[];foreach(IssuerStorage::query($db,"SHOW INDEX FROM `{$t}`")->result_array()as$r){if($r['Sub_part']!==null||$r['Index_type']!=='BTREE')throw new RuntimeException('native_consent_schema_invalid');$actual[$r['Key_name']][0]=(int)$r['Non_unique'];$actual[$r['Key_name']][1][(int)$r['Seq_in_index']-1]=$r['Column_name'];}
        foreach($actual as&$index){ksort($index[1]);}unset($index);$expected=self::INDEXES[$name];ksort($actual);ksort($expected);if($actual!==$expected)throw new RuntimeException('native_consent_schema_invalid');
        $fk=IssuerStorage::query($db,'SELECT k.COLUMN_NAME,k.REFERENCED_TABLE_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA=k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME=k.CONSTRAINT_NAME AND r.TABLE_NAME=k.TABLE_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.TABLE_NAME=? AND k.REFERENCED_TABLE_NAME IS NOT NULL',[$t])->result_array();if(count($fk)!==1||$fk[0]['COLUMN_NAME']!=='issuer_id'||$fk[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$fk[0]['REFERENCED_COLUMN_NAME']!=='id'||!in_array($fk[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($fk[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))throw new RuntimeException('native_consent_schema_invalid');
    }
}
