<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyFinancialApiGuard.php';require_once __DIR__.'/CompanyCredits.php';require_once __DIR__.'/InvoicePaymentWriter.php';

/** Explicit company payments/credits. No bank action or legacy API credential is consumed. */
final class CompanyFinancialApiService
{
    private CompanyApiTokens $tokens;
    public function __construct(private $db,private string $p){CompanyApiSchema::assertValid($db,$p);CompanyFinancialApiSchema::assertValid($db,$p);$this->tokens=new CompanyApiTokens($db,$p);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_values(array_unique(array_merge(['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_audit','company_api_tokens','company_api_token_requests','company_financial_api_requests','invoices','issuers_invoice_documents','invoicepaymentrecords','company_payment_receipts','creditnotes','credits','creditnote_refunds','company_credit_documents','company_credit_entries','currencies','company_currency_units'],(new CompanyInvoiceBalance($this->db,$this->p))->tables())));}
    private function read(string $secret,string $scope,string $action,array $context,callable $work)
    {
        $who=$this->tokens->resolve($secret,$scope);return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($secret,$scope,$action,$context,$who,$work){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($who['issuer_id'],$who['actor_id']);$this->tokens->guard($secret,$scope,$who['issuer_id'],$who['actor_id']);$this->tokens->usedInOwnedTransaction($secret,$scope,$who['issuer_id'],$who['actor_id'],$action,null,$context);$data=$work($who);$this->tokens->guard($secret,$scope,$who['issuer_id'],$who['actor_id']);if($work($who)!==$data)throw new DomainException('company_api_resource_changed');return ['issuer_id'=>$who['issuer_id']]+$data;});
    }
    private static function cursor($after):int{return $after===0||$after==='0'?0:IssuerPolicy::id($after);}
    public function payments(string $secret,$invoice,$after=0):array
    {
        $invoice=IssuerPolicy::id($invoice);$after=self::cursor($after);return $this->read($secret,'payments:read','payment_list',['invoice_id'=>$invoice,'after'=>$after],function($who)use($invoice,$after):array{
            $issuer=$who['issuer_id'];$position=(new CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$who['actor_id'],$invoice);$parent=$this->rows('SELECT currency FROM '.$this->p.'invoices WHERE id=? AND issuer_id=?',[$invoice,$issuer])[0]??null;if(!$parent)throw new DomainException('company_invoice_access_denied');$unit=(new CompanyCurrencyUnits($this->db,$this->p))->resolveRead($parent['currency']);
            $rows=$this->rows('SELECT p.id,p.amount native_amount,p.date,p.paymentmode,p.transactionid reference,p.note FROM '.$this->p.'invoicepaymentrecords p JOIN '.$this->p.'invoices i ON i.id=p.invoiceid WHERE p.invoiceid=? AND i.issuer_id=? AND p.id>? ORDER BY p.id LIMIT 51',[$invoice,$issuer,$after]);$more=count($rows)>50;if($more)array_pop($rows);
            foreach($rows as &$row){$id=(int)$row['id'];$manual=0;foreach($this->rows("SELECT amount FROM {$this->p}company_credit_entries WHERE issuer_id=? AND invoice_id=? AND kind='manual_reverse' AND native_id=?",[$issuer,$invoice,$id]) as $entry)$manual+=CompanyInvoiceAmounts::minor($entry['amount']);$current=CompanyInvoiceAmounts::minor($row['native_amount']);$external=$position['reversals'][$id]??null;$reversed=$external===null?0:CompanyInvoiceAmounts::minor($external['cash_principal_reversed']);$row['id']=$id;$row['original_amount']=CompanyInvoiceAmounts::decimal($current+$manual);$row['manual_reversed_amount']=CompanyInvoiceAmounts::decimal($manual);$row['external_reversed_amount']=CompanyInvoiceAmounts::decimal($reversed);$row['effective_amount']=CompanyInvoiceAmounts::decimal($current-$reversed);$row['bank_components']=$position['components'][$id]??null;$row['external_reversal']=$external;}unset($row);
            return ['invoice_id'=>$invoice,'currency'=>$unit['iso_code'],'currency_basis'=>$unit['basis'],'position'=>$position,'items'=>$rows,'next'=>$more?(int)$rows[count($rows)-1]['id']:null];
        });
    }
    public function credits(string $secret,$after=0):array
    {
        $after=self::cursor($after);return $this->read($secret,'credits:read','credit_list',['after'=>$after],function($who)use($after):array{$rows=$this->rows('SELECT c.id FROM '.$this->p.'creditnotes c WHERE c.issuer_id=? AND c.id>? ORDER BY c.id LIMIT 51',[$who['issuer_id'],$after]);$more=count($rows)>50;if($more)array_pop($rows);$items=[];$reader=new CompanyCredits($this->db,$this->p);foreach($rows as $row){$doc=$reader->inOwnedTransaction($who['issuer_id'],$who['actor_id'],$row['id']);$items[]=array_intersect_key($doc,array_flip(['id','issuer_id','client_id','currency','currency_basis','number','date','status','available','applied','refunded']))+['total'=>$doc['amounts']['total']];}return ['items'=>$items,'next'=>$more?(int)$rows[count($rows)-1]['id']:null];});
    }
    public function credit(string $secret,$credit):array
    {
        $credit=IssuerPolicy::id($credit);return $this->read($secret,'credits:read','credit_read',['credit_id'=>$credit],fn($who):array=>['document'=>(new CompanyCredits($this->db,$this->p))->inOwnedTransaction($who['issuer_id'],$who['actor_id'],$credit)]);
    }
    public function creditPdf(string $secret,$credit):string
    {
        $credit=IssuerPolicy::id($credit);$who=$this->tokens->resolve($secret,'credits:pdf');$reader=new CompanyCredits($this->db,$this->p);$before=$reader->get($who['issuer_id'],$who['actor_id'],$credit);$bytes=$reader->pdf($who['issuer_id'],$who['actor_id'],$credit);
        $verified=$this->read($secret,'credits:pdf','credit_pdf',['credit_id'=>$credit],fn($current):array=>['document'=>$reader->inOwnedTransaction($current['issuer_id'],$current['actor_id'],$credit)]);if($verified['document']!==$before)throw new DomainException('company_api_resource_changed');return $bytes;
    }
    private static function canonical($value)
    {
        if(!is_array($value))return $value;if(!array_is_list($value))ksort($value,SORT_STRING);foreach($value as &$child)$child=self::canonical($child);unset($child);return $value;
    }
    private function writeIdentity(string $secret,string $operation,array $target,array $input,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_api_invalid_input');$who=$this->tokens->resolve($secret,CompanyFinancialApiGuard::OPERATIONS[$operation]);$nativeKey=hash('sha256','company-financial-api|'.$who['issuer_id'].'|'.$who['actor_id'].'|'.$operation.'|'.$key);$hash=hash('sha256',json_encode(self::canonical([$operation,$target,$input]),JSON_THROW_ON_ERROR));$guard=new CompanyFinancialApiGuard($this->db,$this->p,$secret,$who['issuer_id'],$who['actor_id'],$operation,$target,$nativeKey,$hash);return [$who,$nativeKey,$guard];
    }
    private static function amountInput(array $input):void{if(!is_string($input['amount']??null))throw new DomainException('company_api_invalid_input');}
    public function recordManual(string $secret,$invoice,array $input,string $key):array
    {
        $invoice=IssuerPolicy::id($invoice);self::amountInput($input);[$who,$nativeKey,$guard]=$this->writeIdentity($secret,'manual_payment',['invoice_id'=>$invoice],$input,$key);$id=(new app\services\InvoicePaymentWriter($this->db))->recordCompanyManual($who['issuer_id'],$who['actor_id'],$invoice,$input,$nativeKey,$guard);return ['issuer_id'=>$who['issuer_id'],'invoice_id'=>$invoice,'payment_id'=>$id,'replayed'=>$guard->replayed()];
    }
    public function issueCredit(string $secret,array $input,string $key):array
    {
        $target=['client_id'=>IssuerPolicy::id($input['client_id']??null),'currency'=>IssuerPolicy::id($input['currency']??null)];if(!is_array($input['items']??null))throw new DomainException('company_api_invalid_input');foreach(['discount_value','adjustment'] as $field)if(isset($input[$field])&&!is_string($input[$field]))throw new DomainException('company_api_invalid_input');foreach($input['items'] as $item){if(!is_array($item)||!is_string($item['qty']??null)||!is_string($item['rate']??null))throw new DomainException('company_api_invalid_input');if(isset($item['taxes'])&&!is_array($item['taxes']))throw new DomainException('company_api_invalid_input');if(isset($item['taxrate'])&&!is_string($item['taxrate']))throw new DomainException('company_api_invalid_input');foreach($item['taxes']??[] as $tax)if(!is_array($tax)||!is_string($tax['rate']??null))throw new DomainException('company_api_invalid_input');}
        [$who,$nativeKey,$guard]=$this->writeIdentity($secret,'credit_issue',$target,$input,$key);$id=(new app\services\InvoicePaymentWriter($this->db))->issueCompanyCredit($who['issuer_id'],$who['actor_id'],$input,$nativeKey,$guard);return ['issuer_id'=>$who['issuer_id'],'credit_id'=>$id,'replayed'=>$guard->replayed()];
    }
    public function moveCredit(string $secret,string $kind,$id,array $input,string $key):array
    {
        if(!in_array($kind,['apply','unapply','refund','refund_reverse','manual_reverse'],true))throw new DomainException('company_api_invalid_input');$id=IssuerPolicy::id($id);self::amountInput($input);if(array_key_exists('credit_id',$input)||array_key_exists('payment_id',$input))throw new DomainException('company_api_invalid_input');
        if($kind==='manual_reverse')$input['payment_id']=$id;else $input['credit_id']=$id;
        $target=['credit_id'=>isset($input['credit_id'])?IssuerPolicy::id($input['credit_id']):null,'invoice_id'=>isset($input['invoice_id'])?IssuerPolicy::id($input['invoice_id']):null,'parent_key'=>$input['parent_key']??null,'payment_id'=>isset($input['payment_id'])?IssuerPolicy::id($input['payment_id']):null];$operation=$kind==='manual_reverse'?'manual_reverse':'credit_'.$kind;
        [$who,$nativeKey,$guard]=$this->writeIdentity($secret,$operation,$target,$input,$key);$entry=(new app\services\InvoicePaymentWriter($this->db))->moveCompanyCredit($who['issuer_id'],$who['actor_id'],$kind,$input,$nativeKey,$guard);return ['issuer_id'=>$who['issuer_id'],'entry_key'=>$entry,'replayed'=>$guard->replayed()];
    }
    public function voidCredit(string $secret,$credit,string $key):array
    {
        $credit=IssuerPolicy::id($credit);[$who,$nativeKey,$guard]=$this->writeIdentity($secret,'credit_void',['credit_id'=>$credit],[],$key);(new app\services\InvoicePaymentWriter($this->db))->voidCompanyCredit($who['issuer_id'],$who['actor_id'],$credit,$nativeKey,$guard);return ['issuer_id'=>$who['issuer_id'],'credit_id'=>$credit,'status'=>3,'replayed'=>$guard->replayed()];
    }
}
