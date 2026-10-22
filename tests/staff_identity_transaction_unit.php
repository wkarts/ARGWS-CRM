<?php
/** Unit-only: SQLite checks ownership/control flow, not MySQL collation, DDL or race semantics. */
require_once __DIR__.'/../application/services/StaffIdentityTransaction.php';
final class IdentityUnitDb
{
    public PDO $pdo;
    public bool $db_debug=true;
    public bool $save_queries=true;
    public array $lastError=[];
    public function __construct(){ $this->pdo=new PDO('sqlite::memory:');$this->pdo->exec('CREATE TABLE probe(id INTEGER)'); }
    public function trans_begin(){return $this->pdo->beginTransaction();}
    public function trans_status(){return true;}
    public function trans_commit(){return $this->pdo->commit();}
    public function trans_rollback(){return $this->pdo->rollBack();}
    public function error(){return $this->lastError;}
}
$checks=0;
function identityUnitCheck($condition,$label){global $checks;if(!$condition)throw new RuntimeException($label);$checks++;}
function identityUnitThrows(callable $action,string $message){try{$action();}catch(Throwable $error){identityUnitCheck($error->getMessage()===$message,'Expected '.$message.'; received '.$error->getMessage());return;}throw new RuntimeException('Expected failure '.$message);}
$db=new IdentityUnitDb();$effects=[];
$result=StaffIdentityTransaction::run($db,'tbl',['staff'],function()use($db,&$effects){
    identityUnitCheck(!$db->db_debug&&!$db->save_queries,'Sensitive query presentation disabled');
    $db->pdo->exec('INSERT INTO probe VALUES(1)');
    StaffIdentityTransaction::afterCommit($db,function()use($db,&$effects){$effects[]=$db->pdo->inTransaction();});
    identityUnitCheck($effects===[],'Effect queued until commit');return 7;
});
identityUnitCheck($result===7&&$effects===[false],'Result and effect after confirmed commit');
identityUnitCheck($db->db_debug&&$db->save_queries,'Flags restored');
identityUnitThrows(fn()=>StaffIdentityTransaction::run($db,'tbl',['staff'],function()use($db,&$effects){
    $db->pdo->exec('INSERT INTO probe VALUES(2)');
    StaffIdentityTransaction::afterCommit($db,function()use(&$effects){$effects[]='unexpected';});
    throw new DomainException('issuer_staff_email_conflict');
}),'issuer_staff_email_conflict');
identityUnitCheck((int)$db->pdo->query('SELECT COUNT(*) FROM probe')->fetchColumn()===1&&$effects===[false],'Whole failed operation rolled back without effects');
$db->pdo->beginTransaction();
identityUnitThrows(fn()=>StaffIdentityTransaction::run($db,'tbl',['staff'],fn()=>null),'issuer_outer_transaction_not_allowed');
identityUnitCheck($db->pdo->inTransaction(),'Caller transaction retained');$db->pdo->rollBack();
$db->lastError=['code'=>1062,'message'=>"Duplicate entry 'sensitive@example.invalid' for key 'tblstaff.".StaffEmailIdentity::INDEX."'"];
identityUnitThrows(fn()=>StaffIdentityTransaction::write($db,fn()=>false),'issuer_staff_email_conflict');
$db->lastError=['code'=>1062,'message'=>"Duplicate entry 'sensitive@example.invalid' for key 'unrelated_index'"];
identityUnitThrows(fn()=>StaffIdentityTransaction::write($db,fn()=>false),'issuer_storage_failed');
$db->lastError=['code'=>1062,'message'=>"Duplicate entry 'uq_staff_email_identity' for key 'other_index'"];
identityUnitThrows(fn()=>StaffIdentityTransaction::write($db,fn()=>false),'issuer_storage_failed');
identityUnitCheck($db->db_debug&&$db->save_queries,'Error flags restored');
identityUnitThrows(fn()=>StaffIdentityTransaction::afterCommit($db,fn()=>null),'issuer_owned_transaction_required');
echo 'Staff identity transaction unit: '.$checks.' checks passed (SQLite control-flow only).'.PHP_EOL;
