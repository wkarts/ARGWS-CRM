<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .connect-api-card .panel-body{padding:16px 18px;}
            .connect-api-card h4{margin:0;font-size:18px;font-weight:600;}
            .connect-api-muted{font-size:12px;color:#6c7a89;}
            .connect-api-toolbar{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;}
            .connect-api-toolbar-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap;}
            .connect-api-meta{display:flex;gap:7px;align-items:center;flex-wrap:wrap;margin-top:8px;}
            .connect-api-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 9px;border:1px solid #e4e7ea;background:#f8fafb;border-radius:999px;font-size:11px;color:#52606d;}
            .connect-api-form-title{display:flex;align-items:center;gap:7px;margin-bottom:14px;}
            .connect-api-form-title i{color:#506176;}
            .connect-api-final-name{padding:8px 10px;border:1px dashed #cbd3da;border-radius:5px;background:#fbfcfd;font-size:12px;margin-top:-4px;margin-bottom:12px;word-break:break-all;}
            .connect-api-hint{font-size:12px;color:#6c7a89;margin:2px 0 10px;}
            .connect-api-table td,.connect-api-table th{vertical-align:middle;}
            .connect-api-table td:first-child{min-width:220px;}
            .connect-api-actions{display:flex;justify-content:flex-end;gap:5px;align-items:center;white-space:nowrap;}
            .connect-api-actions form{display:inline;margin:0;}
            .connect-api-actions .btn{padding:4px 8px;min-width:30px;}
            .connect-api-instance-flags{display:flex;gap:5px;flex-wrap:wrap;margin-top:4px;}
            .connect-api-instance-flags .label{font-size:10px;font-weight:500;}
            .connect-api-token-wrap{display:flex;gap:6px;align-items:center;}
            .connect-api-token-wrap input{font-family:monospace;}
            .connect-api-token-feedback{font-size:12px;margin-top:7px;min-height:18px;}
            .connect-api-field-hidden{display:none!important;}
            @media (max-width: 1199px){
                .connect-api-table td:first-child{min-width:190px;}
            }
            @media (max-width: 991px){
                .connect-api-card .panel-body{padding:14px;}
                .connect-api-table td,.connect-api-table th{white-space:normal;}
                .connect-api-actions{justify-content:flex-start;flex-wrap:wrap;}
                .connect-api-toolbar{align-items:flex-start;}
            }
        </style>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s connect-api-card">
                    <div class="panel-body">
                        <div class="connect-api-toolbar">
                            <div>
                                <h4><i class="fa fa-sitemap"></i> <?php echo _l('connect_api_manager_title'); ?></h4>
                                <div class="connect-api-meta">
                                    <span class="connect-api-chip"><i class="fa fa-lock"></i> <?php echo html_escape($namespace_label); ?></span>
                                    <span class="connect-api-chip"><i class="fa fa-filter"></i> <?php echo $scope === 'all' ? _l('connect_api_manager_scope_all') : _l('connect_api_manager_scope_module'); ?></span>
                                    <span class="connect-api-chip"><i class="fa fa-cubes"></i> <?php echo count($instances); ?> <?php echo _l('connect_api_manager_instances'); ?></span>
                                </div>
                            </div>
                            <div class="connect-api-toolbar-actions">
                                <?php if (connect_api_manager_can('credentials')) { ?>
                                    <a class="btn btn-default btn-sm" href="<?php echo admin_url('connect_api_manager/settings'); ?>"><i class="fa fa-sliders"></i> <?php echo _l('connect_api_manager_settings_small'); ?></a>
                                <?php } ?>
                            </div>
                        </div>
                        <hr class="hr-panel-heading" />
                        <?php if (!$configured) { ?>
                            <div class="alert alert-warning no-margin"><i class="fa fa-exclamation-triangle"></i> <?php echo _l('connect_api_manager_not_configured'); ?></div>
                        <?php } elseif ($api_error) { ?>
                            <div class="alert alert-danger no-margin"><i class="fa fa-times-circle"></i> <?php echo html_escape($api_error); ?></div>
                        <?php } else { ?>
                            <div class="alert alert-success no-margin"><i class="fa fa-check-circle"></i> <strong>Connect|API</strong> <?php echo _l('connect_api_manager_online'); ?></div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (connect_api_manager_can('create')) { ?>
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s connect-api-card">
                    <div class="panel-body">
                        <div class="connect-api-form-title"><i class="fa fa-plus-circle"></i><h4><?php echo _l('connect_api_manager_new_instance'); ?></h4></div>
                        <?php echo form_open(admin_url('connect_api_manager/create'), ['id' => 'connect-api-create-form']); ?>
                            <div class="row">
                                <div class="col-lg-4 col-md-6">
                                    <?php echo render_input('instance_name', 'connect_api_manager_instance_name', '', 'text', ['required' => true, 'maxlength' => 170, 'autocomplete' => 'off']); ?>
                                    <div class="connect-api-final-name"><span class="text-muted"><?php echo _l('connect_api_manager_final_name'); ?>:</span> <strong id="connect-api-final-instance-name"><?php echo html_escape($namespace_label); ?>-...</strong></div>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <?php echo render_select('integration', [
                                        ['id'=>'WHATSAPP-BAILEYS','name'=>'WHATSAPP-BAILEYS'],
                                        ['id'=>'WHATSAPP-BUSINESS','name'=>'WHATSAPP-BUSINESS'],
                                        ['id'=>'CONNECT','name'=>'CONNECT'],
                                    ], ['id','name'], 'connect_api_manager_integration', 'WHATSAPP-BAILEYS'); ?>
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <?php echo render_input('instance_token', 'connect_api_manager_optional_token', '', 'password', ['autocomplete'=>'new-password']); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-lg-4 col-md-6" id="connect-api-method-wrap">
                                    <?php echo render_select('connection_method', [
                                        ['id'=>'later','name'=>_l('connect_api_manager_connect_later')],
                                        ['id'=>'qrcode','name'=>'QR Code'],
                                        ['id'=>'pairing','name'=>_l('connect_api_manager_pairing_code')],
                                    ], ['id','name'], 'connect_api_manager_connection_method', 'qrcode'); ?>
                                </div>
                                <div class="col-lg-4 col-md-6" id="connect-api-number-wrap">
                                    <?php echo render_input('number', 'connect_api_manager_pairing_number', '', 'text', ['placeholder'=>'5575988449231', 'inputmode'=>'numeric']); ?>
                                </div>
                                <div class="col-lg-4 col-md-12" style="padding-top:28px;">
                                    <button type="submit" class="btn btn-primary"><i class="fa fa-plus"></i> <?php echo _l('connect_api_manager_create_instance'); ?></button>
                                </div>
                            </div>
                            <p class="connect-api-hint"><i class="fa fa-info-circle"></i> <?php echo _l('connect_api_manager_integration_hint'); ?></p>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <div class="row">
            <div class="col-md-12">
                <div class="panel_s connect-api-card">
                    <div class="panel-body">
                        <div class="connect-api-form-title"><i class="fa fa-cubes"></i><h4><?php echo _l('connect_api_manager_instances'); ?></h4></div>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover connect-api-table">
                                <thead>
                                    <tr>
                                        <th><?php echo _l('connect_api_manager_instance_name'); ?></th>
                                        <th><?php echo _l('connect_api_manager_number'); ?></th>
                                        <th><?php echo _l('connect_api_manager_state'); ?></th>
                                        <th><?php echo _l('connect_api_manager_integration'); ?></th>
                                        <th class="text-right"><?php echo _l('connect_api_manager_actions'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $rendered = 0; ?>
                                <?php foreach ($instances as $row) {
                                    $name = $row['name'] ?? $row['instanceName'] ?? ($row['instance']['instanceName'] ?? '');
                                    $number = $row['_connect_number'] ?? $row['number'] ?? ($row['instance']['number'] ?? '');
                                    $state = $row['connectionStatus'] ?? $row['state'] ?? $row['status'] ?? ($row['instance']['state'] ?? 'unknown');
                                    $integration = $row['integration'] ?? ($row['instance']['integration'] ?? '');
                                    if ($name === '') continue;
                                    $rendered++;
                                    $hasToken = !empty($row['_connect_has_token']);
                                    $owned = !empty($row['_connect_owned']);
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo html_escape($name); ?></strong>
                                            <div class="connect-api-instance-flags">
                                                <span class="label label-<?php echo $owned ? 'info' : 'default'; ?>"><?php echo $owned ? _l('connect_api_manager_owned') : _l('connect_api_manager_external'); ?></span>
                                                <span class="label label-<?php echo $hasToken ? 'success' : 'default'; ?>"><?php echo $hasToken ? _l('connect_api_manager_available_token') : _l('connect_api_manager_no_token'); ?></span>
                                            </div>
                                        </td>
                                        <td><?php echo html_escape(connect_api_manager_format_phone($number)); ?></td>
                                        <td><span class="label label-<?php echo connect_api_manager_state_badge_class($state); ?>"><?php echo html_escape(connect_api_manager_translate_state($state)); ?></span></td>
                                        <td><?php echo html_escape($integration ?: '-'); ?></td>
                                        <td>
                                            <div class="connect-api-actions">
                                                <a class="btn btn-xs btn-default" href="<?php echo admin_url('connect_api_manager/state/' . rawurlencode($name)); ?>" data-toggle="tooltip" title="<?php echo _l('connect_api_manager_state_view'); ?>"><i class="fa fa-info-circle"></i></a>
                                                <?php if ($hasToken && connect_api_manager_can('credentials')) { ?>
                                                    <button type="button" class="btn btn-xs btn-default connect-api-token-btn" data-url="<?php echo admin_url('connect_api_manager/token/' . rawurlencode($name)); ?>" data-instance="<?php echo html_escape($name); ?>" data-toggle="tooltip" title="<?php echo _l('connect_api_manager_token_view'); ?>"><i class="fa fa-key"></i></button>
                                                <?php } ?>
                                                <?php if (connect_api_manager_can('manage')) { ?>
                                                    <button type="button" class="btn btn-xs btn-info" data-toggle="modal" data-target="#connect-<?php echo md5($name); ?>" title="<?php echo _l('connect_api_manager_connect_test'); ?>"><i class="fa fa-qrcode"></i></button>
                                                    <?php echo form_open(admin_url('connect_api_manager/restart/' . rawurlencode($name))); ?><button type="submit" class="btn btn-xs btn-default" data-toggle="tooltip" title="<?php echo _l('connect_api_manager_restart'); ?>"><i class="fa fa-refresh"></i></button><?php echo form_close(); ?>
                                                    <?php echo form_open(admin_url('connect_api_manager/logout/' . rawurlencode($name))); ?><button type="submit" class="btn btn-xs btn-warning" data-toggle="tooltip" title="<?php echo _l('connect_api_manager_logout'); ?>" onclick="return confirm('Desconectar esta instância?');"><i class="fa fa-sign-out"></i></button><?php echo form_close(); ?>
                                                <?php } ?>
                                                <?php if (connect_api_manager_can('delete')) { ?>
                                                    <?php echo form_open(admin_url('connect_api_manager/delete/' . rawurlencode($name))); ?><button type="submit" class="btn btn-xs btn-danger" data-toggle="tooltip" title="<?php echo _l('connect_api_manager_delete'); ?>" onclick="return confirm('Excluir definitivamente esta instância?');"><i class="fa fa-trash"></i></button><?php echo form_close(); ?>
                                                <?php } ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                                <?php if ($rendered === 0) { ?><tr><td colspan="5" class="text-center text-muted"><?php echo _l('connect_api_manager_no_instances'); ?></td></tr><?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (connect_api_manager_can('manage')) { ?>
            <?php foreach ($instances as $row) {
                $name = $row['name'] ?? $row['instanceName'] ?? ($row['instance']['instanceName'] ?? '');
                $modalNumber = $row['_connect_number'] ?? $row['number'] ?? ($row['instance']['number'] ?? '');
                if ($name === '') continue;
            ?>
            <div class="modal fade" id="connect-<?php echo md5($name); ?>" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4><i class="fa fa-plug"></i> <?php echo html_escape($name); ?></h4></div>
                        <div class="modal-body">
                            <?php echo form_open(admin_url('connect_api_manager/connection/' . rawurlencode($name)), ['class'=>'connect-api-instance-connect-form']); ?>
                                <div class="form-group">
                                    <label><?php echo _l('connect_api_manager_connection_method'); ?></label>
                                    <select class="form-control connect-api-instance-method" name="method">
                                        <option value="qrcode">QR Code</option>
                                        <option value="pairing"><?php echo _l('connect_api_manager_pairing_code'); ?></option>
                                    </select>
                                </div>
                                <div class="form-group connect-api-instance-number-wrap connect-api-field-hidden">
                                    <label><?php echo _l('connect_api_manager_pairing_number'); ?></label>
                                    <input class="form-control" name="number" type="text" value="<?php echo html_escape($modalNumber); ?>" placeholder="5575988449231" inputmode="numeric">
                                </div>
                                <button class="btn btn-primary" type="submit"><i class="fa fa-plug"></i> <?php echo _l('connect_api_manager_connect'); ?></button>
                            <?php echo form_close(); ?>
                            <hr>
                            <?php echo form_open(admin_url('connect_api_manager/send_test/' . rawurlencode($name))); ?>
                                <?php echo render_input('number', 'connect_api_manager_test_number', '', 'text', ['placeholder'=>'5575900001111']); ?>
                                <?php echo render_textarea('message', 'connect_api_manager_test_message', 'Teste Connect|API'); ?>
                                <button class="btn btn-default" type="submit"><i class="fa fa-paper-plane"></i> <?php echo _l('connect_api_manager_send_test'); ?></button>
                            <?php echo form_close(); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        <?php } ?>

        <div class="modal fade" id="connect-api-token-modal" tabindex="-1" role="dialog">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4><i class="fa fa-key"></i> <?php echo _l('connect_api_manager_instance_token'); ?></h4></div>
                    <div class="modal-body">
                        <p class="connect-api-muted" id="connect-api-token-instance"></p>
                        <div class="connect-api-token-wrap">
                            <input type="password" class="form-control" id="connect-api-token-value" readonly autocomplete="off">
                            <button type="button" class="btn btn-default" id="connect-api-token-toggle" title="<?php echo _l('connect_api_manager_token_view'); ?>"><i class="fa fa-eye"></i></button>
                            <button type="button" class="btn btn-primary" id="connect-api-token-copy"><i class="fa fa-copy"></i> <?php echo _l('connect_api_manager_token_copy'); ?></button>
                        </div>
                        <div class="connect-api-token-feedback text-muted" id="connect-api-token-feedback"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function($){
    'use strict';

    var namespace = <?php echo json_encode($namespace_label, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function slug(value){
        value = (value || '').trim();
        if(value.normalize){ value = value.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
        value = value.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9._-]+/g, '-').replace(/-+/g, '-').replace(/^[-._]+|[-._]+$/g, '');
        return value;
    }

    function updateFinalName(){
        var logical = slug($('#instance_name').val());
        $('#connect-api-final-instance-name').text(namespace + '-' + (logical || '...'));
    }

    function updateCreateFields(){
        var integration = $('#integration').val();
        var method = $('#connection_method').val();
        var $methodWrap = $('#connect-api-method-wrap');
        var $numberWrap = $('#connect-api-number-wrap');

        var requireNumber = false;
        $methodWrap.removeClass('connect-api-field-hidden');
        $numberWrap.addClass('connect-api-field-hidden');

        if(integration === 'WHATSAPP-BAILEYS'){
            if(method === 'pairing'){
                $numberWrap.removeClass('connect-api-field-hidden');
                requireNumber = true;
            }
        } else if(integration === 'WHATSAPP-BUSINESS'){
            $methodWrap.addClass('connect-api-field-hidden');
            $numberWrap.removeClass('connect-api-field-hidden');
        } else {
            $methodWrap.addClass('connect-api-field-hidden');
            $numberWrap.addClass('connect-api-field-hidden');
        }

        $numberWrap.find('input[name="number"]').prop('required', requireNumber);
    }

    $('#instance_name').on('input', updateFinalName);
    $('#integration, #connection_method').on('change', updateCreateFields);
    updateFinalName();
    updateCreateFields();

    $('.connect-api-instance-method').on('change', function(){
        var $form = $(this).closest('form');
        var pairing = $(this).val() === 'pairing';
        var $wrap = $form.find('.connect-api-instance-number-wrap');
        $wrap.toggleClass('connect-api-field-hidden', !pairing);
        $wrap.find('input[name="number"]').prop('required', pairing);
    }).trigger('change');

    $(document).on('click', '.connect-api-token-btn', function(){
        var $button = $(this);
        var $modal = $('#connect-api-token-modal');
        var $value = $('#connect-api-token-value');
        var $feedback = $('#connect-api-token-feedback');
        $value.val('').attr('type', 'password');
        $('#connect-api-token-toggle i').attr('class', 'fa fa-eye');
        $('#connect-api-token-instance').text($button.data('instance'));
        $feedback.removeClass('text-danger text-success').addClass('text-muted').text(<?php echo json_encode(_l('connect_api_manager_token_loading'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
        $modal.modal('show');

        $.ajax({url:$button.data('url'),method:'GET',dataType:'json',cache:false})
            .done(function(data){
                if(data && data.success && data.token){
                    $value.val(data.token);
                    $feedback.text('');
                } else {
                    $feedback.removeClass('text-muted').addClass('text-danger').text((data && data.message) || <?php echo json_encode(_l('connect_api_manager_token_unavailable'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
                }
            })
            .fail(function(xhr){
                var msg = <?php echo json_encode(_l('connect_api_manager_token_unavailable'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
                if(xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message;
                $feedback.removeClass('text-muted').addClass('text-danger').text(msg);
            });
    });

    $('#connect-api-token-toggle').on('click', function(){
        var $value = $('#connect-api-token-value');
        var reveal = $value.attr('type') === 'password';
        $value.attr('type', reveal ? 'text' : 'password');
        $(this).find('i').attr('class', reveal ? 'fa fa-eye-slash' : 'fa fa-eye');
    });

    $('#connect-api-token-copy').on('click', function(){
        var value = $('#connect-api-token-value').val();
        if(!value) return;
        var $feedback = $('#connect-api-token-feedback');
        function done(){
            $feedback.removeClass('text-muted text-danger').addClass('text-success').text(<?php echo json_encode(_l('connect_api_manager_token_copied'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>);
        }
        if(navigator.clipboard && navigator.clipboard.writeText){
            navigator.clipboard.writeText(value).then(done).catch(function(){ fallbackCopy(value); done(); });
        } else {
            fallbackCopy(value); done();
        }
    });

    function fallbackCopy(value){
        var el = document.createElement('textarea');
        el.value = value;
        el.style.position = 'fixed';
        el.style.opacity = '0';
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
    }

    if($.fn.tooltip){ $('[data-toggle="tooltip"]').tooltip(); }
})(jQuery);
</script>

