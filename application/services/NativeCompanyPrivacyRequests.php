<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyPortal.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/NativeCompanyPrivacySchema.php';
require_once __DIR__.'/NativeCompanyPrivacyMail.php';
require_once __DIR__.'/NativeCompanyCustomerAudit.php';
require_once __DIR__.'/NativeCompanyNotifications.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
/** Existing native removal-request workflow. A status change is not authority to erase financial data. */
final class NativeCompanyPrivacyRequests
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public function tables():array{return array_values(array_unique(array_merge((new NativeCompanyPrivacyMail($this->db,$this->p))->tables(),NativeCompanyNotifications::tables('privacy_request'),['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','issuers_clients','issuers_audit','staff','contacts','clients','gdpr_requests','company_native_gdpr_request_owners'])));}
    private function admin(int$issuer,int$actor):void{if(!(new IssuerResourceAccess($this->db,$this->p))->administrative($issuer,$actor))throw new DomainException('company_client_access_denied');}
    public function request(CompanyPortal$portal,array$binding,int$issuer,string$description,string$key):int
    {
        $portal->assertStorageBinding($this->db,$this->p);if(strlen($description)>10000||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$description)||!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('native_privacy_invalid');
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($portal,$binding,$issuer,$description,$key):int{
            NativeCompanyPrivacySchema::assertValid($this->db,$this->p);$who=$portal->invoiceIdentity($binding,$issuer,true);if(!$who)throw new DomainException('native_privacy_denied');
            $storage=new CompanySettingsStorage($this->db,$this->p);foreach(['enable_gdpr','gdpr_contact_enable_right_to_be_forgotten']as$name)if($storage->get($issuer,$name)!=='1')throw new DomainException('native_privacy_disabled');
            $nonce=hash('sha256',json_encode([$issuer,$who['contact_id'],$key],JSON_THROW_ON_ERROR));$hash=hash('sha256',json_encode([$issuer,$who['client_id'],$who['contact_id'],$description],JSON_THROW_ON_ERROR));
            $prior=$this->rows('SELECT * FROM '.$this->p.'company_native_gdpr_request_owners WHERE nonce=?',[$nonce])[0]??null;if($prior){if((int)$prior['issuer_id']!==$issuer||(int)$prior['source_id']!==$who['contact_id']||$prior['source_kind']!=='contact'||!hash_equals($prior['input_hash'],$hash))throw new DomainException('native_privacy_request_conflict');$actual=$this->owned($issuer,(int)$prior['request_id']);if($actual['description']!==$description||$actual['request_type']!=='account_removal')throw new RuntimeException('issuer_privacy_write_unconfirmed');return(int)$prior['request_id'];}
            $at=gmdate('Y-m-d H:i:s');$name=trim($who['firstname'].' '.$who['lastname']);if(strlen($name)>150)throw new DomainException('native_privacy_invalid');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'gdpr_requests(clientid,contact_id,lead_id,request_type,status,request_date,request_from,description) VALUES(?,?,0,?,?,?,?,?)',[$who['client_id'],$who['contact_id'],'account_removal','pending',$at,$name,$description]);$id=(int)$this->db->insert_id();$connection=$this->db->pdo??$this->db->conn_id??null;$countSql=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite'?'SELECT changes() n':'SELECT ROW_COUNT() n';if((int)($this->rows($countSql)[0]['n']??0)!==1)throw new RuntimeException('issuer_privacy_write_unconfirmed');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_gdpr_request_owners(request_id,issuer_id,source_kind,source_id,revision,nonce,input_hash) VALUES(?,?,?,?,1,?,?)',[$id,$issuer,'contact',$who['contact_id'],$nonce,$hash]);
            $actual=$this->owned($issuer,$id);if($actual['nonce']!==$nonce||$actual['input_hash']!==$hash||$actual['description']!==$description||(int)$actual['clientid']!==$who['client_id']||(int)$actual['contact_id']!==$who['contact_id']||$actual['status']!=='pending'||$actual['request_date']!==$at)throw new RuntimeException('issuer_privacy_write_unconfirmed');
            NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,0,$id,'native_contact_privacy_requested');(new NativeCompanyNotifications($this->db,$this->p))->recordPrivacyRequestInOwnedTransaction($issuer,$id);(new NativeCompanyPrivacyMail($this->db,$this->p))->prepareInOwned($issuer,$id);if(!$portal->invoiceIdentity($binding,$issuer,true))throw new DomainException('native_privacy_denied');return$id;
        });
    }
    private function legacy(int$issuer,int$id,bool$lock=false):?array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if($acl->primaryId()!==$issuer)return null;
        $r=$this->rows('SELECT r.* FROM '.$this->p.'gdpr_requests r JOIN '.$this->p.'contacts c ON c.id=r.contact_id AND c.userid=r.clientid JOIN '.$this->p.'clients u ON u.userid=c.userid LEFT JOIN '.$this->p.'issuers_clients x ON x.client_id=u.userid WHERE r.id=? AND r.lead_id=0 AND r.request_type=? AND (x.client_id IS NULL OR x.owner_issuer_id=?) AND NOT EXISTS (SELECT 1 FROM '.$this->p.'company_native_gdpr_request_owners o WHERE o.request_id=r.id)'.($lock?' FOR UPDATE':''),[$id,'account_removal',$issuer])[0]??null;
        if(!$r)return null;ksort($r);return$r+['issuer_id'=>$issuer,'source_kind'=>'contact','source_id'=>(int)$r['contact_id'],'revision'=>hash('sha256',json_encode($r,JSON_THROW_ON_ERROR)),'nonce'=>null,'input_hash'=>null];
    }
    private function owned(int$issuer,int$id,bool$lock=false):array
    {
        $row=$this->rows('SELECT r.*,o.issuer_id,o.source_kind,o.source_id,o.revision,o.nonce,o.input_hash FROM '.$this->p.'gdpr_requests r JOIN '.$this->p.'company_native_gdpr_request_owners o ON o.request_id=r.id WHERE o.issuer_id=? AND r.id=?'.($lock?' FOR UPDATE':''),[$issuer,$id])[0]??null;
        if(!$row)$row=$this->legacy($issuer,$id,$lock);
        if(!$row||$row['source_kind']!=='contact'||(int)$row['contact_id']!==(int)$row['source_id']||(int)$row['lead_id']!==0||!$this->rows('SELECT c.id FROM '.$this->p.'contacts c LEFT JOIN '.$this->p.'issuers_clients x ON x.client_id=c.userid LEFT JOIN '.$this->p.'issuers_installation_identity i ON i.id=1 JOIN '.$this->p.'issuers_registry r ON r.id=COALESCE(x.owner_issuer_id,i.primary_issuer_id) WHERE c.id=? AND c.userid=? AND r.id=?',[$row['source_id'],$row['clientid'],$issuer]))throw new DomainException('native_privacy_denied');return$row;
    }
    public function list(int$issuer,int$actor):array
    {
        $this->admin($issuer,$actor);NativeCompanyPrivacySchema::assertValid($this->db,$this->p);$out=[];
        $ids=array_map('intval',array_column($this->rows('SELECT request_id FROM '.$this->p.'company_native_gdpr_request_owners WHERE issuer_id=? ORDER BY request_id DESC',[$issuer]),'request_id'));
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer)foreach($this->rows('SELECT r.id FROM '.$this->p.'gdpr_requests r JOIN '.$this->p.'contacts c ON c.id=r.contact_id AND c.userid=r.clientid JOIN '.$this->p.'clients u ON u.userid=c.userid LEFT JOIN '.$this->p.'issuers_clients x ON x.client_id=u.userid WHERE r.lead_id=0 AND r.request_type=? AND (x.client_id IS NULL OR x.owner_issuer_id=?) AND NOT EXISTS (SELECT 1 FROM '.$this->p.'company_native_gdpr_request_owners o WHERE o.request_id=r.id)', ['account_removal',$issuer])as$r)$ids[]=(int)$r['id'];
        rsort($ids,SORT_NUMERIC);foreach(array_unique($ids)as$id){$record=$this->owned($issuer,$id);unset($record['nonce'],$record['input_hash']);$out[]=$record;}$this->admin($issuer,$actor);return$out;
    }
    public function status(int$issuer,int$actor,int$id,string$status,int|string$revision):void
    {
        if(!in_array($status,['pending','removed','refused'],true))throw new DomainException('native_privacy_invalid');
        IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$id,$status,$revision):void{
            $this->admin($issuer,$actor);$old=$this->owned($issuer,$id,true);if(!hash_equals((string)$old['revision'],(string)$revision))throw new DomainException('company_client_stale');
            if($old['nonce']===null){
                $nonce=hash('sha256','native-privacy-primary-adoption:'.$issuer.':'.$id);$hash=(string)$old['revision'];
                IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_native_gdpr_request_owners(request_id,issuer_id,source_kind,source_id,revision,nonce,input_hash) VALUES(?,?,?,?,1,?,?)',[$id,$issuer,'contact',$old['source_id'],$nonce,$hash]);
                $adopted=$this->owned($issuer,$id,true);if($adopted['nonce']!==$nonce||$adopted['input_hash']!==$hash||(int)$adopted['revision']!==1)throw new RuntimeException('issuer_privacy_write_unconfirmed');
                NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,'native_primary_privacy_ownership_confirmed');$old=$adopted;$revision=1;
            }
            if($old['status']===$status)return;
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'gdpr_requests SET status=? WHERE id=?',[$status,$id]);IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_native_gdpr_request_owners SET revision=revision+1 WHERE issuer_id=? AND request_id=?',[$issuer,$id]);
            $actual=$this->owned($issuer,$id);if($actual['status']!==$status||(int)$actual['revision']!==(int)$revision+1)throw new RuntimeException('issuer_privacy_write_unconfirmed');NativeCompanyCustomerAudit::record($this->db,$this->p,$issuer,$actor,$id,'native_contact_privacy_status');$this->admin($issuer,$actor);
        });
    }
}
