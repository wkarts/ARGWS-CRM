<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyCalendarPreferences.php';
require_once __DIR__.'/NativeCompanyCalendar.php';
require_once __DIR__.'/NativeCompanyAuxiliary.php';
require_once __DIR__.'/CompanyCalendarRead.php';
require_once __DIR__.'/NativeCompanyLegacyCalendarRead.php';
/** Original FullCalendar event DTO; reads only, with no provider request or notification write. */
final class NativeCompanyCalendarFeed
{
    public function __construct(private $db,private string $p){}
    public static function filters(array $input):array
    {
        $keys=['start','end','calendar_filters','events','tasks','projects','invoices','estimates','proposals','contracts'];foreach(NativeCompanyCalendarPreferences::REMINDERS as$t)$keys[]=$t.'_reminders';
        if(array_diff(array_keys($input),$keys))throw new DomainException('native_calendar_range_invalid');
        $dates=[];foreach(['start','end']as$k){$v=$input[$k]??null;if(!is_string($v)||!preg_match('/^\d{4}-\d{2}-\d{2}(?:T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2}))?$/D',$v))throw new DomainException('native_calendar_range_invalid');if(strlen($v)>10){try{$stamp=new DateTimeImmutable($v);}catch(Throwable $e){throw new DomainException('native_calendar_range_invalid');}if($stamp->format('Y-m-d\TH:i:s')!==substr($v,0,19))throw new DomainException('native_calendar_range_invalid');}$d=DateTimeImmutable::createFromFormat('!Y-m-d',substr($v,0,10));if(!$d||$d->format('Y-m-d')!==substr($v,0,10))throw new DomainException('native_calendar_range_invalid');$dates[$k]=$d;}
        if($dates['end']<=$dates['start']||$dates['start']->diff($dates['end'])->days>63)throw new DomainException('native_calendar_range_invalid');
        $flags=[];foreach(array_diff($keys,['start','end'])as$k)if(array_key_exists($k,$input)){if(!in_array($input[$k],[true,1,'1','true'],true))throw new DomainException('native_calendar_range_invalid');$flags[$k]=true;}
        return ['start'=>$dates['start'],'end'=>$dates['end'],'flags'=>$flags,'filtered'=>isset($flags['calendar_filters'])];
    }
    public function read(int $i,int $a,array $input):array
    {
        $f=self::filters($input);
        $prefs=new NativeCompanyCalendarPreferences($this->db,$this->p);$snapshot=$prefs->page($i,$a);$v=$snapshot['values'];$out=[];
        $enabled=static fn(string $key)=>!$f['filtered']||isset($f['flags'][$key]);
        if($enabled('events'))foreach((new NativeCompanyCalendar($this->db,$this->p))->range($i,$a,$f['start'],$f['end'])as$r)$out[]=['title'=>$r['title'],'start'=>$r['start'],'end'=>$r['end']?:null,'color'=>$r['color'],'eventid'=>$r['eventid'],'id'=>'event:'.$r['eventid'],'_tooltip'=>$r['description']];
        $sources=[];foreach(CompanyCalendarSources::definitions()as$source=>$definition){$filter=in_array($source,['project_tasks','standalone_tasks'],true)?'tasks':$source;if($enabled($filter))$sources[]=$source;}
        $documents=new CompanyCalendarRead($this->db,$this->p);$page=$documents->page($i,$a,['start'=>$f['start']->format('Y-m-d'),'end'=>$f['end']->modify('-1 day')->format('Y-m-d'),'sources'=>$sources]);
        foreach($page['events']as$r){[$source,$id]=explode(':',$r['id'],2);$kind=match($source){'invoices'=>'invoice','estimates'=>'company_estimate','proposals'=>'company_proposal','projects'=>'company_project','project_tasks'=>'company_task','standalone_tasks'=>'company_standalone_task','contracts'=>'company_contract'};$canonical=NativeCompanyResourceId::encode($kind,$id);$path=match($source){'invoices'=>'invoices/list_invoices/','estimates'=>'estimates/list_estimates/','proposals'=>'proposals/list_proposals/','projects'=>'projects/view/','project_tasks','standalone_tasks'=>'tasks/view/','contracts'=>'contracts/contract/'}.$canonical;$out[]=['id'=>$r['id'],'title'=>$r['title'],'start'=>$r['date'],'allDay'=>true,'color'=>$r['color'],'native_path'=>$path];}
        $aux=new NativeCompanyAuxiliary($this->db,$this->p,$i,$a);
        foreach($aux->calendarReminders($f['start']->format('Y-m-d H:i:s'),$f['end']->format('Y-m-d H:i:s'),$v['hide_notified_reminders_from_calendar']==='1')as$r){$type=$r['rel_type'];if(!in_array($type,NativeCompanyCalendarPreferences::REMINDERS,true)||$v['show_'.$type.'_reminders_on_calendar']!=='1'||!$enabled($type.'_reminders'))continue;$title=($a!==(int)$r['staff']?'('.$r['firstname'].' '.$r['lastname'].') ':'').mb_substr($r['description'],0,60).'...';$out[]=['id'=>'reminder:'.$r['id'],'title'=>$title,'start'=>$r['date'],'color'=>$v['calendar_reminder_color'],'native_path'=>$r['relation']['url']];}
        $legacy=new NativeCompanyLegacyCalendarRead($this->db,$this->p);$out=array_merge($out,$legacy->read($i,$a,$f,$v));
        $documents->confirm($i,$a);$legacy->confirm($i,$a);if($prefs->page($i,$a)!==$snapshot)throw new DomainException('native_calendar_preferences_stale');if(count($out)>1500)throw new DomainException('native_calendar_range_too_large');return$out;
    }
}
