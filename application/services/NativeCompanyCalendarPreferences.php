<?php
declare(strict_types=1);
require_once __DIR__.'/CompanyCalendarPreferences.php';
require_once __DIR__.'/CompanySettingsStorage.php';
require_once __DIR__.'/CompanySettingsCommitGuard.php';
/** Adapter for the existing Settings/calendar fields. No parallel form or global named fallback. */
final class NativeCompanyCalendarPreferences
{
    public const REMINDERS=['lead','customer','estimate','invoice','proposal','expense','task','credit_note','ticket'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public static function defaults():array
    {
        $v=CompanyCalendarPreferences::defaults();unset($v['calendar_task_color']);
        $v['hide_notified_reminders_from_calendar']='1';foreach(self::REMINDERS as$type)$v['show_'.$type.'_reminders_on_calendar']='1';$v['calendar_reminder_color']='#03A9F4';return$v;
    }
    public static function prepare(array $values):array
    {
        $keys=array_keys($values);$expected=array_keys(self::defaults());sort($keys);sort($expected);if($keys!==$expected)throw new DomainException('native_calendar_preferences_invalid');
        $base=CompanyCalendarPreferences::defaults();foreach($base as$key=>$value)if(array_key_exists($key,$values))$base[$key]=$values[$key];$base=CompanyCalendarPreferences::prepare($base);
        $result=[];foreach(self::defaults()as$key=>$default){$v=$values[$key];if(!is_string($v))throw new DomainException('native_calendar_preferences_invalid');if(array_key_exists($key,$base))$result[$key]=$base[$key];elseif($key==='calendar_reminder_color'){if(!preg_match('/^#[a-fA-F0-9]{6}$/D',$v))throw new DomainException('native_calendar_preferences_invalid');$result[$key]=strtoupper($v);}else{if(!in_array($v,['0','1'],true))throw new DomainException('native_calendar_preferences_invalid');$result[$key]=$v;}}return$result;
    }
    public function tables():array{return array_merge((new CompanyCalendarPreferences($this->db,$this->p))->tables(),['options']);}
    private function authorize(int $i,int $a,bool $write=false):void{CompanyCalendarPreferences::authorize($this->db,$this->p,$i,$a,$write);}
    public function valuesInOwnedTransaction(int $i,int $a):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$this->authorize($i,$a);$acl=new IssuerResourceAccess($this->db,$this->p);$primary=$acl->primaryId();$store=new CompanySettingsStorage($this->db,$this->p);$snapshot=CompanyCalendarPreferences::readInOwnedTransaction($this->db,$this->p,$i);$values=[];
        foreach(self::defaults()as$key=>$default){
            if($primary===$i){$rows=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'options WHERE name=?',[$key])->result_array();if(count($rows)!==1)throw new DomainException('native_calendar_preferences_invalid');$values[$key]=$rows[0]['value'];}
            elseif(array_key_exists($key,$snapshot['values']))$values[$key]=$snapshot['values'][$key];
            else $values[$key]=$store->exists($i,$key)?$store->get($i,$key):$default;
        }
        $values=self::prepare($values);$this->authorize($i,$a);if($primary!==$acl->primaryId())throw new DomainException('native_calendar_preferences_invalid');
        return ['values'=>$values,'revision'=>hash('sha256',json_encode([$i,$primary,$values,$snapshot],JSON_THROW_ON_ERROR)),'calendar_revision'=>$snapshot['revision'],'calendar_values'=>$snapshot['values']];
    }
    public function page(int $i,int $a):array
    {
        return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$first=$this->valuesInOwnedTransaction($i,$a);if($first!==$this->valuesInOwnedTransaction($i,$a))throw new DomainException('native_calendar_preferences_stale');return['values'=>$first['values'],'revision'=>$first['revision']];});
    }
    public function saveInOwnedTransaction(int $i,int $a,string $revision,array $values):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());$values=self::prepare($values);$this->authorize($i,$a,true);$old=$this->valuesInOwnedTransaction($i,$a);if(!preg_match('/^[a-f0-9]{64}$/D',$revision)||!hash_equals($old['revision'],$revision))throw new DomainException('native_calendar_preferences_stale');if($values===$old['values'])return;
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$i){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$i,$a,'edit');foreach($values as$key=>$value)if($value!==$old['values'][$key])$guard->writeOption('update',$key,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();}
        else{
            $calendar=$old['calendar_values'];foreach($calendar as$key=>$value)if(array_key_exists($key,$values))$calendar[$key]=$values[$key];
            (new CompanyCalendarPreferences($this->db,$this->p))->saveInOwnedTransaction($i,$a,$old['calendar_revision'],$calendar);
            $extra=array_diff_key($values,$calendar);(new CompanySettingsStorage($this->db,$this->p))->updateBatchInOwnedTransaction($i,$a,$extra);
        }
        $new=$this->valuesInOwnedTransaction($i,$a);if($new['values']!==$values)throw new RuntimeException('native_calendar_preferences_unconfirmed');$this->authorize($i,$a,true);
    }
}
