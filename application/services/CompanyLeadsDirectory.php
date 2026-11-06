<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyLeadRules.php';
require_once __DIR__.'/NativeCompanyDataTableWire.php';
require_once __DIR__.'/NativeCompanyResourceId.php';

/** Original list/board read model, inside the existing CompanyLeads transaction and ACL. */
trait CompanyLeadsDirectory
{
    private function directoryStaff(int $issuer):array
    {
        $read=function()use($issuer):array{$rows=$this->rows('SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE active=1 ORDER BY staffid LIMIT 1001');if(count($rows)>1000)throw new DomainException('company_lead_staff_limit');return array_values(array_filter($rows,fn($r)=>$this->acl->can($issuer,(int)$r['staffid'],'leads','view')));};
        $staff=$read();$this->validators[]=static function()use($read,$staff):void{if($read()!==$staff)throw new DomainException('company_lead_directory_stale');};return$staff;
    }
    private function directorySource(int $issuer,array $staff):array
    {
        $ids=array_map(static fn($r)=>(int)$r['staffid'],$staff);$allowed=$ids?'u.staffid IN ('.implode(',',array_fill(0,count($ids),'?')).')':'1=0';$p=$this->p;
        $metadata=CompanyLeadMetadataSchema::available($this->db,$p);$columns=array_merge(array_keys(CompanyLeadMetadata::TEXT),['country','lead_value','currency_json','lastcontact']);$select=implode(',',array_map(static fn($field)=>($metadata?'m.'.$field:'NULL').' '.$field,$columns));
        $select.=','.($metadata?'m.lead_id':'NULL').' native_metadata_id,'.($metadata?'m.issuer_id':'NULL').' native_metadata_issuer,'.($metadata?'m.revision':'NULL').' native_metadata_revision,'.($metadata?'m.updated_at':'NULL').' native_metadata_updated_at';
        $join=$metadata?' LEFT JOIN '.$p.CompanyLeadMetadataSchema::TABLE.' m ON m.issuer_id=l.issuer_id AND m.lead_id=l.id':'';
        $sql="SELECT l.*,0 selection,$select,NULL tags,TRIM(CONCAT(COALESCE(u.firstname,''),' ',COALESCE(u.lastname,''))) assigned_name,g.name status_name,g.category status_category,g.active status_active,s.name source_name,(SELECT COUNT(*) FROM {$p}company_lead_notes n WHERE n.issuer_id=l.issuer_id AND n.lead_id=l.id) total_notes,(SELECT COUNT(*) FROM {$p}company_lead_files f WHERE f.issuer_id=l.issuer_id AND f.lead_id=l.id AND f.active=1) total_files FROM {$p}company_leads l JOIN {$p}company_lead_stages g ON g.id=l.stage_id AND g.issuer_id=l.issuer_id JOIN {$p}company_lead_sources s ON s.id=l.source_id AND s.issuer_id=l.issuer_id LEFT JOIN {$p}staff u ON u.staffid=l.assignee_id AND $allowed$join WHERE l.issuer_id=?";
        return['sql'=>$sql,'params'=>[...$ids,$issuer]];
    }
    private function directoryMetadataTables():array
    {
        return CompanyLeadMetadataSchema::available($this->db,$this->p)?['company_work_tags','company_work_tag_links']:[];
    }
    /** One bounded, owner-joined tag read for the page. No global suggestions or per-card lookups. */
    private function directoryTags(int $issuer,array $ids):array
    {
        if(!$ids||!CompanyLeadMetadataSchema::available($this->db,$this->p))return[];
        IssuerTransaction::assertOwned($this->db,$this->p,$this->directoryMetadataTables());NativeCompanyWorkTagsSchema::assertValid($this->db,$this->p);$p=$this->p;
        $budget=min(count($ids)*100,20000);$sql="SELECT x.source_id,x.position,t.id,t.label,t.label_hash,t.revision,t.feature FROM {$p}company_work_tag_links x JOIN {$p}company_work_tags t ON t.issuer_id=x.issuer_id AND t.id=x.tag_id JOIN {$p}company_leads l ON l.issuer_id=x.issuer_id AND l.id=x.source_id WHERE x.issuer_id=? AND x.source_kind='company_lead' AND x.source_id IN (".implode(',',array_fill(0,count($ids),'?')).') ORDER BY x.source_id,x.position,t.id LIMIT '.($budget+1);$args=[$issuer,...$ids];$rows=$this->rows($sql,$args);if(count($rows)>$budget)throw new DomainException('company_lead_export_limit');$result=[];
        foreach($rows as$row){$id=(int)$row['source_id'];$name=$row['label'];if($row['feature']!=='leads'||(int)$row['revision']<1||(int)$row['id']<1||$name!==trim($name)||!hash_equals(hash('sha256',mb_strtolower($name,'UTF-8')),$row['label_hash']))throw new DomainException('native_tags_invalid');$result[$id][]=['id'=>NativeCompanyResourceId::encode('company_work_tag',$row['id']),'name'=>$name,'order'=>(int)$row['position']];}
        foreach($result as$tags)CompanyLeadMetadata::tags(array_column($tags,'name'));
        $this->validators[]=function()use($sql,$args,$rows):void{if($this->rows($sql,$args)!==$rows)throw new DomainException('company_lead_directory_stale');};return$result;
    }
    private function directoryRows(int $issuer,array $rows):array
    {
        // directoryPage rechecks the complete bounded SELECT after the owned transaction's
        // last authority/API check. Keep that batch proof instead of N additional reads per row.
        $tags=$this->directoryTags($issuer,array_map('intval',array_column($rows,'id')));$out=[];
        foreach($rows as$row){$row=$this->projection($row);$values=null;
            if($row['native_metadata_id']!==null){$metadata=array_intersect_key($row,array_fill_keys(array_merge(array_keys(CompanyLeadMetadata::TEXT),['country','lead_value','currency_json','lastcontact']),true));$metadata+=['lead_id'=>$row['native_metadata_id'],'issuer_id'=>$row['native_metadata_issuer'],'revision'=>$row['native_metadata_revision'],'updated_at'=>$row['native_metadata_updated_at']];$values=CompanyLeadMetadata::fromRow($metadata);}
            unset($row['native_metadata_id'],$row['native_metadata_issuer'],$row['native_metadata_revision'],$row['native_metadata_updated_at'],$row['currency_json']);$row['currency']=$values['currency']??null;$row['tags']=$tags[$row['id']]??[];
            $row['canonical_id']=NativeCompanyResourceId::encode('company_lead',$row['id']);$row['total_notes']=(int)$row['total_notes'];$row['total_files']=(int)$row['total_files'];$out[]=$row;
        }return$out;
    }
    private function directoryPage(int $issuer,array $staff,array $input,string $format):array
    {
        $state=$input['state']??'active';if(!in_array($state,['active','archived','all'],true))throw new DomainException('company_lead_invalid_input');
        if(isset($input['last_order_identifier'])&&$input['last_order_identifier']!=='leads')throw new DomainException('native_lead_filter_invalid');if(($input['custom_view']??'')!=='')throw new DomainException('native_lead_filter_invalid');
        $builder=$input['filters']??['match_type'=>'and','rules'=>[]];if(is_array($builder)&&array_keys($builder)===['match_type'])$builder['rules']=[];if(!is_array($builder))throw new DomainException('native_lead_filter_invalid');
        unset($input['filters'],$input['state'],$input['last_order_identifier'],$input['custom_view']);$wire=NativeCompanyDataTableWire::request($input,self::NATIVE_COLUMNS);$rules=NativeCompanyLeadRules::compile($builder,gmdate('Y-m-d'),$format);$source=$this->directorySource($issuer,$staff);$base=' FROM ('.$source['sql'].') q';$where=[];$args=$source['params'];
        if($state!=='all'){$where[]='q.archived=?';$args[]=$state==='archived'?1:0;}$totalSql='SELECT COUNT(*) n'.$base.($where?' WHERE '.implode(' AND ',$where):'');$totalArgs=$args;
        if($rules['sql']!==''){$where[]=$rules['sql'];array_push($args,...$rules['params']);}
        if($wire['search']!==''){$pattern='%'.str_replace(['!','%','_'],['!!','!%','!_'],$wire['search']).'%';$parts=[];foreach(['name','company','email','phone','vat','assigned_name','status_name','source_name','title','website','address','city','state','zip','lead_value','lastcontact']as$field){$parts[]='q.'.$field." LIKE ? ESCAPE '!'";$args[]=$pattern;}
            if(CompanyLeadMetadataSchema::available($this->db,$this->p)){$p=$this->p;$parts[]="EXISTS (SELECT 1 FROM {$p}company_work_tag_links x JOIN {$p}company_work_tags t ON t.issuer_id=x.issuer_id AND t.id=x.tag_id WHERE x.issuer_id=q.issuer_id AND x.source_kind='company_lead' AND x.source_id=q.id AND t.feature='leads' AND t.label LIKE ? ESCAPE '!')";$args[]=$pattern;}$where[]='('.implode(' OR ',$parts).')';}
        $filteredSql='SELECT COUNT(*) n'.$base.($where?' WHERE '.implode(' AND ',$where):'');$total=(int)$this->rows($totalSql,$totalArgs)[0]['n'];$filtered=(int)$this->rows($filteredSql,$args)[0]['n'];$limit=$wire['length']===-1?$filtered:$wire['length'];if($limit>10000)throw new DomainException('company_lead_export_limit');
        $order=implode(',',array_map(static fn($r)=>'q.'.$r['field'].' '.strtoupper($r['direction']),$wire['orders']));$sql='SELECT q.*'.$base.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY '.$order.',q.id ASC LIMIT '.$limit.' OFFSET '.$wire['start'];$rows=$this->rows($sql,$args);
        $this->validators[]=function()use($sql,$args,$rows,$totalSql,$totalArgs,$total,$filteredSql,$filtered):void{if($this->rows($sql,$args)!==$rows||(int)$this->rows($totalSql,$totalArgs)[0]['n']!==$total||(int)$this->rows($filteredSql,$args)[0]['n']!==$filtered)throw new DomainException('company_lead_directory_stale');};
        return['draw'=>$wire['draw'],'recordsTotal'=>$total,'recordsFiltered'=>$filtered,'rows'=>$this->directoryRows($issuer,$rows)];
    }
    public function nativeDirectory($issuer,$actor,array $request,string $dateFormat='Y-m-d|%Y-%m-%d'):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);return$this->transaction($issuer,$actor,'view','native_directory',null,fn()=>$this->directoryPage($issuer,$this->directoryStaff($issuer),$request,$dateFormat),$this->directoryMetadataTables());
    }
    public function nativeSummary($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);return$this->transaction($issuer,$actor,'view','native_summary',null,function()use($issuer):array{
            $sql='SELECT g.*,COUNT(l.id) total FROM '.$this->p.'company_lead_stages g LEFT JOIN '.$this->p.'company_leads l ON l.stage_id=g.id AND l.issuer_id=g.issuer_id AND l.archived=0 WHERE g.issuer_id=? GROUP BY g.id,g.issuer_id,g.name,g.category,g.position,g.active,g.revision,g.created_at,g.updated_at ORDER BY g.position,g.id';$rows=$this->rows($sql,[$issuer]);if(count($rows)>50)throw new RuntimeException('issuer_lead_configuration_invalid');
            $this->validators[]=function()use($sql,$issuer,$rows):void{if($this->rows($sql,[$issuer])!==$rows)throw new DomainException('company_lead_directory_stale');};return array_map(static function($r){$r['id']=(int)$r['id'];$r['total']=(int)$r['total'];return$r;},$rows);
        });
    }
    public function nativeHistory($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        return$this->transaction($issuer,$actor,'view','native_history',null,function()use($issuer,$id):array{
            $lead=$this->lead($issuer,$id);$this->remember('company_leads',['id'=>$id],$lead);
            $events=['created','updated','moved','archived','restored','noted','converted','file_added','file_hidden'];$events=array_map(static fn($event)=>'company_lead_'.$event,$events);
            $sql='SELECT id,event,actor_id,created_at FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND subject_id=? AND event IN ('.implode(',',array_fill(0,count($events),'?')).') ORDER BY id DESC LIMIT 100';$args=[$issuer,$id,...$events];$rows=$this->rows($sql,$args);$this->validators[]=function()use($sql,$args,$rows):void{if($this->rows($sql,$args)!==$rows)throw new DomainException('company_lead_directory_stale');};return$rows;
        });
    }
    public function nativeBoard($issuer,$actor,string $search='',?int $stage=null,int $offset=0,string $direction='desc'):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$search=self::text($search,191);self::integer($offset);if($stage!==null)$stage=IssuerPolicy::id($stage);if(!in_array($direction,['asc','desc'],true)||($stage===null&&$offset!==0))throw new DomainException('company_lead_invalid_input');
        return$this->transaction($issuer,$actor,'view','native_board',null,function()use($issuer,$search,$stage,$offset,$direction):array{
            $stages=$this->rows('SELECT * FROM '.$this->p.'company_lead_stages WHERE issuer_id=? ORDER BY position,id LIMIT 51',[$issuer]);if(count($stages)>50)throw new RuntimeException('issuer_lead_configuration_invalid');if($stage!==null&&!in_array($stage,array_map(static fn($r)=>(int)$r['id'],$stages),true))throw new DomainException('company_lead_reference_denied');$staff=$this->directoryStaff($issuer);$columns=[];
            foreach($stages as$r){$this->remember('company_lead_stages',['id'=>$r['id']],$r);if($stage!==null&&(int)$r['id']!==$stage)continue;
                $request=['draw'=>0,'start'=>$offset,'length'=>25,'search'=>['value'=>$search,'regex'=>false],'columns'=>array_map(static fn($i)=>['data'=>(string)$i,'name'=>'','searchable'=>true,'orderable'=>true,'search'=>['value'=>'','regex'=>false]],array_keys(self::NATIVE_COLUMNS)),'order'=>[['column'=>12,'dir'=>$direction]],'filters'=>['match_type'=>'and','rules'=>[['id'=>'status','type'=>'MultiSelectRule','operator'=>'in','value'=>[(string)$r['id']]]]]];
                $page=$this->directoryPage($issuer,$staff,$request,'Y-m-d|%Y-%m-%d');if((int)$r['active']===0&&$page['recordsFiltered']===0)continue;$next=$offset+count($page['rows']);$columns[]=['stage'=>$r,'rows'=>$page['rows'],'total'=>$page['recordsFiltered'],'offset'=>$offset,'next'=>$next<$page['recordsFiltered']?$next:null];
            }return$columns;
        },$this->directoryMetadataTables());
    }
}
