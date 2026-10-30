<?php
declare(strict_types=1);

/** Payment rows projected only after the same owned invoice balance was reconciled. */
final class CompanyInvoicePaymentPresentation
{
    public static function captureInOwned($db,string $prefix,int $issuer,int $invoice,array $position):array
    {
        IssuerTransaction::assertOwned($db,$prefix,['invoices','invoicepaymentrecords','company_payment_receipts']);
        if(($position['issuer_id']??null)!==$issuer||($position['invoice_id']??null)!==$invoice)throw new DomainException('issuer_document_scope_mismatch');
        $rows=IssuerStorage::query($db,'SELECT p.id,p.date,p.amount,p.paymentmode,p.transactionid FROM '.$prefix.'invoicepaymentrecords p JOIN '.$prefix.'invoices i ON i.id=p.invoiceid WHERE i.id=? AND i.issuer_id=? ORDER BY p.id FOR UPDATE',[$invoice,$issuer])->result_array();
        $out=[];$originalTotal=0;$reversedTotal=0;$effectiveTotal=0;
        foreach($rows as$row){
            $id=IssuerPolicy::id($row['id']);$remaining=CompanyInvoiceAmounts::minor((string)$row['amount']);$manual=0;
            if($db->table_exists($prefix.'company_credit_entries')){
                IssuerTransaction::assertOwned($db,$prefix,['company_credit_entries']);
                foreach(IssuerStorage::query($db,"SELECT amount FROM {$prefix}company_credit_entries WHERE issuer_id=? AND invoice_id=? AND native_id=? AND kind='manual_reverse' ORDER BY created_at,request_key",[$issuer,$invoice,$id])->result_array()as$entry)$manual+=CompanyInvoiceAmounts::minor((string)$entry['amount']);
            }
            $external=isset($position['reversals'][$id])?CompanyInvoiceAmounts::minor($position['reversals'][$id]['cash_principal_reversed']):0;
            $original=$remaining+$manual;$reversed=$manual+$external;$effective=$remaining-$external;
            if($original>CompanyInvoiceAmounts::MAX||$effective<0)throw new DomainException('company_invoice_balance_review');
            $originalTotal+=$original;$reversedTotal+=$reversed;$effectiveTotal+=$effective;
            $out[]=['id'=>$id,'date'=>$row['date'],'mode'=>(string)$row['paymentmode'],'reference'=>(string)$row['transactionid'],'original'=>CompanyInvoiceAmounts::decimal($original),'reversed'=>CompanyInvoiceAmounts::decimal($reversed),'effective'=>CompanyInvoiceAmounts::decimal($effective)];
        }
        if($originalTotal!==CompanyInvoiceAmounts::minor($position['received_original'])||$reversedTotal!==CompanyInvoiceAmounts::minor($position['received_reversed'])||$effectiveTotal!==CompanyInvoiceAmounts::minor($position['received']))throw new DomainException('company_invoice_balance_review');
        return $out;
    }
    /** The server-configured application origin, never a submitted logo/payment URL. */
    public static function portalUrl(string $path):string
    {
        if(!preg_match('~^invoice/[1-9][0-9]*/[a-f0-9]{32}\?issuer_id=[1-9][0-9]*$~D',$path))throw new DomainException('issuer_document_invalid_content');
        $base=defined('APP_BASE_URL')?APP_BASE_URL:(function_exists('site_url')?site_url(''):null);
        $parts=is_string($base)?parse_url($base):false;
        if(!$parts||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||!in_array($parts['scheme']??null,['http','https'],true)||empty($parts['host'])||preg_match('/[\x00-\x20\x7f]/',$base))throw new DomainException('issuer_document_application_url_invalid');
        return rtrim($base,'/').'/'.$path;
    }
}
