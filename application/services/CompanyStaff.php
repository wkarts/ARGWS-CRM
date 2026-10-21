<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyStaffSchema.php';
require_once __DIR__.'/CompanyResourceCatalog.php';
require_once __DIR__.'/issuers/IssuerStaffAccess.php';

/** Scoped membership writer; shared login identity and all other company links are preserved. */
final class CompanyStaff
{
    private IssuerResourceAccess $acl;
    private array $catalog;
    public function __construct(private $db,private string $prefix,?array $catalog=null)
    {
        CompanyStaffSchema::statements($prefix);$this->acl=new IssuerResourceAccess($db,$prefix);
        $this->catalog=$catalog??array_map(static fn($d)=>array_keys($d['capabilities']),CompanyResourceCatalog::definitions());
    }
    public function tables():array{return array_merge(IssuerStaffAccess::TABLES,['company_staff_commands']);}
    private function query(string $sql,array $params=[]){return IssuerStorage::query($this->db,$sql,$params);}
    private function rows(string $sql,array $params=[]):array{return $this->query($sql,$params)->result_array();}
    private function guard(int $issuer,int $actor,string $cap):void
    {
        if(!$this->acl->can($issuer,$actor,'staff','view')||!$this->acl->can($issuer,$actor,'staff',$cap)){throw new DomainException('issuer_staff_denied');}
    }
    private static function canonical($value)
    {
        if(!is_array($value)){return $value===null?null:(string)$value;}
        if(!array_is_list($value)){ksort($value);}return array_map([self::class,'canonical'],$value);
    }
    private static function digest(array $value):string{return hash('sha256',json_encode(self::canonical($value),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));}
    private function state(int $staff):array
    {
        return [
            'profile'=>$this->rows('SELECT staffid,firstname,lastname,email,phonenumber,password,admin,active,role,is_not_staff FROM '.$this->prefix.'staff WHERE staffid=?',[$staff]),
            'members'=>$this->rows('SELECT issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations FROM '.$this->prefix.'issuers_staff WHERE staff_id=? ORDER BY issuer_id',[$staff]),
            'grants'=>$this->acl->rowsForStaff($staff),
            'default'=>$this->rows('SELECT staff_id,issuer_id FROM '.$this->prefix.'issuers_staff_defaults WHERE staff_id=?',[$staff]),
        ];
    }
    private function linked(int $issuer,int $staff):bool
    {
        return $this->acl->isInstallationMaster($staff)||count($this->rows('SELECT issuer_id FROM '.$this->prefix.'issuers_staff WHERE issuer_id=? AND staff_id=? AND can_view=1',[$issuer,$staff]))===1;
    }
    private function editable(int $issuer,int $actor,int $staff,array $state):bool
    {
        if($this->acl->isMaster($actor)){return true;}
        $row=$state['profile'][0]??[];
        return !$this->acl->isInstallationMaster($staff)&&(int)($row['admin']??1)===0&&(int)($row['role']??0)===0
            &&array_map('intval',array_column($state['members'],'issuer_id'))===[$issuer];
    }
    public function get($issuer,$actor,$staff):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$staff=IssuerPolicy::id($staff);$this->guard($issuer,$actor,'view');
        if(!$this->linked($issuer,$staff)){throw new DomainException('issuer_staff_denied');}$state=$this->state($staff);$row=$state['profile'][0]??null;
        if(!$row){throw new DomainException('issuer_staff_denied');}unset($row['password']);
        $grants=[];foreach($state['grants'] as$grant){if((int)$grant['issuer_id']===$issuer){$grants[$grant['feature']][]=$grant['capability'];}}
        return $row+['revision'=>self::digest($state),'grants'=>$grants,'default_current'=>(int)($state['default'][0]['issuer_id']??0)===$issuer,'master'=>$this->acl->isInstallationMaster($staff),'profile_editable'=>$this->editable($issuer,$actor,$staff,$state)];
    }
    public function page($issuer,$actor,$after=0,int $limit=50,string $search=''):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$this->guard($issuer,$actor,'view');
        $after=$after===0||$after==='0'?0:IssuerPolicy::id($after);if($limit<1||$limit>100||strlen($search)>100){throw new DomainException('issuer_staff_invalid');}
        $where='';$params=[$issuer,$after];if($search!==''){$where=' AND (s.firstname LIKE ? OR s.lastname LIKE ? OR s.email LIKE ?)';$term='%'.addcslashes($search,'%_\\').'%';array_push($params,$term,$term,$term);}
        $rows=$this->rows('SELECT s.staffid,s.firstname,s.lastname,s.email,s.active FROM '.$this->prefix.'staff s WHERE (EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_staff m WHERE m.staff_id=s.staffid AND m.issuer_id=? AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->prefix.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid)) AND s.staffid>?'.$where.' ORDER BY s.staffid LIMIT '.($limit+1),$params);
        $next=count($rows)>$limit?(int)$rows[$limit-1]['staffid']:null;return ['items'=>array_slice($rows,0,$limit),'next'=>$next];
    }
    private function profile(array $profile,bool $create):array
    {
        if(array_diff(array_keys($profile),['firstname','lastname','email','phonenumber','password'])||(!$create&&array_key_exists('password',$profile))){throw new DomainException('issuer_staff_invalid');}
        $out=[];foreach(['firstname'=>50,'lastname'=>50,'email'=>100,'phonenumber'=>30] as$key=>$limit){
            if(!array_key_exists($key,$profile)){if($create&&$key!=='phonenumber'){throw new DomainException('issuer_staff_invalid');}continue;}
            if(!is_string($profile[$key])||preg_match('/[\x00-\x1F\x7F]/',$profile[$key])||mb_strlen($profile[$key])>$limit){throw new DomainException('issuer_staff_invalid');}
            $out[$key]=trim($profile[$key]);if($key!=='phonenumber'&&$out[$key]===''){throw new DomainException('issuer_staff_invalid');}
        }
        if(isset($out['email'])){$out['email']=strtolower($out['email']);if(!filter_var($out['email'],FILTER_VALIDATE_EMAIL)){throw new DomainException('issuer_staff_invalid');}}
        if($create){$password=$profile['password']??null;if(!is_string($password)||strlen($password)<12||strlen($password)>128||str_contains($password,"\0")){throw new DomainException('issuer_staff_invalid');}$out['password']=$password;$out['phonenumber']=$out['phonenumber']??'';}
        return $out;
    }
    private function grants(int $issuer,int $actor,array $grants,array $existing=[]):array
    {
        if(count($grants)>1000){throw new DomainException('issuer_invalid_permission');}$out=[];
        foreach($grants as$feature=>$caps){
            if(!isset($this->catalog[$feature])||!is_array($caps)||count($caps)>100){throw new DomainException('issuer_invalid_permission');}
            foreach($caps as$cap){if(!is_string($cap)||!in_array($cap,$this->catalog[$feature],true)){throw new DomainException('issuer_invalid_permission');}
                if(!$this->acl->can($issuer,$actor,$feature,$cap)&&!in_array($cap,$existing[$feature]??[],true)){throw new DomainException('issuer_staff_grant_denied');}$out[$feature][$cap]=true;}
        }
        ksort($out);foreach($out as&$caps){ksort($caps);$caps=array_keys($caps);}unset($caps);return $out;
    }
    public function create($issuer,$actor,array $profile,array $grants,bool $makeDefault,string $key):int
    {
        return $this->write($issuer,$actor,'create',null,null,$this->profile($profile,true),$grants,$makeDefault,$key);
    }
    public function link($issuer,$actor,string $email,array $grants,bool $makeDefault,string $key):int
    {
        $profile=$this->profile(['email'=>$email],false);return $this->write($issuer,$actor,'link',null,null,$profile,$grants,$makeDefault,$key);
    }
    public function update($issuer,$actor,$staff,string $revision,array $profile,array $grants,bool $makeDefault,string $key):int
    {
        return $this->write($issuer,$actor,'update',IssuerPolicy::id($staff),$revision,$this->profile($profile,false),$grants,$makeDefault,$key);
    }
    public function revoke($issuer,$actor,$staff,string $revision,string $key):int
    {
        return $this->write($issuer,$actor,'revoke',IssuerPolicy::id($staff),$revision,[],[],false,$key);
    }
    private function write($issuer,$actor,string $operation,?int $staff,?string $revision,array $profile,array $grants,bool $makeDefault,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$cap=match($operation){'create','link'=>'create','revoke'=>'delete',default=>'edit'};
        if(!preg_match('/^[a-f0-9]{64}$/D',$key)||($revision!==null&&!preg_match('/^[a-f0-9]{64}$/D',$revision))){throw new DomainException('issuer_staff_invalid');}
        $this->guard($issuer,$actor,$cap);CompanyStaffSchema::assertValid($this->db,$this->prefix);
        $input=$profile;unset($input['password']);$inputHash=self::digest([$issuer,$actor,$operation,$staff,$revision,$input,$grants,$makeDefault]);
        return IssuerTransaction::run($this->db,$this->prefix,$this->tables(),function()use($issuer,$actor,$operation,$staff,$revision,$profile,$grants,$makeDefault,$key,$cap,$inputHash):int{
            $this->acl->lockFinancialIdentity($issuer,$actor);$this->guard($issuer,$actor,$cap);
            $receipt=$this->rows('SELECT * FROM '.$this->prefix.'company_staff_commands WHERE issuer_id=? AND actor_id=? AND request_key=?',[$issuer,$actor,$key])[0]??null;
            if($receipt){
                if($receipt['operation']!==$operation||!hash_equals($receipt['input_hash'],$inputHash)){throw new DomainException('issuer_staff_conflict');}
                $id=(int)$receipt['staff_id'];$state=$this->state($id);
                if(!$state['profile']||!hash_equals($receipt['result_hash'],self::digest($state))||($operation!=='revoke'&&!$this->linked($issuer,$id))){throw new DomainException('issuer_staff_stale');}
                if($operation==='create'&&!$this->hasher()->CheckPassword($profile['password'],$state['profile'][0]['password'])){throw new DomainException('issuer_staff_conflict');}
                $this->guard($issuer,$actor,$cap);return $id;
            }
            $before=[];
            if($operation==='create'){
                if($this->rows('SELECT staffid FROM '.$this->prefix.'staff WHERE email=? FOR UPDATE',[$profile['email']])){throw new DomainException('issuer_staff_email_conflict');}
                $grants=$this->grants($issuer,$actor,$grants);$values=$profile;$values['password']=$this->hasher()->HashPassword($profile['password']);
                $values+=['admin'=>0,'active'=>1,'role'=>0,'is_not_staff'=>0,'datecreated'=>gmdate('Y-m-d H:i:s')];
                $this->query('INSERT INTO '.$this->prefix.'staff ('.implode(',',array_keys($values)).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',array_values($values));$staff=IssuerPolicy::id((int)$this->db->insert_id());
                $before=$this->state($staff);$created=$before['profile'][0]??[];foreach($values as$field=>$value){if($field==='datecreated'){continue;}if((string)($created[$field]??'')!==(string)$value){throw new RuntimeException('issuer_staff_write_unconfirmed');}}
            }elseif($operation==='link'){
                $found=$this->rows('SELECT staffid FROM '.$this->prefix.'staff WHERE email=? AND active=1 FOR UPDATE',[$profile['email']]);
                if(count($found)!==1){throw new DomainException('issuer_staff_denied');}$staff=(int)$found[0]['staffid'];$before=$this->state($staff);
                if($this->acl->isInstallationMaster($staff)||(!$this->acl->isMaster($actor)&&(int)$before['profile'][0]['admin']===1)){throw new DomainException('issuer_staff_denied');}
                if($this->linked($issuer,$staff)){throw new DomainException('issuer_staff_conflict');}$grants=$this->grants($issuer,$actor,$grants);
            }else{
                if(!$this->linked($issuer,$staff)){throw new DomainException('issuer_staff_denied');}$this->rows('SELECT staffid FROM '.$this->prefix.'staff WHERE staffid=? FOR UPDATE',[$staff]);$before=$this->state($staff);
                if(!hash_equals(self::digest($before),(string)$revision)){throw new DomainException('issuer_staff_stale');}
                if($operation==='revoke'&&($staff===$actor||$this->acl->isInstallationMaster($staff))){throw new DomainException('issuer_master_protected');}
                if($operation==='update'){
                    if($profile&&!$this->editable($issuer,$actor,$staff,$before)){throw new DomainException('issuer_staff_shared_identity');}
                    if(isset($profile['email'])&&$this->rows('SELECT staffid FROM '.$this->prefix.'staff WHERE email=? AND staffid<>? FOR UPDATE',[$profile['email'],$staff])){throw new DomainException('issuer_staff_email_conflict');}
                    $current=[];foreach($before['grants'] as$g){if((int)$g['issuer_id']===$issuer){$current[$g['feature']][]=$g['capability'];}}$grants=$this->grants($issuer,$actor,$grants,$current);
                }
            }
            $expected=$before;
            if($operation==='update'&&$profile){$this->query('UPDATE '.$this->prefix.'staff SET '.implode(',',array_map(static fn($f)=>$f.'=?',array_keys($profile))).' WHERE staffid=?',array_merge(array_values($profile),[$staff]));$expected['profile'][0]=array_replace($expected['profile'][0],$profile);}
            $master=$this->acl->isInstallationMaster($staff);
            if(!$master){
                $expected['grants']=array_values(array_filter($before['grants'],static fn($g)=>(int)$g['issuer_id']!==$issuer));
                $this->query('DELETE FROM '.$this->prefix.'issuers_resource_permissions WHERE issuer_id=? AND staff_id=?',[$issuer,$staff]);
                if($operation==='revoke'){
                    if((int)($before['default'][0]['issuer_id']??0)===$issuer){$this->query('DELETE FROM '.$this->prefix.'issuers_staff_defaults WHERE staff_id=?',[$staff]);$expected['default']=[];}
                    $this->query('DELETE FROM '.$this->prefix.'issuers_staff WHERE issuer_id=? AND staff_id=?',[$issuer,$staff]);$expected['members']=array_values(array_filter($before['members'],static fn($m)=>(int)$m['issuer_id']!==$issuer));
                }else{
                    if(!$this->linked($issuer,$staff)){$this->query('INSERT INTO '.$this->prefix.'issuers_staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,?,1,0,0,0)',[$issuer,$staff]);if(!$this->linked($issuer,$staff)){throw new RuntimeException('issuer_staff_write_unconfirmed');}$expected['members'][]=['issuer_id'=>$issuer,'staff_id'=>$staff,'can_view'=>1,'can_manage_clients'=>0,'can_share_clients'=>0,'can_manage_integrations'=>0];}
                    foreach($before['grants'] as$g){if((int)$g['issuer_id']===$issuer&&$g['feature']==='core_legacy_administration'&&$g['capability']==='access'){$grants['core_legacy_administration']=['access'];}}
                    foreach($grants as$feature=>$caps){foreach($caps as$permission){$this->query('INSERT INTO '.$this->prefix.'issuers_resource_permissions(issuer_id,staff_id,feature,capability) VALUES(?,?,?,?)',[$issuer,$staff,$feature,$permission]);$expected['grants'][]=['issuer_id'=>$issuer,'feature'=>$feature,'capability'=>$permission];}}
                }
            }elseif($grants){throw new DomainException('issuer_master_access_automatic');}
            if($operation!=='revoke'&&$makeDefault){
                if($master&&!in_array($issuer,array_map('intval',array_column($before['members'],'issuer_id')),true)){$this->query('INSERT INTO '.$this->prefix.'issuers_staff(issuer_id,staff_id,can_view,can_manage_clients,can_share_clients,can_manage_integrations) VALUES(?,?,1,0,0,0)',[$issuer,$staff]);$expected['members'][]=['issuer_id'=>$issuer,'staff_id'=>$staff,'can_view'=>1,'can_manage_clients'=>0,'can_share_clients'=>0,'can_manage_integrations'=>0];}
                $this->query('DELETE FROM '.$this->prefix.'issuers_staff_defaults WHERE staff_id=?',[$staff]);$this->query('INSERT INTO '.$this->prefix.'issuers_staff_defaults(staff_id,issuer_id) VALUES(?,?)',[$staff,$issuer]);$expected['default']=[['staff_id'=>$staff,'issuer_id'=>$issuer]];
            }
            usort($expected['members'],static fn($a,$b)=>(int)$a['issuer_id']<=>(int)$b['issuer_id']);usort($expected['grants'],static fn($a,$b)=>[(int)$a['issuer_id'],$a['feature'],$a['capability']]<=>[(int)$b['issuer_id'],$b['feature'],$b['capability']]);
            $resultHash=self::digest($expected);if(!hash_equals($resultHash,self::digest($this->state($staff)))){throw new RuntimeException('issuer_staff_write_unconfirmed');}
            $now=gmdate('Y-m-d H:i:s');$metadata=json_encode(['before'=>self::digest($before),'after'=>$resultHash],JSON_THROW_ON_ERROR);$event='company_staff_'.$operation;
            $last=(int)($this->rows('SELECT MAX(id) AS id FROM '.$this->prefix.'issuers_audit')[0]['id']??0);
            $this->query('INSERT INTO '.$this->prefix.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$staff,$now,$metadata]);$audit=(int)$this->db->insert_id();
            $auditRow=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->prefix.'issuers_audit WHERE id=?',[$audit]);
            if($audit<=$last||self::canonical($auditRow)!==self::canonical([['actor_id'=>$actor,'event'=>$event,'issuer_id'=>$issuer,'subject_id'=>$staff,'created_at'=>$now,'metadata_json'=>$metadata]])){throw new RuntimeException('issuer_staff_write_unconfirmed');}
            $command=['issuer_id'=>$issuer,'actor_id'=>$actor,'request_key'=>$key,'operation'=>$operation,'input_hash'=>$inputHash,'staff_id'=>$staff,'result_hash'=>$resultHash,'created_at'=>$now];
            $this->query('INSERT INTO '.$this->prefix.'company_staff_commands('.implode(',',array_keys($command)).') VALUES(?,?,?,?,?,?,?,?)',array_values($command));
            if(self::canonical($this->rows('SELECT * FROM '.$this->prefix.'company_staff_commands WHERE issuer_id=? AND actor_id=? AND request_key=?',[$issuer,$actor,$key]))!==self::canonical([$command])){throw new RuntimeException('issuer_staff_write_unconfirmed');}
            $this->guard($issuer,$actor,$cap);if(!hash_equals($resultHash,self::digest($this->state($staff)))){throw new RuntimeException('issuer_staff_write_unconfirmed');}return $staff;
        });
    }
    private function hasher()
    {
        if(function_exists('app_hasher')){return app_hasher();}require_once __DIR__.'/../third_party/phpass.php';return new PasswordHash(8,true);
    }
}
