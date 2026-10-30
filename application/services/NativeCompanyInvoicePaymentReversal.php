<?php
declare(strict_types=1);
require_once __DIR__.'/InvoicePaymentWriter.php';
require_once __DIR__.'/CompanyInvoices.php';
/** The original payment removal action records an explicit, auditable reversal. */
final class NativeCompanyInvoicePaymentReversal
{
    public function __construct(private $db,private string $p){}
    public function submit(int $issuer,int $actor,$payment,$invoice,array $post):void
    {
        $payment=IssuerPolicy::id($payment);$invoice=IssuerPolicy::id($invoice);
        if(array_diff(array_keys($post),['revision','request_key','reversal'])||!is_string($post['revision']??null)||!preg_match('/^[a-f0-9]{64}$/D',$post['revision'])||!is_string($post['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$post['request_key'])||!is_array($post['reversal']??null))throw new DomainException('company_credit_invalid_input');
        $input=$post['reversal'];
        if(array_diff(array_keys($input),['payment_id','amount','date','reference','reason'])||IssuerPolicy::id($input['payment_id']??null)!==$payment)throw new DomainException('company_credit_invalid_input');
        $input['payment_id']=$payment;$input['invoice_id']=$invoice;
        // The financial writer checks ownership, current ACL, the revision inside
        // its transaction, manual provenance, exact balance and durable replay.
        (new \app\services\InvoicePaymentWriter($this->db))->moveCompanyCredit($issuer,$actor,'manual_reverse',$input,$post['request_key'],null,null,null,$post['revision']);
    }
}
