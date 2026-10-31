<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCalendarPreferences.php';
require_once __DIR__.'/NativeCompanyDepartments.php';
/** Existing Google calendar settings; the browser-restricted API key is never inherited across issuers. */
final class NativeCompanyGoogleCalendarPreferences
{
    public const KEYS=['google_api_key','google_calendar_main_calendar'];
    public function __construct(private $db,private string $p){if(!preg_match('/^[A-Za-z0-9_]+$/D',$p))throw new InvalidArgumentException('Invalid prefix');}
    public function tables():array{return(new NativeCompanyCalendarPreferences($this->db,$this->p))->tables();}
    public static function prepare(array $values):array
    {
        $keys=array_keys($values);sort($keys);$expected=self::KEYS;sort($expected);if($keys!==$expected)throw new DomainException('native_calendar_google_invalid');
        foreach($values as$key=>$value)if(!is_string($value)||strlen($value)>65535||preg_match('/[^\x21-\x7e]/',$value))throw new DomainException('native_calendar_google_invalid');return$values;
    }
    public function valuesInOwnedTransaction(int $i,int $a):array
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());CompanyCalendarPreferences::authorize($this->db,$this->p,$i,$a);$acl=new IssuerResourceAccess($this->db,$this->p);$primary=$acl->primaryId();$storage=new CompanySettingsStorage($this->db,$this->p);$values=[];
        foreach(self::KEYS as$key){if($primary===$i){$rows=IssuerStorage::query($this->db,'SELECT value FROM '.$this->p.'options WHERE name=?',[$key])->result_array();if(count($rows)!==1)throw new DomainException('native_calendar_google_invalid');$values[$key]=$rows[0]['value'];}else $values[$key]=$storage->exists($i,$key)?$storage->get($i,$key):'';}
        $values=self::prepare($values);CompanyCalendarPreferences::authorize($this->db,$this->p,$i,$a);if($primary!==$acl->primaryId())throw new DomainException('native_calendar_google_invalid');return['values'=>$values,'revision'=>hash('sha256',json_encode([$i,$primary,$values],JSON_THROW_ON_ERROR))];
    }
    public function page(int $i,int $a):array{return IssuerTransaction::run($this->db,$this->p,$this->tables(),function()use($i,$a){(new IssuerResourceAccess($this->db,$this->p))->lockFinancialIdentity($i,$a);$v=$this->valuesInOwnedTransaction($i,$a);if($v!==$this->valuesInOwnedTransaction($i,$a))throw new DomainException('native_calendar_google_stale');return$v;});}
    public function saveInOwnedTransaction(int $i,int $a,string $revision,array $values):void
    {
        IssuerTransaction::assertOwned($this->db,$this->p,$this->tables());CompanyCalendarPreferences::authorize($this->db,$this->p,$i,$a,true);$values=self::prepare($values);$old=$this->valuesInOwnedTransaction($i,$a);if(!hash_equals($old['revision'],$revision))throw new DomainException('native_calendar_google_stale');
        if((new IssuerResourceAccess($this->db,$this->p))->primaryId()===$i){$guard=new CompanySettingsCommitGuard($this->db,$this->p,$i,$a,'edit');foreach($values as$key=>$value)if($value!==$old['values'][$key])$guard->writeOption('update',$key,$value);$guard->audit('company_settings_batch_updated');$guard->confirm();}
        else(new CompanySettingsStorage($this->db,$this->p))->updateBatchInOwnedTransaction($i,$a,$values);
        if($this->valuesInOwnedTransaction($i,$a)['values']!==$values)throw new RuntimeException('native_calendar_google_unconfirmed');CompanyCalendarPreferences::authorize($this->db,$this->p,$i,$a,true);
    }
    public function browserSources(int $i,int $a):array
    {
        $snapshot=$this->page($i,$a);$ids=(new NativeCompanyDepartments($this->db,$this->p))->calendarSources($i,$a);$main=$snapshot['values']['google_calendar_main_calendar'];if($main!=='')$ids[]=$main;if($this->page($i,$a)!==$snapshot)throw new DomainException('native_calendar_google_stale');return['api_key'=>$snapshot['values']['google_api_key'],'calendar_ids'=>array_values(array_unique($ids))];
    }
}
