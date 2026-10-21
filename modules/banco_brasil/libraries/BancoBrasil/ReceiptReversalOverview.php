<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Reconciliation.php';require_once APPPATH.'services/CompanyInvoiceBalance.php';
/** Authorized current disposition of original component receipts. No bank operation or financial write. */
final class ReceiptReversalOverview
{
    public function __construct(private $db,private string $p){}
    public function forIssuer($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$balance=new \CompanyInvoiceBalance($this->db,$this->p);$tables=array_values(array_unique(array_merge(Reconciliation::tables(),$balance->tables(),['modules'])));
        return \IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$actor,$balance):array{(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);Reconciliation::authorize($this->db,$this->p,$issuer,$actor,'reconcile');$rows=\IssuerStorage::query($this->db,"SELECT r.event_id,r.payment_id,p.invoiceid FROM {$this->p}bb_event_receipts r JOIN {$this->p}bb_return_events e ON e.id=r.event_id AND e.issuer_id=r.issuer_id JOIN {$this->p}invoicepaymentrecords p ON p.id=r.payment_id JOIN {$this->p}invoices i ON i.id=p.invoiceid AND i.issuer_id=r.issuer_id WHERE r.issuer_id=? ORDER BY r.event_id DESC LIMIT 50",[$issuer])->result_array();$positions=[];$result=[];foreach($rows as $row){$invoice=(int)$row['invoiceid'];$payment=(int)$row['payment_id'];$position=$positions[$invoice]??= $balance->inOwned($issuer,$actor,$invoice);if(!isset($position['components'][$payment]))continue;$component=$position['components'][$payment];$reversal=$position['reversals'][$payment]??null;$result[(int)$row['event_id']]=['invoice_id'=>$invoice,'payment_id'=>$payment,'basis'=>$component['basis'],'fees'=>$component['fees'],'net_observed'=>$component['net_observed'],'cash_principal'=>$component['cash_principal'],'principal_extinguished'=>$component['principal_extinguished'],'paid'=>$component['paid'],'discount'=>$component['discount'],'abatement'=>$component['abatement'],'charges_aggregate'=>$component['charges_aggregate'],'reversed'=>$reversal!==null,'reversal_date'=>$reversal['book_date']??null,'reversal_reference'=>$reversal['external_reference']??null];}Reconciliation::authorize($this->db,$this->p,$issuer,$actor,'reconcile');return $result;});
    }
}
