<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyAuxiliary.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/NativeCompanyAuxiliaryLegacy.php';

/** Browser IDs carry storage lineage; encoding never authorizes a resource. */
final class NativeCompanyAuxiliaryUi
{
    private NativeCompanyAuxiliary $owned;
    private ?NativeCompanyAuxiliaryLegacy $legacy=null;
    public function __construct(private $db,private string $p,private int $issuer,private int $actor,private $ci=null)
    { $this->owned=new NativeCompanyAuxiliary($db,$p,$issuer,$actor); if(NativeCompanyAuxiliaryLegacy::eligible($db,$p,$issuer,$actor))$this->legacy=new NativeCompanyAuxiliaryLegacy($db,$p,$issuer,$actor); }
    private static function id(string $kind,$id):int
    {
        $decoded=NativeCompanyResourceId::decode($kind,$id);
        if($decoded['source_kind']!=='company_'.$kind)throw new DomainException('company_auxiliary_access_denied');
        return $decoded['source_id'];
    }
    private static function encoded(string $kind,$id):string{return NativeCompanyResourceId::encode('company_'.$kind,$id);}
    private static function row(string $kind,array $row):array
    {
        $key=match($kind){'todo'=>'todoid','announcement'=>'announcementid',default=>'id'};
        $row['source_id']=$row[$key];$row['source_kind']='company_'.$kind;$row[$key]=self::encoded($kind,$row[$key]);return$row;
    }
    private static function rows(string $kind,array $rows):array{return array_map(static fn($row)=>self::row($kind,$row),$rows);}
    public function todos(bool $finished,int $limit=10,int $offset=0):array
    {
        if($this->legacy===null)return self::rows('todo',$this->owned->todos($finished,$limit,$offset));
        if($limit<1||$limit>100||$offset<0||$offset>100000)throw new DomainException('company_auxiliary_invalid_page');
        $rows=[];$need=$limit+$offset;
        foreach([$this->owned,$this->legacy]as$source){for($pos=0;$pos<$need;$pos+=100){$part=$source->todos($finished,min(100,$need-$pos),$pos);foreach($part as$row)$rows[]=$source===$this->owned?self::row('todo',$row):$row;if(count($part)<min(100,$need-$pos))break;}}
        usort($rows,static fn($a,$b)=>(int)$a['item_order']<=>(int)$b['item_order']?:((int)$a['todoid']<=>(int)$b['todoid']));return array_slice($rows,$offset,$limit);
    }
    public function todoCounts():array{$counts=$this->owned->todoCounts();if($this->legacy!==null){$old=$this->legacy->todoCounts();$counts[0]+=$old[0];$counts[1]+=$old[1];}return$counts;}
    public function todo($id):array{$ref=NativeCompanyResourceId::decode('todo',$id);if($ref['source_kind']==='todo'&&$this->legacy!==null)return$this->legacy->todo($ref['source_id']);return self::row('todo',$this->owned->todo(self::id('todo',$id)));}
    public function saveTodo(array $input,$id=null,$revision=null):string
    {
        if($id!==null&&$id!==''){$ref=NativeCompanyResourceId::decode('todo',$id);if($ref['source_kind']==='todo'&&$this->legacy!==null){if(array_keys($input)!==['description']||!is_string($input['description']))throw new DomainException('company_auxiliary_invalid_input');$this->legacy->mutateTodo($ref['source_id'],$revision,$input['description']);return(string)$id;}}
        return self::encoded('todo',$this->owned->saveTodo($input,$id===null||$id===''?null:self::id('todo',$id),$revision));
    }
    public function deleteTodo($id,$revision):bool{$ref=NativeCompanyResourceId::decode('todo',$id);if($ref['source_kind']==='todo'&&$this->legacy!==null){$this->legacy->mutateTodo($ref['source_id'],$revision,null,null,true);return true;}return$this->owned->deleteTodo(self::id('todo',$id),$revision);}
    public function orderTodos(array $input):array
    {
        if(count($input)>200)throw new DomainException('company_auxiliary_invalid_input');$owned=[];$legacy=[];$seen=[];
        foreach($input as$row){
            if(!is_array($row)||array_keys($row)!==[0,1,2,3])throw new DomainException('company_auxiliary_invalid_input');
            $ref=NativeCompanyResourceId::decode('todo',$row[0]);if(isset($seen[$ref['canonical_id']]))throw new DomainException('company_auxiliary_invalid_input');$seen[$ref['canonical_id']]=true;
            if($ref['source_kind']==='todo'&&$this->legacy!==null){if(!preg_match('/^[0-9]{1,6}$/D',(string)$row[1])||!in_array($row[2],[0,1,'0','1'],true))throw new DomainException('company_auxiliary_invalid_input');$legacy[]=[$ref['source_id'],(int)$row[1],(int)$row[2],$row[3]];}
            else{$row[0]=self::id('todo',$row[0]);$owned[]=$row;}
        }
        $run=function(bool $inOwned)use($owned,$legacy):array{
            $result=[];foreach($legacy as[$id,$order,$finished,$revision]){$saved=$this->legacy->mutateTodo($id,$revision,null,[$order,$finished],false,true);$result[(string)$id]=$saved['revision'];}
            foreach($this->owned->orderTodos($owned,$inOwned)as$id=>$revision)$result[self::encoded('todo',$id)]=$revision;return$result;
        };
        if(!$legacy)return$run(false);
        return IssuerTransaction::run($this->db,$this->p,array_merge(NativeCompanyAuxiliary::tableNames(),['todos']),function()use($run){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($this->issuer,$this->actor);return$run(true);});
    }
    public function allowed(string $feature,string $cap):bool{return$this->owned->allowed($feature,$cap);}
    public function announcements(int $limit=100,int $offset=0):array
    {
        if($this->legacy===null)return self::rows('announcement',$this->owned->announcements($limit,$offset));
        if($limit<1||$limit>100||$offset<0||$offset>100000)throw new DomainException('company_auxiliary_invalid_page');$rows=[];$need=$limit+$offset;
        foreach([$this->owned,$this->legacy]as$source){for($pos=0;$pos<$need;$pos+=100){$part=$source->announcements(min(100,$need-$pos),$pos);foreach($part as$row)$rows[]=$source===$this->owned?self::row('announcement',$row):$row;if(count($part)<min(100,$need-$pos))break;}}
        usort($rows,static fn($a,$b)=>strcmp($b['dateadded'],$a['dateadded'])?:((int)$b['announcementid']<=>(int)$a['announcementid']));return array_slice($rows,$offset,$limit);
    }
    public function announcement($id):array{$ref=NativeCompanyResourceId::decode('announcement',$id);if($ref['source_kind']==='announcement'&&$this->legacy!==null)return$this->legacy->announcement($ref['source_id']);return self::row('announcement',$this->owned->announcement(self::id('announcement',$id)));}
    public function announcementTable(int $start,int $length,string $search=''):array
    {
        if($this->legacy===null){$result=$this->owned->announcementTable($start,$length,$search);$result['rows']=self::rows('announcement',$result['rows']);return$result;}
        if($length<1||$length>100||$start<0||$start>100000)throw new DomainException('company_auxiliary_invalid_page');$rows=[];$need=$start+$length;$total=0;$filtered=0;
        foreach([$this->owned,$this->legacy]as$source){for($pos=0;$pos<$need;$pos+=100){$part=$source->announcementTable($pos,min(100,$need-$pos),$search);if($pos===0){$total+=$part['total'];$filtered+=$part['filtered'];}foreach($part['rows']as$row)$rows[]=$source===$this->owned?self::row('announcement',$row):$row;if(count($part['rows'])<min(100,$need-$pos))break;}}
        usort($rows,static fn($a,$b)=>strcmp($b['dateadded'],$a['dateadded'])?:((int)$b['announcementid']<=>(int)$a['announcementid']));return['total'=>$total,'filtered'=>$filtered,'rows'=>array_slice($rows,$start,$length)];
    }
    /** Original Dashboard alert partial, backed only by this actor's active company and its historical lineage. */
    public function dashboardAnnouncements(int $limit=100):array
    {
        if($limit<1||$limit>100)throw new DomainException('company_auxiliary_invalid_page');
        $rows=self::rows('announcement',$this->owned->dashboardAnnouncements($limit));
        if($this->legacy!==null)$rows=array_merge($rows,$this->legacy->dashboardAnnouncements($limit));
        usort($rows,static fn($a,$b)=>strcmp($b['dateadded'],$a['dateadded'])?:((int)$b['announcementid']<=>(int)$a['announcementid']));
        return array_slice($rows,0,$limit);
    }
    public function undismissedAnnouncementsCount():int{return$this->owned->undismissedAnnouncementsCount()+($this->legacy!==null?$this->legacy->undismissedAnnouncementsCount():0);}
    public function saveAnnouncement(array $input,$id=null,$revision=null):string
    {
        if($id!==null&&$id!==''){$ref=NativeCompanyResourceId::decode('announcement',$id);if($ref['source_kind']==='announcement'&&$this->legacy!==null){$this->legacy->mutateAnnouncement($this->ci,$ref['source_id'],$revision,$input);return(string)$id;}}
        return self::encoded('announcement',$this->owned->saveAnnouncement($input,$id===null||$id===''?null:self::id('announcement',$id),$revision));
    }
    public function dismissAnnouncement($id):void{$ref=NativeCompanyResourceId::decode('announcement',$id);if($ref['source_kind']==='announcement'&&$this->legacy!==null){$this->legacy->dismissAnnouncement($ref['source_id']);return;}$this->owned->dismissAnnouncement(self::id('announcement',$id));}
    public function deleteAnnouncement($id,$revision):void{$ref=NativeCompanyResourceId::decode('announcement',$id);if($ref['source_kind']==='announcement'&&$this->legacy!==null){$this->legacy->mutateAnnouncement($this->ci,$ref['source_id'],$revision,null);return;}$this->owned->deleteAnnouncement(self::id('announcement',$id),$revision);}

    public function remindersCount():int{return$this->owned->remindersCount()+($this->legacy!==null?$this->legacy->remindersCount():0);}
    public function activity(int $limit=30):array{$rows=$this->owned->activity($limit);if($this->legacy!==null){$rows=array_merge($rows,$this->legacy->activity($limit));usort($rows,static fn($a,$b)=>strcmp($b['date'],$a['date']));$rows=array_slice($rows,0,$limit);}return$rows;}
    public function members():array{return$this->owned->members();}
    public function notes(string $type,$id):array{$rows=self::rows('note',$this->owned->notes($type,$id));if($this->legacy!==null){$rows=array_merge($rows,$this->legacy->notes($type,$id));usort($rows,static fn($a,$b)=>strcmp($b['dateadded'],$a['dateadded'])?:((int)$b['id']<=>(int)$a['id']));}return$rows;}
    public function note($id):array{$ref=NativeCompanyResourceId::decode('note',$id);if($ref['source_kind']==='note'&&$this->legacy!==null)return$this->legacy->note($ref['source_id']);return self::row('note',$this->owned->note(self::id('note',$id)));}
    public function saveNote(string $type,$relId,array $input,$id=null,$revision=null,?string$requestKey=null,?int$parentRevision=null):string
    {
        if($id!==null){$ref=NativeCompanyResourceId::decode('note',$id);if($ref['source_kind']==='note'&&$this->legacy!==null){$row=$this->legacy->note($ref['source_id']);if($row['rel_type']!==$type||(string)$row['rel_id']!==(string)$relId)throw new DomainException('company_auxiliary_access_denied');$this->legacy->mutateRelation('note',$ref['source_id'],$revision,$input);return(string)$id;}}
        return self::encoded('note',$this->owned->saveNote($type,$relId,$input,$id===null||$id===''?null:self::id('note',$id),$revision,$requestKey,$parentRevision));
    }
    public function deleteNote($id,$revision):void{$ref=NativeCompanyResourceId::decode('note',$id);if($ref['source_kind']==='note'&&$this->legacy!==null){$this->legacy->mutateRelation('note',$ref['source_id'],$revision,null);return;}$this->owned->deleteNote(self::id('note',$id),$revision);}
    public function reminders(?string $type=null,$id=null,int $limit=100,int $offset=0):array{return$this->reminderTable($type,$id,$offset,$limit)['rows'];}
    public function reminder($id):array{$ref=NativeCompanyResourceId::decode('reminder',$id);if($ref['source_kind']==='reminder'&&$this->legacy!==null)return$this->legacy->reminder($ref['source_id']);return self::row('reminder',$this->owned->reminder(self::id('reminder',$id)));}
    public function reminderTable(?string $type,$id,int $start,int $length,string $search='',bool $pendingOnly=false,bool $allVisible=false):array
    {
        if($this->legacy===null){$result=$this->owned->reminderTable($type,$id,$start,$length,$search,$pendingOnly,$allVisible);$result['rows']=self::rows('reminder',$result['rows']);return$result;}
        if($length<1||$length>100||$start<0||$start>100000)throw new DomainException('company_auxiliary_invalid_page');$rows=[];$need=$start+$length;$total=0;$filtered=0;
        foreach([$this->owned,$this->legacy]as$source){for($pos=0;$pos<$need;$pos+=100){$part=$source->reminderTable($type,$id,$pos,min(100,$need-$pos),$search,$pendingOnly,$allVisible);if($pos===0){$total+=$part['total'];$filtered+=$part['filtered'];}foreach($part['rows']as$row)$rows[]=$source===$this->owned?self::row('reminder',$row):$row;if(count($part['rows'])<min(100,$need-$pos))break;}}
        usort($rows,static fn($a,$b)=>strcmp($a['date'],$b['date'])?:((int)$a['id']<=>(int)$b['id']));return['total'=>$total,'filtered'=>$filtered,'rows'=>array_slice($rows,$start,$length)];
    }
    public function saveReminder(string $type,$relId,array $input,$id=null,$revision=null,?string$requestKey=null,?int$parentRevision=null):string
    {
        if($id!==null){$ref=NativeCompanyResourceId::decode('reminder',$id);if($ref['source_kind']==='reminder'&&$this->legacy!==null){$row=$this->legacy->reminder($ref['source_id']);if($row['rel_type']!==$type||(string)$row['rel_id']!==(string)$relId)throw new DomainException('company_auxiliary_access_denied');$this->legacy->mutateRelation('reminder',$ref['source_id'],$revision,$input);return(string)$id;}}
        return self::encoded('reminder',$this->owned->saveReminder($type,$relId,$input,$id===null||$id===''?null:self::id('reminder',$id),$revision,$requestKey,$parentRevision));
    }
    public function deleteReminder($id,$revision):void{$ref=NativeCompanyResourceId::decode('reminder',$id);if($ref['source_kind']==='reminder'&&$this->legacy!==null){$this->legacy->mutateRelation('reminder',$ref['source_id'],$revision,null);return;}$this->owned->deleteReminder(self::id('reminder',$id),$revision);}
}
