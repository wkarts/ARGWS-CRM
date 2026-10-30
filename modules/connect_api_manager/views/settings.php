<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <style>
            .connect-api-settings-card .panel-body{padding:18px;}
            .connect-api-settings-card h4{margin:0 0 4px;font-size:18px;}
            .connect-api-muted{font-size:12px;color:#6c7a89;}
            .connect-api-namespace{display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 12px;border:1px solid #e4e7ea;border-radius:6px;background:#f8fafb;margin-bottom:15px;}
            .connect-api-namespace code{font-size:13px;}
            .connect-api-scope-help{font-size:12px;color:#6c7a89;margin-top:6px;}
            .connect-api-actions-row{display:flex;gap:8px;flex-wrap:wrap;align-items:center;}
        </style>
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel_s connect-api-settings-card">
                    <div class="panel-body">
                        <h4><?php echo _l('connect_api_manager_settings'); ?></h4>
                        <p class="connect-api-muted">Connect|API Manage</p>
                        <hr class="hr-panel-heading" />

                        <div class="connect-api-namespace">
                            <i class="fa fa-lock"></i>
                            <strong><?php echo _l('connect_api_manager_namespace'); ?>:</strong>
                            <code><?php echo html_escape($namespace_label); ?></code>
                            <span class="connect-api-muted"><?php echo _l('connect_api_manager_namespace_help'); ?></span>
                        </div>

                        <?php echo form_open(admin_url('connect_api_manager/settings')); ?>
                            <?php echo render_input('api_url', 'connect_api_manager_api_url', $api_url, 'url', ['required' => true, 'placeholder' => 'https://api.connect.example.com']); ?>

                            <div class="form-group">
                                <label for="admin_token"><i class="fa fa-key"></i> <?php echo _l('connect_api_manager_admin_token'); ?></label>
                                <input type="password" class="form-control" id="admin_token" name="admin_token" autocomplete="new-password" placeholder="<?php echo $has_token ? '••••••••••••••••' : ''; ?>">
                                <?php if ($has_token) { ?><p class="text-muted mtop5"><?php echo _l('connect_api_manager_token_preserved'); ?></p><?php } ?>
                            </div>

                            <?php echo render_select('scope', [
                                ['id' => 'module', 'name' => _l('connect_api_manager_scope_module')],
                                ['id' => 'all', 'name' => _l('connect_api_manager_scope_all')],
                            ], ['id', 'name'], 'connect_api_manager_scope', $scope); ?>
                            <p id="connect-api-scope-help" class="connect-api-scope-help"></p>

                            <div class="row">
                                <div class="col-md-6"><?php echo render_input('timeout', 'connect_api_manager_timeout', $timeout, 'number', ['min' => 5, 'max' => 120]); ?></div>
                                <div class="col-md-6" style="padding-top:30px;">
                                    <div class="checkbox checkbox-primary">
                                        <input type="checkbox" id="verify_tls" name="verify_tls" value="1" <?php echo $verify_tls ? 'checked' : ''; ?>>
                                        <label for="verify_tls"><?php echo _l('connect_api_manager_verify_tls'); ?></label>
                                    </div>
                                </div>
                            </div>

                            <div class="connect-api-actions-row">
                                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> <?php echo _l('save'); ?></button>
                                <a href="<?php echo admin_url('connect_api_manager/test_connection'); ?>" class="btn btn-default"><i class="fa fa-plug"></i> <?php echo _l('connect_api_manager_test_connection'); ?></a>
                                <a href="<?php echo admin_url('connect_api_manager'); ?>" class="btn btn-default"><i class="fa fa-arrow-left"></i> <?php echo _l('back'); ?></a>
                            </div>
                        <?php echo form_close(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
<script>
(function(){
    var scope = document.getElementById('scope');
    var help = document.getElementById('connect-api-scope-help');
    if(!scope || !help) return;
    var messages = {
        module: <?php echo json_encode(_l('connect_api_manager_scope_module_help'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
        all: <?php echo json_encode(_l('connect_api_manager_scope_all_help'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>
    };
    function updateHelp(){ help.textContent = messages[scope.value] || messages.module; }
    scope.addEventListener('change', updateHelp);
    updateHelp();
})();
</script>

