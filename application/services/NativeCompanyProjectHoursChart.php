<?php
declare(strict_types=1);
require_once __DIR__.'/projects/HoursOverviewChart.php';
/** Same native chart renderer. The caller supplies an already-authorized timer projection, never a project ID to a global model. */
final class NativeCompanyProjectHoursChart extends \app\services\projects\HoursOverviewChart
{
    private array $rows;
    public function __construct(array $projection,string$type,bool$canCreate,int$actor,string$timesheetsType)
    {
        if(!in_array($type,['this_week','last_week','this_month','last_month'],true)||!in_array($timesheetsType,['total_logged_time_only','billable_unbilled'],true)||$actor<1||!isset($projection['timers'])||!is_array($projection['timers']))throw new DomainException('native_project_chart_invalid');
        $this->type=$type;$this->canCreate=$canCreate;$this->timesheetsType=$canCreate?$timesheetsType:'total_logged_time_only';$this->rows=[];
        foreach($projection['timers']as$row){
            foreach(['start_time','total_seconds','staff_id']as$f)if(!isset($row[$f])||!is_int($row[$f])||$row[$f]<0)throw new DomainException('native_project_chart_invalid');
            if($this->timesheetsType==='billable_unbilled'&&(!is_bool($row['billable']??null)||!is_bool($row['billed']??null)))throw new DomainException('native_project_chart_billing_required');
            if($canCreate||$row['staff_id']===$actor)$this->rows[]=$row;
        }
    }
    protected function timerSeconds(array$timer){return$timer['total_seconds'];}
    protected function getChartTimesheets($unbilled=false)
    {
        [$from,$to]=$this->getBetweenDates();$start=strtotime($from);$end=strtotime($to);
        return array_values(array_filter($this->rows,function(array$row)use($start,$end,$unbilled):bool{return$row['start_time']>=$start&&$row['start_time']<=$end&&($this->timesheetsType==='total_logged_time_only'||($row['billable']&&(!$unbilled||!$row['billed'])));}));
    }
}
