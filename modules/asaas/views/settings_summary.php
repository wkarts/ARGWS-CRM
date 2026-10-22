<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div data-asaas-settings-section="authentication">
    <h4>URLs da API por ambiente</h4>
    <?php foreach ($asaas_summary['api_urls'] as $environment => $url) { ?>
    <div class="form-group">
        <label for="asaas-api-url-<?= html_escape($environment); ?>"><?= $environment === 'production' ? 'Produção' : 'Homologação'; ?></label>
        <div class="input-group">
            <input id="asaas-api-url-<?= html_escape($environment); ?>" class="form-control" type="url" readonly value="<?= html_escape($url); ?>">
            <span class="input-group-btn"><button type="button" class="btn btn-default" data-asaas-copy="asaas-api-url-<?= html_escape($environment); ?>">Copiar URL</button></span>
        </div>
        <span role="status" class="help-block" data-asaas-copy-status></span>
    </div>
    <?php } ?>
    <p class="help-block">As URLs e as credenciais de ambos os ambientes permanecem independentes. O seletor de novas operações não altera recebedores nem cobranças existentes.</p>
</div>
<div data-asaas-settings-section="webhook">
    <h4>URLs de recebimento por conta e ambiente</h4>
    <?php $states = [
        'restricted' => 'Os recebedores são exibidos aos administradores no contexto autorizado. As contas de outra empresa não são carregadas aqui.',
        'schema_pending' => 'Atualize o módulo Asaas para consultar os recebedores locais.',
        'invalid_base' => 'A URL pública configurada precisa ser HTTPS válida. Revise a configuração da aplicação; o endereço não será inferido do navegador ou do proxy.',
        'empty' => 'Cadastre um recebedor local para gerar sua URL específica de conta e ambiente.',
        'unavailable' => 'Não foi possível consultar os recebedores. Nenhum endereço ou segredo foi inferido.',
    ]; ?>
    <?php if (isset($states[$asaas_summary['state']])) { ?><p role="status"><?= html_escape($states[$asaas_summary['state']]); ?></p><?php } ?>
    <?php foreach ($asaas_summary['integrations'] as $integration) { $fieldId = 'asaas-settings-webhook-url-' . $integration['id']; ?>
    <section class="well">
        <h5><?= html_escape($integration['label']); ?></h5>
        <p>Conta: <?= html_escape($integration['account_id']); ?> · <?= $integration['environment'] === 'production' ? 'Produção' : 'Homologação'; ?> · <?= $integration['enabled'] ? 'Recebedor habilitado' : 'Recebedor desativado'; ?></p>
        <div class="form-group">
            <label for="<?= html_escape($fieldId); ?>">URL do webhook</label>
            <div class="input-group">
                <input id="<?= html_escape($fieldId); ?>" type="url" readonly class="form-control" value="<?= html_escape($integration['callback_url']); ?>">
                <span class="input-group-btn"><button type="button" class="btn btn-default" data-asaas-copy="<?= html_escape($fieldId); ?>">Copiar URL</button></span>
            </div>
            <span role="status" class="help-block" data-asaas-copy-status></span>
        </div>
    </section>
    <?php } ?>
    <p class="help-block">A URL identifica o recebedor; o token é separado. Exibir o endereço não comprova cadastro remoto ou conectividade pública. Credenciais e tokens são administrados pela empresa e pelo ambiente correspondentes.</p>
</div>
