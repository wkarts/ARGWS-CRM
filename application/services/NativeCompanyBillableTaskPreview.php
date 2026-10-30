<?php
declare(strict_types=1);
require_once __DIR__.'/NativeCompanyBillableTaskRead.php';
require_once __DIR__.'/NativeCompanyProjectMetadata.php';
require_once __DIR__.'/NativeCompanyTaskSettings.php';
require_once __DIR__.'/NativeCompanyTaskTimerRounding.php';
require_once __DIR__.'/utilities/Format.php';
/** Original task-to-item preview. No billing reservation, quote, receipt or conversion authority. */
final class NativeCompanyBillableTaskPreview
{
 public function __construct(private $db,private string $p){}
 private function preferences(int$i):array{$store=new CompanySettingsStorage($this->db,$this->p);$out=[];foreach(['round_off_task_timer_option','round_off_task_timer_time']as$key)$out[$key]=$store->exists($i,$key)?$store->get($i,$key):NativeCompanyTaskSettings::extras()[$key];NativeCompanyTaskSettings::validateExtras($out);return$out;}
 public static function roundSeconds(int $seconds,array $options):int
 {return NativeCompanyTaskTimerRounding::roundSeconds($seconds,$options);}
 public function read(int$i,int$a,$task,?int$now=null):array
 {
  $acl=new IssuerResourceAccess($this->db,$this->p);$check=static function()use($acl,$i,$a):void{if(!$acl->can($i,$a,'invoices','view')||(!$acl->can($i,$a,'invoices','create')&&!$acl->can($i,$a,'invoices','edit')))throw new DomainException('native_billable_task_denied');};$check();$now??=time();
  $reader=new NativeCompanyBillableTaskRead($this->db,$this->p);$item=$reader->forTask($i,$a,$task);$preferences=$this->preferences($i);$metadata=null;$legacy=null;$project=null;
  if($item['rel_type']==='project'){
   $project=NativeCompanyResourceReferences::resolve($this->db,$this->p,$i,$a,'project',$item['rel_id'],'view');
   if($project['source_kind']==='company_project'){$read=fn()=>IssuerTransaction::run($this->db,$this->p,NativeCompanyProjectMetadata::tables($this->db,$this->p),fn()=>(new NativeCompanyProjectMetadata($this->db,$this->p))->readInOwnedTransaction($i,$a,$project['source_id']));$metadata=$read();if($metadata===null)throw new DomainException('native_billable_task_project_undeclared');$rate=(int)$metadata['billing_type']===2?$metadata['project_rate_per_hour']:$item['hourly_rate'];}
   else{if($acl->primaryId()!==$i)throw new DomainException('native_billable_task_denied');$legacy=IssuerStorage::query($this->db,'SELECT billing_type,project_rate_per_hour FROM '.$this->p.'projects WHERE id=?',[$project['source_id']])->row_array();if(!$legacy)throw new DomainException('native_billable_task_denied');$rate=(int)$legacy['billing_type']===2?(string)$legacy['project_rate_per_hour']:$item['hourly_rate'];}
  }else $rate=$item['hourly_rate'];
  $timers=(new NativeCompanyTasksRead($this->db,$this->p))->trackingStats($i,$a,$task,static fn($key)=>$key,static fn($seconds)=>$seconds,$now);$seconds=0;foreach($timers['datasets'][0]['data']as$value){if(!is_int($value)||$value<0||$seconds>PHP_INT_MAX-$value)throw new DomainException('native_billable_task_timer_invalid');$seconds+=$value;}$rounded=self::roundSeconds($seconds,$preferences);
  if($reader->forTask($i,$a,$task)!==$item||$this->preferences($i)!==$preferences||($metadata!==null&&$read()!==$metadata)||($legacy!==null&&IssuerStorage::query($this->db,'SELECT billing_type,project_rate_per_hour FROM '.$this->p.'projects WHERE id=?',[$project['source_id']])->row_array()!==$legacy))throw new DomainException('native_billable_task_snapshot_changed');$check();if((new NativeCompanyTasksRead($this->db,$this->p))->trackingStats($i,$a,$task,static fn($key)=>$key,static fn($seconds)=>$seconds,$now)!==$timers)throw new DomainException('native_billable_task_snapshot_changed');$item['hourly_rate']=$rate;if($project!==null)$item['name']=$project['name'].' - '.$item['name'];
  return$item+['total_seconds'=>$rounded,'total_hours'=>\app\services\utilities\Format::sec2qty($rounded),'description'=>\app\services\utilities\Format::secondsToTime($rounded).' '._l('hours')];
 }
}
