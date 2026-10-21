<?php
declare(strict_types=1);
register_shutdown_function(static function(){global $db,$p;if(!isset($db,$p))return;try{if($db->pdo->inTransaction())$db->pdo->rollBack();$db->failOn=null;$db->ignoreOn=null;$db->observer=null;foreach(array_reverse(array_keys(CompanyHrSchema::definitions($p))) as $table)$db->pdo->exec('DROP TABLE IF EXISTS '.$p.$table);}catch(Throwable $e){fwrite(STDERR,'HR seed fixture cleanup failed: '.$e->getMessage().PHP_EOL);}});
require __DIR__.'/staff_fixture.php';
require_once APPPATH.'services/CompanyHrSchema.php';
$db=staffFixture();$p=$db->prefix;$history=staffSnapshot($db);
$seed='INSERT INTO '.$p.'company_hr_cursor(';
$read=static fn()=>array_map(static fn($row)=>array_map('intval',$row),$db->query('SELECT id,last_id,upper_id,revision FROM '.$p.'company_hr_cursor ORDER BY id')->result_array());
$db->ignoreOn=$seed;
staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_hr_write_unconfirmed','Ignored initial seed is not a completed migration');
$db->ignoreOn=null;staffCheck($read()===[],'Ignored seed leaves no infrastructure cursor');
$db->failOn=$seed;
staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_storage_failed','Failed initial seed aborts');
$db->failOn=null;staffCheck($read()===[],'Failed seed leaves no cursor');
CompanyHrSchema::ensure($db,$p);$initial=[['id'=>1,'last_id'=>0,'upper_id'=>0,'revision'=>1]];
staffCheck($read()===$initial,'Retry confirms exactly one initial cursor');
CompanyHrSchema::ensure($db,$p);staffCheck($read()===$initial,'Replay preserves the exact initial cursor');
$db->query('UPDATE '.$p.'company_hr_cursor SET last_id=11,upper_id=29,revision=7 WHERE id=1');$progress=$read();
CompanyHrSchema::ensure($db,$p);staffCheck($read()===$progress,'Ensure preserves existing valid progress');
$db->ignoreOn=$seed;CompanyHrSchema::ensure($db,$p);$db->ignoreOn=null;
staffCheck($read()===$progress,'A replay does not require rewriting existing progress');
$db->query('INSERT INTO '.$p.'company_hr_cursor(id,last_id,upper_id,revision) VALUES(2,0,0,1)');$extra=$read();
staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_hr_write_unconfirmed','An extra cursor is not silently adopted');
staffCheck($read()===$extra,'Rejected extra cursor is preserved for explicit repair');$db->query('DELETE FROM '.$p.'company_hr_cursor WHERE id=2');
foreach(['last_id=30','revision=0'] as $corruption){
    $db->query('UPDATE '.$p.'company_hr_cursor SET '.$corruption.' WHERE id=1');$before=$read();
    staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_hr_write_unconfirmed','Corrupt progress is not normalized: '.$corruption);
    staffCheck($read()===$before,'Failed validation preserves corrupt evidence: '.$corruption);
    $db->query('UPDATE '.$p.'company_hr_cursor SET last_id=11,upper_id=29,revision=7 WHERE id=1');
}
$fired=false;$db->observer=static function($sql,$params,$connection)use(&$fired,$seed,$p):void{if(!$fired&&str_starts_with($sql,$seed)){$fired=true;$connection->pdo->exec('UPDATE '.$p.'company_hr_cursor SET revision=revision+1 WHERE id=1');}};
staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_hr_write_unconfirmed','Late mutation of existing progress is rejected');$db->observer=null;
staffCheck($fired&&$read()===$progress,'Late mutation rolls back without resetting progress');
$db->query('DELETE FROM '.$p.'company_hr_cursor WHERE id=1');$fired=false;
$db->observer=static function($sql,$params,$connection)use(&$fired,$seed,$p):void{if(!$fired&&str_starts_with($sql,$seed)){$fired=true;$connection->pdo->exec('UPDATE '.$p.'company_hr_cursor SET revision=2 WHERE id=1');}};
staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_hr_write_unconfirmed','Late mutation of new seed is rejected');$db->observer=null;
staffCheck($fired&&$read()===[],'New seed and late mutation roll back together');
CompanyHrSchema::ensure($db,$p);staffCheck($read()===$initial,'Retry after late mutation restores one confirmed initial cursor');
$db->pdo->beginTransaction();staffThrows(fn()=>CompanyHrSchema::ensure($db,$p),'issuer_outer_transaction_not_allowed','Ensure never adopts a caller transaction');
staffCheck($db->pdo->inTransaction(),'Caller transaction remains owned by caller');$db->pdo->rollBack();
class HrSeedStringDb extends StaffTestDb { public function query(string $sql,array $params=[]){$result=parent::query($sql,$params);return new StaffTestResult(array_map(static fn($row)=>array_map(static fn($value)=>$value===null?null:(string)$value,$row),$result->result_array()));} }
CompanyHrSchema::ensure(new HrSeedStringDb($db->pdo,$p),$p);staffCheck($read()===$initial,'Native string-valued columns preserve the same seed contract');
staffCheck(staffSnapshot($db)===$history,'Staff, membership, installation identity, permissions and audit remain exact');
foreach(array_keys(CompanyHrSchema::definitions($p)) as $table)if($table!=='company_hr_cursor')staffCheck((int)$db->query('SELECT COUNT(*) n FROM '.$p.$table)->row_array()['n']===0,'No employment backfill: '.$table);
echo 'HR migration cursor readback: '.$GLOBALS['staffChecks'].' checks; '.PHP_VERSION.'; 128MiB.'.PHP_EOL;
