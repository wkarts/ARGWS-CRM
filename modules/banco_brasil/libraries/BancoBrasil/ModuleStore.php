<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Agreement.php';
require_once __DIR__.'/Cnab240ReturnParser.php';
require_once __DIR__.'/Cnab400ReturnParser.php';
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Transaction.php';
require_once __DIR__.'/ReturnOriginalArchive.php';
require_once __DIR__.'/ReturnRejections.php';

/** Call only after actor/issuer capability checks; credentials never enter this store; original bank files stay in scoped private storage. */
final class ModuleStore
{
    public function __construct(private $db,private string $prefix)
    {
        if(!preg_match('/^[A-Za-z0-9_]*$/D',$prefix))throw new \InvalidArgumentException('bb_prefix_invalid');
    }
    public function connections(int $issuerId): array
    {
        return Transaction::quiet($this->db,function()use($issuerId){
        return self::rows($this->db->where('issuer_id',$issuerId)->order_by('id','DESC')->get($this->prefix.'bb_connections'),'result_array');
            });
    }
    public function connection(int $id,int $issuerId): array
    {
        return Transaction::quiet($this->db,function()use($id,$issuerId){
        $row=self::rows($this->db->where('id',$id)->where('issuer_id',$issuerId)->get($this->prefix.'bb_connections'),'row_array');
        if(!$row)throw new \DomainException('bb_connection_unavailable');
        return $row;
            });
    }
    public static function agreement(array $row): Agreement
    {
        return new Agreement(new Scope((int)$row['issuer_id'],(int)$row['id'],$row['account_id'],$row['environment'],$row['agreement_number']),
            $row['issuer_tax_id'],$row['issuer_name'],$row['variation'],$row['wallet']);
    }
    public function createConnection(int $issuerId,int $actor,array $issuer,array $data): int
    {
        $allowed=['account_id','environment','agreement_number','wallet','variation'];
        if($issuerId<1||$actor<1||(int)($issuer['id']??0)!==$issuerId||array_diff(array_keys($data),$allowed)||array_diff($allowed,array_keys($data)))throw new \InvalidArgumentException('bb_connection_data_invalid');
        foreach($data as $value)if(!is_string($value))throw new \InvalidArgumentException('bb_connection_data_invalid');
        // Legal identity is the authorized issuer snapshot. Never accept it from this form.
        $tax=Fields::taxId((string)$issuer['tax_id']);$name=(string)$issuer['legal_name'];
        if($name===''||strlen($name)>160||preg_match('/[\x00-\x1F\x7F]/',$name))throw new \InvalidArgumentException('bb_issuer_name_invalid');
        new Agreement(new Scope($issuerId,1,$data['account_id'],$data['environment'],$data['agreement_number']),$tax,$name,$data['variation'],$data['wallet']);
        if($data['wallet']!=='17'||!preg_match('/^[0-9]{3}$/D',$data['variation']))throw new \InvalidArgumentException('bb_agreement_profile_unsupported');
        $identity=hash('sha256',json_encode([$issuerId,$data['account_id'],$data['environment'],$data['agreement_number'],$data['wallet'],$data['variation']],JSON_THROW_ON_ERROR));
        $configuration=hash('sha256',json_encode([$identity,$tax,$name],JSON_THROW_ON_ERROR));
        return Transaction::run($this->db,$this->prefix,['bb_connections','bb_audit'],function()use($issuerId,$actor,$data,$tax,$name,$identity,$configuration){
            $existing=$this->db->query("SELECT id,configuration_hash FROM `{$this->prefix}bb_connections` WHERE identity_hash=? FOR UPDATE",[$identity]);
            if(!$existing)throw new \RuntimeException('bb_connection_read_failed');
            $row=$existing->row_array();
            if($row){if(!hash_equals($configuration,(string)$row['configuration_hash']))throw new \DomainException('bb_immutable_connection_conflict');return (int)$row['id'];}
            $record=$data+['issuer_id'=>$issuerId,'provider'=>'banco_brasil','issuer_tax_id'=>$tax,'issuer_name'=>$name,
                'identity_hash'=>$identity,'configuration_hash'=>$configuration,'lifecycle'=>'pending_verification','created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')];
            if(!$this->db->insert($this->prefix.'bb_connections',$record))throw new \RuntimeException('bb_connection_write_failed');
            $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('bb_connection_write_failed');
            $this->audit($issuerId,$id,$actor,'connection_registered','connection',$id);
            return $id;
        });
    }
    public function layoutBindings(int $issuerId):array
    {
        return Transaction::quiet($this->db,function()use($issuerId){
        return self::rows($this->db->where('issuer_id',$issuerId)->order_by('id','DESC')->get($this->prefix.'bb_layout_bindings'),'result_array');
            });
    }
    public function layoutBinding(int $id,array $connection):array
    {
        return Transaction::quiet($this->db,function()use($id,$connection){
        if($id<1)throw new \DomainException('bb_layout_selection_required');
        $row=self::rows($this->db->where('id',$id)->where('issuer_id',(int)$connection['issuer_id'])
            ->where('connection_id',(int)$connection['id'])->get($this->prefix.'bb_layout_bindings'),'row_array');
        $a=self::agreement($connection);
        if(!$row||!hash_equals($a->scope->key(),(string)$row['scope_key'])
            ||!hash_equals((string)$connection['configuration_hash'],(string)$row['connection_hash'])
            ||!hash_equals(LayoutCatalog::fingerprint($row['layout_id']),(string)$row['profile_hash']))throw new \DomainException('bb_layout_binding_mismatch');
        $catalog=LayoutCatalog::get($row['layout_id']);
        if($row['date_window']!==$catalog['date_window']||$row['declared_currency']!==$catalog['currency']
            ||$row['remittance_source_hash']!==$catalog['remittance_sha256']||$row['return_source_hash']!==$catalog['return_sha256'])throw new \DomainException('bb_layout_binding_mismatch');
        return $row;
            });
    }
    public function registerLayout(array $connection,int $actor,string $layoutId):int
    {
        if($actor<1)throw new \InvalidArgumentException('bb_actor_required');
        $profile=LayoutCatalog::get($layoutId);$a=self::agreement($connection);
        if(in_array($layoutId,[LayoutCatalog::CNAB400,LayoutCatalog::CNAB400_AUX],true))Fields400::account($a);
        return Transaction::run($this->db,$this->prefix,['bb_connections','bb_layout_bindings','bb_audit'],function()use($connection,$actor,$layoutId,$profile,$a){
            $r=$this->db->query("SELECT * FROM `{$this->prefix}bb_connections` WHERE id=? AND issuer_id=? FOR UPDATE",[$a->scope->connectionId,$a->scope->issuerId]);
            $current=$r?$r->row_array():[];
            if(!$current||!hash_equals((string)$connection['configuration_hash'],(string)$current['configuration_hash']))throw new \DomainException('bb_immutable_connection_conflict');
            $currentAgreement=self::agreement($current);$a->scope->assertSame($currentAgreement->scope);
            if($a->taxId!==$currentAgreement->taxId||$a->name!==$currentAgreement->name||$a->variation!==$currentAgreement->variation||$a->wallet!==$currentAgreement->wallet)throw new \DomainException('bb_immutable_connection_conflict');
            $old=$this->db->where('connection_id',$a->scope->connectionId)->where('layout_id',$layoutId)
                ->where('profile_hash',LayoutCatalog::fingerprint($layoutId))->get($this->prefix.'bb_layout_bindings')->row_array();
            if($old){$this->layoutBinding((int)$old['id'],$current);return (int)$old['id'];}
            $record=['issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),
                'layout_id'=>$layoutId,'profile_hash'=>LayoutCatalog::fingerprint($layoutId),'connection_hash'=>$current['configuration_hash'],
                'remittance_source_hash'=>$profile['remittance_sha256'],'return_source_hash'=>$profile['return_sha256'],
                'date_window'=>$profile['date_window'],'declared_currency'=>$profile['currency'],'verification_state'=>'declared_unverified',
                'created_by'=>$actor,'created_at'=>date('Y-m-d H:i:s')];
            if(!$this->db->insert($this->prefix.'bb_layout_bindings',$record))throw new \RuntimeException('bb_layout_write_failed');
            $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('bb_layout_write_failed');
            $this->audit($a->scope->issuerId,$a->scope->connectionId,$actor,'layout_declared','layout_binding',$id);
            return $id;
        });
    }
    private function auxiliaryImportAccess(int$issuer,int$actor,string$cap='import'):void
    {
        if(!in_array($cap,['import','download'],true))throw new \LogicException('bb_archive_capability_invalid');
        require_once APPPATH.'services/issuers/IssuerCore.php';require_once APPPATH.'services/issuers/IssuerResourceAccess.php';require_once __DIR__.'/ModuleRelease.php';
        \IssuerTransaction::assertOwned($this->db,$this->prefix,['issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules']);
        $acl=new \IssuerResourceAccess($this->db,$this->prefix);$acl->lockFinancialIdentity($issuer,$actor);
        $module=$this->db->query("SELECT active,installed_version FROM {$this->prefix}modules WHERE module_name='banco_brasil' FOR UPDATE")->row_array();
        if(!\IssuerCore::ready($this->db,$this->prefix)||(int)($module['active']??0)!==1||($module['installed_version']??null)!==ModuleRelease::VERSION||!$acl->can($issuer,$actor,'banco_brasil','view')||!$acl->can($issuer,$actor,'banco_brasil',$cap))throw new \DomainException('bb_auxiliary_import_access_denied');
    }
    private function confirmAuxiliaryRow(string$table,array$keys,array$expected):void
    {
        if(!in_array($table,['bb_return_files','bb_return_events','bb_return_file_events','bb_connections','bb_layout_bindings'],true))throw new \LogicException('bb_auxiliary_table_invalid');
        $where=[];foreach(array_keys($keys)as$key){if(!preg_match('/^[a-z_]+$/D',$key))throw new \LogicException('bb_auxiliary_key_invalid');$where[]='`'.$key.'`=?';}
        $rows=$this->db->query('SELECT * FROM `'.$this->prefix.$table.'` WHERE '.implode(' AND ',$where),array_values($keys))->result_array();
        if(count($rows)!==1)throw new \RuntimeException('bb_auxiliary_write_unconfirmed');
        foreach($expected as$key=>$value)if(!array_key_exists($key,$rows[0])||(string)$rows[0][$key]!== (string)$value)throw new \RuntimeException('bb_auxiliary_write_unconfirmed');
    }
    private function confirmAuxiliarySet(int$file,int$count,array$newIds):void
    {
        $links=$this->db->query("SELECT return_event_id FROM {$this->prefix}bb_return_file_events WHERE return_file_id=?",[$file])->result_array();
        $created=$this->db->query("SELECT id FROM {$this->prefix}bb_return_events WHERE return_file_id=?",[$file])->result_array();
        $actual=array_map(static fn($row)=>(int)$row['id'],$created);sort($actual);sort($newIds);
        if(count($links)!==$count||$actual!==$newIds)throw new \RuntimeException('bb_auxiliary_event_set_unconfirmed');
    }
    /** The new raw envelope never invents/adopts a main profile for an old canonical event. */
    private function main400Binding(array$connection):array
    {
        $row=$this->db->where('connection_id',(int)$connection['id'])->where('layout_id',LayoutCatalog::CNAB400)->where('profile_hash',LayoutCatalog::fingerprint(LayoutCatalog::CNAB400))->get($this->prefix.'bb_layout_bindings')->row_array();
        if(!$row)throw new \DomainException('bb400_main_profile_required');
        return $this->layoutBinding((int)$row['id'],$connection);
    }
    /** Native upload orchestration; parser refusal is retained separately from accepted files. */
    public function receiveReturn(array $connection,int $actor,string $bytes,int $layoutBindingId):array
    {
        try{return $this->importReturn($connection,$actor,$bytes,$layoutBindingId,true);}
        catch(ReturnValidationFailure $failure){return(new ReturnRejections($this->db,$this->prefix))->retain($connection,$actor,$bytes,$layoutBindingId,$failure);}
    }
    public function importReturn(array $connection,int $actor,string $bytes,int $layoutBindingId,bool $nativeAuthority=false): array
    {
        if($actor<1)throw new \InvalidArgumentException('bb_actor_required');
        $a=self::agreement($connection);$binding=$this->layoutBinding($layoutBindingId,$connection);
        $auxEnvelope=$binding['layout_id']===LayoutCatalog::CNAB400_AUX;
        $parentBinding=$auxEnvelope?$this->main400Binding($connection):$binding;
        $parser=in_array($binding['layout_id'],[LayoutCatalog::CNAB400,LayoutCatalog::CNAB400_AUX],true)?new Cnab400ReturnParser():new Cnab240ReturnParser();
        try{
            $width=$parser instanceof Cnab400ReturnParser?400:240;
            if(substr_count($bytes,"\r\n")>intdiv(16*1024*1024,$width+2))throw new \UnexpectedValueException('bb_return_record_limit');
            $parsed=$parser instanceof Cnab400ReturnParser?$parser->parse($a,$bytes,$binding['layout_id']):$parser->parse($a,$bytes);
        }catch(\UnexpectedValueException|\InvalidArgumentException $error){throw ReturnValidationFailure::from($error);}
        $tables=['bb_connections','bb_layout_bindings','bb_return_files','bb_return_originals','bb_return_events','bb_return_file_events','bb_audit'];
        $provenance=['declared_environment'=>$a->scope->environment,'environment_evidence'=>$parsed['environment_evidence']??'lot_marker_matches_declared_profile',
            'authenticity'=>'not_verified','issuer_tax_id_evidence'=>$parsed['issuer_tax_id_evidence']??'matches_file',
            'date_window'=>$binding['date_window'],'trailer_raw_totals'=>$parsed['trailer_raw_totals']??null,
            'trailer_total_interpretation'=>$parsed['trailer_total_interpretation']??null];
        if($auxEnvelope){$provenance['auxiliary_contract']=Cnab400Auxiliary::CONTRACT;$provenance['parent_layout_binding_id']=(int)$parentBinding['id'];$provenance['return_only']=true;}
        $provenanceJson=json_encode($provenance,JSON_THROW_ON_ERROR);
        $guarded=$auxEnvelope||$nativeAuthority;
        $work=function()use($a,$parsed,$actor,$binding,$parentBinding,$auxEnvelope,$provenanceJson,$bytes,$guarded){
            if($guarded)$this->auxiliaryImportAccess($a->scope->issuerId,$actor);
            $locked=$this->db->query("SELECT * FROM `{$this->prefix}bb_connections` WHERE id=? AND issuer_id=? FOR UPDATE",[$a->scope->connectionId,$a->scope->issuerId]);
            if(!$locked||!$locked->row_array())throw new \RuntimeException('bb_connection_unavailable');
            $current=self::agreement($locked->row_array());
            $a->scope->assertSame($current->scope);
            if($a->taxId!==$current->taxId||$a->name!==$current->name||$a->variation!==$current->variation||$a->wallet!==$current->wallet){
                throw new \DomainException('bb_immutable_connection_conflict');
            }
            $connectionSnapshot=$locked->row_array();$referenceLocks=[];
            if($auxEnvelope){
                $identity=hash('sha256',json_encode([$a->scope->issuerId,$connectionSnapshot['account_id'],$connectionSnapshot['environment'],$connectionSnapshot['agreement_number'],$connectionSnapshot['wallet'],$connectionSnapshot['variation']],JSON_THROW_ON_ERROR));
                $configuration=hash('sha256',json_encode([$identity,$connectionSnapshot['issuer_tax_id'],$connectionSnapshot['issuer_name']],JSON_THROW_ON_ERROR));
                if(($connectionSnapshot['provider']??null)!=='banco_brasil'||!hash_equals($identity,(string)$connectionSnapshot['identity_hash'])||!hash_equals($configuration,(string)$connectionSnapshot['configuration_hash']))throw new \DomainException('bb_immutable_connection_conflict');
                $lockedProfiles=$this->db->query("SELECT * FROM {$this->prefix}bb_layout_bindings WHERE id IN (?,?) ORDER BY id FOR UPDATE",[(int)$binding['id'],(int)$parentBinding['id']])->result_array();
                foreach($lockedProfiles as$profile)$referenceLocks[(int)$profile['id']]=$profile;
                if(count($referenceLocks)!==2)throw new \DomainException('bb_layout_binding_mismatch');
            }
            $bindingSnapshot=$this->layoutBinding((int)$binding['id'],$connectionSnapshot);
            $parentBindingSnapshot=$auxEnvelope?$this->layoutBinding((int)$parentBinding['id'],$connectionSnapshot):$bindingSnapshot;
            if($auxEnvelope&&($referenceLocks[(int)$bindingSnapshot['id']]!==$bindingSnapshot||$referenceLocks[(int)$parentBindingSnapshot['id']]!==$parentBindingSnapshot))throw new \DomainException('bb_layout_binding_mismatch');
            $confirmReferences=function()use($a,$connectionSnapshot,$bindingSnapshot,$parentBindingSnapshot):void{
                $this->confirmAuxiliaryRow('bb_connections',['id'=>$a->scope->connectionId],$connectionSnapshot);
                $this->confirmAuxiliaryRow('bb_layout_bindings',['id'=>(int)$bindingSnapshot['id']],$bindingSnapshot);
                $this->confirmAuxiliaryRow('bb_layout_bindings',['id'=>(int)$parentBindingSnapshot['id']],$parentBindingSnapshot);
            };
            $old=$this->db->where('scope_key',$a->scope->key())->where('content_hash',$parsed['file_hash'])->get($this->prefix.'bb_return_files')->row_array();
            if($old){
                if(($auxEnvelope||($old['layout_id']??null)===LayoutCatalog::CNAB400_AUX)&&(($old['layout_id']??null)!==$binding['layout_id']||(int)($old['layout_binding_id']??0)!==(int)$binding['id']))throw new \DomainException('bb_return_file_profile_conflict');
                if($auxEnvelope){
                    if(!is_string($old['provenance_json']??null)||!hash_equals($provenanceJson,$old['provenance_json']))throw new \DomainException('bb_auxiliary_replay_corrupt');
                    $this->confirmAuxiliaryRow('bb_return_files',['id'=>(int)$old['id']],['issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),'content_hash'=>$parsed['file_hash'],'layout_id'=>$binding['layout_id'],'layout_binding_id'=>(int)$binding['id'],'provenance_json'=>$provenanceJson,'record_count'=>$parsed['records'],'event_count'=>count($parsed['events']),'process_state'=>'review_required']);
                    $links=$this->db->query("SELECT * FROM {$this->prefix}bb_return_file_events WHERE return_file_id=?",[(int)$old['id']])->result_array();
                    if(count($links)!==count($parsed['events']))throw new \DomainException('bb_auxiliary_replay_corrupt');
                    $replayNewIds=[];foreach($parsed['events']as$replayed){
                        $lot=$replayed['lot'];$sequence=$replayed['sequence'];$eventBinding=Cnab400Auxiliary::isAuxiliary($replayed)?(int)$binding['id']:(int)$parentBinding['id'];unset($replayed['file_hash'],$replayed['lot'],$replayed['sequence']);$json=json_encode($replayed,JSON_THROW_ON_ERROR);
                        $stored=$this->db->where('scope_key',$a->scope->key())->where('event_key',$replayed['event_key'])->get($this->prefix.'bb_return_events')->row_array();
                        if(!$stored)throw new \DomainException('bb_auxiliary_replay_corrupt');
                        if((int)$stored['return_file_id']===(int)$old['id'])$replayNewIds[]=(int)$stored['id'];
                        $this->confirmAuxiliaryRow('bb_return_events',['id'=>(int)$stored['id']],['issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),'layout_binding_id'=>$eventBinding,'event_key'=>$replayed['event_key'],'payload_hash'=>hash('sha256',$json),'observation_json'=>$json,'remote_id'=>$replayed['our_number'],'control_reference'=>$replayed['control'],'occurrence'=>$replayed['occurrence'],'observation'=>$replayed['observation'],'financial_state'=>'not_applied']);
                        $this->confirmAuxiliaryRow('bb_return_file_events',['return_file_id'=>(int)$old['id'],'return_event_id'=>(int)$stored['id']],['issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),'layout_binding_id'=>$eventBinding,'lot_number'=>$lot,'record_sequence'=>$sequence]);
                    }
                    $this->confirmAuxiliarySet((int)$old['id'],count($parsed['events']),$replayNewIds);$confirmReferences();$this->auxiliaryImportAccess($a->scope->issuerId,$actor);
                }
                // Exact bytes may recover a pre-retention history entry; never reconstruct an original.
                (new ReturnOriginalArchive($this->db,$this->prefix))->retain($old,$actor,$bytes);
                if($guarded){$confirmReferences();$this->auxiliaryImportAccess($a->scope->issuerId,$actor);}
                return ['id'=>(int)$old['id'],'duplicate'=>true,'events'=>(int)$old['event_count'],'financial_effect'=>'not_applied'];
            }
            $now=date('Y-m-d H:i:s');$file=['issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),
                'content_hash'=>$parsed['file_hash'],'layout_id'=>$binding['layout_id'],'layout_binding_id'=>(int)$binding['id'],
                'provenance_json'=>$provenanceJson,'record_count'=>$parsed['records'],'event_count'=>count($parsed['events']),
                'process_state'=>'review_required','created_by'=>$actor,'created_at'=>$now];
            if(!$this->db->insert($this->prefix.'bb_return_files',$file))throw new \RuntimeException('bb_return_write_failed');
            $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('bb_return_write_failed');
            $archive=new ReturnOriginalArchive($this->db,$this->prefix);$archiveFile=['id'=>$id]+$file;
            $archive->retain($archiveFile,$actor,$bytes);$archiveProof=$archive->read($archiveFile);
            $parents=[];$newEventIds=[];$confirmed=[['bb_return_files',['id'=>$id],$file]];
            foreach($parsed['events'] as $event){
                $auxiliary=Cnab400Auxiliary::isAuxiliary($event);$eventBindingId=$auxEnvelope&&!$auxiliary?(int)$parentBinding['id']:(int)$binding['id'];
                if($auxiliary){Cnab400Auxiliary::view($event);if(!$auxEnvelope||!isset($parents[$event['parent_event_key']])||!hash_equals($parents[$event['parent_event_key']],$event['parent_payload_hash']))throw new \DomainException('bb400_auxiliary_parent_mismatch');}
                $lot=$event['lot'];$sequence=$event['sequence'];
                unset($event['file_hash'],$event['lot'],$event['sequence']);
                $json=json_encode($event,JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
                $old=$this->db->where('scope_key',$a->scope->key())->where('event_key',$event['event_key'])->get($this->prefix.'bb_return_events')->row_array();
                if($old){
                    if(!hash_equals($hash,(string)$old['payload_hash']))throw new \DomainException('bb_return_event_conflict');
                    if($auxEnvelope&&(int)($old['layout_binding_id']??0)!==$eventBindingId)throw new \DomainException('bb_return_event_profile_conflict');
                    $eventId=(int)$old['id'];
                }else{
                $eventRecord=['return_file_id'=>$id,'layout_binding_id'=>$eventBindingId,'issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,
                    'scope_key'=>$a->scope->key(),'event_key'=>$event['event_key'],'payload_hash'=>$hash,'remote_id'=>$event['our_number'],'control_reference'=>$event['control'],
                    'occurrence'=>$event['occurrence'],'observation'=>$event['observation'],'observation_json'=>$json,'financial_state'=>'not_applied','created_at'=>$now];if(!$this->db->insert($this->prefix.'bb_return_events',$eventRecord))throw new \RuntimeException('bb_event_write_failed');
                    $eventId=(int)$this->db->insert_id();$newEventIds[]=$eventId;
                }
                if($auxEnvelope){
                    if(($old['financial_state']??'not_applied')!=='not_applied')throw new \DomainException('bb400_parent_financial_state_conflict');
                    if($old)$this->confirmAuxiliaryRow('bb_return_events',['id'=>$eventId],['layout_binding_id'=>$eventBindingId,'issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),'event_key'=>$event['event_key'],'payload_hash'=>$hash,'remote_id'=>$event['our_number'],'control_reference'=>$event['control'],'occurrence'=>$event['occurrence'],'observation'=>$event['observation'],'observation_json'=>$json,'financial_state'=>'not_applied']);
                    $confirmed[]=['bb_return_events',['id'=>$eventId],$old?:$eventRecord];
                }
                if($auxEnvelope&&!$auxiliary)$parents[$event['event_key']]=$hash;
                $provenanceRecord=['return_file_id'=>$id,'return_event_id'=>$eventId,'layout_binding_id'=>$eventBindingId,
                    'issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),
                    'lot_number'=>$lot,'record_sequence'=>$sequence,'created_at'=>$now];
                if($eventId<1||!$this->db->insert($this->prefix.'bb_return_file_events',$provenanceRecord))throw new \RuntimeException('bb_event_provenance_write_failed');
                if($auxEnvelope)$confirmed[]=['bb_return_file_events',['return_file_id'=>$id,'return_event_id'=>$eventId],$provenanceRecord];
            }
            $this->audit($a->scope->issuerId,$a->scope->connectionId,$actor,'return_imported_for_review','return_file',$id);
            if($auxEnvelope){
                $audit=$this->db->query("SELECT * FROM {$this->prefix}bb_audit WHERE issuer_id=? AND connection_id=? AND actor_id=? AND action='return_imported_for_review' AND resource_type='return_file' AND resource_id=?",[$a->scope->issuerId,$a->scope->connectionId,$actor,$id])->result_array();
                if(count($audit)!==1)throw new \RuntimeException('bb_auxiliary_audit_unconfirmed');
                foreach($confirmed as[$table,$keys,$expected])$this->confirmAuxiliaryRow($table,$keys,$expected);
                $this->confirmAuxiliarySet($id,count($parsed['events']),$newEventIds);$confirmReferences();$this->auxiliaryImportAccess($a->scope->issuerId,$actor);
            }
            if($archive->read($archiveFile)!==$archiveProof)throw new \RuntimeException('bb_return_original_write_unconfirmed');
            if($guarded){$confirmReferences();$this->auxiliaryImportAccess($a->scope->issuerId,$actor);}
            return ['id'=>$id,'duplicate'=>false,'events'=>count($parsed['events']),'financial_effect'=>'not_applied'];
        };
        if($guarded){require_once APPPATH.'services/issuers/IssuerTransaction.php';return \IssuerTransaction::run($this->db,$this->prefix,array_merge($tables,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules']),$work);}
        return Transaction::run($this->db,$this->prefix,$tables,$work);
    }
    /** Authorized private download, including original connection scope and audit, never an HTTP cache. */
    public function downloadReturn(int $issuer,int $actor,int $fileId):array
    {
        require_once APPPATH.'services/issuers/IssuerTransaction.php';
        if($issuer<1||$actor<1||$fileId<1)throw new \DomainException('bb_return_original_unavailable');
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','modules','bb_connections','bb_return_files','bb_return_originals','bb_audit'];
        return \IssuerTransaction::run($this->db,$this->prefix,$tables,function()use($issuer,$actor,$fileId){
            $this->auxiliaryImportAccess($issuer,$actor,'download');
            $file=$this->db->where('id',$fileId)->where('issuer_id',$issuer)->get($this->prefix.'bb_return_files')->row_array();
            if(!$file)throw new \DomainException('bb_return_original_unavailable');
            $connection=$this->connection((int)$file['connection_id'],$issuer);
            if(!hash_equals(self::agreement($connection)->scope->key(),(string)$file['scope_key']))throw new \DomainException('bb_return_original_unavailable');
            $original=(new ReturnOriginalArchive($this->db,$this->prefix))->read($file);
            if($original===null)throw new \DomainException('bb_return_original_not_retained');
            $auditSql='SELECT id FROM '.$this->prefix.'bb_audit WHERE issuer_id=? AND connection_id=? AND actor_id=? AND action=? AND resource_type=? AND resource_id=? ORDER BY id';
            $auditKeys=[$issuer,(int)$file['connection_id'],$actor,'return_original_downloaded','return_file',$fileId];
            $auditsBefore=$this->db->query($auditSql,$auditKeys)->result_array();
            $this->audit($issuer,(int)$file['connection_id'],$actor,'return_original_downloaded','return_file',$fileId);
            $auditsAfter=$this->db->query($auditSql,$auditKeys)->result_array();
            if(count($auditsAfter)!==count($auditsBefore)+1||array_slice($auditsAfter,0,count($auditsBefore))!==$auditsBefore)throw new \RuntimeException('bb_return_download_audit_unconfirmed');

            $this->auxiliaryImportAccess($issuer,$actor,'download');
            $again=$this->db->where('id',$fileId)->where('issuer_id',$issuer)->get($this->prefix.'bb_return_files')->row_array();
            if($again!==$file||(new ReturnOriginalArchive($this->db,$this->prefix))->read($file)!==$original
                ||$this->connection((int)$file['connection_id'],$issuer)!==$connection)throw new \DomainException('bb_return_original_changed');
            return ['content'=>$original['content'],'hash'=>$original['content_hash'],'filename'=>'bb-retorno-'.$fileId.'.ret'];
        });
    }
    public function recentReturns(int $issuerId): array
    {
        return Transaction::quiet($this->db,function()use($issuerId){
        $rows=self::rows($this->db->select('id,connection_id,layout_id,layout_binding_id,provenance_json,content_hash,record_count,event_count,process_state,created_at')
            ->where('issuer_id',$issuerId)->order_by('id','DESC')->limit(30)->get($this->prefix.'bb_return_files'),'result_array');
        foreach($rows as &$row){
            $original=$this->db->select('id,content_hash,byte_count')->where('issuer_id',$issuerId)->where('return_file_id',(int)$row['id'])->get($this->prefix.'bb_return_originals');
            if(!$original)throw new \RuntimeException('bb_return_original_unavailable');
            $meta=$original->row_array();$row['original_retained']=$meta&&hash_equals((string)$row['content_hash'],(string)$meta['content_hash'])&&(int)$meta['byte_count']>0;
        }unset($row);return $rows;
            });
    }
    public function recentEvents(int $issuerId): array
    {
        return Transaction::quiet($this->db,function()use($issuerId){
        $rows=self::rows($this->db->select('id,connection_id,remote_id,occurrence,observation,observation_json,payload_hash,financial_state,created_at')
            ->where('issuer_id',$issuerId)->order_by('id','DESC')->limit(50)->get($this->prefix.'bb_return_events'),'result_array');
        foreach($rows as &$row){
            $event=json_decode($row['observation_json'],true);
            unset($row['observation_json']);
            if(is_array($event)&&(Cnab400Auxiliary::isAuxiliary($event)||($event['layout']??null)===LayoutCatalog::CNAB400_AUX)){if(!hash_equals($row['payload_hash'],hash('sha256',json_encode($event,JSON_THROW_ON_ERROR))))throw new \DomainException('bb400_auxiliary_corrupt');$row['auxiliary']=Cnab400Auxiliary::view($event);}
            unset($row['payload_hash']);
            foreach(['nominal_amount','fees','interest','discount','abatement','iof','channel','layout','declared_currency','currency_evidence','posting_amount','posting_direction','currency','paid_amount','net_amount','other_expenses','other_credits','occurrence_date','credit_date'] as $field)$row[$field]=$event[$field]??null;
        }
        return $rows;
            });
    }
    private static function rows($result,string $method):array
    {
        if($result===false)throw new \RuntimeException('bb_storage_unavailable');
        return $result->$method();
    }
    private function audit(int $issuer,?int $connection,int $actor,string $action,string $type,int $id):void
    {
        if(!$this->db->insert($this->prefix.'bb_audit',['issuer_id'=>$issuer,'connection_id'=>$connection,'actor_id'=>$actor,'action'=>$action,
            'resource_type'=>$type,'resource_id'=>$id,'created_at'=>date('Y-m-d H:i:s')]))throw new \RuntimeException('bb_audit_failed');
    }
}
