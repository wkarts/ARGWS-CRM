<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $nativeTaskIndex = $native_task_index ?? null; $taskKanban = isset($native_task_board) || $nativeTaskIndex === null && $this->session->has_userdata('tasks_kanban_view') && $this->session->userdata('tasks_kanban_view') == 'true'; ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content" id="vueApp">
        <div class="row">
            <?php if (! $taskKanban) { ?>
            <div class="col-md-12 tw-mb-3">
                <h4 class="tw-my-0 tw-font-bold tw-text-xl">
                    <?= _l('tasks'); ?>
                </h4>
                <a
                    href="<?= admin_url('tasks/detailed_overview'); ?>">
                    <?= _l('detailed_overview'); ?>
                    &rarr;
                </a>
            </div>
            <div class="col-md-12 tw-mb-6">
                <?php $this->load->view('admin/tasks/_summary', ['table' => '.table-tasks']); ?>
            </div>
            <?php } ?>
        </div>
        <div class="row _buttons tw-mb-2">
            <div class="col-md-8">
                <?php if ($nativeTaskIndex !== null ? $nativeTaskIndex['permissions']['create'] : staff_can('create', 'tasks')) { ?>
                <a href="#" onclick="new_task(<?php if ($this->input->get('project_id')) {
                    echo "'" . admin_url('tasks/task?rel_id=' . $this->input->get('project_id') . '&rel_type=project') . "'";
                } ?>); return false;" class="btn btn-primary pull-left new">
                    <i class="fa-regular fa-plus tw-mr-1"></i>
                    <?= _l('new_task'); ?>
                </a>
                <?php } ?>
                <?php if($nativeTaskIndex!==null){ ?>
                <?= form_open(admin_url('tasks/switch_kanban'),['class'=>'pull-left tw-ml-1']); ?>
                <?= form_hidden('_company_context',$native_task_context).form_hidden('_native_company_csrf',$native_task_csrf).form_hidden('board_revision',$native_task_board_preference['revision']).form_hidden('native_request_key',bin2hex(random_bytes(32))).form_hidden('view',$taskKanban?'table':'board'); ?>
                <button class="btn btn-default" type="submit"><?= e($taskKanban?_l('switch_to_list_view'):_l('leads_switch_to_kanban')); ?></button>
                <?= form_close(); ?>
                <?php }else{ ?>
                <a href="<?= admin_url(! $this->input->get('project_id') ? ('tasks/switch_kanban/' . $switch_kanban) : ('projects/view/' . $this->input->get('project_id') . '?group=project_tasks')); ?>"
                    class="btn btn-default tw-ml-1 pull-left hidden-xs !tw-px-3" data-toggle="tooltip"
                    data-placement="top"
                    data-title="<?= $switch_kanban == 1 ? _l('switch_to_list_view') : _l('leads_switch_to_kanban'); ?>">
                    <?php if ($switch_kanban == 1) { ?>
                    <i class="fa-solid fa-table-list"></i>
                    <?php } else { ?>
                    <i class="fa-solid fa-grip-vertical"></i>
                    <?php } ?>
                </a>
                <?php } ?>
            </div>
            <div class="col-md-4">
                <?php if(isset($native_task_board)){ ?>
                <form action="<?= e(admin_url('tasks')); ?>" method="get"><input type="hidden" name="kanban" value="1"><label class="sr-only" for="native-task-board-search"><?= e(_l('search_tasks')); ?></label><div class="input-group"><input class="form-control" id="native-task-board-search" name="search" maxlength="191" value="<?= e($this->input->get('search')??''); ?>"><span class="input-group-btn"><button type="submit" class="btn btn-default"><?= e(_l('search')); ?></button></span></div></form>
                <?php }elseif ($taskKanban) { ?>
                <div data-toggle="tooltip" data-placement="top"
                    data-title="<?= _l('search_by_tags'); ?>">
                    <?= render_input('search', '', '', 'search', ['data-name' => 'search', 'onkeyup' => 'tasks_kanban();', 'placeholder' => _l('search_tasks')], [], 'no-margin') ?>
                </div>
                <?php } else { ?>
                <?php $this->load->view('admin/tasks/filters', ['filters_wrapper_id' => 'vueApp', 'detached' => true]); ?>
                <?php } ?>
            </div>
        </div>
        <div class="row">

            <div class="col-md-12">
                <?php if ($taskKanban) { ?>
                <div class="kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
                    <div class="row">
                        <div id="kanban-params">
                            <?= form_hidden('project_id', $this->input->get('project_id')); ?>
                        </div>
                        <div class="container-fluid">
                            <div id="kan-ban"><?php if(isset($native_task_board))$this->load->view('admin/tasks/kan_ban'); ?></div>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                <div class="panel_s">
                    <div class="panel-body">

                        <a href="#" data-toggle="modal" data-target="#tasks_bulk_actions"
                            class="hide bulk-actions-btn table-btn"
                            data-table=".table-tasks"><?= _l('bulk_actions'); ?></a>
                        <div class="panel-table-full">
                            <?php $this->load->view('admin/tasks/_table', ['bulk_actions' => true]); ?>
                        </div>
                        <?php $this->load->view('admin/tasks/_bulk_actions'); ?>
                    </div>
                </div>
                <?php } ?>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
    taskid = '<?= e($taskid); ?>';
    $(function() {
        <?php if($nativeTaskIndex !== null){ ?>
        var filters = {};
        $('._hidden_inputs._filters._tasks_filters input').each(function(){filters[this.name]='[name="'+this.name+'"]';});
        if($('.table-tasks').length)initDataTable('.table-tasks',admin_url+'tasks/table?bulk_actions=true',[0],[0],filters,[$('.table-tasks th.duedate').index(),'asc']);
        if(taskid!=='')init_task_modal(taskid);
        <?php }else{ ?>
        tasks_kanban();
        <?php } ?>
    });
</script>
</body>

</html>
