<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketRead.php';
/** Existing owned ticket rows, native catalog references and original durable command receipts. */
final class NativeCompanyTicketUpdates
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor):void
    {if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'tickets','edit'))throw new DomainException('native_ticket_update_denied');}
    private function run(int $issuer,int $actor,$id,string $expected,string $nonce,string $operation,array $input,callable $write,array $extraTables=[],?callable $verify=null):array
    {
        $d=NativeCompanyResourceId::decode('ticket',$id);if($d['source_kind']!=='company_ticket'||!preg_match('/^[a-f0-9]{64}$/D',$expected)||!preg_match('/^[a-f0-9]{64}$/D',$nonce))throw new DomainException('native_ticket_update_invalid');
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$this->authorize($issuer,$actor);
        NativeCompanyDepartmentSchema::assertValid($this->db,$this->p);NativeCompanyTicketCatalogSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique(array_merge($reader->tables(),['company_ticket_requests'],$extraTables))),function()use($issuer,$actor,$id,$d,$expected,$nonce,$operation,$input,$write,$reader,$verify){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);
            $before=$reader->getInOwnedTransaction($issuer,$actor,$id);
            $hash=hash('sha256',json_encode([$d,$expected,$input],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_ticket_requests WHERE request_key=? FOR UPDATE',[$nonce])[0]??null;
            if($receipt){if((int)$receipt['issuer_id']!==$issuer||(int)$receipt['actor_id']!==$actor||$receipt['operation']!==$operation||!hash_equals($hash,$receipt['input_hash'])||(int)$receipt['result_id']!==$d['source_id'])throw new DomainException('native_ticket_request_conflict');if($verify)$verify($d['source_id']);return['ticketid'=>$before['ticketid'],'revision'=>$before['revision_token'],'replayed'=>true];}
            if(!hash_equals($before['revision_token'],$expected))throw new DomainException('native_ticket_stale');
            $row=$this->rows('SELECT revision FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$d['source_id']])[0]??null;
            if(!$row||(int)$row['revision']<1||(int)$row['revision']>=2147483647)throw new DomainException('native_ticket_revision_invalid');
            $write($d['source_id'],$before);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[date('Y-m-d H:i:s'),$issuer,$d['source_id'],(int)$row['revision']]);
            $revision=$this->rows('SELECT revision FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$issuer,$d['source_id']])[0]??null;if(!$revision||(int)$revision['revision']!==(int)$row['revision']+1)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $this->authorize($issuer,$actor);$after=$reader->getInOwnedTransaction($issuer,$actor,$id);
            $audit=['actor_id'=>(string)$actor,'event'=>$operation,'issuer_id'=>(string)$issuer,'subject_id'=>(string)$d['source_id'],'created_at'=>date('Y-m-d H:i:s'),'metadata_json'=>json_encode(['request_nonce'=>$nonce,'before_revision'=>$expected,'after_revision'=>$after['revision_token']],JSON_THROW_ON_ERROR)];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($audit));$auditId=(int)$this->db->insert_id();$stored=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??[];
            if($auditId<1||array_map('strval',$stored)!==$audit)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $receipt=['request_key'=>$nonce,'issuer_id'=>$issuer,'actor_id'=>$actor,'input_hash'=>$hash,'operation'=>$operation,'result_id'=>$d['source_id'],'created_at'=>date('Y-m-d H:i:s')];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_requests(request_key,issuer_id,actor_id,input_hash,operation,result_id,created_at) VALUES(?,?,?,?,?,?,?)',array_values($receipt));$stored=$this->rows('SELECT * FROM '.$this->p.'company_ticket_requests WHERE request_key=?',[$nonce])[0]??[];
            foreach($receipt as$key=>$value)if(!array_key_exists($key,$stored)||(string)$stored[$key]!== (string)$value)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $this->authorize($issuer,$actor);if($reader->getInOwnedTransaction($issuer,$actor,$id)!==$after)throw new DomainException('native_ticket_changed');
            if($verify)$verify($d['source_id']);return['ticketid'=>$after['ticketid'],'revision'=>$after['revision_token'],'replayed'=>false];
        });
    }
    private function catalogReference(int $issuer,int $actor,int $ticket,string $kind,array $reference):void
    {
        $old=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' WHERE issuer_id=? AND ticket_id=? AND kind=? FOR UPDATE',[$issuer,$ticket,$kind])[0]??null;$now=date('Y-m-d H:i:s');
        if($old){if((int)$old['revision']<1||(int)$old['revision']>=2147483647)throw new DomainException('native_ticket_revision_invalid');IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' SET source_kind=?,source_id=?,revision=revision+1,actor_id=?,updated_at=? WHERE issuer_id=? AND ticket_id=? AND kind=? AND revision=?',[$reference['source_kind'],$reference['source_id'],$actor,$now,$issuer,$ticket,$kind,(int)$old['revision']]);}
        else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.'(issuer_id,ticket_id,kind,source_kind,source_id,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,?,1,?,?,?)',[$issuer,$ticket,$kind,$reference['source_kind'],$reference['source_id'],$actor,$now,$now]);
        $saved=$this->rows('SELECT source_kind,source_id,revision,actor_id FROM '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' WHERE issuer_id=? AND ticket_id=? AND kind=?',[$issuer,$ticket,$kind])[0]??null;if(!$saved||$saved['source_kind']!==$reference['source_kind']||(int)$saved['source_id']!==$reference['source_id']||(int)$saved['revision']!==($old?(int)$old['revision']+1:1)||(int)$saved['actor_id']!==$actor)throw new RuntimeException('issuer_ticket_write_unconfirmed');
    }
    /** Advisory lease, not authorization to edit. Volatile fields never change the content CAS. */
    public function replying(int$issuer,int$actor,$ticket):array
    {
        $reader=new NativeCompanyTicketRead($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$reader->tables(),function()use($reader,$issuer,$actor,$ticket){$t=$reader->getInOwnedTransaction($issuer,$actor,$ticket);$result=$this->leaseProjection($issuer,$actor,$t);if($reader->getInOwnedTransaction($issuer,$actor,$ticket)!==$t)throw new DomainException('native_ticket_changed');return$result;});
    }
    private function leaseProjection(int$issuer,int$actor,array$t):array
    {
        if(!$t['metadata'])throw new DomainException('native_ticket_metadata_required');$m=$t['metadata'];$owner=$m['staff_replying'];$stamp=$m['staff_replying_at'];
        if(($owner===null)!==($stamp===null))throw new DomainException('native_ticket_lease_invalid');
        if($owner===null)return['is_other_staff_replying'=>false,'staff_id'=>null,'name'=>'','state'=>'vacant'];
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',(string)$stamp);if(!$date||$date->format('Y-m-d H:i:s')!==$stamp||$date->getTimestamp()>time()+5)throw new DomainException('native_ticket_lease_invalid');
        // A crashed tab cannot reserve a ticket permanently. No read clears or renews the stored lease.
        if(time()-$date->getTimestamp()>=120)return['is_other_staff_replying'=>false,'staff_id'=>null,'name'=>'','state'=>'expired'];
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,(int)$owner,'tickets','view')||!$acl->can($issuer,(int)$owner,'tickets','edit')||!$acl->member($issuer,(int)$owner))return['is_other_staff_replying'=>false,'staff_id'=>null,'name'=>'','state'=>'revoked'];
        $staff=$this->rows('SELECT firstname,lastname FROM '.$this->p.'staff WHERE staffid=? AND active=1',[(int)$owner])[0]??null;if(!$staff)throw new DomainException('native_ticket_lease_invalid');
        return['is_other_staff_replying'=>(int)$owner!==$actor,'staff_id'=>(int)$owner,'name'=>trim($staff['firstname'].' '.$staff['lastname']),'state'=>'active'];
    }
    public function updateReplying(int$issuer,int$actor,$ticket,string$expected,bool$start):array
    {
        NativeCompanyDepartmentSchema::assertValid($this->db,$this->p);if(!preg_match('/^[a-f0-9]{64}$/D',$expected))throw new DomainException('native_ticket_stale');$this->authorize($issuer,$actor);$reader=new NativeCompanyTicketRead($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$reader->tables(),function()use($reader,$issuer,$actor,$ticket,$expected,$start){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);$t=$reader->getInOwnedTransaction($issuer,$actor,$ticket);if($t['source_kind']!=='company_ticket'||!hash_equals($t['revision_token'],$expected))throw new DomainException('native_ticket_stale');$lease=$this->leaseProjection($issuer,$actor,$t);
            if($lease['state']==='active'&&$lease['staff_id']!==$actor)throw new DomainException('native_ticket_lease_conflict');
            $owner=$start?$actor:null;$stamp=$start?date('Y-m-d H:i:s'):null;
            IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' SET staff_replying=?,staff_replying_at=? WHERE issuer_id=? AND ticket_id=?',[$owner,$stamp,$issuer,$t['source_id']]);
            $after=$reader->getInOwnedTransaction($issuer,$actor,$ticket);if($after['revision_token']!==$expected||$after['metadata']['staff_replying_at']!==$stamp||($owner===null?$after['metadata']['staff_replying']!==null:(int)$after['metadata']['staff_replying']!==$owner))throw new RuntimeException('issuer_ticket_write_unconfirmed');$this->authorize($issuer,$actor);return['success'=>true,'revision'=>$expected]+$this->leaseProjection($issuer,$actor,$after);
        });
    }
    public function hideFile(int$issuer,int$actor,$ticket,string$key,string$fileExpected,string$expected,string$nonce):array
    {
        require_once __DIR__.'/NativeCompanyTicketFiles.php';if(!preg_match('/^[a-f0-9]{64}$/D',$key)||!preg_match('/^[a-f0-9]{64}$/D',$fileExpected))throw new DomainException('native_ticket_file_denied');CompanyOperationFilesSchema::assertValid($this->db,$this->p);
        $permission=function()use($issuer,$actor){$acl=new IssuerResourceAccess($this->db,$this->p);$policy=(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor);if(!$acl->administrative($issuer,$actor)&&$policy['values']['allow_non_admin_staff_to_delete_ticket_attachments']!=='1')throw new DomainException('native_ticket_file_delete_denied');};
        return$this->run($issuer,$actor,$ticket,$expected,$nonce,'native_ticket_file_hidden',[$key,$fileExpected],function(int$id)use($issuer,$key,$fileExpected,$expected,$permission){
            $permission();$row=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1 FOR UPDATE',[$issuer,$id,$key])[0]??null;
            if(!$row||!hash_equals($fileExpected,NativeCompanyTicketFiles::revision($row,$expected)))throw new DomainException('native_ticket_file_stale');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_ticket_files SET active=0 WHERE issuer_id=? AND ticket_id=? AND file_key=? AND active=1',[$issuer,$id,$key]);
            $saved=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=?',[$issuer,$id,$key])[0]??null;$row['active']=0;if(!$saved||array_map('strval',$saved)!==array_map('strval',$row))throw new RuntimeException('issuer_ticket_write_unconfirmed');
        },['company_ticket_files'],function(int$id)use($issuer,$key,$permission,$expected,$fileExpected){$permission();$r=$this->rows('SELECT file_key,filename,mime,content_hash,byte_size,active,actor_id,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? AND file_key=?',[$issuer,$id,$key])[0]??null;if(!$r||(int)$r['active']!==0)throw new RuntimeException('issuer_ticket_write_unconfirmed');$r['active']=1;if(!hash_equals($fileExpected,NativeCompanyTicketFiles::revision($r,$expected)))throw new RuntimeException('issuer_ticket_write_unconfirmed');});
    }
    public function changeStatus(int $issuer,int $actor,$ticket,$status,string $expected,string $nonce):array
    {
        $d=NativeCompanyResourceId::decode('ticket_status',$status);if($d['source_kind']!=='company_ticket_status')throw new DomainException('native_ticket_status_denied');
        return $this->run($issuer,$actor,$ticket,$expected,$nonce,'native_ticket_status_updated',[$status],function(int $id)use($issuer,$actor,$status){
            $catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$ref=$catalog->get($issuer,$actor,'status',$status);$this->catalogReference($issuer,$actor,$id,'status',$ref);
            // Keep the historical row marker/body provenance intact. The explicit owned catalog reference is authoritative.
            if($catalog->get($issuer,$actor,'status',$status)!==$ref)throw new RuntimeException('issuer_ticket_write_unconfirmed');
        });
    }
    /** Edit the original ticket body without reclassifying historical text or changing sender provenance. */
    public function editMessage(int $issuer,int $actor,$ticket,string $message,string $expected,string $nonce):array
    {
        if(strlen($message)>64000||preg_match('//u',$message)!==1||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message))throw new DomainException('native_ticket_message_invalid');
        $message=NativeCompanyContentHtml::purify($message);if(trim(strip_tags($message))==='')throw new DomainException('native_ticket_message_invalid');
        NativeCompanyTicketReplySchema::assertValid($this->db,$this->p);
        $permission=function()use($issuer,$actor){$acl=new IssuerResourceAccess($this->db,$this->p);$policy=(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor);if(!$acl->administrative($issuer,$actor)&&$policy['values']['allow_non_admin_members_to_edit_ticket_messages']!=='1')throw new DomainException('native_ticket_message_edit_denied');};
        return $this->run($issuer,$actor,$ticket,$expected,$nonce,'native_ticket_message_edited',[$message],function(int$id)use($issuer,$actor,$message,$permission){
            $permission();$now=date('Y-m-d H:i:s');$table=$this->p.NativeCompanyTicketReplySchema::BODY;
            $old=$this->rows('SELECT * FROM '.$table.' WHERE ticket_id=? FOR UPDATE',[$id])[0]??null;
            if($old&&((int)$old['issuer_id']!==$issuer||(int)$old['revision']<1||(int)$old['revision']>=2147483647))throw new DomainException('native_ticket_body_provenance_invalid');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET body=? WHERE issuer_id=? AND id=?',[$message,$issuer,$id]);
            $proof=['ticket_id'=>$id,'issuer_id'=>$issuer,'body_format'=>'native_html','content_hash'=>hash('sha256',$message),'revision'=>$old?(int)$old['revision']+1:1,'actor_id'=>$actor,'created_at'=>$old?$old['created_at']:$now,'updated_at'=>$now];
            if($old)IssuerStorage::query($this->db,'UPDATE '.$table.' SET body_format=?,content_hash=?,revision=?,actor_id=?,updated_at=? WHERE ticket_id=? AND issuer_id=? AND revision=?',[$proof['body_format'],$proof['content_hash'],$proof['revision'],$actor,$now,$id,$issuer,$old['revision']]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$table.'('.implode(',',array_keys($proof)).') VALUES(?,?,?,?,?,?,?,?)',array_values($proof));
            $saved=$this->rows('SELECT * FROM '.$table.' WHERE ticket_id=?',[$id])[0]??null;if(!$saved||array_map('strval',$saved)!==array_map('strval',$proof))throw new RuntimeException('issuer_ticket_write_unconfirmed');
        },[NativeCompanyTicketReplySchema::BODY],function(int$id)use($issuer,$message,$permission){
            $permission();$row=$this->rows('SELECT t.body,m.body_format,m.content_hash FROM '.$this->p.'company_tickets t JOIN '.$this->p.NativeCompanyTicketReplySchema::BODY.' m ON m.ticket_id=t.id AND m.issuer_id=t.issuer_id WHERE t.issuer_id=? AND t.id=?',[$issuer,$id])[0]??null;
            if(!$row||$row['body']!==$message||$row['body_format']!=='native_html'||!hash_equals(hash('sha256',$message),$row['content_hash']))throw new RuntimeException('issuer_ticket_write_unconfirmed');
        });
    }
    public function editReply(int $issuer,int $actor,$ticket,$reply,string $message,string $expected,string $replyExpected,string $nonce):array
    {
        $decoded=NativeCompanyResourceId::decode('ticket_reply',$reply);if($decoded['source_kind']!=='company_ticket_reply'||!preg_match('/^[a-f0-9]{64}$/D',$replyExpected)||strlen($message)>64000||preg_match('//u',$message)!==1||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message))throw new DomainException('native_ticket_message_invalid');
        $message=NativeCompanyContentHtml::purify($message);if(trim(strip_tags($message))==='')throw new DomainException('native_ticket_message_invalid');NativeCompanyTicketReplySchema::assertValid($this->db,$this->p);
        $reader=new NativeCompanyTicketRead($this->db,$this->p);
        $select=function()use($reader,$issuer,$actor,$ticket,$reply){$matches=array_values(array_filter($reader->repliesInOwnedTransaction($issuer,$actor,$ticket),fn($r)=>(string)$r['id']===(string)$reply));if(count($matches)!==1)throw new DomainException('native_ticket_reply_denied');return$matches[0];};
        $permission=function()use($issuer,$actor){$acl=new IssuerResourceAccess($this->db,$this->p);$p=(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor);if(!$acl->administrative($issuer,$actor)&&$p['values']['allow_non_admin_members_to_edit_ticket_messages']!=='1')throw new DomainException('native_ticket_message_edit_denied');};
        return$this->run($issuer,$actor,$ticket,$expected,$nonce,'native_ticket_reply_edited',[$reply,$message,$replyExpected],function(int$id)use($issuer,$actor,$decoded,$message,$replyExpected,$select,$permission){
            $permission();$before=$select();if(!hash_equals($before['revision_token'],$replyExpected))throw new DomainException('native_ticket_stale');$table=$this->p.NativeCompanyTicketReplySchema::EDIT;$now=date('Y-m-d H:i:s');$replyId=$decoded['source_id'];
            $old=$this->rows('SELECT * FROM '.$table.' WHERE reply_id=? FOR UPDATE',[$replyId])[0]??null;if($old&&((int)$old['revision']<1||(int)$old['revision']>=2147483647))throw new DomainException('native_ticket_revision_invalid');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_ticket_replies SET body=? WHERE issuer_id=? AND ticket_id=? AND id=?',[$message,$issuer,$id,$replyId]);
            $proof=['reply_id'=>$replyId,'issuer_id'=>$issuer,'ticket_id'=>$id,'content_hash'=>hash('sha256',$message),'revision'=>$old?(int)$old['revision']+1:1,'actor_id'=>$actor,'created_at'=>$old?$old['created_at']:$now,'updated_at'=>$now];
            if($old)IssuerStorage::query($this->db,'UPDATE '.$table.' SET content_hash=?,revision=?,actor_id=?,updated_at=? WHERE issuer_id=? AND ticket_id=? AND reply_id=? AND revision=?',[$proof['content_hash'],$proof['revision'],$actor,$now,$issuer,$id,$replyId,$old['revision']]);else IssuerStorage::query($this->db,'INSERT INTO '.$table.'('.implode(',',array_keys($proof)).') VALUES(?,?,?,?,?,?,?,?)',array_values($proof));
            $saved=$this->rows('SELECT * FROM '.$table.' WHERE reply_id=?',[$replyId])[0]??null;if(!$saved||array_map('strval',$saved)!==array_map('strval',$proof))throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $after=$select();foreach(['admin','userid','contactid','submitter','date']as$key)if($after[$key]!==$before[$key])throw new RuntimeException('issuer_ticket_write_unconfirmed');
        },[NativeCompanyTicketReplySchema::TABLE,NativeCompanyTicketReplySchema::EDIT,'company_portal_support_replies'],function(int$id)use($permission,$select,$message){$permission();$r=$select();if($r['message']!==$message||$r['message_format']!=='native_html')throw new RuntimeException('issuer_ticket_write_unconfirmed');});
    }
    public function reply(int $issuer,int $actor,$ticket,string $message,$status,string $expected,string $nonce,array $options=[]):array
    {
        require_once __DIR__.'/NativeCompanyTicketReplySchema.php';
        if(strlen($message)>64000||preg_match('//u',$message)!==1||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/',$message))throw new DomainException('native_ticket_message_invalid');
        $message=NativeCompanyContentHtml::purify($message);if(trim(strip_tags($message))==='')throw new DomainException('native_ticket_message_invalid');
        if($status!==null){$decoded=NativeCompanyResourceId::decode('ticket_status',$status);if($decoded['source_kind']!=='company_ticket_status')throw new DomainException('native_ticket_status_denied');}
        if(array_diff(array_keys($options),['cc','assign_to_current_user'])||isset($options['cc'])&&!is_string($options['cc'])||isset($options['assign_to_current_user'])&&!is_bool($options['assign_to_current_user']))throw new DomainException('native_ticket_input_invalid');
        $cc=null;if(array_key_exists('cc',$options)){$cc=[];foreach(explode(',',$options['cc'])as$email){$email=trim($email);if($email==='')continue;if(strlen($email)>191||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new DomainException('native_ticket_input_invalid');$cc[]=$email;}if(count($cc)>20||count(array_unique(array_map('strtolower',$cc)))!==count($cc))throw new DomainException('native_ticket_input_invalid');}
        $assign=$options['assign_to_current_user']??false;
        NativeCompanyTicketReplySchema::assertValid($this->db,$this->p);
        return$this->run($issuer,$actor,$ticket,$expected,$nonce,'native_ticket_replied',[$message,$status,$cc,$assign],function(int$id,array$before)use($issuer,$actor,$message,$status,$nonce,$cc,$assign){
            NativeCompanyTicketReplySchema::assertValid($this->db,$this->p);$now=date('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_replies(issuer_id,ticket_id,actor_id,body,created_at) VALUES(?,?,?,?,?)',[$issuer,$id,$actor,$message,$now]);$reply=(int)$this->db->insert_id();
            $row=$this->rows('SELECT issuer_id,ticket_id,actor_id,body,created_at FROM '.$this->p.'company_ticket_replies WHERE id=?',[$reply])[0]??null;
            if($reply<1||!$row||array_map('strval',$row)!==array_map('strval',['issuer_id'=>$issuer,'ticket_id'=>$id,'actor_id'=>$actor,'body'=>$message,'created_at'=>$now]))throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $meta=['reply_id'=>$reply,'issuer_id'=>$issuer,'ticket_id'=>$id,'body_format'=>'native_html','request_key'=>$nonce,'revision'=>1,'actor_id'=>$actor,'created_at'=>$now,'updated_at'=>$now];
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyTicketReplySchema::TABLE.'('.implode(',',array_keys($meta)).') VALUES(?,?,?,?,?,?,?,?,?)',array_values($meta));
            $saved=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketReplySchema::TABLE.' WHERE reply_id=?',[$reply])[0]??null;if(!$saved||array_map('strval',$saved)!==array_map('strval',$meta))throw new RuntimeException('issuer_ticket_write_unconfirmed');
            if($status!==null){$catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$ref=$catalog->get($issuer,$actor,'status',$status);$this->catalogReference($issuer,$actor,$id,'status',$ref);if($catalog->get($issuer,$actor,'status',$status)!==$ref)throw new DomainException('native_ticket_changed');}
            if($assign||($before['assigned']===0&&$before['policy']['values']['automatically_assign_ticket_to_first_staff_responding']==='1'&&(int)($this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_ticket_replies WHERE issuer_id=? AND ticket_id=? AND actor_id>0',[$issuer,$id])[0]['n']??0)===1)){IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_tickets SET assignee_id=? WHERE issuer_id=? AND id=? AND assignee_id=?',[$actor,$issuer,$id,$before['assigned']]);$a=$this->rows('SELECT assignee_id FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$issuer,$id])[0]??null;if(!$a||(int)$a['assignee_id']!==$actor)throw new RuntimeException('issuer_ticket_write_unconfirmed');}
            if($cc!==null){if(!$before['metadata'])throw new DomainException('native_ticket_metadata_required');$json=json_encode($cc,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' SET cc_json=? WHERE issuer_id=? AND ticket_id=?',[$json,$issuer,$id]);$c=$this->rows('SELECT cc_json FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=?',[$issuer,$id])[0]??null;if(!$c||$c['cc_json']!==$json)throw new RuntimeException('issuer_ticket_write_unconfirmed');}
            if($before['metadata']){if((int)$before['metadata']['revision']<1||(int)$before['metadata']['revision']>=2147483647)throw new DomainException('native_ticket_revision_invalid');IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' SET last_reply_at=?,staff_replying=NULL,staff_replying_at=NULL,revision=revision+1 WHERE issuer_id=? AND ticket_id=? AND revision=?',[$now,$issuer,$id,$before['metadata']['revision']]);$m=$this->rows('SELECT last_reply_at,staff_replying,staff_replying_at,revision FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=?',[$issuer,$id])[0]??null;if(!$m||$m['last_reply_at']!==$now||$m['staff_replying']!==null||$m['staff_replying_at']!==null||(int)$m['revision']!==(int)$before['metadata']['revision']+1)throw new RuntimeException('issuer_ticket_write_unconfirmed');}
        },[NativeCompanyTicketReplySchema::TABLE],function(int$id)use($issuer,$actor,$message,$nonce){
            $rows=$this->rows('SELECT r.*,m.body_format,m.revision AS metadata_revision,m.actor_id AS metadata_actor,m.created_at AS metadata_created FROM '.$this->p.'company_ticket_replies r JOIN '.$this->p.NativeCompanyTicketReplySchema::TABLE.' m ON m.reply_id=r.id AND m.issuer_id=r.issuer_id AND m.ticket_id=r.ticket_id WHERE m.issuer_id=? AND m.request_key=?',[$issuer,$nonce]);
            if(count($rows)!==1)throw new RuntimeException('issuer_ticket_write_unconfirmed');$r=$rows[0];if((int)$r['ticket_id']!==$id||(int)$r['actor_id']!==$actor||(int)$r['metadata_actor']!==$actor||$r['body']!==$message||$r['body_format']!=='native_html'||(int)$r['metadata_revision']<1||$r['created_at']!==$r['metadata_created'])throw new RuntimeException('issuer_ticket_write_unconfirmed');
        });
    }

}
