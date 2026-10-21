<?php

declare(strict_types=1);
require_once __DIR__.'/CompanyLeadsSchema.php';
require_once __DIR__.'/CompanyClients.php';
require_once __DIR__.'/CompanyContacts.php';
require_once __DIR__.'/CompanyApiTokens.php';
require_once __DIR__.'/CompanyLeadsFiles.php';
require_once __DIR__.'/CompanyLeadsImports.php';
require_once __DIR__.'/CompanyLeadsNotifications.php';

/** Private company leads; legacy lead IDs, hooks, email and ambient sessions are never inputs. */
final class CompanyLeads
{
    use CompanyLeadsFiles, CompanyLeadsImports, CompanyLeadsNotifications;
    private const CSV_FIELDS=['name','company','email','phone','vat','description'];
    private IssuerResourceAccess $acl;
    private array $frames=[];
    private array $validators=[];
    private ?array $api=null;
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid table prefix');
        $this->acl=new IssuerResourceAccess($db,$p);
    }
    /** Finite facade binds the actual authenticated token; callers cannot supply an issuer override. */
    public static function forApi($db,string $p,string $secret,string $scope):array
    {
        $s=new self($db,$p);$who=(new CompanyApiTokens($db,$p))->resolve($secret,$scope);
        $s->api=$who+['secret'=>$secret];return [$s,$who];
    }
    public function tables():array
    {
        return array_values(array_unique(array_merge(array_keys(CompanyLeadsSchema::definitions($this->p)),(new CompanyClients($this->db,$this->p))->tables(),(new CompanyContacts($this->db,$this->p))->leadTables(),['company_api_tokens','company_api_token_requests'])));
    }
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private static function now():string{return gmdate('Y-m-d H:i:s');}
    private static function key(string $key):void{if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new DomainException('company_lead_invalid_input');}
    private static function integer($v,int $min=0,int $max=2147483647):int
    {
        if(!(is_int($v)||(is_string($v)&&preg_match('/^(0|[1-9][0-9]*)$/D',$v)))||(float)$v>$max||(int)$v<$min)throw new DomainException('company_lead_invalid_input');return (int)$v;
    }
    private static function text($v,int $max,bool $required=false,bool $multiline=false):string
    {
        if(!is_string($v)||strlen($v)>$max||preg_match('//u',$v)!==1||preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/':'/[\x00-\x1f\x7f]/',$v))throw new DomainException('company_lead_invalid_input');
        $v=trim($v);if($required&&$v==='')throw new DomainException('company_lead_invalid_input');return $v;
    }
    private function authorize(int $issuer,int $actor,string $cap):void
    {
        if(!$this->acl->can($issuer,$actor,'leads','view')||!$this->acl->can($issuer,$actor,'leads',$cap==='convert'?'edit':$cap)||($cap==='convert'&&(!$this->acl->can($issuer,$actor,'customers','view')||!$this->acl->can($issuer,$actor,'customers','create'))))throw new DomainException('company_lead_access_denied');
        if($this->api!==null){if($this->api['issuer_id']!==$issuer||$this->api['actor_id']!==$actor)throw new DomainException('company_api_scope_denied');(new CompanyApiTokens($this->db,$this->p))->guard($this->api['secret'],$this->api['scope'],$issuer,$actor);}
    }
    private function transaction(int $issuer,int $actor,string $cap,string $operation,?string $key,callable $work)
    {
        CompanyLeadsSchema::assertValid($this->db,$this->p);CompanyApiSchema::assertValid($this->db,$this->p);
        $this->frames=[];$this->validators=[];
        try{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($issuer,$actor,$cap,$operation,$key,$work){
            $this->acl->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor,$cap);$result=$work();
            $this->authorize($issuer,$actor,$cap);
            if($this->api!==null)(new CompanyApiTokens($this->db,$this->p))->usedInOwnedTransaction($this->api['secret'],$this->api['scope'],$issuer,$actor,'leads_'.$operation,$key);
            $this->authorize($issuer,$actor,$cap);$this->assertFrames();foreach($this->validators as $check)$check();
            return $result;
        });}finally{$this->frames=[];$this->validators=[];}
    }
    private function remember(string $table,array $where,array $want,bool $replace=false):void
    {
        $key=$table.':'.json_encode($where,JSON_THROW_ON_ERROR);if(isset($this->frames[$key])&&!$replace){[$oldTable,$oldWhere,$oldWant]=$this->frames[$key];$this->confirm($oldTable,$oldWhere,$oldWant);}else $this->frames[$key]=[$table,$where,$want];$this->confirm($table,$where,$want);
    }
    private function confirm(string $table,array $where,array $want):void
    {
        $columns=implode(',',array_map(static fn($v)=>'`'.$v.'`',array_keys($want)));$parts=[];foreach($where as $col=>$v)$parts[]='`'.$col.'`=?';
        $rows=$this->rows('SELECT '.$columns.' FROM `'.$this->p.$table.'` WHERE '.implode(' AND ',$parts),array_values($where));
        if(count($rows)!==1)throw new RuntimeException('issuer_lead_write_unconfirmed');
        foreach($want as $field=>$v)if(!array_key_exists($field,$rows[0])||($v===null?$rows[0][$field]!==null:$rows[0][$field]===null||(string)$rows[0][$field]!== (string)$v))throw new RuntimeException('issuer_lead_write_unconfirmed');
    }
    private function assertFrames():void{foreach($this->frames as [$table,$where,$want])$this->confirm($table,$where,$want);}
    private function insert(string $table,array $data,string $pk='id')
    {
        IssuerStorage::query($this->db,'INSERT INTO `'.$this->p.$table.'` (`'.implode('`,`',array_keys($data)).'`) VALUES('.implode(',',array_fill(0,count($data),'?')).')',array_values($data));
        $id=$data[$pk]??(int)$this->db->insert_id();if($id===0||$id==='')throw new RuntimeException('issuer_lead_write_unconfirmed');$this->remember($table,[$pk=>$id],[$pk=>$id]+$data);return $id;
    }
    private function updateRow(string $table,array $old,array $changes,string $pk='id'):array
    {
        $parts=[];foreach($changes as $field=>$unused)$parts[]='`'.$field.'`=?';
        IssuerStorage::query($this->db,'UPDATE `'.$this->p.$table.'` SET '.implode(',',$parts).' WHERE `'.$pk.'`=? AND issuer_id=?',array_merge(array_values($changes),[$old[$pk],$old['issuer_id']]));
        $want=array_replace($old,$changes);$this->remember($table,[$pk=>$old[$pk]],$want,true);return $want;
    }
    private function audit(int $issuer,int $actor,string $operation,string $kind,string $id):void
    {
        $this->insert('issuers_audit',['actor_id'=>$actor,'event'=>'company_lead_'.$operation,'issuer_id'=>$issuer,'subject_id'=>$kind==='lead'?(int)$id:$issuer,'created_at'=>self::now(),'metadata_json'=>json_encode(['kind'=>$kind,'id'=>$id],JSON_THROW_ON_ERROR)]);
    }
    private function receiptObject(int $issuer,int $actor,string $kind,string $id):void
    {
        [$table,$pk]=match($kind){'lead'=>['company_leads','id'],'stage'=>['company_lead_stages','id'],'source'=>['company_lead_sources','id'],'import'=>['company_lead_imports','batch_id'],'notice'=>['company_lead_notices','id'],default=>throw new RuntimeException('issuer_lead_receipt_invalid')};
        $check=function()use($table,$pk,$id,$issuer,$actor,$kind):void{$r=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE `'.$pk.'`=? AND issuer_id=?',[$id,$issuer])[0]??null;if(!$r||($kind==='import'&&(int)$r['actor_id']!==$actor)||($kind==='notice'&&(int)$r['recipient_id']!==$actor))throw new DomainException('company_lead_request_conflict');if($kind==='notice')$this->guardNotice($r);if($kind==='import')$this->importPayload($r);};$check();$this->validators[]=$check;
    }
    private function request($issuer,$actor,string $cap,string $operation,string $key,array $input,callable $work)
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);self::key($key);
        $origin=$this->api===null?null:[$this->api['token_id'],$this->api['scope']];$hash=hash('sha256',json_encode([$operation,$input,$origin],JSON_THROW_ON_ERROR));
        return $this->transaction($issuer,$actor,$cap,$operation,$key,function()use($issuer,$actor,$operation,$key,$hash,$work){
            $r=$this->rows('SELECT * FROM '.$this->p.'company_lead_requests WHERE request_key=? FOR UPDATE',[$key])[0]??null;
            if($r){if((int)$r['issuer_id']!==$issuer||(int)$r['actor_id']!==$actor||$r['operation']!==$operation||!hash_equals($r['input_hash'],$hash))throw new DomainException('company_lead_request_conflict');$this->receiptObject($issuer,$actor,$r['object_kind'],$r['object_id']);$this->remember('company_lead_requests',['request_key'=>$key],$r);return json_decode($r['result_json'],true,32,JSON_THROW_ON_ERROR);}
            [$kind,$id,$result]=$work($issuer,$actor);$id=(string)$id;$this->audit($issuer,$actor,$operation,$kind,$id);
            $this->insert('company_lead_requests',['request_key'=>$key,'issuer_id'=>$issuer,'actor_id'=>$actor,'operation'=>$operation,'input_hash'=>$hash,'object_kind'=>$kind,'object_id'=>$id,'result_json'=>json_encode($result,JSON_THROW_ON_ERROR),'created_at'=>self::now()],'request_key');return $result;
        });
    }
    private function lead(int $issuer,int $id,bool $lock=true):array
    {
        $r=$this->rows('SELECT * FROM '.$this->p.'company_leads WHERE id=? AND issuer_id=?'.($lock?' FOR UPDATE':''),[$id,$issuer])[0]??null;
        if(!$r)throw new DomainException('company_lead_access_denied');return $r;
    }
    private function editable(array $row,$revision,bool $allowArchived=false):void
    {
        if((int)$row['revision']!==self::integer($revision,1))throw new DomainException('company_lead_stale');
        if($row['converted_client_id']!==null||(!$allowArchived&&(int)$row['archived']!==0))throw new DomainException('company_lead_closed');
        if((int)$row['revision']>=2147483647)throw new DomainException('company_lead_revision_limit');
    }
    private function related(int $issuer,string $kind,int $id,bool $active=true):array
    {
        $table=$kind==='stage'?'company_lead_stages':'company_lead_sources';$r=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE id=? AND issuer_id=? FOR UPDATE',[$id,$issuer])[0]??null;
        if(!$r||($active&&(int)$r['active']!==1))throw new DomainException('company_lead_reference_denied');$this->remember($table,['id'=>$id],$r);return $r;
    }
    private function assignee(int $issuer,int $id):void
    {
        if($id===0)return;$this->acl->lockFinancialIdentity($issuer,$id);
        if(!$this->acl->can($issuer,$id,'leads','view'))throw new DomainException('company_lead_assignee_denied');
        $this->validators[]=function()use($issuer,$id):void{if(!$this->acl->can($issuer,$id,'leads','view'))throw new DomainException('company_lead_assignee_denied');};
    }
    /** Reject nonexistent and ambiguous local times rather than silently move a reminder. */
    public static function reminder(string $local,string $zone):array
    {
        if($local===''){if($zone!=='')throw new DomainException('company_lead_invalid_reminder');return [null,''];}
        if(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}$/D',$local)||!in_array($zone,DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC),true))throw new DomainException('company_lead_invalid_reminder');
        $base=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$local,new DateTimeZone('UTC'));
        if(!$base||$base->format('Y-m-d\TH:i')!==$local||(int)$base->format('Y')<2000||(int)$base->format('Y')>2100)throw new DomainException('company_lead_invalid_reminder');
        $tz=new DateTimeZone($zone);$stamp=$base->getTimestamp();$offsets=[];foreach($tz->getTransitions($stamp-172800,$stamp+172800)?:[] as $t)$offsets[$t['offset']]=true;
        if(!$offsets)$offsets[$tz->getOffset($base)]=true;$matches=[];
        foreach(array_keys($offsets) as $offset){$candidate=(new DateTimeImmutable('@'.($stamp-$offset)))->setTimezone($tz);if($candidate->format('Y-m-d\TH:i')===$local)$matches[$candidate->getTimestamp()]=true;}
        if(count($matches)!==1)throw new DomainException('company_lead_invalid_reminder');return [gmdate('Y-m-d H:i:s',(int)array_key_first($matches)),$zone];
    }
    private function input(array $input):array
    {
        $fields=['name','company','email','phone','vat','description','stage_id','source_id','assignee_id','notify_assignee','reminder_local','reminder_timezone'];
        if(array_diff(array_keys($input),$fields))throw new DomainException('company_lead_invalid_input');$r=[];
        foreach(['name'=>191,'company'=>191,'email'=>100,'phone'=>64,'description'=>4000] as $field=>$max)$r[$field]=self::text($input[$field]??'', $max,$field==='name',$field==='description');
        $r['email']=strtolower($r['email']);if($r['email']!==''&&!filter_var($r['email'],FILTER_VALIDATE_EMAIL))throw new DomainException('company_lead_invalid_input');$r['vat']=IssuerPolicy::taxId($input['vat']??'',false)??'';
        $r['stage_id']=self::integer($input['stage_id']??null,1);$r['source_id']=self::integer($input['source_id']??null,1);$r['assignee_id']=self::integer($input['assignee_id']??0);$r['notify_assignee']=self::integer($input['notify_assignee']??0,0,1);
        [$r['reminder_at'],$r['reminder_timezone']]=self::reminder(self::text($input['reminder_local']??'',16),self::text($input['reminder_timezone']??'',64));
        if(($r['notify_assignee']===1||$r['reminder_at']!==null)&&$r['assignee_id']===0)throw new DomainException('company_lead_assignee_denied');return $r;
    }
    private function createRow(int $issuer,int $actor,array $input):int
    {
        $this->related($issuer,'stage',$input['stage_id']);$this->related($issuer,'source',$input['source_id']);$this->assignee($issuer,$input['assignee_id']);$now=self::now();
        $row=['issuer_id'=>$issuer,'created_by'=>$actor]+$input+['archived'=>0,'converted_client_id'=>null,'converted_contact_id'=>null,'converted_at'=>null,'revision'=>1,'created_at'=>$now,'updated_at'=>$now];
        $id=$this->insert('company_leads',$row);$this->scheduleNotices($issuer,$actor,['id'=>$id]+$row,'create');return $id;
    }
    public function create($issuer,$actor,array $input,string $key):int
    {
        $r=$this->input($input);return $this->request($issuer,$actor,'create','created',$key,$r,function(int $i,int $a)use($r):array{$id=$this->createRow($i,$a,$r);return ['lead',$id,$id];});
    }
    public function update($issuer,$actor,$id,$revision,array $input,string $key):int
    {
        $id=IssuerPolicy::id($id);$revision=self::integer($revision,1);$r=$this->input($input);
        return $this->request($issuer,$actor,'edit','updated',$key,[$id,$revision,$r],function(int $i,int $a)use($id,$revision,$r):array{$old=$this->lead($i,$id);$this->editable($old,$revision);$this->related($i,'stage',$r['stage_id']);$this->related($i,'source',$r['source_id']);$this->assignee($i,$r['assignee_id']);$new=$this->updateRow('company_leads',$old,$r+['revision'=>$revision+1,'updated_at'=>self::now()]);$this->cancelNotices($i,$id);$this->scheduleNotices($i,$a,$new,'edit');return ['lead',$id,$id];});
    }
    public function move($issuer,$actor,$id,$revision,$stage,string $key):int
    {
        $id=IssuerPolicy::id($id);$stage=IssuerPolicy::id($stage);$revision=self::integer($revision,1);
        return $this->request($issuer,$actor,'edit','moved',$key,[$id,$revision,$stage],function(int $i,int $a)use($id,$revision,$stage):array{$old=$this->lead($i,$id);$this->editable($old,$revision);$this->related($i,'stage',$stage);$new=$this->updateRow('company_leads',$old,['stage_id'=>$stage,'revision'=>$revision+1,'updated_at'=>self::now()]);$this->cancelNotices($i,$id);$this->scheduleNotices($i,$a,$new,'edit');return ['lead',$id,$id];});
    }
    public function archive($issuer,$actor,$id,$revision,bool $archived,string $key):int
    {
        $id=IssuerPolicy::id($id);$revision=self::integer($revision,1);
        return $this->request($issuer,$actor,'delete',$archived?'archived':'restored',$key,[$id,$revision,$archived],function(int $i,int $a)use($id,$revision,$archived):array{$old=$this->lead($i,$id);$this->editable($old,$revision,true);$this->updateRow('company_leads',$old,['archived'=>$archived?1:0,'revision'=>$revision+1,'updated_at'=>self::now()]);$this->cancelNotices($i,$id);return ['lead',$id,$id];});
    }
    public function note($issuer,$actor,$id,$revision,string $body,string $key):int
    {
        $id=IssuerPolicy::id($id);$revision=self::integer($revision,1);$body=self::text($body,4000,true,true);
        return $this->request($issuer,$actor,'edit','noted',$key,[$id,$revision,$body],function(int $i,int $a)use($id,$revision,$body):array{$old=$this->lead($i,$id);$this->editable($old,$revision);$count=(int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.'company_lead_notes WHERE issuer_id=? AND lead_id=?',[$i,$id])[0]['n'];if($count>=200)throw new DomainException('company_lead_note_limit');$this->insert('company_lead_notes',['issuer_id'=>$i,'lead_id'=>$id,'actor_id'=>$a,'body'=>$body,'created_at'=>self::now()]);$this->updateRow('company_leads',$old,['revision'=>$revision+1,'updated_at'=>self::now()]);$this->bumpNoticeRevision($i,$id,$revision+1);return ['lead',$id,$id];});
    }
    public function configure($issuer,$actor,string $kind,?int $id,?int $revision,array $input,string $key):int
    {
        if(!in_array($kind,['stage','source'],true))throw new DomainException('company_lead_invalid_input');$fields=$kind==='stage'?['name','category','position','active']:['name','active'];if(array_diff(array_keys($input),$fields))throw new DomainException('company_lead_invalid_input');
        $r=['name'=>self::text($input['name']??null,100,true),'active'=>self::integer($input['active']??1,0,1)];
        if($kind==='stage'){$r['category']=$input['category']??'open';if(!in_array($r['category'],['open','won','lost'],true))throw new DomainException('company_lead_invalid_input');$r['position']=self::integer($input['position']??0,0,999);}
        if($id!==null){$id=IssuerPolicy::id($id);$revision=self::integer($revision,1);}elseif($revision!==null)throw new DomainException('company_lead_invalid_input');
        return $this->request($issuer,$actor,'edit',$kind.'_configured',$key,[$id,$revision,$r],function(int $i,int $a)use($kind,$id,$revision,$r):array{
            $table=$kind==='stage'?'company_lead_stages':'company_lead_sources';
            if($this->rows('SELECT id FROM '.$this->p.$table.' WHERE issuer_id=? AND name=? AND id<>? FOR UPDATE',[$i,$r['name'],$id??0]))throw new DomainException('company_lead_name_conflict');
            if($id===null){if((int)$this->rows('SELECT COUNT(*) n FROM '.$this->p.$table.' WHERE issuer_id=?',[$i])[0]['n']>=50)throw new DomainException('company_lead_configuration_limit');$now=self::now();$id=$this->insert($table,['issuer_id'=>$i]+$r+['revision'=>1,'created_at'=>$now,'updated_at'=>$now]);}
            else{$old=$this->related($i,$kind,$id,false);if((int)$old['revision']!==$revision)throw new DomainException('company_lead_stale');if($revision>=2147483647)throw new DomainException('company_lead_revision_limit');if($kind==='stage'&&$old['category']!==$r['category'])throw new DomainException('company_lead_category_immutable');if($r['active']===0&&$this->rows('SELECT id FROM '.$this->p.'company_leads WHERE issuer_id=? AND '.$kind.'_id=? AND archived=0 AND converted_client_id IS NULL LIMIT 1',[$i,$id]))throw new DomainException('company_lead_reference_in_use');$this->updateRow($table,$old,$r+['revision'=>$revision+1,'updated_at'=>self::now()]);}
            return [$kind,$id,$id];
        });
    }
    public function configuration($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);return $this->transaction($issuer,$actor,'view','configuration',null,function()use($issuer):array{$out=[];foreach(['stages','sources'] as $kind){$table='company_lead_'.$kind;$rows=$this->rows('SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? ORDER BY '.($kind==='stages'?'position,':'').'id LIMIT 51',[$issuer]);if(count($rows)>50)throw new RuntimeException('issuer_lead_configuration_invalid');foreach($rows as $r)$this->remember($table,['id'=>$r['id']],$r);$out[$kind]=$rows;}return $out;});
    }
    private function projection(array $r):array
    {
        foreach(['id','issuer_id','created_by','assignee_id','stage_id','source_id','notify_assignee','archived','revision'] as $f)$r[$f]=(int)$r[$f];foreach(['converted_client_id','converted_contact_id'] as $f)if($r[$f]!==null)$r[$f]=(int)$r[$f];
        $r['reminder_local']=$r['reminder_at']===null?'':(new DateTimeImmutable($r['reminder_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone($r['reminder_timezone']))->format('Y-m-d\TH:i');return $r;
    }
    public function get($issuer,$actor,$id):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$id=IssuerPolicy::id($id);
        return $this->transaction($issuer,$actor,'view','read',null,function()use($issuer,$id):array{$r=$this->lead($issuer,$id);$this->remember('company_leads',['id'=>$id],$r);$r=$this->projection($r);$r['notes']=$this->rows('SELECT * FROM '.$this->p.'company_lead_notes WHERE issuer_id=? AND lead_id=? ORDER BY id LIMIT 201',[$issuer,$id]);if(count($r['notes'])>200)throw new RuntimeException('issuer_lead_notes_invalid');foreach($r['notes'] as $n)$this->remember('company_lead_notes',['id'=>$n['id']],$n);$r['files']=$this->rows('SELECT file_key,issuer_id,lead_id,actor_id,filename,mime,byte_size,active,created_at FROM '.$this->p.'company_lead_files WHERE issuer_id=? AND lead_id=? ORDER BY created_at,file_key LIMIT 21',[$issuer,$id]);if(count($r['files'])>20)throw new RuntimeException('issuer_lead_files_invalid');foreach($r['files'] as $f)$this->remember('company_lead_files',['file_key'=>$f['file_key']],$f);return $r;});
    }
    public function page($issuer,$actor,$after=0,int $limit=50,string $search='',string $state='active',?int $stage=null):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);$after=self::integer($after);self::integer($limit,1,100);$search=self::text($search,191);if(!in_array($state,['active','archived','all'],true))throw new DomainException('company_lead_invalid_input');if($stage!==null)$stage=IssuerPolicy::id($stage);
        return $this->transaction($issuer,$actor,'view','list',null,function()use($issuer,$after,$limit,$search,$state,$stage):array{$args=[$issuer,$after];$where='issuer_id=? AND id>?';if($state!=='all'){$where.=' AND archived=?';$args[]=$state==='archived'?1:0;}if($stage!==null){$where.=' AND stage_id=?';$args[]=$stage;}$needle='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%';$where.=" AND (name LIKE ? ESCAPE '!' OR company LIKE ? ESCAPE '!' OR email LIKE ? ESCAPE '!')";array_push($args,$needle,$needle,$needle);$rows=$this->rows('SELECT * FROM '.$this->p.'company_leads WHERE '.$where.' ORDER BY id LIMIT '.($limit+1),$args);$more=count($rows)>$limit;if($more)array_pop($rows);foreach($rows as $r)$this->remember('company_leads',['id'=>$r['id']],$r);return ['items'=>array_map(fn($r)=>$this->projection($r),$rows),'next'=>$more?(int)$rows[count($rows)-1]['id']:null];});
    }
    public function staffOptions($issuer,$actor):array
    {
        $issuer=IssuerPolicy::id($issuer);$actor=IssuerPolicy::id($actor);return $this->transaction($issuer,$actor,'view','assignees',null,function()use($issuer):array{$rows=$this->rows('SELECT staffid,firstname,lastname FROM '.$this->p.'staff WHERE active=1 ORDER BY staffid LIMIT 1001');if(count($rows)>1000)throw new DomainException('company_lead_staff_limit');return array_values(array_filter($rows,fn($r)=>$this->acl->can($issuer,(int)$r['staffid'],'leads','view')));});
    }
    public function convert($issuer,$actor,$id,$revision,string $key):array
    {
        $id=IssuerPolicy::id($id);$revision=self::integer($revision,1);
        return $this->request($issuer,$actor,'convert','converted',$key,[$id,$revision],function(int $i,int $a)use($id,$revision,$key):array{
            $lead=$this->lead($i,$id);$this->editable($lead,$revision);$stage=$this->related($i,'stage',(int)$lead['stage_id']);if($stage['category']!=='won')throw new DomainException('company_lead_conversion_stage');
            $company=$lead['company']!==''?$lead['company']:$lead['name'];$vat=$lead['vat']!==''?$lead['vat']:null;$clientKey=hash('sha256','company-lead-conversion:'.$key);$before=date('Y-m-d H:i:s');
            $client=(new CompanyClients($this->db,$this->p))->createInOwnedTransaction($i,$a,['company'=>$company,'vat'=>$vat??''],$clientKey);$after=date('Y-m-d H:i:s');
            $receipt=$this->rows('SELECT * FROM '.$this->p.'company_client_requests WHERE request_key=?',[$clientKey])[0]??[];$created=$receipt['created_at']??'';
            if($created<$before||$created>$after)throw new RuntimeException('issuer_lead_conversion_unconfirmed');
            $this->remember('company_client_requests',['request_key'=>$clientKey],['request_key'=>$clientKey,'issuer_id'=>$i,'actor_id'=>$a,'input_hash'=>hash('sha256',json_encode([$company,$vat],JSON_THROW_ON_ERROR)),'client_id'=>$client,'created_at'=>$created]);
            $this->remember('clients',['userid'=>$client],['userid'=>$client,'company'=>$company,'vat'=>$vat,'active'=>1,'addedfrom'=>$a,'datecreated'=>$created]);
            $this->remember('issuers_clients',['client_id'=>$client],['client_id'=>$client,'owner_issuer_id'=>$i,'tax_id'=>$vat,'created_by'=>$a,'created_at'=>$created]);
            $clientAudits=$this->rows('SELECT * FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND subject_id=? AND event=?',[$i,$a,$client,'company_client_created']);
            if(count($clientAudits)!==1||$clientAudits[0]['created_at']!==$created)throw new RuntimeException('issuer_lead_conversion_unconfirmed');$this->remember('issuers_audit',['id'=>$clientAudits[0]['id']],$clientAudits[0]);
            $contactInput=['name'=>$lead['name'],'email'=>$lead['email'],'phone'=>$lead['phone']];$contacts=new CompanyContacts($this->db,$this->p);$proof=$contacts->createFromLeadInOwnedTransaction($i,$a,$client,$id,$contactInput);
            $this->validators[]=function()use($i,$a,$client,$id,$contactInput,$proof,$contacts):void{if(!$this->acl->can($i,$a,'customers','create')||$this->rows('SELECT client_id FROM '.$this->p.'issuers_client_links WHERE client_id=?',[$client]))throw new RuntimeException('issuer_lead_conversion_unconfirmed');$contacts->assertLeadContactInOwnedTransaction($i,$a,$client,$id,$contactInput,$proof);};
            $this->updateRow('company_leads',$lead,['converted_client_id'=>$client,'converted_contact_id'=>$proof['contact_id'],'converted_at'=>self::now(),'revision'=>$revision+1,'updated_at'=>self::now()]);$this->cancelNotices($i,$id);
            return ['lead',$id,['lead_id'=>$id,'client_id'=>$client,'contact_id'=>$proof['contact_id']]];
        });
    }
}
