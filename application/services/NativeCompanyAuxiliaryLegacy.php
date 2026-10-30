<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/issuers/IssuerTransaction.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';

/** Historical rows retain their original tables/IDs and are visible only in the proven principal. */
final class NativeCompanyAuxiliaryLegacy
{
    private IssuerResourceAccess $access;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor)
    {if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');$this->access=new IssuerResourceAccess($db,$p);$this->assertOwner();}
    public static function eligible($db,string $p,int $issuer,int $actor):bool{$acl=new IssuerResourceAccess($db,$p);return$acl->primaryId()===$issuer&&$acl->member($issuer,$actor);}
    private function assertOwner():void{if(!self::eligible($this->db,$this->p,$this->issuer,$this->actor))throw new DomainException('company_auxiliary_access_denied');}
    private function audit(string $event,int $id):void
    {
        $meta=json_encode(['version'=>1,'source'=>'native_legacy_auxiliary','operation_nonce'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,metadata_json,created_at) VALUES(?,?,?,?,?,?)',[$this->issuer,$this->actor,$id,$event,$meta,gmdate('Y-m-d H:i:s')]);
        $audit=(int)$this->db->insert_id();if(!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND metadata_json=?',[$audit,$this->issuer,$this->actor,$id,$event,$meta]))throw new RuntimeException('issuer_auxiliary_audit_unconfirmed');
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function fingerprint(array $row):string{ksort($row);foreach($row as&$v)$v=$v===null?null:(string)$v;unset($v);return hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}
    private static function dto(string $kind,array $row):array
    {
        $row['revision']=self::fingerprint($row);$key=match($kind){'todo'=>'todoid','announcement'=>'announcementid',default=>'id'};
        $row['source_id']=(int)$row[$key];$row['source_kind']=$kind;$row[$key]=(string)$row[$key];
        if($kind==='todo'||$kind==='note')$row['description']=html_entity_decode(strip_tags(preg_replace('/<br\s*\/?\s*>/i',"\n",(string)$row['description'])),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        return$row;
    }
    public function todos(bool $finished,int $limit=100,int $offset=0):array
    {
        $this->assertOwner();if($limit<1||$limit>100||$offset<0||$offset>100000)throw new DomainException('company_auxiliary_invalid_page');
        $rows=$this->rows('SELECT * FROM '.$this->p.'todos WHERE staffid=? AND finished=? ORDER BY item_order,todoid LIMIT '.$limit.' OFFSET '.$offset,[$this->actor,(int)$finished]);
        $this->assertOwner();return array_map(static fn($row)=>self::dto('todo',$row),$rows);
    }
    public function todoCounts():array
    {
        $this->assertOwner();$counts=[0=>0,1=>0];foreach($this->rows('SELECT finished,COUNT(*) n FROM '.$this->p.'todos WHERE staffid=? GROUP BY finished',[$this->actor])as$row){if(!in_array((string)$row['finished'],['0','1'],true))throw new RuntimeException('issuer_native_auxiliary_data_invalid');$counts[(int)$row['finished']]=(int)$row['n'];}return$counts;
    }
    private function todoRow(int $id):array
    {
        $this->assertOwner();$row=$this->rows('SELECT * FROM '.$this->p.'todos WHERE todoid=? AND staffid=?',[$id,$this->actor])[0]??null;
        if(!$row)throw new DomainException('company_auxiliary_access_denied');return$row;
    }
    public function todo($id):array{return self::dto('todo',$this->todoRow(IssuerPolicy::id($id)));}
    public function mutateTodo($id,$revision,?string $description=null,?array $order=null,bool $delete=false,bool $inOwned=false):array
    {
        $id=IssuerPolicy::id($id);if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_auxiliary_stale');
        if($description!==null&&(trim($description)===''||strlen($description)>16000||preg_match('//u',$description)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$description)))throw new DomainException('company_auxiliary_invalid_input');
        $tables=['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','todos','issuers_audit'];
        $operation=function()use($id,$revision,$description,$order,$delete){
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$row=$this->todoRow($id);if(!hash_equals(self::fingerprint($row),$revision))throw new DomainException('company_auxiliary_stale');
            if($delete){IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'todos WHERE todoid=? AND staffid=?',[$id,$this->actor]);if($this->rows('SELECT todoid FROM '.$this->p.'todos WHERE todoid=?',[$id]))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_legacy_todo_deleted',$id);return[];}
            $changes=[];
            if($description!==null)$changes['description']=nl2br(trim($description));
            if($order!==null){if(count($order)!==2||!is_int($order[0])||$order[0]<0||$order[0]>100000||!in_array($order[1],[0,1],true))throw new DomainException('company_auxiliary_invalid_input');$changes['item_order']=$order[0];$changes['finished']=$order[1];$changes['datefinished']=$order[1]===1?($row['datefinished']?:date('Y-m-d H:i:s')):null;}
            if(!$changes)throw new DomainException('company_auxiliary_invalid_input');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'todos SET '.implode(',',array_map(static fn($key)=>$key.'=?',array_keys($changes))).' WHERE todoid=? AND staffid=?',array_merge(array_values($changes),[$id,$this->actor]));
            $saved=$this->todoRow($id);foreach($changes as$key=>$value)if($saved[$key]!==$value&&(string)$saved[$key]!== (string)$value)throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_legacy_todo_saved',$id);return self::dto('todo',$saved);
        };
        if($inOwned){IssuerTransaction::assertOwned($this->db,$this->p,$tables);return$operation();}
        return IssuerTransaction::run($this->db,$this->p,$tables,$operation);
    }
    public function announcements(int $limit=100,int $offset=0):array
    {
        $this->assertOwner();if($limit<1||$limit>100||$offset<0||$offset>100000)throw new DomainException('company_auxiliary_invalid_page');
        $rows=$this->rows('SELECT * FROM '.$this->p.'announcements WHERE showtostaff=1 ORDER BY dateadded DESC,announcementid DESC LIMIT '.$limit.' OFFSET '.$offset);
        $this->assertOwner();return array_map(static fn($r)=>self::dto('announcement',$r),$rows);
    }
    public function undismissedAnnouncementsCount():int
    {
        $this->assertOwner();return(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'announcements a WHERE a.showtostaff=1 AND NOT EXISTS(SELECT 1 FROM '.$this->p.'dismissed_announcements d WHERE d.announcementid=a.announcementid AND d.staff=1 AND d.userid=?)',[$this->actor])[0]['n'];
    }
    public function announcement($id):array
    {
        $this->assertOwner();$row=$this->rows('SELECT * FROM '.$this->p.'announcements WHERE announcementid=?',[IssuerPolicy::id($id)])[0]??null;
        if(!$row||((int)$row['showtostaff']!==1&&!$this->access->can($this->issuer,$this->actor,'announcements','edit')))throw new DomainException('company_auxiliary_access_denied');
        return self::dto('announcement',$row);
    }
    public function announcementTable(int $start,int $length,string $search=''):array
    {
        $this->assertOwner();if($start<0||$start>100000||$length<1||$length>100||strlen($search)>191)throw new DomainException('company_auxiliary_invalid_page');
        $where=$this->access->can($this->issuer,$this->actor,'announcements','edit')?'1=1':'showtostaff=1';$args=[];
        $total=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'announcements WHERE '.$where)[0]['n'];
        if($search!==''){$where.=" AND name LIKE ? ESCAPE '!'";$args[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';}
        $filtered=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'announcements WHERE '.$where,$args)[0]['n'];
        $rows=$this->rows('SELECT * FROM '.$this->p.'announcements WHERE '.$where.' ORDER BY dateadded DESC,announcementid DESC LIMIT '.$length.' OFFSET '.$start,$args);
        foreach($rows as&$row){$row=self::dto('announcement',$row);$row['is_dismissed']=(bool)$this->rows('SELECT announcementid FROM '.$this->p.'dismissed_announcements WHERE announcementid=? AND userid=? AND staff=1',[$row['announcementid'],$this->actor]);}unset($row);
        return['total'=>$total,'filtered'=>$filtered,'rows'=>$rows];
    }
    /** Principal-history staff alerts for this actor, filtered before applying the dashboard limit. */
    public function dashboardAnnouncements(int $limit=100):array
    {
        $this->assertOwner();if($limit<1||$limit>100)throw new DomainException('company_auxiliary_invalid_page');
        $rows=$this->rows('SELECT a.* FROM '.$this->p.'announcements a WHERE a.showtostaff=1 AND NOT EXISTS(SELECT 1 FROM '.$this->p.'dismissed_announcements d WHERE d.announcementid=a.announcementid AND d.userid=? AND d.staff=1) ORDER BY a.dateadded DESC,a.announcementid DESC LIMIT '.$limit,[$this->actor]);
        $this->assertOwner();return array_map(static fn($row)=>self::dto('announcement',$row),$rows);
    }
    public function dismissAnnouncement($id):void
    {
        $id=IssuerPolicy::id($id);IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','announcements','dismissed_announcements'],function()use($id){
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$row=$this->announcement($id);if((int)$row['showtostaff']!==1)throw new DomainException('company_auxiliary_access_denied');
            $sql='SELECT announcementid FROM '.$this->p.'dismissed_announcements WHERE announcementid=? AND userid=? AND staff=1';$args=[$id,$this->actor];
            if(!$this->rows($sql,$args))IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'dismissed_announcements(announcementid,userid,staff) VALUES(?,?,1)',$args);
            if(!$this->rows($sql,$args))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');
        });
    }
    /** The original model executes only after attested principal, ownership, CAS and finite hook review. */
    public function mutateAnnouncement($ci,$id,$revision,?array $input):void
    {
        $id=IssuerPolicy::id($id);$cap=$input===null?'delete':'edit';
        if(!is_object($ci)||!CompanyRequestScope::nativeFor($ci)||!CompanyRequestScope::sealedFor($ci)||CompanyRequestScope::context()->issuerId()!==$this->issuer||CompanyRequestScope::context()->staffId()!==$this->actor||IssuerPolicy::id(get_staff_user_id())!==$this->actor||$ci->db!==$this->db)throw new DomainException('company_auxiliary_access_denied');
        if(!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_auxiliary_stale');
        $fields=$input===null?null:NativeCompanyAuxiliary::announcementFields($input);
        foreach(['before_announcement_updated','announcement_updated','before_delete_announcement','announcement_deleted']as$tag)if(hooks()->has_filter($tag)!==false)throw new DomainException('native_legacy_hook_review_required');
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','announcements','dismissed_announcements','activity_log','issuers_audit'],function()use($ci,$id,$revision,$fields,$cap){
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$this->assertOwner();if(!$this->access->can($this->issuer,$this->actor,'announcements',$cap))throw new DomainException('company_auxiliary_access_denied');
            $row=$this->rows('SELECT * FROM '.$this->p.'announcements WHERE announcementid=? FOR UPDATE',[$id])[0]??null;
            if(!$row||!hash_equals(self::fingerprint($row),$revision))throw new DomainException('company_auxiliary_stale');
            $ci->load->model('announcements_model');if(get_class($ci->announcements_model)!=='Announcements_model')throw new DomainException('native_legacy_model_review_required');
            if($fields===null){$ci->announcements_model->delete($id);if($this->rows('SELECT announcementid FROM '.$this->p.'announcements WHERE announcementid=?',[$id])||$this->rows('SELECT announcementid FROM '.$this->p.'dismissed_announcements WHERE announcementid=?',[$id]))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');}
            else{$modelInput=$fields;foreach(['showtousers','showtostaff','showname']as$key)if($modelInput[$key]===0)unset($modelInput[$key]);$ci->announcements_model->update($modelInput,$id);$saved=$this->rows('SELECT * FROM '.$this->p.'announcements WHERE announcementid=?',[$id])[0]??[];foreach($fields as$key=>$value)if((string)($saved[$key]??'')!==(string)$value)throw new RuntimeException('issuer_auxiliary_write_unconfirmed');}
            $this->assertOwner();$this->audit('native_legacy_announcement_'.$cap,$id);
        });
    }

    private function relationRow(string $kind,int $id):array
    {
        $this->assertOwner();$table=$kind==='note'?'notes':'reminders';$row=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=?',[$id])[0]??null;
        if(!$row)throw new DomainException('company_auxiliary_access_denied');
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$this->actor,$row['rel_type'],(string)$row['rel_id']);
        if(str_starts_with($ref['source_kind'],'company_'))throw new DomainException('company_auxiliary_access_denied');return$row;
    }
    private function relationDto(string $kind,array $row):array
    {
        $dto=self::dto($kind,$row);$dto['relation']=NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$this->actor,$row['rel_type'],(string)$row['rel_id']);
        $author=$kind==='note'?'addedfrom':'creator';$person=$kind==='note'?$row['addedfrom']:$row['staff'];
        $name=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=?',[$person])[0]??[];$dto['firstname']=$name['firstname']??'';$dto['lastname']=$name['lastname']??'';
        try{NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$this->actor,$row['rel_type'],(string)$row['rel_id'],'edit');$dto['can_edit']=(int)$row[$author]===$this->actor||$this->access->administrative($this->issuer,$this->actor);}catch(DomainException $denied){$dto['can_edit']=false;}
        if($kind==='reminder')$dto['description']=html_entity_decode(strip_tags(preg_replace('/<br\s*\/?\s*>/i',"\n",(string)$row['description'])),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');return$dto;
    }
    public function note($id):array{return$this->relationDto('note',$this->relationRow('note',IssuerPolicy::id($id)));}
    public function notes(string $type,$id):array
    {
        $this->assertOwner();$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$this->actor,$type,$id);
        if(str_starts_with($ref['source_kind'],'company_'))return[];
        $rows=$this->rows('SELECT * FROM '.$this->p.'notes WHERE rel_type=? AND rel_id=? ORDER BY dateadded DESC,id DESC',[$type,$ref['source_id']]);return array_map(fn($r)=>$this->relationDto('note',$r),$rows);
    }
    public function reminder($id):array{return$this->relationDto('reminder',$this->relationRow('reminder',IssuerPolicy::id($id)));}
    public function reminderTable(?string $type,$id,int $start,int $length,string $search='',bool $pendingOnly=false,bool $allVisible=false):array
    {
        $this->assertOwner();if($length<1||$length>100||$start<0||$start>100000||strlen($search)>191)throw new DomainException('company_auxiliary_invalid_page');
        $where='1=1';$args=[];
        if($type!==null){$ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$this->actor,$type,$id);if(str_starts_with($ref['source_kind'],'company_'))return['total'=>0,'filtered'=>0,'rows'=>[]];$where.=' AND rel_type=? AND rel_id=?';$args=[$type,$ref['source_id']];}
        else{if(!$allVisible){$where.=' AND staff=?';$args[]=$this->actor;}elseif(!$this->access->administrative($this->issuer,$this->actor)){$where.=' AND (staff=? OR creator=?)';$args[]=$this->actor;$args[]=$this->actor;}}
        if($pendingOnly)$where.=' AND isnotified=0';$visible=[];
        foreach($this->rows('SELECT id FROM '.$this->p.'reminders WHERE '.$where.' ORDER BY date,id',$args)as$row){try{$visible[]=$this->reminder($row['id']);}catch(DomainException $denied){continue;}}
        $total=count($visible);if($search!=='')$visible=array_values(array_filter($visible,static fn($r)=>mb_stripos($r['description'].' '.$r['relation']['name'].' '.$r['firstname'].' '.$r['lastname'],$search)!==false));
        return['total'=>$total,'filtered'=>count($visible),'rows'=>array_slice($visible,$start,$length)];
    }
    public function remindersCount():int{return$this->reminderTable(null,null,0,1,'',true)['filtered'];}
    public function mutateRelation(string $kind,$id,$revision,?array $input):array
    {
        if(!in_array($kind,['note','reminder'],true)||!is_string($revision)||!preg_match('/^[a-f0-9]{64}$/D',$revision))throw new DomainException('company_auxiliary_stale');$id=IssuerPolicy::id($id);
        $source=$this->relationRow($kind,$id);
        require_once __DIR__.'/NativeCompanyReminderDelivery.php';$tables=array_values(array_unique(array_merge(NativeCompanyReminderDelivery::tables(),NativeCompanyResourceReferences::transactionTables($this->db,$this->p,$source['rel_type']),['notes','reminders','issuers_audit'])));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($kind,$id,$revision,$input){
            $this->access->lockFinancialIdentity($this->issuer,$this->actor);$row=$this->relationRow($kind,$id);
            if(!hash_equals(self::fingerprint($row),$revision))throw new DomainException('company_auxiliary_stale');
            $dto=$this->relationDto($kind,$row);if(!$dto['can_edit'])throw new DomainException('company_auxiliary_access_denied');$table=$kind==='note'?'notes':'reminders';
            if($input===null){IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE id=?',[$id]);if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE id=?',[$id]))throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_legacy_'.$kind.'_deleted',$id);return[];}
            $allowed=$kind==='note'?['description']:['description','date','staff','notify_by_email'];if(array_diff(array_keys($input),$allowed))throw new DomainException('company_auxiliary_invalid_input');
            $desc=$input['description']??null;if(!is_string($desc)||trim($desc)===''||strlen($desc)>16000||preg_match('//u',$desc)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$desc))throw new DomainException('company_auxiliary_invalid_input');$changes=['description'=>nl2br(trim($desc))];
            if($kind==='reminder'){
                if((int)$row['isnotified']!==0)throw new DomainException('company_auxiliary_stale');$date=$input['date']??null;if(!is_string($date))throw new DomainException('company_auxiliary_invalid_input');$parsed=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$date);if(!$parsed||$parsed->format('Y-m-d H:i:s')!==$date)throw new DomainException('company_auxiliary_invalid_input');
                $staff=IssuerPolicy::id($input['staff']??null);$notify=$input['notify_by_email']??0;if(!in_array($notify,[0,1,'0','1'],true))throw new DomainException('company_auxiliary_invalid_input');
                NativeCompanyResourceReferences::resolve($this->db,$this->p,$this->issuer,$staff,$row['rel_type'],(string)$row['rel_id']);$changes+=['date'=>$date,'staff'=>$staff,'notify_by_email'=>(int)$notify];
            }
            IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',array_map(static fn($k)=>$k.'=?',array_keys($changes))).' WHERE id=?',array_merge(array_values($changes),[$id]));
            $saved=$this->relationRow($kind,$id);foreach($changes as$key=>$value)if((string)$saved[$key]!== (string)$value)throw new RuntimeException('issuer_auxiliary_write_unconfirmed');$this->audit('native_legacy_'.$kind.'_saved',$id);return$this->relationDto($kind,$saved);
        });
    }
    public function activity(int $limit=30):array
    {
        $this->assertOwner();if(!$this->access->can($this->issuer,$this->actor,'reports','view')||$limit<1||$limit>100)throw new DomainException('company_auxiliary_access_denied');
        return$this->rows('SELECT id,description,date,staffid FROM '.$this->p.'activity_log ORDER BY date DESC,id DESC LIMIT '.$limit);
    }

}
