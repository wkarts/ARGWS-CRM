<?php
/** READ ONLY comparison with the preserved genuine452 backup, after native HTTP login/upgrade. */
if (PHP_SAPI!=='cli' || getenv('ISSUERS_BROWSER_DB_NAME')!=='argws_legacy_ci') { exit(2); }
define('FCPATH',dirname(__DIR__,2).'/'); define('BASEPATH',FCPATH.'system/'); define('APPPATH',FCPATH.'application/');
require APPPATH.'config/app-config.php';
if (APP_DB_NAME!=='argws_legacy_ci' || !preg_match('/^127\.0\.0\.1:[0-9]+$/D',APP_DB_HOSTNAME)) { exit(2); }
$pdo=new PDO('mysql:host='.str_replace(':',';port=',APP_DB_HOSTNAME).';dbname='.APP_DB_NAME.';charset=utf8mb4',APP_DB_USERNAME,APP_DB_PASSWORD,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SET SESSION TRANSACTION READ ONLY'); $pdo->beginTransaction();
register_shutdown_function(static function()use($pdo){if($pdo->inTransaction())$pdo->rollBack();});
$baseline='argws_baseline452'; $current='argws_legacy_ci'; $checks=0;
function nativeHttpAssert(bool $ok,string $label):void { ++$GLOBALS['checks']; if(!$ok)throw new RuntimeException($label); }
function nativeHttpRows(string $schema,string $table):array { return $GLOBALS['pdo']->query('SELECT * FROM `'.$schema.'`.`'.$table.'`')->fetchAll(PDO::FETCH_ASSOC); }
function nativeHttpSet(array $rows):array { $rows=array_map('serialize',$rows);sort($rows,SORT_STRING);return $rows; }
function nativeHttpTables(string $schema):array { $q=$GLOBALS['pdo']->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=? ORDER BY TABLE_NAME');$q->execute([$schema]);$names=$q->fetchAll(PDO::FETCH_COLUMN);sort($names,SORT_STRING);return $names; }
nativeHttpAssert((int)nativeHttpRows($baseline,'tblmigrations')[0]['version']===452,'Preserved actual452 baseline required');
nativeHttpAssert((int)nativeHttpRows($current,'tblmigrations')[0]['version']===453,'Native453 completion required');
nativeHttpAssert(nativeHttpRows($baseline,'tblissuers_installation_identity')===nativeHttpRows($current,'tblissuers_installation_identity'),'Existing identity byte equality');
$new=['tblcompany_staff_commands','tblcompany_task_schedules','tblcompany_task_schedule_runs','tblcompany_task_schedule_requests','tblcompany_task_schedule_cursor'];
$old=nativeHttpTables($baseline);$expected=array_merge($old,$new);sort($expected,SORT_STRING);
nativeHttpAssert(nativeHttpTables($current)===$expected,'Exactly five additive tables');
$proof=[];$qualified=[];
foreach($old as$table){
    nativeHttpAssert(preg_match('/^tbl[a-zA-Z0-9_]+$/D',$table)===1,'Finite table name');
    $ddl=[];foreach([$baseline,$current]as$schema){$value=$pdo->query('SHOW CREATE TABLE `'.$schema.'`.`'.$table.'`')->fetch(PDO::FETCH_ASSOC)['Create Table'];
        if(in_array($table,['tblactivity_log','tblissuers_audit','tbloptions'],true))$value=preg_replace('/ AUTO_INCREMENT=[0-9]+/','',$value);
        $ddl[]=$value;
    }
    nativeHttpAssert($ddl[0]===$ddl[1],'Historical structural metadata '.$table);
    $before=nativeHttpRows($baseline,$table);$after=nativeHttpRows($current,$table);
    if($table==='tblstaff'){
        foreach([$before,$after]as$index=>$rows){foreach($rows as&$row){unset($row['last_ip'],$row['last_login'],$row['last_activity']);}unset($row);if($index===0)$before=$rows;else$after=$rows;}
        $qualified[$table]=['last_ip','last_login','last_activity'];
    }elseif($table==='tblmigrations'){
        $before[0]['version']=$after[0]['version'];$qualified[$table]=['version452→453'];
    }elseif($table==='tbloptions'){
        $values=array_column($after,'value','name');$oldValues=array_column($before,'value','name');
        nativeHttpAssert(($values['upgraded_from_version']??null)==='452','Native previous version option');
        nativeHttpAssert(($values['update_info_message']??null)==='','Native update message cleared');
        nativeHttpAssert(ctype_digit($values['last_updated_date']??'')&&(int)$values['last_updated_date']>=(int)($oldValues['last_updated_date']??0),'Native update timestamp advances');
        $names=['upgraded_from_version','update_info_message','last_updated_date'];
        foreach($after as$row)if(in_array($row['name'],$names,true))nativeHttpAssert((int)$row['autoload']===1,'Native tracking option autoload');
        $before=array_values(array_filter($before,static fn($r)=>!in_array($r['name'],$names,true)));$after=array_values(array_filter($after,static fn($r)=>!in_array($r['name'],$names,true)));$qualified[$table]=$names;
    }elseif($table==='tblactivity_log'){
        nativeHttpAssert(count($before)===0&&count($after)===1,'Only one native login activity');
        nativeHttpAssert($after[0]['description']==='User Successfully Logged In [User Id: 17, Is Staff Member: Yes, IP: 127.0.0.1]','Exact fixture login event');
        $after=[];$qualified[$table]=['one exact native successful login','AUTO_INCREMENT'];
    }elseif($table==='tblissuers_audit'){
        $max=max(array_column($before,'id'));$appended=array_values(array_filter($after,static fn($r)=>(int)$r['id']>$max));
        nativeHttpAssert(array_column($appended,'event')===['company_settings_delete','company_settings_add','company_settings_update','company_settings_update','company_settings_update'],'Exact native Settings tracking audit sequence');
        foreach($appended as$row)nativeHttpAssert((int)$row['actor_id']===17&&(int)$row['issuer_id']===1&&$row['subject_id']===null&&$row['metadata_json']===null,'Tracking event owner and no hidden metadata');
        $after=array_values(array_filter($after,static fn($r)=>(int)$r['id']<=$max));$qualified[$table]=['five native Settings tracking events; prior rows exact','AUTO_INCREMENT'];
    }elseif($table==='tblsessions'){
        $oldSet=nativeHttpSet($before);$newSet=nativeHttpSet($after);nativeHttpAssert(!array_diff($oldSet,$newSet),'Every historical session row remains byte-identical');
        nativeHttpAssert(count($after)-count($before)===2,'Only two native browser sessions appended');
        foreach($after as$row)if(!in_array(serialize($row),$oldSet,true))nativeHttpAssert($row['ip_address']==='127.0.0.1','New sessions are local fixture traffic');
        $after=$before;$qualified[$table]=['two native browser sessions appended; prior rows exact'];
    }
    $a=nativeHttpSet($before);$b=nativeHttpSet($after);nativeHttpAssert($a===$b,'Historical qualified row equality '.$table);
    $proof[$table]=['metadata_sha256'=>hash('sha256',$ddl[0]),'rows_sha256'=>hash('sha256',serialize($a)),'preserved_rows'=>count($a)];
}
foreach($new as$table){$rows=nativeHttpRows($current,$table);if($table==='tblcompany_task_schedule_cursor'){nativeHttpAssert(count($rows)===1&&array_map('intval',$rows[0])===['id'=>1,'last_id'=>0,'upper_id'=>0,'revision'=>1],'Only the finite discovery cursor is seeded');}else nativeHttpAssert($rows===[],'No authorization, staff or task history backfill');}
echo json_encode(['result'=>'POSTCONDITIONS_PASS','checks'=>$checks,'historical_tables'=>count($old),'new_tables'=>$new,'qualified_changes'=>$qualified,'tables'=>$proof,'memory_limit'=>ini_get('memory_limit'),'read_only'=>true],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT).PHP_EOL;
