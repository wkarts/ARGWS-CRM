<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyContractsStorage.php';
require_once __DIR__.'/NativeCompanyTicketCustomerSchema.php';
require_once __DIR__.'/NativeCompanyTicketContacts.php';
require_once __DIR__.'/NativeCompanyTicketPreferences.php';
require_once __DIR__.'/NativeCompanyDepartments.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
require_once __DIR__.'/NativeCompanyContentHtml.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyTicketRead.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Native ticket commands use the original ticket store and receipts, not global models/hooks/mail. */
final class NativeCompanyTicketCommands extends CompanyContractsStorage
{
    protected string $feature='tickets';
    private ?array $pendingCreate=null;
    private ?array $createdProof=null;
    private bool $pendingFiles=false;
    private ?array $createdFilesProof=null;
    protected function transaction(callable $work)
    {
        CompanyContractsSchema::assertValid($this->db,$this->p);NativeCompanyTicketCustomerSchema::assertValid($this->db,$this->p);NativeCompanyDepartmentSchema::assertValid($this->db,$this->p);NativeCompanyTicketCatalogSchema::assertValid($this->db,$this->p);
        $tables=array_merge((new NativeCompanyTicketContacts($this->db,$this->p))->tables(),(new NativeCompanyTicketPreferences($this->db,$this->p))->tables(),array_keys(CompanyContractsSchema::definitions($this->p)),array_keys(NativeCompanyDepartmentSchema::definitions($this->p)),array_keys(NativeCompanyTicketCatalogSchema::definitions($this->p)),NativeCompanyResourceTags::tableNames(),(new NativeCompanyTicketRead($this->db,$this->p))->tables(),(new NativeCompanyCustomFields($this->db,$this->p))->tableNames(),['issuers_client_links','projects','company_projects','departments','staff_departments','services','tickets_predefined_replies']);
        if($this->pendingFiles){require_once __DIR__.'/CompanyOperationFilesSchema.php';CompanyOperationFilesSchema::assertValid($this->db,$this->p);$tables[]='company_ticket_files';}
        return IssuerTransaction::run($this->db,$this->p,array_values(array_unique($tables)),function()use($work){
            $scope=$this->pendingCreate;if($scope===null)throw new LogicException('native_ticket_command_required');
            [$issuer,$actor,$data]=$scope;$before=$this->references($issuer,$actor,$data);$result=$work();if($this->references($issuer,$actor,$data)!==$before)throw new DomainException('native_ticket_references_changed');
            if($this->createdFilesProof!==null){[$ticket,$snapshot]=$this->createdFilesProof;if($this->ticketFileFrame($issuer,$ticket)!==$snapshot)throw new RuntimeException('issuer_ticket_write_unconfirmed');}return$result;
        });
    }
    private static function createInput(array $input):array
    {
        if(array_diff(array_keys($input),['subject','message','contactid','userid','name','email','cc','department','priority','service','assigned','project_id','tags','custom_fields']))throw new DomainException('native_ticket_input_invalid');
        foreach(['department','priority','service','project_id']as$key)if(isset($input[$key])&&!is_string($input[$key])&&!is_int($input[$key]))throw new DomainException('native_ticket_input_invalid');
        $out=['subject'=>self::text($input['subject']??null,191),'message'=>NativeCompanyContentHtml::purify(self::text($input['message']??null,12000)),
            'contactid'=>($input['contactid']??'')===''?null:NativeCompanyResourceId::decode('contact',$input['contactid'])['source_id'],
            'userid'=>($input['userid']??'')===''?null:NativeCompanyResourceId::decode('client',$input['userid'])['source_id'],
            'name'=>self::text($input['name']??'',191,false),'email'=>self::text($input['email']??'',191,false),
            'department'=>(string)($input['department']??''),'priority'=>(string)($input['priority']??''),'service'=>($input['service']??'')===''?null:(string)$input['service'],
            'assigned'=>($input['assigned']??'')===''?0:IssuerPolicy::id($input['assigned']),'project_id'=>($input['project_id']??'')===''?null:(string)$input['project_id']];
        if(preg_match('/[\r\n]/',$out['name'].$out['email'])||($out['email']!==''&&!filter_var($out['email'],FILTER_VALIDATE_EMAIL)))throw new DomainException('native_ticket_input_invalid');
        $cc=self::text($input['cc']??'',2000,false);$out['cc']=[];
        if($cc!=='')foreach(explode(',',$cc)as$email){$email=trim($email);if(strlen($email)>191||!filter_var($email,FILTER_VALIDATE_EMAIL)||preg_match('/[\r\n]/',$email))throw new DomainException('native_ticket_input_invalid');$out['cc'][]=$email;}
        if(count($out['cc'])>20||count(array_unique(array_map('strtolower',$out['cc'])))!==count($out['cc']))throw new DomainException('native_ticket_input_invalid');
        $tags=$input['tags']??'';if(!is_string($tags)||strlen($tags)>10100)throw new DomainException('native_ticket_input_invalid');
        $out['tags']=$tags===''?[]:array_values(array_filter(array_map('trim',explode(',',$tags)),static fn($v)=>$v!==''));
        if(count($out['tags'])>100)throw new DomainException('native_ticket_input_invalid');
        $fields=$input['custom_fields']??[];if(!is_array($fields)||array_diff(array_keys($fields),['tickets'])||isset($fields['tickets'])&&!is_array($fields['tickets']))throw new DomainException('native_ticket_input_invalid');
        $out['custom_fields']=$fields['tickets']??[];
        return$out;
    }
    private function references(int $issuer,int $actor,array $data,string $cap='create'):array
    {
        $this->authorize($issuer,$actor,$cap);$prefs=(new NativeCompanyTicketPreferences($this->db,$this->p))->readInOwnedTransaction($issuer,$actor);$acl=new IssuerResourceAccess($this->db,$this->p);
        $staffActor=$this->rows('SELECT active,is_not_staff FROM '.$this->p.'staff WHERE staffid=?',[$actor])[0]??null;if(!$staffActor||(int)$staffActor['active']!==1||($prefs['values']['access_tickets_to_none_staff_members']==='0'&&(int)$staffActor['is_not_staff']!==0))throw new DomainException('native_ticket_access_denied');
        $departments=new NativeCompanyDepartments($this->db,$this->p);$department=$departments->get($issuer,$actor,$data['department']);
        if(!$acl->administrative($issuer,$actor)&&$prefs['values']['staff_access_only_assigned_departments']==='1'&&!in_array($department['departmentid'],array_column($departments->forStaff($issuer,$actor),'departmentid'),true))throw new DomainException('native_ticket_department_denied');
        $catalog=new NativeCompanyTicketCatalog($this->db,$this->p);$priority=$catalog->get($issuer,$actor,'priority',$data['priority']);$service=$data['service']===null?null:$catalog->get($issuer,$actor,'service',$data['service']);
        if($service!==null&&$prefs['values']['services']!=='1')throw new DomainException('native_ticket_service_disabled');
        $contact=null;$client=null;$name=$data['name'];$email=$data['email'];$origin='staff_entered';
        if($data['contactid']!==null){$contact=($cap==='edit'?(new NativeCompanyTicketContacts($this->db,$this->p))->getForEditInOwnedTransaction($issuer,$actor,$data['contactid'],$data['userid']):(new NativeCompanyTicketContacts($this->db,$this->p))->getInOwnedTransaction($issuer,$actor,$data['contactid'],$data['userid']));$client=(int)$contact['userid'];$name=trim($contact['firstname'].' '.$contact['lastname']);$email=$contact['email'];$origin='registered_contact';if(($data['name']!==''&&$data['name']!==$name)||($data['email']!==''&&$data['email']!==$email))throw new DomainException('native_ticket_sender_mismatch');}
        elseif($data['userid']!==null||trim($name)===''||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new DomainException('native_ticket_sender_required');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>191||preg_match('/[\r\n]/',$email))throw new DomainException('native_ticket_sender_required');
        if($data['assigned']!==0){$this->responsible($issuer,$data['assigned']);$staff=$this->rows('SELECT active,is_not_staff FROM '.$this->p.'staff WHERE staffid=?',[$data['assigned']])[0]??null;if(!$staff||(int)$staff['active']!==1||($prefs['values']['access_tickets_to_none_staff_members']==='0'&&(int)$staff['is_not_staff']!==0))throw new DomainException('native_ticket_assignee_denied');}
        $project=null;if($data['project_id']!==null){$project=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$data['project_id']);$owned=$project['source_kind']==='company_project';$r=$this->rows('SELECT '.($owned?'client_id':'clientid').' AS client_id FROM '.$this->p.($owned?'company_projects':'projects').' WHERE id=?',[$project['source_id']])[0]??null;if(!$r||$client===null||(int)$r['client_id']!==$client)throw new DomainException('native_ticket_project_denied');}
        if($acl->primaryId()===$issuer)$status=$catalog->get($issuer,$actor,'status','1');else{$r=$this->rows('SELECT id FROM '.$this->p.NativeCompanyTicketCatalogSchema::CATALOG.' WHERE issuer_id=? AND kind=? AND code=?',[$issuer,'status','open'])[0]??null;if(!$r)throw new DomainException('native_ticket_catalog_defaults_missing');$status=$catalog->get($issuer,$actor,'status',NativeCompanyResourceId::encode('company_ticket_status',(int)$r['id']));}
        $this->authorize($issuer,$actor,$cap);return compact('prefs','department','priority','service','contact','client','name','email','origin','project','status');
    }
    /** Same finite reference rules, but edit is not a synthetic create grant. No mutation occurs here. */
    public function settingsInputInOwnedTransaction(int $issuer,int $actor,array $before,array $input):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge((new NativeCompanyTicketRead($this->db,$this->p))->tables(),(new NativeCompanyTicketContacts($this->db,$this->p))->tables()));
        if((new NativeCompanyTicketRead($this->db,$this->p))->getInOwnedTransaction($issuer,$actor,$before['ticketid'])!==$before||!$before['metadata'])throw new DomainException('native_ticket_metadata_required');
        if(array_diff(array_keys($input),['subject','contactid','userid','department','assigned','priority','service','project_id','tags','custom_fields']))throw new DomainException('native_ticket_input_invalid');
        $input['message']=$before['message'];$input['cc']=implode(',',json_decode($before['metadata']['cc_json'],true,32,JSON_THROW_ON_ERROR));
        $hasContact=isset($input['contactid'])&&$input['contactid']!=='';$input['name']=$hasContact?'':$before['submitter'];$input['email']=$hasContact?'':$before['ticket_email'];
        $data=self::createInput($input);$refs=$this->references($issuer,$actor,$data,'edit');
        // A new recipient may not inherit an existing customer's authored/public history implicitly.
        if($before['userid']!==null&&$refs['client']!==$before['userid'])throw new DomainException('native_ticket_client_reassignment_requires_review');
        return['data'=>$data,'refs'=>$refs];
    }
    public function createNative(int $issuer,int $actor,array $input,string $request,array $files=[]):string
    {
        $data=self::createInput($input);require_once __DIR__.'/NativeCompanyTicketMultipart.php';$normalized=NativeCompanyTicketMultipart::normalize($files);
        if($this->pendingCreate!==null)throw new LogicException('native_ticket_command_nested');$this->pendingCreate=[$issuer,$actor,$data];$this->pendingFiles=$normalized!==[];$payload=$normalized?['ticket'=>$data,'files'=>array_map(static fn($f)=>[$f['name'],$f['mime'],$f['hash'],$f['extension']],$normalized)]:$data;try{$id=$this->operation($issuer,$actor,'create',$request,'native_ticket_created',$payload,function()use($issuer,$actor,$data,$normalized,$request):int{
            $refs=$this->references($issuer,$actor,$data);$now=date('Y-m-d H:i:s');NativeCompanyTicketMultipart::assertPolicy($normalized,$refs['prefs']['values']);
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_tickets (issuer_id,client_id,assignee_id,subject,body,status,priority,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,1,?,?)',[$issuer,$refs['client'],$data['assigned'],$data['subject'],$data['message'],'native','native',$now,$now]);$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $metadata=['issuer_id'=>$issuer,'ticket_id'=>$id,'department_source_kind'=>$refs['department']['source_kind'],'department_source_id'=>$refs['department']['source_id'],'revision'=>1,'actor_id'=>$actor,'created_at'=>$now,'updated_at'=>$now,'contact_id'=>$data['contactid'],'project_source_kind'=>$refs['project']['source_kind']??'','project_source_id'=>$refs['project']['source_id']??null,'sender_name'=>$refs['name'],'sender_email'=>$refs['email'],'sender_origin'=>$refs['origin'],'cc_json'=>json_encode($data['cc'],JSON_THROW_ON_ERROR),'opened_by'=>$actor,'admin_read'=>0,'last_reply_at'=>null,'staff_replying'=>null,'staff_replying_at'=>null];
            $keys=array_keys($metadata);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' ('.implode(',',$keys).') VALUES('.implode(',',array_fill(0,count($keys),'?')).')',array_values($metadata));
            foreach(['status','priority','service']as$kind){$ref=$refs[$kind];if($ref===null)continue;IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' (issuer_id,ticket_id,kind,source_kind,source_id,revision,actor_id,created_at,updated_at) VALUES(?,?,?,?,?,1,?,?,?)',[$issuer,$id,$kind,$ref['source_kind'],$ref['source_id'],$actor,$now,$now]);}
            $saved=$this->rows('SELECT * FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$issuer,$id])[0]??null;
            if(!$saved||$saved['client_id']!==$refs['client']&&($saved['client_id']===null||$refs['client']===null||(int)$saved['client_id']!==$refs['client'])||(int)$saved['assignee_id']!==$data['assigned']||$saved['subject']!==$data['subject']||$saved['body']!==$data['message']||$saved['status']!=='native'||$saved['priority']!=='native'||(int)$saved['revision']!==1)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $savedMetadata=$this->rows('SELECT * FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=?',[$issuer,$id])[0]??null;if(!$savedMetadata)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            foreach($metadata as$key=>$value)if($value===null?$savedMetadata[$key]!==null:(string)$savedMetadata[$key]!== (string)$value)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            $references=$this->rows('SELECT kind,source_kind,source_id FROM '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' WHERE issuer_id=? AND ticket_id=? ORDER BY kind',[$issuer,$id]);$expected=[];foreach(['priority','service','status']as$kind)if($refs[$kind]!==null)$expected[]=['kind'=>$kind,'source_kind'=>$refs[$kind]['source_kind'],'source_id'=>(string)$refs[$kind]['source_id']];$references=array_map(static fn($r)=>array_map('strval',$r),$references);if($references!==$expected)throw new RuntimeException('issuer_ticket_write_unconfirmed');
            if($this->references($issuer,$actor,$data)!==$refs)throw new DomainException('native_ticket_references_changed');
            $this->createdProof=['issuer'=>$issuer,'actor'=>$actor,'id'=>$id,'frame'=>$this->createdFrame($issuer,$id)];
            $this->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'company_ticket',$id);
            (new NativeCompanyCustomFields($this->db,$this->p))->saveCreatedInOwnedTransaction($issuer,$actor,'tickets',$id,$data['custom_fields'],$this);
            (new NativeCompanyResourceTags($this->db,$this->p))->createTicketTagsInOwnedTransaction($issuer,$actor,$id,$data['tags'],$this);
            foreach($normalized as$n=>$file){$key=hash('sha256','native-ticket-create-file|'.$issuer.'|'.$request.'|'.$n);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_ticket_files(file_key,issuer_id,ticket_id,actor_id,filename,mime,content_hash,byte_size,content,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,1,?)',[$key,$issuer,$id,$actor,$file['name'],$file['mime'],$file['hash'],strlen($file['bytes']),$file['bytes'],$now]);}
            if($normalized){$snapshot=$this->ticketFileFrame($issuer,$id);if(count($snapshot)!==count($normalized))throw new RuntimeException('issuer_ticket_write_unconfirmed');$expectedFiles=[];foreach($normalized as$n=>$expected)$expectedFiles[hash('sha256','native-ticket-create-file|'.$issuer.'|'.$request.'|'.$n)]=$expected;foreach($snapshot as$file){$expected=$expectedFiles[$file['file_key']]??null;if($expected===null||(int)$file['issuer_id']!==$issuer||(int)$file['ticket_id']!==$id||(int)$file['actor_id']!==$actor||(int)$file['active']!==1||$file['filename']!==$expected['name']||$file['mime']!==$expected['mime']||$file['content_hash']!==$expected['hash']||(int)$file['byte_size']!==strlen($expected['bytes'])||!hash_equals($file['content_hash'],hash('sha256',$file['content'])))throw new RuntimeException('issuer_ticket_write_unconfirmed');}$this->createdFilesProof=[$id,$snapshot];}
            return$id;
        });return NativeCompanyResourceId::encode('company_ticket',$id);}finally{$this->pendingCreate=null;$this->createdProof=null;$this->pendingFiles=false;$this->createdFilesProof=null;}
    }
    private function ticketFileFrame(int $issuer,int $ticket):array{return$this->rows('SELECT file_key,issuer_id,ticket_id,actor_id,filename,mime,content_hash,byte_size,content,active,created_at FROM '.$this->p.'company_ticket_files WHERE issuer_id=? AND ticket_id=? ORDER BY file_key',[$issuer,$ticket]);}
    private function createdFrame(int $issuer,int $id):string
    {
        $row=$this->rows('SELECT * FROM '.$this->p.'company_tickets WHERE issuer_id=? AND id=?',[$issuer,$id])[0]??null;
        $metadata=$this->rows('SELECT * FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=?',[$issuer,$id])[0]??null;
        $refs=$this->rows('SELECT * FROM '.$this->p.NativeCompanyTicketCatalogSchema::REFERENCES.' WHERE issuer_id=? AND ticket_id=? ORDER BY kind,id',[$issuer,$id]);
        if(!$row||!$metadata||count($refs)<2)throw new DomainException('native_ticket_creation_proof_denied');
        return hash('sha256',json_encode([$row,$metadata,$refs],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
    /** Capability is minted by this final writer's private callback, never by a submitted ID/flag. */
    public function assertCreatedInOwned($db,string $p,int $issuer,int $actor,string $source,$id):void
    {
        if($db!==$this->db||$p!==$this->p||$source!=='company_ticket'||$this->pendingCreate===null||$this->createdProof===null||
            $this->createdProof['issuer']!==$issuer||$this->createdProof['actor']!==$actor||$this->createdProof['id']!==$id)throw new DomainException('native_ticket_creation_proof_denied');
        IssuerTransaction::assertOwned($db,$p,['issuers_registry','company_tickets',NativeCompanyDepartmentSchema::TICKETS,NativeCompanyTicketCatalogSchema::REFERENCES]);
        $this->authorize($issuer,$actor,'create');if(!hash_equals($this->createdProof['frame'],$this->createdFrame($issuer,$id)))throw new DomainException('native_ticket_creation_proof_denied');
    }
}
