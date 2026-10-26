<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTicketRead.php';
require_once __DIR__.'/NativeCompanyTicketTable.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyResourceReferences.php';
/** Native index selection: authorize identities before filter/count/private row projection. */
final class NativeCompanyTicketIndexRead
{
    public const COLUMNS=['bulk','ticketid','subject','tags','department_name','service_name','contact_full_name','status','priority','lastreply','date'];
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function offset(string$source):int{return(int)NativeCompanyResourceId::encode($source,1)-1;}
    private function normalizeScope(array$scope):array
    {
        $allowed=['customer'=>'client','project'=>'project','via_ticket'=>'ticket','status'=>'ticket_status'];
        foreach($scope as$kind=>$id){
            if(!isset($allowed[$kind])||(!is_string($id)&&!is_int($id)))throw new DomainException('native_ticket_scope_invalid');
            try{$scope[$kind]=NativeCompanyResourceId::decode($allowed[$kind],$id)['canonical_id'];}catch(DomainException$e){throw new DomainException('native_ticket_scope_invalid',0,$e);}
        }
        return$scope;
    }
    public function page(int$i,int$a,array$request,array$builder,NativeCompanyTicketTable$table,array$scope=[]):array
    {
        $scope=$this->normalizeScope($scope);
        $reader=new NativeCompanyTicketRead($this->db,$this->p);$fields=new NativeCompanyCustomFields($this->db,$this->p);$definitions=$fields->definitions($i,$a,'tickets',['active'=>1,'show_on_table'=>1]);$names=self::COLUMNS;$types=array_fill(0,count($names),'');foreach($definitions as$d){$names[]='cf_'.$d['id'];$types[]=$d['type'];}
        $customer=$scope['customer']??null;$customerRef=$customer===null?null:NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'client',$customer,'view');
        $project=$scope['project']??null;$projectRef=$project===null?null:NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$project,'view');
        $status=$scope['status']??null;$statusCatalog=$status===null?null:(new NativeCompanyTicketCatalog($this->db,$this->p))->list($i,$a,'status');if($status!==null&&!in_array($status,array_column($statusCatalog,'ticketstatusid'),true))throw new DomainException('native_ticket_status_invalid');
        $wire=NativeCompanyDataTableWire::request($request,$names,$types);if($table->contextIdentity()!==['issuer_id'=>$i,'actor_id'=>$a])throw new DomainException('native_ticket_denied');$builder=$table->normalizeBuilder($builder);
        $tables=array_values(array_unique(array_merge($reader->tables(),NativeCompanyResourceTags::tableNames(),['company_custom_fields','company_custom_field_values','customfields','customfieldsvalues'])));
        $result=IssuerTransaction::run($this->db,$this->p,$tables,function()use($i,$a,$reader,$fields,$definitions,$wire,$builder,$table,$names,$scope,$customerRef,$projectRef,$status,$statusCatalog){
            $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tickets','view')||$acl->primaryId()===$i||$table->contextIdentity()!==['issuer_id'=>$i,'actor_id'=>$a])throw new DomainException('native_ticket_denied');
            $via=$scope['via_ticket']??null;$viaTicket=$via===null?null:$reader->getInOwnedTransaction($i,$a,$via);
            NativeCompanyTicketCatalogSchema::assertValid($this->db,$this->p);$candidates=$this->rows('SELECT t.id FROM '.$this->p.'company_tickets t WHERE t.issuer_id=? AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'t.issuer_id','t.id').' ORDER BY t.id LIMIT 10001',[$i]);if(count($candidates)>10000)throw new DomainException('native_ticket_index_limit');$allowed=[];$references=[];
            foreach($candidates as$c){$id=NativeCompanyResourceId::encode('company_ticket',(int)$c['id']);try{$ref=$reader->referenceInOwnedTransaction($i,$a,$id);}catch(DomainException$e){if($e->getMessage()==='native_ticket_department_denied')continue;throw$e;}
                if($customerRef!==null||$projectRef!==null||$viaTicket!==null||$status!==null){$ticket=$reader->getInOwnedTransaction($i,$a,$id);
                    if($status!==null&&(string)$ticket['status']!==$status)continue;
                    if($customerRef!==null&&(int)($ticket['userid']??0)!==(int)$customerRef['source_id'])continue;
                    if($viaTicket!==null){if(($ticket['ticketid']??null)===($viaTicket['ticketid']??null))continue;$viaClient=(int)($viaTicket['userid']??0);$ticketClient=(int)($ticket['userid']??0);if($viaClient>0){if($ticketClient!==$viaClient)continue;}elseif($ticketClient>0||trim((string)($viaTicket['ticket_email']??''))===''||strcasecmp(trim((string)($ticket['ticket_email']??'')),trim((string)$viaTicket['ticket_email']))!==0)continue;}
                    if($projectRef!==null){$meta=$this->rows('SELECT project_source_kind,project_source_id FROM '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' WHERE issuer_id=? AND ticket_id=?',[$i,(int)$c['id']])[0]??null;if(!$meta||$meta['project_source_kind']!==$projectRef['source_kind']||(int)$meta['project_source_id']!==(int)$projectRef['source_id'])continue;}
                }
                $allowed[]=(int)$c['id'];$references[$id]=$ref;}
            $pdo=$this->db->pdo??$this->db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$contact=$sqlite?"TRIM(COALESCE(c.firstname,'') || ' ' || COALESCE(c.lastname,''))":"TRIM(CONCAT(COALESCE(c.firstname,''),' ',COALESCE(c.lastname,'')))";
            $columns=['t.id AS source_id',($this->offset('company_ticket')).'+t.id AS ticketid','t.subject','t.assignee_id AS assigned','t.created_at AS date','m.contact_id','t.client_id AS userid','m.sender_name AS ticket_opened_by_name','m.sender_email AS email','m.admin_read AS adminread','v.company',$contact.' AS contact_full_name','d.name AS department_name','svc.name AS service_name','st.color AS statuscolor','st.name AS status_name','pr.name AS priority_name','merged.primary_id AS merged_ticket_id'];
            $params=[];
            foreach(['department'=>'m.department_source_id','status'=>'sr.source_id','priority'=>'prr.source_id','service'=>'svr.source_id']as$kind=>$col)$columns[]=($this->offset('company_ticket_'.$kind)).'+'.$col.' AS '.$kind;
            $columns[]="(SELECT GROUP_CONCAT(tags.label) FROM {$this->p}company_work_tag_links links JOIN {$this->p}company_work_tags tags ON tags.issuer_id=links.issuer_id AND tags.id=links.tag_id WHERE links.issuer_id=t.issuer_id AND links.source_kind='company_ticket' AND links.source_id=t.id) AS tags";
            $columns[]='(SELECT MAX(r.created_at) FROM '.$this->p.'company_ticket_replies r WHERE r.issuer_id=t.issuer_id AND r.ticket_id=t.id AND '.NativeCompanyTicketVisibility::predicate($this->db,$this->p,'r.issuer_id','r.id',true).') AS lastreply';
            foreach($definitions as$definition){$name='cf_'.$definition['id'];if(!preg_match('/^cf_[1-9][0-9]*$/D',$name)||$definition['field_source_kind']!=='company_custom_field')throw new DomainException('native_ticket_field_invalid');$columns[]="COALESCE((SELECT value FROM {$this->p}company_custom_field_values cf WHERE cf.issuer_id=t.issuer_id AND cf.field_source_kind=? AND cf.field_id=? AND cf.source_kind='company_ticket' AND cf.source_id=t.id),'') AS $name";$params[]=$definition['field_source_kind'];$params[]=$definition['source_field_id'];}
            $refs=$this->p.NativeCompanyTicketCatalogSchema::REFERENCES;$catalog=$this->p.NativeCompanyTicketCatalogSchema::CATALOG;
            $base='SELECT '.implode(',',$columns).' FROM '.$this->p.'company_tickets t LEFT JOIN '.$this->p.NativeCompanyDepartmentSchema::TICKETS.' m ON m.issuer_id=t.issuer_id AND m.ticket_id=t.id LEFT JOIN '.$this->p.'company_departments d ON d.issuer_id=t.issuer_id AND d.id=m.department_source_id AND m.department_source_kind=\'company_ticket_department\' LEFT JOIN '.$this->p.'contacts c ON c.id=m.contact_id AND c.userid=t.client_id LEFT JOIN '.$this->p.'clients v ON v.userid=t.client_id LEFT JOIN '.$this->p.NativeCompanyTicketMergeSchema::LINKS.' merged ON merged.issuer_id=t.issuer_id AND merged.child_id=t.id';
            foreach(['status'=>['sr','st'],'priority'=>['prr','pr'],'service'=>['svr','svc']]as$kind=>[$r,$cat])$base.=" LEFT JOIN $refs $r ON $r.issuer_id=t.issuer_id AND $r.ticket_id=t.id AND $r.kind='$kind' LEFT JOIN $catalog $cat ON $cat.issuer_id=t.issuer_id AND $cat.id=$r.source_id AND $cat.kind='$kind' AND $r.source_kind='company_ticket_$kind'";
            $base.=' WHERE t.issuer_id=? AND t.id IN ('.($allowed?implode(',',array_fill(0,count($allowed),'?')):'0').')';$params=array_merge($params,[$i],$allowed);
            $total=(int)$this->rows('SELECT COUNT(*) n FROM ('.$base.') q',$params)[0]['n'];if($total!==count($allowed))throw new DomainException('native_ticket_reference_invalid');
            $compiled=NativeCompanyTicketRules::compile($builder,$a);$conditions=$compiled['sql']?[$compiled['sql']]:[];$queryParams=array_merge($params,$compiled['params']);
            if($wire['search']!==''){$term='%'.str_replace(['!','%','_'],['!!','!%','!_'],$wire['search']).'%';$search=[];foreach(array_slice($names,1)as$name){$search[]="q.$name LIKE ? ESCAPE '!'";$queryParams[]=$term;}$conditions[]='('.implode(' OR ',$search).')';}
            $where=$conditions?' WHERE '.implode(' AND ',$conditions):'';$filtered=(int)$this->rows('SELECT COUNT(*) n FROM ('.$base.') q'.$where,$queryParams)[0]['n'];$order=[];foreach($wire['orders']as$o)$order[]='q.'.($o['field']==='bulk'?'ticketid':$o['field']).' '.strtoupper($o['direction']);$order[]='q.ticketid ASC';$limit=$wire['length']===-1?'':' LIMIT '.$wire['length'].' OFFSET '.$wire['start'];
            $rows=$this->rows('SELECT q.* FROM ('.$base.') q'.$where.' ORDER BY '.implode(',',$order).$limit,$queryParams);$out=[];$tags=new NativeCompanyResourceTags($this->db,$this->p);
            foreach($rows as$row){$id=NativeCompanyResourceId::encode('company_ticket',(int)$row['source_id']);$dto=$reader->getInOwnedTransaction($i,$a,$id);$tag=$tags->read($i,$a,'ticket',$id);$values=$fields->values($i,$a,'tickets',$id);$out[]=['source'=>$dto,'row'=>$row,'tags'=>$tag,'custom_values'=>$values];}
            foreach($references as$id=>$ref)if($reader->referenceInOwnedTransaction($i,$a,(string)$id)!==$ref)throw new DomainException('native_ticket_changed');
            if($viaTicket!==null&&$reader->getInOwnedTransaction($i,$a,$via)!==$viaTicket)throw new DomainException('native_ticket_changed');if($statusCatalog!==null&&(new NativeCompanyTicketCatalog($this->db,$this->p))->list($i,$a,'status')!==$statusCatalog)throw new DomainException('native_ticket_changed');
            if($fields->definitions($i,$a,'tickets',['active'=>1,'show_on_table'=>1])!==$definitions||$table->contextIdentity()!==['issuer_id'=>$i,'actor_id'=>$a])throw new DomainException('native_ticket_changed');return['draw'=>$wire['draw'],'iTotalRecords'=>$total,'iTotalDisplayRecords'=>$filtered,'rows'=>$out,'custom_fields'=>$definitions];
        });
        if($customerRef!==null&&NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'client',$customer,'view')!==$customerRef)throw new DomainException('native_ticket_changed');
        if($projectRef!==null&&NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$project,'view')!==$projectRef)throw new DomainException('native_ticket_changed');
        return$result;
    }
}
