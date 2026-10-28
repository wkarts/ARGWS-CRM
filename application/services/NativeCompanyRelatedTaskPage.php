<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyRelatedTaskRead.php';
require_once __DIR__.'/NativeCompanyRelatedTaskTableFactory.php';
require_once __DIR__.'/NativeCompanyCustomFields.php';
/** Bounded, authorized facts for the original related_tasks DataTable. */
final class NativeCompanyRelatedTaskPage
{
    public function __construct(private$db,private string$p){}
    private function rows(string$sql,array$args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    public static function request(array$input,array$definitions):array
    {
        if(array_diff(array_keys($input),['draw','start','length','search','order','columns','filters','tasks_related_to','custom_view','last_order_identifier']))throw new DomainException('native_task_table_request_invalid');
        $int=static function($v,int$max):int{if((!is_int($v)&&!is_string($v))||!preg_match('/^(0|[1-9][0-9]*)$/D',(string)$v)||(int)$v>$max)throw new DomainException('native_task_table_request_invalid');return(int)$v;};
        $draw=$int($input['draw']??0,2147483647);$start=$int($input['start']??0,2147483647);$length=$int($input['length']??25,500);if(!$length||($input['custom_view']??'')!==''||!in_array($input['tasks_related_to']??[],[[],null,''],true)||isset($input['last_order_identifier'])&&$input['last_order_identifier']!=='tasks')throw new DomainException('native_task_table_request_invalid');
        $search=$input['search']??['value'=>'','regex'=>false];if(!is_array($search)||array_diff(array_keys($search),['value','regex'])||!is_string($search['value']??null)||mb_strlen($search['value'])>500||!in_array($search['regex']??false,[false,0,'0','false'],true)||preg_match('//u',$search['value'])!==1)throw new DomainException('native_task_table_request_invalid');
        $map=['checkbox','id','name','status','startdate','duedate','assigned','tags','priority'];foreach($definitions as$d)$map[]='cf_'.IssuerPolicy::id($d['id']);
        $columns=$input['columns']??null;if(!is_array($columns)||!array_is_list($columns)||count($columns)!==count($map))throw new DomainException('native_task_table_request_invalid');
        foreach($columns as$n=>$c){if(!is_array($c)||array_diff(array_keys($c),['data','name','searchable','orderable','search'])||(string)($c['data']??'')!==(string)$n||($c['name']??'')!==''||($c['search']['value']??'')!==''||!in_array($c['search']['regex']??false,[false,0,'0','false'],true))throw new DomainException('native_task_table_request_invalid');foreach(['searchable','orderable']as$f)if(!in_array($c[$f]??false,[true,false,0,1,'0','1','true','false'],true))throw new DomainException('native_task_table_request_invalid');}
        $orders=$input['order']??[['column'=>5,'dir'=>'asc']];if(!is_array($orders)||!array_is_list($orders)||!$orders||count($orders)>count($map))throw new DomainException('native_task_table_request_invalid');$order=[];
        foreach($orders as$o){if(!is_array($o)||array_diff(array_keys($o),['column','dir','type'])||!in_array($o['dir']??null,['asc','desc'],true))throw new DomainException('native_task_table_request_invalid');$n=$int($o['column']??null,count($map)-1);if($n===0)throw new DomainException('native_task_table_request_invalid');$order[]=['field'=>$map[$n],'direction'=>$o['dir']];}
        return compact('draw','start','length','order')+['search'=>$search['value'],'filters'=>$input['filters']??['match_type'=>'and','rules'=>[]]];
    }
    public function read(int$i,int$a,string$type,$parent,array$input,string$today):array
    {
        $reader=new NativeCompanyRelatedTaskRead($this->db,$this->p);
        $read=fn()=>$reader->read($i,$a,$type,$parent);
        return $this->compose($i,$a,$read(),$read,$input,$today);
    }
    /** Same table grammar over all authorized secondary-company tasks. */
    public function readIndex(int $i,int $a,array $input,string $today,bool $deferConfirmation=false):array
    {
        require_once __DIR__.'/NativeCompanyTaskIndexProjection.php';
        $reader=new NativeCompanyTaskIndexProjection($this->db,$this->p);
        $read=fn()=>['items'=>$reader->captureForComparison($i,$a)];
        $source=$read();
        $page=$this->compose($i,$a,$source,$read,$input,$today,!$deferConfirmation);
        if($deferConfirmation)$page['_native_source']=$source;
        return$page;
    }
    private function compose(int $i,int $a,array $source,callable $read,array $input,string $today,bool $confirm=true):array
    {
        NativeCompanyTaskFormValues::date($today);
        $custom=new NativeCompanyCustomFields($this->db,$this->p);$definitions=$custom->definitions($i,$a,'tasks',['active'=>1,'show_on_table'=>1]);$request=self::request($input,$definitions);
        $table=(new NativeCompanyRelatedTaskTableFactory($this->db,$this->p))->make($i,$a);$builder=$table->normalizeBuilder($request['filters']);$all=[];$snapshots=[];
        foreach($source['items']as$item){
            $id=$item['source_id'];$row=$item['row'];$fields=$item['fields'];$assigned=array_map('intval',array_column($item['assignees'],'staffid'));
            $isProject=$item['source_kind']==='company_task';$timerTable=$isProject?'company_task_timers':'company_standalone_timers';$timerSql='SELECT id,actor_id,started_at,stopped_at,seconds FROM '.$this->p.$timerTable.' WHERE issuer_id=? AND task_id=? ORDER BY id';$timers=$this->rows($timerSql,[$i,$id]);$running=[];foreach($timers as$t)if((int)$t['actor_id']===$a&&$t['stopped_at']===null)$running[]=$t;if(count($running)>1)throw new DomainException('native_company_task_timer_invalid');
            $creatorSql='SELECT actor_id,operation,input_hash,request_key FROM '.$this->p.'company_project_requests WHERE issuer_id=? AND result_id=? AND operation IN (?,?) ORDER BY request_key';$creatorArgs=$isProject?[$i,$id,'task_created','task_native_created']:[$i,$id,'standalone_task_created','standalone_task_native_created'];$creators=$this->rows($creatorSql,$creatorArgs);$actors=array_unique(array_column($creators,'actor_id'));if(count($actors)>1)throw new DomainException('native_task_creation_ambiguous');
            $recurring=false;foreach($item['schedules']as$s)$recurring=$recurring||in_array($s['state'],['active','paused'],true);
            $billed=match($row['billing_state']){'converted'=>true,'converting','partially_billed'=>null,'none','ready','disabled'=>false,default=>throw new DomainException('native_task_billing_state_invalid')};
            $facts=['name'=>$row['name'],'startdate'=>$fields['start_date'],'duedate'=>$row['due_date'],'status'=>$fields['workflow_status'],'priority'=>(int)$row['priority'],'todays_tasks'=>$fields['start_date']===$today&&$fields['workflow_status']!==5,'duedate_passed'=>$row['due_date']!==null&&$row['due_date']<$today&&$fields['workflow_status']!==5,'not_assigned'=>$assigned===[],'my_tasks'=>in_array($a,$assigned,true),'my_following_tasks'=>in_array($a,$item['followers'],true),'upcoming_tasks'=>$fields['start_date']!==null&&$fields['start_date']>$today&&$fields['workflow_status']!==5,'recurring'=>$recurring,'billable'=>$fields['billable']===null?null:$fields['billable']===1,'billed'=>$billed,'assigned'=>$assigned];
            $values=$custom->values($i,$a,'tasks',$item['id']);$sort=$facts+['id'=>$item['id'],'tags'=>implode(',',array_column($item['tags'],'name'))];$sort['assigned']=implode(',',array_map(static fn($m)=>trim($m['firstname'].' '.$m['lastname']),$item['assignees']));foreach($definitions as$d)$sort['cf_'.$d['id']]=$values[(string)$d['id']]??'';
            $all[]=$item+['facts'=>$facts,'sort'=>$sort,'custom_values'=>$values,'running_timer'=>$running[0]??null,'current_user_is_creator'=>$actors!==[]&&(int)reset($actors)===$a,'billing_pending'=>$billed===null];$snapshots[]=[$timerSql,[$i,$id],$timers,$creatorSql,$creatorArgs,$creators,$item['id'],$values];
        }
        $filtered=array_values(array_filter($all,function($r)use($request,$builder,$today){if(!NativeCompanyRelatedTaskRules::matches($builder,$r['facts'],$today))return false;if($request['search']==='')return true;$hay=implode(' ',array_map(static fn($v)=>is_array($v)?implode(',',$v):(string)$v,$r['sort']));return str_contains(mb_strtolower($hay),mb_strtolower($request['search']));}));
        usort($filtered,static function($left,$right)use($request){foreach($request['order']as$o){$x=$left['sort'][$o['field']];$y=$right['sort'][$o['field']];$cmp=in_array($o['field'],['id','status','priority'],true)?((int)$x<=>(int)$y):strnatcasecmp((string)$x,(string)$y);if($cmp!==0)return$o['direction']==='asc'?$cmp:-$cmp;}return(int)$left['id']<=>(int)$right['id'];});
        foreach($snapshots as[$q,$args,$rows,$cq,$ca,$cr,$id,$values])if($this->rows($q,$args)!==$rows||$this->rows($cq,$ca)!==$cr||$custom->values($i,$a,'tasks',$id)!==$values)throw new DomainException('native_task_relation_stale');
        if(($confirm&&$read()!==$source)||$custom->definitions($i,$a,'tasks',['active'=>1,'show_on_table'=>1])!==$definitions)throw new DomainException('native_task_relation_stale');$table->contextIdentity();
        return['draw'=>$request['draw'],'recordsTotal'=>count($all),'recordsFiltered'=>count($filtered),'rows'=>array_slice($filtered,$request['start'],$request['length']),'definitions'=>$definitions,'today'=>$today];
    }
}
