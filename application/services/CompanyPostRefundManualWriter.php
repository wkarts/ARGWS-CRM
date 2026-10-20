<?php
declare(strict_types=1);
namespace app\services;
/** A narrow exception to the ordinary provider-origin veto, authorized by exact closed source only. */
trait CompanyPostRefundManualWriter
{
    public function recordCompanyManualAfterAsaasFullRefund($issuer,$actor,\CompanyPostRefundAsaasManualSource $source):int
    {
        require_once __DIR__.'/CompanyPostRefundAsaasManualSource.php';
        if(get_class($source)!=='CompanyPostRefundAsaasManualSource')throw new \DomainException('company_post_refund_manual_source_denied');
        $i=\IssuerPolicy::id($issuer);$a=\IssuerPolicy::id($actor);$p=db_prefix();$source->assertStorage($this->db,$p);
        $tables=array_values(array_unique(array_merge($source->tables(),(new \CompanyInvoiceBalance($this->db,$p))->tables(),(new \CompanyCurrencyUnits($this->db,$p))->tables(),(new \CompanyFinancialJournal($this->db,$p))->tables('payment'))));
        return \IssuerTransaction::run($this->db,$p,$tables,function()use($source,$i,$a,$p):int{
            $c=$source->claim($this->db,$p,$i,$a);$invoice=self::invoiceId($c['invoice']);
            [$data,$amount,$hash]=$this->companyReceiptInput($invoice,$c['input'],$c['key'],'company_manual','Recebimento manual');
            $id=$this->companyReceiptLocked($i,$a,$invoice,$c['key'],$amount,$data,$hash,$p,[],false);
            $source->applied($this->db,$p,$i,$a,$id);return$id;
        });
    }
}
