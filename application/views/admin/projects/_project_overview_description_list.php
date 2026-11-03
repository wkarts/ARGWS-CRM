<?php $nativeOverview=$native_project_overview??null; if($nativeOverview!==null){$nativeFacts=$nativeOverview['facts'];$nativeFinance=$nativeOverview['finance'];$nativeCurrency=$nativeOverview['currency'];} ?>
<dl class="tw-grid tw-grid-cols-1 tw-gap-x-4 tw-gap-y-3 sm:tw-grid-cols-2">
    <div class="sm:tw-col-span-1 project-overview-id">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project'); ?>
            <?= _l('the_number_sign'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e($nativeOverview!==null?$nativeFacts['reference']['canonical_id']:$project->id); ?>
        </dd>
    </div>

    <div class="sm:tw-col-span-1 project-overview-customer">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_customer'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <a
                href="<?= admin_url(); ?>clients/client/<?= e($nativeOverview!==null?$nativeFacts['customer']['canonical_id']:$project->clientid); ?>">
                <?= e($nativeOverview!==null?$nativeFacts['customer']['name']:$project->client_data->company); ?>
            </a>
        </dd>
    </div>

    <?php if ($nativeOverview!==null ? $nativeFinance!==null : staff_can('edit', 'projects')) { ?>
    <div class="sm:tw-col-span-1 project-overview-billing">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_billing_type'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?php $billingType=$nativeOverview!==null?(int)$nativeFinance['billing_type']:$project->billing_type; if ($billingType == 1) {
                $type_name = 'project_billing_type_fixed_cost';
            } elseif ($billingType == 2) {
                $type_name = 'project_billing_type_project_hours';
            } else {
                $type_name = 'project_billing_type_project_task_hours';
            } ?>
            <?= _l($type_name); ?>
        </dd>
    </div>
    <?php if ($billingType == 1 || $billingType == 2) { ?>
    <div class="sm:tw-col-span-1 project-overview-amount">
        <?php if ($billingType == 1) { ?>
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_total_cost'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e(app_format_money($nativeOverview!==null?$nativeFinance['project_cost']:$project->project_cost, $nativeOverview!==null?(object)$nativeCurrency:$currency)); ?>
        </dd>
        <?php } else { ?>
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_rate_per_hour'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e(app_format_money($nativeOverview!==null?$nativeFinance['project_rate_per_hour']:$project->project_rate_per_hour, $nativeOverview!==null?(object)$nativeCurrency:$currency)); ?>
        </dd>
        <?php } ?>
    </div>
    <?php } ?>
    <?php } ?>

    <div class="sm:tw-col-span-1 project-overview-status">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_status'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e($nativeOverview!==null?$nativeOverview['status']['name']:$project_status['name']); ?>
        </dd>
    </div>

    <div class="sm:tw-col-span-1 project-overview-date-created">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_datecreated'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e($nativeOverview!==null?$nativeOverview['date_labels']['created_at']:_d($project->project_created)); ?>
        </dd>
    </div>
    <div class="sm:tw-col-span-1 project-overview-start-date">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_start_date'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e($nativeOverview!==null?$nativeOverview['date_labels']['start_date']:_d($project->start_date)); ?>
        </dd>
    </div>
    <?php if ($nativeOverview!==null?$nativeOverview['date_labels']['due_date']!=='':$project->deadline) { ?>
    <div class="sm:tw-col-span-1 project-overview-deadline">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_deadline'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e($nativeOverview!==null?$nativeOverview['date_labels']['due_date']:_d($project->deadline)); ?>
        </dd>
    </div>
    <?php } ?>

    <?php if ($nativeOverview===null && $project->date_finished) { ?>
    <div class="sm:tw-col-span-1 project-overview-date-finished">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_completed_date'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm text-success">
            <?= e(_dt($project->date_finished)); ?>
        </dd>
    </div>
    <?php } ?>

    <?php $estimated=$nativeOverview!==null?($nativeFinance['estimated_hours']??null):$project->estimated_hours; if ($estimated && $estimated != '0') { ?>
    <div class="sm:tw-col-span-1 project-overview-estimated-hours">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('estimated_hours'); ?>
        </dt>
        <dd
            class="tw-mt-1 tw-text-sm <?= ($nativeOverview!==null?$nativeOverview['logged_seconds']!==null&&hours_to_seconds_format($estimated)<$nativeOverview['logged_seconds']:hours_to_seconds_format($estimated)<(int)$project_total_logged_time) ? 'text-warning' : 'text-neutral-900'; ?>">
            <?= e(str_replace('.', ':', $estimated)); ?>
        </dd>
    </div>
    <?php } ?>

    <?php if($nativeOverview===null||$nativeOverview['logged_seconds']!==null){ ?><div class="sm:tw-col-span-1 project-overview-total-logged-hours">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('project_overview_total_logged_hours'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= e(seconds_to_time_format($nativeOverview!==null?$nativeOverview['logged_seconds']:$project_total_logged_time)); ?>
        </dd>
    </div><?php } ?>
    <?php $custom_fields = $nativeOverview!==null?$nativeFacts['custom_definitions']:get_custom_fields('projects'); ?>
    <?php if (count($custom_fields) > 0) { ?>
    <?php foreach ($custom_fields as $field) { ?>
    <?php $value = $nativeOverview!==null?($nativeFacts['custom_values'][(string)$field['id']]??''):get_custom_field_value($project->id, $field['id'], 'projects');
        if ($value == '') {
            continue;
        } ?>
    <div class="sm:tw-col-span-1">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= e(ucfirst($field['name'])); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?= $nativeOverview!==null?e($value):$value; ?>
        </dd>
    </div>
    <?php } ?>
    <?php } ?>

    <?php $tags = $nativeOverview!==null?$nativeOverview['tags']:get_tags_in($project->id, 'project'); ?>
    <?php if (count($tags) > 0) { ?>
    <div class="sm:tw-col-span-1 project-overview-tags">
        <dt class="tw-text-sm tw-font-normal tw-text-neutral-500">
            <?= _l('tags'); ?>
        </dt>
        <dd class="tw-mt-1 tw-text-sm tw-text-neutral-700 tw-font-medium">
            <?php if($nativeOverview!==null){require_once APPPATH.'services/TagPresentation.php';echo TagPresentation::render($tags);}else echo render_tags($tags); ?>
        </dd>
    </div>
    <?php } ?>
    <div class="clearfix"></div>
    <div class="sm:tw-col-span-2 project-overview-description tc-content">
        <dt class="tw-text-sm tw-font-medium tw-text-neutral-600">
            <?= _l('project_description'); ?>
        </dt>
        <dd class="tw-mt-1 tw-space-y-5 tw-text-sm tw-text-neutral-500">
            <?php $description=$nativeOverview!==null?$nativeFacts['project']['description']:$project->description; if ($nativeOverview!==null?$description==='':empty($description)) { ?>
            <p class="tw-text-neutral-400 tw-mb-0">
                <?= _l('no_description_project'); ?>
            </p>
            <?php } ?>
            <?= $nativeOverview!==null?nl2br(e($description)):check_for_links($description); ?>
        </dd>
    </div>
</dl>
