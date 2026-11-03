<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyProjectRead.php';
require_once __DIR__.'/NativeCompanyProjectMetadataSchema.php';
require_once __DIR__.'/NativeCompanyProjectRules.php';
require_once __DIR__.'/NativeCompanyResourceTags.php';
require_once __DIR__.'/NativeCompanyWorkAssignments.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCatalog.php';
/** Paged projection for the original projects DataTable, with explicit source identity. */
final class NativeCompanyProjectPage
{
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function request(array$input,array$definitions):array
    {
        if(array_diff(array_keys($input),['draw','start','length','search','order','columns','filters','last_order_identifier','custom_view']))throw new DomainException('native_project_table_request_invalid');
        $int=static function($v,int$max):int{if((!is_int($v)&&!is_string($v))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$v)||(int)$v>$max)throw new DomainException('native_project_table_request_invalid');return(int)$v;};
        $draw=$int($input['draw']??0,2147483647);$start=$int($input['start']??0,2147483647);$length=$int($input['length']??25,500);if(!$length)throw new DomainException('native_project_table_request_invalid');
        $search=$input['search']??['value'=>'','regex'=>false];if(!is_array($search)||array_diff(array_keys($search),['value','regex'])||!is_string($search['value']??null)||mb_strlen($search['value'])>500||!in_array($search['regex']??false,[false,0,'0','false'],true)||preg_match('//u',$search['value'])!==1)throw new DomainException('native_project_table_request_invalid');
        if(($input['custom_view']??'')!==''||isset($input['last_order_identifier'])&&$input['last_order_identifier']!=='projects')throw new DomainException('native_project_table_request_invalid');
        $map=['id','name','company','tags','start_date','deadline','members','status'];foreach($definitions as$d)$map[]='cf_'.IssuerPolicy::id($d['id']);
        $columns=$input['columns']??null;if(!is_array($columns)||!array_is_list($columns)||count($columns)!==count($map))throw new DomainException('native_project_table_request_invalid');
        foreach($columns as$i=>$c){if(!is_array($c)||array_diff(array_keys($c),['data','name','searchable','orderable','search'])||(string)($c['data']??'')!==(string)$i||($c['name']??'')!==''||($c['search']['value']??'')!==''||!in_array($c['search']['regex']??false,[false,0,'0','false'],true))throw new DomainException('native_project_table_request_invalid');foreach(['searchable','orderable']as$f)if(!in_array($c[$f]??false,[true,false,0,1,'0','1','true','false'],true))throw new DomainException('native_project_table_request_invalid');}
        $orders=$input['order']??[['column'=>5,'dir'=>'asc']];if(!is_array($orders)||!array_is_list($orders)||!$orders||count($orders)>count($map))throw new DomainException('native_project_table_request_invalid');$order=[];
        foreach($orders as$o){if(!is_array($o)||array_diff(array_keys($o),['column','dir','type'])||!in_array($o['dir']??null,['asc','desc'],true))throw new DomainException('native_project_table_request_invalid');$i=$int($o['column']??null,count($map)-1);$order[]=['field'=>$map[$i],'direction'=>$o['dir']];}
        return compact('draw','start','length','order')+['search'=>$search['value'],'filters'=>$input['filters']??['match_type'=>'and','rules'=>[]]];
    }
    private function sourceSql(int$issuer,bool$primary,array$definitions,array$visibleMembers):array
    {
        $p=$this->p;$args=[$issuer];$allowedMembers=$visibleMembers?implode(',',$visibleMembers):'0';$pdo=$this->db->pdo??$this->db->conn_id??null;$sqlite=$pdo instanceof PDO&&$pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';$name=$sqlite?"s.firstname || ' ' || s.lastname":"CONCAT(s.firstname,' ',s.lastname)";$code=static fn($s)=>NativeCompanyWorkStatusCatalog::id('project',$s);
        $status="CASE WHEN m.workflow_status IS NULL THEN CASE p.status WHEN 'active' THEN ".$code('active')." WHEN 'completed' THEN ".$code('completed')." WHEN 'cancelled' THEN ".$code('cancelled')." END ELSE CASE m.workflow_status WHEN 0 THEN ".$code('active')." WHEN 1 THEN ".$code('not_started')." WHEN 2 THEN ".$code('in_progress')." WHEN 3 THEN ".$code('on_hold')." WHEN 4 THEN ".$code('completed')." WHEN 5 THEN ".$code('cancelled')." END END";
        $select="SELECT p.id+4294967296 id,p.id source_id,p.issuer_id,'company_project' source_kind,p.name,p.client_id clientid,c.company,p.start_date,p.due_date deadline,$status status,m.billing_type,p.revision FROM {$p}company_projects p JOIN {$p}clients c ON c.userid=p.client_id LEFT JOIN {$p}company_project_native_metadata m ON m.issuer_id=p.issuer_id AND m.project_id=p.id WHERE p.issuer_id=?";
        if($primary){$select.=" UNION ALL SELECT p.id,p.id,$issuer,'project',p.name,p.clientid,c.company,p.start_date,p.deadline,p.status,p.billing_type,NULL FROM {$p}projects p JOIN {$p}clients c ON c.userid=p.clientid WHERE ".\app\services\CustomerRelationBoundary::sql($this->db,$p,'projects','p');}
        // These are sort/search projections only. Visible tags/members are hydrated by their actual authorized readers.
        $ownedTags="SELECT GROUP_CONCAT(t.label) FROM {$p}company_work_tag_links l JOIN {$p}company_work_tags t ON t.issuer_id=l.issuer_id AND t.id=l.tag_id WHERE l.issuer_id=q.issuer_id AND l.source_kind=q.source_kind AND l.source_id=q.source_id";
        $legacyTags="SELECT GROUP_CONCAT(t.name) FROM {$p}taggables l JOIN {$p}tags t ON t.id=l.tag_id WHERE l.rel_type='project' AND l.rel_id=q.source_id";
        $members="CASE WHEN q.source_kind='company_project' THEN (SELECT GROUP_CONCAT($name) FROM {$p}company_project_members pm JOIN {$p}staff s ON s.staffid=pm.staff_id WHERE pm.issuer_id=q.issuer_id AND pm.project_id=q.source_id AND pm.active=1 AND s.staffid IN ($allowedMembers)) ELSE (SELECT GROUP_CONCAT($name) FROM {$p}project_members pm JOIN {$p}staff s ON s.staffid=pm.staff_id WHERE pm.project_id=q.source_id) END members";
        $custom='';foreach($definitions as$d){$id=IssuerPolicy::id($d['id']);$source=$d['field_source_kind'];if(!in_array($source,['custom_field','company_custom_field'],true))throw new DomainException('native_project_columns_invalid');$raw=IssuerPolicy::id($d['source_field_id']);
            $expr="(SELECT v.value FROM {$p}company_custom_field_values v WHERE v.issuer_id=q.issuer_id AND v.field_source_kind='$source' AND v.field_id=$raw AND v.source_kind=q.source_kind AND v.source_id=q.source_id)";
            if($source==='custom_field')$expr="CASE WHEN q.source_kind='project' THEN COALESCE($expr,(SELECT v.value FROM {$p}customfieldsvalues v WHERE v.fieldto='projects' AND v.fieldid=$raw AND v.relid=q.source_id)) ELSE $expr END";
            $custom.=",$expr cf_$id";
        }
        return['sql'=>"SELECT q.*,CASE WHEN q.source_kind='company_project' THEN ($ownedTags) ELSE ($legacyTags) END tags,$members$custom FROM ($select) q",'params'=>$args];
    }
    public function read(int$issuer,int$actor,array$input,$client='',string$today=''):array
    {
        $acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($issuer,$actor,'projects','view'))throw new DomainException('native_project_access_denied');$primary=$acl->primaryId()===$issuer;
        NativeCompanyProjectMetadataSchema::assertValid($this->db,$this->p);NativeCompanyWorkAssignmentsSchema::assertValid($this->db,$this->p);NativeCompanyWorkTagsSchema::assertValid($this->db,$this->p);
        $custom=new NativeCompanyCustomFields($this->db,$this->p);$definitions=$custom->definitions($issuer,$actor,'projects',['active'=>1,'show_on_table'=>1]);$request=self::request($input,$definitions);
        $summary=(new NativeCompanyProjectRead($this->db,$this->p))->summary($issuer,$actor,static fn($k)=>$k);$membersSql='SELECT DISTINCT staff_id FROM '.$this->p.'company_project_members WHERE issuer_id=? AND active=1 ORDER BY staff_id';$memberFacts=$this->rows($membersSql,[$issuer]);$visibleMembers=[];foreach($memberFacts as$m){$staff=IssuerPolicy::id($m['staff_id']);if($acl->can($issuer,$staff,'projects','view'))$visibleMembers[]=$staff;}$source=$this->sourceSql($issuer,$primary,$definitions,$visibleMembers);
        $where=[];$args=$source['params'];if($client!==''&&$client!==null){$client=IssuerPolicy::id($client);NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'client',$client,'view');$where[]='q.clientid=?';$args[]=$client;}
        $base=' FROM ('.$source['sql'].') q';$scope=$where?' WHERE '.implode(' AND ',$where):'';$totalSql='SELECT COUNT(*) n'.$base.$scope;$totalArgs=$args;
        $formatStore=new CompanySettingsStorage($this->db,$this->p);$format=$formatStore->exists($issuer,'dateformat')?$formatStore->get($issuer,'dateformat'):CompanySettingsCatalog::defaultValue('localization','dateformat');
        $filters=NativeCompanyProjectRules::compile($request['filters'],$this->p,$today?:gmdate('Y-m-d'),$format,$visibleMembers);if($filters['sql']!==''){$where[]=$filters['sql'];$args=array_merge($args,$filters['params']);}
        if($request['search']!==''){$pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$request['search']).'%';$parts=[];foreach(array_merge(['id','name','company','tags','start_date','deadline','members','status'],array_map(static fn($d)=>'cf_'.$d['id'],$definitions))as$field){$parts[]='q.'.$field." LIKE ? ESCAPE '!'";$args[]=$pattern;}$where[]='('.implode(' OR ',$parts).')';}
        $filter=$where?' WHERE '.implode(' AND ',$where):'';$countSql='SELECT COUNT(*) n'.$base.$filter;$order=implode(',',array_map(static fn($o)=>'q.'.$o['field'].' '.strtoupper($o['direction']),$request['order']));
        $sql='SELECT q.*'.$base.$filter.' ORDER BY '.$order.',q.id ASC LIMIT '.$request['length'].' OFFSET '.$request['start'];
        $total=(int)$this->rows($totalSql,$totalArgs)[0]['n'];$filtered=(int)$this->rows($countSql,$args)[0]['n'];$rows=$this->rows($sql,$args);$result=[];
        foreach($rows as$r){$id=(string)$r['id'];$r['id']=$id;NativeCompanyResourceReferences::resolve($this->db,$this->p,$issuer,$actor,'project',$id,'view');$r['tags']=(new NativeCompanyResourceTags($this->db,$this->p))->read($issuer,$actor,'project',$id)['tags'];
            if($r['source_kind']==='company_project')$members=(new NativeCompanyWorkAssignments($this->db,$this->p))->read($issuer,$actor,'project',(int)$r['source_id'])['members'];
            else $members=$this->rows('SELECT s.staffid,s.firstname,s.lastname FROM '.$this->p.'project_members m JOIN '.$this->p.'staff s ON s.staffid=m.staff_id WHERE m.project_id=? ORDER BY s.staffid',[$r['source_id']]);
            $r['member_rows']=$members;$r['members']=implode(',',array_map(static fn($m)=>trim($m['firstname'].' '.$m['lastname']),$members));$r['members_ids']=implode(',',array_column($members,'staffid'));$r['custom_values']=$custom->values($issuer,$actor,'projects',$id);$result[]=$r;
        }
        $currentVisible=[];foreach($this->rows($membersSql,[$issuer])as$m){$staff=IssuerPolicy::id($m['staff_id']);if($acl->can($issuer,$staff,'projects','view'))$currentVisible[]=$staff;}
        if($currentVisible!==$visibleMembers||!$acl->can($issuer,$actor,'projects','view')||($acl->primaryId()===$issuer)!==$primary||$this->rows($sql,$args)!==$rows||(int)$this->rows($countSql,$args)[0]['n']!==$filtered||(int)$this->rows($totalSql,$totalArgs)[0]['n']!==$total||$custom->definitions($issuer,$actor,'projects',['active'=>1,'show_on_table'=>1])!==$definitions)throw new DomainException('native_project_snapshot_changed');
        return['draw'=>$request['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'rows'=>$result,'definitions'=>$definitions,'statuses'=>$summary['statuses']];
    }
}
