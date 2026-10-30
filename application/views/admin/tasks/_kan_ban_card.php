<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_task_card)){$item=$native_task_card;$task=$item['row']; ?>
<li class="task" data-task-id="<?= e($item['id']); ?>"><div class="panel-body"><a class="tw-font-medium" href="<?= e(admin_url('tasks/list_tasks/'.$item['id'])); ?>" onclick="init_task_modal('<?= e($item['id']); ?>');return false;"><?= e($task['name']); ?></a>
<p><?= e($task['due_date']??''); ?></p><p><?= e(implode(', ',array_map(static fn($member)=>trim($member['firstname'].' '.$member['lastname']),$item['assignees']))); ?></p>
<?php foreach($item['tags']as$tag){ ?><span class="label label-default"><?= e($tag['name']); ?></span><?php } ?>
<?php $context=CompanyRequestScope::context();if((new IssuerResourceAccess($this->db,db_prefix()))->can($context->issuerId(),$context->staffId(),'tasks','edit')){ ?>
<?= form_open(admin_url('tasks/update_order')); ?>
<?= form_hidden('_company_context',$native_task_context).form_hidden('_native_company_csrf',$native_task_csrf).form_hidden('board_revision',$native_task_board_preference['revision']).form_hidden('native_request_key',bin2hex(random_bytes(32))).form_hidden('task',$item['id']); ?>
<label for="task-order-<?= e($item['id']); ?>">Posicionar antes de</label><select class="form-control" id="task-order-<?= e($item['id']); ?>" name="before"><option value="">Final do quadro</option><?php foreach($native_task_board['items']as$target)if($target['id']!==$item['id']){ ?><option value="<?= e($target['id']); ?>"><?= e($target['row']['name']); ?></option><?php } ?></select><button class="btn btn-default btn-xs mtop5" type="submit"><?= e(_l('save')); ?></button><?= form_close(); ?>
<?php } ?></div></li>
<?php return;} ?>
<li data-task-id="<?= e($task['id']); ?>" class="task<?php if ($task['current_user_is_assigned']) {
    echo ' current-user-task';
} if ((! empty($task['duedate']) && $task['duedate'] < date('Y-m-d')) && $task['status'] != Tasks_model::STATUS_COMPLETE) {
    echo ' overdue-task';
} ?><?php if (! $task['current_user_is_assigned'] && $task['current_user_is_creator'] == '0' && ! is_admin()) {
    echo ' not-sortable';
} ?>">
    <div class="panel-body"
        style="border-top: 2px solid <?= e(task_priority_color($task['priority'])); ?>;">
        <div class="row">
            <div class="col-md-12 task-name">
                <a href="<?= admin_url('tasks/view/' . $task['id']); ?>"
                    class="tw-font-medium"
                    onclick="init_task_modal(<?= e($task['id']); ?>);return false;">
                    <span
                        class="inline-block full-width mtop10 tw-truncate"><?= e($task['name']); ?></span>
                </a>
                <?php
               if ($task['rel_name']) {
                   $relName = task_rel_name($task['rel_name'], $task['rel_id'], $task['rel_type']);
                   $link    = task_rel_link($task['rel_id'], $task['rel_type']);

                   echo '<a class="tw-text-neutral-600 tw-truncate -tw-mt-1 tw-block tw-text-sm" data-toggle="tooltip" title="' . _l('task_related_to') . '" href="' . e($link) . '">' . e($relName) . '</a>';
               }
?>
            </div>
            <div class="col-md-4 tw-text-neutral-600 mtop10 tw-text-sm">
                <?= format_members_by_ids_and_names($task['assignees_ids'], $task['assignees'], 'sm');
?>
            </div>
            <div class="col-md-8 text-right tw-text-neutral-500 mtop10 tw-text-sm">
                <?php if ($task['total_checklist_items'] > 0) { ?>
                <span class="mright5 inline-block tw-text-neutral-500" data-toggle="tooltip"
                    data-title="<?= _l('task_checklist_items'); ?>">
                    <i class="fa-regular fa-square-check"></i>
                    <?= e($task['total_finished_checklist_items']); ?>
                    /
                    <?= e($task['total_checklist_items']); ?>
                </span>
                <?php } ?>
                <span class="mright5 inline-block tw-text-neutral-500" data-toggle="tooltip"
                    data-title="<?= _l('task_comments'); ?>">
                    <i class="fa-regular fa-comments"></i>
                    <?= e($task['total_comments']); ?>
                </span>
                <span class="inline-block tw-text-neutral-500" data-toggle="tooltip"
                    data-title="<?= _l('task_view_attachments'); ?>">
                    <i class="fa fa-paperclip"></i>
                    <?= e($task['total_files']); ?>
                </span>
            </div>
        </div>
        <?php if (! empty($task['duedate'])) { ?>
        <div class="row">
            <div class="tw-text-sm col-md-12 text-right">
                <span class="tw-text-neutral-500" data-toggle="tooltip"
                    title="<?= _l('task_single_due_date'); ?>">
                    <i class="fa fa-calendar-check-o"></i>
                    <?= e(_d($task['duedate'])); ?>
                </span>
            </div>
        </div>
        <?php } ?>
        <?php $tags = get_tags_in($task['id'], 'task'); ?>
        <?php if (count($tags) > 0) { ?>
        <div class="kanban-tags tw-text-sm tw-inline-flex">
            <?= render_tags($tags); ?>
        </div>
        <?php } ?>
    </div>
</li>