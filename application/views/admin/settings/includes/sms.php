<?php defined('BASEPATH') or exit('No direct script access allowed');
hooks()->do_action('before_sms_gateways_settings');

$gateways       = $this->app_sms->get_gateways();
$triggers       = $this->app_sms->get_available_triggers();
$total_gateways = count($gateways);

if ($total_gateways > 1) { ?>
<div class="alert alert-info">
    <?php echo _l('notice_only_one_active_sms_gateway'); ?>
</div>
<?php } ?>

<div class="panel-group" id="sms_gateways_options" role="tablist" aria-multiselectable="false">
    <?php foreach ($gateways as $gateway) { ?>
    <div class="panel panel-default">
        <div class="panel-heading" role="tab" id="<?php echo 'heading' . $gateway['id']; ?>">
            <h4 class="panel-title">
                <a role="button" data-toggle="collapse" data-parent="#sms_gateways_options"
                    href="#sms_<?php echo e($gateway['id']); ?>" aria-expanded="true"
                    aria-controls="sms_<?php echo e($gateway['id']); ?>">
                    <?php echo e($gateway['name']); ?> <span class="pull-right"><i class="fa fa-sort-down"></i></span>
                </a>
            </h4>
        </div>
        <div id="sms_<?php echo e($gateway['id']); ?>" class="panel-collapse collapse<?php if ($this->app_sms->get_option($gateway['id'], 'active') == 1 || $total_gateways == 1) {
    echo ' in';
} ?>" role="tabpanel" aria-labelledby="<?php echo 'heading' . $gateway['id']; ?>">
            <div class="panel-body">
        <?php
        if (isset($gateway['info']) && $gateway['info'] != '') {
            echo $gateway['info'];
        }

       if (isset($gateway['deprecated'])) { ?>
            <div class="alert alert-warning">
                Este provedor de mensagens está obsoleto e poderá ser descontinuado.
            </div>
        <?php }

        foreach ($gateway['options'] as $g_option) {
            $type = isset($g_option['field_type']) ? $g_option['field_type'] : 'text';
            if ($type == 'text') {
                echo render_input(
                    'settings[' . $this->app_sms->option_name($gateway['id'], $g_option['name']) . ']',
                    $g_option['label'],
                    $this->app_sms->get_option($gateway['id'], $g_option['name']),
                    'text',
                    [],
                    [],
                    isset($g_option['info']) ? 'mbot5' : 'mbot15'
                );
            } elseif ($type == 'radio') {
                ?>
                <div class="form-group">
                    <p><?php echo e($g_option['label']); ?></p>
                    <?php
                foreach ($g_option['options'] as $option) {
                    ?>
                    <div class="radio radio-info radio-inline">
                        <input type="radio"
                            name="settings[<?php echo $optionName = $this->app_sms->option_name($gateway['id'], $g_option['name']); ?>]"
                            value="<?php echo e($option['value']); ?>"
                            id="<?php echo $option['value'] . '-' . $optionName; ?>" <?php if ($this->app_sms->get_option($gateway['id'], $g_option['name']) == $option['value']) {
                        echo ' checked';
                    } ?>>
                        <label
                            for="<?php echo $option['value'] . '-' . $optionName; ?>"><?php echo e($option['label']); ?></label>
                    </div>
                    <?php
                } ?>
                </div>
                <?php
            }

            if (isset($g_option['info'])) { ?>
                <div class="mbot15">
                    <?php echo $g_option['info']; ?>
                </div>
                <?php }
        }
        echo '<div class="sms_gateway_active">';

        echo render_yes_no_option($this->app_sms->option_name($gateway['id'], 'active'), 'Ativo');

        echo '</div>';
            if (get_option($this->app_sms->option_name($gateway['id'], 'active')) == '1') {
                echo '<hr />';
                echo '<h4 class="mbot15">' . _l('test_sms_config') . '</h4>';
                echo '<div class="form-group"><input type="text" placeholder="' . _l('staff_add_edit_phonenumber') . '" class="form-control test-phone" data-id="' . $gateway['id'] . '"></div>';
                echo '<div class="form-group"><textarea class="form-control sms-gateway-test-message" placeholder="' . _l('test_sms_message') . '" data-id="' . $gateway['id'] . '" rows="4"></textarea></div>';
                echo '<button type="button" class="btn btn-primary send-test-sms" data-id="' . $gateway['id'] . '">' . _l('send_test_sms') . '</button>';
                echo '<div id="sms_test_response" data-id="' . $gateway['id'] . '"></div>';
            }
        ?>
            </div>
        </div>
    </div>
    <?php } ?>
    <hr />
    <?php echo render_input('settings[bitly_access_token]', 'Token de acesso Bitly', get_option('bitly_access_token')); ?>
    <hr />
    <h4 class="mbot10">
        <i class="fa-regular fa-message tw-mr-1"></i> Modelos e gatilhos de mensagens
    </h4>
    <p class="text-muted">
        Ative ou desative cada gatilho separadamente. Desativar um gatilho não
        apaga a mensagem que você escreveu. Os novos gatilhos vêm desativados
        para não gerar envios inesperados.
    </p>
    <?php
    uasort($triggers, static function ($a, $b) {
        return strcmp((string) ($a['group'] ?? 'Outras integrações'),
            (string) ($b['group'] ?? 'Outras integrações'))
            ?: strcasecmp((string) ($a['label'] ?? ''), (string) ($b['label'] ?? ''));
    });
    $lastGroup = null;
    foreach ($triggers as $trigger_name => $trigger_opts) {
        $safeId = preg_replace('/[^a-z0-9_-]/i', '-', (string) $trigger_name);
        $group = (string) ($trigger_opts['group'] ?? 'Outras integrações');
        $active = !empty($trigger_opts['enabled']);
        $optionKey = $this->app_sms->trigger_enabled_option_name($trigger_name);
        $messageKey = $this->app_sms->trigger_option_name($trigger_name);
        $default = (string) ($trigger_opts['default_message'] ?? '');

        if ($lastGroup !== $group) {
            if ($lastGroup !== null) {
                echo '</div>';
            }
            echo '<h4 class="tw-mt-6 tw-mb-3">' . html_escape($group) . '</h4>';
            echo '<div class="row">';
            $lastGroup = $group;
        }
    ?>
        <div class="col-xs-12">
            <section class="panel panel-default sms-trigger-card" id="sms-trigger-<?= html_escape($safeId); ?>">
                <div class="panel-heading clearfix">
                    <div class="row">
                        <div class="col-sm-8">
                            <strong><?= html_escape((string) ($trigger_opts['label'] ?? $trigger_name)); ?></strong>
                            <?php if (!empty($trigger_opts['info'])) { ?>
                                <p class="text-muted mbot0 mtop5">
                                    <?= $trigger_opts['info']; ?>
                                </p>
                            <?php } ?>
                        </div>
                        <div class="col-sm-4 text-right">
                            <div class="checkbox checkbox-primary" style="margin-top:0">
                                <input type="hidden" name="settings[<?= html_escape($optionKey); ?>]" value="0">
                                <input type="checkbox"
                                    id="sms-enabled-<?= html_escape($safeId); ?>"
                                    name="settings[<?= html_escape($optionKey); ?>]"
                                    value="1" <?= $active ? 'checked' : ''; ?>>
                                <label for="sms-enabled-<?= html_escape($safeId); ?>">Gatilho ativo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel-body">
                    <label for="sms-message-<?= html_escape($safeId); ?>" class="control-label">
                        Mensagem do gatilho
                    </label>
                    <textarea class="form-control" rows="3"
                        name="settings[<?= html_escape($messageKey); ?>]"
                        id="sms-message-<?= html_escape($safeId); ?>"
                        aria-label="Mensagem de <?= html_escape((string) $trigger_opts['label']); ?>"><?= html_escape((string) ($trigger_opts['value'] ?? '')); ?></textarea>
                    <?php hooks()->do_action('after_sms_trigger_textarea_content', [
                        'name' => $trigger_name, 'options' => $trigger_opts,
                    ]); ?>
                    <div class="mtop10 clearfix">
                        <?php if ($default !== '') { ?>
                            <button type="button" class="btn btn-default btn-xs sms-use-default"
                                data-target="sms-message-<?= html_escape($safeId); ?>"
                                data-default="<?= html_escape(base64_encode($default)); ?>">
                                Usar modelo padrão
                            </button>
                        <?php } ?>
                        <?php if (!empty($trigger_opts['merge_fields'])) { ?>
                            <button type="button" class="btn btn-link btn-xs pull-right sms-toggle-fields"
                                data-target="sms-fields-<?= html_escape($safeId); ?>"
                                aria-expanded="false">
                                Ver variáveis disponíveis
                            </button>
                        <?php } ?>
                    </div>
                    <?php if (!empty($trigger_opts['merge_fields'])) { ?>
                        <div id="sms-fields-<?= html_escape($safeId); ?>" class="well well-sm mtop10"
                            style="display:none;overflow-wrap:anywhere">
                            <small><?= html_escape(implode(', ', $trigger_opts['merge_fields'])); ?></small>
                        </div>
                    <?php } ?>
                </div>
            </section>
        </div>
    <?php
    }
    if ($lastGroup !== null) {
        echo '</div>';
    }
    ?>
    <script>
    (function () {
        if (window.smsTemplatesPtBrInitialized) return;
        window.smsTemplatesPtBrInitialized = true;
        document.addEventListener('click', function (event) {
            const restore = event.target.closest('.sms-use-default');
            if (restore) {
                const target = document.getElementById(restore.dataset.target);
                if (!target) return;
                const bytes = Uint8Array.from(atob(restore.dataset.default), c => c.charCodeAt(0));
                target.value = new TextDecoder('utf-8').decode(bytes);
                target.dispatchEvent(new Event('input', {bubbles: true}));
            }
            const toggle = event.target.closest('.sms-toggle-fields');
            if (toggle) {
                const target = document.getElementById(toggle.dataset.target);
                if (!target) return;
                const show = target.style.display === 'none';
                target.style.display = show ? 'block' : 'none';
                toggle.setAttribute('aria-expanded', show ? 'true' : 'false');
            }
        });
    }());
    </script>
</div>
