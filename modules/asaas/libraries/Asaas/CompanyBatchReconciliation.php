<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyReceivedReceipt.php';
/** One explicit item per request; browser queues items and can stop after an in-flight item. */
final class CompanyBatchReconciliation
{
    public function __construct(private $db,private string $prefix){}
    public function item($issuer,$actor,$origin,bool $apply,callable $decrypt,?callable $transport=null):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$origin=\IssuerPolicy::id($origin);
        $service=new CompanyService($this->db,$this->prefix);
        $guard=function()use($service,$issuer,$actor,$apply):void{
            if(class_exists('CompanySettingsRuntime',false)&&\CompanySettingsRuntime::active()&&(\CompanySettingsRuntime::issuerId()!==$issuer||\CompanySettingsRuntime::staffId()!==$actor))throw new \DomainException('asaas_company_access_denied');
            $service->authorize($issuer,$actor,'configuration');$service->authorize($issuer,$actor,$apply?'settle':'query');
            $row=\IssuerStorage::query($this->db,'SELECT installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['asaas'])->row_array();
            if(version_compare($row['installed_version']??'0','1.7.5','<'))throw new \DomainException('asaas_module_175_required');
        };
        $guard();$proof=$service->query($issuer,$actor,$origin,$decrypt,$transport);$guard();
        $result=['origin_id'=>$origin,'proof_id'=>$proof,'outcome'=>'compared','payment_id'=>null];
        if($apply){
            // Native receipt claims enforce immutable origin, freshness, amount, status, ACL and idempotency.
            $source=CompanyReceivedReceipt::forMonitor($origin,$proof);
            $result['payment_id']=(new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($issuer,$actor,$source);
            $result['outcome']='reconciled';
        }
        return $result;
    }
}
