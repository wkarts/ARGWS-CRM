<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyTaskPusherNotice.php';

/** Private empty pulses backed by a durable own source. Accepted != websocket delivery. */
final class CompanyPusherTaskNotifications
{
    private CompanyPusherProfile $profile;
    private CompanyTaskPusherNotice $source;
    public function __construct(private $db,private string $p,private ?CompanyPusherTransport $transport=null)
    {
        $this->profile=new CompanyPusherProfile($db,$p);$this->source=new CompanyTaskPusherNotice($db,$p);$this->transport??=new CompanyPusherTransport();
    }
    public function tables():array{return array_merge($this->profile->tables(),$this->source->tables());}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function tx(callable $work){CompanyPusherSchema::assertValid($this->db,$this->p);CompanyProjectsSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables(),$work);}
    private function channel(array $profile,int $actor):string
    {
        return 'private-company-'.(int)$profile['issuer_id'].'-staff-'.$actor.'-'.hash('sha256',$profile['profile_hash'].':'.$this->source->permissionStamp((int)$profile['issuer_id'],$actor));
    }
    public function descriptor(int $issuer,int $actor):array
    {
        return $this->tx(function()use($issuer,$actor){$this->source->receiver($issuer,$actor);$p=$this->profile->runtimeInOwned($issuer);return ['app_key'=>$p['app_key'],'cluster'=>$p['cluster'],'channel'=>$this->channel($p,$actor),'revision'=>(int)$p['revision']];});
    }
    public function authorizeChannel(int $issuer,int $actor,string $channel,string $socket,callable $decrypt):array
    {
        if(!preg_match('/^[0-9]{1,20}\.[0-9]{1,20}$/D',$socket)||strlen($channel)>191)throw new DomainException('company_pusher_denied');
        return $this->tx(function()use($issuer,$actor,$channel,$socket,$decrypt){$this->source->receiver($issuer,$actor);$p=$this->profile->runtimeInOwned($issuer);if(!hash_equals($this->channel($p,$actor),$channel))throw new DomainException('company_pusher_denied');$secret=CompanyPusherProfile::secret($p,$decrypt);$auth=$this->transport->authorize($p,$secret,$channel,$socket);$this->source->receiver($issuer,$actor);if(!hash_equals($this->profile->runtimeInOwned($issuer)['profile_hash'],$p['profile_hash'])||!hash_equals($this->channel($p,$actor),$channel))throw new DomainException('company_pusher_stale');return $auth;});
    }
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_pusher_invalid');}
    private function row(int $issuer,string $key):array{return $this->rows('SELECT * FROM '.$this->p.'company_pusher_task_notices WHERE issuer_id=? AND request_key=? FOR UPDATE',[$issuer,$key])[0]??throw new DomainException('company_pusher_denied');}
    private function validate(array $claim):array
    {
        $issuer=(int)$claim['issuer_id'];$actor=(int)$claim['actor_id'];$now=$this->row($issuer,$claim['request_key']);foreach(['actor_id','recipient_id','task_id','profile_hash','source_hash','channel_name','status','claim_key']as$f)if((string)$now[$f]!==(string)$claim[$f])throw new DomainException('company_pusher_stale');$p=$this->profile->runtimeInOwned($issuer);$s=$this->source->snapshotInOwned($issuer,$actor,(int)$claim['task_id']);if(!hash_equals($p['profile_hash'],$claim['profile_hash'])||!hash_equals($s['source_hash'],$claim['source_hash'])||$s['recipient_id']!==(int)$claim['recipient_id']||!hash_equals($this->channel($p,$s['recipient_id']),$claim['channel_name']))throw new DomainException('company_pusher_stale');return $p;
    }
    public function queue(int $issuer,int $actor,int $task,int $taskRevision,int $profileRevision,string $key):string
    {
        self::key($key);return $this->tx(function()use($issuer,$actor,$task,$taskRevision,$profileRevision,$key){$s=$this->source->snapshotInOwned($issuer,$actor,$task);$p=$this->profile->runtimeInOwned($issuer);if($s['row']['revision']!==$taskRevision||(int)$p['revision']!==$profileRevision)throw new DomainException('company_pusher_stale');$old=$this->rows('SELECT * FROM '.$this->p.'company_pusher_task_notices WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($old){if((int)$old['issuer_id']!==$issuer||(int)$old['actor_id']!==$actor||(int)$old['task_id']!==$task||!hash_equals($old['source_hash'],$s['source_hash'])||!hash_equals($old['profile_hash'],$p['profile_hash']))throw new DomainException('company_pusher_conflict');return $key;}
            $stamp=gmdate('Y-m-d H:i:s');$row=['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'recipient_id'=>$s['recipient_id'],'task_id'=>$task,'profile_hash'=>$p['profile_hash'],'source_hash'=>$s['source_hash'],'channel_name'=>$this->channel($p,$s['recipient_id']),'status'=>'pending','claim_key'=>'','created_at'=>$stamp,'updated_at'=>$stamp];IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_pusher_task_notices (`'.implode('`,`',array_keys($row)).'`) VALUES('.implode(',',array_fill(0,count($row),'?')).')',array_values($row));CompanyPusherProfile::confirm($this->row($issuer,$key),$row);$this->profile->audit($issuer,$actor,'task_queued',$task);$this->validate($row);return $key;});
    }
    public function dispatch(int $issuer,int $actor,string $key,callable $decrypt):string
    {
        self::key($key);$claim=$this->tx(function()use($issuer,$actor,$key){$row=$this->row($issuer,$key);if((int)$row['actor_id']!==$actor)throw new DomainException('company_pusher_denied');$p=$this->validate($row);if($row['status']!=='pending')return ['done'=>$row['status']];$row['status']='submitting';$row['claim_key']=bin2hex(random_bytes(32));IssuerStorage::query($this->db,"UPDATE ".$this->p."company_pusher_task_notices SET status='submitting',claim_key=?,updated_at=? WHERE issuer_id=? AND request_key=?",[$row['claim_key'],gmdate('Y-m-d H:i:s'),$issuer,$key]);CompanyPusherProfile::confirm($this->row($issuer,$key),['status'=>'submitting','claim_key'=>$row['claim_key']]);return ['row'=>$row,'profile'=>$p];});if(isset($claim['done']))return $claim['done'];$row=$claim['row'];
        try{$secret=CompanyPusherProfile::secret($claim['profile'],$decrypt);$this->tx(fn()=>$this->validate($row));$this->transport->pulse($claim['profile'],$secret,$row['channel_name']);return $this->tx(function()use($issuer,$actor,$key,$row){$this->validate($row);IssuerStorage::query($this->db,"UPDATE ".$this->p."company_pusher_task_notices SET status='accepted',updated_at=? WHERE issuer_id=? AND request_key=?",[gmdate('Y-m-d H:i:s'),$issuer,$key]);CompanyPusherProfile::confirm($this->row($issuer,$key),['status'=>'accepted']);$this->profile->audit($issuer,$actor,'task_accepted',(int)$row['task_id']);$after=$row;$after['status']='accepted';$this->validate($after);return 'accepted';});}
        catch(Throwable $e){try{$this->tx(function()use($issuer,$key,$row){IssuerStorage::query($this->db,"UPDATE ".$this->p."company_pusher_task_notices SET status='uncertain',updated_at=? WHERE issuer_id=? AND request_key=? AND status='submitting' AND claim_key=?",[gmdate('Y-m-d H:i:s'),$issuer,$key,$row['claim_key']]);});}catch(Throwable $ignored){}if($e instanceof DomainException)throw $e;throw new RuntimeException('company_pusher_unavailable');}
    }
    /** Fresh own rows only; pulses contain neither IDs nor body. No call to Misc/global notifications. */
    public function inbox(int $issuer,int $actor):array
    {
        return $this->tx(function()use($issuer,$actor){$this->source->receiver($issuer,$actor);$p=$this->profile->runtimeInOwned($issuer);$result=[];foreach($this->rows("SELECT * FROM ".$this->p."company_pusher_task_notices WHERE issuer_id=? AND recipient_id=? AND status='accepted' ORDER BY created_at DESC,request_key LIMIT 30",[$issuer,$actor])as$row){try{$this->validate($row);$s=$this->source->snapshotInOwned($issuer,(int)$row['actor_id'],(int)$row['task_id']);$result[]=['request_key'=>$row['request_key'],'task_id'=>(int)$row['task_id'],'project_id'=>$s['row']['project_id'],'name'=>$s['row']['name'],'created_at'=>$row['created_at']];}catch(DomainException $e){continue;}}$this->source->receiver($issuer,$actor);if(!hash_equals($this->profile->runtimeInOwned($issuer)['profile_hash'],$p['profile_hash']))throw new DomainException('company_pusher_stale');return $result;});
    }
    public function page(int $issuer,int $actor):array
    {
        return $this->tx(function()use($issuer,$actor){$this->source->receiver($issuer,$actor);$acl=new IssuerResourceAccess($this->db,$this->p);$publish=$acl->can($issuer,$actor,'pusher','publish')&&$acl->can($issuer,$actor,'tasks','edit');$tasks=$publish?$this->rows('SELECT t.id,t.project_id,t.name,t.assignee_id,t.revision FROM '.$this->p.'company_tasks t JOIN '.$this->p.'company_projects p ON p.issuer_id=t.issuer_id AND p.id=t.project_id WHERE t.issuer_id=? ORDER BY t.id DESC LIMIT 50',[$issuer]):[];return ['tasks'=>$tasks,'publish'=>$publish,'notices'=>$this->rows('SELECT request_key,status,task_id,created_at FROM '.$this->p.'company_pusher_task_notices WHERE issuer_id=? AND actor_id=? ORDER BY created_at DESC,request_key LIMIT 30',[$issuer,$actor])];});
    }
    public function work(callable $decrypt,int $limit=20):array
    {
        if($limit<1||$limit>100)throw new InvalidArgumentException('Invalid limit');CompanyPusherSchema::assertValid($this->db,$this->p);$out=[];foreach($this->rows("SELECT issuer_id,actor_id,request_key FROM ".$this->p."company_pusher_task_notices WHERE status='pending' ORDER BY created_at,request_key LIMIT ".$limit)as$row){try{$status=$this->dispatch((int)$row['issuer_id'],(int)$row['actor_id'],$row['request_key'],$decrypt);}catch(DomainException $e){$status=$this->tx(function()use($row){$stored=$this->row((int)$row['issuer_id'],$row['request_key']);if($stored['status']!=='pending'||(int)$stored['actor_id']!==(int)$row['actor_id'])return $stored['status'];IssuerStorage::query($this->db,"UPDATE ".$this->p."company_pusher_task_notices SET status='blocked',updated_at=? WHERE issuer_id=? AND request_key=?",[gmdate('Y-m-d H:i:s'),$row['issuer_id'],$row['request_key']]);CompanyPusherProfile::confirm($this->row((int)$row['issuer_id'],$row['request_key']),['status'=>'blocked']);$this->profile->audit((int)$row['issuer_id'],(int)$row['actor_id'],'task_blocked',(int)$stored['task_id']);return 'blocked';});}catch(Throwable $e){$status='unavailable';}$out[]=['request_key'=>$row['request_key'],'status'=>$status];}return $out;
    }
}
