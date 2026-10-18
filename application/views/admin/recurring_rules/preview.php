<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h1 class="tw-text-xl tw-font-semibold">Prévia dos ciclos futuros</h1>
<p>Operação: <strong><?= e(['due_mode' => 'Alterar vencimento', 'migrate' => 'Migrar para regras independentes pausadas', 'pause' => 'Pausar', 'resume' => 'Ativar / retomar', 'end' => 'Encerrar definitivamente'][$action]); ?></strong></p>
<div class="alert alert-warning">Nenhuma data, valor ou situação das faturas já emitidas será alterada. Esta confirmação não gera fatura, boleto, cobrança ou envio. A prévia expira em 15 minutos.</div>
<?php if ($action === 'migrate') { ?><p>As matrizes selecionadas deixarão de gerar novos ciclos pelo fluxo legado. As novas regras ficarão pausadas, com integração manual e geração de rascunhos locais após ativação.</p><?php } ?>
<?php if ($action === 'resume') { ?><p>A retomada pula os períodos anteriores a hoje. Não cobra retroativamente os períodos em que a regra ficou pausada.</p><?php } ?>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Registro</th><th>Antes</th><th>Depois</th><th>Próximos 3 ciclos (emissão → vencimento)</th></tr></thead><tbody>
<?php foreach ($rows as $row) { ?>
<tr><td>#<?= (int) $row['id']; ?></td>
<?php if ($row['error']) { ?><td colspan="3" class="text-danger"><?= e($row['error']); ?></td><?php } else { ?>
<?php foreach (['before', 'after'] as $stage) { $s = $row[$stage]; ?><td>
<?= e(['active' => 'Ativa', 'paused' => 'Pausada', 'ended' => 'Encerrada'][$s['state']]); ?><br>
<?= $s['due_mode'] === 'fixed_day' ? 'Dia fixo' : 'Prazo em dias'; ?><br>
<small>Âncora: <?= e($s['anchor_date']); ?> → <?= e($s['anchor_due'] ?? 'Sem vencimento'); ?></small><br>
<small>Intervalo: <?= (int) $s['interval_count']; ?> <?= e(['DAY' => 'dia(s)', 'WEEK' => 'semana(s)', 'MONTH' => 'mês(es)', 'YEAR' => 'ano(s)'][$s['interval_unit']]); ?></small>
</td><?php } ?>
<td><strong>Antes</strong><?php foreach ($row['before_dates'] as $date) { ?><div><?= e($date['issue']); ?> → <?= e($date['due'] ?? 'Sem vencimento'); ?></div><?php } ?>
<strong>Depois<?= in_array($row['after']['state'], ['paused', 'ended'], true) ? ' (sem execução neste estado)' : ''; ?></strong>
<?php foreach ($row['after_dates'] as $date) { ?><div><?= e($date['issue']); ?> → <?= e($date['due'] ?? 'Sem vencimento'); ?></div><?php } ?></td>
<?php } ?></tr><?php } ?>
</tbody></table></div>
<?php if ($token) { ?>
<?= form_open(admin_url('recurring_rules/apply')); ?>
<?= form_hidden('recurring_rules_csrf', $recurring_rules_csrf); ?>
<?= form_hidden('token', $token); ?>
<div class="checkbox"><input type="checkbox" id="confirm-future" name="confirm_future" value="1" required><label for="confirm-future">Confirmo o lote exato acima e que somente ciclos futuros serão afetados.</label></div>
<button class="btn btn-primary">Confirmar alteração</button>
<a class="btn btn-default" href="<?= admin_url('recurring_rules?source=' . $source); ?>">Cancelar</a>
<?= form_close(); ?>
<?php } else { ?><div class="alert alert-danger">O lote contém registros inelegíveis. Nenhum registro será alterado. Ajuste a seleção e gere nova prévia.</div><a class="btn btn-default" href="<?= admin_url('recurring_rules?source=' . $source); ?>">Voltar à seleção</a><?php } ?>
</div></div></div></div><?php init_tail(); ?></body></html>
