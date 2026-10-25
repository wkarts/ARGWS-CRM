<?php

declare(strict_types=1);
require_once __DIR__.'/issuers/IssuerResourceAccess.php';
require_once __DIR__.'/NativeCompanyWorkStatusCatalog.php';
require_once __DIR__.'/CustomerRelationBoundary.php';
require_once __DIR__.'/ClientIssuerScope.php';

/** Read projection for the existing projects screen, keeping native and owned storage disjoint. */
final class NativeCompanyProjectRead
{
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
    }
    private function authority(int $issuer,int $actor):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);
        if(!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('native_project_access_denied');
        return ['issuer'=>$issuer,'actor'=>$actor,'primary'=>$acl->primaryId()===$issuer];
    }
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    /** Project association candidates for the native invoice editor, not a declaration that work is billable. */
    public function choices($issuer,$actor,$client,string$query=''):array
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);
        if(mb_strlen($query)>100||preg_match('//u',$query)!==1||preg_match('/[\x00-\x1f\x7f]/',$query))throw new DomainException('native_project_query_invalid');
        $authority=$this->authority($issuer,$actor);$customer=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$client,'view');
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower($query)).'%';
        $ownedSql='SELECT id,name,client_id,status,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND client_id=? AND status=? AND LOWER(name) LIKE ? ESCAPE \'!\' ORDER BY name,id LIMIT 51';
        $owned=$this->rows($ownedSql,[$issuer,$client,'active',$pattern]);$out=[];
        foreach($owned as$row)$out[]=['id'=>NativeCompanyResourceId::encode('company_project',$row['id']),'name'=>$row['name'],'clientid'=>$client,'source_kind'=>'company_project','source_id'=>(int)$row['id'],'source_revision'=>(int)$row['revision'],'status'=>$row['status']];
        $legacy=[];$legacySql=null;
        if($authority['primary']){
            $legacySql='SELECT p.id,p.name,p.clientid,p.status FROM '.$this->p.'projects p WHERE p.clientid=? AND '.\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'projects','p').' AND LOWER(p.name) LIKE ? ESCAPE \'!\' ORDER BY p.name,p.id LIMIT 51';
            $legacy=$this->rows($legacySql,[$client,$pattern]);
            foreach($legacy as$row)$out[]=['id'=>(string)$row['id'],'name'=>$row['name'],'clientid'=>$client,'source_kind'=>'project','source_id'=>(int)$row['id'],'source_revision'=>null,'status'=>(int)$row['status']];
        }
        if(count($out)>50)throw new DomainException('native_project_query_refine');
        if($this->authority($issuer,$actor)!==$authority||NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$client,'view')!==$customer
            ||$this->rows($ownedSql,[$issuer,$client,'active',$pattern])!==$owned||($legacySql!==null&&$this->rows($legacySql,[$client,$pattern])!==$legacy))throw new DomainException('native_project_snapshot_changed');
        return$out;
    }
    /** Already-declared whole-task quotes for the native invoice selector; never prepares or reserves billing. */
    public function billingCandidates($issuer,$actor,$canonicalProject,$client):array
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';require_once __DIR__.'/CompanyTaskBilling.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$client=IssuerPolicy::id($client);$authority=$this->authority($issuer,$actor);
        $authorize=function()use($issuer,$actor):void{$acl=new IssuerResourceAccess($this->db,$this->p);foreach(['tasks','customers','invoices']as$feature)if(!$acl->can($issuer,$actor,$feature,'view'))throw new DomainException('company_task_billing_read_denied');};$authorize();
        $resolve=fn()=>NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$canonicalProject,'view');$project=$resolve();
        if($project['source_kind']!=='company_project')throw new DomainException('native_project_legacy_billing_adapter_required');
        $customer=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$client,'view');
        $parent=$this->rows('SELECT client_id,revision FROM '.$this->p.'company_projects WHERE issuer_id=? AND id=?',[$issuer,$project['source_id']])[0]??null;
        if(!$parent||(int)$parent['client_id']!==$client)throw new DomainException('native_project_reference_denied');
        $sql='SELECT t.id,t.name,t.revision FROM '.$this->p.'company_tasks t JOIN '.$this->p.'company_task_billing_links l ON l.issuer_id=t.issuer_id AND l.task_id=t.id WHERE t.issuer_id=? AND t.project_id=? AND l.state=? ORDER BY t.id LIMIT 101';$args=[$issuer,$project['source_id'],'ready'];$rows=$this->rows($sql,$args);if(count($rows)>100)throw new DomainException('native_project_query_refine');
        $billing=new CompanyTaskBilling($this->db,$this->p);$items=[];$unavailable=[];$proofs=[];
        foreach($rows as$row){try{$proof=$billing->readCandidate($issuer,$actor,$row['id']);}catch(DomainException$e){if(!in_array($e->getMessage(),['company_task_billing_stale','company_task_billing_not_ready','company_task_billing_timer','company_time_billing_overlap','company_project_fixed_billing_immutable','company_sales_origin_changed','company_project_rate_stale'],true))throw$e;$unavailable[]=['id'=>NativeCompanyResourceId::encode('company_task',$row['id']),'reason'=>$e->getMessage()];continue;}
            if(!$proof['available']){$unavailable[]=['id'=>NativeCompanyResourceId::encode('company_task',$row['id']),'reason'=>$proof['reason']];continue;}
            if($proof['project_id']!==(int)$project['source_id']||$proof['client_id']!==$client||$proof['task_revision']!==(int)$row['revision'])throw new DomainException('native_project_snapshot_changed');
            $proofs[(int)$row['id']]=$proof;$items[]=['id'=>NativeCompanyResourceId::encode('company_task',$row['id']),'name'=>$row['name'],'source_kind'=>'company_task','source_id'=>(int)$row['id'],'source_revision'=>(int)$row['revision'],'project_id'=>(string)$canonicalProject,'client_id'=>$client,'billing_origin_id'=>$proof['origin_id'],'source_hash'=>$proof['source_hash'],'payload_hash'=>$proof['payload_hash'],'currency'=>$proof['currency'],'items'=>$proof['payload']['items']];
        }
        foreach($proofs as$id=>$proof)if($billing->readCandidate($issuer,$actor,$id)!==$proof)throw new DomainException('native_project_snapshot_changed');
        if($this->rows($sql,$args)!==$rows||$resolve()!==$project||$this->authority($issuer,$actor)!==$authority||NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$client,'view')!==$customer)throw new DomainException('native_project_snapshot_changed');
        $authorize();return['project'=>$project,'client_id'=>$client,'items'=>$items,'unavailable'=>$unavailable,'basis'=>'existing_whole_task_quotes_only'];
    }
    /** Native Misc AJAX relation search: completed projects remain selectable; this is not billing authority. */
    public function relationOptions($issuer,$actor,string$query='', $customerId=null,int$limit=50):array
    {
        require_once __DIR__.'/NativeCompanyResourceReferences.php';
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);
        if($limit<1||$limit>50||mb_strlen($query)>100||preg_match('//u',$query)!==1||preg_match('/[\x00-\x1f\x7f]/',$query))throw new DomainException('native_project_query_invalid');
        $authority=$this->authority($issuer,$actor);$customer=null;
        if($customerId!==null&&$customerId!==''){$customerId=IssuerPolicy::id($customerId);$customer=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$customerId,'view');}
        else$customerId=null;
        $pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],mb_strtolower($query)).'%';$args=[$issuer,$pattern];
        $ownedSql='SELECT p.id,p.name,p.client_id,p.status,p.revision FROM '.$this->p.'company_projects p WHERE p.issuer_id=? AND LOWER(p.name) LIKE ? ESCAPE \'!\'';
        if($customerId!==null){$ownedSql.=' AND p.client_id=?';$args[]=$customerId;}$ownedSql.=' ORDER BY p.name,p.id LIMIT '.$limit;
        $owned=$this->rows($ownedSql,$args);$out=[];
        foreach($owned as$row){$id=NativeCompanyResourceId::encode('company_project',$row['id']);$out[]=['id'=>$id,'name'=>$row['name'],'link'=>'projects/view/'.$id,'source_kind'=>'company_project','source_id'=>(int)$row['id'],'clientid'=>(int)$row['client_id'],'source_revision'=>(int)$row['revision']];}
        $legacy=[];$legacySql=null;$legacyArgs=[$pattern];
        if($authority['primary']){$legacySql='SELECT p.id,p.name,p.clientid,p.status FROM '.$this->p.'projects p WHERE '.\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'projects','p').' AND LOWER(p.name) LIKE ? ESCAPE \'!\'';
            if($customerId!==null){$legacySql.=' AND p.clientid=?';$legacyArgs[]=$customerId;}$legacySql.=' ORDER BY p.name,p.id LIMIT '.$limit;$legacy=$this->rows($legacySql,$legacyArgs);
            foreach($legacy as$row)$out[]=['id'=>(string)$row['id'],'name'=>$row['name'],'link'=>'projects/view/'.$row['id'],'source_kind'=>'project','source_id'=>(int)$row['id'],'clientid'=>(int)$row['clientid'],'source_revision'=>null];}
        if($this->authority($issuer,$actor)!==$authority||$this->rows($ownedSql,$args)!==$owned||($legacySql!==null&&$this->rows($legacySql,$legacyArgs)!==$legacy)||($customer!==null&&NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$customerId,'view')!==$customer))throw new DomainException('native_project_snapshot_changed');
        usort($out,static fn($a,$b)=>[mb_strtolower($a['name']),$a['source_kind'],$a['source_id']]<=>[mb_strtolower($b['name']),$b['source_kind'],$b['source_id']]);return array_slice($out,0,$limit);
    }
    private function ownedSnapshot(int $issuer):array
    {
        $metadata=$this->db->table_exists($this->p.'company_project_native_metadata');
        if($metadata){require_once __DIR__.'/NativeCompanyProjectMetadataSchema.php';NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->p);}
        $sql=$metadata
            ? 'SELECT p.status,m.workflow_status,COUNT(*) count FROM '.$this->p.'company_projects p LEFT JOIN '.$this->p.'company_project_native_metadata m ON m.issuer_id=p.issuer_id AND m.project_id=p.id WHERE p.issuer_id=? GROUP BY p.status,m.workflow_status ORDER BY p.status,m.workflow_status'
            : 'SELECT status,NULL workflow_status,COUNT(*) count FROM '.$this->p.'company_projects WHERE issuer_id=? GROUP BY status ORDER BY status';
        $rows=$this->rows($sql,[$issuer]);$counts=[];
        foreach($rows as$row){$state=NativeCompanyWorkStatusCatalog::projectState((string)$row['status'],$row['workflow_status']);$counts[$state]=($counts[$state]??0)+(int)$row['count'];}
        return ['sql'=>$sql,'rows'=>$rows,'counts'=>$counts];
    }
    /** Actual owned phases for the native dashboard, with no financial-field hydration. */
    public function ownedStatusCounts($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$authority=$this->authority($issuer,$actor);$snapshot=$this->ownedSnapshot($issuer);
        if($this->authority($issuer,$actor)!==$authority||$this->ownedSnapshot($issuer)!==$snapshot)throw new DomainException('native_project_snapshot_changed');
        return $snapshot['counts'];
    }
    public function summary($issuer,$actor,callable$translate):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$authority=$this->authority($issuer,$actor);
        $statuses=NativeCompanyWorkStatusCatalog::owned('project',$translate);$counts=[];
        foreach($statuses as$status)$counts[$status['id']]=0;
        $snapshot=$this->ownedSnapshot($issuer);$owned=$snapshot['rows'];
        foreach($snapshot['counts']as$state=>$count)$counts[NativeCompanyWorkStatusCatalog::id('project',$state)]=$count;
        $legacy=[];$legacySql=null;
        if($authority['primary']){
            $definitions=WorkStatusPresentation::projects($translate);
            foreach($definitions as$status){$status['id']=(string)$status['id'];$status['source_kind']='project_status';$status['source_id']=(int)$status['id'];$status['state']=(int)$status['id'];$statuses[]=$status;$counts[$status['id']]=0;}
            $legacySql='SELECT p.status,COUNT(*) count FROM '.$this->p.'projects p WHERE '.\app\services\CustomerRelationBoundary::sql($this->db,$this->p,'projects','p').' GROUP BY p.status ORDER BY p.status';
            $legacy=$this->rows($legacySql);
            $ids=array_column($definitions,'id');
            foreach($legacy as$row){NativeCompanyWorkStatusCatalog::predicate('project',(string)$row['status'],$ids);$counts[(string)$row['status']]=(int)$row['count'];}
        }
        // Do not return a mixture of an old grant/primary identity and newly selected rows.
        if($this->authority($issuer,$actor)!==$authority||$this->ownedSnapshot($issuer)!==$snapshot
            ||($legacySql!==null&&$this->rows($legacySql)!==$legacy))throw new DomainException('native_project_snapshot_changed');
        return ['statuses'=>$statuses,'status_counts'=>$counts,'total'=>array_sum($counts),
            'lineages'=>['owned'=>array_sum(array_column($owned,'count')),'legacy'=>array_sum(array_column($legacy,'count'))],
            'primary_included'=>$authority['primary']];
    }
}
