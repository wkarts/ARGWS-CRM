<?php
$calendarValues=$native_settings_calendar_values??null;
$calendarOption=static fn(string $key):string=>is_array($calendarValues)?$calendarValues[$key]:get_option($key);
$calendarBoolean=static function(...$args)use($calendarValues):void{if(is_array($calendarValues)){$args=array_pad($args,7,'');$args[]=$calendarValues[$args[0]];}render_yes_no_option(...$args);};
?>
<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="horizontal-scrollable-tabs panel-full-width-tabs">
    <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
    <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
    <div class="horizontal-tabs">
        <ul class="nav nav-tabs nav-tabs-horizontal" role="tablist">
            <li role="presentation" class="active">
                <a href="#general" aria-controls="general" role="tab"
                    data-toggle="tab"><?php echo _l('settings_group_general'); ?></a>
            </li>
            <li role="presentation">
                <a href="#colors" aria-controls="colors" role="tab"
                    data-toggle="tab"><?php echo _l('settings_calendar_colors_heading'); ?></a>
            </li>
        </ul>
    </div>
</div>
<div class="tab-content mtop15">
    <div role="tabpanel" class="tab-pane active" id="general">
        <?php echo render_input('settings[calendar_events_limit]', 'calendar_events_limit', $calendarOption('calendar_events_limit'), 'number'); ?>
        <hr />
        <div class="form-group">
            <label for="settings[default_view_calendar]"><?php echo _l('default_view'); ?></label>
            <br />
            <select class="selectpicker" data-width="100%" name="settings[default_view_calendar]"
                id="default_view_calendar">
                <option value="dayGridMonth" <?php if ($calendarOption('default_view_calendar') == 'dayGridMonth') {
    echo ' selected';
} ?>><?php echo _l('month'); ?></option>
                <option value="dayGridWeek" <?php if ($calendarOption('default_view_calendar') == 'dayGridWeek') {
    echo ' selected';
} ?>><?php echo _l('week'); ?></option>
                <option value="dayGridDay" <?php if ($calendarOption('default_view_calendar') == 'dayGridDay') {
    echo ' selected';
} ?>><?php echo _l('day'); ?></option>
                <option value="timeGridWeek" <?php if ($calendarOption('default_view_calendar') == 'timeGridWeek') {
    echo ' selected';
} ?>><?php echo _l('agenda'); ?> <?php echo _l('week'); ?></option>
                <option value="timeGridDay" <?php if ($calendarOption('default_view_calendar') == 'timeGridDay') {
    echo ' selected';
} ?>><?php echo _l('agenda'); ?> <?php echo _l('day'); ?></option>
            </select>
        </div>
        <hr />
        <label><?php echo _l('calendar_first_day'); ?></label>
        <select name="settings[calendar_first_day]" class="selectpicker" data-width="100%">
            <?php
      $weekdays = get_weekdays();
      end($weekdays);
      $last = key($weekdays);
      foreach ($weekdays as $key => $val) {
          if ($key == $last) {
              $key = 0;
          } else {
              $key = $key + 1;
          } ?>
            <option value="<?php echo e($key); ?>" <?php if ($calendarOption('calendar_first_day') == $key) {
              echo 'selected';
          } ?>><?php echo e($val); ?></option>
            <?php
      } ?>
        </select>
        <hr />
        <h4><?php echo _l('show_on_calendar'); ?></h4>
        <hr />
        <div class="row">
            <div class="col-md-6">
                <?php echo $calendarBoolean('hide_notified_reminders_from_calendar', 'hide_notified_reminders_from_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_lead_reminders_on_calendar', 'show_lead_reminders_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_customer_reminders_on_calendar', 'show_customer_reminders_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_estimate_reminders_on_calendar', 'show_estimate_reminders_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_invoice_reminders_on_calendar', 'show_invoice_reminders_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_proposal_reminders_on_calendar', 'show_proposal_reminders_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_expense_reminders_on_calendar', 'calendar_expense_reminder'); ?>
                <hr />
                <?php $calendarBoolean('show_task_reminders_on_calendar', 'show_task_reminders_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_credit_note_reminders_on_calendar', 'show_credit_note_reminders_on_calendar'); ?>
            </div>
            <div class="col-md-6">
                <?php $calendarBoolean('show_ticket_reminders_on_calendar', 'calendar_ticket_reminder'); ?>
                <br />
                <?php $calendarBoolean('show_invoices_on_calendar', 'show_invoices_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_estimates_on_calendar', 'show_estimates_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_proposals_on_calendar', 'show_proposals_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_contracts_on_calendar', 'show_contracts_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('show_tasks_on_calendar', 'show_tasks_on_calendar'); ?>
                <hr />
                <?php $calendarBoolean('calendar_only_assigned_tasks', 'calendar_only_assigned_tasks'); ?>
                <hr />
                <?php $calendarBoolean('show_projects_on_calendar', 'show_projects_on_calendar'); ?>
            </div>

        </div>
    </div>
    <div role="tabpanel" class="tab-pane" id="colors">

        <?php echo render_color_picker('settings[calendar_invoice_color]', _l('settings_calendar_color', _l('invoice')), $calendarOption('calendar_invoice_color')); ?>
        <?php echo render_color_picker('settings[calendar_estimate_color]', _l('settings_calendar_color', _l('estimate')), $calendarOption('calendar_estimate_color')); ?>
        <?php echo render_color_picker('settings[calendar_proposal_color]', _l('settings_calendar_color', _l('proposal')), $calendarOption('calendar_proposal_color')); ?>
        <?php echo render_color_picker('settings[calendar_reminder_color]', _l('settings_calendar_color', _l('reminder')), $calendarOption('calendar_reminder_color')); ?>
        <?php echo render_color_picker('settings[calendar_contract_color]', _l('settings_calendar_color', _l('contract')), $calendarOption('calendar_contract_color')); ?>
        <?php echo render_color_picker('settings[calendar_project_color]', _l('settings_calendar_color', _l('project')), $calendarOption('calendar_project_color')); ?>

    </div>