<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyInstallmentGenerations.php';
/** Reviewed, once-only allocation of fully reversed principal retained in an older generation. */
final class CompanyInstallmentReopened
{
 public static function candidates($db,string $p,array $active,int $actor,string $revision,bool $fresh):array
 {
  $issuer=(int)$active['issuer_id'];$invoice=(int)$active['invoice_id'];$rows=static fn($sql,$args=[])=>\IssuerStorage::query($db,$sql,$args)->result_array();
  $all=$rows('SELECT * FROM '.$p.'asaas_payment_origins WHERE invoice_id=? ORDER BY id',[$invoice]);CompanyInstallmentGenerations::currentMembers($db,$p,$active,$all,$actor);
  $used=[];foreach($all as $o)if($o['channel']==='installments')foreach(CompanyInstallmentEditJournal::records($db,$p,$issuer,(int)$o['id'],'group_next') as $link)foreach($link['value']['reopened']??[] as $allocation)$used[(int)$allocation['origin_id']]=true;
  $posted=(new \CompanyAsaasRefundPostings($db,$p))->forInvoiceInOwned($issuer,$actor,$invoice);$out=[];
  foreach($all as $o){if($o['channel']!=='installment'||$o['batch_id']===$active['batch_id']||isset($used[(int)$o['id']]))continue;
   $r=$rows('SELECT payment_id FROM '.$p.'asaas_company_receipts WHERE origin_id=? AND issuer_id=?',[$o['id'],$issuer])[0]??null;
   if(!$r||!isset($posted[(int)$r['payment_id']])||!CompanyInstallmentGenerations::retainedPayment($db,$p,$issuer,$invoice,(int)$r['payment_id']))continue;
   $bound=CompanyInstallmentIdentity::member($db,$p,$issuer,(int)$o['id']);$e=CompanyInstallmentRefundClosure::evidence($db,$p,$issuer,$actor,$bound['members'][$o['remote_id']],$revision,$fresh);
   $out[]=['origin_id'=>(int)$o['id'],'source_root'=>(int)$bound['root']['id'],'value'=>$bound['identity']['member_value'],'closure'=>$e];
  }
  return $out;
 }
 public static function summary(array $allocations):array
 {return array_map(static fn($a)=>['origin_id'=>$a['origin_id'],'source_root'=>$a['source_root'],'value'=>$a['value'],'posting_key'=>$a['closure']['posting_key'],'posting_hash'=>$a['closure']['posting_hash']],$allocations);}
 public static function amount(array $allocations):int
 {$sum=0;foreach($allocations as $a){$sum+=\CompanyInvoiceAmounts::minor($a['value']);if($sum>\CompanyInvoiceAmounts::MAX)throw new \DomainException('asaas_installment_reopened_conflict');}return $sum;}
}
