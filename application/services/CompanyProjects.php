<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyProjectsStorage.php';
require_once __DIR__.'/CompanyTaskBillingSourceLocks.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
final class CompanyProjects extends CompanyProjectsStorage
{
    private static ?WeakMap $nativeCreationProofs=null;
    /** Private, short-lived proof: callers cannot manufacture or retain a create-only write capability. */
    public static function assertNativeCreationProof(object$proof,$db,string$p,int$issuer,int$actor,int$project):void
    {
        $record=self::$nativeCreationProofs[$proof]??null;if(!$record||$record['db']!==$db||$record['prefix']!==$p||$record['issuer']!==$issuer||$record['actor']!==$actor||$record['project']!==$project)throw new DomainException('native_project_creation_proof_denied');
        IssuerTransaction::assertOwned($db,$p,['company_projects','company_project_requests']);
        if(!(new IssuerResourceAccess($db,$p))->can($issuer,$actor,'projects','create'))throw new DomainException('native_project_creation_proof_denied');
        $rows=IssuerStorage::query($db,'SELECT * FROM '.$p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])->result_array();if(count($rows)!==1||$rows[0]!==$record['row'])throw new DomainException('native_project_creation_proof_denied');
    }
    use CompanyTaskBillingSourceLocks { apiTables as private billingApiTables; }
    protected function apiTables():array
    {
        $tables=$this->billingApiTables();
        if($this->db->table_exists($this->p.'company_project_native_form_state')){require_once __DIR__.'/NativeCompanyProjectFormState.php';$tables=array_merge($tables,NativeCompanyProjectFormState::tables());}
        if($this->db->table_exists($this->p.'company_project_members')){require_once __DIR__.'/NativeCompanyWorkAssignments.php';$tables=array_merge($tables,(new NativeCompanyWorkAssignments($this->db,$this->p))->tableNames('project'));}
        if($this->db->table_exists($this->p.'company_custom_fields')){require_once __DIR__.'/NativeCompanyCustomFields.php';$tables=array_merge($tables,(new NativeCompanyCustomFields($this->db,$this->p))->tableNames());}
        if($this->db->table_exists($this->p.'company_work_tags')){require_once __DIR__.'/NativeCompanyResourceTags.php';$tables=array_merge($tables,NativeCompanyResourceTags::tableNames());}
        if($this->db->table_exists($this->p.'company_project_native_metadata')){
            require_once __DIR__.'/NativeCompanyProjectMetadata.php';NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->p);
            $tables=array_merge($tables,NativeCompanyProjectMetadata::tables($this->db,$this->p));
        }
        return array_values(array_unique($tables));
    }
    protected function operationFrame(int $issuer,int $actor,int $id,string $operation):?array
    {
        if(!in_array($operation,['project_updated','native_project_updated','native_project_created'],true))return parent::operationFrame($issuer,$actor,$id,$operation);
        $frame=['project'=>$this->row($issuer,$id)];
        if($this->db->table_exists($this->p.'company_project_native_metadata'))$frame['native']=$this->rows('SELECT * FROM '.$this->p.'company_project_native_metadata WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$id]);
        if(str_starts_with($operation,'native_project_')){
            if($this->db->table_exists($this->p.'company_project_members'))$frame['members']=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($issuer,$actor,'project',$id);
            $canonical=NativeCompanyResourceId::encode('company_project',$id);
            if($this->db->table_exists($this->p.'company_work_tags'))$frame['tags']=(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'project',$canonical);
            if($this->db->table_exists($this->p.'company_custom_fields'))$frame['custom_fields']=(new NativeCompanyCustomFields($this->db,$this->p))->values($issuer,$actor,'projects',$canonical);
            if($this->db->table_exists($this->p.'company_project_native_form_state'))$frame['form_state']=(new NativeCompanyProjectFormState($this->db,$this->p))->readInOwnedTransaction($issuer,$actor,$id);
        }
        return $frame;
    }
    protected string $feature='projects';
    private static function input(array $input,bool$native=false):array
    {
        if(array_diff(array_keys($input),['client_id','responsible_id','name','description','start_date','due_date','status']))throw new DomainException('company_project_invalid_input');
        $data=['client_id'=>IssuerPolicy::id($input['client_id']??null),'responsible_id'=>$native&&in_array($input['responsible_id']??null,[0,'0'],true)?0:IssuerPolicy::id($input['responsible_id']??null),'name'=>self::text($input['name']??null,191),'description'=>self::text($input['description']??'',4000,false),'start_date'=>self::date($input['start_date']??null),'due_date'=>($input['due_date']??null)===null||$input['due_date']===''?null:self::date($input['due_date']),'status'=>$input['status']??'active'];
        if(!in_array($data['status'],['active','completed','cancelled'],true)||($data['due_date']!==null&&$data['due_date']<$data['start_date']))throw new DomainException('company_project_invalid_input');return $data;
    }
    private function references(int $issuer,int $actor,array $data,bool$native=false):void
    {
        $client=(new CompanyClients($this->db,$this->p))->get($issuer,$actor,$data['client_id']);if(!$client||$client['active']!==1)throw new DomainException('company_project_reference_denied');if(!$native||$data['responsible_id']!==0)$this->responsible($issuer,$data['responsible_id']);
    }
    private function row(int $issuer,int $id):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])[0]??null;if(!$r)throw new DomainException('company_project_access_denied');return $r;
    }
    private function insertCreatedInOwned(int $issuer,int $actor,array $data,bool$native=false):int
    {
        if($data['due_date']===null){require_once __DIR__.'/NativeCompanyProjectDeadlineSchema.php';NativeCompanyProjectDeadlineSchema::assertValid($this->db,$this->p);}$this->references($issuer,$actor,$data,$native);$now=date('Y-m-d H:i:s');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_projects(issuer_id,client_id,responsible_id,name,description,start_date,due_date,status,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,1,?,?)',[$issuer,$data['client_id'],$data['responsible_id'],$data['name'],$data['description'],$data['start_date'],$data['due_date'],$data['status'],$now,$now]);$id=(int)$this->db->insert_id();$row=$this->row($issuer,$id);foreach($data as $f=>$v)if($v===null?$row[$f]!==null:(string)$row[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');return $id;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);if($data['status']!=='active')throw new DomainException('company_project_invalid_input');
        return $this->operation($issuer,$actor,'create',$key,'project_created',$data,fn():int=>$this->insertCreatedInOwned($issuer,$actor,$data));
    }
    public function createNative($issuer,$actor,array$input,array$metadata,string$key,array$members=[],?array$decorations=null,?array$formState=null):int
    {
        require_once __DIR__.'/NativeCompanyProjectMetadata.php';NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->p);
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input,true);$metadata=NativeCompanyProjectMetadataInput::normalize($metadata,$data['status']);
        if($decorations!==null&&(array_keys($decorations)!==['tags','custom_fields']||!is_array($decorations['tags'])||!is_array($decorations['custom_fields'])))throw new DomainException('company_project_invalid_input');
        if($data['responsible_id']!==0||$metadata['workflow_status']===0)throw new DomainException('native_project_configuration_invalid');
        return$this->operation($issuer,$actor,'create',$key,'native_project_created',[$data,$metadata,$members,$decorations,$formState],function()use($issuer,$actor,$data,$metadata,$members,$decorations,$formState):int{
            $id=$this->insertCreatedInOwned($issuer,$actor,$data,true);$proof=new stdClass();self::$nativeCreationProofs??=new WeakMap();
            self::$nativeCreationProofs[$proof]=['db'=>$this->db,'prefix'=>$this->p,'issuer'=>$issuer,'actor'=>$actor,'project'=>$id,'row'=>$this->row($issuer,$id)];
            try{(new NativeCompanyProjectMetadata($this->db,$this->p))->writeCreatedInOwnedTransaction($issuer,$actor,$id,$metadata,$proof);if($members!==[]){require_once __DIR__.'/NativeCompanyWorkAssignments.php';(new NativeCompanyWorkAssignments($this->db,$this->p))->createProjectMembersInOwnedTransaction($issuer,$actor,$id,$members,$proof);}if($decorations!==null){require_once __DIR__.'/NativeCompanyCustomFields.php';require_once __DIR__.'/NativeCompanyResourceTags.php';(new NativeCompanyCustomFields($this->db,$this->p))->saveCreatedProjectInOwnedTransaction($issuer,$actor,$id,$decorations['custom_fields'],$proof);(new NativeCompanyResourceTags($this->db,$this->p))->createProjectTagsInOwnedTransaction($issuer,$actor,$id,$decorations['tags'],$proof);}if($formState!==null){require_once __DIR__.'/NativeCompanyProjectFormState.php';$state=new NativeCompanyProjectFormState($this->db,$this->p);$before=$state->readInOwnedTransaction($issuer,$actor,$id);$state->saveInOwnedTransaction($issuer,$actor,$id,$before['revision'],$formState,$proof);}}
            finally{unset(self::$nativeCreationProofs[$proof]);}
            return$id;
        });
    }
    private function nativeUnassignedOrigin(int$issuer,int$project):void
    {
        $rows=$this->rows('SELECT actor_id,input_hash FROM '.$this->p.'company_project_requests WHERE issuer_id=? AND result_id=? AND operation=? FOR UPDATE',[$issuer,$project,'native_project_created']);
        if(count($rows)!==1||!preg_match('/^[a-f0-9]{64}$/D',(string)$rows[0]['input_hash'])||(int)$rows[0]['actor_id']<1)throw new DomainException('native_project_origin_unconfirmed');
    }
    /** Canonical copy compositor; cannot join an arbitrary caller transaction. */
    public function createCopyInOwnedTransaction($issuer,$actor,array $input):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$data=self::input($input);if($data['status']!=='active')throw new DomainException('company_project_invalid_input');
        CompanyProjectsSchema::assertValid($this->db,$this->p);CompanyProjectExtrasSchema::assertValid($this->db,$this->p);CompanyStandaloneTasksSchema::assertValid($this->db,$this->p);
        IssuerTransaction::assertOwned($this->db,$this->p,['company_project_copy_requests','company_project_copy_snapshots']);
        IssuerTransaction::assertOwned($this->db,$this->p,array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_clients','issuers_client_links','clients','issuers_audit'],array_keys(CompanyProjectsSchema::definitions($this->p)),array_keys(CompanyProjectExtrasSchema::definitions($this->p)),array_keys(CompanyStandaloneTasksSchema::definitions($this->p)),$this->apiTables()));
        return ($this->guardedWork(function()use($issuer,$actor,$data):int{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'create');$id=$this->insertCreatedInOwned($issuer,$actor,$data);$this->audit($issuer,$actor,$id,'company_project_created');$this->authorize($issuer,$actor,'create');return $id;}))();
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input);
        return $this->operation($issuer,$actor,'edit',$key,'project_updated',[$id,$revision,$data],fn():int=>$this->updateRowInOwned($issuer,$actor,$id,$revision,$data,true));
    }
    public function updateNative($issuer,$actor,$id,$revision,array$input,int$metadataRevision,array$metadata,string$key,?array$related=null):int
    {
        require_once __DIR__.'/NativeCompanyProjectMetadata.php';NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->p);
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);$revision=self::revision($revision);$data=self::input($input,true);
        $metadata=NativeCompanyProjectMetadataInput::normalize($metadata,$data['status']);
        if($related!==null&&(array_diff(array_keys($related),['members','members_revision','tags','tags_revision','custom_fields','form_state','form_state_revision'])||array_diff(['members','members_revision','tags','tags_revision','custom_fields','form_state','form_state_revision'],array_keys($related))))throw new DomainException('company_project_invalid_input');
        $payload=[$id,$revision,$data,$metadataRevision,$metadata];if($related!==null)$payload[]=$related;
        return $this->operation($issuer,$actor,'edit',$key,'native_project_updated',$payload,function()use($issuer,$actor,$id,$revision,$data,$metadataRevision,$metadata,$related):int{
            $this->updateRowInOwned($issuer,$actor,$id,$revision,$data,false,true);
            (new NativeCompanyProjectMetadata($this->db,$this->p))->writeInOwnedTransaction($issuer,$actor,$id,$revision+1,$metadataRevision,$metadata);
            if($related!==null){require_once __DIR__.'/NativeCompanyWorkAssignments.php';require_once __DIR__.'/NativeCompanyResourceTags.php';require_once __DIR__.'/NativeCompanyCustomFields.php';require_once __DIR__.'/NativeCompanyProjectFormState.php';$canonical=NativeCompanyResourceId::encode('company_project',$id);
                (new NativeCompanyWorkAssignments($this->db,$this->p))->replaceInOwnedTransaction($issuer,$actor,'project',$id,$related['members_revision'],$related['members']);
                (new NativeCompanyResourceTags($this->db,$this->p))->replaceInOwnedTransaction($issuer,$actor,'project',$canonical,$related['tags_revision'],$related['tags']);
                (new NativeCompanyCustomFields($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,'projects',$canonical,$related['custom_fields']);
                (new NativeCompanyProjectFormState($this->db,$this->p))->saveInOwnedTransaction($issuer,$actor,$id,$related['form_state_revision'],$related['form_state']);
            }return$id;
        });
    }
    private function updateRowInOwned(int$issuer,int$actor,int$id,int$revision,array$data,bool$sync,bool$native=false):int
    {
            if($data['due_date']===null){require_once __DIR__.'/NativeCompanyProjectDeadlineSchema.php';NativeCompanyProjectDeadlineSchema::assertValid($this->db,$this->p);}$old=$this->row($issuer,$id);if((int)$old['revision']!==$revision)throw new DomainException('company_project_stale');if($old['status']!=='active')throw new DomainException('company_project_closed');
            if((int)$old['client_id']!==$data['client_id'])throw new DomainException('company_project_reference_denied');if($data['responsible_id']===0){if(!$native||(int)$old['responsible_id']!==0)throw new DomainException('company_project_reference_denied');$this->nativeUnassignedOrigin($issuer,$id);}$this->references($issuer,$actor,$data,$native);if($data['status']==='cancelled')$this->assertProjectNoPendingBilling($issuer,$id);
            require_once __DIR__.'/CompanyProjectExtras.php';if($data['status']!=='active')CompanyProjectExtras::assertProjectReady($this->db,$this->p,$issuer,$id);
            if($this->rows('SELECT id FROM '.$this->p.'company_project_milestones WHERE issuer_id=? AND project_id=? AND (due_date<? OR (? IS NOT NULL AND due_date>?)) LIMIT 1 FOR UPDATE',[$issuer,$id,$data['start_date'],$data['due_date'],$data['due_date']]))throw new DomainException('company_project_invalid_input');
            if($data['status']!=='active'&&$this->rows('SELECT id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND status<>? LIMIT 1 FOR UPDATE',[$issuer,$id,'done']))throw new DomainException('company_project_open_tasks');
            if($this->rows('SELECT id FROM '.$this->p.'company_tasks WHERE issuer_id=? AND project_id=? AND ((? IS NOT NULL AND due_date>?) OR due_date<?) LIMIT 1 FOR UPDATE',[$issuer,$id,$data['due_date'],$data['due_date'],$data['start_date']]))throw new DomainException('company_project_invalid_input');
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_projects SET responsible_id=?,name=?,description=?,start_date=?,due_date=?,status=?,revision=revision+1,updated_at=? WHERE issuer_id=? AND id=? AND revision=?',[$data['responsible_id'],$data['name'],$data['description'],$data['start_date'],$data['due_date'],$data['status'],date('Y-m-d H:i:s'),$issuer,$id,$revision]);
            $stored=$this->row($issuer,$id);if((int)$stored['revision']!==$revision+1)throw new RuntimeException('issuer_project_write_unconfirmed');foreach($data as $f=>$v)if($v===null?$stored[$f]!==null:(string)$stored[$f]!== (string)$v)throw new RuntimeException('issuer_project_write_unconfirmed');if($sync&&$this->db->table_exists($this->p.'company_project_native_metadata'))(new NativeCompanyProjectMetadata($this->db,$this->p))->syncLifecycleInOwnedTransaction($issuer,$actor,$id,$revision+1);return $id;
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);return $this->transaction(function()use($issuer,$actor,$id):array{(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,'view');$row=$this->row($issuer,$id);$this->authorize($issuer,$actor,'view');return $row;});
    }
    /** Read-only project reference for composition by an already-owned canonical writer. */
    public static function ownedReferenceTables(): array
    {
        return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions',
            'issuers_installation_identity','staff','company_projects'];
    }
    public function getInOwnedTransaction($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        IssuerTransaction::assertOwned($this->db,$this->p,self::ownedReferenceTables());
        // No nested transaction and no broad project models/hooks. The owner already locked issuers_lock.
        (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);
        $this->authorize($issuer,$actor,'view');
        $row=$this->row($issuer,$id);
        $this->authorize($issuer,$actor,'view');
        if($this->row($issuer,$id)!==$row)throw new RuntimeException('issuer_project_write_unconfirmed');
        return $row;
    }
    public function page($issuer,$actor,$after=0):array
    {
        $read=function()use($issuer,$actor,$after){
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=$after===0?0:IssuerPolicy::id($after);$this->authorize($issuer,$actor,'view');$rows=$this->rows('SELECT id,name,status,due_date,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND id>? ORDER BY id LIMIT 51',[$issuer,$after]);$this->authorize($issuer,$actor,'view');return ['items'=>array_slice($rows,0,50),'next'=>count($rows)>50?(int)$rows[49]['id']:null];

        };return $this->originGuard===null?$read():$this->transaction($read);
    }
}
