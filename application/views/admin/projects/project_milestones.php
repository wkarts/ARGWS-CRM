<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if(isset($native_project_view)){ ?><div class="panel_s"><div class="panel-body">
<?php if($native_project_view['can_edit']){ $basic=$milestone_state['selected']['milestone']??null;echo form_open(admin_url('projects/milestone/'.($basic['id']??'')),['id'=>'project-milestone-form']);foreach(['action'=>'milestone','company_project_rules_csrf'=>$company_project_rules_csrf,'request_key'=>bin2hex(random_bytes(32)),'project_id'=>$native_project_view['canonical_id'],'project_revision'=>$milestone_state['project']['revision']]as$name=>$value)echo form_hidden($name,$value); ?>
<h4><?= $basic?'Editar marco':'Novo marco'; ?></h4><label>Nome<input class="form-control" name="name" maxlength="191" required value="<?= e($basic['name']??''); ?>"></label><label>Prazo<input class="form-control" type="date" name="due_date" required value="<?= e($basic['due_date']??''); ?>"></label><label><input type="checkbox" name="done" value="1" <?= ($basic['done']??0)?'checked':''; ?>> Concluído</label><button class="btn btn-primary" type="submit">Salvar marco</button><?= form_close(); ?><?php }require __DIR__.'/native_milestone_rules.php'; ?></div></div><?php return; } ?>
<?php if (staff_can('create_milestones', 'projects')) { ?>
<a href="#" class="btn btn-primary" onclick="new_milestone();return false;">
    <i class="fa-regular fa-plus tw-mr-1"></i>
    <?= _l('new_milestone'); ?>
</a>
<?php } ?>
<a href="#" class="btn btn-default" onclick="milestones_switch_view(); return false;"><i class="fa fa-th-list"></i></a>
<?php if ($milestones_found) { ?>
<div id="kanban-params" class="pull-right">
    <div class="checkbox">
        <input type="checkbox" value="yes" id="exclude_completed_tasks" name="exclude_completed_tasks" <?php if ($milestones_exclude_completed_tasks) {
            echo ' checked';
        } ?> onclick="window.location.href =
        '<?= admin_url('projects/view/' . $project->id . '?group=project_milestones&exclude_completed='); ?>'+(this.checked
        ? 'yes' : 'no')">
        <label
            for="exclude_completed_tasks"><?= _l('exclude_completed_tasks') ?></label>
    </div>
    <div class="clearfix"></div>
    <?= form_hidden('project_id', $project->id); ?>
</div>
<div class="clearfix"></div>
<?php } ?>
<?php if ($milestones_found) { ?>
<div class="project-milestones-kanban tw-mt-0">
    <div class="kan-ban-tab" id="kan-ban-tab" style="overflow:auto;">
        <div class="row">
            <div class="container-fluid">
                <div id="kan-ban"></div>
            </div>
        </div>
    </div>
</div>
<?php } else { ?>
<div class="alert alert-info mtop15 no-mbot">
    <?= _l('no_milestones_found'); ?>
</div>
<?php } ?>
<div id="milestones-table" class="hide tw-mt-5">
    <div class="panel_s panel-table-full">
        <div class="panel-body">
            <?php render_datatable([
                        _l('milestone_name'),
                        _l('milestone_start_date'),
                        _l('milestone_due_date'),
                        _l('milestone_description'),
                    ], 'milestones'); ?>
        </div>
    </div>
</div>