<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyInstallmentEditJournal.php';
/** Explicit fixed-total reallocation/date edits. One durable PUT claim per selected member. */
trait CompanyInstallmentEdits
{
 private function installmentEditSource(int $issuer,int $actor,int $group,?string $key=null):array
 {
  $this->authorize($issuer,$actor,'configure');$this->authorize($issuer,$actor,'issue');(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
  $v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($v,'2.0.1','<'))throw new \DomainException('asaas_module_201_required');
  return $this->installmentGroupInOwned($issuer,$actor,$group,$key);
 }
 private function installmentEditEligible(array $s,array $changed,int $actor):void
 {
  if($this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$s[0]['invoice_id']]))throw new \DomainException('asaas_installment_edit_financial_history');
  foreach($this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$s[0]['invoice_id']]) as $payment){$receipt=$this->rows('SELECT o.id FROM '.$this->p.'asaas_company_receipts r JOIN '.$this->p.'asaas_payment_origins o ON o.id=r.origin_id WHERE r.payment_id=? AND r.issuer_id=? AND o.invoice_id=? AND o.batch_id=? AND o.channel=? FOR UPDATE',[$payment['id'],$s[0]['issuer_id'],$s[0]['invoice_id'],$s[0]['batch_id'],'installment']);if(count($receipt)!==1){$closed=(new \CompanyAsaasRefundPostings($this->db,$this->p))->forInvoiceInOwned((int)$s[0]['issuer_id'],$actor,(int)$s[0]['invoice_id']);if(!isset($closed[(int)$payment['id']]))throw new \DomainException('asaas_installment_edit_financial_history');}}
  foreach(['bb_origins','efi_company_origins'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE invoice_id=? FOR UPDATE',[$s[0]['invoice_id']]))throw new \DomainException('asaas_company_multiple_providers');
  foreach($changed as $remote){$id=(int)$s[5][$remote]['origin']['id'];
   foreach(['asaas_company_receipts','asaas_company_remote_operations','asaas_company_refund_requests'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT origin_id FROM '.$this->p.$table.' WHERE origin_id=? FOR UPDATE',[$id]))throw new \DomainException('asaas_installment_edit_financial_history');
   foreach($this->rows('SELECT event_type,state FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$s[2]['id'],$remote]) as $e)if(!in_array($e['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE'],true)||in_array($e['state'],['conflict','review_required'],true))throw new \DomainException('asaas_installment_edit_financial_history');
  }
  if($this->rows("SELECT id FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='group_remove'",[$s[0]['id']]))throw new \DomainException('asaas_installment_edit_financial_history');
 }
 private function installmentEditCanonical(array $s,array $member,array $response):array
 {
  if(($response['installment']??null)!==$s[0]['batch_id']||($response['installmentNumber']??null)!==$member['number'])throw new \DomainException('asaas_installment_edit_remote_conflict');
  if(!empty($response['dunning']))throw new \DomainException('asaas_installment_edit_remote_conflict');
  unset($response['installment']);$parent=$s[3];$parent['hash']=$member['external_reference'];$parent['duedate']=$member['due_date'];
  $safe=self::normalize($response,['remote_id'=>$member['id']],['customer_id'=>$s[1]['customer_id'],'billing_type'=>$s[1]['billing_type'],'reviewed_amount'=>$member['value']],$parent,$member['id']);
  if(!in_array($safe['status'],['PENDING','OVERDUE'],true)||$safe['deleted']||$safe['adverse']||$safe['payment_date']!==''||$safe['credit_date']!=='')throw new \DomainException('asaas_installment_edit_unpaid_required');return $safe;
 }
 /** Review has no remote effects. Values across the existing group must retain the exact total. */
 public function reviewInstallmentEdit(int $issuer,int $actor,int $group,array $changes,callable $decrypt,?callable $transport=null):array
 {
  $s=$this->transaction(fn()=>$this->installmentEditSource($issuer,$actor,$group));$before=array_map(static fn($m)=>$m['member'],$s[5]);$after=$before;$changed=[];
  if(!$changes||count($changes)>100)throw new \DomainException('asaas_installment_edit_invalid');
  foreach($changes as $remote=>$change){if(!isset($before[$remote])||!is_array($change)||array_keys($change)!==['value','due_date']||!is_string($change['value'])||!is_string($change['due_date']))throw new \DomainException('asaas_installment_edit_invalid');$value=self::money($change['value']);$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$change['due_date']);if(\CompanyInvoiceAmounts::minor($value)<1||!$date||$date->format('Y-m-d')!==$change['due_date'])throw new \DomainException('asaas_installment_edit_invalid');$after[$remote]=array_replace($before[$remote],['value'=>$value,'due_date'=>$change['due_date']]);}
  $sum=static fn($items)=>array_sum(array_map(static fn($m)=>\CompanyInvoiceAmounts::minor($m['value']),$items));if($sum($before)!==$sum($after))throw new \DomainException('asaas_installment_edit_total_changed');
  foreach($after as $remote=>$member)if($member!==$before[$remote])$changed[]=$remote;if(!$changed)throw new \DomainException('asaas_installment_edit_empty');
  $guard=function()use($issuer,$actor,$group,$s,$changed){$now=$this->installmentEditSource($issuer,$actor,$group);if($now[2]['revision']!==$s[2]['revision']||$now[5]!==$s[5])throw new \DomainException('asaas_company_stale');$this->installmentEditEligible($now,$changed,$actor);return $now;};
  $this->transaction($guard);$client=$this->client($s[2],$decrypt,$transport);self::wallet($client,$s[2]);$this->transaction($guard);self::customer($client,$s[1]['customer_id'],$s[3]);
  foreach($changed as $remote){$this->transaction($guard);$this->installmentEditCanonical($s,$before[$remote],$client->request('GET','payments/'.$remote));}
  $this->transaction($guard);$key=CompanyInstallmentEditJournal::hash(['installment-edit',$issuer,$group,$actor,$s[2]['revision'],$before,$after]);
  return ['issuer_id'=>$issuer,'actor_id'=>$actor,'group_origin'=>$group,'account_revision'=>$s[2]['revision'],'before'=>$before,'after'=>$after,'key'=>$key];
 }
 private function installmentEditPlan(int $issuer,int $actor,int $group,string $key):?array
 {
  $plans=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit');foreach($plans as $plan)if(($plan['value']['key']??null)===$key){if((int)$plan['actor_id']!==$actor||($plan['value']['actor_id']??null)!==$actor)throw new \DomainException('asaas_installment_edit_denied');return $plan;}return null;
 }
 public function executeInstallmentEdit(int $issuer,int $actor,int $group,array $changes,string $approval,bool $confirmed,callable $decrypt,?callable $transport=null):array
 {
  if(!$confirmed)throw new \DomainException('asaas_company_confirmation_required');if(!preg_match('/^[a-f0-9]{64}$/D',$approval))throw new \DomainException('asaas_installment_edit_invalid');
  $plan=$this->transaction(function()use($issuer,$actor,$group,$approval){$this->installmentEditSource($issuer,$actor,$group,$approval);return $this->installmentEditPlan($issuer,$actor,$group,$approval);});
  if(!$plan){$review=$this->reviewInstallmentEdit($issuer,$actor,$group,$changes,$decrypt,$transport);if($review['key']!==$approval)throw new \DomainException('asaas_legacy_review_changed');
   $plan=$this->transaction(function()use($issuer,$actor,$group,$approval,$review){$s=$this->installmentEditSource($issuer,$actor,$group,$approval);$old=$this->installmentEditPlan($issuer,$actor,$group,$approval);if($old)return $old;if(array_map(static fn($m)=>$m['member'],$s[5])!==$review['before']||$s[2]['revision']!==$review['account_revision'])throw new \DomainException('asaas_company_stale');return CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$s[2]['revision'],'parcel_edit',$review);});
  }
  // A retry resumes only the frozen plan. Submitted fields cannot replace it.
  foreach($changes as $remote=>$change)if(!isset($plan['value']['after'][$remote])||$change!==['value'=>$plan['value']['after'][$remote]['value'],'due_date'=>$plan['value']['after'][$remote]['due_date']])throw new \DomainException('asaas_legacy_review_changed');
  return $this->resumeInstallmentEdit($issuer,$actor,$group,$approval,$decrypt,$transport);
 }
 public function installmentEditPage(int $issuer,int $actor,int $group):array
 {
  return $this->transaction(function()use($issuer,$actor,$group){
   $this->authorize($issuer,$actor,'configure');$this->authorize($issuer,$actor,'issue');
   $plans=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit');$last=$plans?end($plans):null;
   $s=$this->installmentEditSource($issuer,$actor,$group,$last['value']['key']??null);$pending=null;
   if($last){$done=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit_done');if(!array_filter($done,static fn($r)=>($r['value']['plan_id']??null)===(int)$last['id']))$pending=$last['value'];}
   return ['group_origin'=>$group,'group_id'=>$s[0]['batch_id'],'members'=>array_values(array_map(static fn($m)=>$m['member'],$s[5])),'pending'=>$pending];
  });
 }
 public function resumeInstallmentEdit(int $issuer,int $actor,int $group,string $key,callable $decrypt,?callable $transport=null):array
 {
  $read=function()use($issuer,$actor,$group,$key){$s=$this->installmentEditSource($issuer,$actor,$group,$key);$plan=$this->installmentEditPlan($issuer,$actor,$group,$key);if(!$plan||$plan['account_revision']!==$s[2]['revision'])throw new \DomainException('asaas_company_stale');$done=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit_done');foreach($done as $d)if(($d['value']['plan_id']??null)===(int)$plan['id'])return [$s,$plan,true];$changed=[];foreach($plan['value']['after'] as $remote=>$m)if($m!==$plan['value']['before'][$remote])$changed[]=$remote;$this->installmentEditEligible($s,$changed,$actor);return [$s,$plan,false];};
  [$s,$plan,$done]=$this->transaction($read);if($done)return ['key'=>$key,'complete'=>true,'items'=>[]];$client=$this->client($s[2],$decrypt,$transport);self::wallet($client,$s[2]);$this->transaction($read);self::customer($client,$s[1]['customer_id'],$s[3]);$items=[];
  foreach($plan['value']['after'] as $remote=>$target){if($target===$plan['value']['before'][$remote])continue;
   $claims=$this->transaction(function()use($read,$issuer,$group,$plan,$remote){$read();return array_values(array_filter(CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit_send'),static fn($r)=>($r['value']['plan_id']??null)===(int)$plan['id']&&($r['value']['remote_id']??null)===$remote));});
   if(!$claims){$this->installmentEditCanonical($s,$plan['value']['before'][$remote],$client->request('GET','payments/'.$remote));
    $send=$this->transaction(function()use($read,$issuer,$actor,$group,$plan,$remote){[$now]=$read();foreach(CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit_send') as $r)if(($r['value']['plan_id']??null)===(int)$plan['id']&&($r['value']['remote_id']??null)===$remote)return false;CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$now[2]['revision'],'parcel_edit_send',['issuer_id'=>$issuer,'plan_id'=>(int)$plan['id'],'remote_id'=>$remote]);return true;});
    if($send)try{$client->request('PUT','payments/'.$remote,['billingType'=>$target['billing_type'],'value'=>$target['value'],'dueDate'=>$target['due_date']]);}catch(\Throwable $e){/* Ambiguous response is recovered only by GET below or a later resume. */}
   }
   try{$this->transaction($read);$safe=$this->installmentEditCanonical($s,$target,$client->request('GET','payments/'.$remote));$this->transaction(function()use($read,$issuer,$actor,$group,$plan,$remote,$target,$safe){[$now]=$read();CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$now[2]['revision'],'parcel_edit_seen',['issuer_id'=>$issuer,'plan_id'=>(int)$plan['id'],'remote_id'=>$remote,'target'=>$target,'canonical'=>$safe]);});$items[$remote]='confirmed';}
   catch(\Throwable $e){$items[$remote]='unconfirmed';}
  }
  $complete=$this->transaction(function()use($read,$issuer,$actor,$group,$plan,$items){[$now,,$done]=$read();if($done)return true;if(in_array('unconfirmed',$items,true))return false;$results=[];$records=CompanyInstallmentEditJournal::records($this->db,$this->p,$issuer,$group,'parcel_edit_seen');foreach($plan['value']['after'] as $remote=>$target){if($target===$plan['value']['before'][$remote])continue;foreach($records as $r)if(($r['value']['plan_id']??null)===(int)$plan['id']&&($r['value']['remote_id']??null)===$remote&&($r['value']['target']??null)===$target&&$r['account_revision']===$now[2]['revision']&&$r['created_at']>=gmdate('Y-m-d H:i:s',time()-300))$results[$remote]=(int)$r['id'];if(!isset($results[$remote]))return false;}CompanyInstallmentEditJournal::append($this->db,$this->p,$issuer,$actor,$group,$now[2]['revision'],'parcel_edit_done',['issuer_id'=>$issuer,'plan_id'=>(int)$plan['id'],'results'=>$results]);$this->installmentGroupInOwned($issuer,$actor,$group);return true;});
  return ['key'=>$key,'complete'=>$complete,'items'=>$items];
 }
}
