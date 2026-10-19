<?php

declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Agreement.php';
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Cnab240Generator.php';
require_once __DIR__.'/OriginationSchema.php';
require_once APPPATH.'services/issuers/IssuerTransaction.php';
require_once APPPATH.'services/issuers/IssuerResourceAccess.php';
require_once APPPATH.'services/issuers/InvoiceIssuerDocuments.php';
require_once APPPATH.'services/InvoicePaymentWriter.php';

/** Local remittance production. No bank transport, API authentication or financial settlement. */
final class Origination
{
    public function __construct(private $db,private string $prefix,private $encoder=null)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new \InvalidArgumentException('bb_prefix_invalid');
    }
    private function rows(string $sql,array $args=[]):array{return \IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function insert(string $table,array $fields):int
    {
        \IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.' (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));return (int)$this->db->insert_id();
    }
    public function ready():bool
    {
        $row=$this->rows('SELECT active,installed_version FROM '.$this->prefix.'modules WHERE module_name=?',['banco_brasil'])[0]??[];
        return (int)($row['active']??0)===1&&($row['installed_version']??null)==='1.0.6';
    }
    private function authorize(int $issuer,int $actor,string $cap,bool $invoice=false):void
    {
        if(!$this->ready())throw new \DomainException('bb_origination_upgrade_required');
        $a=new \IssuerResourceAccess($this->db,$this->prefix);
        if(!$a->can($issuer,$actor,'banco_brasil',$cap)||($invoice&&!$a->can($issuer,$actor,'invoices','view')))throw new \DomainException('bb_origination_access_denied');
    }
    private function transaction(callable $work)
    {
        OriginationSchema::assertValid($this->db,$this->prefix);
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules','invoices','issuers_invoice_documents','currencies','invoicepaymentrecords','credits','bb_connections','bb_layout_bindings','bb_origins','bb_batches','bb_audit','bb_number_reservations','bb_contract_declarations','bb_origin_snapshots','bb_batch_origins','bb_batch_actions'];
        if($this->db->table_exists($this->prefix.'asaas_payment_origins'))$tables[]='asaas_payment_origins';
        return \IssuerTransaction::run($this->db,$this->prefix,$tables,$work);
    }
    private function connection(int $issuer,int $id,int $binding):array
    {
        $row=$this->rows('SELECT * FROM '.$this->prefix.'bb_connections WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;
        if(!$row)throw new \DomainException('bb_connection_unavailable');
        $identity=hash('sha256',json_encode([$issuer,$row['account_id'],$row['environment'],$row['agreement_number'],$row['wallet'],$row['variation']],JSON_THROW_ON_ERROR));
        if(($row['provider']??null)!=='banco_brasil'||!hash_equals($identity,$row['identity_hash'])||!hash_equals(hash('sha256',json_encode([$identity,$row['issuer_tax_id'],$row['issuer_name']],JSON_THROW_ON_ERROR)),$row['configuration_hash']))throw new \DomainException('bb_connection_unavailable');
        $a=new Agreement(new Scope($issuer,$id,$row['account_id'],$row['environment'],$row['agreement_number']),$row['issuer_tax_id'],$row['issuer_name'],$row['variation'],$row['wallet']);
        $layout=$this->rows('SELECT * FROM '.$this->prefix.'bb_layout_bindings WHERE id=? AND issuer_id=? AND connection_id=? FOR UPDATE',[$binding,$issuer,$id])[0]??[];
        if(($layout['layout_id']??null)!==Agreement::LAYOUT_ID||($layout['scope_key']??null)!==$a->scope->key()||($layout['connection_hash']??null)!==$row['configuration_hash']||($layout['profile_hash']??null)!==LayoutCatalog::fingerprint(Agreement::LAYOUT_ID)||($layout['remittance_source_hash']??null)!==Agreement::SOURCE_SHA256)throw new \DomainException('bb_origination_profile_denied');
        $profile=LayoutCatalog::get(Agreement::LAYOUT_ID);
        foreach(['return_source_hash'=>'return_sha256','date_window'=>'date_window','declared_currency'=>'currency'] as $field=>$key)if(($layout[$field]??null)!==$profile[$key])throw new \DomainException('bb_origination_profile_denied');
        return [$row,$a,$layout];
    }
    private static function number($value,int $max):int
    {
        if(!is_string($value)||!preg_match('/^[0-9]{1,10}$/D',$value)||(int)$value<1||(int)$value>$max)throw new \DomainException('bb_origination_invalid_input');return (int)$value;
    }
    private static function text($value,int $max):string
    {
        if(!is_string($value)||trim($value)===''||strlen($value)>$max||preg_match('/[\x00-\x1f\x7f]/',$value)||preg_match('//u',$value)!==1)throw new \DomainException('bb_origination_invalid_input');return trim($value);
    }
    private static function bankNamespace(Agreement $a):string
    {
        // Nosso Número identifies the agreement, across account/connection changes and future channels.
        return hash('sha256',json_encode(['banco_brasil',$a->scope->environment,$a->scope->agreement],JSON_THROW_ON_ERROR));
    }
    public function declareContract($issuer,$actor,$connection,$binding,array $input):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$connection=\IssuerPolicy::id($connection);$binding=\IssuerPolicy::id($binding);
        if(array_diff(array_keys($input),['first_number','last_number','first_file','reference','interest_exempt','exclusive_range','test_exchange','beneficiary_name'])||($input['interest_exempt']??null)!=='1'||($input['exclusive_range']??null)!=='1')throw new \DomainException('bb_contract_declaration_required');
        $first=self::number($input['first_number']??null,9999999999);$last=self::number($input['last_number']??null,9999999999);$file=self::number($input['first_file']??null,999999);$reference=self::text($input['reference']??null,191);
        if($last<$first||!in_array($input['test_exchange']??null,['0','1'],true))throw new \DomainException('bb_origination_invalid_input');
        return $this->transaction(function()use($issuer,$actor,$connection,$binding,$input,$first,$last,$file,$reference):int{
            $this->authorize($issuer,$actor,'manage');[$row,$a,$layout]=$this->connection($issuer,$connection,$binding);
            if($a->scope->environment==='sandbox'&&$input['test_exchange']!=='1')throw new \DomainException('bb_contract_declaration_required');
            $wireName=self::text($input['beneficiary_name']??null,30);Fields::alpha($wireName,30);
            $namespace=self::bankNamespace($a);$counter=$this->rows('SELECT * FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=? FOR UPDATE',[$namespace])[0]??null;
            if($counter){
                if((int)$counter['issuer_id']!==$issuer||$counter['beneficiary_tax_id']!==$a->taxId||(int)$counter['first_number']!==$first||(int)$counter['last_number']!==$last||(int)$counter['first_file']!==$file)throw new \DomainException('bb_number_range_conflict');
            }else{
                $existing=$this->rows('SELECT remote_id FROM '.$this->prefix.'bb_origins WHERE agreement_number=? AND environment=?',[$a->scope->agreement,$a->scope->environment]);
                foreach($existing as $old)if(!is_string($old['remote_id'])||!preg_match('/^'.preg_quote($a->scope->agreement,'/').'[0-9]{10}$/D',$old['remote_id'])||(int)substr($old['remote_id'],7)>=$first)throw new \DomainException('bb_existing_number_requires_review');
                $this->insert('bb_number_reservations',['bank_namespace'=>$namespace,'issuer_id'=>$issuer,'beneficiary_tax_id'=>$a->taxId,'first_number'=>$first,'last_number'=>$last,'next_number'=>$first,'first_file'=>$file,'next_file'=>$file]);
            }
            $declaration=['beneficiary_name'=>$wireName,'connection_hash'=>$row['configuration_hash'],'profile_hash'=>$layout['profile_hash'],'first_number'=>$first,'last_number'=>$last,'first_file'=>$file,'interest_exempt'=>true,'exclusive_range'=>true,'test_exchange'=>$input['test_exchange']==='1','reference'=>$reference,'verification'=>'operator_declaration_not_bank_verification'];
            $json=json_encode($declaration,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
            $old=$this->rows('SELECT id FROM '.$this->prefix.'bb_contract_declarations WHERE declaration_hash=?',[$hash])[0]??null;if($old)return (int)$old['id'];
            $id=$this->insert('bb_contract_declarations',['issuer_id'=>$issuer,'connection_id'=>$connection,'layout_binding_id'=>$binding,'declaration_hash'=>$hash,'reference'=>$reference,'declaration_json'=>$json,'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')]);
            if($id<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');$this->audit($issuer,$connection,$actor,'contract_declared','contract',$id);$this->authorize($issuer,$actor,'manage');return $id;
        });
    }
    public function generate($issuer,$actor,$connection,$binding,string $date,array $titles,string $requestKey):int
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$connection=\IssuerPolicy::id($connection);$binding=\IssuerPolicy::id($binding);
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey)||!$titles||count($titles)>100)throw new \DomainException('bb_origination_invalid_input');Fields::date($date);
        $normalized=[];foreach($titles as $input){
            if(!is_array($input)||array_diff(array_keys($input),['invoice_id','payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance','payer_name']))throw new \DomainException('bb_origination_invalid_input');
            $id=\IssuerPolicy::id($input['invoice_id']??null);if(isset($normalized[$id]))throw new \DomainException('bb_duplicate_title');
            $title=[];foreach(['payer_address','payer_district','payer_zip','payer_city','payer_state','species','acceptance','payer_name'] as $key)$title[$key]=self::text($input[$key]??null,100);
            $normalized[$id]=$title;
        }ksort($normalized,SORT_NUMERIC);
        $hash=hash('sha256',json_encode([$connection,$binding,$date,$normalized],JSON_THROW_ON_ERROR));
        $id=$this->transaction(function()use($issuer,$actor,$connection,$binding,$date,$normalized,$hash,$requestKey):int{
            $this->authorize($issuer,$actor,'generate',true);[$c,$a,$layout]=$this->connection($issuer,$connection,$binding);
            $old=$this->rows('SELECT id,payload_hash FROM '.$this->prefix.'bb_batches WHERE scope_key=? AND idempotency_key=? FOR UPDATE',[$a->scope->key(),$requestKey])[0]??null;
            if($old){if(!hash_equals($old['payload_hash'],$hash))throw new \DomainException('bb_batch_request_conflict');return (int)$old['id'];}
            $contract=$this->rows('SELECT * FROM '.$this->prefix.'bb_contract_declarations WHERE issuer_id=? AND connection_id=? AND layout_binding_id=? ORDER BY id DESC LIMIT 1 FOR UPDATE',[$issuer,$connection,$binding])[0]??null;
            if(!$contract)throw new \DomainException('bb_contract_declaration_required');
            $declared=json_decode($contract['declaration_json'],true,16,JSON_THROW_ON_ERROR);
            $wireName=$declared['beneficiary_name']??$c['issuer_name'];Fields::alpha($wireName,30);
            $a=new Agreement($a->scope,$a->taxId,$wireName,$a->variation,$a->wallet);
            $counter=$this->rows('SELECT * FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=? FOR UPDATE',[self::bankNamespace($a)])[0]??null;
            if(!$counter||(int)$counter['issuer_id']!==$issuer||$counter['beneficiary_tax_id']!==$a->taxId||(int)$counter['next_number']> (int)$counter['last_number']-count($normalized)+1||(int)$counter['next_file']>999999)throw new \DomainException('bb_number_range_exhausted');
            $number=(int)$counter['next_number'];$file=(int)$counter['next_file'];$snapshots=[];$wire=[];
            $acl=new \IssuerResourceAccess($this->db,$this->prefix);$docs=new \InvoiceIssuerDocuments($this->db,$this->prefix,$actor,static fn($id,$staff,$cap)=>$id===$issuer&&$staff===$actor&&$acl->can($issuer,$actor,'invoices','view'));
            foreach($normalized as $invoice=>$payer){
                $record=$this->rows('SELECT i.id,i.issuer_id,i.clientid,i.total,i.status,i.date,i.duedate,c.name currency FROM '.$this->prefix.'invoices i JOIN '.$this->prefix.'currencies c ON c.id=i.currency WHERE i.id=? AND i.issuer_id=? FOR UPDATE',[$invoice,$issuer])[0]??null;
                if(!$record||!in_array((int)$record['status'],[1,4],true)||$record['currency']!=='BRL')throw new \DomainException('bb_invoice_not_eligible');
                foreach([['invoicepaymentrecords','invoiceid'],['credits','invoice_id']] as [$table,$column])if($this->rows('SELECT amount FROM '.$this->prefix.$table.' WHERE '.$column.'=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_not_eligible');
                if($this->rows('SELECT id FROM '.$this->prefix.'bb_origins WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_already_bound');
                if($this->db->table_exists($this->prefix.'asaas_payment_origins')&&$this->rows('SELECT id FROM '.$this->prefix.'asaas_payment_origins WHERE invoice_id=? FOR UPDATE',[$invoice]))throw new \DomainException('bb_invoice_already_bound');
                $doc=$docs->get($invoice,$issuer);if($doc['identity']['tax_id']!==$a->taxId)throw new \DomainException('bb_beneficiary_identity_mismatch');
                $t=$payer+['sequence'=>(string)$number,'document'=>'CRM'.$invoice,'control'=>'CRM'.$invoice,'issue_date'=>$record['date'],'due_date'=>$record['duedate'],'amount'=>self::money($record['total']),'payer_tax_id'=>$doc['recipient']['tax_id'],'payer_name'=>$doc['recipient']['name']];
                $snapshots[]=['invoice_document'=>$doc['formatted_number'],'invoice'=>$record,'title'=>$t,'connection'=>$c,'beneficiary_name'=>$wireName,'layout_binding_id'=>$binding,'contract_id'=>(int)$contract['id'],'scope_key'=>$a->scope->key()];$wire[]=$t;$number++;
            }
            // Validate all fields before a durable reservation. These bytes never leave this scope.
            (new Cnab240Generator())->generate($a,$file,$date,$wire);
            \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_number_reservations SET next_number=?,next_file=? WHERE bank_namespace=?',[$number,$file+1,self::bankNamespace($a)]);
            $batch=$this->insert('bb_batches',['issuer_id'=>$issuer,'connection_id'=>$connection,'scope_key'=>$a->scope->key(),'idempotency_key'=>$requestKey,'payload_hash'=>$hash,'layout_id'=>Agreement::LAYOUT_ID,'layout_source_hash'=>Agreement::SOURCE_SHA256,'layout_binding_id'=>$binding,'file_sequence'=>$file,'content_hash'=>hash('sha256',''),'content'=>'','generation_state'=>'reserved','delivery_state'=>'not_submitted','financial_state'=>'not_applied','created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')]);
            if($batch<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            foreach($snapshots as $snapshot){$snapshot['generation_date']=$date;$json=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);$t=$snapshot['title'];
                $origin=$this->insert('bb_origins',['invoice_id'=>$snapshot['invoice']['id'],'issuer_id'=>$issuer,'connection_id'=>$connection,'account_id'=>$a->scope->accountId,'environment'=>$a->scope->environment,'agreement_number'=>$a->scope->agreement,'provider'=>'banco_brasil','channel'=>'cnab240','layout_binding_id'=>$binding,'remote_id'=>$a->ourNumber($t['sequence']),'control_reference'=>$t['control'],'scope_key'=>$a->scope->key(),'state'=>'reserved','active_slot'=>1,'payload_hash'=>hash('sha256',$json),'created_at'=>date('Y-m-d H:i:s')]);
                if($origin<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');$this->insert('bb_origin_snapshots',['origin_id'=>$origin,'layout_binding_id'=>$binding,'contract_id'=>$contract['id'],'snapshot_json'=>$json]);$this->insert('bb_batch_origins',['batch_id'=>$batch,'origin_id'=>$origin]);
            }
            $confirmed=$this->rows('SELECT next_number,next_file FROM '.$this->prefix.'bb_number_reservations WHERE bank_namespace=?',[self::bankNamespace($a)])[0]??[];
            if((int)($confirmed['next_number']??0)!==$number||(int)($confirmed['next_file']??0)!==$file+1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            $this->audit($issuer,$connection,$actor,'numbers_reserved','batch',$batch);$this->authorize($issuer,$actor,'generate',true);return $batch;
        });
        $this->finish($issuer,$actor,$id);return $id;
    }
    private static function money($value):string
    {
        $c=\app\services\InvoicePaymentWriter::minorUnits($value);return intdiv($c,100).'.'.str_pad((string)($c%100),2,'0',STR_PAD_LEFT);
    }
    private function load(int $issuer,int $batch):array
    {
        $b=$this->rows('SELECT * FROM '.$this->prefix.'bb_batches WHERE id=? AND issuer_id=?',[$batch,$issuer])[0]??null;if(!$b)throw new \DomainException('bb_batch_unavailable');
        $rows=$this->rows('SELECT o.*,s.snapshot_json FROM '.$this->prefix.'bb_batch_origins x JOIN '.$this->prefix.'bb_origins o ON o.id=x.origin_id JOIN '.$this->prefix.'bb_origin_snapshots s ON s.origin_id=o.id WHERE x.batch_id=? AND o.issuer_id=? ORDER BY o.invoice_id',[$batch,$issuer]);
        if(!$rows||count($rows)>100)throw new \DomainException('bb_batch_corrupt');$snapshots=[];
        if($b['layout_id']!==Agreement::LAYOUT_ID||$b['layout_source_hash']!==Agreement::SOURCE_SHA256)throw new \DomainException('bb_batch_corrupt');
        foreach($rows as $row){
            if(!hash_equals($row['payload_hash'],hash('sha256',$row['snapshot_json'])))throw new \DomainException('bb_batch_corrupt');
            $s=json_decode($row['snapshot_json'],true,32,JSON_THROW_ON_ERROR);$c=$s['connection'];
            $a=new Agreement(new Scope($issuer,(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$s['beneficiary_name']??$c['issuer_name'],$c['variation'],$c['wallet']);
            if(($s['scope_key']??null)!==$b['scope_key']||$a->scope->key()!==$b['scope_key']||(int)$s['invoice']['id']!==(int)$row['invoice_id']||(int)$s['invoice']['issuer_id']!==$issuer||(int)$c['id']!==(int)$b['connection_id']||(int)$s['layout_binding_id']!==(int)$b['layout_binding_id'])throw new \DomainException('bb_batch_corrupt');
            $expected=['scope_key'=>$b['scope_key'],'provider'=>'banco_brasil','channel'=>'cnab240','account_id'=>$c['account_id'],'environment'=>$c['environment'],'agreement_number'=>$c['agreement_number'],'remote_id'=>$a->ourNumber($s['title']['sequence']),'control_reference'=>$s['title']['control']];
            foreach($expected as $key=>$value)if($row[$key]!==$value)throw new \DomainException('bb_batch_corrupt');
            if((int)$row['connection_id']!==(int)$b['connection_id']||(int)$row['layout_binding_id']!==(int)$b['layout_binding_id'])throw new \DomainException('bb_batch_corrupt');
            if($snapshots&&($snapshots[0]['connection']!==$c||$snapshots[0]['generation_date']!==$s['generation_date']))throw new \DomainException('bb_batch_corrupt');
            $snapshots[]=$s;
        }
        return [$b,$snapshots];
    }
    public function finish($issuer,$actor,$batch):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$batch=\IssuerPolicy::id($batch);$this->authorize($issuer,$actor,'generate',true);
        [$b,$snapshots]=$this->load($issuer,$batch);if($b['generation_state']==='generated')return;
        $c=$snapshots[0]['connection'];$a=new Agreement(new Scope($issuer,(int)$c['id'],$c['account_id'],$c['environment'],$c['agreement_number']),$c['issuer_tax_id'],$snapshots[0]['beneficiary_name']??$c['issuer_name'],$c['variation'],$c['wallet']);
        try{
            $encode=$this->encoder??static fn($agreement,$sequence,$date,$titles)=>(new Cnab240Generator())->generate($agreement,$sequence,$date,$titles);
            $bytes=$encode($a,(int)$b['file_sequence'],$snapshots[0]['generation_date'],array_column($snapshots,'title'));self::validateBytes($bytes,count($snapshots));
            $this->transaction(function()use($issuer,$actor,$batch,$bytes):void{
                $this->authorize($issuer,$actor,'generate',true);[$current,$stored]=$this->load($issuer,$batch);
                if($current['generation_state']==='generated'){if(!hash_equals($current['content_hash'],hash('sha256',$bytes)))throw new \DomainException('bb_batch_corrupt');return;}
                \IssuerStorage::query($this->db,'UPDATE '.$this->prefix.'bb_batches SET content=?,content_hash=?,generation_state=? WHERE id=? AND issuer_id=?',[$bytes,hash('sha256',$bytes),'generated',$batch,$issuer]);
                \IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_origins SET state='generated_not_submitted' WHERE id IN(SELECT origin_id FROM ".$this->prefix."bb_batch_origins WHERE batch_id=?)",[$batch]);
                $saved=$this->rows('SELECT content,content_hash,generation_state FROM '.$this->prefix.'bb_batches WHERE id=? AND issuer_id=?',[$batch,$issuer])[0]??[];
                if(($saved['content']??null)!==$bytes||($saved['content_hash']??null)!==hash('sha256',$bytes)||($saved['generation_state']??null)!=='generated')throw new \RuntimeException('issuer_bb_write_unconfirmed');
                $this->audit($issuer,(int)$current['connection_id'],$actor,'remittance_generated','batch',$batch);$this->authorize($issuer,$actor,'generate',true);
            });
        }catch(\Throwable $error){
            try{$this->transaction(function()use($issuer,$actor,$batch):void{$this->authorize($issuer,$actor,'generate',true);$current=$this->rows('SELECT connection_id,generation_state FROM '.$this->prefix.'bb_batches WHERE id=? AND issuer_id=? FOR UPDATE',[$batch,$issuer])[0]??null;if($current&&$current['generation_state']!=='generated'){\IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_batches SET generation_state='generation_failed' WHERE id=? AND issuer_id=?",[$batch,$issuer]);$this->audit($issuer,(int)$current['connection_id'],$actor,'generation_failed_reserved','batch',$batch);}});}catch(\Throwable $ignored){}
            throw new \RuntimeException('bb_generation_unconfirmed_numbers_reserved');
        }
    }
    private static function validateBytes($bytes,int $titles):void
    {
        if(!is_string($bytes)||strlen($bytes)>16*1024*1024||!str_ends_with($bytes,"\r\n")||preg_match('/[^\x20-\x7e\r\n]/',$bytes))throw new \DomainException('bb_batch_corrupt');$lines=explode("\r\n",substr($bytes,0,-2));if(count($lines)!==$titles*2+4)throw new \DomainException('bb_batch_corrupt');foreach($lines as $line)if(strlen($line)!==240)throw new \DomainException('bb_batch_corrupt');
    }
    public function download($issuer,$actor,$batch):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$batch=\IssuerPolicy::id($batch);
        return $this->transaction(function()use($issuer,$actor,$batch):array{
            $this->authorize($issuer,$actor,'download',true);[$b,$snapshots]=$this->load($issuer,$batch);
            if($b['generation_state']!=='generated'||!hash_equals($b['content_hash'],hash('sha256',$b['content'])))throw new \DomainException('bb_batch_unavailable');self::validateBytes($b['content'],count($snapshots));
            if($b['delivery_state']==='not_submitted')\IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_batches SET delivery_state='downloaded' WHERE id=? AND issuer_id=?",[$batch,$issuer]);
            $this->audit($issuer,(int)$b['connection_id'],$actor,'remittance_downloaded','batch',$batch);$this->authorize($issuer,$actor,'download',true);
            return ['content'=>$b['content'],'hash'=>$b['content_hash'],'filename'=>'bb-cnab240-'.$batch.'.rem'];
        });
    }
    public function reportTransmission($issuer,$actor,$batch,string $reference):void
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$batch=\IssuerPolicy::id($batch);$reference=self::text($reference,191);
        $this->transaction(function()use($issuer,$actor,$batch,$reference):void{
            $this->authorize($issuer,$actor,'generate',true);[$b]=$this->load($issuer,$batch);
            if($b['generation_state']!=='generated'||!in_array($b['delivery_state'],['downloaded','reported_submitted'],true))throw new \DomainException('bb_batch_unavailable');
            $old=$this->rows("SELECT reference FROM ".$this->prefix."bb_batch_actions WHERE batch_id=? AND action='manual_submission_reported'",[$batch]);if($old){if(count($old)!==1||$old[0]['reference']!==$reference)throw new \DomainException('bb_submission_report_conflict');return;}
            $id=$this->insert('bb_batch_actions',['batch_id'=>$batch,'issuer_id'=>$issuer,'actor_id'=>$actor,'action'=>'manual_submission_reported','reference'=>$reference,'created_at'=>date('Y-m-d H:i:s')]);if($id<1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
            \IssuerStorage::query($this->db,"UPDATE ".$this->prefix."bb_batches SET delivery_state='reported_submitted' WHERE id=? AND issuer_id=?",[$batch,$issuer]);$this->audit($issuer,(int)$b['connection_id'],$actor,'submission_reported_not_verified','batch',$batch);$this->authorize($issuer,$actor,'generate',true);
        });
    }
    public function batches($issuer,$actor):array
    {
        $issuer=\IssuerPolicy::id($issuer);$actor=\IssuerPolicy::id($actor);$this->authorize($issuer,$actor,'view');
        $rows=$this->rows('SELECT id,connection_id,file_sequence,generation_state,delivery_state,financial_state,content_hash,created_at FROM '.$this->prefix.'bb_batches WHERE issuer_id=? ORDER BY id DESC LIMIT 30',[$issuer]);$this->authorize($issuer,$actor,'view');return $rows;
    }
    private function audit(int $issuer,int $connection,int $actor,string $action,string $type,int $id):void
    {
        $key=$this->insert('bb_audit',['issuer_id'=>$issuer,'connection_id'=>$connection,'actor_id'=>$actor,'action'=>$action,'resource_type'=>$type,'resource_id'=>$id,'created_at'=>date('Y-m-d H:i:s')]);
        if($key<1||count($this->rows('SELECT id FROM '.$this->prefix.'bb_audit WHERE id=? AND issuer_id=?',[$key,$issuer]))!==1)throw new \RuntimeException('issuer_bb_write_unconfirmed');
    }
}
