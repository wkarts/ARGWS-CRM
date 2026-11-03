<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyEstimateRequestSchema.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Read the actual native forms; never borrow another company's form or manufacture an empty catalogue. */
final class NativeCompanyEstimateRequestForms
{
 public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 public function tables():array{return['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','estimate_request_forms'];}
 private function authorized(int$i,int$a):IssuerResourceAccess{$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'settings','view'))throw new DomainException('native_estimate_form_denied');return$acl;}
 private function read(int$i,int$a):array
 {
  IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=$this->authorized($i,$a);NativeCompanyEstimateRequestSchema::assertValid($this->db,$this->p);$primary=$acl->primaryId()===$i;
  $sql='SELECT id,name FROM '.$this->p.'estimate_request_forms WHERE '.($primary?'issuer_id IS NULL':'issuer_id=?').' ORDER BY id';$args=$primary?[]:[$i];$rows=IssuerStorage::query($this->db,$sql,$args)->result_array();
  if(($this->authorized($i,$a)->primaryId()===$i)!==$primary||IssuerStorage::query($this->db,$sql,$args)->result_array()!==$rows)throw new DomainException('native_estimate_form_changed');return$rows;
 }
 public function forSettings(int$i,int$a):array
 {
  $this->authorized($i,$a);$owned=false;try{IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$owned=true;}catch(RuntimeException$e){if($e->getMessage()!=='issuer_owned_transaction_required')throw$e;}
  return$owned?$this->read($i,$a):IssuerTransaction::run($this->db,$this->p,$this->tables(),fn()=>$this->read($i,$a));
 }
}
