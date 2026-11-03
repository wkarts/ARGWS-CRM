<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyProjectMetadataSchema.php';
require_once __DIR__.'/NativeCompanyProjectMetadataInput.php';
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/CompanyTaskTimeBillingFence.php';
require_once __DIR__.'/CompanyProjectFixedBillingFence.php';
require_once __DIR__.'/CompanyTaskBillingSchema.php';
require_once __DIR__.'/CompanyProjectBillingRates.php';

/** Explicit project configuration compositor. The surrounding project operation owns transaction and lifecycle changes. */
final class NativeCompanyProjectMetadata
{
    public function __construct(private$db,private string$p){NativeCompanyProjectMetadataSchema::definitions($p);}
    public static function tables($db,string$p):array
    {
        $tables=array_merge(['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','company_projects','company_tasks','currencies','issuers_audit',NativeCompanyProjectMetadataSchema::TABLE],CompanyTaskTimeBillingFence::tables($db,$p),CompanyProjectFixedBillingFence::tables($db,$p));
        if($db->table_exists($p.'company_task_billing_links')||$db->table_exists($p.'company_task_billing_origins')){CompanyTaskBillingSchema::assertValid($db,$p);$tables=array_merge($tables,['company_task_billing_links','company_task_billing_origins']);}
        if($db->table_exists($p.'company_project_billing_rates')){CompanyProjectBillingRateSchema::assertValid($db,$p);$tables=array_merge($tables,(new CompanyProjectBillingRates($db,$p))->tables());}
        return array_values(array_unique($tables));
    }
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function project(int$issuer,int$actor,int$project,string$cap):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,self::tables($this->db,$this->p));NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->p);
        $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
        if(!$acl->can($issuer,$actor,'projects','view')||!$acl->can($issuer,$actor,'projects',$cap))throw new DomainException('native_project_access_denied');
        $row=$this->rows('SELECT * FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$project])[0]??null;
        if(!$row)throw new DomainException('native_project_access_denied');return$row;
    }
    private function authorizeMoney(int$issuer,int$actor,bool$write):void
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        foreach([['customers','view'],['invoices','view']]as[$feature,$cap])if(!$acl->can($issuer,$actor,$feature,$cap))throw new DomainException('company_project_rate_denied');
        if($write&&!$acl->can($issuer,$actor,'invoices','create'))throw new DomainException('company_project_rate_denied');
    }
    private function row(int$issuer,int$project):?array
    {return$this->rows('SELECT * FROM '.$this->p.NativeCompanyProjectMetadataSchema::TABLE.' WHERE issuer_id=? AND project_id=? FOR UPDATE',[$issuer,$project])[0]??null;}
    private function verify(array$row,array$project,int$actor):array
    {
        $input=array_intersect_key($row,array_fill_keys(NativeCompanyProjectMetadataInput::FIELDS,true));
        $normal=NativeCompanyProjectMetadataInput::normalize($input,$project['status']);
        foreach($normal as$key=>$value)if($value===null?$row[$key]!==null:(string)$row[$key]!== (string)$value)throw new RuntimeException('native_project_configuration_unconfirmed');
        if((int)$row['revision']<1||(int)$row['issuer_id']!==(int)$project['issuer_id']||(int)$row['project_id']!==(int)$project['id'])throw new RuntimeException('native_project_configuration_unconfirmed');
        if(count($this->rows('SELECT id FROM '.$this->p.'currencies WHERE id=?',[$normal['currency_id']]))!==1)throw new DomainException('native_project_currency_invalid');
        $hourly=$normal['billing_type']===2&&$normal['project_rate_per_hour']!=='0.00';
        if($hourly){
            $proof=(new CompanyProjectBillingRates($this->db,$this->p))->inOwned((int)$project['issuer_id'],$actor,(int)$project['id'],IssuerPolicy::id($row['hourly_rate_id']??null));
            if($proof['payload']['rate']!==$normal['project_rate_per_hour']||$proof['payload']['currency_id']!==$normal['currency_id']||$proof['payload']['round_mode']!=='none'||$proof['payload']['round_minutes']!==0)throw new RuntimeException('native_project_configuration_unconfirmed');
        }elseif($row['hourly_rate_id']!==null)throw new RuntimeException('native_project_configuration_unconfirmed');
        return$normal;
    }
    public function readInOwnedTransaction($issuer,$actor,$project):?array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);
        $source=$this->project($issuer,$actor,$project,'view');$row=$this->row($issuer,$project);if($row===null)return null;
        $this->authorizeMoney($issuer,$actor,false);$this->verify($row,$source,$actor);if($this->project($issuer,$actor,$project,'view')!==$source||$this->row($issuer,$project)!==$row)throw new RuntimeException('native_project_configuration_unconfirmed');return$row;
    }
    /** Same immutable billing fences used by the writer; absence of a reservation is not a financial approval. */
    public function billingLockedInOwnedTransaction(int$issuer,int$actor,int$project):bool
    {
        $source=$this->project($issuer,$actor,$project,'view');$this->authorizeMoney($issuer,$actor,false);$locked=false;
        try{CompanyTaskTimeBillingFence::assertProjectWholeAvailableInOwned($this->db,$this->p,$issuer,$project);CompanyProjectFixedBillingFence::assertAvailableInOwned($this->db,$this->p,$issuer,$actor,$project);}
        catch(DomainException$e){if(!in_array($e->getMessage(),['company_time_billing_overlap','company_time_batch_overlap','company_project_fixed_billing_immutable'],true))throw$e;$locked=true;}
        if($this->db->table_exists($this->p.'company_task_billing_links'))$locked=$locked||$this->rows('SELECT l.task_id FROM '.$this->p.'company_task_billing_links l JOIN '.$this->p.'company_tasks t ON t.issuer_id=l.issuer_id AND t.id=l.task_id WHERE l.issuer_id=? AND t.project_id=? AND l.state IN (?,?) LIMIT 1 FOR UPDATE',[$issuer,$project,'converting','converted'])!==[];
        if($this->project($issuer,$actor,$project,'view')!==$source)throw new DomainException('native_project_configuration_stale');return$locked;
    }
    /** Keep terminal lifecycle transitions coherent even when an existing API changes the project. No price fields are touched. */
    public function syncLifecycleInOwnedTransaction(int$issuer,int$actor,int$project,int$expectedProjectRevision):void
    {
        $source=$this->project($issuer,$actor,$project,'edit');if((int)$source['revision']!==$expectedProjectRevision)throw new DomainException('company_project_stale');
        $before=$this->row($issuer,$project);if($before===null)return;
        $status=(int)$before['workflow_status'];if(!in_array($status,[0,1,2,3,4,5],true))throw new DomainException('native_project_configuration_invalid');
        $target=match($source['status']){'active'=>in_array($status,[0,1,2,3],true)?$status:throw new DomainException('native_project_workflow_lifecycle_conflict'),'completed'=>4,'cancelled'=>5,default=>throw new DomainException('native_project_workflow_lifecycle_conflict')};
        if($target===$status)return;
        $expected=$before;$expected['workflow_status']=$target;$expected['revision']=(int)$before['revision']+1;
        IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyProjectMetadataSchema::TABLE.' SET workflow_status=?,revision=revision+1 WHERE issuer_id=? AND project_id=? AND revision=?',[$target,$issuer,$project,$before['revision']]);
        $normalize=static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r);
        $stored=$this->row($issuer,$project);if(!$stored||$normalize($stored)!==$normalize($expected))throw new RuntimeException('native_project_configuration_unconfirmed');
        $nonce=bin2hex(random_bytes(16));$json=json_encode(['operation_nonce'=>$nonce,'before_revision'=>(int)$before['revision'],'after_revision'=>(int)$expected['revision'],'workflow_status'=>$target],JSON_THROW_ON_ERROR);$at=gmdate('Y-m-d H:i:s');
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,'native_project_lifecycle_synced',$issuer,$project,$at,$json]);$id=(int)$this->db->insert_id();
        if($id<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND event=? AND issuer_id=? AND subject_id=? AND created_at=? AND metadata_json=?',[$id,$actor,'native_project_lifecycle_synced',$issuer,$project,$at,$json])||$this->row($issuer,$project)!==$stored||$this->project($issuer,$actor,$project,'edit')!==$source)throw new RuntimeException('native_project_configuration_unconfirmed');
    }
    public function writeCreatedInOwnedTransaction(int$issuer,int$actor,int$project,array$input,object$proof):array
    {
        CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,$project);
        return$this->write($issuer,$actor,$project,1,0,$input,$proof);
    }
    /** expectedProjectRevision is the actual post-update revision, verified in the same owned transaction. */
    public function writeInOwnedTransaction($issuer,$actor,$project,$expectedProjectRevision,$expectedMetadataRevision,array$input):array
    {return$this->write($issuer,$actor,$project,$expectedProjectRevision,$expectedMetadataRevision,$input,null);}
    private function write($issuer,$actor,$project,$expectedProjectRevision,$expectedMetadataRevision,array$input,?object$creationProof):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$project=IssuerPolicy::id($project);$expectedProjectRevision=IssuerPolicy::id($expectedProjectRevision);
        if($creationProof!==null)CompanyProjects::assertNativeCreationProof($creationProof,$this->db,$this->p,$issuer,$actor,$project);
        if(!is_int($expectedMetadataRevision)||$expectedMetadataRevision<0||$expectedMetadataRevision>=2147483647)throw new DomainException('native_project_configuration_stale');
        $source=$this->project($issuer,$actor,$project,$creationProof!==null?'create':'edit');if((int)$source['revision']!==$expectedProjectRevision)throw new DomainException('company_project_stale');
        $data=NativeCompanyProjectMetadataInput::normalize($input,$source['status']);
        if(count($this->rows('SELECT id FROM '.$this->p.'currencies WHERE id=? FOR UPDATE',[$data['currency_id']]))!==1)throw new DomainException('native_project_currency_invalid');
        $before=$this->row($issuer,$project);if(($before===null?0:(int)$before['revision'])!==$expectedMetadataRevision)throw new DomainException('native_project_configuration_stale');
        // Monetary settings cannot race a billing conversion or rewrite its mode/currency afterward.
        $moneyChanged=$before===null;foreach(['billing_type','currency_id','project_cost','project_rate_per_hour']as$field)if($before!==null&&($before[$field]===null?$data[$field]!==null:(string)$before[$field]!== (string)$data[$field]))$moneyChanged=true;
        if($moneyChanged){
            $this->authorizeMoney($issuer,$actor,true);
            CompanyTaskTimeBillingFence::assertProjectNoPendingInOwned($this->db,$this->p,$issuer,$project);
            if($before!==null&&((string)$before['billing_type']!==(string)$data['billing_type']||(string)$before['currency_id']!==(string)$data['currency_id']))CompanyTaskTimeBillingFence::assertProjectWholeAvailableInOwned($this->db,$this->p,$issuer,$project);
            CompanyProjectFixedBillingFence::assertAvailableInOwned($this->db,$this->p,$issuer,$actor,$project);
            if($this->db->table_exists($this->p.'company_task_billing_links')){
                $states=$this->rows('SELECT l.state FROM '.$this->p.'company_task_billing_links l JOIN '.$this->p.'company_tasks t ON t.issuer_id=l.issuer_id AND t.id=l.task_id WHERE l.issuer_id=? AND t.project_id=? AND l.state IN (?,?) FOR UPDATE',[$issuer,$project,'converting','converted']);
                foreach($states as$state)if($state['state']==='converting'||($before!==null&&((string)$before['billing_type']!==(string)$data['billing_type']||(string)$before['currency_id']!==(string)$data['currency_id'])))throw new DomainException('company_task_billing_immutable');
            }
        }
        $rateId=null;
        if($data['billing_type']===2&&$data['project_rate_per_hour']!=='0.00'){
            $rates=new CompanyProjectBillingRates($this->db,$this->p);
            if($before!==null&&(string)$before['billing_type']==='2'&&(string)$before['currency_id']===(string)$data['currency_id']&&$before['project_rate_per_hour']===$data['project_rate_per_hour']&&$before['hourly_rate_id']!==null){
                $rateId=IssuerPolicy::id($before['hourly_rate_id']);$proof=$rates->inOwned($issuer,$actor,$project,$rateId);
                if($proof['payload']['rate']!==$data['project_rate_per_hour']||$proof['payload']['currency_id']!==$data['currency_id'])throw new DomainException('company_project_rate_stale');
            }else{
                $rateKey=hash('sha256',json_encode(['native-project-config-rate',$issuer,$actor,$project,$expectedProjectRevision,$expectedMetadataRevision,$data],JSON_THROW_ON_ERROR));
                $rateId=$rates->declareHourlyInOwnedTransaction($issuer,$actor,$project,$expectedProjectRevision,['currency'=>$data['currency_id'],'rate'=>$data['project_rate_per_hour'],'round_mode'=>'none','round_minutes'=>0],$rateKey,$creationProof);
            }
        }
        $data['hourly_rate_id']=$rateId;
        $expected=['issuer_id'=>$issuer,'project_id'=>$project,'revision'=>$expectedMetadataRevision+1]+$data;
        if($before===null){$columns=array_keys($expected);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.NativeCompanyProjectMetadataSchema::TABLE.'('.implode(',',$columns).') VALUES('.implode(',',array_fill(0,count($columns),'?')).')',array_values($expected));}
        else{$columns=array_keys($data);IssuerStorage::query($this->db,'UPDATE '.$this->p.NativeCompanyProjectMetadataSchema::TABLE.' SET '.implode(',',array_map(static fn($key)=>$key.'=?',$columns)).',revision=revision+1 WHERE issuer_id=? AND project_id=? AND revision=?',array_merge(array_values($data),[$issuer,$project,$expectedMetadataRevision]));}
        $normalize=static fn($r)=>array_map(static fn($v)=>$v===null?null:(string)$v,$r);
        $stored=$this->row($issuer,$project);if(!$stored||$normalize($stored)!==$normalize($expected))throw new RuntimeException('native_project_configuration_unconfirmed');
        $audit=['actor_id'=>(string)$actor,'event'=>'native_project_configuration_updated','issuer_id'=>(string)$issuer,'subject_id'=>(string)$project,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>json_encode(['operation_nonce'=>bin2hex(random_bytes(16)),'before_revision'=>$expectedMetadataRevision,'after_revision'=>$expectedMetadataRevision+1],JSON_THROW_ON_ERROR)];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($audit));$auditId=(int)$this->db->insert_id();$receipt=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$auditId])[0]??[];
        if($auditId<1||array_map('strval',$receipt)!==$audit||$this->row($issuer,$project)!==$stored||$this->project($issuer,$actor,$project,$creationProof!==null?'create':'edit')!==$source)throw new RuntimeException('native_project_configuration_unconfirmed');
        if($creationProof!==null)CompanyProjects::assertNativeCreationProof($creationProof,$this->db,$this->p,$issuer,$actor,$project);$this->authorizeMoney($issuer,$actor,$moneyChanged);$this->verify($stored,$source,$actor);return$stored;
    }
}
