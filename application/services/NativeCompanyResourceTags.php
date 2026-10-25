<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyWorkTags.php';

/** Tags attached to the actual resource, never the global suggestion catalogue. */
final class NativeCompanyResourceTags
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function tableNames():array{return ['issuers_lock','issuers_registry','issuers_staff','issuers_resource_permissions','issuers_installation_identity','staff','issuers_audit','company_work_tags','company_work_tag_links','tags','taggables'];}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function reference(int $issuer,int $actor,string $type,$id,string $cap):array
    {
        $ref=NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,$type,$id,$cap);$source=$ref['source_kind'];if($source==='company_task'&&!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'projects','view'))throw new DomainException('native_tags_reference_denied');$owned=str_starts_with($source,'company_');
        if(in_array($source,['invoice','credit_note'],true)){$table=$source==='invoice'?'invoices':'creditnotes';$row=$this->rows('SELECT issuer_id FROM '.$this->p.$table.' WHERE id=?',[$ref['source_id']])[0]??null;if(!$row)throw new DomainException('native_tags_reference_denied');$owned=$row['issuer_id']!==null;if($owned&&(int)$row['issuer_id']!==$issuer)throw new DomainException('native_tags_reference_denied');}
        elseif($source==='client'){$owner=$this->rows('SELECT owner_issuer_id FROM '.$this->p.'issuers_clients WHERE client_id=?',[$ref['source_id']])[0]??null;$owned=$owner!==null;if($owned&&(int)$owner['owner_issuer_id']!==$issuer)throw new DomainException('native_tags_reference_denied');}
        if(!$owned&&(new IssuerResourceAccess($this->db,$this->p))->primaryId()!==$issuer)throw new DomainException('native_tags_reference_denied');return $ref+['tag_lineage'=>$owned?'owned':'legacy','tag_rel_type'=>$source==='client'?'customer':$source];
    }
    private function data(int $issuer,array $ref):array
    {
        if($ref['tag_lineage']==='owned')$rows=$this->rows('SELECT t.id,t.label AS name,t.feature,t.label_hash,t.revision,l.position AS tag_order FROM '.$this->p.'company_work_tag_links l JOIN '.$this->p.'company_work_tags t ON t.issuer_id=l.issuer_id AND t.id=l.tag_id WHERE l.issuer_id=? AND l.source_kind=? AND l.source_id=? ORDER BY l.position,t.id',[$issuer,$ref['source_kind'],$ref['source_id']]);
        else$rows=$this->rows('SELECT t.id,t.name,l.tag_order FROM '.$this->p.'taggables l JOIN '.$this->p.'tags t ON t.id=l.tag_id WHERE l.rel_type=? AND l.rel_id=? ORDER BY l.tag_order,t.id',[$ref['tag_rel_type'],$ref['source_id']]);
        if(count($rows)>100)throw new DomainException('native_tags_limit');$seen=[];$out=[];
        foreach($rows as$row){$name=self::label($row['name']);$hash=hash('sha256',mb_strtolower($name,'UTF-8'));if(isset($seen[$hash]))throw new DomainException('native_tags_invalid');$seen[$hash]=true;
            if($ref['tag_lineage']==='owned'&&($row['feature']!==$ref['feature']||!hash_equals($hash,$row['label_hash'])||(int)$row['revision']<1))throw new DomainException('native_tags_invalid');
            $out[]=['id'=>$ref['tag_lineage']==='owned'?NativeCompanyResourceId::encode('company_work_tag',$row['id']):(string)$row['id'],'name'=>$name,'order'=>(int)$row['tag_order']];
        }return $out;
    }
    private static function label($label):string{if(!is_string($label)||trim($label)!==$label||$label===''||preg_match('//u',$label)!==1||preg_match('/[\x00-\x1f\x7f]/',$label)||mb_strlen($label)>100)throw new DomainException('native_tags_invalid');return $label;}
    private static function hash(array$ref,array$tags):string{return hash('sha256',json_encode([$ref['tag_lineage'],$ref['source_kind'],$ref['source_id'],$tags],JSON_THROW_ON_ERROR));}
    public function read(int $issuer,int $actor,string $type,$id):array
    {
        NativeCompanyWorkTagsSchema::assertValid($this->db,$this->p);$ref=$this->reference($issuer,$actor,$type,$id,'view');$tags=$this->data($issuer,$ref);
        if($this->reference($issuer,$actor,$type,$id,'view')!==$ref||$this->data($issuer,$ref)!==$tags)throw new DomainException('native_tags_changed');return ['tags'=>$tags,'revision'=>self::hash($ref,$tags),'lineage'=>$ref['tag_lineage']];
    }
    /** Invoice wrapper retains the strict edit grant; creation needs a separate verified creation capability. */
    public function replaceInvoiceInOwnedTransaction(int $issuer,int $actor,int $invoice,string $expected,array $labels):array
    {return $this->replaceInOwnedTransaction($issuer,$actor,'invoice',$invoice,$expected,$labels);}
    /** Native resource writer compositor. Never owns/creates the caller transaction itself. */
    public function replaceInOwnedTransaction(int $issuer,int $actor,string $type,$canonicalId,string $expected,array $labels):array
    {return$this->write($issuer,$actor,$type,$canonicalId,$expected,$labels,null);}
    public function createTaskTagsInOwnedTransaction(int$issuer,int$actor,string$source,int$id,array$labels,object$proof):array
    {
        $canonical=NativeCompanyResourceId::encode($source,$id);$ref=$this->taskCreationReference($issuer,$actor,$canonical,$proof);
        if($this->data($issuer,$ref)!==[])throw new DomainException('native_tags_reference_denied');
        return$this->write($issuer,$actor,'task',$canonical,self::hash($ref,[]),$labels,$proof);
    }
    private function taskCreationReference(int$issuer,int$actor,$canonical,object$proof):array
    {
        $identity=NativeCompanyResourceId::decode('task',$canonical);
        if($identity['source_kind']==='company_task'){require_once __DIR__.'/CompanyTasks.php';CompanyTasks::assertNativeTaskCreationProof($proof,$this->db,$this->p,$issuer,$actor,$identity['source_id']);}
        elseif($identity['source_kind']==='company_standalone_task'){require_once __DIR__.'/CompanyStandaloneTasks.php';CompanyStandaloneTasks::assertNativeTaskCreationProof($proof,$this->db,$this->p,$issuer,$actor,$identity['source_id']);}
        else throw new DomainException('native_tags_reference_denied');
        return$this->reference($issuer,$actor,'task',$canonical,'view');
    }
    public function createProjectTagsInOwnedTransaction(int$issuer,int$actor,int$project,array$labels,object$proof):array
    {
        CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,$project);
        $canonical=NativeCompanyResourceId::encode('company_project',$project);$ref=$this->reference($issuer,$actor,'project',$canonical,'view');
        if($ref['source_kind']!=='company_project'||$this->data($issuer,$ref)!==[])throw new DomainException('native_tags_reference_denied');
        return$this->write($issuer,$actor,'project',$canonical,self::hash($ref,[]),$labels,$proof);
    }
    /** Only a live, private creation capability from the final native ticket writer may bypass edit. */
    public function createTicketTagsInOwnedTransaction(int $issuer,int $actor,int $ticket,array $labels,object $proof):array
    {
        if(get_class($proof)!=='NativeCompanyTicketCommands')throw new DomainException('native_tags_reference_denied');
        $proof->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'company_ticket',$ticket);
        $canonical=NativeCompanyResourceId::encode('company_ticket',$ticket);
        $ref=$this->ticketCreationReference($issuer,$actor,$canonical,$proof);
        if($this->data($issuer,$ref)!==[])throw new DomainException('native_tags_reference_denied');
        return $this->write($issuer,$actor,'ticket',$canonical,self::hash($ref,[]),$labels,$proof);
    }
    private function ticketCreationReference(int $issuer,int $actor,$canonical,object $proof):array
    {
        if(get_class($proof)!=='NativeCompanyTicketCommands')throw new DomainException('native_tags_reference_denied');
        $d=NativeCompanyResourceId::decode('ticket',$canonical);
        if($d['source_kind']!=='company_ticket')throw new DomainException('native_tags_reference_denied');
        $proof->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'company_ticket',$d['source_id']);
        require_once __DIR__.'/NativeCompanyTicketRead.php';
        $ref=(new NativeCompanyTicketRead($this->db,$this->p))->referenceInOwnedTransaction($issuer,$actor,$canonical);
        return $ref+['tag_lineage'=>'owned','tag_rel_type'=>'company_ticket'];
    }
    public function createDraftTagsInOwnedTransaction(int $issuer,int $actor,int $draft,array $labels,CompanyInvoiceDrafts $writer):array
    {
        $canonical=NativeCompanyResourceId::encode('company_invoice_draft',$draft);
        $ref=$this->draftCreationReference($issuer,$actor,$canonical,$writer);
        if($this->data($issuer,$ref)!==[])throw new DomainException('native_tags_creation_conflict');
        return $this->write($issuer,$actor,'invoice',$canonical,self::hash($ref,[]),$labels,$writer);
    }
    public function createInvoiceTagsInOwnedTransaction(int $issuer,int $actor,int $invoice,array $labels,CompanyInvoices $writer):array
    {
        $ref=$this->invoiceCreationReference($issuer,$actor,$invoice,$writer);
        if($this->data($issuer,$ref)!==[])throw new DomainException('native_tags_creation_conflict');
        return $this->write($issuer,$actor,'invoice',$invoice,self::hash($ref,[]),$labels,$writer);
    }
    private function invoiceCreationReference(int $issuer,int $actor,$canonical,object $writer):array
    {
        if(get_class($writer)!=='CompanyInvoices')throw new DomainException('native_tags_reference_denied');
        $source=NativeCompanyResourceId::decode('invoice',$canonical);if($source['source_kind']!=='invoice')throw new DomainException('native_tags_reference_denied');
        $writer->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'invoice',$source['source_id']);
        return $this->reference($issuer,$actor,'invoice',$canonical,'view');
    }
    private function draftCreationReference(int $issuer,int $actor,$canonical,object $writer):array
    {
        if(get_class($writer)!=='CompanyInvoiceDrafts')throw new DomainException('native_tags_reference_denied');
        $source=NativeCompanyResourceId::decode('invoice',$canonical);if($source['source_kind']!=='company_invoice_draft')throw new DomainException('native_tags_reference_denied');
        $writer->assertCreatedInOwned($this->db,$this->p,$issuer,$actor,'company_invoice_draft',$source['source_id']);
        return $this->reference($issuer,$actor,'invoice',$canonical,'view');
    }
    private function write(int$issuer,int$actor,string$type,$canonicalId,string$expected,array$labels,?object$proof):array
    {
        if(!preg_match('/^[a-f0-9]{64}$/D',$expected)||!array_is_list($labels)||count($labels)>100)throw new DomainException('native_tags_invalid');
        $labels=array_map([self::class,'label'],$labels);$keys=array_map(static fn($s)=>mb_strtolower($s,'UTF-8'),$labels);if(count(array_unique($keys))!==count($keys))throw new DomainException('native_tags_invalid');
        IssuerTransaction::assertOwned($this->db,$this->p,self::tableNames());NativeCompanyWorkTagsSchema::assertValid($this->db,$this->p);
        (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$authorize=function()use($issuer,$actor,$type,$canonicalId,$proof):array{if($proof===null)return$this->reference($issuer,$actor,$type,$canonicalId,'edit');if($type==='task')return$this->taskCreationReference($issuer,$actor,$canonicalId,$proof);if($type==='ticket')return$this->ticketCreationReference($issuer,$actor,$canonicalId,$proof);if($type==='invoice')return get_class($proof)==='CompanyInvoices'?$this->invoiceCreationReference($issuer,$actor,$canonicalId,$proof):$this->draftCreationReference($issuer,$actor,$canonicalId,$proof);$ref=$this->reference($issuer,$actor,$type,$canonicalId,'view');if($type!=='project'||$ref['source_kind']!=='company_project')throw new DomainException('native_tags_reference_denied');CompanyProjects::assertNativeCreationProof($proof,$this->db,$this->p,$issuer,$actor,(int)$ref['source_id']);return$ref;};$ref=$authorize();
        $resourceTables=['company_invoice_draft'=>'company_invoice_drafts','invoice'=>'invoices','credit_note'=>'creditnotes','project'=>'projects','task'=>'tasks','ticket'=>'tickets','contract'=>'contracts','lead'=>'leads','estimate'=>'estimates','proposal'=>'proposals','expense'=>'expenses','client'=>'clients','company_project'=>'company_projects','company_task'=>'company_tasks','company_standalone_task'=>'company_standalone_tasks','company_ticket'=>'company_tickets','company_contract'=>'company_contracts','company_lead'=>'company_leads','company_estimate'=>'company_sales_documents','company_proposal'=>'company_sales_documents','company_expense'=>'company_expenses'];
        $source=$ref['source_kind'];$table=$resourceTables[$source]??throw new DomainException('native_tags_reference_denied');IssuerTransaction::assertOwned($this->db,$this->p,[$table]);$resourceId=$ref['source_id'];
        $key=$source==='client'?'userid':($source==='ticket'?'ticketid':'id');$ownedSource=str_starts_with($source,'company_');$lock=$this->rows('SELECT '.$key.' FROM '.$this->p.$table.' WHERE '.$key.'=?'.($ownedSource?' AND issuer_id=?':'').' FOR UPDATE',$ownedSource?[$resourceId,$issuer]:[$resourceId]);if(count($lock)!==1)throw new DomainException('native_tags_reference_denied');
        $before=$this->data($issuer,$ref);if(!hash_equals($expected,self::hash($ref,$before)))throw new DomainException('native_tags_stale');
        if($ref['tag_lineage']==='owned')IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'company_work_tag_links WHERE issuer_id=? AND source_kind=? AND source_id=?',[$issuer,$source,$resourceId]);
        else IssuerStorage::query($this->db,'DELETE FROM '.$this->p.'taggables WHERE rel_type=? AND rel_id=?',[$ref['tag_rel_type'],$resourceId]);
        foreach($labels as$position=>$label){
            if($ref['tag_lineage']==='owned'){
                $hash=hash('sha256',mb_strtolower($label,'UTF-8'));$tag=$this->rows('SELECT id,label FROM '.$this->p.'company_work_tags WHERE issuer_id=? AND feature=? AND label_hash=? FOR UPDATE',[$issuer,$ref['feature'],$hash])[0]??null;
                if(!$tag){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_work_tags(issuer_id,feature,label,label_hash,created_by,revision) VALUES(?,?,?,?,?,1)',[$issuer,$ref['feature'],$label,$hash,$actor]);$tag=['id'=>(int)$this->db->insert_id(),'label'=>$label];}
                if($tag['label']!==$label)throw new DomainException('native_tags_label_conflict');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_work_tag_links(issuer_id,tag_id,source_kind,source_id,position) VALUES(?,?,?,?,?)',[$issuer,$tag['id'],$source,$resourceId,$position]);
            }else{
                $matches=$this->rows('SELECT id,name FROM '.$this->p.'tags WHERE name=? ORDER BY id FOR UPDATE',[$label]);if(count($matches)>1)throw new DomainException('native_tags_label_conflict');$tag=$matches[0]??null;
                if(!$tag){IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'tags(name) VALUES(?)',[$label]);$tag=['id'=>(int)$this->db->insert_id(),'name'=>$label];}
                if($tag['name']!==$label)throw new DomainException('native_tags_label_conflict');IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'taggables(rel_id,rel_type,tag_id,tag_order) VALUES(?,?,?,?)',[$resourceId,$ref['tag_rel_type'],$tag['id'],$position]);
            }
        }
        $after=$this->data($issuer,$ref);if(array_column($after,'name')!==$labels||array_column($after,'order')!==array_keys($labels))throw new RuntimeException('native_tags_write_unconfirmed');
        $receipt=['actor_id'=>(string)$actor,'event'=>'native_'.$type.'_tags_updated','issuer_id'=>(string)$issuer,'subject_id'=>(string)$resourceId,'created_at'=>gmdate('Y-m-d H:i:s'),'metadata_json'=>json_encode(['operation_nonce'=>bin2hex(random_bytes(16)),'lineage'=>$ref['tag_lineage'],'before_hash'=>$expected,'after_hash'=>self::hash($ref,$after)],JSON_THROW_ON_ERROR)];
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',array_values($receipt));$audit=(int)$this->db->insert_id();$actual=$this->rows('SELECT actor_id,event,issuer_id,subject_id,created_at,metadata_json FROM '.$this->p.'issuers_audit WHERE id=?',[$audit])[0]??[];
        if($audit<1||array_map('strval',$actual)!==$receipt||$this->data($issuer,$ref)!==$after)throw new RuntimeException('native_tags_write_unconfirmed');
        if($authorize()!==$ref)throw new DomainException('native_tags_changed');
        return ['tags'=>$after,'revision'=>self::hash($ref,$after),'lineage'=>$ref['tag_lineage']];
    }

}
