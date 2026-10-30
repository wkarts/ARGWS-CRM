<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyTaskTimerDetailsSchema.php';
require_once __DIR__.'/NativeCompanyStaffRates.php';
/** Snapshots never reinterpret a historical timer using today's staff rate. */
final class NativeCompanyTaskTimerDetails
{
    private const SOURCES=['company_task_timer'=>'company_task_timers','company_standalone_timer'=>'company_standalone_timers','company_blank_task_timer'=>'company_blank_task_timers'];
    public function __construct(private$db,private string$p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return array_values(array_unique(array_merge(['company_task_timer_details'],array_values(self::SOURCES),(new NativeCompanyStaffRates($this->db,$this->p))->tables())));}
    private function source(int$i,string$kind,int$id):array
    {
        $table=self::SOURCES[$kind]??throw new DomainException('native_timer_source_invalid');$row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$table.' WHERE issuer_id=? AND id=?',[$i,$id])->row_array();if(!$row)throw new DomainException('native_timer_source_denied');return$row;
    }
    private static function rate(array$rate):array
    {
        if(array_keys($rate)!==['hourly_rate','revision','source_kind','source_id']||!in_array($rate['source_kind'],['company_staff_rate','unconfirmed','staff'],true)||NativeCompanyStaffRates::normalize($rate['hourly_rate'])!==$rate['hourly_rate'])throw new RuntimeException('native_timer_snapshot_invalid');IssuerPolicy::id($rate['source_id']);
        if($rate['source_kind']==='staff'){if(!is_string($rate['revision'])||!preg_match('/^[a-f0-9]{64}$/D',$rate['revision']))throw new RuntimeException('native_timer_snapshot_invalid');}
        elseif(!is_int($rate['revision'])||($rate['source_kind']==='unconfirmed'?($rate['revision']!==0||$rate['hourly_rate']!==null):$rate['revision']<1))throw new RuntimeException('native_timer_snapshot_invalid');return$rate;
    }
    private function row(int$i,int$subject,string$kind,int$id):array
    {
        NativeCompanyTaskTimerDetailsSchema::assertValid($this->db,$this->p);$source=$this->source($i,$kind,$id);if((int)$source['actor_id']!==$subject)throw new DomainException('native_timer_source_denied');
        $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_task_timer_details WHERE source_kind=? AND timer_id=?',[$kind,$id])->row_array();
        if(!$row)return['hourly_rate'=>null,'note'=>$kind==='company_blank_task_timer'?$source['note']:null,'revision'=>0,'rate_basis'=>null,'declared'=>false];
        if((int)$row['issuer_id']!==$i||(int)$row['actor_id']!==$subject||(int)$row['revision']<1)throw new DomainException('native_timer_source_denied');$rate=$row['rate_snapshot_json']===null?null:self::rate(json_decode($row['rate_snapshot_json'],true,16,JSON_THROW_ON_ERROR));if($rate!==null&&(int)$rate['source_id']!==$subject)throw new RuntimeException('native_timer_snapshot_invalid');
        if($kind==='company_blank_task_timer'&&$row['note']!==null)throw new RuntimeException('native_timer_snapshot_invalid');return['hourly_rate'=>$rate['hourly_rate']??null,'note'=>$kind==='company_blank_task_timer'?$source['note']:$row['note'],'revision'=>(int)$row['revision'],'rate_basis'=>$rate,'declared'=>true];
    }
    public function forStaffTimesheet(int$i,int$a,int$subject,string$kind,int$id):array
    {
        foreach([$i,$a,$subject,$id]as$value)IssuerPolicy::id($value);$acl=new IssuerResourceAccess($this->db,$this->p);$authorize=function()use($acl,$i,$a,$subject):void{if(!$acl->member($i,$a)||!$acl->can($i,$a,'staff','view'))throw new DomainException('native_timer_staff_denied');require_once __DIR__.'/CompanyStaff.php';(new CompanyStaff($this->db,$this->p))->assertAdministrativeTarget($i,$a,$subject);};$authorize();$source=$this->source($i,$kind,$id);$result=$this->row($i,$subject,$kind,$id);$authorize();if($this->source($i,$kind,$id)!==$source||$this->row($i,$subject,$kind,$id)!==$result)throw new DomainException('native_timer_snapshot_changed');return$result;
    }
    /** Own timesheets are an actor-private read, not an implicit staff/tasks grant. */
    public function forOwnTimesheet(int$i,int$a,string$kind,int$id):array
    {
        foreach([$i,$a,$id]as$value)IssuerPolicy::id($value);$acl=new IssuerResourceAccess($this->db,$this->p);$authorize=function()use($acl,$i,$a):void{if(!$acl->member($i,$a))throw new DomainException('native_timer_source_denied');};$authorize();$source=$this->source($i,$kind,$id);$result=$this->row($i,$a,$kind,$id);$authorize();if($this->source($i,$kind,$id)!==$source||$this->row($i,$a,$kind,$id)!==$result)throw new DomainException('native_timer_snapshot_changed');return$result;
    }
    /** Note-only report projection: deliberately never SELECT rate_snapshot_json. */
    public function forReport(int $i,int $a,int $subject,string $kind,int $id):array
    {
        require_once __DIR__.'/NativeCompanyTimesheetReportAuthority.php';
        $authority=new NativeCompanyTimesheetReportAuthority($this->db,$this->p);
        $authority->assertTarget($i,$a,$subject);IssuerPolicy::id($id);
        NativeCompanyTaskTimerDetailsSchema::assertValid($this->db,$this->p);
        $read=function()use($i,$subject,$kind,$id):array{
            $source=$this->source($i,$kind,$id);if((int)$source['actor_id']!==$subject)throw new DomainException('native_timer_source_denied');
            $row=IssuerStorage::query($this->db,'SELECT issuer_id,actor_id,note,revision FROM '.$this->p.'company_task_timer_details WHERE source_kind=? AND timer_id=?',[$kind,$id])->row_array();
            if($row&&((int)$row['issuer_id']!==$i||(int)$row['actor_id']!==$subject||(int)$row['revision']<1))throw new DomainException('native_timer_source_denied');
            if($kind==='company_blank_task_timer'&&$row&&$row['note']!==null)throw new RuntimeException('native_timer_snapshot_invalid');
            return ['source'=>$source,'note'=>$kind==='company_blank_task_timer'?$source['note']:($row['note']??null),'revision'=>$row?(int)$row['revision']:0,'declared'=>(bool)$row];
        };
        $before=$read();$authority->assertTarget($i,$a,$subject);if($read()!==$before)throw new DomainException('native_timer_snapshot_changed');$authority->assertTarget($i,$a,$subject);unset($before['source']);return $before;
    }
    public function ownInOwned(int$i,int$a,string$kind,int$id):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$acl=new IssuerResourceAccess($this->db,$this->p);if(!$acl->can($i,$a,'tasks','view'))throw new DomainException('native_timer_source_denied');return$this->row($i,$a,$kind,$id);
    }
    private function prove(object$proof,int$i,int$a,string$kind,int$id,?int$origin=null):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());NativeCompanyTaskTimerDetailsSchema::assertValid($this->db,$this->p);$row=$this->source($i,$kind,$id);if((int)$row['actor_id']!==$a)throw new DomainException('native_timer_source_denied');$class=match($kind){'company_task_timer'=>CompanyTasks::class,'company_standalone_timer'=>CompanyStandaloneTasks::class,'company_blank_task_timer'=>NativeCompanyBlankTimers::class,default=>throw new DomainException('native_timer_source_invalid')};$class::assertNativeTimerCreationProof($proof,$this->db,$this->p,$i,$a,$kind,$id,$row,$origin);return$row;
    }
    private function insert(int$i,int$a,string$kind,int$id,?array$rate,?string$note):void
    {
        if($rate!==null)$rate=self::rate($rate);if($note!==null&&(strlen($note)>4000||!mb_check_encoding($note,'UTF-8')))throw new DomainException('native_timer_note_invalid');$json=$rate===null?null:json_encode($rate,JSON_THROW_ON_ERROR);$expectedNote=$kind==='company_blank_task_timer'?$this->source($i,$kind,$id)['note']:$note;if($kind==='company_blank_task_timer')$note=null;
        IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_task_timer_details(source_kind,timer_id,issuer_id,actor_id,rate_snapshot_json,note,revision) VALUES(?,?,?,?,?,?,1)',[$kind,$id,$i,$a,$json,$note]);if(method_exists($this->db,'affected_rows')&&(int)$this->db->affected_rows()!==1)throw new RuntimeException('native_timer_snapshot_unconfirmed');$after=$this->row($i,$a,$kind,$id);if($after!==['hourly_rate'=>$rate['hourly_rate']??null,'note'=>$expectedNote,'revision'=>1,'rate_basis'=>$rate,'declared'=>true])throw new RuntimeException('native_timer_snapshot_unconfirmed');
    }
    public function captureForStaffInOwned(int $issuer,int $actor,int $subject,string $kind,int $id,object $proof,string $note):void
    {
        $this->prove($proof,$issuer,$subject,$kind,$id);$rates=new NativeCompanyStaffRates($this->db,$this->p);
        $read=fn()=>$actor===$subject?$rates->forOwnTimerInOwned($issuer,$actor):$rates->forStaff($issuer,$actor,$subject);
        $rate=$read();$this->insert($issuer,$subject,$kind,$id,$rate,$note);if($read()!==$rate)throw new DomainException('native_staff_rate_stale');
    }
    public function captureNewInOwned(int$i,int$a,string$kind,int$id,object$proof,string$note):void
    {
        $this->prove($proof,$i,$a,$kind,$id);$rates=new NativeCompanyStaffRates($this->db,$this->p);$rate=$rates->forOwnTimerInOwned($i,$a);$this->insert($i,$a,$kind,$id,$rate,$note);if($rates->forOwnTimerInOwned($i,$a)!==$rate)throw new DomainException('native_staff_rate_stale');
    }
    public function captureTransferInOwned(int$i,int$a,string$kind,int$id,object$proof,int$blank,string$note):void
    {
        $this->prove($proof,$i,$a,$kind,$id,$blank);$source=$this->source($i,'company_blank_task_timer',$blank);if((int)$source['actor_id']!==$a||$source['state']!=='active')throw new DomainException('native_timer_source_denied');$snapshot=$this->row($i,$a,'company_blank_task_timer',$blank);$this->insert($i,$a,$kind,$id,$snapshot['rate_basis'],$note);
    }
    /** The canonical stop/edit writer has already proved task, actor and billing mutability. */
    public function saveNoteInOwned(int$i,int$a,string$kind,int$id,int$revision,string$note,object$proof):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$source=$this->source($i,$kind,$id);$class=match($kind){'company_task_timer'=>CompanyTasks::class,'company_standalone_timer'=>CompanyStandaloneTasks::class,default=>throw new DomainException('native_timer_source_invalid')};$class::assertNativeTimerEditProof($proof,$this->db,$this->p,$i,$a,$kind,$id,$source);$before=$this->row($i,$a,$kind,$id);
        if($revision<0||$revision>=2147483647||$before['revision']!==$revision)throw new DomainException('native_timer_note_stale');if(strlen($note)>4000||!mb_check_encoding($note,'UTF-8')||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$note))throw new DomainException('native_timer_note_invalid');
        if(!$before['declared']){$this->insert($i,$a,$kind,$id,null,$note);return;}
        if($before['note']===$note)return;
        IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_task_timer_details SET note=?,revision=revision+1 WHERE source_kind=? AND timer_id=? AND issuer_id=? AND actor_id=? AND revision=?',[$note,$kind,$id,$i,$a,$revision]);$expected=$before;$expected['note']=$note;$expected['revision']=$revision+1;if($this->row($i,$a,$kind,$id)!==$expected)throw new RuntimeException('native_timer_snapshot_unconfirmed');
    }
}
