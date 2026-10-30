<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_project_view)){ ?><div class="panel_s"><div class="panel-body"><h4>Apontamentos do projeto</h4><table class="table"><thead><tr><th>Tarefa</th><th>Profissional</th><th>Início</th><th>Fim</th><th>Duração</th></tr></thead><tbody><?php foreach($native_project_timesheets['timers']as$timer){ ?><tr><td><a href="<?= admin_url('tasks/list_tasks/'.$timer['task_id']); ?>" onclick="init_task_modal(<?= e($timer['task_id']); ?>);return false;"><?= e($timer['task_name']); ?></a></td><td><?= (int)$timer['staff_id']; ?></td><td><?= e(date('Y-m-d H:i:s',$timer['start_time'])); ?></td><td><?= $timer['end_time']===null?'Em execução':e(date('Y-m-d H:i:s',$timer['end_time'])); ?></td><td><?= e(gmdate('H:i:s',$timer['total_seconds'])); ?></td></tr><?php } ?></tbody></table><?php require __DIR__.'/native_timesheet_rules.php'; ?></div></div><?php return; } ?>
<a href="#" onclick="new_timesheet();return false;" class="btn btn-primary tw-mb-2">
    <i class="fa-regular fa-plus tw-mr-1"></i>
    <?= _l('record_timesheet'); ?>
</a>
<div class="panel_s">
    <div class="panel-body panel-table-full">
        <?php if (staff_can('create', 'projects')) { ?>
        <div class="_filters _hidden_inputs timesheets_filters hidden">
            <?php
            foreach ($timesheets_staff_ids as $t_staff_id) {
                echo form_hidden('staff_id_' . $t_staff_id['staff_id'], $t_staff_id['staff_id']);
            }
            ?>
        </div>
        <?php if (count($timesheets_staff_ids) > 0) { ?>
        <div class="btn-group pull-right mleft4 btn-with-tooltip-group _filter_data" data-toggle="tooltip"
            data-title="<?= _l('filter_by'); ?>">
            <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true"
                aria-expanded="false">
                <i class="fa fa-filter" aria-hidden="true"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-right width300">
                <?php foreach ($timesheets_staff_ids as $t_staff_id) { ?>
                <li class="active">
                    <a href="#"
                        data-cview="staff_id_<?= e($t_staff_id['staff_id']); ?>"
                        onclick="dt_custom_view(<?= e($t_staff_id['staff_id']); ?>,'.table-timesheets','staff_id_<?= e($t_staff_id['staff_id']); ?>'); return false;"><?= e(get_staff_full_name($t_staff_id['staff_id'])); ?>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </div>
        <?php } ?>
        <?php } ?>
        <?php $table_data = [
                _l('project_timesheet_user'),
                _l('project_timesheet_task'),
                _l('timesheet_tags'),
                _l('project_timesheet_start_time'),
                _l('project_timesheet_end_time'),
                _l('note'),
                _l('time_h'),
                _l('time_decimal'), ];
$table_data = hooks()->apply_filters('projects_timesheets_table_columns', $table_data);
array_push($table_data, _l('options'));
render_datatable($table_data, 'timesheets'); ?>
        <?php $this->load->view('admin/projects/timesheet'); ?>
    </div>
</div>