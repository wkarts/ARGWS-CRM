<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h1 class="tw-text-xl tw-font-semibold">Recorrências</h1>
<p>As regras independentes guardam cliente, itens, valores e calendário. Geram somente rascunhos locais para revisão, sem envio ou cobrança automática.</p>
<div class="tw-mb-4">
    <?php if (staff_can('create', 'invoices')) { ?><a class="btn btn-primary" href="<?= admin_url('recurring_rules/create'); ?>">Nova recorrência</a><?php } ?>
    <a class="btn btn-default" href="<?= admin_url('recurring_rules'); ?>">Regras independentes</a>
    <a class="btn btn-default" href="<?= admin_url('recurring_rules?source=legacy'); ?>">Matrizes legadas</a>
    <a class="btn btn-default" href="<?= admin_url('invoices/recurring'); ?>">Faturas recorrentes</a>
</div>
<?php if ($source === 'legacy') { ?>
<div class="alert alert-info">Matrizes legadas continuam usando o fluxo atual. Migrar encerra sua programação legada e cria uma regra independente pausada, preservando as faturas existentes. A ativação posterior gera apenas rascunhos locais.</div>
<?php } else { ?>
<form method="get" action="<?= admin_url('recurring_rules'); ?>" class="tw-mb-4">
    <label for="filter-state">Estado</label>
    <select id="filter-state" name="state" class="form-control tw-inline-block tw-w-auto">
        <?php foreach (['' => 'Todos', 'active' => 'Ativas', 'paused' => 'Pausadas', 'ended' => 'Encerradas'] as $key => $label) { ?>
        <option value="<?= e($key); ?>" <?= $state === $key ? 'selected' : ''; ?>><?= e($label); ?></option><?php } ?>
    </select>
    <label for="calendar-group">Grupo de calendário</label>
    <input id="calendar-group" name="calendar_group" value="<?= e($calendar_group); ?>" maxlength="64" class="form-control tw-inline-block tw-w-auto">
    <button class="btn btn-default">Filtrar</button>
</form>
<?php } ?>
<?= form_open(admin_url('recurring_rules/preview')); ?>
<?= form_hidden('recurring_rules_csrf', $recurring_rules_csrf); ?>
<?= form_hidden('source', $source); ?>
<div class="table-responsive"><table class="table table-striped">
<thead><tr><th><span class="sr-only">Selecionar</span></th><th>Regra / matriz</th><th>Estado</th><th>Grupos</th><th>Emitente</th><th>Vencimento</th><th>Próximas emissões → vencimentos</th></tr></thead>
<tbody>
<?php foreach ($rows as $row) { $schedule = $row['schedule'] ?? []; ?>
<tr>
<td><?php if (staff_can('edit', 'invoices')) { ?><input type="checkbox" name="ids[]" value="<?= (int) $row['id']; ?>" aria-label="Selecionar registro <?= (int) $row['id']; ?>"><?php } ?></td>
<td><a href="<?= admin_url($source === 'legacy' ? 'invoices/list_invoices/' . (int) $row['id'] : 'recurring_rules/view/' . (int) $row['id']); ?>"><?= e($source === 'legacy' ? 'Fatura #' . $row['id'] : $row['title']); ?></a><br><small>Cliente #<?= (int) $row['clientid']; ?></small></td>
<td><?= e(['active' => 'Ativa', 'paused' => 'Pausada', 'ended' => 'Encerrada'][$schedule['state'] ?? ''] ?? 'Revisar'); ?></td>
<td><?= e($row['calendar_group'] ?? 'Legado'); ?><br><small><?= $source === 'legacy' ? 'Integração existente' : 'Revisão manual'; ?></small></td>
<td><?= e($schedule['emitter_key'] ?? 'legacy'); ?></td>
<td><?= ($schedule['due_mode'] ?? 'days') === 'fixed_day' ? 'Dia fixo' : 'Prazo em dias'; ?></td>
<td><?php if ($row['error']) { ?><span class="text-danger"><?= e($row['error']); ?></span><?php } else { foreach ($row['preview'] as $dates) { ?>
<div><?= e($dates['issue']); ?> → <?= e($dates['due'] ?? 'Sem vencimento'); ?></div>
<?php } } ?></td>
</tr>
<?php } ?>
<?php if (!$rows) { ?><tr><td colspan="7">Nenhuma recorrência neste filtro.</td></tr><?php } ?>
</tbody></table></div>
<?php if (staff_can('edit', 'invoices')) { ?>
<div class="row tw-mt-4"><div class="col-md-4"><label for="bulk-action">Alterar somente ciclos futuros</label>
<select id="bulk-action" name="action" class="form-control">
<option value="due_mode">Alterar regra de vencimento</option>
<?php if ($source === 'legacy') { if (staff_can('create', 'invoices')) { ?><option value="migrate">Migrar para regras independentes pausadas</option><?php } } else { ?>
<option value="pause">Pausar</option><option value="resume">Ativar / retomar sem períodos perdidos</option><option value="end">Encerrar definitivamente</option>
<?php } ?>
</select></div><div class="col-md-4"><label for="due-mode">Modo de vencimento (quando aplicável)</label>
<select id="due-mode" name="due_mode" class="form-control"><option value="days">Prazo em dias (legado)</option><option value="fixed_day">Dia fixo do mês (opcional)</option></select>
</div><div class="col-md-4"><p>Selecione até 100 registros. A prévia não modifica faturas.</p><button class="btn btn-primary">Revisar prévia</button></div></div>
<?php } ?>
<?= form_close(); ?>
<div class="tw-mt-4">
<?php $query = ['source' => $source, 'state' => $state, 'calendar_group' => $calendar_group]; ?>
<?php if ($page > 1) { ?><a class="btn btn-default" href="<?= admin_url('recurring_rules?' . http_build_query($query + ['page' => $page - 1])); ?>">Anterior</a><?php } ?>
<span>Página <?= (int) $page; ?></span>
<?php if ($more) { ?><a class="btn btn-default" href="<?= admin_url('recurring_rules?' . http_build_query($query + ['page' => $page + 1])); ?>">Próxima</a><?php } ?>
</div></div></div></div></div>
<?php init_tail(); ?></body></html>
