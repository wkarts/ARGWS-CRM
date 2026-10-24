<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php $embedded_panel = !empty($embedded_panel); if (!$embedded_panel) { init_head(); } ?>
<div data-asaas-operation-content>
<?php if ($embedded_panel) { foreach (['success', 'warning', 'danger', 'info'] as $alertType) {
    $panelAlert = get_instance()->session->flashdata('message-' . $alertType);
    if (is_string($panelAlert) && $panelAlert !== '') { ?>
<div role="status" class="alert alert-<?= html_escape($alertType); ?>"><?= html_escape($panelAlert); ?></div>
<?php } } } ?>
<div<?= $embedded_panel ? '' : ' id="wrapper"'; ?>><div class="content"><div class="row"><div class="col-md-12">
<div class="panel_s"><div class="panel-body">
<h4><?php echo html_escape($title); ?></h4>
<p>A origem identifica a conta e o ambiente usados na emissão. Alterar as configurações atuais não altera cobranças anteriores.</p>
<?php if (!$ready) { ?>
<div class="alert alert-warning">Atualize o módulo Asaas para a versão 1.4.6 antes de emitir ou conciliar cobranças.</div>
<?php } else { ?>
<div class="alert alert-info">Exibidos até 250 vínculos recentes de cada grupo. Legados sem origem não são associados automaticamente. Recebimentos e estornos exigem revisão, sem devolução automática.</div>
<h5>Origens registradas e pendências</h5>
<div class="table-responsive"><table class="table table-striped">
<thead><tr><th>Fatura</th><th>Conta</th><th>Ambiente</th><th>ID remoto</th><th>Estado</th><th>Último resultado</th></tr></thead><tbody>
<?php foreach ($origins as $origin) { ?>
<tr><td><?php echo (int) $origin['invoice_id']; ?></td><td><?php echo html_escape($origin['account_id']); ?></td>
<td><?php echo html_escape($origin['environment']); ?></td><td><?php echo html_escape($origin['remote_id'] ?? 'Emissão não confirmada'); ?></td>
<td><?php echo html_escape($origin['state']); ?></td><td><?php echo html_escape($origin['last_code']); ?></td></tr>
<?php } ?></tbody></table></div>
<h5>Legado com origem ainda não conciliada</h5>
<div class="table-responsive"><table class="table table-striped">
<thead><tr><th>Fatura</th><th>ID remoto</th><th>Estado conhecido</th></tr></thead><tbody>
<?php foreach ($legacy as $map) { ?><tr><td><?php echo (int) $map['invoice_id']; ?></td>
<td><?php echo html_escape($map['asaas_payment_id']); ?></td><td><?php echo html_escape($map['status'] ?? ''); ?></td></tr><?php } ?>
</tbody></table></div>
<h5>Conciliação assistida de uma origem</h5>
<p>Informe a conta que você verificou no Asaas. A chave já configurada para o ambiente selecionado precisa pertencer exatamente a ela. Esta operação só consulta o provedor e registra o vínculo após conferir referência, valor e vencimento. Não informa nem salva chaves.</p>
<?php echo form_open(admin_url('asaas/payment_origins/reconcile')); ?>
<input type="hidden" name="origin_csrf" value="<?php echo html_escape($csrf_token); ?>">
<div class="row">
<div class="col-md-2"><label for="invoice_id">ID da fatura</label><input id="invoice_id" name="invoice_id" class="form-control" type="number" min="1" required></div>
<div class="col-md-3"><label for="remote_id">ID da cobrança no Asaas</label><input id="remote_id" name="remote_id" class="form-control" maxlength="64" required></div>
<div class="col-md-4"><label for="account_id">Wallet ID da conta verificada</label><input id="account_id" name="account_id" class="form-control" maxlength="128" required></div>
<div class="col-md-3"><label for="environment">Ambiente</label><select id="environment" name="environment" class="form-control" required><option value="">Selecione</option><option value="sandbox">Homologação</option><option value="production">Produção</option></select></div>
</div><p class="mtop15"><button type="submit" class="btn btn-primary">Verificar e registrar origem</button></p>
<?php echo form_close(); ?>
<?php } ?>
</div></div></div></div></div></div>
</div>
<?php if (!$embedded_panel) { init_tail(); } ?>
<?php if (!$embedded_panel) { ?></body></html><?php } ?>
