<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskItemSchema.php';
require_once __DIR__.'/NativeCompanyTaskIndexProjection.php';

/** A checklist snapshot never aliases legacy/global item ids or invents authors. */
final class NativeCompanyTaskItems
{
    public function __construct(private $db,private string $prefix){}
    public static function tables(string $kind):array
    {
        if(!in_array($kind,['project','standalone'],true))throw new InvalidArgumentException('Invalid task kind');
        return ['company_'.$kind.'_checklist_state','company_'.$kind.'_checklist_items'];
    }
    private function rows(string $query,array $args):array{return IssuerStorage::query($this->db,$query,$args)->result_array();}
    private static function decoded(string $json):array
    {
        $items=json_decode($json,true,32,JSON_THROW_ON_ERROR);if(!is_array($items)||!array_is_list($items)||count($items)>30)throw new DomainException('native_task_checklist_invalid');
        foreach($items as$item)if(!is_array($item)||array_keys($item)!==['text','done']||!is_string($item['text'])||$item['text']===''||strlen($item['text'])>191||!in_array($item['done'],[0,1],true))throw new DomainException('native_task_checklist_invalid');return$items;
    }
    private function snapshot(int $issuer,int $task,string $kind,string $json,bool $lock):array
    {
        [$state,$table]=self::tables($kind);$suffix=$lock?' FOR UPDATE':'';$args=[$issuer,$task];
        $meta=$this->rows('SELECT issuer_id,task_id,source_hash,revision FROM '.$this->prefix.$state.' WHERE issuer_id=? AND task_id=?'.$suffix,$args);
        $all=$this->rows('SELECT id,issuer_id,task_id,position,description,finished,created_by,finished_by,assigned_id,revision FROM '.$this->prefix.$table.' WHERE issuer_id=? AND task_id=? ORDER BY id'.$suffix,$args);
        $items=array_values(array_filter($all,static fn($row)=>$row['position']!==null));usort($items,static fn($x,$y)=>(int)$x['position']<=>(int)$y['position']);
        $source=self::decoded($json);if(count($meta)>1)throw new DomainException('native_task_checklist_changed');
        if(!$meta){if($all)throw new DomainException('native_task_checklist_changed');return['initialized'=>false,'source_hash'=>hash('sha256',$json),'revision'=>null,'items'=>[],'historical_count'=>count($source),'historical_items'=>$source];}
        if(!hash_equals(hash('sha256',$json),$meta[0]['source_hash'])||count($source)!==count($items)||(int)$meta[0]['revision']<1)throw new DomainException('native_task_checklist_changed');
        foreach($items as$n=>$row){if((int)$row['issuer_id']!==$issuer||(int)$row['task_id']!==$task||(int)$row['position']!==$n||$row['description']!==$source[$n]['text']||(int)$row['finished']!==$source[$n]['done']||(int)$row['revision']<1||((int)$row['finished']===0&&$row['finished_by']!==null))throw new DomainException('native_task_checklist_changed');foreach(['created_by','finished_by','assigned_id']as$key)if($row[$key]!==null)IssuerPolicy::id($row[$key]);}
        return['initialized'=>true,'source_hash'=>$meta[0]['source_hash'],'revision'=>(int)$meta[0]['revision'],'items'=>$items,'historical_count'=>count($source),'historical_items'=>[]];
    }
    public function read(int $issuer,int $actor,$canonical):array
    {
        NativeCompanyTaskItemSchema::assertValid($this->db,$this->prefix);$reader=new NativeCompanyTaskIndexProjection($this->db,$this->prefix);$item=$reader->readOne($issuer,$actor,$canonical);$kind=$item['source_kind']==='company_task'?'project':'standalone';$id=$item['source_id'];$json=$item['row']['checklist_json'];
        $snapshot=$this->snapshot($issuer,$id,$kind,$json,false);if($reader->readOne($issuer,$actor,$canonical)!==$item||$this->snapshot($issuer,$id,$kind,$json,false)!==$snapshot)throw new DomainException('native_task_checklist_changed');return$snapshot;
    }
    /** Caller owns all tables, has already locked task and authorized mutation. */
    public function initializeInOwned(int $issuer,int $task,string $kind,string $json):array
    {
        [$state,$table]=self::tables($kind);IssuerTransaction::assertOwned($this->db,$this->prefix,[$state,$table]);$before=$this->snapshot($issuer,$task,$kind,$json,true);if($before['initialized'])return$before;
        foreach(self::decoded($json)as$position=>$entry)IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.'(issuer_id,task_id,position,description,finished,created_by,finished_by,assigned_id,revision) VALUES(?,?,?,?,?,NULL,NULL,NULL,1)',[$issuer,$task,$position,$entry['text'],$entry['done']]);
        IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$state.'(issuer_id,task_id,source_hash,revision) VALUES(?,?,?,1)',[$issuer,$task,hash('sha256',$json)]);
        $after=$this->snapshot($issuer,$task,$kind,$json,true);if(!$after['initialized']||$after['historical_count']!==$before['historical_count'])throw new RuntimeException('native_task_checklist_unconfirmed');return$after;
    }
    public function guardLegacyWriteInOwned(int $issuer,int $task,string $kind,string $oldJson,string $newJson):void
    {
        IssuerTransaction::assertOwned($this->db,$this->prefix,self::tables($kind));$current=$this->snapshot($issuer,$task,$kind,$oldJson,true);
        if($current['initialized']&&!hash_equals(hash('sha256',$oldJson),hash('sha256',$newJson)))throw new DomainException('native_task_checklist_writer_required');
    }
    /** Mutate active items and the legacy JSON together, retaining deleted rows. */
    public function mutateInOwned(int $issuer,int $actor,int $task,string $kind,string $json,int $stateRevision,array $command):array
    {
        [$state,$table]=self::tables($kind);IssuerTransaction::assertOwned($this->db,$this->prefix,[$state,$table]);$before=$this->snapshot($issuer,$task,$kind,$json,true);
        if(!$before['initialized']||$before['revision']!==$stateRevision)throw new DomainException('native_task_checklist_stale');
        $action=$command['action']??null;$allowed=match($action){'add'=>['action','text'],'edit'=>['action','id','revision','text'],'complete'=>['action','id','revision','finished'],'assign'=>['action','id','revision','assigned_id'],'delete'=>['action','id','revision'],'reorder'=>['action','ids'],default=>throw new DomainException('native_task_checklist_invalid')};
        if(array_diff(array_keys($command),$allowed)||array_diff($allowed,array_keys($command)))throw new DomainException('native_task_checklist_invalid');
        $items=$before['items'];$selected=null;
        if(in_array($action,['edit','complete','assign','delete'],true)){$id=IssuerPolicy::id($command['id']);$revision=IssuerPolicy::id($command['revision']);foreach($items as$n=>$row)if((int)$row['id']===$id)$selected=$n;if($selected===null||(int)$items[$selected]['revision']!==$revision)throw new DomainException('native_task_checklist_stale');}
        if(in_array($action,['add','edit'],true)){$text=$command['text'];if(!is_string($text)||trim($text)===''||strlen($text)>191||preg_match('//u',$text)!==1||preg_match('/[\x00-\x1f\x7f]/',$text))throw new DomainException('native_task_checklist_invalid');$text=trim($text);}
        if($action==='add'){
            if(count($items)>=30)throw new DomainException('native_task_checklist_limit');IssuerStorage::query($this->db,'INSERT INTO '.$this->prefix.$table.'(issuer_id,task_id,position,description,finished,created_by,finished_by,assigned_id,revision) VALUES(?,?,?,?,0,?,NULL,NULL,1)',[$issuer,$task,count($items),$text,$actor]);$id=(int)$this->db->insert_id();if($id<1)throw new RuntimeException('native_task_checklist_unconfirmed');$items[]=['id'=>$id,'issuer_id'=>$issuer,'task_id'=>$task,'position'=>count($items),'description'=>$text,'finished'=>0,'created_by'=>$actor,'finished_by'=>null,'assigned_id'=>null,'revision'=>1];
        }elseif($action==='edit'||$action==='complete'||$action==='assign'){
            $field=$action==='edit'?'description':($action==='complete'?'finished':'assigned_id');$value=$action==='edit'?$text:($action==='complete'?$command['finished']:$command['assigned_id']);if($action==='assign'&&$value!==null)$value=IssuerPolicy::id($value);if($action==='complete'&&!in_array($value,[0,1,'0','1'],true))throw new DomainException('native_task_checklist_invalid');if($action==='complete')$value=(int)$value;
            $item=$items[$selected];if($action==='complete'){$finisher=$value===1?$actor:null;IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$table.' SET finished=?,finished_by=?,revision=revision+1 WHERE issuer_id=? AND task_id=? AND id=? AND revision=?',[$value,$finisher,$issuer,$task,$id,$revision]);$items[$selected]['finished_by']=$finisher;}else IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$table.' SET '.$field.'=?,revision=revision+1 WHERE issuer_id=? AND task_id=? AND id=? AND revision=?',[$value,$issuer,$task,$id,$revision]);$items[$selected][$field]=$value;$items[$selected]['revision']=(int)$item['revision']+1;
        }else{
            if($action==='delete'){$item=$items[$selected];IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$table.' SET position=NULL,revision=revision+1 WHERE issuer_id=? AND task_id=? AND id=? AND revision=?',[$issuer,$task,$id,$revision]);array_splice($items,$selected,1);}
            else{$ids=$command['ids'];if(!is_array($ids)||!array_is_list($ids)||count($ids)!==count($items))throw new DomainException('native_task_checklist_invalid');$indexed=[];foreach($items as$row)$indexed[(int)$row['id']]=$row;$ordered=[];foreach($ids as$value){$value=IssuerPolicy::id($value);if(!isset($indexed[$value]))throw new DomainException('native_task_checklist_invalid');$ordered[]=$indexed[$value];unset($indexed[$value]);}if($indexed)throw new DomainException('native_task_checklist_invalid');$items=$ordered;}
            // Free the unique position range before assigning the new order.
            IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$table.' SET position=position+64 WHERE issuer_id=? AND task_id=? AND position IS NOT NULL',[$issuer,$task]);
            foreach($items as$n=>&$row){$old=(int)$row['position'];$increment=$old===$n?0:1;IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$table.' SET position=?,revision=revision+? WHERE issuer_id=? AND task_id=? AND id=?',[$n,$increment,$issuer,$task,$row['id']]);$row['position']=$n;$row['revision']=(int)$row['revision']+$increment;}unset($row);
        }
        $source=[];foreach($items as$row)$source[]=['text'=>$row['description'],'done'=>(int)$row['finished']];$updated=json_encode($source,JSON_THROW_ON_ERROR);
        $parent=$kind==='project'?'company_tasks':'company_standalone_tasks';IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$parent.' SET checklist_json=? WHERE issuer_id=? AND id=?',[$updated,$issuer,$task]);
        IssuerStorage::query($this->db,'UPDATE '.$this->prefix.$state.' SET source_hash=?,revision=revision+1 WHERE issuer_id=? AND task_id=? AND revision=?',[hash('sha256',$updated),$issuer,$task,$stateRevision]);
        $after=$this->snapshot($issuer,$task,$kind,$updated,true);$normalize=static fn($rows)=>array_map(static fn($row)=>array_map(static fn($v)=>$v===null?null:(string)$v,$row),$rows);
        if($after['revision']!==$stateRevision+1||$normalize($after['items'])!==$normalize($items))throw new RuntimeException('native_task_checklist_unconfirmed');
        return['before'=>$before,'after'=>$after,'json'=>$updated,'action'=>$action];
    }
}
