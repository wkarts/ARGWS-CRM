<?php
$estimateValues = $native_settings_estimate_values ?? null;
$estimateOption = static fn(string $key): string => is_array($estimateValues) ? $estimateValues[$key] : get_option($key);
$estimateBoolean = static function(...$args) use ($estimateValues): void {
    if (is_array($estimateValues)) { $args = array_pad($args, 7, ''); $args[] = $estimateValues[$args[0]]; }
    render_yes_no_option(...$args);
};
?>
<style>
@media (max-width: 767px) {
    .sales-settings-form .radio-inline { max-width: 100%; vertical-align: top; }
    .sales-settings-form label, .sales-settings-form a { overflow-wrap: anywhere; word-break: break-word; }
}
</style>
<div class="sales-settings-form">
<div class="form-group">
    <label class="control-label"
        for="estimate_prefix"><?= _l('settings_sales_estimate_prefix'); ?></label>
    <input type="text" name="settings[estimate_prefix]" class="form-control"
        value="<?= htmlspecialchars((string) $estimateOption('estimate_prefix'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
</div>
<hr />
<i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip"
    data-title="<?= _l('settings_sales_next_estimate_number_tooltip'); ?>"></i>
<?= render_input('settings[next_estimate_number]', 'settings_sales_next_estimate_number', $estimateOption('next_estimate_number'), 'number', ['min' => 1]); ?>
<hr />

<i class="fa-regular fa-circle-question pull-left tw-mt-0.5 tw-mr-1" data-toggle="tooltip"
    data-title="<?= _l('invoice_due_after_help'); ?>"></i>
<?= render_input('settings[estimate_due_after]', 'estimate_due_after', $estimateOption('estimate_due_after')); ?>
<hr />
<?php $estimateBoolean('delete_only_on_last_estimate', 'settings_delete_only_on_last_estimate'); ?>
<hr />
<?php $estimateBoolean('estimate_number_decrement_on_delete', 'settings_sales_decrement_estimate_number_on_delete', 'settings_sales_decrement_estimate_number_on_delete_tooltip'); ?>
<hr />
<?= $estimateBoolean('allow_staff_view_estimates_assigned', 'allow_staff_view_estimates_assigned'); ?>
<hr />

<?php $estimateBoolean('view_estimate_only_logged_in', 'settings_sales_require_client_logged_in_to_view_estimate'); ?>
<hr />
<?php $estimateBoolean('show_sale_agent_on_estimates', 'settings_show_sale_agent_on_estimates'); ?>
<hr />
<?php $estimateBoolean('show_project_on_estimate', 'show_project_on_estimate'); ?>
<hr />
<?php $estimateBoolean('estimate_auto_convert_to_invoice_on_client_accept', 'settings_estimate_auto_convert_to_invoice_on_client_accept'); ?>
<hr />
<?php $estimateBoolean('exclude_estimate_from_client_area_with_draft_status', 'settings_exclude_estimate_from_client_area_with_draft_status'); ?>
<hr />
<div class="form-group">
    <label for="estimate_number_format"
        class="control-label clearfix"><?= _l('settings_sales_estimate_number_format'); ?></label>
    <div class="radio radio-primary radio-inline">
        <input type="radio" name="settings[estimate_number_format]" value="1" id="e_number_based"
            <?= $estimateOption('estimate_number_format') == '1' ? 'checked' : '' ?>>
        <label
            for="e_number_based"><?= _l('settings_sales_estimate_number_format_number_based'); ?></label>
    </div>
    <div class="radio radio-primary radio-inline">
        <input type="radio" name="settings[estimate_number_format]" value="2" id="e_year_based"
            <?= $estimateOption('estimate_number_format') == '2' ? 'checked' : '' ?>>
        <label
            for="e_year_based"><?= _l('settings_sales_estimate_number_format_year_based'); ?>
            (YYYY/000001)</label>
    </div>
    <div class="radio radio-primary radio-inline">
        <input type="radio" name="settings[estimate_number_format]" value="3" id="e_short_year_based"
            <?= $estimateOption('estimate_number_format') == '3' ? 'checked' : '' ?>>
        <label for="e_short_year_based">000001-YY</label>
    </div>
    <div class="radio radio-primary radio-inline">
        <input type="radio" name="settings[estimate_number_format]" value="4" id="e_year_month_based"
            <?= $estimateOption('estimate_number_format') == '4' ? 'checked' : '' ?>>
        <label for="e_year_month_based">000001/MM/YYYY</label>
    </div>
    <hr />
</div>
<div class="row">
    <div class="col-md-12">
        <?= render_input('settings[estimates_pipeline_limit]', 'pipeline_limit_status', $estimateOption('estimates_pipeline_limit')); ?>
    </div>
    <div class="col-md-7">
        <label for="default_proposals_pipeline_sort"
            class="control-label"><?= _l('default_pipeline_sort'); ?></label>
        <select name="settings[default_estimates_pipeline_sort]" id="default_estimates_pipeline_sort"
            class="selectpicker" data-width="100%"
            data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
            <option value="datecreated" <?= $estimateOption('default_estimates_pipeline_sort') == 'datecreated' ? 'selected' : '' ?>>
                <?= _l('estimates_sort_datecreated'); ?>
            </option>
            <option value="date" <?= $estimateOption('default_estimates_pipeline_sort') == 'date' ? 'selected' : '' ?>>
                <?= _l('estimates_sort_estimate_date'); ?>
            </option>
            <option value="pipeline_order" <?= $estimateOption('default_estimates_pipeline_sort') == 'pipeline_order' ? 'selected' : '' ?>>
                <?= _l('estimates_sort_pipeline'); ?>
            </option>
            <option value="expirydate" <?= $estimateOption('default_estimates_pipeline_sort') == 'expirydate' ? 'selected' : '' ?>>
                <?= _l('estimates_sort_expiry_date'); ?>
            </option>
        </select>
    </div>
    <div class="col-md-5">
        <div class="mtop30 text-right">
            <div class="radio radio-inline radio-primary">
                <input type="radio" id="k_desc_estimate" name="settings[default_estimates_pipeline_sort_type]"
                    value="asc"
                    <?= $estimateOption('default_estimates_pipeline_sort_type') == 'asc' ? 'checked' : '' ?>>
                <label
                    for="k_desc_estimate"><?= _l('order_ascending'); ?></label>
            </div>
            <div class="radio radio-inline radio-primary">
                <input type="radio" id="k_asc_estimate" name="settings[default_estimates_pipeline_sort_type]"
                    value="desc"
                    <?= $estimateOption('default_estimates_pipeline_sort_type') == 'desc' ? 'checked' : '' ?>>
                <label
                    for="k_asc_estimate"><?= _l('order_descending'); ?></label>
            </div>
        </div>
    </div>
    <div class="clearfix"></div>
</div>
<hr />
<?= render_textarea('settings[predefined_clientnote_estimate]', 'settings_predefined_clientnote', $estimateOption('predefined_clientnote_estimate'), ['rows' => 6]); ?>
<?= render_textarea('settings[predefined_terms_estimate]', 'settings_predefined_predefined_term', $estimateOption('predefined_terms_estimate'), ['rows' => 6]); ?>
</div>
