<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyPixAuthorizationContract.php';
require_once __DIR__.'/AutomaticPixRecovery.php';
/** Durable invoice exclusion and canonical GET recovery. A reservation is not customer consent. */
trait CompanyPixReservations
{
 private function pixReservationReady():void
 {
  $this->module();$v=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';
  if(version_compare($v,'1.9.3','<'))throw new \DomainException('asaas_module_193_required');
  CompanyOriginGenerations::ensure($this->db,$this->p,false);
 }
 private function pixReservationSource(int $issuer,int $actor,int $origin):array
 {
  $this->pixReservationReady();$this->authorize($issuer,$actor,'issue');
  if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
  $row=$this->rows('SELECT * FROM '.$this->p.'asaas_payment_origins WHERE id=? AND issuer_id=? AND channel=? FOR UPDATE',[$origin,$issuer,'pix_mandate'])[0]??null;
  $meta=$this->rows('SELECT * FROM '.$this->p.'asaas_company_origin_meta WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;
  if(!$row||!$meta)throw new \DomainException('asaas_pix_reservation_denied');CompanyOriginGenerations::assertActive($row);
  $account=$this->account($issuer,(int)$meta['account_row']);$parent=$this->parent($issuer,(int)$row['invoice_id']);$this->unchanged($meta,$parent);
  if(!(int)$account['enabled']||$account['verified_at']===null||$row['account_id']!==$account['account_id']||$row['environment']!==$account['environment'])throw new \DomainException('asaas_company_account_unverified');
  $proofs=$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? FOR UPDATE',[$origin,'pix_reserve']);
  if(count($proofs)!==1)throw new \DomainException('asaas_pix_reservation_conflict');$proof=$proofs[0];$source=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
  if(!is_array($source)||self::hash($source)!==$proof['evidence_hash']||$proof['account_revision']!==$account['revision']||($source['issuer_id']??null)!==$issuer||($source['account_row']??null)!==(int)$account['id']||($source['invoice_id']??null)!==(int)$parent['id']||($source['invoice_hash']??null)!==$parent['hash']||($source['amount']??null)!==self::money($parent['total'])||($source['customer_id']??null)!==$meta['customer_id']||($source['request_key']??null)!==$meta['request_key'])throw new \DomainException('asaas_pix_reservation_conflict');
  CompanyPixAuthorizationContract::request($source);CompanyOriginPolicy::assertManual($this->db,$this->p,$origin,$issuer,$meta['request_key']);
  return [$row,$meta,$account,$parent,$source];
 }
 /** Internal portal composition; the caller must own native financial/identity tables. */
 public function pixConsentSourceInOwnedTransaction(int $issuer,int $actor,int $origin):array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());
  $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.9.4','<'))throw new \DomainException('asaas_module_194_required');
  return $this->pixReservationSource($issuer,$actor,$origin);
 }
 /** Operational list scoped to invoice visibility; no credentials or QR payloads. */
 public function pixOperations(int $issuer,int $actor):array
 {
  return $this->transaction(function()use($issuer,$actor){$this->pixReservationReady();$this->authorize($issuer,$actor,'view');if(!(new \IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'invoices','view'))throw new \DomainException('asaas_company_access_denied');
   $rows=$this->rows('SELECT o.id,o.invoice_id,o.remote_id,o.environment,o.state,m.account_row FROM '.$this->p.'asaas_payment_origins o JOIN '.$this->p.'asaas_company_origin_meta m ON m.origin_id=o.id WHERE o.issuer_id=? AND o.channel=? AND o.active_slot=1 ORDER BY o.id DESC LIMIT 100',[$issuer,'pix_mandate']);
   foreach($rows as &$row){$row['receipt']=$this->rows('SELECT payment_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? AND issuer_id=?',[$row['id'],$issuer])[0]['payment_id']??null;$row['initial_proof']=null;$proof=$this->rows('SELECT id,result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1',[$row['id'],'pix_initial'])[0]??null;if($proof){$safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($safe)||self::hash($safe)!==$proof['evidence_hash'])throw new \DomainException('asaas_pix_initial_requires_review');$row['initial_proof']=['id'=>(int)$proof['id']]+array_intersect_key($safe,array_flip(['payment_id','amount','net_value','fee','payment_date','status']));}}unset($row);return $rows;
  });
 }
 public function reservePixAuthorization(int $issuer,int $actor,int $invoice,int $account,string $customer,string $revision,string $key,string $frequency,string $startDate,?string $accountRevision=null):int
 {
  foreach([$issuer,$actor,$invoice,$account] as $id)\IssuerPolicy::id($id);self::revision($key);self::revision($revision);self::opaque($customer,64);
  return $this->transaction(function()use($issuer,$actor,$invoice,$account,$customer,$revision,$key,$frequency,$startDate,$accountRevision){
   $this->pixReservationReady();$this->authorize($issuer,$actor,'issue');
   if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
   $config=$this->account($issuer,$account);if($accountRevision!==null&&!hash_equals($config['revision'],$accountRevision))throw new \DomainException('asaas_company_stale');if(!(int)$config['enabled']||$config['verified_at']===null)throw new \DomainException('asaas_company_account_unverified');
   $parent=$this->parent($issuer,$invoice);$this->authorize($issuer,$actor,'issue');
   if(!hash_equals((new \CompanyInvoices($this->db,$this->p))->revision($issuer,$actor,$invoice),$revision))throw new \DomainException('asaas_company_stale');
   $old=$this->rows('SELECT id,channel FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=?'.CompanyOriginGenerations::predicate($this->db,$this->p).' FOR UPDATE',[$invoice])[0]??null;
   if($old){if($old['channel']!=='pix_mandate')throw new \DomainException('asaas_company_origin_conflict');$saved=$this->pixReservationSource($issuer,$actor,(int)$old['id']);if($saved[1]['request_key']!==$key||(int)$saved[2]['id']!==$account||$saved[4]['customer_id']!==$customer||$saved[4]['frequency']!==$frequency||$saved[4]['start_date']!==$startDate)throw new \DomainException('asaas_company_origin_conflict');return (int)$old['id'];}
   (new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,$invoice);(new \CompanyCurrencyUnits($this->db,$this->p))->assertWrite($parent['currency']);
   if(!in_array((int)$parent['status'],[1,4],true)||$this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$invoice])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('asaas_company_balance_requires_review');
   foreach(['bb_origins','efi_company_origins'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('asaas_company_multiple_providers');
   $source=['issuer_id'=>$issuer,'account_row'=>$account,'invoice_id'=>$invoice,'invoice_hash'=>$parent['hash'],'customer_id'=>$customer,'contract_id'=>'crm'.substr(self::hash([$issuer,$config['account_id'],$config['environment'],$invoice,$key]),0,32),'amount'=>self::money($parent['total']),'frequency'=>$frequency,'start_date'=>$startDate,'request_key'=>$key];CompanyPixAuthorizationContract::request($source);
   $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_payment_origins(invoice_id,invoice_hash,invoice_total,invoice_due_date,issuer_id,provider,account_id,environment,channel,remote_id,batch_id,state,last_code,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?)',[$invoice,$parent['hash'],$parent['total'],$parent['duedate'],$issuer,'asaas',$config['account_id'],$config['environment'],'pix_mandate','reserved','pix_consent_required',$now,$now]);$origin=(int)$this->db->insert_id();
   $snapshot=self::snapshot($parent);\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_origin_meta(origin_id,account_row,created_by,request_key,snapshot_hash,snapshot_json,customer_id,billing_type,created_at) VALUES(?,?,?,?,?,?,?,?,?)',[$origin,$account,$actor,$key,self::hash($snapshot),json_encode($snapshot,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$customer,'PIX',$now]);
   CompanyOriginPolicy::markManual($this->db,$this->p,$origin,$issuer,$key);
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$config['revision'],$actor,'pix_reserve',json_encode($source,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),self::hash($source),'consent_required',$now]);$this->audit($issuer,$actor,'company_asaas_pix_reserved',$origin);$this->pixReservationSource($issuer,$actor,$origin);return $origin;
  });
 }
 /** Recovery observes an exact contract on the authenticated account. Never retries POST or activates jobs. */
 public function recoverPixAuthorization(int $issuer,int $actor,int $origin,string $remote,callable $decrypt,?callable $transport=null):array
 {
  if($remote!=='')self::opaque($remote,120);$read=fn()=>$this->pixReservationSource($issuer,$actor,$origin);[$row,$meta,$account,$parent,$source]=$this->transaction($read);
  $client=$this->client($account,$decrypt,$transport);self::wallet($client,$account);$this->transaction($read);self::customer($client,$meta['customer_id'],$parent);$this->transaction($read);
  $previous=$this->transaction(function()use($read,$origin){$read();return $this->rows('SELECT result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin,'pix_recover'])[0]??null;});
  if($previous){$old=json_decode($previous['result_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($old)||self::hash($old)!==$previous['evidence_hash'])throw new \DomainException('asaas_pix_reservation_conflict');$source+=array_intersect_key($old,array_flip(['remote_id','status','conciliation_id']));}
  if($remote===''){
   $version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.9.4','<'))throw new \DomainException('asaas_module_194_required');
   $remote=$source['remote_id']??AutomaticPixRecovery::find($source,function(string $path)use($read,$client):array{$this->transaction($read);$response=$client->request('GET',$path);$this->transaction($read);return $response;});
  }
  $this->transaction($read);
  $safe=CompanyPixAuthorizationContract::response($source,$client->request('GET','pix/automatic/authorizations/'.$remote));unset($safe['qr']);
  if($safe['remote_id']!==$remote)throw new \DomainException('asaas_pix_authorization_conflict');
  return $this->transaction(function()use($read,$issuer,$actor,$origin,$safe,$account,$previous){[$row,,$current]=$read();if($current['revision']!==$account['revision'])throw new \DomainException('asaas_company_stale');$latest=$this->rows('SELECT result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin,'pix_recover'])[0]??null;if($latest!==$previous)throw new \DomainException('asaas_company_stale');
   $json=json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(($latest['result_json']??null)!==$json){\IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$account['revision'],$actor,'pix_recover',$json,self::hash($safe),'authorization_observed',gmdate('Y-m-d H:i:s')]);$this->audit($issuer,$actor,'company_asaas_pix_recovered',$origin);}
   $written=$this->rows('SELECT result_json,evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? AND operation=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin,'pix_recover'])[0]??null;if(!$written||$written['result_json']!==$json||$written['evidence_hash']!==self::hash($safe))throw new \RuntimeException('issuer_asaas_pix_recovery_unconfirmed');
   // Keep invoice reserved: remote ACTIVE is not a local consent or payment receipt.
   return $safe;
  });
 }
}
