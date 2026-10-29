<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (isset($native_project_view)) { ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="row"><div class="col-md-12">
    <div class="panel_s"><div class="panel-body">
        <div class="tw-flex tw-items-start tw-justify-between tw-gap-4">
            <div><h3 class="tw-font-bold tw-mt-0"><?php echo e($native_project_overview['facts']['project']['name']); ?></h3>
                <span class="label" style="color:<?php echo e($native_project_overview['status']['color']); ?>;border:1px solid <?php echo e($native_project_overview['status']['color']); ?>"><?php echo e($native_project_overview['status']['name']); ?></span>
            </div>
            <div><a class="btn btn-default" href="<?php echo admin_url('projects'); ?>"><?php echo _l('back_to_list'); ?></a>
            <?php if ($native_project_view['can_edit']) { ?><a class="btn btn-primary" href="<?php echo admin_url('projects/project/'.$native_project_view['canonical_id']); ?>"><?php echo _l('edit_project'); ?></a><?php } ?></div>
        </div>
        <ul class="nav nav-tabs tw-mt-5">
            <li class="<?php echo $native_project_view['group']==='project_overview'?'active':''; ?>"><a href="<?php echo admin_url('projects/view/'.$native_project_view['canonical_id'].'?group=project_overview'); ?>"><?php echo _l('project_overview'); ?></a></li>
            <li class="<?php echo $native_project_view['group']==='project_files'?'active':''; ?>"><a href="<?php echo admin_url('projects/view/'.$native_project_view['canonical_id'].'?group=project_files'); ?>"><?php echo _l('project_files'); ?></a></li>
        </ul>
    </div></div>

    <?php if (is_array($portal_controls)) { ?>
    <div class="panel_s" id="operation-portal-publication"><div class="panel-body">
        <h4 class="tw-font-bold tw-mt-0">Portal do cliente</h4>
        <p class="text-muted">A publicação é explícita e os anexos permanecem privados até autorização.</p>
        <?php if (!$portal_controls['private_client']) { ?><div class="alert alert-warning">O cadastro não pertence a esta empresa.</div><?php } ?>
        <?php if ($portal_controls['can_contact_edit']) { foreach ($portal_controls['permissions'] as $contact => $permission) { ?>
        <?php echo form_open(admin_url('projects/view/'.$native_project_view['canonical_id'])); ?>
        <?php echo form_hidden('_company_context',$native_project_view['context_token']); echo form_hidden('_native_company_csrf',$native_project_view['csrf_token']); echo form_hidden('native_csrf',$company_projects_csrf); echo form_hidden('company_projects_csrf',$company_projects_csrf); echo form_hidden('action','portal_permission'); echo form_hidden('revision',$portal_revision); echo form_hidden('request_key',bin2hex(random_bytes(32))); echo form_hidden('contact_id',$contact); echo form_hidden('contact_revision',$permission['revision']); ?>
        <label><input type="checkbox" name="allowed" value="1" <?php echo $permission['allowed']?'checked':''; ?>> <?php echo e($permission['name']); ?></label>
        <button class="btn btn-default btn-sm" type="submit">Salvar acesso</button><?php echo form_close(); ?>
        <?php } } ?>
        <?php if ($portal_controls['can_contact_edit'] && $portal_controls['private_client'] && $portal_controls['eligible']) { ?>
        <?php echo form_open(admin_url('projects/view/'.$native_project_view['canonical_id'])); ?>
        <?php echo form_hidden('_company_context',$native_project_view['context_token']); echo form_hidden('_native_company_csrf',$native_project_view['csrf_token']); echo form_hidden('native_csrf',$company_projects_csrf); echo form_hidden('company_projects_csrf',$company_projects_csrf); echo form_hidden('action','portal_publish'); echo form_hidden('revision',$portal_revision); echo form_hidden('request_key',bin2hex(random_bytes(32))); echo form_hidden('publication_type','record'); echo form_hidden('visible','1'); ?>
        <button class="btn btn-default" type="submit">Publicar projeto</button><?php echo form_close(); ?>
        <?php foreach ($portal_controls['files'] as $file) { ?>
        <?php echo form_open(admin_url('projects/view/'.$native_project_view['canonical_id'])); ?>
        <?php echo form_hidden('_company_context',$native_project_view['context_token']); echo form_hidden('_native_company_csrf',$native_project_view['csrf_token']); echo form_hidden('native_csrf',$company_projects_csrf); echo form_hidden('company_projects_csrf',$company_projects_csrf); echo form_hidden('action','portal_publish'); echo form_hidden('revision',$portal_revision); echo form_hidden('request_key',bin2hex(random_bytes(32))); echo form_hidden('publication_type','file'); echo form_hidden('file_key',$file['file_key']); echo form_hidden('visible','1'); ?>
        <button class="btn btn-default btn-sm" type="submit">Publicar anexo <?php echo e($file['filename']); ?></button><?php echo form_close(); ?>
        <?php } ?>
        <?php } ?>
    </div></div>
    <?php } ?>

    <?php if ($native_project_view['group']==='project_overview') { ?>
        <?php $this->load->view('admin/projects/project_overview',['native_project_overview'=>$native_project_overview]); ?>
        <div class="panel_s"><div class="panel-body"><h4 class="tw-font-bold tw-mt-0"><?php echo _l('project_members'); ?></h4>
        <?php if ($native_project_view['can_edit']) { ?>
            <?php echo form_open(admin_url('projects/add_edit_members/'.$native_project_view['canonical_id']),['id'=>'native-project-members']); ?>
            <?php echo form_hidden('_company_context',$native_project_view['context_token']);echo form_hidden('_native_company_csrf',$native_project_view['csrf_token']);echo form_hidden('native_members_revision',$native_project_view['members_revision']); ?>
            <?php $selected=array_map('intval',array_column($native_project_overview['facts']['members']['members'],'staffid')); ?>
            <select class="selectpicker" multiple name="project_members[]" data-width="100%" data-live-search="true">
                <?php foreach($native_project_staff as $member){$staffId=(int)$member['staffid']; ?><option value="<?php echo e($staffId); ?>"<?php echo in_array($staffId,$selected,true)?' selected':''; ?>><?php echo e(trim($member['firstname'].' '.$member['lastname'])); ?></option><?php } ?>
            </select><button class="btn btn-primary tw-mt-3" type="submit"><?php echo _l('submit'); ?></button><?php echo form_close(); ?>
        <?php } else { ?><ul><?php foreach($native_project_overview['facts']['members']['members'] as $member){ ?><li><?php echo e(trim($member['firstname'].' '.$member['lastname'])); ?></li><?php } ?></ul><?php } ?>
        </div></div>
    <?php } else { ?>
        <div class="panel_s"><div class="panel-body">
            <?php if ($native_project_view['can_edit']) { ?>
            <?php echo form_open_multipart(admin_url('projects/upload_file/'.$native_project_view['canonical_id']),['id'=>'native-project-file-upload']); ?>
            <?php echo form_hidden('_company_context',$native_project_view['context_token']);echo form_hidden('_native_company_csrf',$native_project_view['csrf_token']);echo form_hidden('native_project_revision',$native_project_files['revision']);echo form_hidden('native_request_key',bin2hex(random_bytes(32))); ?>
            <div class="form-group"><label for="native-project-file"><?php echo _l('project_files'); ?></label><input class="form-control" id="native-project-file" type="file" name="file" required></div>
            <button class="btn btn-primary" type="submit"><?php echo _l('upload_file'); ?></button><?php echo form_close(); ?><hr>
            <?php } ?>
            <div class="table-responsive"><table class="table"><thead><tr><th><?php echo _l('project_file_filename'); ?></th><th><?php echo _l('project_file__filetype'); ?></th><th><?php echo _l('options'); ?></th></tr></thead><tbody>
            <?php foreach($native_project_files['items'] as $file){ ?><tr><td><a href="<?php echo site_url('download/file/project/'.$file['file_key'].'?project_id='.$native_project_view['canonical_id']); ?>"><?php echo e($file['filename']); ?></a></td><td><?php echo e($file['mime']); ?></td><td>
                <?php if($native_project_view['can_edit']){echo form_open(admin_url('projects/remove_file/'.$native_project_view['canonical_id'].'/'.$file['file_key']),['class'=>'tw-inline']);echo form_hidden('_company_context',$native_project_view['context_token']);echo form_hidden('_native_company_csrf',$native_project_view['csrf_token']);echo form_hidden('native_project_revision',$native_project_files['revision']);echo form_hidden('native_request_key',bin2hex(random_bytes(32))); ?><button class="btn btn-danger btn-xs" type="submit"><?php echo _l('delete'); ?></button><?php echo form_close(); } ?>
            </td></tr><?php } ?>
            <?php if(!$native_project_files['items']){ ?><tr><td colspan="3"><?php echo _l('no_files_found'); ?></td></tr><?php } ?>
            </tbody></table></div>
        </div></div>
    <?php } ?>
</div></div></div></div>
<?php init_tail(); ?></body></html><?php return; } ?>
<?php init_head(); ?>
<div id="wrapper">
    <?= form_hidden('project_id', $project->id) ?>
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="-tw-mt-3">
                    <?php
               if ((staff_can('create', 'projects') || staff_can('edit', 'projects'))
                 && $project->status == 1
                 && $this->projects_model->timers_started_for_project($project->id)
                 && $tab['slug'] != 'project_milestones') {
                   ?>
                    <div class="alert alert-warning project-no-started-timers-found mbot15">
                        <?= _l('project_not_started_status_tasks_timers_found'); ?>
                    </div>
                    <?php } ?>
                    <?php
               if ($project->deadline && date('Y-m-d') > $project->deadline
                && $project->status == 2
                && $tab['slug'] != 'project_milestones') {
                   ?>
                    <div class="alert alert-warning bold project-due-notice mbot15">
                        <?= _l('project_due_notice', floor((abs(time() - strtotime($project->deadline))) / (60 * 60 * 24))); ?>
                    </div>
                    <?php } ?>
                    <?php
               if (! has_contact_permission('projects', get_primary_contact_user_id($project->clientid))
                 && total_rows(db_prefix() . 'contacts', ['userid' => $project->clientid]) > 0
                 && $tab['slug'] != 'project_milestones') {
                   ?>
                    <div class="alert alert-warning project-permissions-warning mbot15">
                        <?= _l('project_customer_permission_warning'); ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-12">
                <div class="_buttons">
                    <div class="row">
                        <div class="col-md-7 project-heading">
                            <div class="tw-flex tw-flex-wrap tw-items-center">
                                <h3 class="hide project-name">
                                    <?= e($project->name); ?>
                                </h3>
                                <div id="project_view_name"
                                    class="ltr:tw-mr-5 rtl:tw-ml-5 tw-min-w-[250px] tw-max-w-[350px]">
                                    <div class="tw-w-full">
                                        <select class="selectpicker" id="project_top" data-width="100%"
                                            <?php if (count($other_projects) > 6) { ?>
                                            data-live-search="true"
                                            <?php } ?>>
                                            <option
                                                value="<?= e($project->id); ?>"
                                                selected
                                                data-content="<?= e($project->name); ?> - <small><?= e($project->client_data->company); ?></small>">
                                                <?= e($project->client_data->company); ?>
                                                <?= e($project->name); ?>
                                            </option>
                                            <?php foreach ($other_projects as $op) { ?>
                                            <option
                                                value="<?= e($op['id']); ?>"
                                                data-subtext="<?= e($op['company']); ?>">
                                                #<?= e($op['id']); ?>
                                                -
                                                <?= e($op['name']); ?>
                                            </option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="visible-xs">
                                    <div class="clearfix"></div>
                                </div>

                                <div class="tw-items-center tw-space-x-3 tw-inline-flex rtl:tw-space-x-reverse">
                                    <a href="#" data-target="#add-edit-members" data-toggle="modal" class="text-muted">
                                        <i class="fa-regular fa-user"></i>
                                    </a>
                                    <div class="tw-flex -tw-space-x-1">
                                        <?php foreach ($members as $member) { ?>
                                        <span class="tw-group tw-relative"
                                            data-title="<?= e(get_staff_full_name($member['staff_id']) . (staff_can('create', 'projects') || $member['staff_id'] == get_staff_user_id() ? ' - ' . _l('total_logged_hours_by_staff') . ': ' . e(seconds_to_time_format($member['total_logged_time'])) : '')); ?>"
                                            data-toggle="tooltip">
                                            <?php if (staff_can('edit', 'projects')) { ?>
                                            <a href="<?= admin_url('projects/remove_team_member/' . $project->id . '/' . $member['staff_id']); ?>"
                                                class="<?= isset($native_project_view) ? 'native-project-member-remove' : '_delete'; ?> group-hover:tw-inline-flex tw-hidden tw-rounded-full tw-absolute tw-items-center tw-justify-center tw-bg-neutral-300/50 tw-h-7 tw-w-7 tw-cursor-pointer tw-text-neutral-500 hover:tw-text-neutral-600 focus:tw-text-neutral-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                                    stroke-width="1.5" stroke="currentColor" class="tw-w-4 tw-h-4">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </a>
                                            <?php } ?>
                                            <?= staff_profile_image($member['staff_id'], ['tw-inline-block tw-h-7 tw-w-7 tw-rounded-full tw-ring-2 tw-ring-white', '']); ?>
                                        </span>
                                        <?php } ?>
                                    </div>
                                </div>
                                <?= '<span class="ltr:tw-ml-4 rtl:tw-mr-4 project_status tw-inline-block label project-status-' . $project->status . '" style="color:' . $project_status['color'] . ';border:1px solid ' . adjust_hex_brightness($project_status['color'], 0.4) . ';background: ' . adjust_hex_brightness($project_status['color'], 0.04) . ';">' . e($project_status['name']) . '</span>'; ?>
                            </div>
                        </div>
                        <div class="col-md-5 text-right tw-space-x-1">
                            <?php if (staff_can('create', 'tasks')) { ?>
                            <a href="#"
                                onclick="new_task_from_relation(undefined,'project',<?= e($project->id); ?>); return false;"
                                class="btn btn-primary">
                                <i class="fa-regular fa-plus tw-mr-1"></i>
                                <?= _l('new_task'); ?>
                            </a>
                            <?php } ?>
                            <?php $invoice_func = 'pre_invoice_project'; ?>
                            <?php if (staff_can('create', 'invoices')) { ?>
                            <a href="#"
                                onclick="<?= e($invoice_func); ?>(<?= e($project->id); ?>); return false;"
                                class="invoice-project btn btn-primary<?= $project->client_data->active == 0 ? ' disabled' : ''; ?>">
                                <i class="fa-solid fa-file-invoice tw-mr-1"></i>
                                <?= _l('invoice_project'); ?>
                            </a>
                            <?php } ?>
                            <?php $project_pin_tooltip = _l('pin_project'); ?>
                            <?php if (total_rows(db_prefix() . 'pinned_projects', ['staff_id' => get_staff_user_id(), 'project_id' => $project->id]) > 0) {
                                $project_pin_tooltip = _l('unpin_project');
                            } ?>
                            <div class="btn-group">
                                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <?= _l('more'); ?>
                                    <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right width200 project-actions">
                                    <li>
                                        <a
                                            href="<?= admin_url('projects/pin_action/' . $project->id); ?>">
                                            <?= e($project_pin_tooltip); ?>
                                        </a>
                                    </li>
                                    <?php if (staff_can('edit', 'projects')) { ?>
                                    <li>
                                        <a
                                            href="<?= admin_url('projects/project/' . $project->id); ?>">
                                            <?= _l('edit_project'); ?>
                                        </a>
                                    </li>
                                    <?php } ?>
                                    <?php if (staff_can('create', 'projects')) { ?>
                                    <li>
                                        <a href="#" onclick="copy_project(); return false;">
                                            <?= _l('copy_project'); ?>
                                        </a>
                                    </li>
                                    <?php } ?>
                                    <?php if (staff_can('create', 'projects') || staff_can('edit', 'projects')) { ?>
                                    <li class="divider"></li>
                                    <?php foreach ($statuses as $status) {
                                        if ($status['id'] == $project->status) {
                                            continue;
                                        } ?>
                                    <li>
                                        <a href="#"
                                            data-name="<?= _l('project_status_' . $status['id']); ?>"
                                            onclick="project_mark_as_modal(<?= e($status['id']); ?>,<?= e($project->id); ?>, this); return false;"><?= e(_l('project_mark_as', $status['name'])); ?></a>
                                    </li>
                                    <?php } ?>
                                    <?php } ?>
                                    <li class="divider"></li>
                                    <?php if (staff_can('create', 'projects')) { ?>
                                    <li>
                                        <a href="<?= admin_url('projects/export_project_data/' . $project->id); ?>"
                                            target="_blank"><?= _l('export_project_data'); ?></a>
                                    </li>
                                    <?php } ?>
                                    <?php if (is_admin()) { ?>
                                    <li>
                                        <a href="<?= admin_url('projects/view_project_as_client/' . $project->id . '/' . $project->clientid); ?>"
                                            target="_blank"><?= _l('project_view_as_client'); ?></a>
                                    </li>
                                    <?php } ?>
                                    <?php if (staff_can('delete', 'projects')) { ?>
                                    <li>
                                        <a href="<?= admin_url('projects/delete/' . $project->id); ?>"
                                            class="_delete">
                                            <span
                                                class="text-danger"><?= _l('delete_project'); ?></span>
                                        </a>
                                    </li>
                                    <?php } ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="project-menu-panel tw-mt-3 tw-mb-4">
                    <?php hooks()->do_action('before_render_project_view', $project->id); ?>
                    <?php $this->load->view('admin/projects/project_tabs'); ?>
                </div>
                <?php $this->load->view(($tab ? $tab['view'] : 'admin/projects/project_overview')); ?>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<div class="modal fade" id="add-edit-members" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <?= form_open(admin_url('projects/add_edit_members/' . $project->id)); ?>
        <?php if (isset($native_project_view)) { echo form_hidden('_company_context', $native_project_view['context_token']); echo form_hidden('_native_company_csrf', $native_project_view['csrf_token']); echo form_hidden('native_members_revision', $native_project_view['members_revision']); } ?>
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                        aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">
                    <?= _l('project_members'); ?>
                </h4>
            </div>
            <div class="modal-body">
                <?php
            $selected = [];

foreach ($members as $member) {
    array_push($selected, $member['staff_id']);
}
echo render_select('project_members[]', $staff, ['staffid', ['firstname', 'lastname']], 'project_members', $selected, ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default"
                    data-dismiss="modal"><?= _l('close'); ?></button>
                <button type="submit" class="btn btn-primary" autocomplete="off"
                    data-loading-text="<?= _l('wait_text'); ?>"><?= _l('submit'); ?></button>
            </div>
        </div>
        <!-- /.modal-content -->
        <?= form_close(); ?>
    </div>
    <!-- /.modal-dialog -->
</div>
<!-- /.modal -->
<?php if (isset($discussion)) {
    echo form_hidden('discussion_id', $discussion->id);
    echo form_hidden('discussion_user_profile_image_url', $discussion_user_profile_image_url);
    echo form_hidden('current_user_is_admin', $current_user_is_admin);
}
echo form_hidden('project_percent', $percent);
?>
<div id="invoice_project"></div>
<div id="pre_invoice_project"></div>
<?php $this->load->view('admin/projects/milestone'); ?>
<?php $this->load->view('admin/projects/copy_settings'); ?>
<?php $this->load->view('admin/projects/_mark_tasks_finished'); ?>
<?php init_tail(); ?>
<!-- For invoices table -->
<script>
    taskid = '<?= $this->input->get('taskid'); ?>';
</script>
<script>
    var gantt_data = {};
    <?php if (isset($gantt_data)) { ?>
    gantt_data = <?= json_encode($gantt_data); ?> ;
    <?php } ?>
    var discussion_id = $('input[name="discussion_id"]').val();
    var discussion_user_profile_image_url = $('input[name="discussion_user_profile_image_url"]').val();
    var current_user_is_admin = $('input[name="current_user_is_admin"]').val();
    var project_id = $('input[name="project_id"]').val();
    if (typeof(discussion_id) != 'undefined') {
        discussion_comments('#discussion-comments', discussion_id, 'regular');
    }
    $(function() {
        var project_progress_color =
            '<?= hooks()->apply_filters('admin_project_progress_color', '#84c529'); ?>';
        var circle = $('.project-progress').circleProgress({
            fill: {
                gradient: [project_progress_color, project_progress_color]
            }
        }).on('circle-animation-progress', function(event, progress, stepValue) {
            $(this).find('strong.project-percent').html(parseInt(100 * stepValue) + '<i>%</i>');
        });
    });

    function discussion_comments(selector, discussion_id, discussion_type) {
        var defaults = _get_jquery_comments_default_config(
            <?= json_encode(get_project_discussions_language_array()); ?>
        );
        var options = {
            // https://github.com/Viima/jquery-comments/pull/169
            wysiwyg_editor: {
                opts: {
                    enable: true,
                    is_html: true,
                    container_id: 'editor-container',
                    comment_index: 0,
                },
                init: function(textarea, content) {
                    var comment_index = textarea.data('comment_index');
                    var editorConfig = _simple_editor_config();
                    editorConfig.setup = function(ed) {
                        initializeTinyMceMentions(ed, function() {
                            return $.getJSON(admin_url + 'projects/get_staff_names_for_mentions/' +
                                project_id)
                        })

                        textarea.data('wysiwyg_editor', ed);

                        ed.on('change', function() {
                            var value = ed.getContent();
                            if (value !== ed._lastChange) {
                                ed._lastChange = value;
                                textarea.trigger('change');
                            }
                        });

                        ed.on('keyup', function() {
                            var value = ed.getContent();
                            if (value !== ed._lastChange) {
                                ed._lastChange = value;
                                textarea.trigger('change');
                            }
                        });

                        ed.on('Focus', function(e) {
                            setTimeout(function() {
                                textarea.trigger('click');
                            }, 500)
                        });

                        ed.on('init', function() {
                            if (content) ed.setContent(content);
                        })
                    }

                    editorConfig.content_style = 'span.mention {\
                     background-color: #eeeeee;\
                     padding: 3px;\
                }';

                    var containerId = this.get_container_id(comment_index);
                    tinyMCE.remove('#' + containerId);

                    setTimeout(function() {
                        init_editor('#' + containerId, editorConfig)
                    }, 100)
                },
                get_container: function(textarea) {
                    if (!textarea.data('comment_index')) {
                        textarea.data('comment_index', ++this.opts.comment_index);
                    }

                    return $('<div/>', {
                        'id': this.get_container_id(this.opts.comment_index)
                    });
                },
                get_contents: function(editor) {
                    return editor.getContent();
                },
                on_post_comment: function(editor, evt) {
                    editor.setContent('');
                },
                get_container_id: function(comment_index) {
                    var container_id = this.opts.container_id;
                    if (comment_index) container_id = container_id + "-" + comment_index;
                    return container_id;
                }
            },
            currentUserIsAdmin: current_user_is_admin,
            getComments: function(success, error) {
                $.get(admin_url + 'projects/get_discussion_comments/' + discussion_id + '/' + discussion_type,
                    function(response) {
                        success(response);
                    }, 'json');
            },
            postComment: function(commentJSON, success, error) {
                $.ajax({
                    type: 'post',
                    url: admin_url + 'projects/add_discussion_comment/' + discussion_id + '/' +
                        discussion_type,
                    data: commentJSON,
                    success: function(comment) {
                        comment = JSON.parse(comment);
                        success(comment)
                    },
                    error: error
                });
            },
            putComment: function(commentJSON, success, error) {
                $.ajax({
                    type: 'post',
                    url: admin_url + 'projects/update_discussion_comment',
                    data: commentJSON,
                    success: function(comment) {
                        comment = JSON.parse(comment);
                        success(comment)
                    },
                    error: error
                });
            },
            deleteComment: function(commentJSON, success, error) {
                $.ajax({
                    type: 'post',
                    url: admin_url + 'projects/delete_discussion_comment/' + commentJSON.id,
                    success: success,
                    error: error
                });
            },
            uploadAttachments: function(commentArray, success, error) {
                var responses = 0;
                var successfulUploads = [];
                var serverResponded = function() {
                    responses++;
                    // Check if all requests have finished
                    if (responses == commentArray.length) {
                        // Case: all failed
                        if (successfulUploads.length == 0) {
                            error();
                            // Case: some succeeded
                        } else {
                            successfulUploads = JSON.parse(successfulUploads);
                            success(successfulUploads)
                        }
                    }
                }
                $(commentArray).each(function(index, commentJSON) {
                    // Create form data
                    var formData = new FormData();
                    if (commentJSON.file.size && commentJSON.file.size > app
                        .max_php_ini_upload_size_bytes) {
                        alert_float('danger',
                            "<?= _l('file_exceeds_max_filesize'); ?>"
                        );
                        serverResponded();
                    } else {
                        $(Object.keys(commentJSON)).each(function(index, key) {
                            var value = commentJSON[key];
                            if (value) formData.append(key, value);
                        });

                        if (typeof(csrfData) !== 'undefined') {
                            formData.append(csrfData['token_name'], csrfData['hash']);
                        }
                        $.ajax({
                            url: admin_url + 'projects/add_discussion_comment/' +
                                discussion_id +
                                '/' + discussion_type,
                            type: 'POST',
                            data: formData,
                            cache: false,
                            contentType: false,
                            processData: false,
                            success: function(commentJSON) {
                                successfulUploads.push(commentJSON);
                                serverResponded();
                            },
                            error: function(data) {
                                var error = JSON.parse(data.responseText);
                                alert_float('danger', error.message);
                                serverResponded();
                            },
                        });
                    }
                });
            }
        }
        var settings = $.extend({}, defaults, options);
        $(selector).comments(settings);
    }
</script>
</body>

</html>
