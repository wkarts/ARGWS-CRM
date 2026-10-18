<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h1 class="tw-text-xl tw-font-semibold"><?= e($rule['title']); ?></h1>
<p>Estado: <strong><?= e(['active' => 'Ativa', 'paused' => 'Pausada', 'ended' => 'Encerrada'][$rule['state']] ?? $rule['state']); ?></strong>. Cliente #<?= (int) $rule['clientid']; ?>. Emitente: <?= e($rule['emitter_key']); ?>. Grupo: <?= e($rule['calendar_group']); ?>. Integração: revisão manual.</p>
<p>Próxima emissão programada: <?= e($rule['next_issue_date']); ?>. Ciclos gerados: <?= (int) $rule['cycles_emitted']; ?> / <?= (int) $rule['cycles_limit'] === 0 ? 'sem limite' : (int) $rule['cycles_limit']; ?>.</p>
<p>O modelo abaixo é independente do catálogo e da fatura de origem. Mudanças nesses registros não reescrevem esta regra nem suas faturas.</p>
<div class="table-responsive"><table class="table"><thead><tr><th>Item</th><th>Quantidade</th><th>Valor unitário</th><th>Impostos</th></tr></thead><tbody>
<?php foreach ($template['newitems'] as $item) { ?><tr><td><?= e($item['description']); ?><br><small><?= e($item['long_description']); ?></small></td><td><?= e($item['qty']); ?></td><td><?= e($item['rate']); ?></td><td><?= e(implode(', ', $item['taxname'] ?? [])); ?></td></tr><?php } ?>
</tbody></table></div>
<p>Subtotal: <?= e($template['subtotal']); ?>. Total: <?= e($template['total']); ?>. Moeda: <?= e(get_currency($template['currency'])->name); ?>.</p>
<?php if (staff_can('edit', 'invoices') && $rule['state'] !== 'ended') { ?>
<?= form_open(admin_url('recurring_rules/preview')); ?>
<?= form_hidden('recurring_rules_csrf', $recurring_rules_csrf); ?>
<?= form_hidden('source', 'rules'); ?><?= form_hidden('ids[]', $rule['id']); ?>
<label for="rule-action">Próxima ação</label><select id="rule-action" name="action" class="form-control tw-max-w-md">
<?php if ($rule['state'] === 'paused') { ?><option value="resume">Ativar / retomar sem períodos perdidos</option><?php } else { ?><option value="pause">Pausar próximos ciclos</option><option value="due_mode">Alterar regra de vencimento</option><?php } ?>
<option value="end">Encerrar definitivamente</option></select>
<label for="due-mode">Regra de vencimento (quando aplicável)</label><select id="due-mode" name="due_mode" class="form-control tw-max-w-md"><option value="days">Prazo em dias (legado)</option><option value="fixed_day" <?= $rule['due_mode'] === 'fixed_day' ? 'selected' : ''; ?>>Dia fixo do mês (opcional)</option></select>
<button class="btn btn-primary tw-mt-4">Revisar prévia</button><?= form_close(); ?>
<?php } ?>
<h2 class="tw-text-lg tw-mt-6">Faturas geradas (últimas 100)</h2>
<p>Pausar ou encerrar preserva todas as faturas. Rascunhos locais exigem revisão antes de emissão, envio ou escolha de cobrança.</p>
<ul><?php foreach ($runs as $run) { ?><li><?= e($run['cycle_date']); ?>: <a href="<?= admin_url('invoices/list_invoices/' . (int) $run['invoice_id']); ?>">Fatura #<?= (int) $run['invoice_id']; ?></a></li><?php } ?></ul>
<h2 class="tw-text-lg tw-mt-6">Auditoria (últimas 100 operações)</h2>
<div class="table-responsive"><table class="table"><thead><tr><th>Quando</th><th>Autor</th><th>Ação</th><th>Antes / depois</th></tr></thead><tbody>
<?php foreach ($audit as $entry) { ?><tr><td><?= e($entry['created_at']); ?></td><td><?= (int) $entry['staff_id'] === 0 ? 'Agendador' : 'Colaborador #' . (int) $entry['staff_id']; ?></td><td><?= e(['create' => 'Criação', 'migrate' => 'Migração', 'due_mode' => 'Vencimento', 'pause' => 'Pausa', 'resume' => 'Ativação / retomada', 'end' => 'Encerramento', 'generate_draft' => 'Rascunho gerado'][$entry['action']] ?? $entry['action']); ?></td><td><small><?= e($entry['before_json']); ?><br>→ <?= e($entry['after_json']); ?></small></td></tr><?php } ?>
</tbody></table></div>
<a class="btn btn-default" href="<?= admin_url('recurring_rules'); ?>">Voltar às recorrências</a>
</div></div></div></div><?php init_tail(); ?></body></html>
