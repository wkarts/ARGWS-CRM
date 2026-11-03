<?php
declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Editor eligibility, deliberately distinct from historical filter facts. No profile or email hydration. */
final class NativeCompanyInvoiceAgents
{
 public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
 private function authorize(int$i,int$a):IssuerResourceAccess
 {
  $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'invoices','view')||(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit')))throw new DomainException('native_invoice_editor_denied');return$acl;
 }
 private function rows(int$i,IssuerResourceAccess$acl):array
 {
  $out=[];
  // Only immutable staff IDs and display names; membership/feature grant decides eligibility below.
  $rows=IssuerStorage::query($this->db,'SELECT staffid FROM '.$this->p.'staff WHERE active=1 ORDER BY staffid')->result_array();
  foreach($rows as$r){$id=IssuerPolicy::id($r['staffid']);if($acl->member($i,$id)&&$acl->can($i,$id,'invoices','view')){$name=IssuerStorage::query($this->db,'SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[$id])->row_array();if(!$name)throw new DomainException('native_invoice_editor_changed');$out[]=['staffid'=>$id,'firstname'=>(string)$name['firstname'],'lastname'=>(string)$name['lastname']];}}
  usort($out,static fn($a,$b)=>[$a['firstname'],$a['lastname'],$a['staffid']]<=>[$b['firstname'],$b['lastname'],$b['staffid']]);return$out;
 }
 public function choices(int$i,int$a):array
 {
  $acl=$this->authorize($i,$a);$rows=$this->rows($i,$acl);$this->authorize($i,$a);if($this->rows($i,$acl)!==$rows)throw new DomainException('native_invoice_editor_changed');return$rows;
 }
 public function validate(int$i,int$a,$candidate):?array
 {
  if($candidate===0||$candidate==='0'||$candidate===''){$this->authorize($i,$a);return null;}
  $id=IssuerPolicy::id($candidate);foreach($this->choices($i,$a)as$row)if($row['staffid']===$id)return$row;throw new DomainException('native_invoice_agent_denied');
 }
}
