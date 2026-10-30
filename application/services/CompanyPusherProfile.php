<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyPusherSchema.php';
require_once __DIR__.'/CompanyPusherTransport.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';

/** A local opt-in profile; configuration is not proof of connectivity or websocket delivery. */
final class CompanyPusherProfile
{
    public const CLUSTERS=['mt1','us2','us3','eu','ap1','ap2','ap3','ap4','sa1'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_installation_identity','issuers_resource_permissions','staff','issuers_audit'],array_keys(CompanyPusherSchema::definitions($this->p)));}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function transaction(callable $work){CompanyPusherSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    public function authorize(int $issuer,int $actor,string $cap):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'settings',$cap==='configure'?'edit':'view')||!$acl->can($issuer,$actor,'pusher',$cap))throw new DomainException('company_pusher_denied');
    }
    private function row(int $issuer):?array{return $this->rows('SELECT * FROM '.$this->p.'company_pusher_profiles WHERE issuer_id=? FOR UPDATE',[$issuer])[0]??null;}
    public function page(int $issuer,int $actor):array
    {
        return $this->transaction(function()use($issuer,$actor){$this->authorize($issuer,$actor,'view');$r=$this->row($issuer);return ['revision'=>(int)($r['revision']??0),'app_id'=>$r['app_id']??'','app_key'=>$r['app_key']??'','cluster'=>$r['cluster']??'mt1','secret_configured'=>($r['secret_cipher']??'')!=='','status'=>$r['status']??'unconfigured'];});
    }
    public function audit(int $issuer,int $actor,string $event,?int $subject=null):void
    {
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at) VALUES(?,?,?,?,?)',[$actor,'company_pusher_'.$event,$issuer,$subject,gmdate('Y-m-d H:i:s')]);$id=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND event=?',[$id,$issuer,$actor,'company_pusher_'.$event]))throw new RuntimeException('issuer_pusher_write_unconfirmed');
    }
    public function save(int $issuer,int $actor,int $revision,string $id,string $key,string $cluster,string $secret,callable $encrypt):void
    {
        if(!preg_match('/^[1-9][0-9]{0,19}$/D',$id)||!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$key)||!in_array($cluster,self::CLUSTERS,true)||strlen($secret)>4096||preg_match('/[\x00-\x20\x7f]/',$secret)||$revision<0||$revision>=2147483647)throw new DomainException('company_pusher_invalid');
        $this->transaction(function()use($issuer,$actor,$revision,$id,$key,$cluster,$secret,$encrypt){$this->authorize($issuer,$actor,'configure');$old=$this->row($issuer);if((int)($old['revision']??0)!==$revision)throw new DomainException('company_pusher_stale');$changed=$old&&($old['app_id']!==$id||$old['app_key']!==$key||$old['cluster']!==$cluster);if($secret===''&&(!$old||$changed||$old['secret_cipher']===''))throw new DomainException('company_pusher_secret_required');if($this->rows('SELECT issuer_id FROM '.$this->p.'company_pusher_profiles WHERE cluster=? AND app_id=? AND issuer_id<>?',[$cluster,$id,$issuer]))throw new DomainException('company_pusher_conflict');
            $plain=json_encode(['format'=>1,'issuer_id'=>$issuer,'app_id'=>$id,'app_key'=>$key,'cluster'=>$cluster,'secret'=>$secret],JSON_THROW_ON_ERROR);$cipher=$secret===''?$old['secret_cipher']:$encrypt($plain);if(!is_string($cipher)||$cipher===''||$cipher===$plain)throw new DomainException('company_pusher_secret_required');$stamp=gmdate('Y-m-d H:i:s');$fields=['app_id'=>$id,'app_key'=>$key,'cluster'=>$cluster,'secret_cipher'=>$cipher,'revision'=>$revision+1,'status'=>'enabled','configured_by'=>$actor,'updated_at'=>$stamp];if(!$old){$fields=['issuer_id'=>$issuer]+$fields+['created_at'=>$stamp];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_pusher_profiles (`'.implode('`,`',array_keys($fields)).'`) VALUES('.implode(',',array_fill(0,count($fields),'?')).')',array_values($fields));}else IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_pusher_profiles SET '.implode(',',array_map(fn($f)=>'`'.$f.'`=?',array_keys($fields))).' WHERE issuer_id=?',array_merge(array_values($fields),[$issuer]));self::confirm($this->row($issuer),$fields);$this->audit($issuer,$actor,'saved');$this->authorize($issuer,$actor,'configure');});
    }
    public static function confirm(?array $row,array $fields):void{foreach($fields as$f=>$value)if((string)($row[$f]??null)!==(string)$value)throw new RuntimeException('issuer_pusher_write_unconfirmed');}
    public function revoke(int $issuer,int $actor,int $revision):void
    {
        $this->transaction(function()use($issuer,$actor,$revision){$this->authorize($issuer,$actor,'configure');$row=$this->row($issuer);if(!$row||(int)$row['revision']!==$revision)throw new DomainException('company_pusher_stale');IssuerStorage::query($this->db,"UPDATE ".$this->p."company_pusher_profiles SET status='revoked',secret_cipher='',revision=revision+1,updated_at=? WHERE issuer_id=?",[gmdate('Y-m-d H:i:s'),$issuer]);self::confirm($this->row($issuer),['status'=>'revoked','secret_cipher'=>'','revision'=>$revision+1]);$this->audit($issuer,$actor,'revoked');$this->authorize($issuer,$actor,'configure');});
    }
    /** Internal only, under the caller's owned transaction; never a generic DTO. */
    public function runtimeInOwned(int $issuer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables()); $r=$this->row($issuer);if(!$r||$r['status']!=='enabled'||$r['secret_cipher']==='')throw new DomainException('company_pusher_disabled');if(!preg_match('/^[1-9][0-9]{0,19}$/D',$r['app_id'])||!preg_match('/^[A-Za-z0-9_-]{8,80}$/D',$r['app_key'])||!in_array($r['cluster'],self::CLUSTERS,true)||(int)$r['revision']<1)throw new DomainException('company_pusher_invalid');$this->authorize($issuer,(int)$r['configured_by'],'configure');$r['profile_hash']=hash('sha256',json_encode([$issuer,$r['app_id'],$r['app_key'],$r['cluster'],(int)$r['revision'],(int)$r['configured_by']],JSON_THROW_ON_ERROR));return $r;
    }
    public static function secret(array $profile,callable $decrypt):string
    {
        try{$raw=$decrypt($profile['secret_cipher']);if(!is_string($raw)||$raw==='')throw new RuntimeException();$doc=json_decode($raw,true,8,JSON_THROW_ON_ERROR);if(!is_array($doc)||array_keys($doc)!==['format','issuer_id','app_id','app_key','cluster','secret']||$doc['format']!==1||$doc['issuer_id']!==(int)$profile['issuer_id']||$doc['app_id']!==$profile['app_id']||$doc['app_key']!==$profile['app_key']||$doc['cluster']!==$profile['cluster']||!is_string($doc['secret'])||$doc['secret']===''||strlen($doc['secret'])>4096||preg_match('/[\x00-\x20\x7f]/',$doc['secret']))throw new RuntimeException();return $doc['secret'];}catch(Throwable $e){throw new DomainException('company_pusher_secret_required');}
    }
}
