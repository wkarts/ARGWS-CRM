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
                    $('#smtp').prop('checked', true)
                    $('#microsoft').trigger('change')
                }

                if ($('input[name="settings[email_protocol]"]:checked').val() == 'google') {
                    $('#smtp').prop('checked', true)
                    $('#google').trigger('change')
                }
            } else {
                $('.protocol-microsoft').removeClass('hide');
                $('.protocol-google').removeClass('hide');
            }
        });

        $('input[name="settings[email_protocol]"]').on('change', function() {
            var $inputHost = $('input[name="settings[smtp_host]"]');
            var $inputPort = $('input[name="settings[smtp_port]"]');
            var $selectEnc = $('select[name="settings[smtp_encryption]"]');

            var resetFields = function() {
                if ($selectEnc.hasClass('_modified')) {
                    $selectEnc.selectpicker('val', '');
                    $selectEnc.removeClass('_modified');
                }

                if ($inputPort.hasClass('_modified')) {
                    $inputPort.val('');
                    $inputPort.removeClass('_modified');
                }

                if ($inputHost.hasClass('_modified')) {
                    $inputHost.val('');
                    $inputHost.removeClass('_modified');
                }
            }

            if ($(this).val() == 'mail') {
                $('.xoauth-hide').addClass('hide');
                $('.smtp-fields').addClass('hide');
                $('.xoauth-microsoft-show').addClass('hide');
                $('.xoauth-google-show').addClass('hide');
                resetFields();
            } else if ($(this).val() === 'microsoft' || $(this).val() === 'google') {
                $('.smtp-fields').removeClass('hide');
                $('.xoauth-hide').addClass('hide');
                $('.xoauth-microsoft-show').addClass('hide');
                $('.xoauth-google-show').addClass('hide');

                if ($(this).val() === 'microsoft') {
                    $('.xoauth-microsoft-show').removeClass('hide');
                    if ($inputHost.val() == '') {
                        $inputHost.val('smtp.office365.com')
                        $inputHost.addClass('_modified');
                    }
                }

                if ($(this).val() === 'google') {
                    $('.xoauth-google-show').removeClass('hide');
                    if ($inputHost.val() == '') {
                        $inputHost.val('smtp.gmail.com')
                        $inputHost.addClass('_modified');
                    }
                }

                if ($inputPort.val() == '') {
                    $inputPort.val('587')
                    $inputPort.addClass('_modified');
                    if ($selectEnc.selectpicker('val') == '') {
                        $selectEnc.selectpicker('val', 'tls');
                        $selectEnc.addClass('_modified');
                    }
                }
            } else {
                $('.smtp-fields').removeClass('hide');
                $('.xoauth-hide').removeClass('hide');
                $('.xoauth-microsoft-show').addClass('hide');
                $('.xoauth-google-show').addClass('hide');
                resetFields();
            }
        });

        $('.sms_gateway_active input').on('change', function() {
            if ($(this).val() == '1') {
                $('body .sms_gateway_active').not($(this).parents('.sms_gateway_active')[0]).find(
                    'input[value="0"]').prop('checked', true);
            }
        });

        <?php if ($group['id'] == 'pusher') {
            if (get_option('desktop_notifications') == '1') { ?>
        // Let's check if the browser supports notifications
        if (!("Notification" in window)) {
            $('#pusherHelper').html(
                '<div class="alert alert-danger">Your browser does not support desktop notifications, please disable this option or use more modern browser.</div>'
            );
        } else if (Notification.permission == "denied") {
            $('#pusherHelper').html(
                '<div class="alert alert-danger">Desktop notifications not allowed in browser settings, search on Google "How to allow desktop notifications for <?= $this->agent->browser(); ?>"</div>'
            );
        }
        <?php } ?>
        <?php if (get_option('pusher_realtime_notifications') == '0') { ?>
        $('input[name="settings[desktop_notifications]"]').prop('disabled', true);
        <?php } ?>
        <?php } ?>

        $('input[name="settings[pusher_realtime_notifications]"]').on('change', function() {
            if ($(this).val() == '1') {
                $('input[name="settings[desktop_notifications]"]').prop('disabled', false);
            } else {
                $('input[name="settings[desktop_notifications]"]').prop('disabled', true);
                $('input[name="settings[desktop_notifications]"][value="0"]').prop('checked', true);
            }
        });

        $('.test_email').on('click', function() {
            var email = $('input[name="test_email"]').val();
            if (email != '') {
                $(this).attr('disabled', true);
                $.post(admin_url + 'emails/sent_smtp_test_email', {
                    test_email: email
                }).done(function(data) {
                    window.location.reload();
                });
            }
        });

    });

    $('input[name="settings[reminder_for_completed_but_not_billed_tasks]"]').on('change', function() {
        if ($(this).val() == '1') {
            $('.staff_notify_completed_but_not_billed_tasks_fields').removeClass('hide');
        } else {
            $('.staff_notify_completed_but_not_billed_tasks_fields').addClass('hide');
        }
    });
</script>
<?php if(!$isNativeSettings)hooks()->do_action('settings_group_end', $group); ?>
</body>

</html>
