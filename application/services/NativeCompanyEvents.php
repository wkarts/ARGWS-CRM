<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCalendar.php';
/** Existing native event mutations, with explicit owner and stale-form boundaries. */
final class NativeCompanyEvents
{
    private function assertInserted():void
    {
        $connection=$this->db->conn_id??$this->db->pdo??null;
        $sqlite=$connection instanceof PDO&&$connection->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
        $row=IssuerStorage::query($this->db,$sqlite?'SELECT changes() AS affected':'SELECT ROW_COUNT() AS affected')->row_array();
        if((string)($row['affected']??'')!=='1')throw new RuntimeException('native_source_insert_unconfirmed');
    }

    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables(int $issuer):array{$tables=(new CompanyCalendarPreferences($this->db,$this->p))->tables();$tables[]='company_events';if((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer)$tables[]='events';return$tables;}
    private function authorize(int $issuer,int $actor):void{CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);}
    private function transaction(int $issuer,int $actor,callable $work){$this->authorize($issuer,$actor);NativeCompanyEventSchema::assertValid($this->db,$this->p);return IssuerTransaction::run($this->db,$this->p,$this->tables($issuer),function()use($issuer,$actor,$work){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$actor);$this->authorize($issuer,$actor);$result=$work();$this->authorize($issuer,$actor);return$result;});}
    public static function prepare(array $input):array
    {
        $keys=['title','description','start','end','public','color','reminder_before','reminder_before_type'];if(array_diff(array_keys($input),$keys)||array_diff($keys,array_keys($input)))throw new DomainException('native_event_invalid');
        foreach(['title'=>191,'description'=>65535,'start'=>19,'end'=>19,'color'=>7,'reminder_before_type'=>7]as$key=>$max){$value=$input[$key];if(!is_string($value)||strlen($value)>$max||preg_match('//u',$value)!==1||preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/',$value))throw new DomainException('native_event_invalid');}
        if(trim($input['title'])===''||!preg_match('/^#[a-fA-F0-9]{6}$/D',$input['color'])||!in_array($input['public'],[0,1,'0','1'],true)||!in_array($input['reminder_before_type'],['minutes','hours','days','weeks'],true))throw new DomainException('native_event_invalid');
        foreach(['start','end']as$key){if($key==='end'&&$input[$key]==='')continue;$date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$input[$key]);if(!$date||$date->format('Y-m-d H:i:s')!==$input[$key])throw new DomainException('native_event_invalid');}
        if($input['end']!==''&&$input['end']<$input['start'])throw new DomainException('native_event_invalid');
        $reminder=$input['reminder_before'];if((!is_string($reminder)&&!is_int($reminder))||!preg_match('/^(?:0|[1-9][0-9]{0,8})$/D',(string)$reminder))throw new DomainException('native_event_invalid');
        $input['reminder_before']=(int)$reminder;$input['public']=(int)$input['public'];$input['end']=$input['end']===''?null:$input['end'];return$input;
    }
    private function ownedRow(int $issuer,int $actor,$id):array
    {
        $ref=NativeCompanyResourceId::decode('event',$id);$acl=new IssuerResourceAccess($this->db,$this->p);
        if($ref['source_kind']==='event'){if($acl->primaryId()!==$issuer)throw new DomainException('native_event_denied');$table='events';$where='eventid=?';$args=[$ref['source_id']];}
        else{$table='company_events';$where='eventid=? AND issuer_id=?';$args=[$ref['source_id'],$issuer];}
        $meta=IssuerStorage::query($this->db,'SELECT userid FROM '.$this->p.$table.' WHERE '.$where.' FOR UPDATE',$args)->row_array();
        if(!$meta||((int)$meta['userid']!==$actor&&!$acl->administrative($issuer,$actor)))throw new DomainException('native_event_denied');
        $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$table.' WHERE '.$where.' FOR UPDATE',$args)->row_array();if(!$row)throw new DomainException('native_event_denied');
        return[$ref,$table,$where,$args,$row];
    }
    public static function revision(array $row,string $source):string{return$source==='company_event'?(string)$row['revision']:hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));}
    private function audit(int $issuer,int $actor,int $id,string $event):void
    {
        $now=gmdate('Y-m-d H:i:s');$receipt=json_encode(['receipt'=>bin2hex(random_bytes(16))],JSON_THROW_ON_ERROR);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'issuers_audit(issuer_id,actor_id,subject_id,event,created_at,metadata_json) VALUES(?,?,?,?,?,?)',[$issuer,$actor,$id,$event,$now,$receipt]);$audit=(int)$this->db->insert_id();
        if(!IssuerStorage::query($this->db,'SELECT id FROM '.$this->p.'issuers_audit WHERE id=? AND issuer_id=? AND actor_id=? AND subject_id=? AND event=? AND created_at=? AND metadata_json=?',[$audit,$issuer,$actor,$id,$event,$now,$receipt])->row_array())throw new RuntimeException('native_event_audit_unconfirmed');
    }
    public function save(int $issuer,int $actor,array $input,$id=null,?string $revision=null):array
    {
        $input=self::prepare($input);
        return$this->transaction($issuer,$actor,function()use($issuer,$actor,$input,$id,$revision){
            if($id===null){$columns=array_keys($input);IssuerStorage::query($this->db,'INSERT INTO '.$this->p.'company_events(issuer_id,userid,'.implode(',',$columns).',isstartnotified,revision) VALUES(?,?,' . implode(',',array_fill(0,count($columns),'?')).',0,1)',array_merge([$issuer,$actor],array_values($input)));$this->assertInserted();$id=NativeCompanyResourceId::encode('company_event',$this->db->insert_id());$next=1;}
            else{[$ref,$table,$where,$args,$old]=$this->ownedRow($issuer,$actor,$id);if($revision===null||!hash_equals(self::revision($old,$ref['source_kind']),$revision))throw new DomainException('native_event_stale');
                $next=$ref['source_kind']==='company_event'?(int)$old['revision']+1:null;if($next!==null&&$next>2147483647)throw new DomainException('native_event_revision_overflow');
                $sets=[];foreach(array_keys($input)as$key)$sets[]=$key.'=?';$values=array_values($input);if((int)$old['isstartnotified']===1&&$input['start']>$old['start'])$sets[]='isstartnotified=0';if($next!==null){$sets[]='revision=?';$values[]=$next;$where.=' AND revision=?';$args[]=$old['revision'];}
                IssuerStorage::query($this->db,'UPDATE '.$this->p.$table.' SET '.implode(',',$sets).' WHERE '.$where,array_merge($values,$args));
            }
            [$ref,$table,$where,$args,$row]=$this->ownedRow($issuer,$actor,$id);foreach($input as$key=>$value)if($row[$key]!==$value&&(string)$row[$key]!== (string)$value)throw new RuntimeException('native_event_write_unconfirmed');if($next!==null&&(int)$row['revision']!==$next)throw new RuntimeException('native_event_write_unconfirmed');
            $this->audit($issuer,$actor,$ref['source_id'],'native_event_saved');return['eventid'=>$ref['canonical_id'],'revision'=>self::revision($row,$ref['source_kind'])];
        });
    }
    public function delete(int $issuer,int $actor,$id,string $revision):void
    {
        $this->transaction($issuer,$actor,function()use($issuer,$actor,$id,$revision){[$ref,$table,$where,$args,$row]=$this->ownedRow($issuer,$actor,$id);if(!hash_equals(self::revision($row,$ref['source_kind']),$revision))throw new DomainException('native_event_stale');IssuerStorage::query($this->db,'DELETE FROM '.$this->p.$table.' WHERE '.$where,$args);if(IssuerStorage::query($this->db,'SELECT eventid FROM '.$this->p.$table.' WHERE '.$where,$args)->row_array())throw new RuntimeException('native_event_write_unconfirmed');$this->audit($issuer,$actor,$ref['source_id'],'native_event_deleted');});
    }
}
