<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once __DIR__.'/CompanyManualSettlementSchema.php';
require_once APPPATH.'services/CompanyAsaasPartialManualSource.php';
/** Explicit policy and one durable full-receipt command per immutable remote origin. */
final class CompanyManualSettlements
{
    public const ZERO='0000000000000000000000000000000000000000000000000000000000000000';
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $v):string{return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
    public function tables():array{return array_values(array_unique(array_merge((new CompanyService($this->db,$this->p))->tablesForRun(),CompanyManualSettlementSchema::tables(),(new \app\services\InvoicePaymentWriter($this->db))->manualModeParticipants(),(new \CompanyAsaasPartialManualSource($this->db,$this->p,1))->tables())));}
    private function configurationTables():array{return array_values(array_unique(array_merge((new CompanyService($this->db,$this->p))->tablesForRun(),CompanyManualSettlementSchema::tables())));}
    private function ready():void
    {
        CompanyManualSettlementSchema::ensure($this->db,$this->p,false);
        if(version_compare($this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0','1.6.9','<'))throw new \DomainException('asaas_manual_module_required');
    }
    private function run(int $i,int $a,callable $fn)
    {
        $this->ready();return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$fn){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);(new CompanyService($this->db,$this->p))->authorize($i,$a,'settle');return $fn();});
    }
    public function policy(int $i,int $a,int $account):array
    {
        $this->ready();(new CompanyService($this->db,$this->p))->authorize($i,$a,'configuration');
        if(!$this->rows('SELECT id FROM '.$this->p.'asaas_company_accounts WHERE id=? AND issuer_id=?',[$account,$i]))throw new \DomainException('asaas_company_account_denied');
        return $this->rows('SELECT operation,notify_customer,revision FROM '.$this->p.'asaas_company_manual_policies WHERE account_row=? AND issuer_id=?',[$account,$i])[0]??['operation'=>'disabled','notify_customer'=>0,'revision'=>self::ZERO];
    }
    public function configure(int $i,int $a,int $account,string $accountRevision,string $revision,string $operation,bool $notify):void
    {
        if(!in_array($operation,['disabled','cash','remove'],true)||!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$accountRevision)||($operation!=='cash'&&$notify))throw new \DomainException('asaas_manual_policy_invalid');
        $this->ready();\IssuerTransaction::run($this->db,$this->p,$this->configurationTables(),function()use($i,$a,$account,$accountRevision,$revision,$operation,$notify){
            (new CompanyService($this->db,$this->p))->authorize($i,$a,'configure');
            $row=$this->rows('SELECT revision FROM '.$this->p.'asaas_company_accounts WHERE id=? AND issuer_id=? FOR UPDATE',[$account,$i])[0]??null;
            if(!$row||$row['revision']!==$accountRevision)throw new \DomainException('asaas_company_stale');
            $old=$this->rows('SELECT * FROM '.$this->p.'asaas_company_manual_policies WHERE account_row=? FOR UPDATE',[$account])[0]??null;
            if(($old['revision']??self::ZERO)!==$revision||($old&&(int)$old['issuer_id']!==$i))throw new \DomainException('asaas_manual_policy_stale');
            $next=bin2hex(random_bytes(32));
            if($old)\IssuerStorage::query($this->db,'UPDATE '.$this->p.'asaas_company_manual_policies SET operation=?,notify_customer=?,revision=? WHERE account_row=? AND issuer_id=?',[$operation,(int)$notify,$next,$account,$i]);
            else \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_manual_policies(account_row,issuer_id,operation,notify_customer,revision) VALUES(?,?,?,?,?)',[$account,$i,$operation,(int)$notify,$next]);
            $saved=$this->rows('SELECT * FROM '.$this->p.'asaas_company_manual_policies WHERE account_row=?',[$account])[0]??[];
            if(($saved['revision']??null)!==$next||($saved['operation']??null)!==$operation||(int)($saved['notify_customer']??-1)!==(int)$notify)throw new \RuntimeException('issuer_asaas_manual_policy_unconfirmed');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'company_asaas_manual_policy_saved',$i,$account,gmdate('Y-m-d H:i:s'),self::json(['revision'=>$next,'operation'=>$operation,'notify_customer'=>$notify])]);
            $audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$a,$i,$account,'company_asaas_manual_policy_saved',self::json(['revision'=>$next,'operation'=>$operation,'notify_customer'=>$notify])]))throw new \RuntimeException('issuer_asaas_manual_policy_unconfirmed');
        });
    }
    private function command(int $i,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_manual_command_invalid');
        $r=$this->rows('SELECT * FROM '.$this->p.'asaas_company_manual_settlements WHERE request_key=? AND issuer_id=? FOR UPDATE',[$key,$i])[0]??null;
        if(!$r)throw new \DomainException('asaas_manual_command_missing');
        if(!hash_equals($r['input_hash'],hash('sha256',$r['input_json'])))throw new \DomainException('asaas_manual_command_conflict');return $r;
    }
    private function current(int $i,int $a,array $r,bool $financial=true):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        if((int)$r['issuer_id']!==$i||(int)$r['actor_id']!==$a)throw new \DomainException('asaas_manual_command_denied');
        [$origin,$meta,$account,$parent]=(new CompanyService($this->db,$this->p))->manualSourceInOwnedTransaction($i,$a,(int)$r['origin_id']);
        $policy=$this->rows('SELECT * FROM '.$this->p.'asaas_company_manual_policies WHERE account_row=? AND issuer_id=? FOR UPDATE',[$r['account_row'],$i])[0]??null;
        if((int)$meta['account_row']!==(int)$r['account_row']||$account['revision']!==$r['account_revision']||!$policy||$policy['revision']!==$r['policy_revision']||$policy['operation']!==$r['operation']||(int)$policy['notify_customer']!==(int)$r['notify_customer'])throw new \DomainException('asaas_manual_command_stale');
        $input=json_decode($r['input_json'],true,32,JSON_THROW_ON_ERROR);
        if((int)$origin['invoice_id']!==$input['invoice']||$origin['remote_id']!==$input['remote'])throw new \DomainException('asaas_manual_command_conflict');
        if($financial&&$r['state']!=='applied'){
            $review=(new \app\services\InvoicePaymentWriter($this->db))->reviewCompanyManualWithModeInOwned($i,$a,$input['invoice'],$input['receipt'],$r['request_key'],$input['mode'],$input['mode_revision'],$r['invoice_revision']);
            if($review['hash']!==$input['native_hash']||(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($i,$a,$input['invoice'])!==$input['balance'])throw new \DomainException('asaas_manual_balance_changed');
        }
        return [$origin,$meta,$account,$parent,$input];
    }
    public function reserve(int $i,int $a,int $origin,int $invoice,array $receipt,string $key,string $mode,string $modeRevision,string $invoiceRevision,bool $notice):array
    {
        return $this->run($i,$a,function()use($i,$a,$origin,$invoice,$receipt,$key,$mode,$modeRevision,$invoiceRevision,$notice){
            $old=$this->rows('SELECT request_key FROM '.$this->p.'asaas_company_manual_settlements WHERE origin_id=? OR request_key=? FOR UPDATE',[$origin,$key]);
            if($old){if(count($old)!==1||$old[0]['request_key']!==$key)throw new \DomainException('asaas_manual_command_conflict');$r=$this->command($i,$key);[,,$account,,$saved]=$this->current($i,$a,$r);
                if([$saved['invoice'],$saved['receipt'],$saved['mode'],$saved['mode_revision'],$r['invoice_revision'],$saved['notice']]!==[$invoice,$receipt,$mode,$modeRevision,$invoiceRevision,$notice])throw new \DomainException('asaas_manual_command_conflict');return $r;}
            $native=(new \app\services\InvoicePaymentWriter($this->db))->reviewCompanyManualWithModeInOwned($i,$a,$invoice,$receipt,$key,$mode,$modeRevision,$invoiceRevision);
            $review=(new \CompanyAsaasPartialManualSource($this->db,$this->p,$origin))->reviewFull($this->db,$this->p,$i,$a,$invoice,$key,$native['amount'],$native['hash']);
            [$o,$meta,$account,$parent]=(new CompanyService($this->db,$this->p))->manualSourceInOwnedTransaction($i,$a,$origin);
            $policy=$this->rows('SELECT * FROM '.$this->p.'asaas_company_manual_policies WHERE account_row=? AND issuer_id=? FOR UPDATE',[$account['id'],$i])[0]??null;
            if(!$policy||!in_array($policy['operation'],['cash','remove'],true))throw new \DomainException('asaas_manual_policy_required');
            if($receipt['date']<$parent['date']||$receipt['date']>(new \DateTimeImmutable('now',new \DateTimeZone('America/Sao_Paulo')))->format('Y-m-d'))throw new \DomainException('asaas_manual_date_invalid');
            $input=['invoice'=>$invoice,'remote'=>$o['remote_id'],'receipt'=>$receipt,'mode'=>$mode,'mode_revision'=>$modeRevision,'notice'=>$notice,'native_hash'=>$native['hash'],'balance'=>(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($i,$a,$invoice),'total'=>$parent['total']];$json=self::json($input);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'asaas_company_manual_settlements(origin_id,issuer_id,actor_id,account_row,account_revision,policy_revision,request_key,input_json,input_hash,invoice_revision,proof_id,operation,notify_customer,state,result_json,result_hash,payment_id,sent_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,NULL,NULL,NULL,NULL,?,?)',[$origin,$i,$a,$account['id'],$account['revision'],$policy['revision'],$key,$json,hash('sha256',$json),$invoiceRevision,$review['proof'],$policy['operation'],$policy['notify_customer'],'reserved',$now,$now]);
            return $this->command($i,$key);
        });
    }
    private function client(array $account,callable $decrypt,?callable $transport):CompanyClient
    {
        try{$secret=$decrypt($account['secret_cipher']);}catch(\Throwable $e){throw new \DomainException('asaas_company_credentials_required');}
        if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');return new CompanyClient($account['environment'],$secret,$transport);
    }
    private static function confirmResponse(array $response,array $origin,array $meta,array $parent,array $r,array $input):array
    {
        $value=\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($response['value']??''));
        if(($response['id']??null)!==$origin['remote_id']||($response['customer']??null)!==$meta['customer_id']||($response['externalReference']??null)!==$parent['hash']||($response['dueDate']??null)!==$parent['duedate']||$value!==$input['total'])throw new \DomainException('asaas_manual_canonical_conflict');
        foreach(['refunds','chargeback','split','installment','subscription','anticipated','dunning'] as $field)if(!empty($response[$field]))throw new \DomainException('asaas_manual_canonical_conflict');
        $deleted=$response['deleted']??false;if(!is_bool($deleted))throw new \DomainException('asaas_manual_canonical_conflict');
        $status=$response['status']??null;$billing=$response['billingType']??null;$date=$response['paymentDate']??'';$credit=$response['creditDate']??'';
        if($r['operation']==='cash'){
            if($deleted||$status!=='RECEIVED'||$billing!=='RECEIVED_IN_CASH'||$date!==$input['receipt']['date']||$credit!=='')throw new \DomainException('asaas_manual_confirmation_required');
        }elseif(!$deleted||!in_array($status,['PENDING','OVERDUE'],true)||$billing!==$meta['billing_type']||$date!==''||$credit!=='')throw new \DomainException('asaas_manual_confirmation_required');
        return ['id'=>$origin['remote_id'],'status'=>$status,'billing_type'=>$billing,'value'=>$value,'payment_date'=>$date,'deleted'=>$deleted];
    }
    /** At most one mutation. Every subsequent attempt is a canonical GET, never a POST/DELETE retry. */
    public function execute(int $i,int $a,string $key,callable $decrypt,?callable $transport=null):array
    {
        $context=$this->run($i,$a,function()use($i,$a,$key){$r=$this->command($i,$key);return [$r,$this->current($i,$a,$r)];});
        [$r,[$origin,$meta,$account,$parent,$input]]=$context;if($r['state']==='applied')return $r;
        $client=$this->client($account,$decrypt,$transport);
        $wallet=$client->request('GET','wallets/');
        if(!is_array($wallet['data']??null)||count($wallet['data'])!==1||($wallet['hasMore']??false)!==false||($wallet['data'][0]['id']??null)!==$account['account_id'])throw new \DomainException('asaas_company_account_unverified');
        $this->run($i,$a,function()use($i,$a,$key){$this->current($i,$a,$this->command($i,$key));});
        $customer=$client->request('GET','customers/'.$meta['customer_id']);$recipient=json_decode($parent['recipient_identity_json'],true,8,JSON_THROW_ON_ERROR);
        if(($customer['id']??null)!==$meta['customer_id']||($customer['deleted']??false)!==false||\IssuerPolicy::taxId($customer['cpfCnpj']??null)!==\IssuerPolicy::taxId($recipient['tax_id']??null))throw new \DomainException('asaas_company_customer_unverified');
        // Revalidate the live unpaid charge before committing the single dispatch.
        // In particular, receiveInCash on an active dunning can incur a fee.
        if($r['state']==='reserved'){
            $before=$client->request('GET','payments/'.$origin['remote_id']);
            foreach(['refunds','chargeback','split','installment','subscription','anticipated','dunning'] as $field)if(!empty($before[$field]))throw new \DomainException('asaas_manual_canonical_conflict');
            if(($before['id']??null)!==$origin['remote_id']||($before['customer']??null)!==$meta['customer_id']||($before['externalReference']??null)!==$parent['hash']||($before['dueDate']??null)!==$parent['duedate']||\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($before['value']??''))!==$input['total']||!in_array($before['status']??null,['PENDING','OVERDUE'],true)||($before['billingType']??null)!==$meta['billing_type']||($before['deleted']??false)!==false||!empty($before['paymentDate'])||!empty($before['creditDate']))throw new \DomainException('asaas_manual_canonical_conflict');
        }
        $send=$this->run($i,$a,function()use($i,$a,$key){
            $r=$this->command($i,$key);[$o,$m,$account,$parent,$input]=$this->current($i,$a,$r);
            if($r['state']!=='reserved')return false;
            (new \CompanyAsaasPartialManualSource($this->db,$this->p,(int)$r['origin_id']))->reviewFull($this->db,$this->p,$i,$a,$input['invoice'],$key,\app\services\InvoicePaymentWriter::minorUnits($input['receipt']['amount']),$input['native_hash']);
            $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_manual_settlements SET state='sending',sent_at=?,updated_at=? WHERE origin_id=? AND state='reserved'",[$now,$now,$r['origin_id']]);
            $saved=$this->command($i,$key);if($saved['state']!=='sending'||$saved['sent_at']!==$now)throw new \RuntimeException('issuer_asaas_manual_claim_unconfirmed');return true;
        });
        if($send){
            try{if($r['operation']==='cash')$client->request('POST','payments/'.$origin['remote_id'].'/receiveInCash',['paymentDate'=>$input['receipt']['date'],'value'=>$input['total'],'notifyCustomer'=>(bool)$r['notify_customer']]);
                else $client->request('DELETE','payments/'.$origin['remote_id']);
            }catch(\Throwable $error){
                $this->run($i,$a,function()use($i,$key){\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_manual_settlements SET state='uncertain',updated_at=? WHERE request_key=? AND issuer_id=? AND state='sending'",[gmdate('Y-m-d H:i:s'),$key,$i]);});
                throw new \RuntimeException('asaas_manual_remote_unconfirmed');
            }
        }
        $this->run($i,$a,function()use($i,$a,$key){$this->current($i,$a,$this->command($i,$key));});
        $response=$client->request('GET','payments/'.$origin['remote_id']);$safe=self::confirmResponse($response,$origin,$meta,$parent,$r,$input);
        return $this->run($i,$a,function()use($i,$a,$key,$safe){
            $r=$this->command($i,$key);$this->current($i,$a,$r);if($r['state']==='applied')return $r;
            if($r['sent_at']===null)throw new \DomainException('asaas_manual_confirmation_required');
            $json=self::json($safe);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_manual_settlements SET state='confirmed',result_json=?,result_hash=?,updated_at=? WHERE origin_id=?",[$json,hash('sha256',$json),$now,$r['origin_id']]);
            return $this->command($i,$key);
        });
    }
    public function claimInOwned(int $i,int $a,int $invoice,string $key,int $amount,string $hash):array
    {
        $this->ready();$r=$this->command($i,$key);[$origin,$meta,$account,$parent,$input]=$this->current($i,$a,$r);
        if($input['invoice']!==$invoice||$input['native_hash']!==$hash||\app\services\InvoicePaymentWriter::minorUnits($input['receipt']['amount'])!==$amount)throw new \DomainException('asaas_manual_command_conflict');
        if($r['state']==='applied'){if($r['payment_id']===null)throw new \DomainException('asaas_manual_command_conflict');return $r;}
        if($r['state']!=='confirmed'||$r['result_json']===null||!hash_equals($r['result_hash']??'',hash('sha256',$r['result_json']))||$r['updated_at']<gmdate('Y-m-d H:i:s',time()-300)||$r['updated_at']>gmdate('Y-m-d H:i:s'))throw new \DomainException('asaas_manual_confirmation_required');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$origin['remote_id']]) as $event){
            $allowed=$r['operation']==='cash'?['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_RECEIVED']:['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE','PAYMENT_DELETED'];
            if(!in_array($event['event_type'],$allowed,true)||$event['state']==='conflict'||$event['created_at']>$r['updated_at'])throw new \DomainException('asaas_manual_event_review');
        }
        return $r;
    }
    public function appliedInOwned(int $i,int $a,string $key,int $payment):void
    {
        $r=$this->command($i,$key);$this->current($i,$a,$r,false);
        if($r['state']==='applied'){if((int)$r['payment_id']!==$payment)throw new \DomainException('asaas_manual_command_conflict');return;}
        if($r['state']!=='confirmed')throw new \DomainException('asaas_manual_confirmation_required');
        \IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_manual_settlements SET state='applied',payment_id=?,updated_at=? WHERE origin_id=? AND state='confirmed'",[$payment,gmdate('Y-m-d H:i:s'),$r['origin_id']]);
        $saved=$this->command($i,$key);if($saved['state']!=='applied'||(int)$saved['payment_id']!==$payment)throw new \RuntimeException('issuer_asaas_manual_receipt_unconfirmed');
    }
    public function pending(int $i,int $a):array
    {
        $this->ready();return \IssuerTransaction::run($this->db,$this->p,$this->configurationTables(),function()use($i,$a){(new CompanyService($this->db,$this->p))->authorize($i,$a,'settle');return $this->rows("SELECT m.request_key,m.origin_id,o.invoice_id,m.state,m.operation FROM {$this->p}asaas_company_manual_settlements m JOIN {$this->p}asaas_payment_origins o ON o.id=m.origin_id AND o.issuer_id=m.issuer_id WHERE m.issuer_id=? AND m.actor_id=? AND m.state<>'applied' ORDER BY m.created_at LIMIT 100",[$i,$a]);});
    }
    public function recover(int $i,int $a,string $key,callable $decrypt,?callable $transport=null):int
    {
        $r=$this->execute($i,$a,$key,$decrypt,$transport);$v=json_decode($r['input_json'],true,32,JSON_THROW_ON_ERROR);
        require_once APPPATH.'services/CompanyAsaasFullManualSource.php';
        return (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyManualWithMode($i,$a,$v['invoice'],$v['receipt'],$key,$v['mode'],$v['mode_revision'],$v['notice'],$r['invoice_revision'],new \CompanyAsaasFullManualSource($this->db,$this->p,$key));
    }
}
