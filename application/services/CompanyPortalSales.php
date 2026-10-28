<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/CompanySalesSchema.php';
require_once __DIR__.'/CompanySalesPreferences.php';
require_once __DIR__.'/CompanyInvoiceLines.php';
require_once __DIR__.'/CompanySettingsStorage.php';

/** Published accepted sales documents only. Contact grants are native and specific to each kind. */
final class CompanyPortalSales
{
    private ?array $activeDecision=null;
    public function __construct(private $db,private string $p,private CompanyPortal $portal){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $values=[]):array{return IssuerStorage::query($this->db,$sql,$values)->result_array();}
    private static function safeDate($value,bool $nullable=false):?string
    {
        if($nullable&&$value===null)return null;if(!is_string($value)||!($date=DateTimeImmutable::createFromFormat('!Y-m-d',$value))||$date->format('Y-m-d')!==$value)throw new DomainException('company_portal_sales_unavailable');return $value;
    }
    private static function assertAmount(array $row,array $payload):void
    {
        try{$amounts=CompanyInvoiceLines::calculate($payload['items'],array_intersect_key($payload,array_flip(['discount_mode','discount_value','discount_type','adjustment','calculation_contract'])));if(CompanyInvoiceAmounts::minor((string)$row['total'])!==CompanyInvoiceAmounts::minor($amounts['total']))throw new DomainException('company_portal_sales_unavailable');}catch(Throwable $e){throw new DomainException('company_portal_sales_unavailable');}
    }
    private static function permission(string $kind):int{return match($kind){'estimates'=>2,'proposals'=>4,default=>throw new DomainException('company_portal_sales_unavailable')};}
    private function tx(callable $operation,array $extra=[]){CompanySalesSchema::assertValid($this->db,$this->p);$tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','issuers_clients','contacts','clients','contact_permissions','company_sales_documents','issuers_audit','options'];if($this->db->table_exists($this->p.'company_custom_fields')&&$this->db->table_exists($this->p.'company_custom_field_values'))$tables=array_merge($tables,['company_custom_fields','company_custom_field_values']);if($this->db->table_exists($this->p.'company_sales_revisions'))$tables[]='company_sales_revisions';if($this->db->table_exists($this->p.'company_estimate_signatures'))$tables[]='company_estimate_signatures';return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($tables,$extra))),$operation);}
    /** Published definition/value pairs only; caller must validate the contact and source. */
    private function publishedEstimateFields(int $issuer,int $id):array
    {
        if(!$this->db->table_exists($this->p.'company_custom_fields')||!$this->db->table_exists($this->p.'company_custom_field_values'))return [];
        require_once __DIR__.'/NativeCompanyCustomFields.php';NativeCompanyCustomFieldSchema::assertValid($this->db,$this->p);
        $sql="SELECT d.id,d.definition_json,d.revision,v.value FROM {$this->p}company_custom_fields d LEFT JOIN {$this->p}company_custom_field_values v ON v.issuer_id=d.issuer_id AND v.field_source_kind='company_custom_field' AND v.field_id=d.id AND v.source_kind='company_estimate' AND v.source_id=? WHERE d.issuer_id=? AND d.fieldto='estimate' ORDER BY d.id";
        $rows=$this->rows($sql,[$id,$issuer]);$out=[];
        foreach($rows as$row){$def=json_decode($row['definition_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($def)||NativeCompanyCustomFields::definition($def)!==$def||$def['fieldto']!=='estimate'||(int)$row['revision']<1)throw new RuntimeException('native_custom_field_corrupt');if((int)$def['active']===1&&(int)$def['only_admin']===0&&(int)$def['show_on_client_portal']===1&&$row['value']!==null)$out[]=['name'=>$def['name'],'value'=>(string)$row['value'],'type'=>$def['type'],'order'=>(int)$def['field_order'],'show_on_pdf'=>(int)$def['show_on_pdf']];}
        usort($out,static fn($a,$b)=>[$a['order'],$a['name']]<=>[$b['order'],$b['name']]);if($rows!==$this->rows($sql,[$id,$issuer]))throw new DomainException('company_portal_sales_unavailable');return $out;
    }
    /** Bind system conversion to the exact accepted contact receipt, not a caller-chosen staff actor. */
    public function autoConversionReferenceInOwned(array $session,int $issuer,int $id,string $requestKey):array
    {
        $tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','contact_permissions','company_sales_documents','issuers_audit'];if($this->db->table_exists($this->p.'company_sales_revisions'))$tables[]='company_sales_revisions';if($this->db->table_exists($this->p.'company_estimate_signatures'))$tables[]='company_estimate_signatures';IssuerTransaction::assertOwned($this->db,$this->p,$tables);
        $who=$this->identity($session,$issuer,'estimates');if(!$who||!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('native_estimate_auto_convert_denied');
        $row=$this->rows("SELECT * FROM {$this->p}company_sales_documents WHERE issuer_id=? AND client_id=? AND id=? AND kind='estimates' FOR UPDATE",[$issuer,$who['client_id'],$id])[0]??null;if(!$row||!in_array($row['state'],['accepted','converting','converted'],true))throw new DomainException('native_estimate_auto_convert_denied');
        $payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($payload)||(int)($payload['client_id']??0)!==(int)$who['client_id']||!hash_equals($row['input_hash'],hash('sha256',json_encode([$row['kind'],$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))))throw new DomainException('native_estimate_auto_convert_denied');self::assertAmount($row,$payload);CompanySalesPreferences::context($row['context_json'],$row['origin_hash'],$issuer,(int)$who['client_id']);
        $records=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_estimate_portal_decision' ORDER BY id LIMIT 1001",[$issuer,$id]);if(count($records)>1000)throw new DomainException('native_estimate_auto_convert_denied');$matches=[];foreach($records as$record){$meta=json_decode($record['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(($meta['request_key']??null)===$requestKey)$matches[]=$meta;}
        $meta=$matches[0]??null;if(count($matches)!==1||!is_array($meta)||($meta['decision']??null)!=='accepted'||($meta['contact_id']??null)!==(int)$who['contact_id']||($meta['client_id']??null)!==(int)$who['client_id']||!is_bool($meta['auto_convert']??null)||!is_string($meta['revision']??null)||!hash_equals($this->publicationRevision($row),$meta['revision']))throw new DomainException('native_estimate_auto_convert_denied');
        if(($meta['signature_proof']??'')!==''){$visibleRow=$row;$visibleRow['state']='accepted';if($this->signatureProjection($issuer,$visibleRow)===null)throw new DomainException('native_estimate_auto_convert_denied');}
        if(!$this->identity($session,$issuer,'estimates'))throw new DomainException('native_estimate_auto_convert_denied');return ['actor_id'=>IssuerPolicy::id($row['actor_id']),'contact_id'=>(int)$who['contact_id'],'client_id'=>(int)$who['client_id'],'revision'=>$meta['revision'],'requested'=>$meta['auto_convert']];
    }
    private function identity(array $session,int $issuer,string $kind):?array{$who=$this->portal->invoiceIdentity($session,$issuer,true);if(!$who)return null;$rows=$this->rows('SELECT id FROM '.$this->p.'contact_permissions WHERE userid=? AND permission_id=? FOR UPDATE',[$who['contact_id'],self::permission($kind)]);return count($rows)===1?$who:null;}
    private function owned(array $session,int $issuer,int $id):array
    {
        $ref=$this->rows("SELECT kind FROM ".$this->p."company_sales_documents WHERE issuer_id=? AND id=? FOR UPDATE",[$issuer,$id])[0]??null;if(!$ref)throw new DomainException('company_portal_sales_unavailable');$who=$this->identity($session,$issuer,$ref['kind']);if(!$who)throw new DomainException('company_portal_sales_unavailable');$r=$this->rows("SELECT * FROM ".$this->p."company_sales_documents WHERE issuer_id=? AND client_id=? AND id=? AND kind=? FOR UPDATE",[$issuer,$who['client_id'],$id,$ref['kind']])[0]??null;if(!$r||!$this->visible($issuer,$r))throw new DomainException('company_portal_sales_unavailable');return [$r,$who];
    }
    /** Share the same contact/publication proof only inside an already owned transaction. */
    public function referenceInOwned(array $session,int $issuer,int $id):array
    {
        $tables=['issuers_lock','issuers_registry','issuers_installation_identity','issuers_clients','contacts','clients','contact_permissions','company_sales_documents','issuers_audit'];if($this->db->table_exists($this->p.'company_sales_revisions'))$tables[]='company_sales_revisions';IssuerTransaction::assertOwned($this->db,$this->p,$tables);
        [$row,$who]=$this->owned($session,$issuer,$id);return ['issuer_id'=>$issuer,'id'=>$id,'kind'=>$row['kind'],'state'=>$row['state'],'client_id'=>(int)$row['client_id'],'contact_id'=>(int)$who['contact_id']];
    }
    private function publicationRevision(array $row):string
    {
        $history=null;if($this->db->table_exists($this->p.'company_sales_revisions')){$history=$this->rows('SELECT revision_no,record_hash FROM '.$this->p.'company_sales_revisions WHERE issuer_id=? AND document_id=? ORDER BY revision_no DESC LIMIT 1',[$row['issuer_id']??0,$row['id']])[0]??null;}
        return hash('sha256',json_encode([(int)$row['id'],$row['kind'],(int)$row['client_id'],(int)$row['number'],$row['input_hash'],$row['origin_hash'],$history],JSON_THROW_ON_ERROR));
    }
    private function visible(int $issuer,array $row):bool
    {
        if(in_array($row['state'],['accepted','converting','converted'],true))return true;
        if($row['kind']!=='estimates'||!in_array($row['state'],['open','declined'],true))return false;
        $records=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_estimate_published' ORDER BY id DESC LIMIT 1",[$issuer,$row['id']]);
        if(count($records)!==1)return false;
        try{$meta=json_decode($records[0]['metadata_json'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_portal_sales_unavailable');}
        return is_array($meta)&&($meta['issuer']??null)===$issuer&&($meta['estimate']??null)===(int)$row['id']&&is_string($meta['revision']??null)&&hash_equals($this->publicationRevision($row),$meta['revision']);
    }
    private function staffEstimate(int $issuer,int $actor,int $id):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'estimates','view')||!$acl->can($issuer,$actor,'estimates','edit'))throw new DomainException('company_portal_sales_unavailable');
        $row=$this->rows("SELECT * FROM {$this->p}company_sales_documents WHERE issuer_id=? AND id=? AND kind='estimates' FOR UPDATE",[$issuer,$id])[0]??null;
        if(!$row||$row['state']!=='open')throw new DomainException('company_portal_sales_unavailable');
        $payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($payload)||(int)($payload['client_id']??0)!==(int)$row['client_id']||!hash_equals($row['input_hash'],hash('sha256',json_encode([$row['kind'],$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))))throw new DomainException('company_portal_sales_unavailable');
        self::assertAmount($row,$payload);CompanySalesPreferences::context($row['context_json'],$row['origin_hash'],$issuer,(int)$row['client_id']);return$row;
    }
    /** Review gives the exact document revision which may be explicitly published. */
    public function publicationReview(int $issuer,int $actor,int $id):array
    {return $this->tx(function()use($issuer,$actor,$id){$row=$this->staffEstimate($issuer,$actor,$id);return ['revision'=>$this->publicationRevision($row),'published'=>$this->visible($issuer,$row)];});}
    public function publish(int $issuer,int $actor,int $id,string $revision):void
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_portal_sales_unavailable');
        $this->tx(function()use($issuer,$actor,$id,$revision){$row=$this->staffEstimate($issuer,$actor,$id);if(!hash_equals($this->publicationRevision($row),$revision))throw new DomainException('company_sales_stale');if($this->visible($issuer,$row))return;
            $meta=json_encode(['issuer'=>$issuer,'estimate'=>$id,'revision'=>$revision],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,"INSERT INTO {$this->p}issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)",[$actor,'company_estimate_published',$issuer,$id,gmdate('Y-m-d H:i:s'),$meta]);
            $saved=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE id=? AND issuer_id=? AND subject_id=?",[(int)$this->db->insert_id(),$issuer,$id]);if(count($saved)!==1||$saved[0]['metadata_json']!==$meta||!$this->visible($issuer,$this->staffEstimate($issuer,$actor,$id)))throw new RuntimeException('issuer_portal_write_unconfirmed');
        });
    }
    private static function signatureInput(?array $signature):?array
    {
        if($signature===null)return null;
        if(array_diff(array_keys($signature),['image','firstname','lastname','email','title','ip'])||array_diff(['image','firstname','lastname','email','title','ip'],array_keys($signature)))throw new DomainException('native_estimate_signature_invalid');
        foreach(['firstname','lastname','email','title'] as$key)if(!is_string($signature[$key])||trim($signature[$key])!==$signature[$key]||strlen($signature[$key])>191||preg_match('//u',$signature[$key])!==1||preg_match('/[\x00-\x1f\x7f]/',$signature[$key])||($key!=='title'&&$signature[$key]===''))throw new DomainException('native_estimate_signature_invalid');
        if(filter_var($signature['email'],FILTER_VALIDATE_EMAIL)===false||!is_string($signature['ip'])||filter_var($signature['ip'],FILTER_VALIDATE_IP)===false||!is_string($signature['image'])||strlen($signature['image'])>700000||!str_starts_with($signature['image'],'data:image/png;base64,'))throw new DomainException('native_estimate_signature_invalid');
        $raw=base64_decode(substr($signature['image'],22),true);if($raw===false)throw new DomainException('native_estimate_signature_invalid');$info=@getimagesizefromstring($raw);if(($info['mime']??null)!=='image/png')throw new DomainException('native_estimate_signature_invalid');
        require_once __DIR__.'/CompanyAssets.php';$image=CompanyAssets::normalize($raw);$bytes=base64_decode(substr($image,22),true);if($bytes===false)throw new DomainException('native_estimate_signature_invalid');$hash=hash('sha256',$bytes);
        return ['firstname'=>$signature['firstname'],'lastname'=>$signature['lastname'],'email'=>$signature['email'],'title'=>$signature['title'],'ip'=>$signature['ip'],'bytes'=>$bytes,'hash'=>$hash,'proof'=>hash('sha256',json_encode([$signature['firstname'],$signature['lastname'],$signature['email'],$signature['title'],$signature['ip'],$hash],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))];
    }
    /** Historic signature is disclosed only after the calling document authorization. */
    private function signatureProjection(int $issuer,array $row):?array
    {
        if($row['kind']!=='estimates'||!in_array($row['state'],['accepted','converting','converted'],true))return null;
        $hasTable=$this->db->table_exists($this->p.'company_estimate_signatures');
        $events=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_estimate_portal_decision' ORDER BY id LIMIT 1001",[$issuer,$row['id']]);if(count($events)>1000)throw new DomainException('company_portal_sales_unavailable');
        $proofs=[];foreach($events as$event){$meta=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($meta))throw new DomainException('company_portal_sales_unavailable');if(($meta['signature_proof']??'')!=='')$proofs[]=$meta;}
        if(!$hasTable){if($proofs!==[])throw new DomainException('native_estimate_signature_unavailable');return null;}
        require_once __DIR__.'/NativeCompanyEstimateSignatureSchema.php';NativeCompanyEstimateSignatureSchema::assertValid($this->db,$this->p);
        $stored=$this->rows('SELECT * FROM '.$this->p.NativeCompanyEstimateSignatureSchema::TABLE.' WHERE issuer_id=? AND document_id=?',[$issuer,$row['id']]);if($stored===[]){if($proofs!==[])throw new DomainException('native_estimate_signature_unavailable');return null;}if(count($stored)!==1||count($proofs)!==1)throw new DomainException('native_estimate_signature_conflict');
        $signature=$stored[0];$proof=$proofs[0];$hash=hash('sha256',$signature['content']);$commitment=hash('sha256',json_encode([$signature['signer_firstname'],$signature['signer_lastname'],$signature['signer_email'],$signature['signer_title'],$signature['signer_ip'],$hash],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        $image=@getimagesizefromstring($signature['content']);if((int)$signature['client_id']!==(int)$row['client_id']||(int)$signature['byte_size']!==strlen($signature['content'])||!hash_equals($signature['content_hash'],$hash)||($image['mime']??null)!=='image/png'||($proof['request_key']??null)!==$signature['request_key']||($proof['contact_id']??null)!==(int)$signature['contact_id']||($proof['client_id']??null)!==(int)$signature['client_id']||($proof['decision']??null)!=='accepted'||($proof['revision']??null)!==$signature['revision']||!hash_equals($this->publicationRevision($row),$signature['revision'])||($proof['signature_proof']??null)!==$commitment)throw new DomainException('native_estimate_signature_conflict');
        return ['firstname'=>$signature['signer_firstname'],'lastname'=>$signature['signer_lastname'],'email'=>$signature['signer_email'],'title'=>$signature['signer_title'],'ip'=>$signature['signer_ip'],'date'=>$signature['created_at'],'image'=>'data:image/png;base64,'.base64_encode($signature['content'])];
    }
    private function signatureRequired(int $issuer):bool
    {
        $settings=new CompanySettingsStorage($this->db,$this->p);$value=$settings->exists($issuer,'estimate_accept_identity_confirmation')?$settings->get($issuer,'estimate_accept_identity_confirmation'):'1';
        if(!in_array($value,['0','1'],true))throw new DomainException('native_estimate_signature_invalid');return $value==='1';
    }
    private function autoConvertRequired(int $issuer):bool
    {
        $settings=new CompanySettingsStorage($this->db,$this->p);$key='estimate_auto_convert_to_invoice_on_client_accept';$value=$settings->exists($issuer,$key)?$settings->get($issuer,$key):'1';
        if(!in_array($value,['0','1'],true))throw new DomainException('native_estimate_auto_convert_invalid');return $value==='1';
    }
    private function verifySignatureReceipt(int $issuer,int $id,array $who,string $revision,string $requestKey,array $signature):void
    {
        $stored=$this->rows('SELECT * FROM '.$this->p.NativeCompanyEstimateSignatureSchema::TABLE.' WHERE request_key=? AND issuer_id=? AND document_id=?',[$requestKey,$issuer,$id])[0]??null;
        if(!$stored||(int)$stored['contact_id']!==(int)$who['contact_id']||(int)$stored['client_id']!==(int)$who['client_id']||$stored['revision']!==$revision||$stored['signer_firstname']!==$signature['firstname']||$stored['signer_lastname']!==$signature['lastname']||$stored['signer_email']!==$signature['email']||$stored['signer_title']!==$signature['title']||$stored['signer_ip']!==$signature['ip']||(int)$stored['byte_size']!==strlen($signature['bytes'])||!hash_equals($stored['content_hash'],$signature['hash'])||!hash_equals($signature['hash'],hash('sha256',$stored['content'])))throw new DomainException('native_estimate_signature_conflict');
    }
    /** Proof is live only while this exact contact decision transaction is committing. */
    public function assertDecisionInOwned($db,string $prefix,int $issuer,int $audit):void
    {
        $frame=$this->activeDecision;if($db!==$this->db||$prefix!==$this->p||!$frame||$frame['issuer']!==$issuer||$frame['audit']!==$audit)throw new DomainException('native_estimate_notice_denied');
        $who=$this->identity($frame['session'],$issuer,'estimates');if(!$who||(int)$who['contact_id']!==$frame['contact']||(int)$who['client_id']!==$frame['client'])throw new DomainException('native_estimate_notice_denied');
        $rows=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND id=? AND event='company_estimate_portal_decision'",[$issuer,$frame['document'],$audit]);if(count($rows)!==1||$rows[0]['metadata_json']!==$frame['metadata'])throw new DomainException('native_estimate_notice_denied');
    }
    /** Contact decision is bound to an explicit publication, current identity and revision. */
    public function decide(array $session,int $issuer,int $id,string $decision,string $revision,string $requestKey,?array $signature=null):void
    {
        if(!in_array($decision,['accepted','declined'],true)||!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('company_portal_sales_unavailable');$signature=self::signatureInput($signature);if($decision==='declined'&&$signature!==null)throw new DomainException('native_estimate_signature_invalid');if($signature!==null){require_once __DIR__.'/NativeCompanyEstimateSignatureSchema.php';NativeCompanyEstimateSignatureSchema::assertValid($this->db,$this->p);}require_once __DIR__.'/NativeCompanyEstimateDecisionNotice.php';NativeCompanyHeaderSchema::assertValid($this->db,$this->p);
        $this->tx(function()use($session,$issuer,$id,$decision,$revision,$requestKey,$signature){[$row,$who]=$this->owned($session,$issuer,$id);if($row['kind']!=='estimates')throw new DomainException('company_portal_sales_unavailable');$payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($payload)||(int)($payload['client_id']??0)!==(int)$who['client_id']||!hash_equals($row['input_hash'],hash('sha256',json_encode([$row['kind'],$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))))throw new DomainException('company_portal_sales_unavailable');self::assertAmount($row,$payload);CompanySalesPreferences::context($row['context_json'],$row['origin_hash'],$issuer,(int)$who['client_id']);
            $records=$this->rows("SELECT id,metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_estimate_portal_decision' ORDER BY id",[$issuer,$id]);
            foreach($records as$record){$saved=json_decode($record['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(($saved['request_key']??null)===$requestKey){if(($saved['contact_id']??null)!==(int)$who['contact_id']||($saved['decision']??null)!==$decision||($saved['revision']??null)!==$revision||($saved['signature_proof']??'')!==($signature['proof']??'')||!hash_equals($this->publicationRevision($row),$revision)||!($row['state']===$decision||$decision==='accepted'&&in_array($row['state'],['converting','converted'],true)))throw new DomainException('company_portal_sales_unavailable');if($signature!==null)$this->verifySignatureReceipt($issuer,$id,$who,$revision,$requestKey,$signature);$this->activeDecision=['issuer'=>$issuer,'document'=>$id,'audit'=>(int)$record['id'],'session'=>$session,'contact'=>(int)$who['contact_id'],'client'=>(int)$who['client_id'],'metadata'=>$record['metadata_json']];try{(new NativeCompanyEstimateDecisionNotice($this->db,$this->p))->queueInOwned($issuer,(int)$record['id'],$this);}finally{$this->activeDecision=null;}return;}}
            if($row['state']!=='open'||!hash_equals($this->publicationRevision($row),$revision))throw new DomainException('company_sales_stale');
            if(isset($payload['due_date'])&&$payload['due_date']<date('Y-m-d'))throw new DomainException('company_estimate_expired');$required=$decision==='accepted'?$this->signatureRequired($issuer):false;$autoConvert=$decision==='accepted'?$this->autoConvertRequired($issuer):false;if($required&&$signature===null)throw new DomainException('native_estimate_signature_required');
            if($signature!==null){$fields=['request_key'=>$requestKey,'issuer_id'=>$issuer,'document_id'=>$id,'contact_id'=>(int)$who['contact_id'],'client_id'=>(int)$who['client_id'],'revision'=>$revision,'signer_firstname'=>$signature['firstname'],'signer_lastname'=>$signature['lastname'],'signer_email'=>$signature['email'],'signer_title'=>$signature['title'],'signer_ip'=>$signature['ip'],'content_hash'=>$signature['hash'],'byte_size'=>strlen($signature['bytes']),'content'=>$signature['bytes'],'created_at'=>gmdate('Y-m-d H:i:s')];$old=$this->rows('SELECT request_key FROM '.$this->p.NativeCompanyEstimateSignatureSchema::TABLE.' WHERE request_key=? OR (issuer_id=? AND document_id=?) FOR UPDATE',[$requestKey,$issuer,$id]);if($old)throw new DomainException('native_estimate_signature_conflict');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyEstimateSignatureSchema::TABLE.' (`'.implode('`,`',array_keys($fields)).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));$stored=$this->rows('SELECT * FROM '.$this->p.NativeCompanyEstimateSignatureSchema::TABLE.' WHERE request_key=? AND issuer_id=? AND document_id=?',[$requestKey,$issuer,$id])[0]??[];foreach($fields as$key=>$value)if((string)($stored[$key]??'')!==(string)$value)throw new RuntimeException('issuer_portal_write_unconfirmed');if(!hash_equals($signature['hash'],hash('sha256',$stored['content']??'')))throw new RuntimeException('issuer_portal_write_unconfirmed');}
            $meta=json_encode(['contact_id'=>(int)$who['contact_id'],'client_id'=>(int)$who['client_id'],'decision'=>$decision,'revision'=>$revision,'request_key'=>$requestKey,'signature_proof'=>$signature['proof']??'','auto_convert'=>$autoConvert],JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,"UPDATE {$this->p}company_sales_documents SET state=? WHERE issuer_id=? AND id=? AND state='open' AND input_hash=?",[$decision,$issuer,$id,$row['input_hash']]);IssuerStorage::query($this->db,"INSERT INTO {$this->p}issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)",[0,'company_estimate_portal_decision',$issuer,$id,gmdate('Y-m-d H:i:s'),$meta]);$auditId=(int)$this->db->insert_id();
            $saved=$this->rows("SELECT state,input_hash FROM {$this->p}company_sales_documents WHERE issuer_id=? AND id=?",[$issuer,$id])[0]??null;$audit=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE id=? AND issuer_id=? AND subject_id=?",[$auditId,$issuer,$id]);if(!$saved||$saved['state']!==$decision||$saved['input_hash']!==$row['input_hash']||count($audit)!==1||$audit[0]['metadata_json']!==$meta)throw new RuntimeException('issuer_portal_write_unconfirmed');if(!$this->identity($session,$issuer,'estimates'))throw new DomainException('company_portal_sales_unavailable');if($signature!==null)$this->verifySignatureReceipt($issuer,$id,$who,$revision,$requestKey,$signature);if($decision==='accepted'&&($required!==$this->signatureRequired($issuer)||$autoConvert!==$this->autoConvertRequired($issuer)))throw new DomainException('company_portal_sales_unavailable');$this->activeDecision=['issuer'=>$issuer,'document'=>$id,'audit'=>$auditId,'session'=>$session,'contact'=>(int)$who['contact_id'],'client'=>(int)$who['client_id'],'metadata'=>$meta];try{(new NativeCompanyEstimateDecisionNotice($this->db,$this->p))->queueInOwned($issuer,$auditId,$this);}finally{$this->activeDecision=null;}
        },array_merge(NativeCompanyNotifications::tables('estimate_decision'),$signature!==null?[NativeCompanyEstimateSignatureSchema::TABLE]:[]));
    }
    /** Return only the exact contact receipt needed to resume an interrupted automatic conversion. */
    public function autoConversionRetry(array $session,$issuer,$id):?array
    {
        $issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);
        return $this->tx(function()use($session,$issuer,$id){
            [$row,$who]=$this->owned($session,$issuer,$id);if($row['kind']!=='estimates'||!in_array($row['state'],['accepted','converting'],true))return null;
            $records=$this->rows("SELECT metadata_json FROM {$this->p}issuers_audit WHERE issuer_id=? AND subject_id=? AND event='company_estimate_portal_decision' ORDER BY id LIMIT 1001",[$issuer,$id]);if(count($records)>1000)throw new DomainException('native_estimate_auto_convert_denied');$matches=[];
            foreach($records as$record){$meta=json_decode($record['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($meta))throw new DomainException('native_estimate_auto_convert_denied');if(($meta['decision']??null)==='accepted'&&($meta['contact_id']??null)===(int)$who['contact_id']&&($meta['client_id']??null)===(int)$who['client_id']&&($meta['auto_convert']??null)===true)$matches[]=$meta;}
            if($matches===[])return null;if(count($matches)!==1)throw new DomainException('native_estimate_auto_convert_denied');$meta=$matches[0];if(!is_string($meta['revision']??null)||!is_string($meta['request_key']??null)||!preg_match('/^[a-f0-9]{64}$/D',$meta['revision'])||!preg_match('/^[a-f0-9]{64}$/D',$meta['request_key'])||!hash_equals($this->publicationRevision($row),$meta['revision']))throw new DomainException('native_estimate_auto_convert_denied');$this->owned($session,$issuer,$id);return ['revision'=>$meta['revision'],'request_key'=>$meta['request_key']];
        });
    }
    private function projectListRow(array $row,int $issuer,int $client):array
    {
        $context=CompanySalesPreferences::context($row['context_json'],$row['origin_hash'],$issuer,$client);try{$payload=json_decode($row['payload_json'],true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){throw new DomainException('company_portal_sales_unavailable');}if(!is_array($payload)||!hash_equals($row['input_hash'],hash('sha256',json_encode([$row['kind'],$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))||(int)($payload['client_id']??0)!==$client)throw new DomainException('company_portal_sales_unavailable');self::assertAmount($row,$payload);$row['date']=self::safeDate($payload['date']??null);$row['due_date']=self::safeDate($payload['due_date']??null,true);if($row['kind']==='estimates')$row['status']=$row['state']==='declined'?'3':(in_array($row['state'],['accepted','converting','converted'],true)?'4':($row['due_date']!==null&&$row['due_date']<date('Y-m-d')?'5':'2'));$row['formatted_number']=CompanySalesPreferences::presentation($row['kind'],$row['number'],$context,'',$payload['date']??null)['formatted_number'];$row['link_hash']=$this->portal->salesLinkHash($issuer,(int)$row['id'],$row['origin_hash']);unset($row['context_json'],$row['origin_hash'],$row['payload_json'],$row['input_hash']);return $row;
    }
    private function listBatch(int $issuer,int $client,string $kind,?int $before):array
    {
        $sql="SELECT id,issuer_id,kind,number,state,total,client_id,created_at,context_json,origin_hash,payload_json,input_hash FROM {$this->p}company_sales_documents WHERE issuer_id=? AND client_id=? AND kind=? AND state IN ('open','accepted','declined','converting','converted')";$args=[$issuer,$client,$kind];if($before!==null){$sql.=' AND id<?';$args[]=$before;}return $this->rows($sql.' ORDER BY id DESC LIMIT 200',$args);
    }
    /** Exact count scans bounded batches and validates each visible source, without a row cap. */
    public function countVisible(array $session,$issuer,string $kind,?string $status=null):?int
    {
        $issuer=IssuerPolicy::id($issuer);self::permission($kind);if($status!==null&&($kind!=='estimates'||!in_array($status,['1','2','3','4','5'],true)))throw new DomainException('company_portal_sales_limit');
        return $this->tx(function()use($session,$issuer,$kind,$status){$who=$this->identity($session,$issuer,$kind);if(!$who)return null;$cursor=null;$count=0;do{$batch=$this->listBatch($issuer,(int)$who['client_id'],$kind,$cursor);foreach($batch as$row){if(!$this->visible($issuer,$row))continue;$projected=$this->projectListRow($row,$issuer,(int)$who['client_id']);if($status===null||$projected['status']===$status)$count++;}if($batch)$cursor=(int)$batch[count($batch)-1]['id'];}while(count($batch)===200);if(!$this->identity($session,$issuer,$kind))throw new DomainException('company_portal_sales_unavailable');return$count;});
    }
    /** Compatibility list for callers that request the first 100 documents. */
    public function page(array $session,$issuer,string $kind):?array
    {
        $items=[];$cursor=null;do{$page=$this->pageCursor($session,$issuer,$kind,$cursor,100-count($items));if($page===null)return null;$items=array_merge($items,$page['items']);$cursor=$page['next'];}while($cursor!==null&&count($items)<100);return $items;
    }
    /** Bounded scan; next can be non-null even when this page contains no visible items. */
    public function pageCursor(array $session,$issuer,string $kind,$before=null,int $limit=50,?string $status=null):?array
    {
        $issuer=IssuerPolicy::id($issuer);self::permission($kind);if($before!==null)$before=IssuerPolicy::id($before);if($limit<1||$limit>100||($status!==null&&($kind!=='estimates'||!in_array($status,['1','2','3','4','5'],true))))throw new DomainException('company_portal_sales_limit');
        return $this->tx(function()use($session,$issuer,$kind,$before,$limit,$status){
            $who=$this->identity($session,$issuer,$kind);if(!$who)return null;
            $batch=$this->listBatch($issuer,(int)$who['client_id'],$kind,$before);$items=[];$last=null;$used=0;
            foreach($batch as$row){$used++;$last=(int)$row['id'];if(!$this->visible($issuer,$row))continue;$projected=$this->projectListRow($row,$issuer,(int)$who['client_id']);if($status!==null&&$projected['status']!==$status)continue;$items[]=$projected;if(count($items)===$limit)break;}
            if(!$this->identity($session,$issuer,$kind))throw new DomainException('company_portal_sales_unavailable');$more=$last!==null&&($used<count($batch)||count($batch)===200);return ['items'=>$items,'next'=>$more?$last:null];
        });
    }
    public function document(array $session,$issuer,$id):array{$issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);return $this->tx(function()use($session,$issuer,$id){[$r,$who]=$this->owned($session,$issuer,$id);$payload=json_decode($r['payload_json'],true,32,JSON_THROW_ON_ERROR);$context=json_decode($r['context_json'],true,32,JSON_THROW_ON_ERROR);if(!is_array($payload)||!is_array($context)||!hash_equals($r['origin_hash'],hash('sha256',json_encode($context,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))||!hash_equals($r['input_hash'],hash('sha256',json_encode([$r['kind'],$payload],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)))||(int)($context['issuer_id']??0)!==$issuer||(int)($context['recipient']['client_id']??0)!==$who['client_id']||(int)($payload['client_id']??0)!==$who['client_id']||(int)($payload['currency']??0)!==(int)$r['currency'])throw new DomainException('company_portal_sales_unavailable');self::assertAmount($r,$payload);self::safeDate($payload['date']??null);self::safeDate($payload['due_date']??null,true);$published=$r['kind']==='estimates'?$this->publishedEstimateFields($issuer,$id):[];$signature=$this->signatureProjection($issuer,$r);$this->owned($session,$issuer,$id);if(isset($payload['native_fields'])&&is_array($payload['native_fields']))unset($payload['native_fields']['adminnote'],$payload['native_fields']['sale_agent']);unset($payload['native_project'],$payload['native_tags'],$payload['native_custom_fields'],$context['native_invoice_defaults']);return ['id'=>(int)$r['id'],'number'=>(int)$r['number'],'kind'=>$r['kind'],'state'=>$r['state'],'total'=>$r['total'],'payload'=>$payload,'context'=>$context,'custom_fields'=>$published,'signature'=>$signature,'link_hash'=>$this->portal->salesLinkHash($issuer,$id,$r['origin_hash']),'publication_revision'=>$this->publicationRevision($r),'signature_required'=>$r['kind']==='estimates'?$this->signatureRequired($issuer):false];});}
    public function confirmDownload(array $session,$issuer,$id,string $hash):void{$issuer=IssuerPolicy::id($issuer);$id=IssuerPolicy::id($id);if(!preg_match('/^[a-f0-9]{64}$/D',$hash))throw new DomainException('company_portal_sales_unavailable');$this->tx(function()use($session,$issuer,$id,$hash){[$r,$who]=$this->owned($session,$issuer,$id);$metadata=json_encode(['client_id'=>$who['client_id'],'contact_id'=>$who['contact_id'],'kind'=>$r['kind'],'sha256'=>$hash,'receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);$event='company_portal_sales_pdf_downloaded';IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(0,?,?,?,?,?)',[$event,$issuer,$id,date('Y-m-d H:i:s'),$metadata]);$audit=(int)$this->db->insert_id();if(!$audit||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND subject_id=? AND metadata_json=?',[$audit,$issuer,$id,$metadata]))throw new RuntimeException('issuer_portal_write_unconfirmed');$this->owned($session,$issuer,$id);});}
}
