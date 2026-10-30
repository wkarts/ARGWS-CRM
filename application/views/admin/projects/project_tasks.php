<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_project_view)){ ?><div class="panel_s"><div class="panel-body"><table class="table"><thead><tr><th>Tarefa</th><th>Estado</th><th>Início</th><th>Prazo</th></tr></thead><tbody><?php foreach($native_project_tasks as$item){$status=NativeCompanyWorkStatusCatalog::owned('task','_l');$status=array_column($status,null,'id')[$item['fields']['workflow_status']]; ?><tr><td><a href="<?= admin_url('tasks/list_tasks/'.$item['id']); ?>" onclick="init_task_modal(<?= e($item['id']); ?>);return false;"><?= e($item['row']['name']); ?></a></td><td><?= e($status['name']); ?></td><td><?= e($item['fields']['start_date']??''); ?></td><td><?= e($item['row']['due_date']??''); ?></td></tr><?php } ?></tbody></table></div></div><?php return; } ?>
<!-- Project Tasks -->
<?php
    if ($project->settings->hide_tasks_on_main_tasks_table == '1') {
        echo '<i class="fa fa-exclamation fa-2x pull-left" data-toggle="tooltip" data-title="' . _l('project_hide_tasks_settings_info') . '"></i>';
    }
?>
<div class="panel_s">
    <div class="panel-body">
        <div class="tasks-table panel-table-full" id="vueApp">
            <?php init_relation_tasks_table(['data-new-rel-id' => $project->id, 'data-new-rel-type' => 'project'], 'vueApp', true); ?>
        </div>
    </div>
</div>