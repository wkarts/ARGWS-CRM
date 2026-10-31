<?php defined('BASEPATH') or exit('No direct script access allowed');init_head();
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$selectedProjects=$this->input->get('project_id',false);$selectedProjects=is_array($selectedProjects)?array_map('strval',$selectedProjects):($selectedProjects===null?[]:[(string)$selectedProjects]);
$selectedStaff=(string)($this->input->get('staff_id',false)??'');$selectedClient=(string)($this->input->get('clientid',false)??'');
$rangeValue=$range['range'];
?>
<div id="wrapper"><div class="content"><div class="row"><div class="col-md-12">
<div class="panel_s"><div class="panel-body">
<div class="tw-flex tw-items-center tw-justify-between tw-gap-3"><h1 class="tw-text-xl tw-font-semibold"><?= $esc(_l('timesheets_overview')); ?></h1>
<a class="btn btn-primary" href="<?= $esc(admin_url('staff/timesheets'.($view_all?'':'?view=all'))); ?>"><?= $esc($view_all?_l('my_timesheets'):_l('view_members_timesheets')); ?></a></div>
<form method="get" action="<?= $esc(admin_url('staff/timesheets')); ?>" class="tw-mt-4" data-native-timesheet-filters>
<?php if($view_all){ ?><input type="hidden" name="view" value="all"><?php } ?>
<div class="row"><div class="col-md-3"><label for="native-timesheet-range"><?= $esc(_l('period_datepicker')); ?></label><select class="form-control" id="native-timesheet-range" name="range">
<?php foreach(['today'=>_l('today'),'this_month'=>_l('staff_stats_this_month_total_logged_time'),'last_month'=>_l('staff_stats_last_month_total_logged_time'),'this_week'=>_l('staff_stats_this_week_total_logged_time'),'last_week'=>_l('staff_stats_last_week_total_logged_time'),'period'=>_l('period_datepicker')]as$value=>$label){ ?><option value="<?= $esc($value); ?>" <?= $rangeValue===$value?'selected':''; ?>><?= $esc($label); ?></option><?php } ?>
</select></div>
<div class="col-md-2"><label for="native-timesheet-from"><?= $esc(_l('report_sales_from_date')); ?></label><input class="form-control" id="native-timesheet-from" type="date" name="period-from" value="<?= $esc($rangeValue==='period'?$range['from']:''); ?>"></div>
<div class="col-md-2"><label for="native-timesheet-to"><?= $esc(_l('report_sales_to_date')); ?></label><input class="form-control" id="native-timesheet-to" type="date" name="period-to" value="<?= $esc($rangeValue==='period'?$range['to']:''); ?>"></div>
<?php if($view_all){ ?><div class="col-md-3"><label for="native-timesheet-staff"><?= $esc(_l('staff_member')); ?></label><select class="form-control" id="native-timesheet-staff" name="staff_id"><option value=""><?= $esc(_l('all')); ?></option><?php foreach($subjects as$subject){ ?><option value="<?= (int)$subject['staff_id']; ?>" <?= $selectedStaff===(string)$subject['staff_id']?'selected':''; ?>><?= $esc($subject['name']); ?></option><?php } ?></select></div><?php } ?>
</div><div class="row tw-mt-3">
<div class="col-md-4"><label for="native-timesheet-client"><?= $esc(_l('client')); ?></label><select class="form-control" id="native-timesheet-client" name="clientid"><option value=""><?= $esc(_l('all')); ?></option><?php foreach($clients as$client){ ?><option value="<?= (int)$client['id']; ?>" <?= $selectedClient===(string)$client['id']?'selected':''; ?>><?= $esc($client['name']); ?></option><?php } ?></select></div>
<div class="col-md-5"><label for="native-timesheet-projects"><?= $esc(_l('projects')); ?></label><select class="form-control" multiple id="native-timesheet-projects" name="project_id[]"><?php foreach($projects as$project){ ?><option value="<?= $esc($project['id']); ?>" <?= in_array((string)$project['id'],$selectedProjects,true)?'selected':''; ?>><?= $esc($project['name']); ?></option><?php } ?></select></div>
<div class="col-md-3"><button class="btn btn-primary tw-mt-6" type="submit"><?= $esc(_l('apply')); ?></button></div>
</div></form>
</div></div>
<div class="panel_s"><div class="panel-body panel-table-full"><div class="table-responsive"><table class="table table-striped" data-native-timesheets><thead><tr>
<?php if($view_all){ ?><th><?= $esc(_l('staff_member')); ?></th><?php } ?><th><?= $esc(_l('project_timesheet_task')); ?></th><th><?= $esc(_l('task_relation')); ?></th><th><?= $esc(_l('timesheet_tags')); ?></th><th><?= $esc(_l('project_timesheet_start_time')); ?></th><th><?= $esc(_l('project_timesheet_end_time')); ?></th><th><?= $esc(_l('note')); ?></th><th><?= $esc(_l('time_h')); ?></th><th><?= $esc(_l('invoice_status')); ?></th></tr></thead><tbody>
<?php $total=0;foreach($rows as$row){$total+=(int)$row['total']; ?><tr>
<?php if($view_all){ ?><td><a href="<?= $esc(admin_url('staff/profile/'.(int)$row['staff_id'])); ?>"><?= $esc($row['staff_name']); ?></a></td><?php } ?>
<td><?php if($row['can_open_task']){ ?><a href="<?= $esc(admin_url('tasks/task/'.$row['task_id'])); ?>"><?= $esc($row['name']); ?></a><?php }else{echo $esc($row['name']);} ?></td>
<td><?php if($row['rel_type']==='project'){ ?><a href="<?= $esc(admin_url('projects/view/'.$row['rel_id'])); ?>"><?= $esc($row['relation_name']); ?></a><?php } ?></td>
<td><?= $esc(implode(', ',array_column($row['tags'],'name'))); ?></td><td><?= $esc(date('Y-m-d H:i:s',(int)$row['start_time'])); ?></td><td><?= $row['not_finished']?'—':$esc(date('Y-m-d H:i:s',(int)$row['end_time'])); ?></td><td><?= $esc($row['note']); ?></td><td><?= $esc(seconds_to_time_format((int)$row['total'])); ?></td><td><?= $esc($row['billing_state']); ?></td></tr><?php } ?>
<?php if(!$rows){ ?><tr><td colspan="<?= $view_all?9:8; ?>" class="text-center"><?= $esc(_l('no_results_found')); ?></td></tr><?php } ?>
</tbody><tfoot><tr><th colspan="<?= $view_all?7:6; ?>" class="text-right"><?= $esc(_l('total_logged_hours_by_staff')); ?></th><th><?= $esc(seconds_to_time_format($total)); ?></th><th></th></tr></tfoot></table></div></div></div>
</div></div></div></div>
<?php init_tail(); ?>
</body></html>
