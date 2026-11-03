<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyExpenseFormSchema.php';
require_once __DIR__.'/NativeCompanyExpenseFormValues.php';
/** Audited presentation/tax snapshot. It neither creates a settlement nor grants document access. */
final class NativeCompanyExpenseFormProfile
{
 public function __construct(private$db,private string$p){NativeCompanyExpenseFormSchema::definitions($p);}
 public static function tables():array{return['issuers_lock','company_expenses','issuers_audit',NativeCompanyExpenseFormSchema::TABLE];}
 private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
 private static function basis(array$r):string{return hash('sha256',json_encode(array_intersect_key($r,array_flip(['id','issuer_id','category_id','client_id','project_id','currency','expense_date','description','note','amount_cents','tax_cents','tax_rate','total_cents'])),JSON_THROW_ON_ERROR));}
 private static function metadata(array$r):string{return json_encode(array_intersect_key($r,array_flip(['expense_id','issuer_id','revision','actor_id','expense_revision','profile_json','basis_hash'])),JSON_THROW_ON_ERROR);}
 /** Callers must first authorize this exact canonical row. Integrity checking returns no other row or private catalog data. */
 public function verifyRow(array$expense):?array
 {
  $i=IssuerPolicy::id($expense['issuer_id']??null);$id=IssuerPolicy::id($expense['id']??null);
  if(!NativeCompanyExpenseFormSchema::installed($this->db,$this->p)){if($expense['tax_rate']===null)self::fail();return null;}
  NativeCompanyExpenseFormSchema::assertValid($this->db,$this->p);
  $sql='SELECT * FROM '.$this->p.NativeCompanyExpenseFormSchema::TABLE.' WHERE expense_id=?';$rows=$this->rows($sql,[$id]);
  if($expense['tax_rate']!==null){if($rows)self::fail();return null;}
  if(count($rows)!==1)self::fail();$r=$rows[0];
  if((int)$r['issuer_id']!==$i||(int)$r['revision']<1||(int)$r['expense_revision']<1||(int)$r['expense_revision']>(int)$expense['revision']||!hash_equals((string)$r['basis_hash'],self::basis($expense)))self::fail();
  $profile=json_decode($r['profile_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($profile)||NativeCompanyExpenseFormValues::normalize($profile)!==$profile)self::fail();
  $money=NativeCompanyExpenseFormValues::amounts(CompanyExpenses::money((int)$expense['amount_cents']),$profile);foreach($money as$k=>$v)if($v===null?$expense[$k]!==null:(string)$expense[$k]!== (string)$v)self::fail();
  $proofSql='SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?';$args=[$r['audit_id'],$i,$r['actor_id'],$id,'native_expense_form_saved',self::metadata($r)];$proof=$this->rows($proofSql,$args);if(count($proof)!==1||$this->rows($sql,[$id])!==$rows||$this->rows($proofSql,$args)!==$proof)self::fail();return$profile;
 }
 public function recordInOwned(int$i,int$a,int$id,array$profile,CompanyExpenses$writer):void
 {
  IssuerTransaction::assertOwned($this->db,$this->p,self::tables());NativeCompanyExpenseFormSchema::assertValid($this->db,$this->p);$profile=NativeCompanyExpenseFormValues::normalize($profile);$writer->assertNativeFormProof($this->db,$this->p,$i,$a,$id,$profile);
  $expense=$this->rows('SELECT * FROM '.$this->p.'company_expenses WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$i])[0]??null;if(!$expense||$expense['tax_rate']!==null)self::fail();
  $old=$this->rows('SELECT * FROM '.$this->p.NativeCompanyExpenseFormSchema::TABLE.' WHERE expense_id=? FOR UPDATE',[$id]);if(count($old)>1||($old&&(int)$old[0]['issuer_id']!==$i))self::fail();$revision=$old?(int)$old[0]['revision']+1:1;if($revision>=2147483647)self::fail();
  $r=['expense_id'=>$id,'issuer_id'=>$i,'revision'=>$revision,'actor_id'=>$a,'expense_revision'=>(int)$expense['revision'],'profile_json'=>json_encode($profile,JSON_THROW_ON_ERROR),'basis_hash'=>self::basis($expense)];
  IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'native_expense_form_saved',$i,$id,gmdate('Y-m-d H:i:s'),self::metadata($r)]);$r['audit_id']=(int)$this->db->insert_id();if($r['audit_id']<1)self::fail();
  if($old)IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyExpenseFormSchema::TABLE.' SET revision=?,actor_id=?,expense_revision=?,profile_json=?,basis_hash=?,audit_id=? WHERE expense_id=? AND issuer_id=? AND revision=?',[$revision,$a,$r['expense_revision'],$r['profile_json'],$r['basis_hash'],$r['audit_id'],$id,$i,$revision-1]);
  else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyExpenseFormSchema::TABLE.'('.implode(',',array_keys($r)).') VALUES(?,?,?,?,?,?,?,?)',array_values($r));
  if($this->verifyRow($expense)!==$profile)self::fail();$writer->assertNativeFormProof($this->db,$this->p,$i,$a,$id,$profile);
 }
 private static function fail():never{throw new RuntimeException('issuer_native_expense_form_unconfirmed');}
}
