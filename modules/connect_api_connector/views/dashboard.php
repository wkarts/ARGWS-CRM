<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .connect-api-card .panel-body{padding:14px 16px;}
            .connect-api-card h4{margin:0;font-size:17px;font-weight:600;line-height:1.25;}
            .connect-api-muted{font-size:12px;color:#6c7a89;margin-bottom:8px;}
            .connect-api-grid-gap{margin-bottom:12px;}
            .connect-api-title{display:flex;align-items:center;gap:7px;margin-bottom:10px;}
            .connect-api-form-actions{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
            .connect-api-form-actions .btn{padding:6px 11px;}
            .connect-api-manager-box{border-left:3px solid #03a9f4;}
            .connect-api-manager-box .panel-body{padding:16px 18px;}
            .connect-api-connect-box .panel-body{padding:13px 15px;}
            .connect-api-manager-box .connect-api-title{margin-bottom:10px;}
            .connect-api-manager-box .connect-api-muted{margin-bottom:12px;}
            .connect-api-manager-box .btn{margin-top:15px;}
            .connect-api-manager-disabled{opacity:.68;}
            .connect-api-manager-disabled select,.connect-api-manager-disabled .btn{cursor:not-allowed!important;}
            .connect-api-connection-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:2px;}
            .connect-api-connection-actions .btn{padding:6px 11px;}
            .connect-api-field-hidden{display:none!important;}
            .connect-api-connection-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border-radius:999px;border:1px solid #e4e7ea;background:#f8fafb;font-size:11px;margin-top:7px;}
            .connect-api-test-box .panel-body{padding:14px 16px;}
            .connect-api-test-box textarea{height:76px!important;min-height:76px!important;resize:vertical;}
            .connect-api-test-box .form-group{margin-bottom:10px;}
            .connect-api-test-box .btn{padding:6px 11px;}
            @media (max-width: 991px){
                .connect-api-card .panel-body{padding:13px 14px;}
                .connect-api-mobile-gap{margin-top:0;}
                .connect-api-test-box textarea{height:72px!important;min-height:72px!important;}
            }
            @media (max-width: 767px){
                .connect-api-connection-actions{display:grid;grid-template-columns:1fr 1fr;}
                .connect-api-connection-actions .btn{width:100%;}
            }
        </style>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s connect-api-card">
                    <div class="panel-body">
                        <div class="connect-api-title"><i class="fa fa-plug"></i><h4><?php echo _l('connect_api_connector_title'); ?></h4></div>
                        <p class="connect-api-muted"><?php echo _l('connect_api_connector_status_summary'); ?></p>
                        <hr class="hr-panel-heading" />
                        <?php $currentState = $state['instance']['state'] ?? null; $currentStateClass = $currentState ? connect_api_connector_state_badge_class($currentState) : 'info'; if ($currentStateClass === 'default') $currentStateClass = 'info'; ?>
                        <?php if ($api_error) { ?>
                            <div class="alert alert-danger no-margin"><i class="fa fa-times-circle"></i> <?php echo html_escape($api_error); ?></div>
                        <?php } elseif ($currentState) { ?>
                            <div class="alert alert-<?php echo $currentStateClass; ?> no-margin"><i class="fa fa-signal"></i> Connect|API: <strong><?php echo html_escape(connect_api_connector_translate_state($currentState)); ?></strong></div>
                        <?php } else { ?>
                            <div class="alert alert-info no-margin"><i class="fa fa-info-circle"></i> <?php echo _l('connect_api_connector_not_configured'); ?></div>
                        <?php } ?>
                        <?php if (!empty($instance_number)) { ?>
                            <div class="connect-api-connection-badge"><i class="fa fa-phone"></i> <?php echo _l('connect_api_connector_instance_phone'); ?>: <strong><?php echo html_escape(connect_api_connector_format_phone($instance_number)); ?></strong></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (connect_api_connector_can('configure')) { ?>
        <div class="row">
            <div class="col-lg-8 col-md-7 connect-api-grid-gap">
                <div class="panel_s connect-api-card">
                    <div class="panel-body">
                        <div class="connect-api-title"><i class="fa fa-sliders"></i><h4><?php echo _l('connect_api_connector_configuration'); ?></h4></div>
                        <?php echo form_open(admin_url('connect_api_connector/save')); ?>
                            <?php echo render_input('api_url', 'connect_api_connector_api_url', $config['base_url'], 'url', ['required'=>true,'placeholder'=>'https://api.connect.example.com']); ?>
                            <div class="row">
                                <div class="col-md-6"><?php echo render_input('instance_name', 'connect_api_connector_instance_name', $config['instance_name'], 'text', ['required'=>true]); ?></div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="instance_token"><i class="fa fa-key"></i> <?php echo _l('connect_api_connector_instance_token'); ?></label>
                                        <input class="form-control" type="password" id="instance_token" name="instance_token" autocomplete="new-password" placeholder="<?php echo $config['instance_token'] !== '' ? '••••••••••••••••' : ''; ?>">
                                        <p class="text-muted mtop5"><?php echo _l('connect_api_connector_token_preserved'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4"><?php echo render_input('default_country','connect_api_connector_default_country',$config['default_country'],'text'); ?></div>
                                <div class="col-sm-4"><?php echo render_input('default_area','connect_api_connector_default_area',$config['default_area'],'text'); ?></div>
                                <div class="col-sm-4"><?php echo render_input('timeout','connect_api_connector_timeout',$config['timeout'],'number',['min'=>5,'max'=>120]); ?></div>
                            </div>
                            <div class="row">
                                <div class="col-md-6"><div class="checkbox checkbox-primary"><input type="checkbox" id="auto_normalize" name="auto_normalize" value="1" <?php echo $config['auto_normalize']?'checked':''; ?>><label for="auto_normalize"><?php echo _l('connect_api_connector_auto_normalize'); ?></label></div></div>
                                <div class="col-md-6"><div class="checkbox checkbox-primary"><input type="checkbox" id="verify_tls" name="verify_tls" value="1" <?php echo $config['verify_tls']?'checked':''; ?>><label for="verify_tls"><?php echo _l('connect_api_connector_verify_tls'); ?></label></div></div>
                            </div>
                            <div class="well well-sm" style="margin-bottom:12px;">
                                <div class="checkbox checkbox-primary" style="margin-top:0;">
                                    <input type="checkbox" id="sms_takeover" name="sms_takeover" value="1" <?php echo !empty($config['sms_takeover'])?'checked':''; ?>>
                                    <label for="sms_takeover"><strong><?php echo _l('connect_api_connector_sms_takeover'); ?></strong></label>
                                </div>
                                <p class="text-muted no-margin" style="font-size:12px;"><?php echo _l('connect_api_connector_sms_takeover_info'); ?></p>
                                <div class="mtop10">
                                    <span class="label label-<?php echo connect_api_connector_sms_gateway_is_active() ? 'success' : 'default'; ?>">
                                        <i class="fa fa-comments"></i> <?php echo connect_api_connector_sms_gateway_is_active() ? _l('connect_api_connector_sms_gateway_active') : _l('connect_api_connector_sms_gateway_inactive'); ?>
                                    </span>
                                    <a class="btn btn-link btn-xs" href="<?php echo admin_url('settings?group=sms'); ?>"><i class="fa fa-edit"></i> <?php echo _l('connect_api_connector_sms_configure_triggers'); ?></a>
                                </div>
                            </div>
                            <div class="connect-api-form-actions">
                                <button class="btn btn-primary" type="submit"><i class="fa fa-save"></i> <?php echo _l('save'); ?></button>
                                <a class="btn btn-default" href="<?php echo admin_url('connect_api_connector/test_connection'); ?>"><i class="fa fa-plug"></i> <?php echo _l('connect_api_connector_test_connection'); ?></a>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 col-md-5 connect-api-mobile-gap">
                <div class="panel_s connect-api-card connect-api-grid-gap connect-api-manager-box<?php echo empty($managed_module_available) ? ' connect-api-manager-disabled' : ''; ?>">
                    <div class="panel-body">
                        <div class="connect-api-title"><i class="fa fa-sitemap"></i><h4><?php echo _l('connect_api_connector_managed_instances'); ?></h4></div>

                        <?php if (!empty($managed_module_available)) { ?>
                            <p class="connect-api-muted"><?php echo _l('connect_api_connector_manager_help'); ?></p>
                            <?php if (!empty($managed_instances)) { ?>
                                <?php echo form_open(admin_url('connect_api_connector/bind_managed')); ?>
                                    <select name="managed_instance" class="form-control selectpicker" data-live-search="true">
                                        <?php foreach($managed_instances as $item){ ?>
                                            <option value="<?php echo html_escape($item['instance_name']); ?>"><?php echo html_escape($item['instance_name']); ?></option>
                                        <?php } ?>
                                    </select>
                                    <button class="btn btn-info" type="submit" title="<?php echo _l('connect_api_connector_use_managed'); ?>"><i class="fa fa-link"></i> <?php echo _l('connect_api_connector_use_managed'); ?></button>
                                <?php echo form_close(); ?>
                            <?php } else { ?>
                                <select class="form-control" disabled>
                                    <option><?php echo _l('connect_api_connector_no_managed_instances'); ?></option>
                                </select>
                                <button class="btn btn-info" type="button" disabled><i class="fa fa-link"></i> <?php echo _l('connect_api_connector_use_managed'); ?></button>
                            <?php } ?>
                        <?php } else { ?>
                            <p class="connect-api-muted"><?php echo _l('connect_api_connector_manager_unavailable_help'); ?></p>
                            <select class="form-control" disabled>
                                <option><?php echo _l('connect_api_connector_manager_unavailable_option'); ?></option>
                            </select>
                            <button class="btn btn-info" type="button" disabled><i class="fa fa-link"></i> <?php echo _l('connect_api_connector_use_managed'); ?></button>
                        <?php } ?>
                    </div>
                </div>

                <div class="panel_s connect-api-card connect-api-connect-box">
                    <div class="panel-body">
                        <div class="connect-api-title"><i class="fa fa-qrcode"></i><h4><?php echo _l('connect_api_connector_actions'); ?></h4></div>
                        <?php echo form_open(admin_url('connect_api_connector/connect'), ['id'=>'connect-api-connector-connect-form']); ?>
                            <?php echo render_select('method',[['id'=>'qrcode','name'=>'QR Code'],['id'=>'pairing','name'=>_l('connect_api_connector_pairing_code')]],['id','name'],'connect_api_connector_connection_method','qrcode'); ?>
                            <div id="connect-api-connector-number-wrap" class="connect-api-field-hidden">
                                <?php echo render_input('number','connect_api_connector_pairing_number',$instance_number,'text',['placeholder'=>'5575988449231','inputmode'=>'numeric']); ?>
                            </div>
                            <div class="connect-api-connection-actions">
                                <button class="btn btn-info" type="submit"><i class="fa fa-plug"></i> <?php echo _l('connect_api_connector_connect'); ?></button>
                                <?php if(connect_api_connector_can('reconnect')){ ?>
                                    <button class="btn btn-default" type="submit" formaction="<?php echo admin_url('connect_api_connector/restart'); ?>" formmethod="post" formnovalidate><i class="fa fa-refresh"></i> <?php echo _l('connect_api_connector_restart'); ?></button>
                                <?php } ?>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <?php if (connect_api_connector_can('send')) { ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s connect-api-card connect-api-test-box">
                    <div class="panel-body">
                        <div class="connect-api-title"><i class="fa fa-paper-plane"></i><h4><?php echo _l('connect_api_connector_send_test'); ?></h4></div>
                        <?php echo form_open(admin_url('connect_api_connector/send_test')); ?>
                            <div class="row">
                                <div class="col-md-4"><?php echo render_input('number','connect_api_connector_test_number','','text',['placeholder'=>'5575900001111']); ?></div>
                                <div class="col-md-8"><?php echo render_textarea('message','connect_api_connector_test_message','Teste Connect|API'); ?></div>
                            </div>
                            <button class="btn btn-primary" type="submit"><i class="fa fa-paper-plane"></i> <?php echo _l('connect_api_connector_send'); ?></button>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function($){
    'use strict';
    function updatePairingField(){
        var isPairing = $('#method').val() === 'pairing';
        var $wrap = $('#connect-api-connector-number-wrap');
        $wrap.toggleClass('connect-api-field-hidden', !isPairing);
        $wrap.find('input[name="number"]').prop('required', isPairing);
    }
    $('#method').on('change', updatePairingField);
    updatePairingField();
})(jQuery);
</script>

