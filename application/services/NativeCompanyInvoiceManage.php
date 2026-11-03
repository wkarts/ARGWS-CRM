<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoiceTableFactory.php';
require_once __DIR__.'/NativeCompanyFilters.php';
require_once __DIR__.'/NativeCompanyInvoiceRead.php';
require_once __DIR__.'/NativeCompanyInvoiceStats.php';
require_once __DIR__.'/NativeCompanyInvoiceIdentity.php';
/** DTO for the existing invoices/manage view and its existing fragments. */
final class NativeCompanyInvoiceManage
{
    public function __construct(private $db,private string $p){}
    public function data(int$issuer,int$actor,$invoiceId=''):array
    {
        if($invoiceId!==''){
            $reference=NativeCompanyResourceId::decode('invoice',$invoiceId);
            if($reference['source_kind']==='company_invoice_draft'){
                require_once __DIR__.'/CompanyInvoiceDrafts.php';
                $draft=(new CompanyInvoiceDrafts($this->db,$this->p))->document($issuer,$actor,$reference['source_id']);
                if($draft['state']!=='open')throw new DomainException('company_invoice_draft_unavailable');
            }else{(new NativeCompanyInvoiceIdentity($this->db,$this->p))->resolve($issuer,$actor,$invoiceId);}
            $invoiceId=$reference['canonical_id'];
        }
        $factory=new NativeCompanyInvoiceTableFactory($this->db,$this->p);$catalogue=$factory->data($issuer,$actor);$table=$factory->make($issuer,$actor);
        $filters=(new NativeCompanyFilters($this->db,$this->p,$issuer,$actor,$table))->listing();
        $table=$factory->make($issuer,$actor,'invoices',$filters);
        $summary=(new NativeCompanyInvoiceRead($this->db,$this->p))->summary($issuer,$actor);
        return ['invoiceid'=>$invoiceId,'title'=>_l('invoices'),'invoices_table'=>$table,
            'invoices_years'=>$catalogue['invoices_years'],'invoices_sale_agents'=>$catalogue['invoices_sale_agents'],
            'invoices_statuses'=>$catalogue['invoices_statuses'],'payment_modes'=>$catalogue['payment_modes'],
            'native_company_invoice_manage'=>true,
            'native_invoice_statistics'=>NativeCompanyInvoiceStats::fromCounts($summary['counts']),
            'native_invoice_custom_fields'=>array_values(array_filter($catalogue['customDefinitions'],static fn($d)=>(int)$d['show_on_table']===1))];
    }
}
