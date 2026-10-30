<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyHeaderSchema.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/** Owned events only. Global notification rows are neither queried nor adopted. */
final class NativeCompanyNotifications
{
    private $db;private string $p;
    public function __construct($db,string $prefix){if(!preg_match('/^[A-Za-z0-9_]+$/D',$prefix))throw new InvalidArgumentException('Invalid prefix');$this->db=$db;$this->p=$prefix;}
    public static function tables(string $kind):array
    {
        if(!in_array($kind,['reminder','announcement','privacy_request','registration','event','customer_file','task_status','estimate_decision'],true))throw new DomainException('issuer_notification_source_invalid');
        $base=['issuers_lock','issuers_registry','staff','issuers_staff','issuers_installation_identity','issuers_resource_permissions',NativeCompanyHeaderSchema::NOTIFICATIONS];
        if($kind==='task_status')return $base;
        if($kind==='estimate_decision')return array_merge($base,['issuers_audit','company_sales_documents','contacts','clients','issuers_clients']);
        if($kind==='event')return array_merge($base,['company_events']);
        if($kind==='customer_file')return array_merge($base,['files','company_customer_file_contents','contacts','clients','issuers_clients','company_customer_admins','customer_admins']);
        if($kind==='registration')return array_merge($base,['contacts','clients','issuers_clients','company_native_portal_registrations']);
        return array_merge($base,$kind==='privacy_request'?['contacts','clients','issuers_clients','gdpr_requests','company_native_gdpr_request_owners']:[$kind==='reminder'?'company_reminders':'company_announcements']);
    }
    /** Only source metadata selects dependencies; source/recipient authority is rechecked inside the transaction. */
    public function transactionTables(int $issuer,int $recipient,string $kind,int $sourceId):array
    {
        $this->member($issuer,$recipient);$tables=self::tables($kind);if($kind==='task_status'){require_once __DIR__.'/NativeCompanyTaskStatusNotice.php';return array_values(array_unique(array_merge($tables,NativeCompanyTaskStatusNotice::tables($this->db,$this->p))));}
        if($kind==='reminder'){
            $row=IssuerStorage::query($this->db,'SELECT rel_type FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=? AND staff=?',[$issuer,$sourceId,$recipient])->row_array();
            if($row){require_once __DIR__.'/NativeCompanyResourceReferences.php';$tables=array_values(array_unique(array_merge($tables,NativeCompanyResourceReferences::transactionTables($this->db,$this->p,(string)$row['rel_type']))));}
        }
        $this->member($issuer,$recipient);return $tables;
    }
    private function member(int $issuer,int $recipient):void
    {
        if(!(new IssuerResourceAccess($this->db,$this->p))->member($issuer,$recipient))throw new DomainException('issuer_access_denied');
    }
    private function source(int $issuer,int $recipient,string $kind,int $id):array
    {
        $this->member($issuer,$recipient);
        if($kind==='task_status'){require_once __DIR__.'/NativeCompanyTaskStatusNotice.php';return(new NativeCompanyTaskStatusNotice($this->db,$this->p))->source($issuer,$recipient,$id);}
        if($kind==='estimate_decision'){require_once __DIR__.'/NativeCompanyEstimateDecisionNotice.php';return(new NativeCompanyEstimateDecisionNotice($this->db,$this->p))->source($issuer,$recipient,$id);}
        if($kind==='reminder'){
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=? AND staff=?',[$issuer,$id,$recipient])->row_array();
            if(!$row)throw new DomainException('issuer_notification_source_denied');
            require_once __DIR__.'/NativeCompanyResourceReferences.php';
            $resource=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$recipient,(string)$row['rel_type'],(string)$row['rel_id'],'view');
            if($resource['source_kind']!==$row['source_kind']||(string)$resource['source_id']!==(string)$row['source_id'])throw new DomainException('issuer_notification_source_denied');
            return ['actor_id'=>(int)$row['creator'],'resource_id'=>(string)$resource['canonical_id'],'url'=>$resource['url'],'title'=>(string)$resource['name'],'body'=>(string)$row['description'],'revision'=>(int)$row['revision']];
        }
        if($kind==='event'){
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$recipient,'calendar','view'))throw new DomainException('issuer_notification_source_denied');
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_events WHERE issuer_id=? AND eventid=? AND (userid=? OR public=1)',[$issuer,$id,$recipient])->row_array();
            if(!$row||!$acl->can($issuer,$recipient,'calendar','view'))throw new DomainException('issuer_notification_source_denied');
            $canonical=NativeCompanyResourceId::encode('company_event',$id);
            return ['actor_id'=>(int)$row['userid'],'resource_id'=>$canonical,'url'=>'utilities/calendar?eventid='.$canonical,'title'=>(string)$row['title'],'body'=>(string)$row['description'],'revision'=>(int)$row['revision']];
        }
        if($kind==='announcement'){
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_announcements WHERE issuer_id=? AND announcementid=? AND showtostaff=1',[$issuer,$id])->row_array();
            if(!$row)throw new DomainException('issuer_notification_source_denied');
            return ['actor_id'=>(int)$row['actor_id'],'resource_id'=>NativeCompanyResourceId::encode('company_announcement',$id),'url'=>'announcements/view/'.NativeCompanyResourceId::encode('company_announcement',$id),'title'=>(string)$row['name'],'body'=>trim(strip_tags((string)$row['message'])),'revision'=>(int)$row['revision']];
        }
        if($kind==='customer_file'){
            $row=$this->customerFile($issuer,$id);require_once __DIR__.'/CompanyCustomerAccess.php';$access=new CompanyCustomerAccess($this->db,$this->p);
            if(!$access->can($issuer,$recipient,(int)$row['client_id'],'view')&&!$access->canReadHistorical($issuer,$recipient,(int)$row['client_id']))throw new DomainException('issuer_notification_source_denied');
            return ['actor_id'=>null,'actor_kind'=>'contact','contact_actor_id'=>(int)$row['contact_id'],'resource_id'=>(string)$id,'url'=>'clients/client/'.$row['client_id'].'?group=attachments','title'=>'Novo arquivo de cliente','body'=>(string)$row['file_name'],'revision'=>(int)$row['revision']];
        }
        if($kind==='registration'){
            if(!(new IssuerResourceAccess($this->db,$this->p))->administrative($issuer,$recipient))throw new DomainException('issuer_notification_source_denied');
            $row=$this->registration($issuer,$id);
            return ['actor_id'=>null,'actor_kind'=>'contact','contact_actor_id'=>(int)$row['contact_id'],'resource_id'=>(string)$row['contact_id'],'url'=>'clients/client/'.$row['client_id'].'?contactid='.$row['contact_id'],'title'=>'Novo registro de cliente','body'=>'Registro recebido para revisão administrativa.','revision'=>1];
        }
        if($kind==='privacy_request'){
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->administrative($issuer,$recipient))throw new DomainException('issuer_notification_source_denied');
            $row=$this->privacyRequest($issuer,$id);
            return ['actor_id'=>null,'actor_kind'=>'contact','contact_actor_id'=>(int)$row['contact_id'],'resource_id'=>(string)$row['contact_id'],'url'=>'gdpr/index?page=forgotten','title'=>'Solicitação de privacidade','body'=>'Solicitação de remoção de conta #'.$id,'revision'=>1];
        }
        throw new DomainException('issuer_notification_source_invalid');
    }
    /** Read metadata only; a contact actor must be tied to the exact canonical file and owned receipt. */
    private function customerFile(int $issuer,int $file):array
    {
        require_once __DIR__.'/NativeCompanyCustomerFileSchema.php';NativeCompanyCustomerFileSchema::assertValid($this->db,$this->p);
        $row=IssuerStorage::query($this->db,'SELECT f.id,f.rel_id,f.rel_type,f.staffid,f.contact_id,f.file_name,f.visible_to_customer,o.issuer_id,o.client_id,o.request_key,o.request_hash,o.content_hash,o.write_nonce,o.revision,o.active FROM '.$this->p.'files f JOIN '.$this->p.'company_customer_file_contents o ON o.file_id=f.id WHERE f.id=? AND o.issuer_id=?',[$file,$issuer])->row_array();
        if(!$row||$row['rel_type']!=='customer'||(int)$row['staffid']!==0||(int)$row['contact_id']<1||(int)$row['rel_id']!==(int)$row['client_id']||(int)$row['client_id']<1||(int)$row['revision']<1||(int)$row['active']!==1||(int)$row['visible_to_customer']!==1)throw new DomainException('issuer_notification_source_denied');
        foreach(['request_key','request_hash','content_hash','write_nonce']as$key)if(!is_string($row[$key])||!preg_match('/^[a-f0-9]{64}$/D',$row[$key]))throw new DomainException('issuer_notification_source_denied');
        $contact=IssuerStorage::query($this->db,'SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'clients v ON v.userid=c.userid WHERE c.id=? AND c.userid=?',[$row['contact_id'],$row['client_id']])->row_array();
        $owner=IssuerStorage::query($this->db,'SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$row['client_id']])->row_array();
        if(!$contact||($owner?(int)$owner['owner_issuer_id']!==$issuer:(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer))throw new DomainException('issuer_notification_source_denied');return$row;
    }
    /** Only the actual portal INSERT/readback capability may emit this event. No historical adoption. */
    public function recordCustomerFileInOwnedTransaction(int $issuer,int $file,NativePortalCustomerFiles $upload):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables('customer_file'));NativeCompanyHeaderSchema::assertValid($this->db,$this->p);
        $upload->assertUploadedInOwned($this->db,$this->p,$issuer,$file);$before=$this->customerFile($issuer,$file);$ids=[];require_once __DIR__.'/CompanyCustomerAccess.php';$access=new CompanyCustomerAccess($this->db,$this->p);
        $candidates=IssuerStorage::query($this->db,'SELECT s.staffid FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid)) ORDER BY s.staffid',[$issuer])->result_array();
        foreach($candidates as$candidate){$recipient=(int)$candidate['staffid'];if(!$access->can($issuer,$recipient,(int)$before['client_id'],'view')&&!$access->canReadHistorical($issuer,$recipient,(int)$before['client_id']))continue;
            $source=$this->source($issuer,$recipient,'customer_file',$file);$event='customer_file:'.$file.':uploaded';$params=[$issuer,$recipient,$event];$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)->row_array();
            if(!$row){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' (issuer_id,recipient_id,actor_kind,actor_id,contact_actor_id,source_kind,source_id,resource_id,event_key,title,body,created_at) VALUES(?,?,?,NULL,?,?,?,?,?,?,?,?)',[$issuer,$recipient,'contact',$before['contact_id'],'customer_file',$file,$source['resource_id'],$event,$source['title'],$source['body'],gmdate('Y-m-d H:i:s')]);$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)->row_array();}
            if(!$row)throw new RuntimeException('issuer_notification_write_unconfirmed');self::actor($row);
            foreach(['actor_kind'=>'contact','contact_actor_id'=>$before['contact_id'],'source_kind'=>'customer_file','source_id'=>$file,'resource_id'=>$source['resource_id'],'title'=>$source['title'],'body'=>$source['body']]as$key=>$value)if((string)($row[$key]??'')!==(string)$value)throw new DomainException('issuer_notification_event_conflict');
            $this->source($issuer,$recipient,'customer_file',$file);$ids[]=(int)$row['id'];
        }
        $upload->assertUploadedInOwned($this->db,$this->p,$issuer,$file);if($before!==$this->customerFile($issuer,$file))throw new DomainException('issuer_notification_source_denied');return$ids;
    }
    private function privacyRequest(int $issuer,int $id):array
    {
        $row=IssuerStorage::query($this->db,'SELECT r.id,r.contact_id,r.clientid,r.lead_id,r.request_type,o.source_kind,o.source_id FROM '.$this->p.'gdpr_requests r JOIN '.$this->p.'company_native_gdpr_request_owners o ON o.request_id=r.id WHERE o.issuer_id=? AND r.id=?',[$issuer,$id])->row_array();
        if(!$row||$row['source_kind']!=='contact'||(int)$row['source_id']!==(int)$row['contact_id']||(int)$row['lead_id']!==0||$row['request_type']!=='account_removal')throw new DomainException('issuer_notification_source_denied');
        $contact=IssuerStorage::query($this->db,'SELECT userid FROM '.$this->p.'contacts WHERE id=? AND userid=?',[$row['contact_id'],$row['clientid']])->row_array();
        $client=IssuerStorage::query($this->db,'SELECT userid FROM '.$this->p.'clients WHERE userid=?',[$row['clientid']])->row_array();
        $owner=IssuerStorage::query($this->db,'SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$row['clientid']])->row_array();
        if(!$contact||!$client||($owner?(int)$owner['owner_issuer_id']!==$issuer:(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer))throw new DomainException('issuer_notification_source_denied');
        return$row;
    }
    /** A receipt is mandatory even for an inactive contact awaiting confirmation; no historical adoption. */
    private function registration(int $issuer,int $contact):array
    {
        $rows=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_native_portal_registrations WHERE issuer_id=? AND contact_id=?',[$issuer,$contact])->result_array();
        if(count($rows)!==1)throw new DomainException('issuer_notification_source_denied');$row=$rows[0];
        foreach(['request_key','input_hash','nonce'] as$key)if(!is_string($row[$key]??null)||!preg_match('/^[a-f0-9]{64}$/D',$row[$key]))throw new DomainException('issuer_notification_source_denied');
        if((int)$row['client_id']<1||(int)$row['contact_id']<1)throw new DomainException('issuer_notification_source_denied');
        $bound=IssuerStorage::query($this->db,'SELECT c.id FROM '.$this->p.'contacts c JOIN '.$this->p.'clients v ON v.userid=c.userid JOIN '.$this->p.'issuers_clients o ON o.client_id=v.userid WHERE c.id=? AND c.userid=? AND o.owner_issuer_id=?',[$contact,$row['client_id'],$issuer])->result_array();
        if(count($bound)!==1)throw new DomainException('issuer_notification_source_denied');return$row;
    }
    /** Atomic with the real registration receipt. Contact is the actor, never a synthetic staff member. */
    public function recordRegistrationInOwnedTransaction(int $issuer,string $requestKey):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables('registration'));
        require_once __DIR__.'/NativePortalRegistrationSchema.php';NativePortalRegistrationSchema::assertValid($this->db,$this->p);
        if(!preg_match('/^[a-f0-9]{64}$/D',$requestKey))throw new DomainException('issuer_notification_source_denied');
        $receipt=IssuerStorage::query($this->db,'SELECT contact_id FROM '.$this->p.'company_native_portal_registrations WHERE issuer_id=? AND request_key=?',[$issuer,$requestKey])->row_array();
        if(!$receipt)throw new DomainException('issuer_notification_source_denied');$contact=(int)$receipt['contact_id'];$before=$this->registration($issuer,$contact);if(!hash_equals($requestKey,$before['request_key']))throw new DomainException('issuer_notification_source_denied');$ids=[];
        $acl=new IssuerResourceAccess($this->db,$this->p);
        $candidates=IssuerStorage::query($this->db,'SELECT s.staffid FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid)) ORDER BY s.staffid',[$issuer])->result_array();
        foreach($candidates as$candidate){$recipient=(int)$candidate['staffid'];if(!$acl->administrative($issuer,$recipient))continue;
            $source=$this->source($issuer,$recipient,'registration',$contact);$event='registration:'.$contact.':created';
            $params=[$issuer,$recipient,$event];$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)->row_array();
            if(!$row){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' (issuer_id,recipient_id,actor_kind,actor_id,contact_actor_id,source_kind,source_id,resource_id,event_key,title,body,created_at) VALUES(?,?,?,NULL,?,?,?,?,?,?,?,?)',[$issuer,$recipient,'contact',$contact,'registration',$contact,$source['resource_id'],$event,$source['title'],$source['body'],gmdate('Y-m-d H:i:s')]);$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)->row_array();}
            if(!$row)throw new RuntimeException('issuer_notification_write_unconfirmed');self::actor($row);
            foreach(['actor_kind'=>'contact','contact_actor_id'=>$contact,'source_kind'=>'registration','source_id'=>$contact,'resource_id'=>$source['resource_id'],'title'=>$source['title'],'body'=>$source['body']]as$key=>$value)if((string)($row[$key]??'')!==(string)$value)throw new DomainException('issuer_notification_event_conflict');
            $this->source($issuer,$recipient,'registration',$contact);$ids[]=(int)$row['id'];
        }
        if($before!==$this->registration($issuer,$contact))throw new DomainException('issuer_notification_source_denied');return$ids;
    }
    private static function actor(array $row):void
    {
        if(!array_key_exists('actor_id',$row)||!array_key_exists('contact_actor_id',$row))throw new DomainException('issuer_notification_actor_invalid');
        $kind=$row['actor_kind']??null;
        if($kind==='staff'&&isset($row['actor_id'])&&$row['contact_actor_id']===null&&filter_var($row['actor_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])!==false)return;
        if($kind==='contact'&&$row['actor_id']===null&&isset($row['contact_actor_id'])&&filter_var($row['contact_actor_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])!==false)return;
        throw new DomainException('issuer_notification_actor_invalid');
    }
    /** Source writer must enlist this table plus all resource resolver tables before its transaction. */
    public function recordInOwnedTransaction(int $issuer,int $actor,int $recipient,string $kind,int $sourceId):int
    {
        if(!in_array($kind,['reminder','announcement','event','task_status'],true))throw new DomainException('issuer_notification_source_invalid');
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables($kind));
        if($kind==='task_status'){require_once __DIR__.'/NativeCompanyTaskStatusNotice.php';IssuerTransaction::assertOwned($this->db,$this->p,NativeCompanyTaskStatusNotice::tables($this->db,$this->p));}
        $this->member($issuer,$actor);
        $source=$this->source($issuer,$recipient,$kind,$sourceId);
        if($source['actor_id']!==$actor)throw new DomainException('issuer_notification_actor_denied');
        $event=$kind.':'.$sourceId.':'.$source['revision'];
        $title=mb_substr($source['title'],0,191,'UTF-8');$body=mb_substr($source['body'],0,2000,'UTF-8');
        foreach([$title,$body] as $text)if(!preg_match('//u',$text)||str_contains($text,"\0"))throw new DomainException('issuer_notification_text_invalid');
        $params=[$issuer,$recipient,$event];
        $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)->row_array();
        if(!$row){
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' (issuer_id,recipient_id,actor_kind,actor_id,contact_actor_id,source_kind,source_id,resource_id,event_key,title,body,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$issuer,$recipient,'staff',$actor,null,$kind,$sourceId,$source['resource_id'],$event,$title,$body,gmdate('Y-m-d H:i:s')]);
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',$params)->row_array();
        }
        $expected=['issuer_id'=>$issuer,'recipient_id'=>$recipient,'actor_kind'=>'staff','actor_id'=>$actor,'contact_actor_id'=>null,'source_kind'=>$kind,'source_id'=>$sourceId,'resource_id'=>$source['resource_id'],'event_key'=>$event,'title'=>$title,'body'=>$body];
        if(!$row)throw new RuntimeException('issuer_notification_write_unconfirmed');
        foreach($expected as $key=>$value)if((string)($row[$key]??'')!==(string)$value)throw new DomainException('issuer_notification_event_conflict');
        if(in_array($kind,['event','task_status'],true)&&$this->source($issuer,$recipient,$kind,$sourceId)!==$source)throw new DomainException('issuer_notification_source_denied');
        self::actor($row);return (int)$row['id'];
    }
    /** Enlist with the real contact request; delivery becomes visible only with its commit. No SMTP side effect. */
    public function recordPrivacyRequestInOwnedTransaction(int $issuer,int $requestId):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables('privacy_request'));
        $request=$this->privacyRequest($issuer,$requestId);$acl=new IssuerResourceAccess($this->db,$this->p);$ids=[];
        $candidates=IssuerStorage::query($this->db,'SELECT s.staffid FROM '.$this->p.'staff s WHERE s.active=1 AND (EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity i WHERE i.id=1 AND i.master_staff_id=s.staffid)) ORDER BY s.staffid',[$issuer])->result_array();
        foreach($candidates as$candidate){$recipient=(int)$candidate['staffid'];if(!$acl->administrative($issuer,$recipient))continue;
            $source=$this->source($issuer,$recipient,'privacy_request',$requestId);$event='privacy_request:'.$requestId.':requested';
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',[$issuer,$recipient,$event])->row_array();
            if(!$row){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' (issuer_id,recipient_id,actor_kind,actor_id,contact_actor_id,source_kind,source_id,resource_id,event_key,title,body,created_at) VALUES(?,?,?,NULL,?,?,?,?,?,?,?,?)',[$issuer,$recipient,'contact',$request['contact_id'],'privacy_request',$requestId,$source['resource_id'],$event,$source['title'],$source['body'],gmdate('Y-m-d H:i:s')]);$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND event_key=?',[$issuer,$recipient,$event])->row_array();}
            self::actor($row);$expected=['actor_kind'=>'contact','contact_actor_id'=>$request['contact_id'],'source_kind'=>'privacy_request','source_id'=>$requestId,'resource_id'=>$source['resource_id'],'title'=>$source['title'],'body'=>$source['body']];
            foreach($expected as$k=>$v)if((string)($row[$k]??'')!==(string)$v)throw new DomainException('issuer_notification_event_conflict');
            $this->source($issuer,$recipient,'privacy_request',$requestId);$ids[]=(int)$row['id'];
        }
        $this->privacyRequest($issuer,$requestId);return$ids;
    }
    public function page(int $issuer,int $recipient,int $page=0,int $limit=15):array
    {
        if($page<0||$limit<1||$limit>100||$page>intdiv(PHP_INT_MAX,$limit))throw new DomainException('issuer_notification_page_invalid');
        $this->member($issuer,$recipient);
        $rows=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? ORDER BY id DESC',[$issuer,$recipient])->result_array();
        $visible=[];$unread=0;$proof=[];
        foreach($rows as $row){
            self::actor($row);
            try{$source=$this->source($issuer,$recipient,(string)$row['source_kind'],(int)$row['source_id']);}
            catch(DomainException $denied){continue;} // Revoked/deleted sources are never disclosed by notification history.
            if((string)$source['resource_id']!==(string)$row['resource_id'])continue;
            if(($source['actor_kind']??'staff')!==$row['actor_kind']||($row['actor_kind']==='staff'?(string)$source['actor_id']!==(string)$row['actor_id']:(string)$source['contact_actor_id']!==(string)$row['contact_actor_id']))throw new DomainException('issuer_notification_actor_invalid');
            if($row['read_at']===null)$unread++;
            $proof[]=[$row['source_kind'],(int)$row['source_id'],$source];$row['url']=$source['url'];$visible[]=$row;
        }
        if($rows!==IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? ORDER BY id DESC',[$issuer,$recipient])->result_array())throw new DomainException('issuer_notification_changed');foreach($proof as[$kind,$id,$source])if($this->source($issuer,$recipient,$kind,$id)!==$source)throw new DomainException('issuer_notification_changed');$this->member($issuer,$recipient);return ['items'=>array_slice($visible,$page*$limit,$limit),'unread'=>$unread,'total'=>count($visible)];
    }
    public function read(int $issuer,int $recipient):array{$page=$this->page($issuer,$recipient,0,50);return ['items'=>$page['items'],'unread'=>$page['unread']];}
    /** Controller enforces central native CSRF/context; GET callers must not invoke this method. */
    public function markRead(int $issuer,int $recipient,?int $id=null,string $mode='inline'):array
    {
        $tables=array_values(array_unique(array_merge(self::tables('reminder'),self::tables('announcement'),self::tables('privacy_request'))));
        $this->member($issuer,$recipient);
        $sources=IssuerStorage::query($this->db,'SELECT source_kind,source_id FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=?',[$issuer,$recipient])->result_array();
        foreach($sources as$source)$tables=array_values(array_unique(array_merge($tables,$this->transactionTables($issuer,$recipient,(string)$source['source_kind'],(int)$source['source_id']))));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$recipient,$id,$mode){
            $this->member($issuer,$recipient);
            if(!in_array($mode,['seen','inline','all'],true))throw new DomainException('issuer_notification_invalid');
            $columns=$mode==='all'?['read_at','inline_read_at']:[$mode==='seen'?'read_at':'inline_read_at'];
            if($id!==null){
                if($id<1)throw new DomainException('issuer_notification_invalid');
                $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND id=?',[$issuer,$recipient,$id])->row_array();
                if(!$row)throw new DomainException('issuer_notification_source_denied');
                $source=$this->source($issuer,$recipient,$row['source_kind'],(int)$row['source_id']);
                if((string)$source['resource_id']!==(string)$row['resource_id'])throw new DomainException('issuer_notification_source_denied');
            }
            foreach($columns as $column){
            $sql='UPDATE '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' SET '.$column.'=? WHERE issuer_id=? AND recipient_id=? AND '.$column.' IS NULL';$params=[gmdate('Y-m-d H:i:s'),$issuer,$recipient];
            if($id!==null){$sql.=' AND id=?';$params[]=$id;}
            IssuerStorage::query($this->db,$sql,$params);
            $verify='SELECT id FROM '.$this->p.NativeCompanyHeaderSchema::NOTIFICATIONS.' WHERE issuer_id=? AND recipient_id=? AND '.$column.' IS NULL';$bindings=[$issuer,$recipient];if($id!==null){$verify.=' AND id=?';$bindings[]=$id;}
            if(IssuerStorage::query($this->db,$verify,$bindings)->result_array())throw new RuntimeException('issuer_notification_write_unconfirmed');
            }
            return $this->read($issuer,$recipient);
        });
    }
}
