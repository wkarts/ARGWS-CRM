<?php

declare(strict_types=1);
require_once __DIR__.'/NativeCompanyReminderDelivery.php';
require_once __DIR__.'/NativeCompanyNotifications.php';

/** Cron enumerates persisted issuers. It never borrows an interactive context or sends SMTP. */
final class NativeCompanyReminderWorker
{
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function run(string $now,string $baseUrl,callable $decrypt):array
    {
        if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active())throw new DomainException('native_reminder_worker_context_denied');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$now,new DateTimeZone('UTC'));if(!$date||$date->format('Y-m-d H:i:s')!==$now)throw new InvalidArgumentException('Invalid clock');
        NativeCompanyAuxiliarySchema::assertValid($this->db,$this->p);NativeCompanyMailDispatchFence::assertValid($this->db,$this->p);
        $rows=IssuerStorage::query($this->db,'SELECT id,issuer_id,creator FROM '.$this->p.'company_reminders WHERE isnotified=0 AND date<=? ORDER BY date,id LIMIT 500',[$now])->result_array();
        $result=['notified'=>0,'deferred'=>0];
        foreach($rows as$row){
            try{if($this->process((int)$row['issuer_id'],(int)$row['id'],(int)$row['creator'],$now,$baseUrl,$decrypt))$result['notified']++;}
            catch(DomainException|RuntimeException $error){$result['deferred']++;} // No provider error, address, text or secret is logged.
        }
        return$result;
    }
    private function process(int $issuer,int $id,int $creator,string $now,string $baseUrl,callable $decrypt):bool
    {
        $tables=array_values(array_unique(array_merge(NativeCompanyReminderDelivery::tables(),NativeCompanyReminderDelivery::relationTables($this->db,$this->p,$issuer,$id),NativeCompanyNotifications::tables('reminder'))));
        return IssuerTransaction::run($this->db,$this->p,$tables,function()use($issuer,$id,$creator,$now,$baseUrl,$decrypt){
            (new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($issuer,$creator);
            $row=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=? FOR UPDATE',[$issuer,$id])->row_array();
            if(!$row||(int)$row['isnotified']!==0||$row['date']>$now)return false;
            if((int)$row['creator']!==$creator)throw new DomainException('native_reminder_delivery_stale');
            (new NativeCompanyReminderDelivery($this->db,$this->p))->queueInOwned($issuer,$id,$baseUrl,$decrypt);
            (new NativeCompanyNotifications($this->db,$this->p))->recordInOwnedTransaction($issuer,$creator,(int)$row['staff'],'reminder',$id);
            IssuerStorage::query($this->db,'UPDATE '.$this->p.'company_reminders SET isnotified=1 WHERE issuer_id=? AND id=? AND revision=? AND isnotified=0',[$issuer,$id,$row['revision']]);
            $check=IssuerStorage::query($this->db,'SELECT * FROM '.$this->p.'company_reminders WHERE issuer_id=? AND id=?',[$issuer,$id])->row_array();
            $expected=$row;$expected['isnotified']=1;if(!$check)throw new RuntimeException('native_reminder_write_unconfirmed');foreach($expected as$key=>$value)if((string)$check[$key]!==(string)$value)throw new RuntimeException('native_reminder_write_unconfirmed');
            return true;
        });
    }
}
