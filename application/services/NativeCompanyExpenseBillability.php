<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyExpenseBillabilitySchema.php';
/** Explicit native billable classification, not a billing origin or permission to convert an expense. */
final class NativeCompanyExpenseBillability
{
 public function __construct(private$db,private string$p){NativeCompanyExpenseBillabilitySchema::definitions($p);}
 public static function input($value):int{if(!in_array($value,[0,1,'0','1'],true))throw new DomainException('native_expense_billability_invalid');return(int)$value;}
 public static function tables():array{return ['issuers_lock','company_expenses','issuers_audit',NativeCompanyExpenseBillabilitySchema::TABLE];}
 private function metadata(array$r):string{return json_encode(['expense_id'=>(int)$r['expense_id'],'issuer_id'=>(int)$r['issuer_id'],'revision'=>(int)$r['revision'],'billable'=>(int)$r['billable'],'source_revision'=>$r['source_revision']],JSON_THROW_ON_ERROR);}
 public function readInOwned(int$i,int$a,int$id):?array
 {
  IssuerTransaction::assertOwned($this->db,$this->p,self::tables());NativeCompanyExpenseBillabilitySchema::assertValid($this->db,$this->p);$acl=new IssuerResourceAccess($this->db,$this->p);$check=function()use($acl,$i,$a,$id):void{if(!$acl->can($i,$a,'expenses','view')||!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$i])->row())throw new DomainException('company_expense_denied');};$check();
  $sql='SELECT * FROM '.$this->p.NativeCompanyExpenseBillabilitySchema::TABLE.' WHERE expense_id=? FOR UPDATE';$rows=IssuerStorage::query($this->db,$sql,[$id])->result_array();if(!$rows){$check();if(IssuerStorage::query($this->db,$sql,[$id])->result_array()!==[])throw new RuntimeException('issuer_native_expense_billability_unconfirmed');return null;}if(count($rows)!==1)throw new RuntimeException('issuer_native_expense_billability_unconfirmed');$row=$rows[0];if((int)$row['issuer_id']!==$i||(int)$row['revision']<1||!preg_match('/^[a-f0-9]{64}$/D',$row['source_revision']))throw new RuntimeException('issuer_native_expense_billability_unconfirmed');self::input($row['billable']);
  $proofSql='SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?';$args=[$row['audit_id'],$i,$row['actor_id'],$id,'native_expense_billability_declared',$this->metadata($row)];$proof=IssuerStorage::query($this->db,$proofSql,$args)->result_array();if(count($proof)!==1)throw new RuntimeException('issuer_native_expense_billability_unconfirmed');$check();if(IssuerStorage::query($this->db,$sql,[$id])->result_array()!==$rows||IssuerStorage::query($this->db,$proofSql,$args)->result_array()!==$proof)throw new RuntimeException('issuer_native_expense_billability_unconfirmed');return['billable'=>(int)$row['billable'],'revision'=>(int)$row['revision'],'source_revision'=>$row['source_revision']];
 }
 public function recordInOwned(int$i,int$a,int$id,int$value,CompanyExpenses$writer):void
 {
  $writer->assertNativeBillabilityProof($this->db,$this->p,$i,$a,$id,$value);$before=$this->readInOwned($i,$a,$id);$expense=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=?',[$id,$i])->row_array();$row=['expense_id'=>$id,'issuer_id'=>$i,'revision'=>($before['revision']??0)+1,'billable'=>self::input($value),'actor_id'=>$a,'source_revision'=>hash('sha256',json_encode($expense,JSON_THROW_ON_ERROR))];if($row['revision']>=2147483647)throw new DomainException('native_expense_billability_limit');
  IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'native_expense_billability_declared',$i,$id,gmdate('Y-m-d H:i:s'),$this->metadata($row)]);$row['audit_id']=(int)$this->db->insert_id();if($row['audit_id']<1)throw new RuntimeException('issuer_native_expense_billability_unconfirmed');
  if($before)IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyExpenseBillabilitySchema::TABLE.' SET revision=?,billable=?,actor_id=?,source_revision=?,audit_id=? WHERE expense_id=? AND issuer_id=? AND revision=?',[$row['revision'],$value,$a,$row['source_revision'],$row['audit_id'],$id,$i,$before['revision']]);
  else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyExpenseBillabilitySchema::TABLE.'('.implode(',',array_keys($row)).') VALUES(?,?,?,?,?,?,?)',array_values($row));
  $actual=$this->readInOwned($i,$a,$id);if($actual!==['billable'=>$value,'revision'=>$row['revision'],'source_revision'=>$row['source_revision']])throw new RuntimeException('issuer_native_expense_billability_unconfirmed');$writer->assertNativeBillabilityProof($this->db,$this->p,$i,$a,$id,$value);
 }
}
