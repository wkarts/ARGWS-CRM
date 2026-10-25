<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyNativeContacts.php';
require_once __DIR__.'/NativeCompanyConsentSchema.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
final class NativeCompanyConsents
{
    private IssuerResourceAccess $acl;
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->acl=new IssuerResourceAccess($db,$p);}
    public function tables():array{return array_merge(CompanyNativeContacts::TABLES,['company_native_consent_purposes','company_native_consents']);}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function admin(int$issuer,int$actor):void{if(!$this->acl->administrative($issuer,$actor))throw new DomainException('company_client_access_denied');}
    private function contact(int$issuer,int$actor,int$contact):array
    {
        $row=$this->rows('SELECT c.id,c.userid FROM '.$this->p.'contacts c JOIN '.$this->p.'issuers_clients o ON o.client_id=c.userid WHERE c.id=? AND o.owner_issuer_id=?',[$contact,$issuer])[0]??null;
        if(!$row||!(new CompanyCustomerAccess($this->db,$this->p))->can($issuer,$actor,(int)$row['userid'],'view'))throw new DomainException('company_client_access_denied');return$row;
    }
    private static function text($value,int$max,bool$empty=true):string{if(!is_string($value)||strlen($value)>$max||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value)||(!$empty&&trim($value)===''))throw new DomainException('native_consent_invalid');return$value;}
    private function purpose(int$issuer,$wire):array
    {
        $r=NativeCompanyResourceId::decode('consent_purpose',$wire);if($r['source_kind']!=='company_consent_purpose')throw new DomainException('company_client_access_denied');
        $row=$this->rows('SELECT * FROM '.$this->p.'company_native_consent_purposes WHERE issuer_id=? AND id=?',[$issuer,$r['source_id']])[0]??null;if(!$row)throw new DomainException('company_client_access_denied');return$row;
    }
    public function purposes(int$issuer,int$actor,?int$contact=null):array
    {
        if($contact===null)$this->admin($issuer,$actor);else$this->contact($issuer,$actor,$contact);
        NativeCompanyConsentSchema::assertValid($this->db,$this->p);$out=[];
        foreach($this->rows('SELECT * FROM '.$this->p.'company_native_consent_purposes WHERE issuer_id=? ORDER BY name DESC,id',[$issuer])as$row){
            $id=(int)$row['id'];$row['total_usage']=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_native_consents WHERE issuer_id=? AND purpose_id=?'.($contact===null?'':' AND contact_id=?'),$contact===null?[$issuer,$id]:[$issuer,$id,$contact])[0]['n'];
            $latest=$contact===null?null:($this->rows('SELECT record_json,created_at FROM '.$this->p.'company_native_consents WHERE issuer_id=? AND purpose_id=? AND contact_id=? ORDER BY id DESC LIMIT 1',[$issuer,$id,$contact])[0]??null);
            $state=$latest?json_decode($latest['record_json'],true,32,JSON_THROW_ON_ERROR):[];
            $row['consent_given']=($state['action']??null)==='opt-in'?1:0;$row['last_action_is_opt_out']=($state['action']??null)==='opt-out'?1:0;$row['consent_last_updated']=$latest['created_at']??null;$row['opt_in_purpose_description']=$state['opt_in_purpose_description']??'';
            $row['id']=NativeCompanyResourceId::encode('company_consent_purpose',$id);$out[]=$row;
        }
        if($contact===null)$this->admin($issuer,$actor);else$this->contact($issuer,$actor,$contact);return$out;
    }
    public function savePurpose(int$issuer,int$actor,array$input,$id=null,?int$revision=null):string
    {
        $this->admin($issuer,$actor);if(array_diff(array_keys($input),['name','description']))throw new DomainException('native_consent_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$input,$id,$revision):string{
            $this->admin($issuer,$actor);NativeCompanyConsentSchema::assertValid($this->db,$this->p);$old=$id===null?null:$this->purpose($issuer,$id);
            if($old&&(int)$old['revision']!==$revision)throw new DomainException('company_client_stale');
            $name=self::text($input['name']??$old['name']??null,191,false);$description=self::text($input['description']??'',10000);
            if($old&&$name!==$old['name']&&$this->rows('SELECT id FROM '.$this->p.'company_native_consents WHERE issuer_id=? AND purpose_id=? LIMIT 1',[$issuer,$old['id']]))throw new DomainException('native_consent_purpose_in_use');
            $next=$old?(int)$old['revision']+1:1;$nonce=bin2hex(random_bytes(32));$at=gmdate('Y-m-d H:i:s');
            if($old){$raw=(int)$old['id'];IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_consent_purposes SET name=?,description=?,revision=?,write_nonce=?,last_updated=? WHERE issuer_id=? AND id=?',[$name,$description,$next,$nonce,$at,$issuer,$raw]);}
            else{IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_consent_purposes(issuer_id,name,description,revision,write_nonce,date_created,last_updated) VALUES(?,?,?,?,?,?,?)',[$issuer,$name,$description,$next,$nonce,$at,$at]);$raw=(int)$this->db->insert_id();}
            $wire=NativeCompanyResourceId::encode('company_consent_purpose',$raw);$actual=$this->rows('SELECT * FROM '.$this->p.'company_native_consent_purposes WHERE issuer_id=? AND id=?',[$issuer,$raw])[0]??null;if(!$actual||$actual['name']!==$name||$actual['description']!==$description||(int)$actual['revision']!==$next||$actual['write_nonce']!==$nonce)throw new RuntimeException('issuer_consent_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$raw,'native_consent_purpose_saved');$this->admin($issuer,$actor);return$wire;
        });
    }
    public function getPurpose(int$issuer,int$actor,$id):array
    {
        $this->admin($issuer,$actor);foreach($this->purposes($issuer,$actor)as$purpose)if((string)$purpose['id']===(string)$id)return$purpose;throw new DomainException('company_client_access_denied');
    }
    public function deletePurpose(int$issuer,int$actor,$id,int$revision):void
    {
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$id,$revision):void{
            $this->admin($issuer,$actor);$old=$this->purpose($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_client_stale');
            if($this->rows('SELECT id FROM '.$this->p.'company_native_consents WHERE issuer_id=? AND purpose_id=? LIMIT 1',[$issuer,$old['id']]))throw new DomainException('native_consent_purpose_in_use');
            IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_native_consent_purposes WHERE issuer_id=? AND id=?',[$issuer,$old['id']]);
            if($this->rows('SELECT id FROM '.$this->p.'company_native_consent_purposes WHERE id=?',[$old['id']]))throw new RuntimeException('issuer_consent_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,(int)$old['id'],'native_consent_purpose_deleted');$this->admin($issuer,$actor);
        });
    }
    public function history(int$issuer,int$actor,int$contact):array
    {
        $this->contact($issuer,$actor,$contact);$out=[];
        foreach($this->rows('SELECT * FROM '.$this->p.'company_native_consents WHERE issuer_id=? AND contact_id=? ORDER BY id DESC',[$issuer,$contact])as$row){$record=json_decode($row['record_json'],true,32,JSON_THROW_ON_ERROR);$out[]=$record+['id'=>NativeCompanyResourceId::encode('company_consent',(int)$row['id']),'purpose_id'=>NativeCompanyResourceId::encode('company_consent_purpose',(int)$row['purpose_id']),'contact_id'=>$contact,'date'=>$row['created_at']];}
        $this->contact($issuer,$actor,$contact);return$out;
    }
    public function record(int$issuer,int$actor,int$contact,array$input,string$revision,string$ip):string
    {
        if(array_diff(array_keys($input),['action','purpose_id','purpose_revision','description','opt_in_purpose_description'])||!in_array($input['action']??null,['opt-in','opt-out'],true)||!filter_var($ip,FILTER_VALIDATE_IP))throw new DomainException('native_consent_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$contact,$input,$revision,$ip):string{
            $this->contact($issuer,$actor,$contact);$before=$this->history($issuer,$actor,$contact);if(!hash_equals(hash('sha256',json_encode($before,JSON_THROW_ON_ERROR)),$revision))throw new DomainException('company_client_stale');$purpose=$this->purpose($issuer,$input['purpose_id']);if(!isset($input['purpose_revision'])||!is_scalar($input['purpose_revision'])||(string)$purpose['revision']!==(string)$input['purpose_revision'])throw new DomainException('company_client_stale');
            $staff=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$actor])[0]??null;if(!$staff)throw new DomainException('company_client_access_denied');
            $record=['action'=>$input['action'],'purpose_name'=>$purpose['name'],'purpose_revision'=>(int)$purpose['revision'],'description'=>self::text($input['description']??'',10000),'opt_in_purpose_description'=>self::text($input['opt_in_purpose_description']??'',10000),'staff_name'=>trim($staff['firstname'].' '.$staff['lastname']),'ip'=>$ip];
            $json=json_encode($record,JSON_THROW_ON_ERROR);$nonce=bin2hex(random_bytes(32));$at=gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_consents(issuer_id,contact_id,purpose_id,record_json,nonce,created_at) VALUES(?,?,?,?,?,?)',[$issuer,$contact,$purpose['id'],$json,$nonce,$at]);$raw=(int)$this->db->insert_id();
            $actual=$this->rows('SELECT * FROM '.$this->p.'company_native_consents WHERE id=? AND issuer_id=? AND contact_id=?',[$raw,$issuer,$contact])[0]??null;if(!$actual||$actual['nonce']!==$nonce||$actual['record_json']!==$json||(int)$actual['purpose_id']!==(int)$purpose['id'])throw new RuntimeException('issuer_consent_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$contact,'native_contact_consent_recorded');$this->contact($issuer,$actor,$contact);return NativeCompanyResourceId::encode('company_consent',$raw);
        });
    }
}
