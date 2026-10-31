<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Blank native timers remain issuer/actor owned; assignment never rewrites a legacy timer. */
final class NativeCompanyBlankTimerSchema
{
    private const COLUMNS=['id'=>'int unsigned','issuer_id'=>'int unsigned','actor_id'=>'int','started_at'=>'datetime','closed_at'=>'datetime','state'=>'varchar(16)','revision'=>'int unsigned','target_kind'=>'varchar(32)','target_id'=>'int unsigned','target_timer_id'=>'int unsigned','note'=>'text'];
    private const NULLABLE=['closed_at','target_kind','target_id','target_timer_id'];
    public static function ensure($db,string $p):void
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');IssuerTransaction::assertIndependent($db);
        IssuerStorage::query($db,'CREATE TABLE IF NOT EXISTS `'.$p.'company_blank_task_timers` (`id` int unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY,`issuer_id` int unsigned NOT NULL,`actor_id` int NOT NULL,`started_at` datetime NOT NULL,`closed_at` datetime NULL,`state` varchar(16) NOT NULL,`revision` int unsigned NOT NULL,`target_kind` varchar(32) NULL,`target_id` int unsigned NULL,`target_timer_id` int unsigned NULL,`note` text NOT NULL,UNIQUE KEY `issuer_blank` (`issuer_id`,`id`),KEY `actor_blank` (`actor_id`,`state`,`id`),FOREIGN KEY (`issuer_id`) REFERENCES `'.$p.'issuers_registry` (`id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        if(property_exists($db,'data_cache'))unset($db->data_cache['table_names']);
        $conn=$db->pdo??$db->conn_id??null;$sqlite=$conn instanceof PDO&&$conn->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        if(!$sqlite){
            $type=IssuerStorage::query($db,'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?',[$p.'company_blank_task_timers','target_kind'])->row_array()['COLUMN_TYPE']??null;
            if($type==='varchar(16)'){
                // Validate the entire previous schema before a strictly additive widening.
                self::validate($db,$p,16);$query='SELECT * FROM `'.$p.'company_blank_task_timers` ORDER BY id';
                $before=IssuerStorage::query($db,$query)->result_array();
                IssuerStorage::query($db,'ALTER TABLE `'.$p.'company_blank_task_timers` MODIFY `target_kind` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL');
                if(IssuerStorage::query($db,$query)->result_array()!==$before)throw new RuntimeException('issuer_blank_timer_upgrade_unconfirmed');
            }
        }
        self::assertValid($db,$p);
    }
    public static function assertValid($db,string $p):void{self::validate($db,$p,32);}
    private static function validate($db,string $p,int $targetLength):void
    {
        $expected=self::COLUMNS;$expected['target_kind']='varchar('.$targetLength.')';
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$t=$p.'company_blank_task_timers';
        $pdo=$db->pdo??$db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $rows=static fn($sql,$args=[])=>IssuerStorage::query($db,$sql,$args)->result_array();
        if($sqlite){
            $columns=$rows('PRAGMA table_info(`'.$t.'`)');if(array_column($columns,'name')!==array_keys($expected))self::fail();
            foreach($columns as$r){$name=$r['name'];$type=strtolower($r['type']);if($type!==($name==='id'?'integer':$expected[$name])||$r['dflt_value']!==null||(int)$r['pk']!==($name==='id'?1:0)||($name!=='id'&&(bool)$r['notnull']===in_array($name,self::NULLABLE,true)))self::fail();}
            $unique=[];foreach($rows('PRAGMA index_list(`'.$t.'`)')as$r){$fields=array_column($rows('PRAGMA index_info(`'.$r['name'].'`)'),'name');if((int)$r['unique']===1)$unique[]=$fields;}
            if($unique!==[['issuer_id','id']])self::fail();
            $fk=$rows('PRAGMA foreign_key_list(`'.$t.'`)');if(count($fk)!==1||$fk[0]['from']!=='issuer_id'||$fk[0]['table']!==$p.'issuers_registry'||$fk[0]['to']!=='id')self::fail();return;
        }
        $columns=$rows('SHOW FULL COLUMNS FROM `'.$t.'`');if(array_column($columns,'Field')!==array_keys($expected))self::fail();
        foreach($columns as$r){$name=$r['Field'];$type=preg_replace('/^int\([0-9]+\)/','int',strtolower($r['Type']));if($type!==$expected[$name]||$r['Null']!==(in_array($name,self::NULLABLE,true)?'YES':'NO')||$r['Default']!==null||$r['Extra']!==($name==='id'?'auto_increment':'')||$r['Collation']!==(in_array($name,['state','target_kind','note'],true)?'utf8mb4_unicode_ci':null))self::fail();}
        $engine=$rows('SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t])[0]??[];if(strtoupper($engine['ENGINE']??'')!=='INNODB'||($engine['TABLE_COLLATION']??'')!=='utf8mb4_unicode_ci')self::fail();
        $keys=[];foreach($rows('SHOW INDEX FROM `'.$t.'`')as$r){if($r['Sub_part']!==null||$r['Index_type']!=='BTREE')self::fail();$keys[$r['Key_name']]['unique']=(int)$r['Non_unique'];$keys[$r['Key_name']]['columns'][(int)$r['Seq_in_index']]=$r['Column_name'];}
        $expected=['PRIMARY'=>['unique'=>0,'columns'=>[1=>'id']],'issuer_blank'=>['unique'=>0,'columns'=>[1=>'issuer_id',2=>'id']],'actor_blank'=>['unique'=>1,'columns'=>[1=>'actor_id',2=>'state',3=>'id']]];ksort($keys);ksort($expected);if($keys!==$expected)self::fail();
        $fks=$rows('SELECT COLUMN_NAME,REFERENCED_TABLE_SCHEMA,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL',[$t]);$schema=$rows('SELECT DATABASE() AS name')[0]['name']??null;
        if(count($fks)!==1||$fks[0]['COLUMN_NAME']!=='issuer_id'||$fks[0]['REFERENCED_TABLE_SCHEMA']!==$schema||$fks[0]['REFERENCED_TABLE_NAME']!==$p.'issuers_registry'||$fks[0]['REFERENCED_COLUMN_NAME']!=='id')self::fail();
        $rules=$rows('SELECT DELETE_RULE,UPDATE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=?',[$t]);if(count($rules)!==1||!in_array($rules[0]['DELETE_RULE'],['RESTRICT','NO ACTION'],true)||!in_array($rules[0]['UPDATE_RULE'],['RESTRICT','NO ACTION'],true))self::fail();
    }
    private static function fail():never{throw new RuntimeException('native_blank_timer_schema_invalid');}
}
