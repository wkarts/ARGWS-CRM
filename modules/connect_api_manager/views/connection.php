<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .connect-api-connect-card{max-width:760px;margin:0 auto;}
            .connect-api-connect-card .panel-body{padding:20px;}
            .connect-api-connect-title{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:5px;}
            .connect-api-connect-title h3{font-size:20px;margin:0;font-weight:600;word-break:break-all;}
            .connect-api-qr{max-width:300px;width:100%;height:auto;border:1px solid #e4e7ea;border-radius:8px;padding:8px;background:#fff;}
            .connect-api-pair{font-size:30px;font-weight:700;letter-spacing:4px;margin:10px 0;}
            .connect-api-status{display:flex;justify-content:center;align-items:center;gap:7px;margin:16px 0 4px;font-size:13px;}
            .connect-api-phone{display:none;margin:8px auto 0;font-size:13px;font-weight:600;}
            .connect-api-token-box{text-align:left;margin-top:16px;}
            .connect-api-token-value{font-family:monospace;word-break:break-all;}
            @media(max-width:767px){.connect-api-connect-card .panel-body{padding:15px}.connect-api-pair{font-size:24px;letter-spacing:3px}}
        </style>
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s connect-api-connect-card">
                    <div class="panel-body text-center">
                        <div class="connect-api-connect-title"><i class="fa fa-plug"></i><h3><?php echo html_escape($instance_name); ?></h3></div>
                        <?php $qr = $response['qrcode'] ?? $response; $base64 = $qr['base64'] ?? ''; $pair = $qr['pairingCode'] ?? ''; ?>

                        <?php if ($base64) { ?>
                            <div class="mtop20"><img class="connect-api-qr" src="<?php echo html_escape($base64); ?>" alt="QR Code"></div>
                        <?php } ?>

                        <?php if ($pair) { ?>
                            <div class="mtop20">
                                <h4><?php echo _l('connect_api_manager_pairing_code'); ?></h4>
                                <div class="connect-api-pair"><?php echo html_escape($pair); ?></div>
                            </div>
                        <?php } ?>

                        <div id="connect-api-monitor-alert" class="alert alert-info mtop20 no-margin">
                            <i class="fa fa-spinner fa-spin"></i> <span id="connect-api-monitor-text"><?php echo _l('connect_api_manager_connection_waiting'); ?></span>
                        </div>
                        <div id="connect-api-phone" class="connect-api-phone"><i class="fa fa-phone"></i> <span></span></div>

                        <?php if ($instance_token) { ?>
                            <div class="alert alert-warning connect-api-token-box">
                                <strong><i class="fa fa-key"></i> <?php echo _l('connect_api_manager_instance_token'); ?>:</strong><br>
                                <code class="connect-api-token-value"><?php echo html_escape($instance_token); ?></code><br>
                                <small><?php echo _l('connect_api_manager_save_token_notice'); ?></small>
                            </div>
                        <?php } ?>

                        <?php if (!$base64 && !$pair) { ?>
                            <pre class="text-left mtop20" style="white-space:pre-wrap"><?php echo html_escape(json_encode($response, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); ?></pre>
                        <?php } ?>

                        <a class="btn btn-default mtop20" href="<?php echo html_escape($return_url ?? admin_url('connect_api_manager')); ?>"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
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
    var statusUrl = <?php echo json_encode($status_url ?? '', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var returnUrl = <?php echo json_encode($return_url ?? admin_url('connect_api_manager'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var connectedText = <?php echo json_encode(_l('connect_api_manager_connection_connected'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var inFlight = false;
    var timer = null;

    function formatPhone(number){
        var d = String(number || '').replace(/\D+/g, '');
        if(d.length === 13 && d.indexOf('55') === 0) return '+55 ('+d.substr(2,2)+') '+d.substr(4,5)+'-'+d.substr(9,4);
        if(d.length === 12 && d.indexOf('55') === 0) return '+55 ('+d.substr(2,2)+') '+d.substr(4,4)+'-'+d.substr(8,4);
        return d ? '+'+d : '';
    }

    function poll(){
        if(!statusUrl || inFlight) return;
        inFlight = true;
        $.ajax({url:statusUrl,method:'GET',dataType:'json',cache:false,timeout:10000})
            .done(function(data){
                if(!data || !data.success) return;
                if(data.number){
                    $('#connect-api-phone span').text(formatPhone(data.number));
                    $('#connect-api-phone').show();
                }
                if(data.state_label){
                    $('#connect-api-monitor-text').text(data.state_label);
                }
                if(data.connected){
                    if(timer) clearInterval(timer);
                    $('#connect-api-monitor-alert').removeClass('alert-info alert-warning').addClass('alert-success');
                    $('#connect-api-monitor-alert i').attr('class','fa fa-check-circle');
                    $('#connect-api-monitor-text').text(connectedText);
                    setTimeout(function(){ window.location.replace(returnUrl); }, 900);
                }
            })
            .always(function(){ inFlight = false; });
    }

    poll();
    timer = setInterval(poll, 2000);
})(jQuery);
</script>
