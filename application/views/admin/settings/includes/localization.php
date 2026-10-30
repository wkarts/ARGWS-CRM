<?php defined('BASEPATH') or exit('No direct script access allowed');
$date_formats      = get_available_date_formats();
echo form_hidden('settings[localization_settings]', 'true');
?>
<div class="form-group">
    <label for="dateformat"
        class="control-label"><?= _l('settings_localization_date_format'); ?></label>
    <select name="settings[dateformat]" id="dateformat" class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
        <?php foreach ($date_formats as $key => $val) { ?>
        <option value="<?= e($key); ?>" <?php if ($key == get_option('dateformat')) {
            echo 'selected';
        } ?>><?= e($val); ?>
        </option>
        <?php } ?>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="time_format"
        class="control-label"><?= _l('time_format'); ?></label>
    <select name="settings[time_format]" id="time_format" class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>">
        <option value="24" <?php if (get_option('time_format') == '24') {
            echo 'selected';
        } ?>><?= _l('time_format_24'); ?>
        </option>
        <option value="12" <?php if (get_option('time_format') == '12') {
            echo 'selected';
        } ?>><?= _l('time_format_12'); ?>
        </option>
    </select>
</div>
<hr />
<div class="form-group">
    <label for="timezones"
        class="control-label"><?= _l('settings_localization_default_timezone'); ?></label>
    <select name="settings[default_timezone]" id="timezones" class="form-control selectpicker"
        data-none-selected-text="<?= _l('dropdown_non_selected_tex'); ?>"
        data-live-search="true">
        <?php foreach (get_timezones_list() as $key => $timezones) { ?>
        <optgroup label="<?= e($key); ?>">
            <?php foreach ($timezones as $timezone) { ?>
            <option value="<?= e($timezone); ?>" <?php if (get_option('default_timezone') == $timezone) {
                echo 'selected';
            } ?>><?= e($timezone); ?>
            </option>
            <?php } ?>
        </optgroup>
        <?php } ?>
    </select>
</div>
<hr />
<p class="text-muted">Idioma do sistema: <strong>Português (Brasil)</strong>. A aplicação não permite seleção de outros idiomas.</p>
<?= form_hidden('settings[active_language]', 'portuguese_br'); ?>
<?= form_hidden('settings[enabled_languages][]', 'portuguese_br'); ?>
<?= form_hidden('settings[disable_language]', '1'); ?>
