<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyEventMail.php';require_once __DIR__.'/NativeCompanyNotifications.php';
/** Original calendar reminder job, explicitly enumerated issuers; never SMTP inside transaction. */
final class NativeCompanyEventWorker
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function run(string $now,string $base,callable $decrypt):array
    {
        if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active())throw new DomainException('native_event_worker_context_denied');$clock=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$now,new DateTimeZone('UTC'));if(!$clock||$clock->format('Y-m-d H:i:s')!==$now)throw new InvalidArgumentException('Invalid clock');
        NativeCompanyEventSchema::assertValid($this->db,$this->p);NativeCompanyEventMailSchema::assertValid($this->db,$this->p);$ceiling=(int)(IssuerStorage::query($this->db,'SELECT MAX(eventid) AS max_id FROM '.$this->p.'company_events')->row_array()['max_id']??0);$cursor=0;$result=['notified'=>0,'deferred'=>0];
        do{$rows=IssuerStorage::query($this->db,'SELECT eventid,issuer_id,userid FROM '.$this->p.'company_events WHERE isstartnotified=0 AND eventid>? AND eventid<=? ORDER BY eventid LIMIT 500',[$cursor,$ceiling])->result_array();
        foreach($rows as$r){$cursor=(int)$r['eventid'];try{if($this->process((int)$r['issuer_id'],(int)$r['eventid'],(int)$r['userid'],$clock,$base,$decrypt))$result['notified']++;}catch(DomainException|RuntimeException $e){$result['deferred']++;}}}while(count($rows)===500);return$result;
    }
    private function process(int $i,int $id,int $actor,DateTimeImmutable $clock,string $base,callable $decrypt):bool
    {
        $mail=new NativeCompanyEventMail($this->db,$this->p);$tables=array_values(array_unique(array_merge($mail->tables(),NativeCompanyNotifications::tables('event'))));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($i,$id,$actor,$clock,$base,$decrypt,$mail){
            $acl=new IssuerResourceAccess($this->db,$this->p);$acl->lockFinancialIdentity($i,$actor);if(!$acl->can($i,$actor,'calendar','view'))throw new DomainException('native_event_mail_denied');
            $r=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_events WHERE issuer_id=? AND eventid=? FOR UPDATE',[$i,$id])->row_array();if(!$r||(int)$r['isstartnotified']!==0)return false;if((int)$r['userid']!==$actor)throw new DomainException('native_event_mail_stale');
            $timezone=(new CompanySettingsStorage($this->db,$this->p))->get($i,'default_timezone');if(!in_array($timezone,DateTimeZone::listIdentifiers(),true)||!in_array($r['reminder_before_type'],['minutes','hours','days','weeks'],true)||!preg_match('/^(?:0|[1-9][0-9]{0,8})$/D',(string)$r['reminder_before']))throw new DomainException('native_event_mail_denied');
            $threshold=$clock->setTimezone(new DateTimeZone($timezone))->modify('+'.$r['reminder_before'].' '.$r['reminder_before_type']);if($r['start']>$threshold->format('Y-m-d H:i:s'))return false;
            $recipients=[$actor];if((int)$r['public']===1){$candidates=IssuerStorage::query($this->db,'SELECT s.staffid FROM '.$this->p.'staff s WHERE s.active=1 AND s.is_not_staff=0 AND (EXISTS(SELECT 1 FROM '.$this->p.'issuers_staff m WHERE m.issuer_id=? AND m.staff_id=s.staffid AND m.can_view=1) OR EXISTS(SELECT 1 FROM '.$this->p.'issuers_installation_identity n WHERE n.id=1 AND n.master_staff_id=s.staffid)) ORDER BY s.staffid',[$i])->result_array();foreach($candidates as$c)if($acl->can($i,(int)$c['staffid'],'calendar','view'))$recipients[]=(int)$c['staffid'];}
            $notifications=new NativeCompanyNotifications($this->db,$this->p);foreach(array_unique($recipients)as$recipient){$mail->queueInOwned($i,$id,$recipient,$base,$decrypt);$notifications->recordInOwnedTransaction($i,$actor,$recipient,'event',$id);}
            if(!$acl->can($i,$actor,'calendar','view'))throw new DomainException('native_event_mail_denied');IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_events SET isstartnotified=1 WHERE issuer_id=? AND eventid=? AND revision=? AND isstartnotified=0',[$i,$id,$r['revision']]);$saved=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_events WHERE issuer_id=? AND eventid=?',[$i,$id])->row_array();$r['isstartnotified']=1;foreach($r as$k=>$v)if((string)($saved[$k]??'')!==(string)$v)throw new RuntimeException('native_event_worker_unconfirmed');return true;
        });
    }
}
