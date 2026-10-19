<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Origination.php';
require_once __DIR__.'/ApiSchema.php';
require_once __DIR__.'/ApiPayload.php';
require_once __DIR__.'/ApiReceiptSchema.php';

/** Invoice-bound API outbox. Every request uses the immutable origin; no ambient bank/account defaults. */
final class ApiService
{
    private ApiClient $client;
    public function __construct(private $db,private string $prefix,private $encrypt=null,private $decrypt=null,?callable $transport=null)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('bb_prefix_invalid');$this->client=new ApiClient($transport);
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function insert(string $table,array $fields):int
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.' (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('issuer_bb_api_write_unconfirmed');return $id;
    }
    private function transaction(callable $work)
    {
        ApiSchema::ensure($this->db,$this->prefix,false);OriginationSchema::assertValid($this->db,$this->prefix);
        ApiReceiptSchema::ensure($this->db,$this->prefix,false);$tables=self::receiptTables();
        if($this->db->table_exists($this->prefix.'asaas_payment_origins'))$tables[]='asaas_payment_origins';
        return \IssuerTransaction::run($this->db,$this->prefix,$tables,$work);
    }
    /** Internal table contract for the core-owned receipt transaction, never a session scope. */
    public static function receiptTables():array
    {
        return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_audit','staff','modules','invoices','issuers_invoice_documents','currencies','invoicepaymentrecords','credits','bb_connections','bb_layout_bindings','bb_origins','bb_audit','bb_number_reservations','bb_api_profiles','bb_api_credentials','bb_api_origins','bb_api_operations','bb_api_receipts'];
    }
    public function receiptOriginInOwnedTransaction(int $issuer,int $actor,int $origin):array
    {
        \IssuerTransaction::assertOwned($this->db,$this->prefix,self::receiptTables());
        $this->authorize($issuer,$actor,'reconcile',true);return $this->loadOrigin($issuer,$origin);
    }
    private function authorize(int $issuer,int $actor,string $cap,bool $invoice=false):void
    {
        $module=$this->rows('SELECT active,installed_version FROM '.$this->prefix.'modules WHERE module_name=? FOR UPDATE',['banco_brasil'])[0]??[];
        $acl=new \IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$actor);
        if((int)($module['active']??0)!==1||($module['installed_version']??null)!=='1.1.0'||!$acl->can($issuer,$actor,'banco_brasil','view')||!$acl->can($issuer,$actor,'banco_brasil',$cap)||($invoice&&!$acl->can($issuer,$actor,'invoices','view')))throw new \DomainException('bb_api_access_denied');
    }
    private function connection(int $issuer,int $id):array
    {
        $c=$this->rows('SELECT * FROM '.$this->prefix.'bb_connections WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$c)throw new \DomainException('bb_connection_unavailable');
        $identity=hash('sha256',json_encode([$issuer,$c['account_id'],$c['environment'],$c['agreement_number'],$c['wallet'],$c['variation']],JSON_THROW_ON_ERROR));
        if($c['provider']!=='banco_brasil'||!hash_equals($identity,$c['identity_hash'])||!hash_equals(hash('sha256',json_encode([$identity,$c['issuer_tax_id'],$c['issuer_name']],JSON_THROW_ON_ERROR)),$c['configuration_hash']))throw new \DomainException('bb_connection_unavailable');
        $a=new Agreement(new Scope($issuer,$id,$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$c['issuer_name'],$c['variation'],$c['wallet']);return [$c,$a];
    }
    private function profile(int $issuer,int $id):array
    {
        $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_api_profiles WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$row||!hash_equals($row['profile_hash'],hash('sha256',$row['definition_json'])))throw new \DomainException('bb_api_profile_unavailable');
        $def=json_decode($row['definition_json'],true,24,JSON_THROW_ON_ERROR);[$c,$a]=$this->connection($issuer,(int)$row['connection_id']);ApiClient::stage($row['api_stage']);
        if(($def['spec_sha256']??null)!==ApiClient::SPEC_SHA256||($def['api_stage']??null)!==$row['api_stage']||($def['configuration_hash']??null)!==$c['configuration_hash']||$row['configuration_hash']!==$c['configuration_hash']||(int)($def['issuer_id']??0)!==$issuer||(int)($def['connection_id']??0)!==(int)$c['id']||($row['api_stage']==='production')!==($c['environment']==='production'))throw new \DomainException('bb_api_profile_unavailable');
        return [$row,$def,$c,$a];
    }
    private static function number($input,int $max):int
    {
        if(!is_string($input)||!preg_match('/^[0-9]{1,10}$/D',$input)||(int)$input<1||(int)$input>$max)throw new \DomainException('bb_api_invalid_input');return (int)$input;
    }
    private static function text($value,int $limit):string
    {
        if(!is_string($value)||trim($value)===''||strlen($value)>$limit||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_api_invalid_input');return trim($value);
    }
    private static function bankNamespace(Agreement $a):string{return hash('sha256',json_encode(['banco_brasil',$a->scope->environment,$a->scope->agreement],JSON_THROW_ON_ERROR));}
    public function declareProfile($issuer,$actor,$connection,array $input):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$connection=\IssuerPolicy::id($connection);
        if(array_diff(array_keys($input),['api_stage','first_number','last_number','first_file','reference','interest_exempt','exclusive_range'])||($input['interest_exempt']??null)!=='1'||($input['exclusive_range']??null)!=='1'||!is_string($input['api_stage']??null))throw new \DomainException('bb_api_contract_declaration_required');
        ApiClient::stage($input['api_stage']);$first=self::number($input['first_number']??null,9999999999);$last=self::number($input['last_number']??null,9999999999);$file=self::number($input['first_file']??null,999999);$reference=self::text($input['reference']??null,191);if($last<$first)throw new \DomainException('bb_api_invalid_input');
        return $this->transaction(function()use($issuer,$actor,$connection,$input,$first,$last,$file,$reference):int{
            $this->authorize($issuer,$actor,'manage');[$c,$a]=$this->connection($issuer,$connection);
            if(($input['api_stage']==='production')!==($c['environment']==='production'))throw new \DomainException('bb_api_environment_invalid');
            $namespace=self::bankNamespace($a);$range=$this->rows('SELECT * FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=? FOR UPDATE',[$namespace])[0]??null;
            if($range){if((int)$range['issuer_id']!==$issuer||$range['beneficiary_tax_id']!==$a->taxId||(int)$range['first_number']!==$first||(int)$range['last_number']!==$last||(int)$range['first_file']!==$file)throw new \DomainException('bb_number_range_conflict');}
            else{
                foreach($this->rows('SELECT remote_id FROM '.$this->prefix.'bb_origins WHERE agreement_number=? AND environment=?',[$a->scope->agreement,$a->scope->environment]) as $old){$number=$old['remote_id'];if(is_string($number)&&strlen($number)===20&&str_starts_with($number,'000'))$number=substr($number,3);if(!is_string($number)||!preg_match('/^'.preg_quote($a->scope->agreement,'/').'[0-9]{10}$/D',$number)||(int)substr($number,7)>=$first)throw new \DomainException('bb_existing_number_requires_review');}
                \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_number_reservations(bank_namespace,issuer_id,beneficiary_tax_id,first_number,last_number,next_number,first_file,next_file) VALUES(?,?,?,?,?,?,?,?)',[$namespace,$issuer,$a->taxId,$first,$last,$first,$file,$file]);
            }
            $def=['issuer_id'=>$issuer,'connection_id'=>$connection,'configuration_hash'=>$c['configuration_hash'],'api_stage'=>$input['api_stage'],'spec_sha256'=>ApiClient::SPEC_SHA256,'first_number'=>$first,'last_number'=>$last,'first_file'=>$file,'reference'=>$reference,'interest_exempt'=>true,'exclusive_range'=>true,'verification'=>'operator_declaration_not_bank_verification','profile'=>'17-simple-BRL-no-adjustments-no-Pix'];
            $json=json_encode($def,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);$old=$this->rows('SELECT id FROM '.$this->prefix.'bb_api_profiles WHERE profile_hash=?',[$hash])[0]??null;if($old)return (int)$old['id'];
            $id=$this->insert('bb_api_profiles',['issuer_id'=>$issuer,'connection_id'=>$connection,'api_stage'=>$input['api_stage'],'configuration_hash'=>$c['configuration_hash'],'profile_hash'=>$hash,'definition_json'=>$json,'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')]);$this->audit($issuer,$connection,$actor,'api_profile_declared','api_profile',$id);return $id;
        });
    }
    public function saveCredentials($issuer,$actor,$profile,array $input):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$profile=\IssuerPolicy::id($profile);$input=ApiClient::credentials($input);
        if(!is_callable($this->encrypt)||!is_callable($this->decrypt))throw new \DomainException('bb_api_cipher_unavailable');
        return $this->transaction(function()use($issuer,$actor,$profile,$input):int{
            $this->authorize($issuer,$actor,'manage');[$row,,$c]=$this->profile($issuer,$profile);$nonce=bin2hex(random_bytes(32));
            $plain=json_encode(['issuer_id'=>$issuer,'connection_id'=>(int)$c['id'],'profile_id'=>$profile,'api_stage'=>$row['api_stage'],'nonce'=>$nonce,'credentials'=>$input],JSON_THROW_ON_ERROR);
            try{$cipher=($this->encrypt)($plain);$check=is_string($cipher)?($this->decrypt)($cipher):null;}catch(\Throwable $error){throw new \DomainException('bb_api_cipher_unavailable');}
            if(!is_string($cipher)||$cipher===''||strlen($cipher)>65536||hash_equals($plain,$cipher)||!is_string($check)||!hash_equals($plain,$check))throw new \DomainException('bb_api_cipher_unavailable');
            $id=$this->insert('bb_api_credentials',['profile_id'=>$profile,'issuer_id'=>$issuer,'context_nonce'=>$nonce,'ciphertext'=>$cipher,'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')]);$this->audit($issuer,(int)$c['id'],$actor,'api_credential_saved','api_profile',$profile);return $id;
        });
    }
    private function secrets(array $profile,array $credential,array $origin):array
    {
        if(!is_callable($this->decrypt))throw new \DomainException('bb_api_cipher_unavailable');
        try{$plain=($this->decrypt)($credential['ciphertext']);$data=is_string($plain)?json_decode($plain,true,16,JSON_THROW_ON_ERROR):null;}catch(\Throwable $error){throw new \DomainException('bb_api_cipher_unavailable');}
        if(!is_array($data)||(int)($data['issuer_id']??0)!==(int)$origin['issuer_id']||(int)($data['connection_id']??0)!==(int)$origin['connection_id']||(int)($data['profile_id']??0)!==(int)$profile['id']||($data['api_stage']??null)!==$profile['api_stage']||($data['nonce']??null)!==$credential['context_nonce']||(int)$credential['profile_id']!==(int)$profile['id']||(int)$credential['issuer_id']!==(int)$origin['issuer_id'])throw new \DomainException('bb_api_cipher_context_mismatch');
        return ApiClient::credentials($data['credentials']??[]);
    }
    /** Reserve once in a durable core transaction, then execute the outbox outside all SQL locks. */
    public function issue($issuer,$actor,$profile,$invoice,array $input,string $requestKey):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$profile=\IssuerPolicy::id($profile);$invoice=\IssuerPolicy::id($invoice);$input=ApiPayload::input($input);if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new \DomainException('bb_api_invalid_input');$inputHash=hash('sha256',json_encode([$profile,$invoice,$input],JSON_THROW_ON_ERROR));
        $origin=$this->transaction(function()use($issuer,$actor,$profile,$invoice,$input,$requestKey,$inputHash):int{
            $this->authorize($issuer,$actor,'api_issue',true);[$p,,$c,$a]=$this->profile($issuer,$profile);
            $old=$this->rows('SELECT o.origin_id,o.input_hash,b.issuer_id FROM '.$this->prefix.'bb_api_origins o JOIN '.$this->prefix.'bb_origins b ON b.id=o.origin_id WHERE o.request_key=? FOR UPDATE',[$requestKey])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||!hash_equals($old['input_hash'],$inputHash))throw new \DomainException('bb_api_request_conflict');return (int)$old['origin_id'];}
            if(!$this->rows('SELECT id FROM '.$this->prefix.'bb_api_credentials WHERE profile_id=? AND issuer_id=?',[$profile,$issuer]))throw new \DomainException('bb_api_credentials_required');
            $record=$this->rows('SELECT i.id,i.issuer_id,i.clientid,i.total,i.status,i.date,i.duedate,c.name currency FROM '.$this->prefix.'invoices i JOIN '.$this->prefix.'currencies c ON c.id=i.currency WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
            if(!$record||!in_array((int)$record['status'],[1,4],true)||$record['currency']!=='BRL')throw new \DomainException('bb_api_invoice_not_eligible');
            foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_api_invoice_not_eligible');
            // Keep all historical origins. Only an API-origin bank-confirmed cancellation can release its active slot.
            foreach($this->rows('SELECT id,state,active_slot,channel FROM '.$this->prefix.'bb_origins WHERE invoice_id=? FOR UPDATE',[$invoice]) as $old)if($old['active_slot']!==null||$old['channel']!=='api'||$old['state']!=='api_cancelled_confirmed')throw new \DomainException('bb_invoice_already_bound');
            if($this->db->table_exists($this->prefix.'asaas_payment_origins')&&$this->rows('SELECT id FROM '.$this->prefix.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_already_bound');
            $acl=new \IssuerResourceAccess($this->db,$this->prefix);$docs=new \InvoiceIssuerDocuments($this->db,$this->prefix,$actor,static fn($i,$s,$cap)=>$i===$issuer&&$s===$actor&&$acl->can($issuer,$actor,'invoices','view'));$doc=$docs->get($invoice,$issuer);
            $range=$this->rows('SELECT * FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=? FOR UPDATE',[self::bankNamespace($a)])[0]??null;
            if(!$range||(int)$range['issuer_id']!==$issuer||$range['beneficiary_tax_id']!==$a->taxId||(int)$range['next_number']>(int)$range['last_number'])throw new \DomainException('bb_number_range_exhausted');
            $sequence=(int)$range['next_number'];$number='000'.$a->ourNumber((string)$sequence);$payload=ApiPayload::build($a,$record,$doc,$number,$input);
            $snapshot=['invoice'=>$record,'document'=>$doc,'connection'=>$c,'profile_id'=>$profile,'profile_hash'=>$p['profile_hash'],'payload_hash'=>hash('sha256',$payload),'scope_key'=>$a->scope->key(),'api_stage'=>$p['api_stage']];$json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_number_reservations SET next_number=? WHERE bank_namespace=?',[$sequence+1,self::bankNamespace($a)]);
            $confirmed=$this->rows('SELECT next_number FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=?',[self::bankNamespace($a)])[0]??[];if((int)($confirmed['next_number']??0)!==$sequence+1)throw new \RuntimeException('issuer_bb_api_write_unconfirmed');
            $id=$this->insert('bb_origins',['invoice_id'=>$invoice,'issuer_id'=>$issuer,'connection_id'=>$c['id'],'account_id'=>$a->scope->accountId,'environment'=>$a->scope->environment,'agreement_number'=>$a->scope->agreement,'provider'=>'banco_brasil','channel'=>'api','remote_id'=>$number,'control_reference'=>'CRM'.$invoice.'N'.$sequence,'scope_key'=>$a->scope->key(),'state'=>'api_reserved','active_slot'=>1,'payload_hash'=>hash('sha256',$json),'created_at'=>date('Y-m-d H:i:s')]);
            \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.'bb_api_origins(origin_id,profile_id,request_key,input_hash,payload_json,snapshot_json) VALUES(?,?,?,?,?,?)',[$id,$profile,$requestKey,$inputHash,$payload,$json]);
            $this->audit($issuer,(int)$c['id'],$actor,'api_number_reserved','origin',$id);return $id;
        });
        $this->perform($issuer,$actor,$origin,'issue',hash('sha256','bb-api-issue-'.$origin));return $origin;
    }
    private function loadOrigin(int $issuer,int $id):array
    {
        $origin=$this->rows('SELECT * FROM '.$this->prefix.'bb_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;if(!$origin||$origin['provider']!=='banco_brasil'||$origin['channel']!=='api')throw new \DomainException('bb_api_origin_unavailable');
        $stored=$this->rows('SELECT * FROM '.$this->prefix.'bb_api_origins WHERE origin_id=?',[$id])[0]??null;
        if(!$stored||!hash_equals($origin['payload_hash'],hash('sha256',$stored['snapshot_json'])))throw new \DomainException('bb_api_origin_corrupt');$snapshot=json_decode($stored['snapshot_json'],true,32,JSON_THROW_ON_ERROR);
        [$profile,,$c,$a]=$this->profile($issuer,(int)$stored['profile_id']);
        if(!hash_equals($snapshot['payload_hash'],hash('sha256',$stored['payload_json']))||$snapshot['profile_hash']!==$profile['profile_hash']||$snapshot['api_stage']!==$profile['api_stage']||$snapshot['scope_key']!==$origin['scope_key']||$a->scope->key()!==$origin['scope_key']||(int)$snapshot['invoice']['id']!==(int)$origin['invoice_id']||(int)$snapshot['invoice']['issuer_id']!==$issuer||$origin['account_id']!==$c['account_id']||$origin['environment']!==$c['environment']||$origin['agreement_number']!==$c['agreement_number']||!preg_match('/^000'.preg_quote($c['agreement_number'],'/').'[0-9]{10}$/D',$origin['remote_id']))throw new \DomainException('bb_api_origin_corrupt');
        $payload=ApiClient::decodeExact($stored['payload_json']);
        if(($payload['numeroTituloCliente']??null)!==$origin['remote_id']||($payload['campoUtilizacaoBeneficiario']??null)!==$origin['control_reference']||($payload['numeroTituloBeneficiario']??null)!=='CRM'.$origin['invoice_id']||($payload['numeroConvenio']??null)!==$origin['agreement_number']||(int)($snapshot['connection']['id']??0)!==(int)$origin['connection_id']||($snapshot['connection']['account_id']??null)!==$origin['account_id']||($snapshot['connection']['environment']??null)!==$origin['environment']||($payload['pagador']['numeroInscricao']??null)!==$snapshot['document']['recipient']['tax_id']||\app\services\InvoicePaymentWriter::minorUnits($payload['valorOriginal']??null)!==\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']))throw new \DomainException('bb_api_origin_corrupt');
        $accepted=$this->rows("SELECT result_json FROM ".$this->prefix."bb_api_operations WHERE origin_id=? AND status='validated' AND operation IN ('issue','query') ORDER BY id ASC LIMIT 1",[$id])[0]??null;
        if($accepted){$result=json_decode($accepted['result_json'],true,32,JSON_THROW_ON_ERROR);if(isset($result['numeroContratoCobranca']))$snapshot['accepted_contract']=$result['numeroContratoCobranca'];}
        return [$origin,$stored,$snapshot,$profile,$a];
    }
    private function audit(int $issuer,int $connection,int $actor,string $action,string $type,int $resource):void
    {
        $id=$this->insert('bb_audit',['issuer_id'=>$issuer,'connection_id'=>$connection,'actor_id'=>$actor,'action'=>$action,'resource_type'=>$type,'resource_id'=>$resource,'created_at'=>date('Y-m-d H:i:s')]);
        if(count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=? AND actor_id=? AND action=?',[$id,$issuer,$actor,$action]))!==1)throw new \RuntimeException('issuer_bb_api_write_unconfirmed');
    }
    public function resumeIssue($issuer,$actor,$origin):int
    {
        $origin=\IssuerPolicy::id($origin);return $this->perform(\IssuerPolicy::id($issuer),\IssuerPolicy::id($actor),$origin,'issue',hash('sha256','bb-api-issue-'.$origin));
    }
    public function query($issuer,$actor,$origin,string $key):int
    {
        return $this->perform(\IssuerPolicy::id($issuer),\IssuerPolicy::id($actor),\IssuerPolicy::id($origin),'query',$key);
    }
    public function cancel($issuer,$actor,$origin):int
    {
        $origin=\IssuerPolicy::id($origin);return $this->perform(\IssuerPolicy::id($issuer),\IssuerPolicy::id($actor),$origin,'cancel',hash('sha256','bb-api-cancel-'.$origin));
    }
    private function perform(int $issuer,int $actor,int $originId,string $operation,string $requestKey):int
    {
        if(!in_array($operation,['issue','query','cancel'],true)||!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new \DomainException('bb_api_invalid_input');
        $prepared=$this->transaction(function()use($issuer,$actor,$originId,$operation,$requestKey):array{
            $this->authorize($issuer,$actor,'api_'.$operation,true);[$origin,$stored,$snapshot,$profile,$agreement]=$this->loadOrigin($issuer,$originId);
            $op=$this->rows('SELECT * FROM '.$this->prefix.'bb_api_operations WHERE origin_id=? AND operation=? AND request_key=? FOR UPDATE',[$originId,$operation,$requestKey])[0]??null;
            if($op&&in_array($op['status'],['validated','rejected','unknown','sending'],true))return ['id'=>(int)$op['id'],'execute'=>false];
            if($op&&$op['status']==='authenticating'&&$op['locked_until']>gmdate('Y-m-d H:i:s'))return ['id'=>(int)$op['id'],'execute'=>false];
            if($operation==='issue'){
                if(!in_array($origin['state'],['api_reserved','api_auth_failed'],true))throw new \DomainException('bb_api_issue_requires_query');
                $current=$this->rows('SELECT total,status FROM '.$this->prefix.'invoices WHERE id=? AND issuer_id=? FOR UPDATE',[$origin['invoice_id'],$issuer])[0]??null;
                if(!$current||!in_array((int)$current['status'],[1,4],true)||\app\services\InvoicePaymentWriter::minorUnits($current['total'])!==\app\services\InvoicePaymentWriter::minorUnits($snapshot['invoice']['total']))throw new \DomainException('bb_api_invoice_not_eligible');
            }
            if($operation==='cancel'){
                if(in_array($origin['state'],['api_cancelled_confirmed','api_settlement_observed','api_conflict_review'],true))throw new \DomainException('bb_api_cancel_requires_fresh_normal_query');
                $last=$this->rows("SELECT result_json,updated_at FROM ".$this->prefix."bb_api_operations WHERE origin_id=? AND operation='query' AND status='validated' ORDER BY id DESC LIMIT 1",[$originId])[0]??null;
                $result=$last?json_decode($last['result_json'],true,32,JSON_THROW_ON_ERROR):[];
                if(!$last||$last['updated_at']<gmdate('Y-m-d H:i:s',time()-300)||($result['codigoEstadoTituloCobranca']??null)!=='1'||($result['account_scope_confirmed']??null)!=='1')throw new \DomainException('bb_api_cancel_requires_fresh_normal_query');
            }
            $credential=$this->rows('SELECT * FROM '.$this->prefix.'bb_api_credentials WHERE profile_id=? AND issuer_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$profile['id'],$issuer])[0]??null;if(!$credential)throw new \DomainException('bb_api_credentials_required');
            $claim=bin2hex(random_bytes(32));$now=gmdate('Y-m-d H:i:s');$until=gmdate('Y-m-d H:i:s',time()+300);
            if($op){$id=(int)$op['id'];\IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_api_operations SET claim_token=?,status='authenticating',http_status=0,credential_id=?,failure_category='',actor_id=?,locked_until=?,updated_at=? WHERE id=?",[$claim,$credential['id'],$actor,$until,$now,$id]);}
            else{$id=$this->insert('bb_api_operations',['origin_id'=>$originId,'operation'=>$operation,'request_key'=>$requestKey,'claim_token'=>$claim,'status'=>'authenticating','http_status'=>0,'credential_id'=>$credential['id'],'response_hash'=>hash('sha256',''),'result_json'=>'{}','failure_category'=>'','actor_id'=>$actor,'locked_until'=>$until,'created_at'=>$now,'updated_at'=>$now]);}
            $this->audit($issuer,(int)$origin['connection_id'],$actor,'api_call_intent','api_operation',$id);
            return compact('id','claim','origin','stored','snapshot','profile','agreement','credential')+['execute'=>true];
        });
        $id=$prepared['id'];if(!$prepared['execute'])return $id;
        // No charge request can happen before this distinct, confirmed sending marker.
        $sending=false;
        try{
            $credentials=$this->secrets($prepared['profile'],$prepared['credential'],$prepared['origin']);
            $token=$this->client->token($prepared['profile']['api_stage'],$credentials,$operation);
            $this->transaction(function()use($issuer,$actor,$originId,$operation,$prepared,$id):void{
                $this->authorize($issuer,$actor,'api_'.$operation,true);$op=$this->rows('SELECT claim_token,status,actor_id FROM '.$this->prefix.'bb_api_operations WHERE id=? AND origin_id=? FOR UPDATE',[$id,$originId])[0]??[];
                if(($op['claim_token']??null)!==$prepared['claim']||($op['status']??null)!=='authenticating'||(int)$op['actor_id']!==$actor)throw new \DomainException('bb_api_claim_superseded');
                $this->loadOrigin($issuer,$originId);
                \IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_api_operations SET status='sending',updated_at=? WHERE id=?",[gmdate('Y-m-d H:i:s'),$id]);
                $saved=$this->rows('SELECT status FROM '.$this->prefix.'bb_api_operations WHERE id=?',[$id])[0]??[];if(($saved['status']??null)!=='sending')throw new \RuntimeException('issuer_bb_api_write_unconfirmed');
            });
            $sending=true;
            $response=$this->client->execute($prepared['profile']['api_stage'],$credentials,$operation,$prepared['origin']['remote_id'],$prepared['origin']['agreement_number'],$token,$prepared['stored']['payload_json']);
            $projection=ApiPayload::validateResult($operation,$response['data'],$prepared['snapshot'],$prepared['origin'],$prepared['agreement']);
            if($operation==='query')$projection+=$this->client->confirmAccount($prepared['profile']['api_stage'],$credentials,$prepared['agreement'],$prepared['origin']['remote_id'],$token,$prepared['snapshot'],$projection);
            $this->completeOperation($issuer,$actor,$originId,$id,$prepared['claim'],$operation,$response['status'],$response['response_hash'],$projection);
        }catch(\Throwable $error){
            $category=$error instanceof ApiFailure?$error->category:($error instanceof \DomainException&&preg_match('/^bb_api_[a-z_]+$/D',$error->getMessage())?substr($error->getMessage(),7):'operation_unconfirmed');
            $status=$error instanceof ApiFailure?$error->httpStatus:0;
            // Before the sending marker, no charge/cancellation request has been attempted.
            $state=!$sending?'auth_failed':(($error instanceof ApiFailure&&!$error->uncertain)?'rejected':'unknown');
            try{$this->failOperation($issuer,$actor,$originId,$id,$prepared['claim'],$operation,$state,$category,$status);}catch(\Throwable $ignored){}
            // A reserved origin and possibly an external effect survive a process/commit failure. Never repeat POST automatically.
            throw new \RuntimeException($sending?'bb_api_result_unconfirmed_query_origin':'bb_api_authentication_not_submitted');
        }
        return $id;
    }
    private function completeOperation(int $issuer,int $actor,int $originId,int $id,string $claim,string $operation,int $http,string $responseHash,array $result):void
    {
        $this->transaction(function()use($issuer,$actor,$originId,$id,$claim,$operation,$http,$responseHash,$result):void{
            // Persist a previously authorized request's outcome even if its actor was revoked while HTTP was in flight.
            $op=$this->rows('SELECT * FROM '.$this->prefix.'bb_api_operations WHERE id=? AND origin_id=? FOR UPDATE',[$id,$originId])[0]??[];
            if(($op['claim_token']??null)!==$claim||($op['status']??null)!=='sending'||(int)$op['actor_id']!==$actor)throw new \DomainException('bb_api_claim_superseded');
            [$origin]=$this->loadOrigin($issuer,$originId);$json=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            \IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_api_operations SET status='validated',http_status=?,response_hash=?,result_json=?,failure_category='',updated_at=? WHERE id=?",[$http,$responseHash,$json,gmdate('Y-m-d H:i:s'),$id]);
            $state=$operation==='issue'?'api_registered':($operation==='cancel'?'api_cancel_requested':null);
            if($operation==='query'){
                $observed=(int)$result['codigoEstadoTituloCobranca'];
                if($observed===7)$state='api_cancelled_confirmed';
                elseif(in_array($observed,[6,16],true))$state='api_settlement_observed';
                elseif($origin['state']==='api_reserved'||$origin['state']==='api_unknown')$state='api_query_observed';
            }
            // A delayed normal query cannot undo a confirmed terminal observation.
            $changed=false;
            // A later observation cannot erase an already materialized contradictory bank history.
            if($origin['state']==='api_conflict_review'&&$state!==null)$state='api_conflict_review';
            if($state!==null&&(!in_array($origin['state'],['api_cancelled_confirmed','api_settlement_observed'],true)||in_array($state,['api_cancelled_confirmed','api_settlement_observed'],true))){
                if($origin['state']==='api_cancelled_confirmed'&&$state==='api_settlement_observed')$state='api_conflict_review';
                if($origin['state']==='api_settlement_observed'&&$state==='api_cancelled_confirmed')$state='api_conflict_review';
                \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_origins SET state=?,active_slot=? WHERE id=? AND issuer_id=?',[$state,$state==='api_cancelled_confirmed'?null:$origin['active_slot'],$originId,$issuer]);$changed=true;
            }
            if($changed){$materialized=$this->rows('SELECT state,active_slot FROM '.$this->prefix.'bb_origins WHERE id=? AND issuer_id=?',[$originId,$issuer])[0]??[];if(($materialized['state']??null)!==$state||($state==='api_cancelled_confirmed'?($materialized['active_slot']??null)!==null:($materialized['active_slot']??null)!==$origin['active_slot']))throw new \RuntimeException('issuer_bb_api_write_unconfirmed');}
            $this->audit($issuer,(int)$origin['connection_id'],$actor,'api_result_validated','api_operation',$id);
            $saved=$this->rows('SELECT status,http_status,response_hash,result_json FROM '.$this->prefix.'bb_api_operations WHERE id=?',[$id])[0]??[];
            if(($saved['status']??null)!=='validated'||(int)($saved['http_status']??0)!==$http||($saved['response_hash']??null)!==$responseHash||($saved['result_json']??null)!==$json)throw new \RuntimeException('issuer_bb_api_write_unconfirmed');
        });
    }
    private function failOperation(int $issuer,int $actor,int $originId,int $id,string $claim,string $operation,string $state,string $category,int $http):void
    {
        $this->transaction(function()use($issuer,$actor,$originId,$id,$claim,$operation,$state,$category,$http):void{
            $op=$this->rows('SELECT claim_token,status,actor_id FROM '.$this->prefix.'bb_api_operations WHERE id=? AND origin_id=? FOR UPDATE',[$id,$originId])[0]??[];
            if(($op['claim_token']??null)!==$claim||!in_array($op['status']??null,['authenticating','sending'],true)||(int)$op['actor_id']!==$actor)return;
            $origin=$this->rows('SELECT connection_id FROM '.$this->prefix.'bb_origins WHERE id=? AND issuer_id=? FOR UPDATE',[$originId,$issuer])[0]??null;if(!$origin)throw new \DomainException('bb_api_origin_unavailable');
            $category=preg_match('/^[a-z_]{1,48}$/D',$category)?$category:'operation_unconfirmed';
            \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_api_operations SET status=?,http_status=?,failure_category=?,updated_at=? WHERE id=?',[$state,$http,$category,gmdate('Y-m-d H:i:s'),$id]);
            if($operation==='issue')\IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_origins SET state=? WHERE id=? AND issuer_id=?',[$state==='auth_failed'?'api_auth_failed':($state==='rejected'?'api_registration_rejected':'api_unknown'),$originId,$issuer]);
            $this->audit($issuer,(int)$origin['connection_id'],$actor,'api_call_'.$state,'api_operation',$id);
        });
    }
    public function overview($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);
        return $this->transaction(function()use($issuer,$actor):array{
            $this->authorize($issuer,$actor,'view');
            $profiles=$this->rows('SELECT p.id,p.connection_id,p.api_stage,p.definition_json,(SELECT MAX(c.id) FROM '.$this->prefix.'bb_api_credentials c WHERE c.profile_id=p.id AND c.issuer_id=p.issuer_id) credential_id FROM '.$this->prefix.'bb_api_profiles p WHERE p.issuer_id=? ORDER BY p.id DESC LIMIT 30',[$issuer]);
            foreach($profiles as &$p){$def=json_decode($p['definition_json'],true,16,JSON_THROW_ON_ERROR);$p['reference']=$def['reference'];$p['range']=(string)$def['first_number'].'–'.$def['last_number'];unset($p['definition_json']);}unset($p);
            $origins=$this->rows('SELECT o.id,o.invoice_id,o.connection_id,o.remote_id,o.state,a.profile_id FROM '.$this->prefix.'bb_origins o JOIN '.$this->prefix.'bb_api_origins a ON a.origin_id=o.id WHERE o.issuer_id=? ORDER BY o.id DESC LIMIT 30',[$issuer]);
            $receipts=$this->rows('SELECT r.origin_id,r.payment_id,r.operation_id FROM '.$this->prefix.'bb_api_receipts r JOIN '.$this->prefix.'bb_origins o ON o.id=r.origin_id AND o.issuer_id=r.issuer_id WHERE r.issuer_id=?',[$issuer]);
            $operations=$this->rows('SELECT x.id,x.origin_id,x.operation,x.status,x.http_status,x.failure_category,x.result_json,x.updated_at FROM '.$this->prefix.'bb_api_operations x JOIN '.$this->prefix.'bb_origins o ON o.id=x.origin_id WHERE o.issuer_id=? ORDER BY x.id DESC LIMIT 30',[$issuer]);
            foreach($operations as &$op){$op['result']=json_decode($op['result_json'],true,32,JSON_THROW_ON_ERROR);unset($op['result_json']);}unset($op);
            return compact('profiles','origins','operations','receipts');
        });
    }

}
