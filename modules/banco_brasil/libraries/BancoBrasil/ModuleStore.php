<?php
declare(strict_types=1);
namespace BancoBrasil;
require_once __DIR__.'/Agreement.php';
require_once __DIR__.'/Cnab240ReturnParser.php';
require_once __DIR__.'/Cnab400ReturnParser.php';
require_once __DIR__.'/LayoutCatalog.php';
require_once __DIR__.'/Transaction.php';

/** Call only after actor/issuer capability checks; never stores credentials or raw return payload. */
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
        if($layoutId===LayoutCatalog::CNAB400)Fields400::account($a);
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
    public function importReturn(array $connection,int $actor,string $bytes,int $layoutBindingId): array
    {
        if($actor<1)throw new \InvalidArgumentException('bb_actor_required');
        $a=self::agreement($connection);$binding=$this->layoutBinding($layoutBindingId,$connection);
        $parser=$binding['layout_id']===LayoutCatalog::CNAB400?new Cnab400ReturnParser():new Cnab240ReturnParser();
        $parsed=$parser->parse($a,$bytes);
        return Transaction::run($this->db,$this->prefix,['bb_connections','bb_layout_bindings','bb_return_files','bb_return_events','bb_return_file_events','bb_audit'],function()use($a,$parsed,$actor,$binding){
            $locked=$this->db->query("SELECT * FROM `{$this->prefix}bb_connections` WHERE id=? AND issuer_id=? FOR UPDATE",[$a->scope->connectionId,$a->scope->issuerId]);
            if(!$locked||!$locked->row_array())throw new \RuntimeException('bb_connection_unavailable');
            $current=self::agreement($locked->row_array());
            $a->scope->assertSame($current->scope);
            if($a->taxId!==$current->taxId||$a->name!==$current->name||$a->variation!==$current->variation||$a->wallet!==$current->wallet){
                throw new \DomainException('bb_immutable_connection_conflict');
            }
            $this->layoutBinding((int)$binding['id'],$locked->row_array());
            $old=$this->db->where('scope_key',$a->scope->key())->where('content_hash',$parsed['file_hash'])->get($this->prefix.'bb_return_files')->row_array();
            if($old)return ['id'=>(int)$old['id'],'duplicate'=>true,'events'=>(int)$old['event_count'],'financial_effect'=>'not_applied'];
            $now=date('Y-m-d H:i:s');$file=['issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),
                'content_hash'=>$parsed['file_hash'],'layout_id'=>$binding['layout_id'],'layout_binding_id'=>(int)$binding['id'],
                'provenance_json'=>json_encode(['declared_environment'=>$a->scope->environment,'environment_evidence'=>$parsed['environment_evidence']??'lot_marker_matches_declared_profile',
                    'authenticity'=>'not_verified','issuer_tax_id_evidence'=>$parsed['issuer_tax_id_evidence']??'matches_file',
                    'date_window'=>$binding['date_window'],'trailer_raw_totals'=>$parsed['trailer_raw_totals']??null,
                    'trailer_total_interpretation'=>$parsed['trailer_total_interpretation']??null],JSON_THROW_ON_ERROR),'record_count'=>$parsed['records'],'event_count'=>count($parsed['events']),
                'process_state'=>'review_required','created_by'=>$actor,'created_at'=>$now];
            if(!$this->db->insert($this->prefix.'bb_return_files',$file))throw new \RuntimeException('bb_return_write_failed');
            $id=(int)$this->db->insert_id();if($id<1)throw new \RuntimeException('bb_return_write_failed');
            foreach($parsed['events'] as $event){
                $lot=$event['lot'];$sequence=$event['sequence'];
                unset($event['file_hash'],$event['lot'],$event['sequence']);
                $json=json_encode($event,JSON_THROW_ON_ERROR);$hash=hash('sha256',$json);
                $old=$this->db->where('scope_key',$a->scope->key())->where('event_key',$event['event_key'])->get($this->prefix.'bb_return_events')->row_array();
                if($old){
                    if(!hash_equals($hash,(string)$old['payload_hash']))throw new \DomainException('bb_return_event_conflict');
                    $eventId=(int)$old['id'];
                }else{
                if(!$this->db->insert($this->prefix.'bb_return_events',['return_file_id'=>$id,'layout_binding_id'=>(int)$binding['id'],'issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,
                    'scope_key'=>$a->scope->key(),'event_key'=>$event['event_key'],'payload_hash'=>$hash,'remote_id'=>$event['our_number'],'control_reference'=>$event['control'],
                    'occurrence'=>$event['occurrence'],'observation'=>$event['observation'],'observation_json'=>$json,'financial_state'=>'not_applied','created_at'=>$now]))throw new \RuntimeException('bb_event_write_failed');
                    $eventId=(int)$this->db->insert_id();
                }
                if($eventId<1||!$this->db->insert($this->prefix.'bb_return_file_events',['return_file_id'=>$id,'return_event_id'=>$eventId,'layout_binding_id'=>(int)$binding['id'],
                    'issuer_id'=>$a->scope->issuerId,'connection_id'=>$a->scope->connectionId,'scope_key'=>$a->scope->key(),
                    'lot_number'=>$lot,'record_sequence'=>$sequence,'created_at'=>$now]))throw new \RuntimeException('bb_event_provenance_write_failed');
            }
            $this->audit($a->scope->issuerId,$a->scope->connectionId,$actor,'return_imported_for_review','return_file',$id);
            return ['id'=>$id,'duplicate'=>false,'events'=>count($parsed['events']),'financial_effect'=>'not_applied'];
        });
    }
    public function recentReturns(int $issuerId): array
    {
        return Transaction::quiet($this->db,function()use($issuerId){
        return self::rows($this->db->select('id,connection_id,layout_id,layout_binding_id,provenance_json,content_hash,record_count,event_count,process_state,created_at')
            ->where('issuer_id',$issuerId)->order_by('id','DESC')->limit(30)->get($this->prefix.'bb_return_files'),'result_array');
            });
    }
    public function recentEvents(int $issuerId): array
    {
        return Transaction::quiet($this->db,function()use($issuerId){
        $rows=self::rows($this->db->select('id,connection_id,remote_id,occurrence,observation,observation_json,financial_state,created_at')
            ->where('issuer_id',$issuerId)->order_by('id','DESC')->limit(50)->get($this->prefix.'bb_return_events'),'result_array');
        foreach($rows as &$row){
            $event=json_decode($row['observation_json'],true);
            unset($row['observation_json']);
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
