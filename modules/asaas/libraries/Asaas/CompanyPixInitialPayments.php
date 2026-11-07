<?php
declare(strict_types=1);
namespace Asaas;
/** Initial Journey 3 payment is independent of authorization ACTIVE/REFUSED. */
trait CompanyPixInitialPayments
{
 private function pixInitialSource(int $issuer,int $actor,int $origin):array
 {
  $this->authorize($issuer,$actor,'settle');$version=$this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0';if(version_compare($version,'1.9.5','<'))throw new \DomainException('asaas_module_195_required');
  $tuple=$this->pixReservationSource($issuer,$actor,$origin);$authorization=$this->rows("SELECT result_json,evidence_hash,account_revision FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='pix_recover' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$origin])[0]??null;
  if(!$authorization)throw new \DomainException('asaas_pix_initial_requires_authorization');$safe=json_decode($authorization['result_json'],true,32,JSON_THROW_ON_ERROR);
  if(!is_array($safe)||self::hash($safe)!==$authorization['evidence_hash']||$authorization['account_revision']!==$tuple[2]['revision'])throw new \DomainException('asaas_pix_reservation_conflict');
  $tuple[4]['conciliation_id']=$safe['conciliation_id']??null;if($tuple[0]['remote_id']!==null)$tuple[4]['payment_id']=$tuple[0]['remote_id'];return $tuple;
 }
 public function pixInitialIdentityInOwned(int $issuer,int $actor,int $origin):array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());return $this->pixInitialSource($issuer,$actor,$origin)[4];
 }
 private static function pixInitialFinancial(array $source,array $payment,array $transaction):array
 {
  $identity=CompanyPixAuthorizationContract::initialPayment($source,$payment,$transaction);
  if(($payment['status']??null)!=='RECEIVED'||($payment['deleted']??false)!==false)throw new \DomainException('asaas_pix_initial_not_received');
  foreach(['refunds','chargeback','split','anticipated'] as $field)if(!empty($payment[$field]))throw new \DomainException('asaas_pix_initial_not_received');
  foreach(['fine','interest','discount'] as $field)if(isset($payment[$field]['value'])&&self::money($payment[$field]['value'])!=='0.00')throw new \DomainException('asaas_pix_initial_not_received');
  if(isset($payment['interestValue'])&&self::money($payment['interestValue'])!=='0.00'||isset($payment['originalValue'])&&self::money($payment['originalValue'])!==$source['amount'])throw new \DomainException('asaas_pix_initial_not_received');
  $net=self::money($payment['netValue']??null);$gross=\CompanyInvoiceAmounts::minor($source['amount']);$netMinor=\CompanyInvoiceAmounts::minor($net);if($netMinor>$gross)throw new \DomainException('asaas_pix_initial_not_received');
  $date=$payment['paymentDate']??null;$parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;if(!$parsed||$parsed->format('Y-m-d')!==$date)throw new \DomainException('asaas_pix_initial_not_received');
  return $identity+['status'=>'RECEIVED','net_value'=>$net,'fee'=>\CompanyInvoiceAmounts::decimal($gross-$netMinor),'payment_date'=>$date];
 }
 /** GET only, using the already authorized company account before looking up payment identifiers. */
 public function observePixInitialPayment(int $issuer,int $actor,int $origin,string $remote,callable $decrypt,?callable $transport=null):int
 {
  self::opaque($remote,64);$read=fn()=>$this->pixInitialSource($issuer,$actor,$origin);[$row,$meta,$account,$parent,$source]=$this->transaction($read);
  $client=$this->client($account,$decrypt,$transport);self::wallet($client,$account);$this->transaction($read);self::customer($client,$source['customer_id'],$parent);$this->transaction($read);
  $payment=$client->request('GET','payments/'.$remote);$this->transaction($read);
  if(($payment['id']??null)!==$remote||!is_string($payment['pixTransaction']??null)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$payment['pixTransaction']))throw new \DomainException('asaas_pix_payment_conflict');
  $transaction=$client->request('GET','pix/transactions/'.$payment['pixTransaction']);$this->transaction($read);$safe=self::pixInitialFinancial($source,$payment,$transaction);
  $again=$client->request('GET','payments/'.$remote);if(self::pixInitialFinancial($source,$again,$transaction)!==$safe)throw new \DomainException('asaas_pix_payment_conflict');
  return $this->transaction(function()use($read,$issuer,$actor,$origin,$remote,$safe,$account,$source){
   [$row,,$current,,$currentSource]=$read();if($current['revision']!==$account['revision']||$currentSource!==$source)throw new \DomainException('asaas_company_stale');
   if($this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE id<>? AND account_id=? AND environment=? AND remote_id=? FOR UPDATE',[$origin,$account['account_id'],$account['environment'],$remote]))throw new \DomainException('asaas_pix_payment_conflict');
   \IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_payment_origins SET remote_id=?,updated_at=? WHERE id=? AND issuer_id=?',[$remote,gmdate('Y-m-d H:i:s'),$origin,$issuer]);
   \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_proofs(origin_id,account_revision,actor_id,operation,result_json,evidence_hash,state,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$account['revision'],$actor,'pix_initial',json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),self::hash($safe),'received_observed',gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();$this->audit($issuer,$actor,'company_asaas_pix_initial_observed',$origin);
   if(($this->rows('SELECT evidence_hash FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=?',[$id,$origin])[0]['evidence_hash']??null)!==self::hash($safe))throw new \RuntimeException('issuer_asaas_pix_initial_unconfirmed');return $id;
  });
 }
 public function pixInitialClaim(int $issuer,int $actor,int $origin,int $proofId):array
 {
  \IssuerTransaction::assertOwned($this->db,$this->p,$this->tablesForRun());[$row,$meta,$account,$parent,$source]=$this->pixInitialSource($issuer,$actor,$origin);
  $proof=$this->rows("SELECT * FROM {$this->p}asaas_company_proofs WHERE origin_id=? AND operation='pix_initial' ORDER BY id DESC LIMIT 1 FOR UPDATE",[$origin])[0]??null;
  if(!$proof||(int)$proof['id']!==$proofId||$proof['account_revision']!==$account['revision']||$proof['state']!=='received_observed'||$proof['created_at']>gmdate('Y-m-d H:i:s'))throw new \DomainException('asaas_pix_initial_requires_review');
  $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
  if(!is_array($safe)||self::hash($safe)!==$proof['evidence_hash']||($safe['status']??null)!=='RECEIVED'||$safe['payment_id']!==$row['remote_id']||$safe['conciliation_id']!==$source['conciliation_id']||$safe['customer_id']!==$source['customer_id']||$safe['amount']!==$source['amount']||$safe['invoice_hash']!==$source['invoice_hash'])throw new \DomainException('asaas_pix_initial_requires_review');
  $date=$safe['payment_date'];$today=(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');if($date<$parent['date']||$date>$today)throw new \DomainException('asaas_pix_initial_requires_review');
  foreach($this->rows('SELECT event_type,state,created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$row['remote_id']]) as $event)if(!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_RECEIVED'],true)||in_array($event['state'],['conflict','review_required'],true)||$event['created_at']>$proof['created_at'])throw new \DomainException('asaas_company_adverse_history');
  foreach(['bb_origins','efi_company_origins'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE invoice_id=? FOR UPDATE',[$row['invoice_id']]))throw new \DomainException('asaas_company_multiple_providers');
  $key=self::hash(['company-asaas-received',$issuer,$account['account_id'],$account['environment'],$row['remote_id']]);$evidence=self::hash([$meta['snapshot_hash'],$safe]);
  $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? OR canonical_key=? FOR UPDATE',[$origin,$key]);
  if(count($old)>1||$old&&((int)$old[0]['origin_id']!==$origin||(int)$old[0]['issuer_id']!==$issuer||$old[0]['canonical_key']!==$key||$old[0]['evidence_hash']!==$evidence))throw new \DomainException('asaas_company_receipt_conflict');
  if(!$old&&$proof['created_at']<gmdate('Y-m-d H:i:s',time()-300))throw new \DomainException('asaas_pix_initial_requires_review');
  $payments=$this->rows('SELECT id,amount,transactionid,paymentmode FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$row['invoice_id']]);
  if($this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$row['invoice_id']]))throw new \DomainException('asaas_pix_initial_requires_review');
  if($payments){$native=$this->rows('SELECT payment_id FROM '.$this->p.'company_payment_receipts WHERE request_key=? AND issuer_id=? AND invoice_id=? FOR UPDATE',[$key,$issuer,$row['invoice_id']])[0]??null;if(count($payments)!==1||!$native||(int)$native['payment_id']!==(int)$payments[0]['id']||self::money($payments[0]['amount'])!==$safe['amount']||$payments[0]['transactionid']!=='ASAAS-'.$key||$payments[0]['paymentmode']!=='asaas_company_received')throw new \DomainException('asaas_pix_initial_requires_review');}

  return ['origin'=>$origin,'invoice'=>(int)$row['invoice_id'],'key'=>$key,'input'=>['amount'=>$safe['amount'],'date'=>$date,'reference'=>'ASAAS-'.$key,'note'=>'Recebimento inicial Pix Automático. Líquido '.$safe['net_value'].'; tarifa observada '.$safe['fee'].'. Tarifa não contabilizada como despesa.'],'evidence'=>$evidence,'proof'=>$proofId];
 }
 public function pixInitialApplied(int $issuer,int $actor,int $origin,array $claim,int $payment):void
 {
  if($this->pixInitialClaim($issuer,$actor,$origin,$claim['proof'])!==$claim)throw new \DomainException('asaas_company_receipt_conflict');
  $native=$this->rows('SELECT r.payment_id,p.invoiceid,p.amount,p.transactionid,p.paymentmode FROM '.$this->p.'company_payment_receipts r JOIN '.$this->p.'invoicepaymentrecords p ON p.id=r.payment_id WHERE r.request_key=? AND r.issuer_id=? AND r.invoice_id=? FOR UPDATE',[$claim['key'],$issuer,$claim['invoice']])[0]??null;
  if(!$native||(int)$native['payment_id']!==$payment||(int)$native['invoiceid']!==$claim['invoice']||self::money($native['amount'])!==$claim['input']['amount']||$native['transactionid']!==$claim['input']['reference']||$native['paymentmode']!=='asaas_company_received')throw new \DomainException('asaas_company_receipt_conflict');
  $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? FOR UPDATE',[$origin])[0]??null;
  if($old){if((int)$old['payment_id']!==$payment||$old['canonical_key']!==$claim['key']||$old['evidence_hash']!==$claim['evidence'])throw new \DomainException('asaas_company_receipt_conflict');return;}
  \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_receipts(origin_id,issuer_id,proof_id,canonical_key,evidence_hash,payment_id,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$issuer,$claim['proof'],$claim['key'],$claim['evidence'],$payment,$actor,gmdate('Y-m-d H:i:s')]);
  if(!$this->rows('SELECT origin_id FROM '.$this->p.'asaas_company_receipts WHERE origin_id=? AND payment_id=? AND canonical_key=?',[$origin,$payment,$claim['key']]))throw new \RuntimeException('issuer_asaas_receipt_unconfirmed');$this->audit($issuer,$actor,'company_asaas_pix_initial_received',$origin);
 }
}
