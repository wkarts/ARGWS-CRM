<?php defined('BASEPATH') or exit('No direct script access allowed');

if(isset($native_task_board)){
    foreach($task_statuses as$status){$items=array_values(array_filter($native_task_board['items'],static fn($item)=>$item['fields']['workflow_status']===$status['id'])); ?>
    <ul class="kan-ban-col tasks-kanban" data-col-status-id="<?= e($status['id']); ?>"><li class="kan-ban-col-wrapper"><div class="border-right panel_s"><div class="panel-heading tw-font-medium" style="background:<?= e($status['color']); ?>;color:#fff"><?= e($status['name']); ?></div><div class="kan-ban-content-wrapper"><div class="kan-ban-content"><ul class="status tasks-status relative">
    <?php foreach($items as$item){$this->load->view('admin/tasks/_kan_ban_card',['native_task_card'=>$item,'native_task_board'=>$native_task_board,'native_task_board_preference'=>$native_task_board_preference,'native_task_context'=>$native_task_context,'native_task_csrf'=>$native_task_csrf]);} ?>
    <?php if(!$items){ ?><li class="text-center not-sortable"><p><?= e(_l('no_tasks_found')); ?></p></li><?php } ?>
    </ul></div></div></div></li></ul>
    <?php } ?>
    <div class="clearfix"></div>
    <?php if($native_task_board['offset']>0){ ?><a class="btn btn-default" href="<?= e(admin_url('tasks?kanban=1&offset='.max(0,$native_task_board['offset']-50).'&search='.rawurlencode($this->input->get('search')??''))); ?>">Página anterior</a><?php } ?>
    <?php if($native_task_board['next']!==null){ ?><a class="btn btn-default" href="<?= e(admin_url('tasks?kanban=1&offset='.$native_task_board['next'].'&search='.rawurlencode($this->input->get('search')??''))); ?>"><?= e(_l('load_more')); ?></a><?php } ?>
    <?php return;
}

$where = [];
if ($this->input->get('project_id')) {
    $where['rel_id']   = $this->input->get('project_id');
    $where['rel_type'] = 'project';
}

foreach ($task_statuses as $status) {
    $kanBan = new app\services\tasks\TasksKanban($status['id']);
    $kanBan->search($this->input->get('search'))
        ->sortBy($this->input->get('sort_by'), $this->input->get('sort'))
        ->forProject($this->input->get('project_id'));

    if ($this->input->get('refresh')) {
        $kanBan->refresh($this->input->get('refresh')[$status['id']] ?? null);
    }

    $tasks       = $kanBan->get();
    $total_tasks = count($tasks);
    $total_pages = $kanBan->totalPages(); ?>
<ul class="kan-ban-col tasks-kanban"
    data-col-status-id="<?= e($status['id']); ?>"
    data-total-pages="<?= e($total_pages); ?>"
    data-total="<?= e($total_tasks); ?>">
    <li class="kan-ban-col-wrapper">
        <div class="border-right panel_s">
            <div class="panel-heading tw-font-medium"
                style="background:<?= e($status['color']); ?>;border-color:<?= e($status['color']); ?>;color:#fff; ?>"
                data-status-id="<?= e($status['id']); ?>">

                <?= format_task_status($status['id'], false, true); ?>
                -
                <span class="tw-text-sm">
                    <?= $kanBan->countAll() . ' ' . _l('tasks') ?>
                </span>

            </div>
            <div class="kan-ban-content-wrapper">
                <div class="kan-ban-content">
                    <ul class="status tasks-status sortable relative"
                        data-task-status-id="<?= e($status['id']); ?>">
                        <?php
              foreach ($tasks as $task) {
                  if ($task['status'] == $status['id']) {
                      $this->load->view('admin/tasks/_kan_ban_card', ['task' => $task, 'status' => $status['id']]);
                  }
              } ?>
                        <?php if ($total_tasks > 0) { ?>
                        <li class="text-center not-sortable kanban-load-more"
                            data-load-status="<?= e($status['id']); ?>">
                            <a href="#" class="btn btn-default btn-block<?php if ($total_pages <= 1 || $kanBan->getPage() == $total_pages) {
                                echo ' disabled';
                            } ?>"
                                data-page="<?= $kanBan->getPage(); ?>"
                                onclick="kanban_load_more(<?= e($status['id']); ?>,this,'tasks/tasks_kanban_load_more',265,360); return false;"
                                ;><?= _l('load_more'); ?></a>
                        </li>
                        <?php } ?>
                        <li class="text-center not-sortable mtop30 kanban-empty<?php if ($total_tasks > 0) {
                            echo ' hide';
                        } ?>">
                            <h4>
                                <i class="fa-solid fa-circle-notch" aria-hidden="true"></i><br /><br />
                                <?= _l('no_tasks_found'); ?>
                            </h4>
                        </li>
                    </ul>
                </div>
            </div>
    </li>
</ul>
<?php } ?>