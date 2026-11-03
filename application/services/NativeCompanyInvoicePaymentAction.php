<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyInvoicePaymentForm.php';
require_once __DIR__.'/NativeCompanyInvoiceFormInput.php';
require_once __DIR__.'/CompanyInvoiceDelivery.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
require_once __DIR__.'/InvoicePaymentWriter.php';
/** Original payment-form composition; requires a native POST/CSRF boundary at its caller. */
final class NativeCompanyInvoicePaymentAction
{
    public function __construct(private $db,private string $prefix){}
    public function submit(int $issuer,int $actor,array $input,string $baseUrl,callable $decrypt):array
    {
        $keys=['invoiceid','amount','date','paymentmode','transactionid','note','do_not_send_email_template','do_not_redirect','_native_payment_key','_native_payment_mode_revision','_native_invoice_revision'];
        if(array_diff(array_keys($input),$keys))throw new DomainException('company_payment_invalid_input');
        foreach(['_native_payment_key','_native_payment_mode_revision','_native_invoice_revision'] as $key)if(!is_string($input[$key]??null)||!preg_match('/^[a-f0-9]{64}$/D',$input[$key]))throw new DomainException('company_payment_invalid_input');
        foreach(['do_not_send_email_template','do_not_redirect'] as $key)if(isset($input[$key])&&!in_array($input[$key],['on','1',1],true))throw new DomainException('company_payment_invalid_input');
        $id=IssuerPolicy::id($input['invoiceid']??null);$preview=(new NativeCompanyInvoicePreviewData($this->db,$this->prefix))->read($issuer,$actor,$id);
        $receipt=IssuerStorage::query($this->db,'SELECT request_key FROM '.$this->prefix.'company_payment_receipts WHERE request_key=?',[$input['_native_payment_key']])->row_array();
        if(!$receipt&&!hash_equals($preview['revision'],$input['_native_invoice_revision']))throw new DomainException('company_invoice_stale');
        $store=new CompanySettingsStorage($this->db,$this->prefix);$format=$store->exists($issuer,'dateformat')?$store->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $payload=['amount'=>$input['amount']??null,'date'=>NativeCompanyInvoiceFormInput::date($input['date']??null,$format),'reference'=>$input['transactionid']??null,'note'=>$input['note']??''];
        $requested=!isset($input['do_not_send_email_template']);
        if($requested){
            $prior=$receipt?IssuerStorage::query($this->db,'SELECT email_requested FROM '.$this->prefix.'company_payment_notice_intents WHERE request_key=? AND issuer_id=? AND actor_id=? AND invoice_id=?',[$input['_native_payment_key'],$issuer,$actor,$id])->row_array():null;
            if($prior)$requested=(int)$prior['email_requested']===1;
            else{$template=CompanyEmailTemplates::findForIssuer($this->db,$this->prefix,$issuer,'invoice-payment-recorded','portuguese_br');$requested=$template&&(int)$template->active===1;}
        }
        $payment=(new \app\services\InvoicePaymentWriter($this->db))->recordCompanyManualWithMode($issuer,$actor,$id,$payload,$input['_native_payment_key'],$input['paymentmode']??null,$input['_native_payment_mode_revision'],$requested,$input['_native_invoice_revision']);
        $email=$requested?'pending':'suppressed';$queued=0;
        if($requested){
            // A committed payment must never be reported as rolled back when delivery
            // configuration or permission is unavailable; the durable intent permits review.
            try{$delivery=new CompanyInvoiceDelivery($this->db,$this->prefix);$state=$delivery->state($issuer,$actor,$id);$revision=(new CompanyInvoices($this->db,$this->prefix))->revision($issuer,$actor,$id);$failed=$state['recipients_truncated'];
                foreach($state['recipients'] as $contact){try{$delivery->queuePaymentNotice($issuer,$actor,$id,(int)$contact['id'],$revision,$input['_native_payment_key'],$baseUrl,$decrypt);$queued++;}catch(Throwable $error){$failed=true;}}
                if(!$failed&&$queued>0)$email='queued';
            }catch(Throwable $error){$email='pending';}
        }
        return ['payment_id'=>$payment,'invoice_id'=>$id,'email_status'=>$email,'email_queued'=>$queued,'sms_status'=>$requested?'unavailable':'suppressed'];
    }
}
