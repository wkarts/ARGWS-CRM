<?php
declare(strict_types=1);
namespace Asaas;
/** Authenticated webhook projection. No payer, QR, account object or bank data is retained. */
final class PixEvent
{
 public const EVENTS=['PIX_AUTOMATIC_RECURRING_ELIGIBILITY_UPDATED','PIX_AUTOMATIC_RECURRING_AUTHORIZATION_CREATED','PIX_AUTOMATIC_RECURRING_AUTHORIZATION_ACTIVATED','PIX_AUTOMATIC_RECURRING_AUTHORIZATION_CANCELLED','PIX_AUTOMATIC_RECURRING_AUTHORIZATION_EXPIRED','PIX_AUTOMATIC_RECURRING_AUTHORIZATION_REFUSED','PIX_AUTOMATIC_RECURRING_PAYMENT_INSTRUCTION_CREATED','PIX_AUTOMATIC_RECURRING_PAYMENT_INSTRUCTION_SCHEDULED','PIX_AUTOMATIC_RECURRING_PAYMENT_INSTRUCTION_REFUSED','PIX_AUTOMATIC_RECURRING_PAYMENT_INSTRUCTION_CANCELLED'];
 private static function id($value,int $max=120):string {if(!is_string($value)||!preg_match('/^[A-Za-z0-9_-]{1,'.$max.'}$/D',$value))throw new \DomainException('asaas_pix_event_invalid');return $value;}
 public static function read(string $event,array $payload):array
 {
  if(!in_array($event,self::EVENTS,true))throw new \DomainException('asaas_pix_event_invalid');
  if($event===self::EVENTS[0]){$status=$payload['eligibility']['status']??null;if(!in_array($status,['ELIGIBLE','INELIGIBLE'],true))throw new \DomainException('asaas_pix_event_invalid');return ['kind'=>'eligibility','remote_id'=>'account','authorization_id'=>null,'payment_id'=>null,'status'=>$status];}
  if(str_contains($event,'_AUTHORIZATION_'))return ['kind'=>'authorization','remote_id'=>self::id($payload['authorization']['id']??null),'authorization_id'=>null,'payment_id'=>null,'status'=>null];
  $instruction=$payload['paymentInstruction']??[];
  return ['kind'=>'instruction','remote_id'=>self::id($instruction['id']??null),'authorization_id'=>self::id($instruction['authorization']['id']??null),'payment_id'=>self::id($instruction['paymentId']??null,64),'status'=>null];
 }
 public static function canonical(array $event,array $response):array
 {
  if(($response['id']??null)!==$event['remote_id'])throw new \DomainException('asaas_pix_event_identity_conflict');
  if($event['resource_kind']==='authorization')return AutomaticPixQuery::item($response);
  if(($response['authorization']['id']??null)!==$event['authorization_id']||($response['paymentId']??null)!==$event['payment_id'])throw new \DomainException('asaas_pix_event_identity_conflict');
  $status=$response['status']??null;if(!in_array($status,['AWAITING_REQUEST','SCHEDULED','DONE','CANCELLED','REFUSED'],true))throw new \DomainException('asaas_pix_event_identity_conflict');
  return ['remote_id'=>$event['remote_id'],'authorization_id'=>$event['authorization_id'],'payment_id'=>$event['payment_id'],'status'=>$status];
 }
}
