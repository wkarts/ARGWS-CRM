<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$taskValues=$native_settings_task_values??null;
$taskOption=static fn(string$key):string=>is_array($taskValues)?$taskValues[$key]:get_option($key);
$taskBoolean=static function(...$args)use($taskValues):void{if(is_array($taskValues)){$args=array_pad($args,7,'');$args[]=$taskValues[$args[0]];}render_yes_no_option(...$args);};
if(is_array($taskValues)){
 require_once APPPATH.'services/TaskPriorityPresentation.php';
 $nativeTaskPriorities=TaskPriorityPresentation::definitions('_l');
 $nativeRoundOptions=[];foreach(['task_timer_dont_round_off','task_timer_round_up','task_timer_round_down','task_timer_round_nearest']as$id=>$key)$nativeRoundOptions[]=['id'=>$id,'name'=>_l($key)];
}
?>
<?php echo render_input('settings[tasks_kanban_limit]','tasks_kanban_limit',$taskOption('tasks_kanban_limit'),'number'); ?>
<hr />
<?php echo $taskBoolean('show_all_tasks_for_project_member','show_all_tasks_for_project_member'); ?>
<hr />
<?php $taskBoolean('client_staff_add_edit_delete_task_comments_first_hour','settings_client_staff_add_edit_delete_task_comments_first_hour'); ?>
<hr />
<?php $taskBoolean('new_task_auto_assign_current_member','new_task_auto_assign_current_member','new_task_auto_assign_current_member_help'); ?>
<hr />
<?php $taskBoolean('new_task_auto_follower_current_member','new_task_auto_follower_current_member'); ?>
<hr />
<?php $taskBoolean('auto_stop_tasks_timers_on_new_timer','auto_stop_tasks_timers_on_new_timer'); ?>
<hr />
<?php $taskBoolean('timer_started_change_status_in_progress','timer_started_change_status_in_progress'); ?>
<hr />
<?php $taskBoolean('task_biillable_checked_on_creation','task_biillable_checked_on_creation'); ?>
<hr />
<div class="row">
  <div class="col-md-6">
      <label for="round_off_task_timer_option" class="control-label"><?php echo _l('round_off_task_timer_option'); ?></label>
      <select name="settings[round_off_task_timer_option]" class="selectpicker" id="round_off_task_timer_option" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
        <?php
        foreach((is_array($taskValues)?$nativeRoundOptions:get_task_timer_round_off_options()) as $options){?>
          <option value="<?php echo e($options['id']); ?>"<?php if($options['id'] == $taskOption('round_off_task_timer_option')){echo ' selected';}; ?>>
            <?php echo e($options['name']); ?>
          </option>
        <?php } ?>
      </select>
  </div>
  <div class="col-md-6">
    <div class="row">
      <div class="col-md-3 mtop35 no-padding text-lowercase">
        <?php echo _l('multiplies_of'); ?>
      </div>
      <div class="col-md-6 no-padding">
        <div class="mtop25">
          <select name="settings[round_off_task_timer_time]" class="selectpicker" id="round_off_task_timer_time" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
            <?php
            foreach((is_array($taskValues)?[5,10,15,20,25,30,35,40,45]:get_task_timer_round_off_times()) as $option){?>
              <option value="<?php echo e($option); ?>"<?php if($option == $taskOption('round_off_task_timer_time')){echo ' selected';}; ?>>
                <?php echo $option ?>
              </option>
            <?php } ?>
          </select>
        </div>
      </div>
      <div class="col-md-3 mtop35 text-lowercase">
        <?php echo _l('minutes'); ?>
      </div>
    </div>
  </div>
  <div class="col-md-12 mtop5">
      Applied to the Timesheets overview report and when invoicing a task/project.
  </div>
</div>
<hr />
<div class="form-group">
  <label for="default_task_status" class="control-label"><?php echo _l('default_task_status'); ?></label>
  <select name="settings[default_task_status]" class="selectpicker" id="default_task_status" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
    <option value="auto" <?php if($taskOption('default_task_status') == 'auto'){echo 'selected';} ?>><?php echo _l('auto'); ?></option>
    <?php foreach($task_statuses as $status){ ?>
      <option value="<?php echo e($status['id']); ?>"<?php if($status['id'] == $taskOption('default_task_status')){echo ' selected';}; ?>>
        <?php echo e($status['name']); ?>
      </option>
    <?php } ?>
  </select>
</div>
<hr />
<div class="form-group">
  <label for="default_task_priority" class="control-label"><?php echo _l('default_task_priority'); ?></label>
  <select name="settings[default_task_priority]" class="selectpicker" id="default_task_priority" data-width="100%" data-none-selected-text="<?php echo _l('dropdown_non_selected_tex'); ?>">
    <?php foreach((is_array($taskValues)?$nativeTaskPriorities:get_tasks_priorities()) as $priority) { ?>
      <option value="<?php echo e($priority['id']); ?>"<?php if($taskOption('default_task_priority') == $priority['id']){echo ' selected';} ?>><?php echo e($priority['name']); ?>
    </option>
  <?php } ?>
</select>
</div>
<hr />
<div class="form-group">
  <label for="settings[task_modal_class]" class="control-label">
    <?php echo _l('modal_width_class'); ?> (modal-lg, modal-xl, modal-xxl)
  </label>
  <input type="text" id="settings[task_modal_class]" name="settings[task_modal_class]" class="form-control" value="<?php echo e($taskOption('task_modal_class')); ?>">
</div>

