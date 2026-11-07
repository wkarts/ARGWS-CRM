<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyInstallmentGenerations.php';
/** New group, never restoration: durable one-shot POST, canonical discovery and atomic identity succession. */
trait CompanyInstallmentRecreation
{
 private function installmentRecreationSource(int $issuer,int $actor,int $group,bool $fresh):array
 {
  $this->authorize($issuer,$actor,'remote_remove');$this->authorize($issuer,$actor,'issue');(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
  $v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'2.0.2','<'))throw new \DomainException('asaas_module_202_required');
  $s=$this->installmentGroupInOwned($issuer,$actor,$group);
  if(!in_array((int)$s[3]['status'],[1,4],true)||$this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=?',[$s[0]['invoice_id']])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=?',[$s[0]['invoice_id']]))throw new \DomainException('asaas_installment_recreation_unpaid_required');
  foreach($s[5] as $remote=>$member){$origin=(int)$member['origin']['id'];$op=$this->installmentRemovalLedger($origin);if(!$op||$op['state']!=='deleted'||$op['account_revision']!==$s[2]['revision'])throw new \DomainException('asaas_installment_recreation_deleted_required');if($fresh)$this->installmentRemovalProof($s,$remote,true);if($this->db->table_exists($this->p.'asaas_company_refund_requests')&&$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_refund_requests WHERE origin_id=?',[$origin]))throw new \DomainException('asaas_installment_recreation_unpaid_required');}
  return $s;
 }
 private function installmentRecreationPlan(int $issuer,int $actor,int $group):?array
 {
  $this->authorize($issuer,$actor,'remote_remove');$this->authorize($issuer,$actor,'issue');
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
  $plans=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'group_recreate');if(!$plans)return null;if(count($plans)!==1||(int)$plans[0]['actor_id']!==$actor)throw new \DomainException('asaas_installment_recreation_conflict');return $plans[0];
 }
 public function reviewInstallmentRecreation(int $issuer,int $actor,int $group,int $count,string $dueDate,callable $decrypt,?callable $transport=null):array
 {
  $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$dueDate);if($count<2||$count>100||!$date||$date->format('Y-m-d')!==$dueDate)throw new \DomainException('asaas_installment_recreation_invalid');
  $s=$this->transaction(fn()=>$this->installmentRecreationSource($issuer,$actor,$group,false));foreach($s[5] as $remote=>$member)$this->refreshInstallmentRemoval($issuer,$actor,$group,$remote,$decrypt,$transport);
  return $this->transaction(function()use($issuer,$actor,$group,$count,$dueDate){$s=$this->installmentRecreationSource($issuer,$actor,$group,true);if($this->installmentRecreationPlan($issuer,$actor,$group))throw new \DomainException('asaas_installment_recreation_pending');$amount=$s[4]['amount'];if(\CompanyInvoiceAmounts::minor($amount)<$count)throw new \DomainException('asaas_installment_recreation_invalid');$review=['issuer_id'=>$issuer,'actor_id'=>$actor,'previous_root'=>$group,'invoice_id'=>(int)$s[0]['invoice_id'],'account_revision'=>$s[2]['revision'],'customer'=>$s[1]['customer_id'],'billing_type'=>$s[1]['billing_type'],'amount'=>$amount,'count'=>$count,'due_date'=>$dueDate,'binding_hash'=>self::hash($s[4])];$review['key']=self::hash(['group-recreation-v1',$review]);return $review;});
 }
 public function installmentRecreationPage(int $issuer,int $actor,int $group):array
 {
  return $this->transaction(function()use($issuer,$actor,$group){$plan=$this->installmentRecreationPlan($issuer,$actor,$group);$s=$this->installmentRecreationSource($issuer,$actor,$group,false);return ['group_origin'=>$group,'group_id'=>$s[0]['batch_id'],'amount'=>$s[4]['amount'],'count'=>count($s[5]),'due_date'=>$s[4]['members'][0]['due_date'],'pending'=>$plan?$plan['value']:null];});
 }
 public function recreateInstallmentGroup(int $issuer,int $actor,int $group,int $count,string $dueDate,string $approval,bool $confirmed,callable $decrypt,?callable $transport=null):array
 {
  if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');if(!preg_match('/^[a-f0-9]{64}$/D',$approval))throw new \DomainException('asaas_installment_recreation_invalid');
  $plan=$this->transaction(fn()=>$this->installmentRecreationPlan($issuer,$actor,$group));
  if(!$plan){$review=$this->reviewInstallmentRecreation($issuer,$actor,$group,$count,$dueDate,$decrypt,$transport);if($review['key']!==$approval)throw new \DomainException('asaas_legacy_review_changed');
   $plan=$this->transaction(function()use($issuer,$actor,$group,$review){$s=$this->installmentRecreationSource($issuer,$actor,$group,true);$old=$this->installmentRecreationPlan($issuer,$actor,$group);if($old)return $old;if(self::hash($s[4])!==$review['binding_hash']||$s[2]['revision']!==$review['account_revision'])throw new \DomainException('asaas_company_stale');return CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$s[2]['revision'],'group_recreate',$review);});
  }
  if($plan['value']['key']!==$approval||$plan['value']['count']!==$count||$plan['value']['due_date']!==$dueDate)throw new \DomainException('asaas_legacy_review_changed');
  return $this->recoverInstallmentRecreation($issuer,$actor,$group,$approval,$decrypt,$transport);
 }
 public function recoverInstallmentRecreation(int $issuer,int $actor,int $group,string $key,callable $decrypt,?callable $transport=null):array
 {
  $read=function()use($issuer,$actor,$group,$key){$plan=$this->installmentRecreationPlan($issuer,$actor,$group);if(!$plan||$plan['value']['key']!==$key)throw new \DomainException('asaas_installment_recreation_conflict');$links=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'group_next');if($links){if(count($links)!==1)throw new \DomainException('asaas_installment_recreation_conflict');$new=(int)$links[0]['value']['successor_root'];$this->installmentGroupInOwned($issuer,$actor,$new);return ['complete'=>true,'origin_id'=>$new,'key'=>$key];}
   $s=$this->installmentRecreationSource($issuer,$actor,$group,false);if($s[2]['revision']!==$plan['account_revision']||self::hash($s[4])!==$plan['value']['binding_hash'])throw new \DomainException('asaas_company_stale');return ['complete'=>false,'source'=>$s,'plan'=>$plan];};
  $state=$this->transaction($read);if($state['complete'])return $state;$s=$state['source'];$plan=$state['plan'];$input=$plan['value'];$reference=hash('sha256','installment-successor:'.$key);
  foreach($s[5] as $remote=>$member)$this->refreshInstallmentRemoval($issuer,$actor,$group,$remote,$decrypt,$transport);
  $client=$this->client($s[2],$decrypt,$transport);self::wallet($client,$s[2]);$this->transaction($read);self::customer($client,$s[1]['customer_id'],$s[3]);
  $send=$this->transaction(function()use($read,$issuer,$actor,$group,$plan){$state=$read();if($state['complete'])return false;$s=$this->installmentRecreationSource($issuer,$actor,$group,true);$claims=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'group_create_s');if($claims){if(count($claims)!==1||($claims[0]['value']['plan_id']??null)!==(int)$plan['id'])throw new \DomainException('asaas_installment_recreation_conflict');return false;}CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$s[2]['revision'],'group_create_s',['issuer_id'=>$issuer,'plan_id'=>(int)$plan['id']]);return true;});
  if($send)try{$client->createInstallmentSuccessor($input['customer'],$input['billing_type'],$input['count'],$input['amount'],$input['due_date'],$reference);}catch(\Throwable $e){/* One-shot claim survives every unknown response; only canonical GET can recover. */}
  $state=$this->transaction($read);if($state['complete'])return $state;
  try{$review=$this->discoverInstallmentSuccessor($s,$input,$reference,$client,$read);}catch(\Throwable $e){return ['complete'=>false,'key'=>$key,'state'=>'result_unconfirmed'];}
  foreach($s[5] as $remote=>$member)$this->refreshInstallmentRemoval($issuer,$actor,$group,$remote,$decrypt,$transport);
  return $this->transaction(function()use($read,$issuer,$actor,$group,$plan,$review,$key){$state=$read();if($state['complete'])return $state;$s=$this->installmentRecreationSource($issuer,$actor,$group,true);$retired=[];foreach($s[5] as $remote=>$member){$proof=$this->installmentRemovalProof($s,$remote,true);$retired[$remote]=['proof_id'=>(int)$proof['id'],'proof_hash'=>$proof['evidence_hash']];}
   $result=CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$s[2]['revision'],'group_created',['issuer_id'=>$issuer,'plan_id'=>(int)$plan['id'],'review'=>$review]);
   \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET active_slot=NULL WHERE id=? AND issuer_id=? AND active_slot=1',[$group,$issuer]);
   $bound=$this->bindInstallmentReview($issuer,$actor,$review,$s[2],$s[3],$review['review_hash'],false);$next=$bound['origin_id'];$link=['issuer_id'=>$issuer,'previous_root'=>$group,'successor_root'=>$next,'result_proof'=>(int)$result['id'],'result_hash'=>$result['evidence_hash'],'retired'=>$retired];
   CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$s[2]['revision'],'group_next',$link);CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$next,$s[2]['revision'],'group_prev',$link);
   $this->installmentGroupInOwned($issuer,$actor,$next);return ['complete'=>true,'origin_id'=>$next,'key'=>$key];
  });
 }
 private function discoverInstallmentSuccessor(array $s,array $input,string $reference,CompanyClient $client,callable $guard):array
 {
  $this->transaction($guard);$page=$client->request('GET','payments?externalReference='.$reference.'&limit=100&offset=0');
  if(($page['hasMore']??null)!==false||!is_array($page['data']??null)||count($page['data'])!==$input['count']||($page['totalCount']??null)!==$input['count'])throw new \DomainException('asaas_installment_recreation_incomplete');
  $group=null;$members=[];$ids=[];$sum=0;
  foreach($page['data'] as $raw){$id=self::opaque($raw['id']??null,64);$batch=self::opaque($raw['installment']??null,120);$number=$raw['installmentNumber']??null;if($group===null)$group=$batch;
   if($batch!==$group||$group===$s[0]['batch_id']||!is_int($number)||$number<1||$number>$input['count']||isset($members[$number])||isset($ids[$id])||($raw['externalReference']??null)!==$reference)throw new \DomainException('asaas_installment_recreation_conflict');
   $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$raw['dueDate']??'');$value=self::money($raw['value']??null);$minor=\CompanyInvoiceAmounts::minor($value);if(!$date||$date->format('Y-m-d')!==$raw['dueDate']||$raw['dueDate']<$input['due_date']||($number===1&&$raw['dueDate']!==$input['due_date'])||$minor<=0)throw new \DomainException('asaas_installment_recreation_conflict');
   $parent=$s[3];$parent['hash']=$reference;$parent['duedate']=$raw['dueDate'];unset($raw['installment']);$safe=self::normalize($raw,['remote_id'=>$id],['customer_id'=>$input['customer'],'billing_type'=>$input['billing_type'],'reviewed_amount'=>$value],$parent,$id);
   if($safe['adverse']||$safe['deleted']||!in_array($safe['status'],['PENDING','OVERDUE','RECEIVED'],true)||($safe['status']!=='RECEIVED'&&($safe['payment_date']!==''||$safe['credit_date']!=='')))throw new \DomainException('asaas_installment_recreation_conflict');$ids[$id]=true;$sum+=$minor;$members[$number]=['number'=>$number]+$safe;
  }
  if($sum!==\CompanyInvoiceAmounts::minor($input['amount']))throw new \DomainException('asaas_installment_recreation_conflict');ksort($members);
  $this->transaction($guard);$remote=$client->request('GET','installments/'.$group);if(($remote['id']??null)!==$group||($remote['customer']??null)!==$input['customer']||($remote['billingType']??null)!==$input['billing_type']||($remote['installmentCount']??null)!==$input['count']||($remote['deleted']??false)!==false||self::money($remote['value']??null)!==$input['amount'])throw new \DomainException('asaas_installment_recreation_conflict');
  $this->transaction($guard);$listed=$client->request('GET','installments/'.$group.'/payments?limit=100&offset=0');if(($listed['hasMore']??null)!==false||($listed['totalCount']??null)!==$input['count']||!is_array($listed['data']??null)||count($listed['data'])!==count($ids))throw new \DomainException('asaas_installment_recreation_incomplete');
  $original=$page['data'];usort($original,static fn($a,$b)=>strcmp($a['id'],$b['id']));$current=$listed['data'];usort($current,static fn($a,$b)=>strcmp($a['id'],$b['id']));if($current!==$original)throw new \DomainException('asaas_installment_recreation_conflict');
  $result=['bound'=>false,'read_only'=>true,'issuer_id'=>$input['issuer_id'],'invoice_id'=>(int)$s[0]['invoice_id'],'account_row'=>(int)$s[2]['id'],'account_id'=>$s[2]['account_id'],'environment'=>$s[2]['environment'],'group_id'=>$group,'amount'=>$input['amount'],'receivable'=>$s[3]['total'],'billing_type'=>$input['billing_type'],'members'=>array_values($members)];$result['review_hash']=self::hash(['installment_successor_v1',$input['key'],$result]);return $result;
 }
}
