<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';

/** Small issuer catalog with immutable revisions in the existing audit journal. */
final class NativeCompanyContractTypes
{
    private const EVENTS=['native_type_contract_created','native_type_contract_updated','native_type_contract_hidden'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function tables():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','options','issuers_audit'];}
    private function authorize(int $issuer,int $actor,bool $write=false):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'contracts','view'))throw new DomainException('native_contract_type_denied');
        if($write&&!$acl->administrative($issuer,$actor))throw new DomainException('native_contract_type_denied');
    }
    private function events(int $issuer):array
    {
        return IssuerStorage::query($this->db,'SELECT id,subject_id,actor_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND event IN (?,?,?) ORDER BY id',[$issuer,...self::EVENTS])->result_array();
    }
    private function project(array $events):array
    {
        $rows=[];
        foreach($events as$event){
            $data=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR);
            if(array_keys($data)!==['version','name','active','revision','request_key','input_hash']||$data['version']!==1||!is_string($data['name'])||trim($data['name'])===''||strlen($data['name'])>191||!is_bool($data['active'])||!is_int($data['revision'])||!preg_match('/^[a-f0-9]{64}$/D',$data['request_key'])||!preg_match('/^[a-f0-9]{64}$/D',$data['input_hash']))throw new RuntimeException('native_contract_type_corrupt');
            $created=$event['event']===self::EVENTS[0];$id=IssuerPolicy::id($created?$event['id']:$event['subject_id']);
            if(($created&&(isset($rows[$id])||$data['revision']!==1))||(!$created&&(!isset($rows[$id])||$rows[$id]['revision']+1!==$data['revision'])))throw new RuntimeException('native_contract_type_corrupt');
            $rows[$id]=['id'=>NativeCompanyResourceId::encode('company_contract_type',$id),'name'=>$data['name'],'active'=>$data['active'],'revision'=>$data['revision']];
        }
        return$rows;
    }
    public function listing(int $issuer,int $actor,bool $activeOnly=true):array
    {
        $this->authorize($issuer,$actor);$rows=array_values($this->project($this->events($issuer)));if($activeOnly)$rows=array_values(array_filter($rows,static fn($row)=>$row['active']));$this->authorize($issuer,$actor);return$rows;
    }
    public function referenceInOwned(int $issuer,int $actor,$canonical):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables());$this->authorize($issuer,$actor);$ref=NativeCompanyResourceId::decode('contract_type',$canonical);
        if($ref['source_kind']!=='company_contract_type')throw new DomainException('native_contract_type_denied');
        $row=$this->project($this->events($issuer))[$ref['source_id']]??null;if(!$row||!$row['active'])throw new DomainException('native_contract_type_denied');return ['id'=>$row['id'],'name'=>$row['name']];
    }
    public function save(int $issuer,int $actor,?string $canonical,?int $revision,?string $name,string $key):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)||($name!==null&&(trim($name)===''||strlen($name)>191||preg_match('//u',$name)!==1||preg_match('/[\x00-\x1f\x7f]/',$name))))throw new DomainException('native_contract_type_invalid');
        $name=$name===null?null:trim($name);$source=null;if($canonical!==null){$ref=NativeCompanyResourceId::decode('contract_type',$canonical);if($ref['source_kind']!=='company_contract_type')throw new DomainException('native_contract_type_denied');$source=$ref['source_id'];IssuerPolicy::id($revision);}elseif($name===null||$revision!==null)throw new DomainException('native_contract_type_invalid');
        $hash=hash('sha256',json_encode([$canonical,$revision,$name],JSON_THROW_ON_ERROR));
        return IssuerTransaction::run($this->db,$this->p,self::tables(),function()use($issuer,$actor,$source,$revision,$name,$key,$hash):array{
            $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,true);$events=$this->events($issuer);$rows=$this->project($events);
            foreach($events as$event){$old=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR);if($old['request_key']!==$key)continue;if((int)$event['actor_id']!==$actor||!hash_equals($old['input_hash'],$hash))throw new DomainException('native_contract_type_conflict');$id=$event['event']===self::EVENTS[0]?(int)$event['id']:(int)$event['subject_id'];return$rows[$id];}
            $before=$source===null?null:($rows[$source]??throw new DomainException('native_contract_type_denied'));if($before!==null&&$before['revision']!==$revision)throw new DomainException('native_contract_type_stale');
            if($name!==null)foreach($rows as$id=>$row)if($id!==$source&&$row['active']&&mb_strtolower($row['name'])===mb_strtolower($name))throw new DomainException('native_contract_type_conflict');
            if($source===null&&count($rows)>=500)throw new DomainException('native_contract_type_limit');
            $event=$source===null?self::EVENTS[0]:($name===null?self::EVENTS[2]:self::EVENTS[1]);$data=['version'=>1,'name'=>$name??$before['name'],'active'=>$name!==null,'revision'=>$before===null?1:$before['revision']+1,'request_key'=>$key,'input_hash'=>$hash];$json=json_encode($data,JSON_THROW_ON_ERROR);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$source??0,$event,$json,gmdate('Y-m-d H:i:s')]);$audit=(int)$this->db->insert_id();
            $stored=IssuerStorage::query($this->db,'SELECT id,subject_id,actor_id,event,metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=?',[$audit,$issuer])->row_array();if(!$stored||(int)$stored['subject_id']!==($source??0)||(int)$stored['actor_id']!==$actor||$stored['event']!==$event||$stored['metadata_json']!==$json)throw new RuntimeException('native_contract_type_write_unconfirmed');
            $expected=array_merge($events,[$stored]);if($this->events($issuer)!==$expected)throw new RuntimeException('native_contract_type_write_unconfirmed');$this->authorize($issuer,$actor,true);return$this->project($expected)[$source??$audit];
        });
    }
    public static function stored(array $snapshot):array
    {
        if(!array_key_exists('contract_type',$snapshot))return[];$value=$snapshot['contract_type'];
        if(!is_array($value)||array_keys($value)!==['id','name']||!is_string($value['id'])||!is_string($value['name'])||trim($value['name'])===''||strlen($value['name'])>191||NativeCompanyResourceId::decode('contract_type',$value['id'])['source_kind']!=='company_contract_type')throw new DomainException('native_contract_type_invalid');return['contract_type'=>$value];
    }
}
