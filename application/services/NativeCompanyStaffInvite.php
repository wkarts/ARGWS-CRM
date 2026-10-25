<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyStaffInviteSchema.php';
require_once __DIR__.'/CompanyStaff.php';
require_once __DIR__.'/StaffEmailIdentity.php';
/** Creation-only one-time capability for the existing admin/authentication/set_password route. */
final class NativeCompanyStaffInvite
{
    public function __construct(private $db,private string $p,private string $secret){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p)||strlen($secret)<32)throw new DomainException('native_staff_invite_denied');}
    public function tables():array{return array_merge(IssuerStaffAccess::TABLES,['company_staff_commands','company_native_staff_invites','mail_queue','company_native_mail_dispatch_fences']);}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function identity(int $i,int $a,int $id):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($i,$a,'staff','create')||!$acl->member($i,$id)||$acl->isInstallationMaster($id))throw new DomainException('native_staff_invite_denied');
        $members=$this->rows('SELECT issuer_id FROM '.$this->p.'issuers_staff WHERE staff_id=? ORDER BY issuer_id',[$id]);if(array_map('intval',array_column($members,'issuer_id'))!==[$i])throw new DomainException('native_staff_invite_denied');
        $row=$this->rows('SELECT staffid,email,password,active,admin,role,new_pass_key,new_pass_key_requested FROM '.$this->p.'staff WHERE staffid=?',[$id])[0]??null;
        if(!$row||(int)$row['active']!==1||(int)$row['admin']!==0||(int)$row['role']!==0||!filter_var($row['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('native_staff_invite_denied');
        $unique=StaffEmailIdentity::unique($this->db,$this->p,$row['email']);if(!$unique||(int)$unique->staffid!==$id)throw new DomainException('native_staff_invite_denied');
        if(!$acl->can($i,$a,'staff','create')||!$acl->member($i,$id))throw new DomainException('native_staff_invite_denied');return$row;
    }
    private function stamp(array $row):string{return hash_hmac('sha256',json_encode([(int)$row['staffid'],StaffEmailIdentity::normalize($row['email']),(string)$row['password']],JSON_THROW_ON_ERROR),$this->secret);}
    private function tokenHash(int $id,string $token):string
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))throw new DomainException('native_staff_invite_denied');
        return hash_hmac('sha256','staff-invite:'.$id.':'.$token,$this->secret);
    }
    /** Never creates an invitation for an existing identity or from a tenant grant alone. */
    public function issueInOwned(int $i,int $a,int $id,string $requestKey,CompanyStaff $writer):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());NativeCompanyStaffInviteSchema::assertValid($this->db,$this->p);
        $writer->assertCreationRequestInOwned($this->db,$this->p,$i,$a,$id,$requestKey);
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey)||$this->rows('SELECT staff_id FROM '.$this->p.'company_native_staff_invites WHERE staff_id=?',[$id]))throw new DomainException('native_staff_invite_denied');
        $row=$this->identity($i,$a,$id);$token=bin2hex(random_bytes(32));$hash=$this->tokenHash($id,$token);$marker='s1'.substr($hash,0,30);$now=gmdate('Y-m-d H:i:s');$expires=gmdate('Y-m-d H:i:s',time()+48*3600);
        $receipt=['staff_id'=>$id,'issuer_id'=>$i,'actor_id'=>$a,'request_key'=>$requestKey,'identity_hash'=>$this->stamp($row),'token_hash'=>$hash,'marker'=>$marker,'queue_id'=>null,'expires_at'=>$expires,'used_at'=>null,'created_at'=>$now];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_staff_invites('.implode(',',array_keys($receipt)).') VALUES('.implode(',',array_fill(0,count($receipt),'?')).')',array_values($receipt));
        $saved=$this->rows('SELECT * FROM '.$this->p.'company_native_staff_invites WHERE staff_id=?',[$id])[0]??[];
        if(array_keys($saved)!==array_keys($receipt)||array_map(static fn($v)=>$v===null?null:(string)$v,$saved)!==array_map(static fn($v)=>$v===null?null:(string)$v,$receipt))throw new RuntimeException('native_staff_invite_unconfirmed');
        IssuerStorage::query($this->db,'UPDATE '.$this->p.'staff SET new_pass_key=?,new_pass_key_requested=? WHERE staffid=?',[$marker,$now,$id]);
        $current=$this->identity($i,$a,$id);if($current['new_pass_key']!==$marker||$current['new_pass_key_requested']!==$now||!hash_equals($receipt['identity_hash'],$this->stamp($current)))throw new RuntimeException('native_staff_invite_unconfirmed');
        return['token'=>$token,'marker'=>$marker,'requested'=>$now,'expires'=>$expires];
    }
    public function deliveryStateInOwned(int $id,?CompanyStaff $creatingWriter=null):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());
        $receipt=$this->rows('SELECT * FROM '.$this->p.'company_native_staff_invites WHERE staff_id=?',[$id])[0]??null;
        if(!$receipt||$receipt['used_at']!==null||$receipt['expires_at']<=gmdate('Y-m-d H:i:s'))throw new DomainException('native_staff_invite_denied');
        $row=$this->identity((int)$receipt['issuer_id'],(int)$receipt['actor_id'],$id);
        if(!hash_equals($receipt['identity_hash'],$this->stamp($row))||$row['new_pass_key']!==$receipt['marker']||$row['new_pass_key_requested']!==$receipt['created_at'])throw new DomainException('native_staff_invite_denied');
        if($creatingWriter!==null)$creatingWriter->assertCreationRequestInOwned($this->db,$this->p,(int)$receipt['issuer_id'],(int)$receipt['actor_id'],$id,$receipt['request_key']);
        else{$command=$this->rows('SELECT staff_id,operation FROM '.$this->p.'company_staff_commands WHERE issuer_id=? AND actor_id=? AND request_key=?',[$receipt['issuer_id'],$receipt['actor_id'],$receipt['request_key']])[0]??null;if(!$command||(int)$command['staff_id']!==$id||$command['operation']!=='create')throw new DomainException('native_staff_invite_denied');}
        return['receipt'=>$receipt,'identity'=>$row];
    }
    private function verified(int $id,string $token):array
    {
        $hash=$this->tokenHash($id,$token);$receipt=$this->rows('SELECT * FROM '.$this->p.'company_native_staff_invites WHERE staff_id=? AND token_hash=?',[$id,$hash])[0]??null;
        if(!$receipt||$receipt['used_at']!==null||$receipt['queue_id']===null||$receipt['expires_at']<=gmdate('Y-m-d H:i:s'))throw new DomainException('native_staff_invite_denied');
        $row=$this->identity((int)$receipt['issuer_id'],(int)$receipt['actor_id'],$id);
        if(!hash_equals($receipt['identity_hash'],$this->stamp($row))||$row['new_pass_key']!==$receipt['marker']||$row['new_pass_key_requested']!==$receipt['created_at'])throw new DomainException('native_staff_invite_denied');
        $command=$this->rows('SELECT staff_id,operation FROM '.$this->p.'company_staff_commands WHERE issuer_id=? AND actor_id=? AND request_key=?',[$receipt['issuer_id'],$receipt['actor_id'],$receipt['request_key']])[0]??null;
        if(!$command||(int)$command['staff_id']!==$id||$command['operation']!=='create')throw new DomainException('native_staff_invite_denied');
        require_once __DIR__.'/NativeCompanyStaffInviteMail.php';
        $mail=$this->rows('SELECT * FROM '.$this->p.'mail_queue WHERE id=? AND issuer_id=?',[$receipt['queue_id'],$receipt['issuer_id']])[0]??null;
        $fence=$this->rows('SELECT kind,binding_hash FROM '.$this->p.'company_native_mail_dispatch_fences WHERE queue_id=? AND issuer_id=?',[$receipt['queue_id'],$receipt['issuer_id']])[0]??null;
        if(!$mail||!$fence||$fence['kind']!=='staff_invite')throw new DomainException('native_staff_invite_denied');
        set_error_handler(static function(){throw new DomainException('native_staff_invite_denied');});try{$headers=unserialize($mail['headers'],['allowed_classes'=>false]);}finally{restore_error_handler();}$binding=is_array($headers)?($headers[NativeCompanyStaffInviteMail::HEADER]??null):null;
        if(!is_array($binding)||!hash_equals($fence['binding_hash'],NativeCompanyStaffInviteMail::bindingHash($binding))||$binding['staff']!==$id||$binding['issuer']!==(int)$receipt['issuer_id']||$binding['request_key']!==$receipt['request_key']||!hash_equals($binding['identity'],$receipt['identity_hash']))throw new DomainException('native_staff_invite_denied');
        unset($headers[NativeCompanyStaffInviteMail::HEADER]);$mail['headers']=serialize($headers);if(!hash_equals($binding['payload'],CompanyPortalMailIdentity::payload((object)$mail)))throw new DomainException('native_staff_invite_denied');
        return$receipt;
    }
    public function canComplete(int $id,string $token):bool
    {
        try{NativeCompanyStaffInviteSchema::assertValid($this->db,$this->p);$first=$this->verified($id,$token);return$first===$this->verified($id,$token);}catch(DomainException $e){return false;}
    }
    public function complete(int $id,string $token,string $password):bool
    {
        if(strlen($password)<12||strlen($password)>128||str_contains($password,"\0"))throw new DomainException('issuer_staff_invalid');
        if(!$this->canComplete($id,$token))throw new DomainException('native_staff_invite_denied');
        require_once __DIR__.'/../third_party/phpass.php';$hash=(new PasswordHash(8,true))->HashPassword($password);
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($id,$token,$hash){
            $first=$this->verified($id,$token);(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity((int)$first['issuer_id'],(int)$first['actor_id']);$receipt=$this->verified($id,$token);if($first!==$receipt)throw new DomainException('native_staff_invite_denied');
            $now=gmdate('Y-m-d H:i:s');IssuerStorage::query($this->db,'UPDATE '.$this->p.'staff SET password=?,new_pass_key=NULL,new_pass_key_requested=NULL,last_password_change=? WHERE staffid=? AND new_pass_key=?',[$hash,$now,$id,$receipt['marker']]);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_staff_invites SET used_at=? WHERE staff_id=? AND token_hash=? AND used_at IS NULL',[$now,$id,$receipt['token_hash']]);
            $row=$this->rows('SELECT password,new_pass_key,new_pass_key_requested,last_password_change FROM '.$this->p.'staff WHERE staffid=?',[$id])[0]??[];$saved=$this->rows('SELECT used_at FROM '.$this->p.'company_native_staff_invites WHERE staff_id=?',[$id])[0]??[];
            if($row!==['password'=>$hash,'new_pass_key'=>null,'new_pass_key_requested'=>null,'last_password_change'=>$now]||($saved['used_at']??null)!==$now)throw new RuntimeException('native_staff_invite_unconfirmed');
            $nonce=bin2hex(random_bytes(32));$metadata=json_encode(['nonce'=>$nonce],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$id,'native_staff_invite_completed',$receipt['issuer_id'],$id,$now,$metadata]);$audit=(int)$this->db->insert_id();$r=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE id=? AND event=? AND subject_id=?',[$audit,'native_staff_invite_completed',$id])[0]??[];if(($r['metadata_json']??null)!==$metadata)throw new RuntimeException('native_staff_invite_unconfirmed');
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can((int)$receipt['issuer_id'],(int)$receipt['actor_id'],'staff','create')||!$acl->member((int)$receipt['issuer_id'],$id))throw new DomainException('native_staff_invite_denied');$final=$this->identity((int)$receipt['issuer_id'],(int)$receipt['actor_id'],$id);if($final['password']!==$hash||$final['new_pass_key']!==null||$final['new_pass_key_requested']!==null)throw new RuntimeException('native_staff_invite_unconfirmed');return true;
        });
    }
    public function __debugInfo():array{return[];}
    public function __serialize():array{throw new LogicException('native_staff_invite_runtime_only');}

}
