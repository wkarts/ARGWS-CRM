<?php
declare(strict_types=1);
namespace Asaas;
require_once __DIR__.'/CompanyService.php';
require_once __DIR__.'/CompanyInvoiceEditJournal.php';
require_once APPPATH.'services/NativeCompanyInvoiceCommands.php';
/** Durable, single-dispatch native invoice update; original origin snapshots remain immutable. */
final class CompanyInvoiceEdits
{
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function json(array $v):string{return CompanyInvoiceEditJournal::json($v);}
    public function tables():array
    {
        $extra=array_merge((new CompanyService($this->db,$this->p))->tablesForRun(),CompanyInvoiceEditSchema::tables(),['company_invoice_native_commands']);
        foreach(['asaas_company_manual_settlements','asaas_company_refund_requests','asaas_company_card_payments','asaas_company_recreations'] as $table)if($this->db->table_exists($this->p.$table))$extra[]=$table;
        return (new \CompanyInvoices($this->db,$this->p))->transactionParticipants(array_values(array_unique($extra)));
    }
    private function ready():void
    {
        CompanyInvoiceEditSchema::ensure($this->db,$this->p,false);
        if(version_compare($this->rows('SELECT installed_version FROM '.$this->p.'modules WHERE module_name=?',['asaas'])[0]['installed_version']??'0','1.7.0','<'))throw new \DomainException('asaas_invoice_edit_module_required');
    }
    private function run(int $i,int $a,callable $fn)
    {
        $this->ready();return \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$fn){(new \IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);(new CompanyService($this->db,$this->p))->authorize($i,$a,'issue');return $fn();});
    }
    private function command(int $i,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new \DomainException('asaas_invoice_edit_invalid');
        $row=$this->rows('SELECT * FROM '.$this->p.CompanyInvoiceEditJournal::TABLE.' WHERE request_key=? AND issuer_id=? FOR UPDATE',[$key,$i])[0]??null;
        if(!$row||!hash_equals($row['input_hash'],hash('sha256',$row['input_json'])))throw new \DomainException('asaas_invoice_edit_command_conflict');return $row;
    }
    private function eligibility(int $i,int $a,int $origin,array $request,bool $fresh,?array $command=null):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        [$o,$m,$account,$parent]=(new CompanyService($this->db,$this->p))->editSourceInOwnedTransaction($i,$a,$origin,$command['request_key']??null);
        if((int)$o['invoice_id']!==$request['invoice']||$o['state']!=='issued'||!in_array((int)$parent['status'],[1,4],true)||!in_array($m['billing_type'],['PIX','BOLETO'],true))throw new \DomainException('asaas_invoice_edit_unpaid_required');
        (new CompanyService($this->db,$this->p))->assertAutomaticAllowed($origin);
        if(!hash_equals((new \CompanyInvoices($this->db,$this->p))->revision($i,$a,$request['invoice']),$request['revision']))throw new \DomainException('company_invoice_stale');
        if(count($this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$request['invoice']]))>1)CompanyOriginGenerations::postRefundOriginInOwned($this->db,$this->p,$i,$request['invoice'],$origin);
        foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE '.$column.'=? FOR UPDATE',[$request['invoice']]))throw new \DomainException('asaas_invoice_edit_financial_history');
        foreach(['asaas_company_receipts','asaas_company_remote_operations','asaas_company_manual_settlements','asaas_company_refund_requests','asaas_company_card_payments'] as $table)if($this->db->table_exists($this->p.$table)&&$this->rows('SELECT origin_id FROM '.$this->p.$table.' WHERE origin_id=? FOR UPDATE',[$origin]))throw new \DomainException('asaas_invoice_edit_operation_pending');
        if($this->rows("SELECT event_id FROM {$this->p}asaas_company_jobs WHERE origin_id=? AND state IN ('pending','processing') FOR UPDATE",[$origin]))throw new \DomainException('asaas_invoice_edit_operation_pending');
        $proof=$command?$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE id=? AND origin_id=? FOR UPDATE',[$command['proof_id'],$origin])[0]??null:$this->rows('SELECT * FROM '.$this->p.'asaas_company_proofs WHERE origin_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$origin])[0]??null;
        if(!$proof||$proof['operation']!=='query'||$proof['state']!=='issued'||$proof['account_revision']!==$account['revision']||($fresh&&($proof['created_at']<gmdate('Y-m-d H:i:s',time()-300)||$proof['created_at']>gmdate('Y-m-d H:i:s'))))throw new \DomainException('asaas_invoice_edit_fresh_query_required');
        $safe=json_decode($proof['result_json'],true,32,JSON_THROW_ON_ERROR);
        if(!hash_equals($proof['evidence_hash'],hash('sha256',self::json($safe)))||($safe['id']??null)!==$o['remote_id']||!in_array($safe['status']??null,['PENDING','OVERDUE'],true)||($safe['deleted']??true)||($safe['adverse']??true)||($safe['payment_date']??'')!==''||($safe['credit_date']??'')!==''||($safe['value']??null)!==$parent['total']||($safe['due_date']??null)!==$parent['duedate'])throw new \DomainException('asaas_invoice_edit_unpaid_required');
        foreach($this->rows('SELECT event_type,state,created_at FROM '.$this->p.'asaas_company_events WHERE account_row=? AND remote_id=? FOR UPDATE',[$account['id'],$o['remote_id']]) as $event){
            if(!in_array($event['event_type'],['PAYMENT_CREATED','PAYMENT_UPDATED','PAYMENT_OVERDUE'],true)||in_array($event['state'],['conflict','review_required'],true)||($fresh&&$event['created_at']>$proof['created_at']))throw new \DomainException('asaas_invoice_edit_event_review');
        }
        return [$o,$m,$account,$parent,$proof];
    }
    public function reviewInOwned(int $i,int $a,int $origin,array $request,bool $preview):array
    {
        $this->ready();$command=$preview?null:$this->command($i,$request['key']);
        if($command&&((int)$command['actor_id']!==$a||(int)$command['origin_id']!==$origin||$command['state']!=='confirmed'||$command['result_json']===null||!hash_equals($command['result_hash']??'',hash('sha256',$command['result_json']))||$command['updated_at']<gmdate('Y-m-d H:i:s',time()-300)))throw new \DomainException('asaas_invoice_edit_confirmation_required');
        [$o,$m,$account]=$this->eligibility($i,$a,$origin,$request,$preview,$command);
        if($command){$input=json_decode($command['input_json'],true,64,JSON_THROW_ON_ERROR);if($input['request']!==$request||$command['account_revision']!==$account['revision']||$command['before_json']!==$m['snapshot_json'])throw new \DomainException('asaas_invoice_edit_command_conflict');}
        return json_decode($m['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
    }
    private function current(int $i,int $a,string $key):array
    {
        $r=$this->command($i,$key);$input=json_decode($r['input_json'],true,64,JSON_THROW_ON_ERROR);
        if((int)$r['actor_id']!==$a)throw new \DomainException('asaas_invoice_edit_denied');
        if($r['state']==='applied'){
            [$o,$m,$account,$parent]=(new CompanyService($this->db,$this->p))->editSourceInOwnedTransaction($i,$a,(int)$r['origin_id']);return [$r,$input,$o,$m,$account,$parent];
        }
        [$o,$m,$account,$parent]=$this->eligibility($i,$a,(int)$r['origin_id'],$input['request'],false,$r);
        if($r['account_revision']!==$account['revision']||$r['before_json']!==$m['snapshot_json'])throw new \DomainException('asaas_invoice_edit_stale');return [$r,$input,$o,$m,$account,$parent];
    }
    public function prepare(int $i,int $a,int $origin,array $request):array
    {
        $old=$this->run($i,$a,fn()=>$this->rows('SELECT request_key FROM '.$this->p.CompanyInvoiceEditJournal::TABLE.' WHERE request_key=?',[$request['key']]));
        if($old)return $this->run($i,$a,function()use($i,$a,$request){[$r,$input]=$this->current($i,$a,$request['key']);if($input['request']!==$request)throw new \DomainException('asaas_invoice_edit_command_conflict');return $r;});
        require_once APPPATH.'services/CompanyAsaasInvoiceEditSource.php';
        $source=new \CompanyAsaasInvoiceEditSource($this->db,$this->p,$i,$a,$origin,$request,true);
        try{(new \NativeCompanyInvoiceCommands($this->db,$this->p))->updateIssued($i,$a,$request['invoice'],$request['revision'],$request['payload'],$request['metadata'],$request['key'],$source);}
        catch(\CompanyAsaasInvoiceEditPreview $preview){$before=$preview->before;$after=$preview->after;}
        if(!isset($before,$after))throw new \RuntimeException('asaas_invoice_edit_preview_unconfirmed');
        return $this->run($i,$a,function()use($i,$a,$origin,$request,$before,$after){
            [$o,$m,$account,,$proof]=$this->eligibility($i,$a,$origin,$request,true);
            $beforeJson=self::json($before);$afterJson=self::json($after);if($beforeJson!==$m['snapshot_json'])throw new \DomainException('asaas_invoice_edit_stale');
            if($this->rows('SELECT request_key FROM '.$this->p.CompanyInvoiceEditJournal::TABLE.' WHERE origin_id=? AND active_slot=1 FOR UPDATE',[$origin]))throw new \DomainException('asaas_invoice_edit_pending');
            $sequence=(int)($this->rows('SELECT MAX(sequence_no) n FROM '.$this->p.CompanyInvoiceEditJournal::TABLE.' WHERE origin_id=?',[$origin])[0]['n']??0)+1;
            $input=self::json(['invoice'=>$request['invoice'],'remote'=>$o['remote_id'],'request'=>$request]);$now=gmdate('Y-m-d H:i:s');
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.CompanyInvoiceEditJournal::TABLE.'(request_key,origin_id,issuer_id,actor_id,account_row,account_revision,sequence_no,active_slot,input_json,input_hash,invoice_revision,before_json,before_hash,after_json,after_hash,proof_id,state,result_json,result_hash,audit_id,sent_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,?,?,?,?,?,?,?,?,?,NULL,NULL,NULL,NULL,?,?)',[$request['key'],$origin,$i,$a,$account['id'],$account['revision'],$sequence,$input,hash('sha256',$input),$request['revision'],$beforeJson,hash('sha256',$beforeJson),$afterJson,hash('sha256',$afterJson),$proof['id'],'reserved',$now,$now]);
            return $this->command($i,$request['key']);
        });
    }
    public function appliedInOwned(int $i,int $a,string $key,array $after):void
    {
        \IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$r=$this->command($i,$key);
        if((int)$r['actor_id']!==$a||$r['state']!=='confirmed'||self::json($after)!==$r['after_json'])throw new \DomainException('asaas_invoice_edit_command_conflict');
        $input=json_decode($r['input_json'],true,64,JSON_THROW_ON_ERROR);
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$a,'company_asaas_invoice_edited',$i,$input['invoice'],gmdate('Y-m-d H:i:s'),CompanyInvoiceEditJournal::audit($r)]);$audit=(int)$this->db->insert_id();
        \IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_invoice_edits SET state='applied',active_slot=NULL,audit_id=?,updated_at=? WHERE request_key=? AND state='confirmed'",[$audit,gmdate('Y-m-d H:i:s'),$key]);
        [$saved]=$this->current($i,$a,$key);if($saved['state']!=='applied'||(int)$saved['audit_id']!==$audit)throw new \RuntimeException('issuer_asaas_invoice_edit_unconfirmed');
    }
    private static function canonical(array $response,array $origin,array $meta,array $snapshot):array
    {
        $value=\CompanyInvoiceAmounts::decimal(\app\services\InvoicePaymentWriter::minorUnits($response['value']??''));
        if(($response['id']??null)!==$origin['remote_id']||($response['customer']??null)!==$meta['customer_id']||($response['externalReference']??null)!==$snapshot['hash']||($response['dueDate']??null)!==$snapshot['duedate']||$value!==$snapshot['total']||($response['billingType']??null)!==$meta['billing_type']||($response['deleted']??false)!==false||!in_array($response['status']??null,['PENDING','OVERDUE'],true)||!empty($response['paymentDate'])||!empty($response['creditDate']))throw new \DomainException('asaas_invoice_edit_canonical_conflict');
        foreach(['refunds','chargeback','split','installment','subscription','anticipated','dunning'] as $field)if(!empty($response[$field]))throw new \DomainException('asaas_invoice_edit_canonical_conflict');
        foreach(['discount','interest','fine'] as $field)if(isset($response[$field]['value'])&&\app\services\InvoicePaymentWriter::minorUnits($response[$field]['value'])!==0)throw new \DomainException('asaas_invoice_edit_canonical_conflict');
        if(isset($response['interestValue'])&&\app\services\InvoicePaymentWriter::minorUnits($response['interestValue'])!==0)throw new \DomainException('asaas_invoice_edit_canonical_conflict');
        if(($meta['profile']??null)!==null&&($response['description']??null)!==$meta['profile']['description'])throw new \DomainException('asaas_invoice_edit_canonical_conflict');
        return ['id'=>$origin['remote_id'],'customer'=>$meta['customer_id'],'external_reference'=>$snapshot['hash'],'value'=>$value,'due_date'=>$snapshot['duedate'],'billing_type'=>$meta['billing_type'],'status'=>$response['status'],'deleted'=>false];
    }
    public function execute(int $i,int $a,string $key,callable $decrypt,?callable $transport=null):array
    {
        [$r,$input,$origin,$meta,$account,$parent]=$this->run($i,$a,fn()=>$this->current($i,$a,$key));if($r['state']==='applied')return $r;
        try{$secret=$decrypt($account['secret_cipher']);}catch(\Throwable $error){throw new \DomainException('asaas_company_credentials_required');}
        if(!is_string($secret))throw new \DomainException('asaas_company_credentials_required');$client=new CompanyClient($account['environment'],$secret,$transport);unset($secret);
        $wallet=$client->request('GET','wallets/');if(!is_array($wallet['data']??null)||count($wallet['data'])!==1||($wallet['hasMore']??false)!==false||($wallet['data'][0]['id']??null)!==$account['account_id'])throw new \DomainException('asaas_company_account_unverified');
        $this->run($i,$a,fn()=>$this->current($i,$a,$key));
        $customer=$client->request('GET','customers/'.$meta['customer_id']);$recipient=json_decode($parent['recipient_identity_json'],true,8,JSON_THROW_ON_ERROR);
        if(($customer['id']??null)!==$meta['customer_id']||($customer['deleted']??false)!==false||\IssuerPolicy::taxId($customer['cpfCnpj']??null)!==\IssuerPolicy::taxId($recipient['tax_id']??null))throw new \DomainException('asaas_company_customer_unverified');
        if($r['state']==='reserved')self::canonical($client->request('GET','payments/'.$origin['remote_id']),$origin,$meta,json_decode($r['before_json'],true,32,JSON_THROW_ON_ERROR));
        $send=$this->run($i,$a,function()use($i,$a,$key){
            [$r]=$this->current($i,$a,$key);if($r['state']!=='reserved')return false;
            $now=gmdate('Y-m-d H:i:s');\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_invoice_edits SET state='sending',sent_at=?,updated_at=? WHERE request_key=? AND state='reserved'",[$now,$now,$key]);$saved=$this->command($i,$key);
            if($saved['state']!=='sending'||$saved['sent_at']!==$now)throw new \RuntimeException('issuer_asaas_invoice_edit_claim_unconfirmed');return true;
        });
        $after=json_decode($r['after_json'],true,32,JSON_THROW_ON_ERROR);
        if($send){
            try{$client->request('PUT','payments/'.$origin['remote_id'],['billingType'=>$meta['billing_type'],'value'=>$after['total'],'dueDate'=>$after['duedate']]);}
            catch(\Throwable $error){
                // The initiating identity can retain its uncertain dispatch, not authorize any new effect.
                \IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a,$key){$row=$this->command($i,$key);if((int)$row['actor_id']!==$a)throw new \DomainException('asaas_invoice_edit_denied');if($row['state']==='sending')\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_invoice_edits SET state='uncertain',updated_at=? WHERE request_key=?",[gmdate('Y-m-d H:i:s'),$key]);});
                throw new \RuntimeException('asaas_invoice_edit_remote_unconfirmed');
            }
        }
        $this->run($i,$a,fn()=>$this->current($i,$a,$key));
        $safe=self::canonical($client->request('GET','payments/'.$origin['remote_id']),$origin,$meta,$after);
        return $this->run($i,$a,function()use($i,$a,$key,$safe){
            [$r]=$this->current($i,$a,$key);if($r['state']==='applied')return $r;if($r['sent_at']===null)throw new \DomainException('asaas_invoice_edit_confirmation_required');
            $json=self::json($safe);\IssuerStorage::query($this->db,"UPDATE {$this->p}asaas_company_invoice_edits SET state='confirmed',result_json=?,result_hash=?,updated_at=? WHERE request_key=?",[$json,hash('sha256',$json),gmdate('Y-m-d H:i:s'),$key]);$saved=$this->command($i,$key);
            if($saved['state']!=='confirmed'||$saved['result_json']!==$json||$saved['result_hash']!==hash('sha256',$json))throw new \RuntimeException('issuer_asaas_invoice_edit_confirmation_unconfirmed');return $saved;
        });
    }
    public function update(int $i,int $a,int $invoice,string $revision,array $payload,array $metadata,string $key,callable $decrypt,?callable $transport=null):array
    {
        $key=\CompanyInvoiceDraftsInput::key($key);$payload=\CompanyInvoiceDraftsInput::normalize($payload);$request=compact('invoice','revision','payload','metadata','key');
        [$origin,$exists]=$this->run($i,$a,function()use($i,$invoice,$key){$origins=$this->rows('SELECT id FROM '.$this->p.'asaas_payment_origins WHERE invoice_id=? AND issuer_id=?'.CompanyOriginGenerations::predicate($this->db,$this->p).' FOR UPDATE',[$invoice,$i]);if(count($origins)!==1)throw new \DomainException('asaas_invoice_edit_origin_denied');return [(int)$origins[0]['id'],(bool)$this->rows('SELECT request_key FROM '.$this->p.CompanyInvoiceEditJournal::TABLE.' WHERE request_key=?',[$key])];});
        if(!$exists)(new CompanyService($this->db,$this->p))->observe($i,$a,$origin,$decrypt,$transport);
        $this->prepare($i,$a,$origin,$request);return $this->recover($i,$a,$key,$decrypt,$transport);
    }
    public function recover(int $i,int $a,string $key,callable $decrypt,?callable $transport=null):array
    {
        $row=$this->execute($i,$a,$key,$decrypt,$transport);$input=json_decode($row['input_json'],true,64,JSON_THROW_ON_ERROR);$r=$input['request'];
        require_once APPPATH.'services/CompanyAsaasInvoiceEditSource.php';
        $source=new \CompanyAsaasInvoiceEditSource($this->db,$this->p,$i,$a,(int)$row['origin_id'],$r,false);
        return (new \NativeCompanyInvoiceCommands($this->db,$this->p))->updateIssued($i,$a,$r['invoice'],$r['revision'],$r['payload'],$r['metadata'],$r['key'],$source);
    }
    public function pending(int $i,int $a):array
    {
        return $this->run($i,$a,fn()=>$this->rows("SELECT e.request_key,e.state,o.invoice_id FROM {$this->p}asaas_company_invoice_edits e JOIN {$this->p}asaas_payment_origins o ON o.id=e.origin_id AND o.issuer_id=e.issuer_id WHERE e.issuer_id=? AND e.actor_id=? AND e.active_slot=1 ORDER BY e.created_at LIMIT 100",[$i,$a]));
    }

}
