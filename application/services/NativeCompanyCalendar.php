<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyEventSchema.php';
require_once __DIR__.'/NativeCompanyResourceId.php';
require_once __DIR__.'/CompanyCalendarPreferences.php';

/** Native event contracts with issuer-bound reads; no provider requests during hydration. */
final class NativeCompanyCalendar
{
    public function __construct(private $db,private string $p)
    {
        if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');
    }
    private function tables(int $issuer):array
    {
        $t=(new CompanyCalendarPreferences($this->db,$this->p))->tables();$t[]='company_events';
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$issuer)$t[]='events';
        return$t;
    }
    private function read(int $issuer,int $actor,DateTimeImmutable $from,DateTimeImmutable $until):array
    {
        CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);
        NativeCompanyEventSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$this->tables($issuer),function()use($issuer,$actor,$from,$until){
            $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
            CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);
            $args=[$issuer,$actor,$from->format('Y-m-d H:i:s'),$until->format('Y-m-d H:i:s')];
            $rows=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_events WHERE issuer_id=? AND (userid=? OR public=1) AND start>=? AND start<? ORDER BY start,eventid FOR UPDATE',$args)->result_array();
            foreach($rows as&$r){$r['eventid']=NativeCompanyResourceId::encode('company_event',$r['eventid']);$r['source_kind']='company_event';}unset($r);
            if($acl->primaryId()===$issuer){
                // The historical table is a distinct lineage, visible only to the proven principal.
                $legacy=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'events WHERE (userid=? OR public=1) AND start>=? AND start<? ORDER BY start,eventid FOR UPDATE',[$actor,$args[2],$args[3]])->result_array();
                foreach($legacy as$r){$r['eventid']=NativeCompanyResourceId::encode('event',$r['eventid']);$r['source_kind']='event';$rows[]=$r;}
            }
            CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);
            usort($rows,static fn(array$a,array$b)=>[$a['start'],(int)$a['eventid']]<=>[$b['start'],(int)$b['eventid']]);
            return$rows;
        });
    }
    public function range(int $issuer,int $actor,DateTimeImmutable $from,DateTimeImmutable $until):array
    {
        if($until<=$from||$from->diff($until)->days>63)throw new DomainException('native_calendar_range_invalid');
        $rows=$this->read($issuer,$actor,$from,$until);if(count($rows)>500)throw new DomainException('native_calendar_range_too_large');return$rows;
    }
    public static function dashboard($db,string $p,int $issuer,int $actor,?DateTimeImmutable $clock=null):array
    {
        $self=new self($db,$p);$clock??=new DateTimeImmutable('now');
        $monday=$clock->modify('monday this week')->setTime(0,0);$next=$monday->modify('+7 days');
        $events=$self->read($issuer,$actor,$monday,$next);$following=$self->read($issuer,$actor,$next,$next->modify('+7 days'));
        // Native dashboard retains its six-event display and chronological convention.
        usort($events,static fn(array$a,array$b)=>[$b['start'],(int)$b['eventid']]<=>[$a['start'],(int)$a['eventid']]);
        return['upcoming_events'=>array_slice($events,0,6),'upcoming_events_next_week'=>count($following)];
    }
    public function event(int $issuer,int $actor,$canonicalId):array
    {
        $ref=NativeCompanyResourceId::decode('event',$canonicalId);
        CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);
        NativeCompanyEventSchema::assertValid($this->db,$this->p);
        return IssuerTransaction::run($this->db,$this->p,$this->tables($issuer),function()use($issuer,$actor,$ref){
            $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($issuer,$actor);
            CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);
            if($ref['source_kind']==='event'){
                if($acl->primaryId()!==$issuer)throw new DomainException('native_event_denied');
                $table='events';$where='eventid=?';$args=[$ref['source_id']];
            }else{$table='company_events';$where='eventid=? AND issuer_id=?';$args=[$ref['source_id'],$issuer];}
            $args[]=$actor;
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.$table.' WHERE '.$where.' AND (userid=? OR public=1) FOR UPDATE',$args)->row_array();
            if(!$row)throw new DomainException('native_event_denied');
            $row['eventid']=$ref['canonical_id'];$row['source_kind']=$ref['source_kind'];
            CompanyCalendarPreferences::authorize($this->db,$this->p,$issuer,$actor);
            return$row;
        });
    }
}
