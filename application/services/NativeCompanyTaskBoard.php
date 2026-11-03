<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskIndexProjection.php';
require_once __DIR__.'/NativeCompanyTaskSettings.php';
/** Original task board: actor view preference and issuer-wide order over authorized tasks. */
final class NativeCompanyTaskBoard
{
    public function __construct(private $db,private string $p){}
    private function rows(string $sql,array $args=[]):array{return IssuerStorage::query($this->db,$sql,$args)->result_array();}
    private function authorize(int $issuer,int $actor):void{if(!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'tasks','view'))throw new DomainException('native_task_board_denied');}
    private function key(int $issuer):string{return 'issuer:'.$issuer.':tasks-board';}
    private function stored(int $issuer,int $actor):array{
        $this->authorize($issuer,$actor);$rows=$this->rows('SELECT meta_value FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$actor,$this->key($issuer)]);
        if(count($rows)>1)throw new DomainException('native_task_board_ambiguous');
        $value=$rows?json_decode($rows[0]['meta_value'],true,32,JSON_THROW_ON_ERROR):['view'=>'table','order'=>[]];
        $orders=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND event=? ORDER BY id DESC LIMIT 1',[$issuer,'native_task_board_reordered']);
        if($orders){$proof=json_decode($orders[0]['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(!isset($proof['after']['order'])||!is_array($proof['after']['order']))throw new DomainException('native_task_board_invalid');$value['order']=$proof['after']['order'];}
        if(!is_array($value)||array_keys($value)!==['view','order']||!in_array($value['view'],['table','board'],true)||!is_array($value['order'])||!array_is_list($value['order'])||count($value['order'])>10000||count(array_unique($value['order'],SORT_STRING))!==count($value['order']))throw new DomainException('native_task_board_invalid');
        foreach($value['order']as$id)if(!is_string($id)||NativeCompanyResourceId::decode('task',$id)['canonical_id']!==$id)throw new DomainException('native_task_board_invalid');
        return$value;
    }
    public function preference(int $issuer,int $actor):array{
        $value=$this->stored($issuer,$actor);$this->authorize($issuer,$actor);if($this->stored($issuer,$actor)!==$value)throw new DomainException('native_task_board_stale');
        return$value+['revision'=>hash('sha256',json_encode([$issuer,$actor,$value],JSON_THROW_ON_ERROR))];
    }
    private function pageLimit(int $issuer):int{
        $store=new CompanySettingsStorage($this->db,$this->p);$name='tasks_kanban_limit';$value=$store->exists($issuer,$name)?$store->get($issuer,$name):NativeCompanyTaskSettings::extras()[$name];
        NativeCompanyTaskSettings::validateExtras([$name=>$value]);return(int)$value;
    }
    public function read(int $issuer,int $actor,string $search='',?int $status=null,int $offset=0):array{
        if(strlen($search)>191||preg_match('//u',$search)!==1||($status!==null&&!in_array($status,[1,2,3,4,5],true))||$offset<0||$offset>10000)throw new DomainException('native_task_board_invalid');
        $reader=new NativeCompanyTaskIndexProjection($this->db,$this->p);$items=$reader->read($issuer,$actor);$preference=$this->preference($issuer,$actor);$positions=array_flip($preference['order']);
        $filtered=array_values(array_filter($items,static fn($item)=>($status===null||$item['fields']['workflow_status']===$status)&&($search===''||mb_stripos($item['row']['name'],$search)!==false||mb_stripos(implode(' ',array_column($item['tags'],'name')),$search)!==false)));
        usort($filtered,static fn($a,$b)=>($positions[(string)$a['id']]??PHP_INT_MAX)<=>($positions[(string)$b['id']]??PHP_INT_MAX)?:((int)$a['id']<=>(int)$b['id']));
        $limit=$this->pageLimit($issuer);$total=count($filtered);$rows=array_slice($filtered,$offset,$limit);
        if($reader->read($issuer,$actor)!==$items||$this->preference($issuer,$actor)!==$preference||$this->pageLimit($issuer)!==$limit)throw new DomainException('native_task_board_stale');
        return ['items'=>$rows,'total'=>$total,'offset'=>$offset,'next'=>$offset+$limit<$total?$offset+$limit:null,'limit'=>$limit,'preference'=>$preference];
    }
    public function save(int $issuer,int $actor,string $revision,string $request,array $command):void{
        if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!preg_match('/^[a-f0-9]{64}$/D',$request)||!isset($command['action']))throw new DomainException('native_task_board_invalid');
        $action=$command['action'];$keys=match($action){'view'=>['action','view'],'move'=>['action','task','before'],default=>throw new DomainException('native_task_board_invalid')};
        if(array_keys($command)!==$keys)throw new DomainException('native_task_board_invalid');
        $reader=new NativeCompanyTaskIndexProjection($this->db,$this->p);$items=$reader->read($issuer,$actor);$ids=array_map('strval',array_column($items,'id'));
        if($action==='view'&&!in_array($command['view'],['table','board'],true))throw new DomainException('native_task_board_invalid');
        if($action==='move'&&(!in_array($command['task'],$ids,true)||($command['before']!==''&&!in_array($command['before'],$ids,true))||$command['task']===$command['before']))throw new DomainException('native_task_board_denied');
        // A board preference cannot grant access. Every render resolves source
        // ownership again; shared ordering additionally requires task edit access.
        if($action==='move'&&!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'tasks','edit'))throw new DomainException('native_task_board_denied');
        $hash=hash('sha256',json_encode([$issuer,$actor,$revision,$command],JSON_THROW_ON_ERROR));
        IssuerTransaction::run($this->db,$this->p,['issuers_lock','issuers_registry','issuers_installation_identity','issuers_staff','issuers_resource_permissions','staff','user_meta','issuers_audit'],function()use($issuer,$actor,$revision,$request,$command,$action,$ids,$hash){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$before=$this->preference($issuer,$actor);
            if($action==='move'&&!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'tasks','edit'))throw new DomainException('native_task_board_denied');
            $event=$action==='move'?'native_task_board_reordered':'native_task_board_saved';$receipts=$this->rows('SELECT metadata_json FROM '.$this->p.'issuers_audit WHERE issuer_id=? AND actor_id=? AND event IN (?,?) ORDER BY id',[$issuer,$actor,'native_task_board_saved','native_task_board_reordered']);
            foreach($receipts as$receipt){$proof=json_decode($receipt['metadata_json'],true,32,JSON_THROW_ON_ERROR);if(($proof['request_key']??null)===$request){if(($proof['input_hash']??null)!==$hash)throw new DomainException('native_task_board_conflict');return;}}
            if(!hash_equals($revision,$before['revision']))throw new DomainException('native_task_board_stale');$after=['view'=>$before['view'],'order'=>$before['order']];
            if($action==='view')$after['view']=$command['view'];
            else{$order=array_values(array_intersect($before['order'],$ids));foreach($ids as$id)if(!in_array($id,$order,true))$order[]=$id;$order=array_values(array_filter($order,static fn($id)=>$id!==$command['task']));$at=$command['before']===''?count($order):array_search($command['before'],$order,true);array_splice($order,$at,0,[$command['task']]);$after['order']=$order;}
            $value=json_encode(['view'=>$after['view'],'order'=>[]],JSON_THROW_ON_ERROR);$current=$this->rows('SELECT umeta_id FROM '.$this->p.'user_meta WHERE staff_id=? AND meta_key=?',[$actor,$this->key($issuer)]);
            if($current)IssuerStorage::query($this->db,'UPDATE '.$this->p.'user_meta SET meta_value=? WHERE staff_id=? AND meta_key=?',[$value,$actor,$this->key($issuer)]);
            else IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'user_meta(staff_id,meta_key,meta_value) VALUES(?,?,?)',[$actor,$this->key($issuer),$value]);
            $proof=json_encode(['request_key'=>$request,'input_hash'=>$hash,'before'=>$before,'after'=>$after],JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
            IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(actor_id,event,issuer_id,subject_id,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$actor,$event,$issuer,$actor,$now,$proof]);$id=(int)$this->db->insert_id();
            if($id<1||!$this->rows('SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND actor_id=? AND issuer_id=? AND event=? AND metadata_json=?',[$id,$actor,$issuer,$event,$proof])||$this->stored($issuer,$actor)!==$after)throw new RuntimeException('issuer_task_board_unconfirmed');$this->authorize($issuer,$actor);
            if($action==='move'&&!(new IssuerResourceAccess($this->db,$this->p))->can($issuer,$actor,'tasks','edit'))throw new DomainException('native_task_board_denied');
        });
        $this->authorize($issuer,$actor);
    }
}
