<?php
declare(strict_types=1);
namespace Efi;
require_once __DIR__.'/CompanyAccounts.php';
require_once __DIR__.'/CompanySchema.php';
require_once __DIR__.'/CompanyProof.php';
require_once APPPATH.'services/CompanyInvoiceBalance.php';
require_once APPPATH.'services/CompanyCurrencyUnits.php';
require_once APPPATH.'services/InvoicePaymentWriter.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
/** Named-company origin state machine. The legacy NULL-owner gateway is never entered. */
final class CompanyCheckout
{
    private $factory;
    public function __construct(private $db,private string $p,private CompanyAccounts $accounts,?callable $transportFactory=null)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new \InvalidArgumentException('efi_company_prefix_invalid');
        $this->factory=$transportFactory;
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array
    {
        $tables=array_merge(CompanyAccounts::tables(),['invoices','issuers_invoice_documents','invoicepaymentrecords','credits'],(new \CompanyCurrencyUnits($this->db,$this->p))->tables(),(new \CompanyInvoiceBalance($this->db,$this->p))->tables());
        foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists($this->p.$table))$tables[]=$table;
        return array_values(array_unique($tables));
    }
    private function run(callable $operation)
    {
        CompanySchema::ensure($this->db,$this->p,false);
        return \IssuerTransaction::run($this->db,$this->p,$this->tables(),$operation);
    }
    private function audit(int $issuer,int $actor,string $event,int $origin):void
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,$event,$issuer,$origin,gmdate('Y-m-d H:i:s')]);
        $id=(int)$this->db->insert_id();if($id<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=?',[$id,$issuer,$origin]))throw new \RuntimeException('issuer_efi_audit_unconfirmed');
    }
    private function parent(int $issuer,int $invoice):array
    {
        $row=$this->rows('SELECT i.id,i.issuer_id,i.clientid,i.hash,i.date,i.duedate,i.total,i.currency,i.status,d.legal_identity_json,d.recipient_identity_json,d.formatted_number FROM '.$this->p.'invoices i JOIN '.$this->p.'issuers_invoice_documents d ON d.invoice_id=i.id AND d.issuer_id=i.issuer_id WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
        if(!$row)throw new \DomainException('efi_company_invoice_denied');
        $unit=(new \CompanyCurrencyUnits($this->db,$this->p))->resolveRead($row['currency']);
        if($unit['iso_code']!=='BRL')throw new \DomainException('efi_company_currency_denied');
        $row['total']=\EfiChargeLedger::decimal(\app\services\InvoicePaymentWriter::minorUnits($row['total']));
        return $row;
    }
    private static function snapshot(array $invoice):array{unset($invoice['status']);foreach($invoice as &$value)$value=(string)$value;unset($value);return $invoice;}
    private function noOtherOrigin(int $invoice):void
    {
        foreach(['bb_origins','asaas_payment_origins'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT id FROM '.$this->p.$table.' WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('efi_company_multiple_providers');
    }
    /** Must be called under the declared issuer transaction; rechecks revocation on every phase. */
    public function sourceInOwned(int $issuer,int $actor,int $origin,string $action):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->accounts->authorize($issuer,$actor,$action);
        $row=$this->rows('SELECT * FROM '.$this->p.'efi_company_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;
        if(!$row)throw new \DomainException('efi_company_origin_denied');
        if($row['provider_id']!==null)CompanyProof::provider($row['kind'],$row['provider_id']);
        $account=$this->accounts->accountInOwned($issuer,$actor,(int)$row['account_id'],$row['account_revision']);
        if($account['environment']!==$row['environment']||$account['account_hash']!==$row['account_hash'])throw new \DomainException('efi_company_account_conflict');
        $parent=$this->parent($issuer,(int)$row['invoice_id']);$snapshot=json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        if(!is_array($snapshot)||!hash_equals($row['snapshot_hash'],CompanyProof::hash($snapshot))||$snapshot!==self::snapshot($parent)||(int)$row['amount_minor']!==\EfiChargeLedger::minor($parent['total'])||$row['currency']!=='BRL')throw new \DomainException('efi_company_snapshot_conflict');
        $this->noOtherOrigin((int)$row['invoice_id']);return [$row,$account,$parent];
    }
    private static function string($value,int $max):string
    {
        if(!is_string($value)||$value===''||strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('efi_company_invalid_input');return $value;
    }
    private static function payload(string $kind,array $invoice,array $input):array
    {
        if(!in_array($kind,['pix','boleto','card'],true)||array_diff(array_keys($input),['email','phone','birth','payment_token','billing_address']))throw new \DomainException('efi_company_invalid_input');
        $amount=\EfiChargeLedger::minor($invoice['total']);if($amount<1)throw new \DomainException('efi_company_invalid_input');
        $name=self::string('Fatura '.$invoice['formatted_number'],255);
        if($kind==='pix'){if($input)throw new \DomainException('efi_company_invalid_input');return ['calendario'=>['expiracao'=>3600],'valor'=>['original'=>\EfiChargeLedger::decimal($amount)],'solicitacaoPagador'=>$name];}
        $recipient=json_decode($invoice['recipient_identity_json'],true,8,JSON_THROW_ON_ERROR);$tax=\IssuerPolicy::taxId($recipient['tax_id']??null);
        $customerName=self::string($recipient['name']??$recipient['legal_name']??$recipient['company']??null,255);
        $phone=$input['phone']??null;if(!is_string($phone)||!preg_match('/^[0-9]{10,11}$/D',$phone))throw new \DomainException('efi_company_invalid_input');
        $customer=['phone_number'=>$phone]+(strlen($tax)===11?['name'=>$customerName,'cpf'=>$tax]:['juridical_person'=>['corporate_name'=>$customerName,'cnpj'=>$tax]]);
        if(isset($input['email'])){if(!filter_var($input['email'],FILTER_VALIDATE_EMAIL)||strlen($input['email'])>254)throw new \DomainException('efi_company_invalid_input');$customer['email']=$input['email'];}
        $items=[['name'=>$name,'amount'=>1,'value'=>$amount]];
        if($kind==='boleto'){
            if(array_diff(array_keys($input),['email','phone'])||$invoice['duedate']<gmdate('Y-m-d'))throw new \DomainException('efi_company_invalid_input');
            return ['items'=>$items,'payment'=>['banking_billet'=>['expire_at'=>$invoice['duedate'],'customer'=>$customer]]];
        }
        if(strlen($tax)!==11||!isset($customer['email']))throw new \DomainException('efi_company_invalid_input');
        $birth=$input['birth']??null;$date=is_string($birth)?\DateTimeImmutable::createFromFormat('!Y-m-d',$birth):false;
        if(!$date||$date->format('Y-m-d')!==$birth||$birth>=gmdate('Y-m-d'))throw new \DomainException('efi_company_invalid_input');$customer['birth']=$birth;
        $address=$input['billing_address']??null;
        if(!is_array($address)||array_keys($address)!==['street','number','neighborhood','zipcode','city','state'])throw new \DomainException('efi_company_invalid_input');
        foreach($address as $value)self::string($value,255);
        if(!preg_match('/^[0-9]{8}$/D',$address['zipcode'])||!preg_match('/^[A-Z]{2}$/D',$address['state']))throw new \DomainException('efi_company_invalid_input');
        return ['items'=>$items,'payment'=>['credit_card'=>['customer'=>$customer,'installments'=>1,'billing_address'=>$address,'payment_token'=>self::string($input['payment_token']??null,2048)]]];
    }
    private function client(array $account)
    {
        if($this->factory===null)throw new \DomainException('efi_company_transport_unavailable');
        return ($this->factory)($account);
    }
    public function issue(int $issuer,int $actor,int $invoice,int $account,string $revision,string $kind,array $input=[]):int
    {
        foreach([$issuer,$actor,$invoice,$account] as $id)\IssuerPolicy::id($id);
        // Validation and reservation share the canonical lock; only a confirmed commit grants dispatch.
        [$origin,$send,$config,$payload]=$this->run(function()use($issuer,$actor,$invoice,$account,$revision,$kind,$input):array{
            $this->accounts->authorize($issuer,$actor,'issue');$config=$this->accounts->accountInOwned($issuer,$actor,$account,$revision);$parent=$this->parent($issuer,$invoice);
            $old=$this->rows('SELECT id,account_id,kind,state FROM '.$this->p.'efi_company_origins WHERE invoice_id=? FOR UPDATE',[$invoice])[0]??null;
            if($old){[$row]=$this->sourceInOwned($issuer,$actor,(int)$old['id'],'issue');if((int)$old['account_id']!==$account||$old['kind']!==$kind||$old['state']!=='bound')throw new \DomainException('efi_company_existing_origin_requires_review');return [(int)$old['id'],false,$config,[]];}
            $this->noOtherOrigin($invoice);(new \CompanyCurrencyUnits($this->db,$this->p))->assertWrite($parent['currency']);
            $balance=(new \CompanyInvoiceBalance($this->db,$this->p))->inOwned($issuer,$actor,$invoice);
            if(!in_array((int)$parent['status'],[1,4],true)||\EfiChargeLedger::minor($balance['receivable'])!==\EfiChargeLedger::minor($parent['total'])||$this->rows('SELECT id FROM '.$this->p.'invoicepaymentrecords WHERE invoiceid=? FOR UPDATE',[$invoice])||$this->rows('SELECT id FROM '.$this->p.'credits WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('efi_company_balance_requires_review');
            $payload=self::payload($kind,$parent,$input);$snapshot=self::snapshot($parent);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'efi_company_origins(issuer_id,invoice_id,account_id,account_revision,environment,account_hash,kind,amount_minor,currency,snapshot_json,snapshot_hash,state,provider_id,created_by,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,NULL,?,?,?)',[$issuer,$invoice,$account,$config['revision'],$config['environment'],$config['account_hash'],$kind,\EfiChargeLedger::minor($parent['total']),'BRL',json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),CompanyProof::hash($snapshot),'reserved',$actor,$now,$now]);
            $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('issuer_efi_origin_unconfirmed');$this->audit($issuer,$actor,'company_efi_reserved',$id);return [$id,true,$config,$payload];
        });
        if(!$send)return $origin;
        // SDK construction/certificate validation happens before durable dispatch, no provider operation yet.
        $client=$this->client($config);
        $attempt=$this->transition($issuer,$actor,$origin,'reserved','dispatched');
        try{
            if($kind==='boleto'){
                $response=$client->request('createCharge',[],['items'=>$payload['items']]);
                $this->retain($attempt,$actor,$response,'issue');$provider=CompanyProof::provider($kind,$response['data']['charge_id']??null);
                $attempt=$this->transition($issuer,$actor,$origin,'provider_created','payment_dispatched');
                $response=$client->request('definePayMethod',['id'=>$provider],['payment'=>$payload['payment']]);
                $this->retain($attempt,$actor,$response,'issue');
                $this->transition($issuer,$actor,$origin,'provider_created','bound');
            }else{
                $response=$client->request($kind==='pix'?'pixCreateImmediateCharge':'createOneStepCharge',[],$payload);
                $this->retain($attempt,$actor,$response,'issue');
                $this->transition($issuer,$actor,$origin,'provider_created','bound');
            }
        }catch(\Throwable $error){$this->uncertain($issuer,$actor,$origin);throw new \RuntimeException('efi_company_creation_unconfirmed');}
        return $origin;
    }
    private static function issuedAmount(string $kind,array $response,int $expected):void
    {
        $amount=$kind==='pix'?\EfiChargeLedger::minor($response['valor']['original']??null):($response['data']['total']??null);
        if(($kind!=='pix'&&($response['code']??null)!==200)||(!is_int($amount)&&!is_string($amount))||!preg_match('/^[1-9][0-9]{0,14}$/D',(string)$amount)||(int)$amount!==$expected)throw new \DomainException('efi_company_amount_conflict');
    }
    private function transition(int $issuer,int $actor,int $origin,string $from,string $to,?string $provider=null):array
    {
        return $this->run(function()use($issuer,$actor,$origin,$from,$to,$provider):array{
            [$row]=$this->sourceInOwned($issuer,$actor,$origin,'issue');if($row['state']!==$from)throw new \DomainException('efi_company_dispatch_denied');
            $id=$provider??$row['provider_id'];if($provider!==null)CompanyProof::provider($row['kind'],$provider);
            \IssuerStorage::query($this->db,'UPDATE '.$this->p.'efi_company_origins SET state=?,provider_id=?,updated_at=? WHERE id=? AND issuer_id=? AND state=?',[$to,$id,gmdate('Y-m-d H:i:s'),$origin,$issuer,$from]);
            $saved=$this->rows('SELECT state,provider_id FROM '.$this->p.'efi_company_origins WHERE id=? AND issuer_id=?',[$origin,$issuer])[0]??[];
            if(($saved['state']??null)!==$to||($saved['provider_id']??null)!==$id)throw new \RuntimeException('issuer_efi_transition_unconfirmed');
            $this->audit($issuer,$actor,'company_efi_'.$to,$origin);return array_replace($row,['state'=>$to,'provider_id'=>$id]);
        });
    }
    private function uncertain(int $issuer,int $actor,int $origin):void
    {
        try{$this->run(function()use($issuer,$actor,$origin):void{
            $this->accounts->authorize($issuer,$actor,'issue');
            \IssuerStorage::query($this->db,"UPDATE {$this->p}efi_company_origins SET state='unknown',updated_at=? WHERE id=? AND issuer_id=? AND state IN ('dispatched','provider_created','payment_dispatched')",[gmdate('Y-m-d H:i:s'),$origin,$issuer]);
            $this->audit($issuer,$actor,'company_efi_creation_unknown',$origin);
        });}catch(\Throwable $ignored){/* Existing durable intermediate fence still forbids another POST. */}
    }
    /** Retain an already-obtained technical result even when the actor/credentials were
     * revoked during network I/O. Private frozen-attempt input is never accepted from HTTP.
     * This boundary grants no financial authority and cannot trigger another provider call.
     */
    private function retain(array $attempt,int $actor,array $response,string $operation):int
    {
        $provider=null;$valid=true;
        try{
            $provider=CompanyProof::provider($attempt['kind'],$attempt['kind']==='pix'?($response['txid']??null):($response['data']['charge_id']??null));
            if($attempt['provider_id']!==null&&$provider!==$attempt['provider_id'])throw new \DomainException('efi_company_provider_conflict');
            if($operation==='query')$safe=CompanyProof::query($attempt,$response);
            else{self::issuedAmount($attempt['kind'],$response,(int)$attempt['amount_minor']);$safe=['provider_id'=>$provider,'amount_minor'=>(int)$attempt['amount_minor'],'kind'=>$attempt['kind'],'technical_issue'=>true];}
        }catch(\Throwable $error){$valid=false;$safe=['provider_id'=>$provider,'kind'=>$attempt['kind'],'technical_result'=>'unconfirmed'];}
        $frozen=['id','issuer_id','invoice_id','account_id','account_revision','environment','account_hash','kind','amount_minor','currency','snapshot_hash','state','provider_id'];
        $permit=[];foreach($frozen as $field)$permit[$field]=$attempt[$field]===null?null:(string)$attempt[$field];
        $safe['attempt_hash']=CompanyProof::hash($permit);
        [$proof,$authorized]=$this->run(function()use($attempt,$actor,$operation,$provider,$valid,$safe,$frozen,$permit):array{
            $issuer=(int)$attempt['issuer_id'];$origin=(int)$attempt['id'];
            $current=$this->rows('SELECT * FROM '.$this->p.'efi_company_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$origin,$issuer])[0]??null;
            if(!$current)throw new \DomainException('efi_company_attempt_conflict');
            foreach($frozen as $field)if(($current[$field]===null?null:(string)$current[$field])!==$permit[$field])throw new \DomainException('efi_company_attempt_conflict');
            $authorized=true;
            try{$this->sourceInOwned($issuer,$actor,$origin,$operation==='issue'?'issue':'observe');}catch(\DomainException $revoked){$authorized=false;}
            $ok=$authorized&&$valid;
            $state=$ok?($operation==='query'?(($safe['receipt_eligible']??false)?'received_observed':'review_required'):'technical_observed'):'quarantine';
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'efi_company_proofs(origin_id,account_revision,operation,result_json,evidence_hash,state,created_by,created_at) VALUES(?,?,?,?,?,?,?,?)',[$origin,$attempt['account_revision'],$operation,json_encode($safe,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),CompanyProof::hash($safe),$state,$actor,gmdate('Y-m-d H:i:s')]);
            $proof=(int)$this->db->insert_id();if($proof<1||!$this->rows('SELECT id FROM '.$this->p.'efi_company_proofs WHERE id=? AND origin_id=? AND evidence_hash=?',[$proof,$origin,CompanyProof::hash($safe)]))throw new \RuntimeException('issuer_efi_proof_unconfirmed');
            if($operation==='issue'||!$ok){
                // Never replace a previously recorded provider identity with a conflicting one.
                $stored=$current['provider_id']??$provider;$next=$ok?'provider_created':'quarantined';
                \IssuerStorage::query($this->db,'UPDATE '.$this->p.'efi_company_origins SET provider_id=?,state=?,updated_at=? WHERE id=? AND issuer_id=?',[$stored,$next,gmdate('Y-m-d H:i:s'),$origin,$issuer]);
                $saved=$this->rows('SELECT provider_id,state FROM '.$this->p.'efi_company_origins WHERE id=? AND issuer_id=?',[$origin,$issuer])[0]??[];
                if(($saved['provider_id']??null)!==$stored||($saved['state']??null)!==$next)throw new \RuntimeException('issuer_efi_transition_unconfirmed');
            }
            $this->audit($issuer,$actor,$ok?'company_efi_result_retained':'company_efi_result_quarantined',$origin);
            return [$proof,$ok];
        });
        if(!$authorized)throw new \DomainException('efi_company_result_quarantined');
        return $proof;
    }
    public function query(int $issuer,int $actor,int $origin):int
    {
        [$row,$account]=$this->run(fn()=>$this->sourceInOwned($issuer,$actor,$origin,'observe'));
        if($row['state']!=='bound'||$row['provider_id']===null)throw new \DomainException('efi_company_origin_requires_review');
        $client=$this->client($account);$response=$client->request($row['kind']==='pix'?'pixDetailCharge':'detailCharge',$row['kind']==='pix'?['txid'=>$row['provider_id']]:['id'=>$row['provider_id']]);
        return $this->retain($row,$actor,$response,'query');
    }
    /** Read-only instrument retrieval; no creation, no provider-controlled HTML or arbitrary URL. */
    public function instruments(int $issuer,int $actor,int $origin):array
    {
        [$row,$account]=$this->run(fn()=>$this->sourceInOwned($issuer,$actor,$origin,'observe'));
        if($row['state']!=='bound'||$row['provider_id']===null||$row['kind']==='card')throw new \DomainException('efi_company_instrument_unavailable');
        $client=$this->client($account);
        $response=$client->request($row['kind']==='pix'?'pixDetailCharge':'detailCharge',$row['kind']==='pix'?['txid'=>$row['provider_id']]:['id'=>$row['provider_id']]);
        CompanyProof::query($row,$response); // Exact account-bound provider ID and original amount.
        if($row['kind']==='pix'){
            $location=$response['loc']['id']??null;
            if((!is_int($location)&&!is_string($location))||!preg_match('/^[1-9][0-9]{0,18}$/D',(string)$location))throw new \DomainException('efi_company_instrument_unavailable');
            $qr=$client->request('pixGenerateQRCode',['id'=>$location]);$code=$qr['qrcode']??null;
            if(!is_string($code)||strlen($code)>4096||!str_starts_with($code,'000201')||preg_match('/[^\x20-\x7e]/',$code))throw new \DomainException('efi_company_instrument_unavailable');
            $instrument=['pix_copy_paste'=>$code];
        }else{
            $payment=$response['data']['payment']['banking_billet']??[];$url=$payment['link']??null;$barcode=$payment['barcode']??null;
            $parts=is_string($url)?parse_url($url):false;
            if(!is_array($parts)||($parts['scheme']??'')!=='https'||($parts['host']??'')!=='visualizacao.gerencianet.com.br'||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])||isset($parts['fragment'])||!str_starts_with($parts['path']??'','/emissao/')||strlen($url)>2048||preg_match('/[\x00-\x20\x7f]/',$url))throw new \DomainException('efi_company_instrument_unavailable');
            if(!is_string($barcode)||!preg_match('/^[0-9 .-]{44,80}$/D',$barcode))throw new \DomainException('efi_company_instrument_unavailable');
            $instrument=['boleto_barcode'=>$barcode,'boleto_url'=>$url];
        }
        $this->run(function()use($issuer,$actor,$origin,$row):void{[$current]=$this->sourceInOwned($issuer,$actor,$origin,'observe');if($current['state']!=='bound'||$current['provider_id']!==$row['provider_id']||$current['account_revision']!==$row['account_revision'])throw new \DomainException('efi_company_query_stale');});
        return $instrument;
    }
    public function settle(int $issuer,int $actor,int $origin,int $proof):int
    {
        require_once __DIR__.'/CompanyReceivedReceipt.php';
        return (new \app\services\InvoicePaymentWriter($this->db))->recordCompanyProviderReceipt($issuer,$actor,new CompanyReceivedReceipt($origin,$proof));
    }
    public function overview(int $issuer,int $actor):array
    {
        $this->accounts->authorize($issuer,$actor,'observe');CompanySchema::ensure($this->db,$this->p,false);
        $origins=$this->rows('SELECT id,invoice_id,account_id,environment,kind,amount_minor,currency,state,provider_id,created_at FROM '.$this->p.'efi_company_origins WHERE issuer_id=? ORDER BY id DESC LIMIT 100',[$issuer]);
        foreach($origins as &$origin){$proof=$this->rows('SELECT id,state,result_json,created_at FROM '.$this->p.'efi_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1',[$origin['id']])[0]??null;if($proof){$proof['result']=json_decode($proof['result_json'],true,16,JSON_THROW_ON_ERROR);unset($proof['result_json']);}$origin['proof']=$proof;$origin['receipt']=$this->rows('SELECT payment_id,created_at FROM '.$this->p.'efi_company_receipts WHERE origin_id=? AND issuer_id=?',[$origin['id'],$issuer])[0]??null;}unset($origin);
        return ['origins'=>$origins];
    }
}
