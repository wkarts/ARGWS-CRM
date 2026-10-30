<?php
declare(strict_types=1);
/** Exact list quick-stats denominator includes cancelled rows, unlike the dashboard percentage helper. */
final class NativeCompanyInvoiceStats
{
    public static function fromCounts(array $counts):array
    {
        if(array_keys($counts)!==range(1,6))throw new DomainException('native_company_invoice_statistics_invalid');
        $total=0;
        foreach($counts as$count){if(!is_int($count)||$count<0||$count>PHP_INT_MAX-$total)throw new DomainException('native_company_invoice_statistics_invalid');$total+=$count;}
        return ['counts'=>$counts,'total'=>$total];
    }
}
