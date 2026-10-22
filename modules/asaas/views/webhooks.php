<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h3>Webhooks de pagamento Asaas</h3>
<p>Cada endereço pertence a uma conta e ambiente verificados. O token é exclusivo do webhook, diferente da chave da API, e os novos valores são guardados cifrados para recuperação por administradores autorizados.</p>
<div class="alert alert-info">Esta tela configura o recebedor local. A confirmação de conta não comprova que o webhook remoto está cadastrado. Emitentes adicionais permanecem indisponíveis até o isolamento operacional completo.</div>
<?php if (!$ready) { ?><div class="alert alert-warning">Atualize o módulo Asaas antes de configurar recebedores. Nenhuma alteração será feita enquanto a estrutura estiver pendente.</div><?php } ?>
<?php if (!$trusted_url_ready) { ?><div class="alert alert-danger">A URL base configurada precisa ser HTTPS, sem usuário, senha, consulta ou fragmento. O endereço não será inferido do navegador ou do cabeçalho Host.</div><?php } ?>
<?php if (is_string($generated_token) && $generated_token !== '') { ?>
<div class="alert alert-warning"><strong>Token do recebedor #<?= (int) $generated_id; ?> <?= !empty($token_revealed) ? 'recuperado' : 'gerado'; ?>.</strong>
<p>Copie e configure o mesmo valor no painel Asaas. O CRM guarda o hash de verificação e uma cópia cifrada. Administradores podem recuperar o token com uma ação explícita nesta tela. Na regeneração, o token anterior deixa de autenticar imediatamente; não há período de sobreposição nesta versão. O cadastro remoto continua não verificado.</p>
<div class="form-group"><label for="generated-webhook-token">Token (revelação temporária)</label>
<div class="input-group"><input id="generated-webhook-token" type="password" readonly autocomplete="off" class="form-control" value="<?= html_escape($generated_token); ?>">
<span class="input-group-btn"><button type="button" class="btn btn-default js-reveal-webhook-token" aria-controls="generated-webhook-token" aria-pressed="false">Revelar</button>
<button type="button" class="btn btn-default js-copy-webhook-url" data-target="generated-webhook-token">Copiar token</button></span></div>
<span class="js-copy-status" role="status"></span></div></div>
<?php } ?>
<h4>Recebedores locais</h4>
<?php if (!$integrations) { ?><p>Nenhum recebedor por conta cadastrado.</p><?php } ?>
<?php foreach ($integrations as $integration) {
    $id = (int) $integration['id'];
    $enabled = (int) $integration['enabled'] === 1;
    $callback = $integration['callback_url'] ?? null;
?>
<section class="well" aria-labelledby="integration-title-<?= $id; ?>">
<h4 id="integration-title-<?= $id; ?>"><?= html_escape($integration['label']); ?></h4>
<p>Emitente: <?= $integration['issuer_id'] === null ? 'Instalação legada (sem emitente isolado)' : 'Emitente #' . (int) $integration['issuer_id']; ?>
 · Provedor: Asaas · Conta: <?= html_escape($integration['account_id']); ?>
 · Ambiente: <?= $integration['environment'] === 'sandbox' ? 'Homologação' : 'Produção'; ?></p>
<p>Recebedor local: <strong><?= $enabled ? 'Habilitado' : 'Desativado'; ?></strong>
 · Cadastro remoto: <strong>Não verificado</strong>
 · Token: <span aria-label="Token protegido">••••••••</span> · <?= !empty($integration['token_recoverable']) ? 'Recuperação cifrada disponível' : 'Legado somente hash: regenere explicitamente para permitir recuperação'; ?></p>
<div class="form-group">
<label for="webhook-url-<?= $id; ?>">Endereço de recebimento desta conta</label>
<div class="input-group"><input id="webhook-url-<?= $id; ?>" type="text" readonly class="form-control" value="<?= html_escape($callback ?? 'URL indisponível: revise a configuração HTTPS.'); ?>">
<span class="input-group-btn"><button type="button" class="btn btn-default js-copy-webhook-url" data-target="webhook-url-<?= $id; ?>" <?= $callback === null ? 'disabled' : ''; ?>>Copiar URL</button></span></div>
<span class="text-muted js-copy-status" role="status"></span></div>
<p class="text-muted">Último evento autenticado: <?= html_escape($integration['last_valid_at'] ?: 'Nenhum'); ?>
 · Última rejeição de autenticação/entrada: <?= html_escape($integration['last_rejected_at'] ?: 'Nenhuma'); ?>
 · Rejeições: <?= (int) $integration['rejection_count']; ?>
 · Última rotação: <?= html_escape($integration['rotated_at'] ?: 'Não realizada'); ?></p>
<a href="<?= html_escape(admin_url('asaas?integration=' . $id)); ?>">Ver eventos e falhas deste recebedor</a>
<?php if ($enabled) { ?>
<?php if (!empty($integration['token_recoverable'])) { ?>
<?= form_open(admin_url('asaas/reveal_integration_token'), ['autocomplete' => 'off']); ?>
<input type="hidden" name="webhook_admin_csrf" value="<?= html_escape($csrf_token); ?>"><input type="hidden" name="integration_id" value="<?= $id; ?>">
<button type="submit" class="btn btn-default">Recuperar token para revelar ou copiar</button><?= form_close(); ?>
<?php } ?>
<details class="mtop15"><summary>Trocar token local</summary>
<p class="text-warning">A troca invalida imediatamente o token anterior no CRM. Atualize o mesmo token no painel Asaas; os eventos serão recusados enquanto os valores não coincidirem.</p>
<?= form_open(admin_url('asaas/rotate_integration_token'), ['autocomplete' => 'off']); ?>
<input type="hidden" name="webhook_admin_csrf" value="<?= html_escape($csrf_token); ?>"><input type="hidden" name="integration_id" value="<?= $id; ?>">
<button type="submit" class="btn btn-warning">REGERAR token local</button><?= form_close(); ?></details>
<details class="mtop15"><summary>Desativar recebedor local</summary><p>Novos eventos deste endereço deixarão de ser aceitos e o processamento dos eventos pendentes deste recebedor será interrompido. Esta versão não oferece reativação nem transferência dos eventos já vinculados: confira as pendências antes de desativar. Histórico e cadastro remoto serão preservados.</p>
<?= form_open(admin_url('asaas/disable_integration')); ?>
<input type="hidden" name="webhook_admin_csrf" value="<?= html_escape($csrf_token); ?>"><input type="hidden" name="integration_id" value="<?= $id; ?>">
<button type="submit" class="btn btn-danger">Desativar recebedor local</button><?= form_close(); ?></details>
<?php } ?></section>
<?php } ?>
<?php if ($selected_id !== null) { ?>
<h4>Últimos eventos e processamento do recebedor #<?= (int) $selected_id; ?></h4>
<p class="text-muted">Evento autenticado não significa pagamento conciliado. Falhas, revisão e rejeições precisam de conferência. Somente metadados são exibidos, sem payloads ou segredos.</p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Evento</th><th>Tipo</th><th>Processamento</th><th>Estado verificado</th><th>Recebido</th><th>Processado</th><th>Tentativas</th></tr></thead><tbody>
<?php foreach ($events as $event) { ?><tr>
<td><?= html_escape($event['event_id']); ?></td><td><?= html_escape($event['event_type']); ?></td><td><?= html_escape(\Asaas\WebhookAdminPresentation::statusLabel($event['process_status'])); ?></td>
<td><?= html_escape(\Asaas\WebhookAdminPresentation::statusLabel($event['verified_status']) ?: 'Não verificado'); ?></td><td><?= html_escape($event['received_at']); ?></td>
<td><?= html_escape($event['processed_at'] ?: 'Pendente'); ?></td><td><?= (int) $event['attempt_count']; ?></td>
</tr><?php } ?>
<?php if (!$events) { ?><tr><td colspan="7">Nenhum evento registrado para este recebedor.</td></tr><?php } ?>
</tbody></table></div><?php } ?>
<h4>Automações após recebimento local</h4>
<p>O registro financeiro validado é separado das automações de contabilidade, produtos e notificações. Itens pendentes abaixo ainda não dispararam essas automações; não repita o pagamento.</p>
<?php if (!$pending_automations) { ?><p>Nenhuma automação pendente nesta listagem.</p><?php } ?>
<?php foreach ($pending_automations as $pending) { ?><p>Fatura #<?= (int) ($pending['invoice_id'] ?? 0); ?> · Registro #<?= (int) ($pending['id'] ?? 0); ?> · Pendente de processamento</p><?php } ?>
<h4>Eventos que exigem conferência</h4>
<p>A origem da cobrança deve estar conciliada antes de liberar um evento. A conta e o ambiente precisam coincidir com o recebedor selecionado. A validação canônica será refeita pelo CRON; esta ação não confirma pagamento.</p>
<p><a href="<?= html_escape(admin_url('asaas/payment_origins')); ?>">Conciliar origem e consultar pendências</a></p>
<?php if (!$review_queue) { ?><p>Nenhum evento pendente de conferência nesta listagem.</p><?php } ?>
<?php foreach ($review_queue as $review) { ?>
<div class="well">
<p>Evento <?= html_escape($review['event_id']); ?> · <?= html_escape($review['event_type']); ?>
 · Cobrança <?= html_escape($review['asaas_payment_id'] ?? ''); ?> · Estado <?= html_escape(\Asaas\WebhookAdminPresentation::statusLabel($review['process_status'])); ?></p>
<?= form_open(admin_url('asaas/retry_reviewed_event')); ?>
<input type="hidden" name="webhook_admin_csrf" value="<?= html_escape($csrf_token); ?>">
<input type="hidden" name="event_row_id" value="<?= (int) $review['id']; ?>">
<label for="review-integration-<?= (int) $review['id']; ?>">Recebedor da conta conciliada</label>
<select class="form-control" id="review-integration-<?= (int) $review['id']; ?>" name="integration_id" required>
<option value="">Selecione explicitamente</option>
<?php foreach ($integrations as $candidate) { if ((int) $candidate['enabled'] !== 1) { continue; } ?>
<option value="<?= (int) $candidate['id']; ?>"><?= html_escape($candidate['label'] . ' · ' . $candidate['account_id'] . ' · ' . ($candidate['environment'] === 'sandbox' ? 'Homologação' : 'Produção')); ?></option>
<?php } ?></select>
<button type="submit" class="btn btn-warning mtop10">Revalidar evento após conciliação</button>
<?= form_close(); ?></div>
<?php } ?>
<h4>Cadastrar recebedor local</h4>
<p>A conta será consultada no Asaas com a credencial do ambiente selecionado. O identificador deve coincidir com a conta informada. A operação não cadastra webhook remoto nem emite cobrança.</p>
<?= form_open(admin_url('asaas/register_integration'), ['autocomplete' => 'off']); ?>
<input type="hidden" name="webhook_admin_csrf" value="<?= html_escape($csrf_token); ?>">
<fieldset <?= !$ready || !$trusted_url_ready ? 'disabled' : ''; ?>>
<div class="form-group"><label for="webhook-label">Nome de identificação</label><input id="webhook-label" type="text" class="form-control" name="label" required maxlength="100"></div>
<div class="form-group"><label for="webhook-environment">Ambiente</label><select id="webhook-environment" class="form-control" name="environment" required>
<option value="">Selecione explicitamente</option><option value="sandbox">Homologação</option><option value="production">Produção</option></select></div>
<div class="form-group"><label for="webhook-account">Identificador da conta Asaas (wallet ID)</label><input id="webhook-account" type="text" class="form-control" name="account_id" required maxlength="128" autocomplete="off"></div>
<p>O botão verifica a conta e gera um token exclusivo por sorteio criptográfico. O token poderá ser recuperado por uma ação administrativa protegida, para copiar e configurar no painel Asaas. Nenhum segredo existente será rotacionado automaticamente.</p>
<button type="submit" class="btn btn-primary">Verificar conta e GERAR token</button></fieldset><?= form_close(); ?>
<p class="text-muted mtop15">O endpoint legado continua separado e não comprova o vínculo por conta. Não configure eventos financeiros no módulo genérico de webhooks.</p>
<a class="btn btn-default" href="<?= html_escape(admin_url('asaas/payment_origins')); ?>">Origem e pendências</a>
<a class="btn btn-default" href="<?= html_escape(admin_url('settings?group=online_payment_modes')); ?>">Configurações de pagamento</a>
</div></div></div></div>
<?php init_tail(); ?>
<script>
document.querySelectorAll('.js-copy-webhook-url').forEach(function (button) {
  button.addEventListener('click', function () {
    var input = document.getElementById(button.getAttribute('data-target'));
    var status = button.closest('.form-group').querySelector('.js-copy-status');
    if (!input || button.disabled) return;
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(input.value).then(function () { status.textContent = 'Valor copiado.'; })
      .catch(function () { input.focus(); input.select(); status.textContent = 'Selecione e copie o valor.'; });
    } else { input.focus(); input.select(); status.textContent = 'Selecione e copie o valor.'; }
  });
});
document.querySelectorAll('.js-reveal-webhook-token').forEach(function (button) {
  button.addEventListener('click', function () {
    var input = document.getElementById(button.getAttribute('aria-controls'));
    if (!input) return;
    var show = input.type === 'password'; input.type = show ? 'text' : 'password';
    button.setAttribute('aria-pressed', show ? 'true' : 'false'); button.textContent = show ? 'Ocultar' : 'Revelar';
  });
});
window.addEventListener('pagehide', function () {
  var input = document.getElementById('generated-webhook-token');
  if (input) { input.value = ''; input.type = 'password'; }
});
</script>
