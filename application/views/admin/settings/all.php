<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $isNativeSettings=!empty($native_settings)&&class_exists('CompanyRequestScope',false)&&CompanyRequestScope::nativeFor(get_instance()); init_head(); ?>
<div id="wrapper">
    <div class="content">
        <?php if ($this->session->flashdata('debug')) { ?>
        <div class="alert alert-warning">
            <?= $this->session->flashdata('debug'); ?>
        </div>
        <?php } ?>
        <?php
$settingsNavigation=[];
foreach($sections as $sectionId=>$section) {
    $children=[];
    foreach($section['children'] as $child) {
        $children[]=['id'=>$child['id'],'name'=>$child['name'],'url'=>admin_url('settings?group='.$child['id']),
            'icon'=>$child['icon']??'fa-regular fa-circle-question','badge'=>$child['badge']??[]];
    }
    if($sectionId==='general' && !$isNativeSettings) {
        $children[]=['id'=>'update','name'=>_l('settings_update'),'url'=>admin_url('settings?group=update'),'icon'=>'fa-solid fa-hammer'];
        if(is_admin()) {
            $children[]=['id'=>'argws_platform','name'=>'Plataforma','url'=>admin_url('settings?group=argws_platform'),'icon'=>'fa-solid fa-sliders'];
            $children[]=['id'=>'info','name'=>'Informações do servidor','url'=>admin_url('settings?group=info'),'icon'=>'fa-solid fa-question'];
        }
    }
    $settingsNavigation[]=['title'=>$section['title'],'children'=>$children];
}
ob_start();
$actionUrl = $group['update_url']
                            ?? $this->uri->uri_string() . '?group=' . $group['id'] . ($this->input->get('tab') ? '&active_tab=' . $this->input->get('tab') : '');

$formAttributes = [
    'id'    => 'settings-form',
    'class' => isset($group['update_url']) ? 'custom-update-url' : '',
];

echo form_open_multipart($actionUrl, $formAttributes);
if($isNativeSettings){echo form_hidden('native_settings_revision',$native_settings_revision);echo form_hidden('_native_company_csrf',CompanyRequestScope::nativeCsrfToken(get_instance()));echo form_hidden('_company_context',CompanyRequestScope::token());}
?>
                        <div class="panel_s">
                            <div class="panel-body">
                                <fieldset <?= $isNativeSettings && !$native_settings_can_edit ? 'disabled' : ''; ?>>
                                <?php if(!$isNativeSettings)hooks()->do_action('before_settings_group_view', $group); ?>
                                <?php $this->load->view($group['view']) ?>
                                <?php if(!$isNativeSettings)hooks()->do_action('after_settings_group_view', $group); ?>
                                </fieldset>
                            </div>
                            <?php if (($group['without_submit_button'] ?? false) !== true && (!$isNativeSettings || $native_settings_can_edit)) { ?>
                            <div class="panel-footer text-right">
                                <button type="submit" class="btn btn-primary">
                                    <?= _l('settings_save'); ?>
                                </button>
                            </div>
                            <?php } ?>
                        </div>
                        <?= form_close(); ?>
<?php
$settingsPresentation=['title'=>_l('settings'),'heading'=>$group['name'],'active'=>$group['id'],'sections'=>$settingsNavigation,'content'=>ob_get_clean()];
require __DIR__.'/presentation_layout.php';
unset($settingsPresentation,$settingsNavigation);
?>
    </div>
</div>
<div id="new_version"></div>
<?php init_tail(); ?>
<script>
    $(function() {
        var settingsForm = $('#settings-form');
        var slug = "<?= e($group['id']); ?>";
        $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {
            if (settingsForm.hasClass('custom-update-url')) {
                return;
            }

            // Nested settings tabs retain their gateway when the form redirects after saving.
            var tab = $(this).attr('data-settings-parent-tab') || $(this).attr('href').slice(1);
            settingsForm.attr('action',
                '<?= site_url($this->uri->uri_string()); ?>?group=' +
                slug +
                '&active_tab=' + tab);
        });

        settingsForm.on('submit', function() {
            var emailProtocol = $('input[name="settings[email_protocol]"]:checked').val();
            if (emailProtocol === 'microsoft' || emailProtocol === 'google') {
                $('input[name="settings[smtp_password]"]').val('')
                $('input[name="settings[smtp_username]"]').val('')
            }
        });

        $('input[name="settings[mail_engine]"]').on('change', function() {
            if ($(this).val() == 'codeigniter') {
                $('.protocol-microsoft').addClass('hide');
                $('.protocol-google').addClass('hide');

                if ($('input[name="settings[email_protocol]"]:checked').val() == 'microsoft') {
                    $('#smtp').prop('