<?php
declare(strict_types=1);
namespace Asaas;
require_once APPPATH.'services/CompanyInvoiceAmounts.php';
/** MANUAL mandate protocol from the documented Journey 3; no subscription or implicit retry. */
final class CompanyPixAuthorizationContract
{
 public const FREQUENCIES=['WEEKLY','MONTHLY','QUARTERLY','SEMIANNUALLY','ANNUALLY'];
 public static function request(array $source):array
 {
  foreach(['customer_id'=>64,'contract_id'=>35] as $key=>$length)if(!is_string($source[$key]??null)||!preg_match('/^[A-Za-z0-9_-]{1,'.$length.'}$/D',$source[$key]))throw new \DomainException('asaas_pix_authorization_invalid');
  if(!in_array($source['frequency']??null,self::FREQUENCIES,true))throw new \DomainException('asaas_pix_authorization_invalid');
  if(!is_string($source['start_date']??null))throw new \DomainException('asaas_pix_authorization_invalid');
  $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$source['start_date']);if(!$date||$date->format('Y-m-d')!==$source['start_date'])throw new \DomainException('asaas_pix_authorization_invalid');
  $amount=$source['amount']??null;if(!is_string($amount)||!preg_match('/^(0|[1-9][0-9]*)\.[0-9]{2}$/D',$amount)||\CompanyInvoiceAmounts::minor($amount)<1)throw new \DomainException('asaas_pix_authorization_invalid');
  return ['customerId'=>$source['customer_id'],'frequency'=>$source['frequency'],'contractId'=>$source['contract_id'],'startDate'=>$source['start_date'],'description'=>'Fatura e pagamentos recorrentes','paymentCreationMode'=>'MANUAL','retryPolicy'=>'NOT_ALLOWED','immediateQrCode'=>['expirationSeconds'=>600,'originalValue'=>$amount]];
 }
 public static function response(array $source,array $remote):array
 {
  self::request($source);
  foreach(['customerId'=>'customer_id','contractId'=>'contract_id','frequency'=>'frequency','startDate'=>'start_date'] as $key=>$local)if(($remote[$key]??null)!==$source[$local])throw new \DomainException('asaas_pix_authorization_conflict');
  $id=$remote['id']??null;$status=$remote['status']??null;
  if(!is_string($id)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$id)||!in_array($status,['CREATED','ACTIVE','CANCELLED','EXPIRED','REFUSED'],true)||!empty($remote['subscriptionId'])||($remote['paymentCreationMode']??'MANUAL')!=='MANUAL'||($remote['retryPolicy']??'NOT_ALLOWED')!=='NOT_ALLOWED')throw new \DomainException('asaas_pix_authorization_conflict');
  if(($source['remote_id']??null)!==null&&$source['remote_id']!==$id)throw new \DomainException('asaas_pix_authorization_conflict');
  if(isset($source['status'])&&(!in_array($source['status'],['CREATED','ACTIVE','CANCELLED','EXPIRED','REFUSED'],true)||(in_array($source['status'],['CANCELLED','EXPIRED','REFUSED'],true)&&$source['status']!==$status)||($source['status']==='ACTIVE'&&$status==='CREATED')))throw new \DomainException('asaas_pix_authorization_conflict');
  $qr=$remote['immediateQrCode']??[];if(!is_array($qr))throw new \DomainException('asaas_pix_authorization_conflict');$conciliation=$qr['conciliationIdentifier']??null;
  if(!is_string($conciliation)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$conciliation)||isset($source['conciliation_id'])&&$source['conciliation_id']!==null&&$source['conciliation_id']!==$conciliation)throw new \DomainException('asaas_pix_authorization_conflict');
  $expires=$qr['expirationDate']??null;if(!is_string($expires)||!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:?\d{2})?$/D',$expires))throw new \DomainException('asaas_pix_authorization_conflict');
  if(!checkdate((int)substr($expires,5,2),(int)substr($expires,8,2),(int)substr($expires,0,4))||(int)substr($expires,11,2)>23||(int)substr($expires,14,2)>59||(int)substr($expires,17,2)>59)throw new \DomainException('asaas_pix_authorization_conflict');
  try{$expiration=new \DateTimeImmutable($expires,new \DateTimeZone('America/Sao_Paulo'));}catch(\Throwable $error){throw new \DomainException('asaas_pix_authorization_conflict');}
  if($status==='CREATED'){$payload=$remote['payload']??null;$image=$remote['encodedImage']??'';if(!is_string($payload)||strlen($payload)<20||strlen($payload)>8192||preg_match('/[\x00-\x1f\x7f]/',$payload)||!is_string($image)||strlen($image)>262144||$image!==''&&base64_decode($image,true)===false)throw new \DomainException('asaas_pix_authorization_conflict');}
  else{$payload='';$image='';}
  return ['remote_id'=>$id,'status'=>$status,'conciliation_id'=>$conciliation,'expires_at'=>$expiration->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),'qr'=>['payload'=>$payload,'encoded_image'=>$image]];
 }
 /** Identity evidence only, after the caller authenticates the account; never a receipt or consent. */
 public static function initialPayment(array $source,array $payment,array $transaction):array
 {
  self::request($source);
  $conciliation=$source['conciliation_id']??null;$invoiceHash=$source['invoice_hash']??null;
  if(!is_string($conciliation)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$conciliation)||!is_string($invoiceHash)||!preg_match('/^[A-Za-z0-9_-]{1,200}$/D',$invoiceHash))throw new \DomainException('asaas_pix_payment_conflict');
  $id=$payment['id']??null;$tx=$payment['pixTransaction']??null;
  if(!is_string($id)||!preg_match('/^pay_[A-Za-z0-9_-]{1,60}$/D',$id)||!is_string($tx)||!preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$tx)
   ||($payment['customer']??null)!==$source['customer_id']||($payment['billingType']??null)!=='PIX'||!empty($payment['deleted'])||!empty($payment['installment'])||!empty($payment['subscription'])
   ||(!empty($payment['externalReference'])&&$payment['externalReference']!==$invoiceHash)
   ||\CompanyInvoiceAmounts::minor($payment['value']??null)!==\CompanyInvoiceAmounts::minor($source['amount'])
   ||($transaction['id']??null)!==$tx||($transaction['payment']??null)!==$id||($transaction['type']??null)!=='CREDIT'||($transaction['conciliationIdentifier']??null)!==$conciliation
   ||(isset($source['payment_id'])&&$source['payment_id']!==$id))throw new \DomainException('asaas_pix_payment_conflict');
  return ['payment_id'=>$id,'transaction_id'=>$tx,'conciliation_id'=>$conciliation,'customer_id'=>$source['customer_id'],'amount'=>$source['amount'],'invoice_hash'=>$invoiceHash];
 }

}
