<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/AutomaticPixQuery.php';
require_once __DIR__.'/CompanyPixAuthorizationContract.php';
/** Account-scoped bounded discovery. Absence is uncertain, never permission to resend POST. */
final class AutomaticPixRecovery
{
 public static function find(array $source,callable $read):string
 {
  CompanyPixAuthorizationContract::request($source);$offset=0;$total=null;$seen=[];$found=null;$deadline=microtime(true)+45;
  for($page=0;$page<5;$page++){
   if(microtime(true)>=$deadline)throw new \DomainException('asaas_pix_recovery_incomplete');
   $response=$read(AutomaticPixQuery::path(['offset'=>$offset]));
   if(microtime(true)>=$deadline)throw new \DomainException('asaas_pix_recovery_incomplete');
   $validated=AutomaticPixQuery::read($response,['offset'=>$offset]);
   if($total!==null&&$total!==$validated['total'])throw new \DomainException('asaas_pix_recovery_incomplete');$total=$validated['total'];
   foreach($response['data'] as $row){
    $id=$row['id'];if(isset($seen[$id]))throw new \DomainException('asaas_pix_recovery_incomplete');$seen[$id]=true;
    if(($row['contractId']??null)!==$source['contract_id'])continue;
    if(($row['customerId']??null)!==$source['customer_id']||$found!==null)throw new \DomainException('asaas_pix_recovery_conflict');
    $found=$id;
   }
   if($validated['next_offset']===null){if($found===null)throw new \DomainException('asaas_pix_recovery_not_found');return $found;}
   $offset=$validated['next_offset'];
  }
  throw new \DomainException('asaas_pix_recovery_incomplete');
 }
}
