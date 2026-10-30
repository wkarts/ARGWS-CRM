<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); $value = static function ($key, $default = '') use ($input) { return is_scalar($input[$key] ?? null) ? $input[$key] : $default; }; ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h1 class="tw-text-xl tw-font-semibold">Nova recorrência</h1>
<p>Reutilize o cadastro de cliente e os itens do catálogo. Os preços e impostos são copiados do catálogo para um modelo independente. Nenhuma fatura é criada agora.</p>
<?php if ($error) { ?><div class="alert alert-danger" role="alert"><?= e($error); ?></div><?php } ?>
<?= form_open(admin_url('recurring_rules/create'), ['id' => 'recurring-rule-form']); ?>
<?= form_hidden('recurring_rules_csrf', $recurring_rules_csrf); ?>
<?= form_hidden('emitter_key', 'legacy'); ?>
<?= form_hidden('request_key', $value('request_key', bin2hex(random_bytes(32)))); ?>
<div class="row"><div class="col-md-6"><div class="form-group"><label for="rule-title">Título</label><input class="form-control" id="rule-title" name="title" maxlength="191" required value="<?= e($value('title')); ?>"></div></div>
<div class="col-md-6"><div class="form-group"><label for="clientid">Cliente</label>
<select id="clientid" name="clientid" data-live-search="true" data-width="100%" class="ajax-search" required data-none-selected-text="Selecione um cliente">
<?php if ($value('clientid')) { ?><option value="<?= e($value('clientid')); ?>" selected>Cliente #<?= e($value('clientid')); ?></option><?php } ?>
</select></div></div></div>
<div class="row"><div class="col-md-4"><div class="form-group"><label for="anchor-date">Primeira emissão</label><input class="form-control" type="date" id="anchor-date" name="anchor_date" required min="<?= e(date('Y-m-d')); ?>" value="<?= e($value('anchor_date', date('Y-m-d'))); ?>"></div></div>
<div class="col-md-4"><div class="form-group"><label for="anchor-due">Vencimento da primeira emissão</label><input class="form-control" type="date" id="anchor-due" name="anchor_due" value="<?= e($value('anchor_due')); ?>"></div></div>
<div class="col-md-4"><div class="form-group"><label for="due-mode">Regra de vencimento</label><select class="form-control" id="due-mode" name="due_mode"><option value="days" <?= $value('due_mode') !== 'fixed_day' ? 'selected' : ''; ?>>Prazo em dias (legado)</option><option value="fixed_day" <?= $value('due_mode') === 'fixed_day' ? 'selected' : ''; ?>>Dia fixo do mês (opcional)</option></select></div></div></div>
<p>Dia fixo está disponível para períodos mensais ou anuais. Dias 29, 30 e 31 usam o último dia nos meses curtos e recuperam o dia original no mês seguinte.</p>
<div class="row"><div class="col-md-3"><div class="form-group"><label for="interval-count">A cada</label><input class="form-control" id="interval-count" type="number" name="interval_count" min="1" max="120" required value="<?= e($value('interval_count', '1')); ?>"></div></div>
<div class="col-md-3"><div class="form-group"><label for="interval-unit">Período</label><select class="form-control" id="interval-unit" name="interval_unit"><?php foreach (['MONTH' => 'Mês(es)', 'YEAR' => 'Ano(s)', 'WEEK' => 'Semana(s)', 'DAY' => 'Dia(s)'] as $key => $label) { ?><option value="<?= e($key); ?>" <?= $value('interval_unit', 'MONTH') === $key ? 'selected' : ''; ?>><?= e($label); ?></option><?php } ?></select></div></div>
<div class="col-md-3"><div class="form-group"><label for="cycles-limit">Ciclos (0 = sem limite)</label><input class="form-control" type="number" id="cycles-limit" name="cycles_limit" min="0" max="10000" required value="<?= e($value('cycles_limit', '0')); ?>"></div></div>
<div class="col-md-3"><div class="form-group"><label for="calendar-group">Grupo de calendário</label><input class="form-control" id="calendar-group" name="calendar_group" maxlength="64" value="<?= e($value('calendar_group')); ?>" placeholder="Ex.: mensalidades dia 5"></div></div></div>
<h2 class="tw-text-lg">Itens e valores</h2>
<p>Use ponto para quantidades decimais. A moeda e o endereço são os do cliente. Descontos e condições especiais existentes podem ser preservados pela migração explícita de uma matriz legada.</p>
<div id="rule-items">
<?php $lines = isset($input['items']) && is_array($input['items']) ? array_slice($input['items'], 0, 100) : [['id' => '', 'qty' => '1']]; foreach (array_values($lines) as $i => $line) { $line = is_array($line) ? $line : []; ?>
<div class="row rule-item tw-mb-4"><div class="col-md-8"><label for="catalogue-<?= $i; ?>">Item do catálogo</label><select class="ajax-search rule-catalogue" id="catalogue-<?= $i; ?>" name="items[<?= $i; ?>][id]" data-live-search="true" data-width="100%" required data-none-selected-text="Selecione um item"><?php if (isset($line['id']) && is_scalar($line['id']) && $line['id'] !== '') { ?><option value="<?= e($line['id']); ?>" selected>Item #<?= e($line['id']); ?></option><?php } ?></select></div>
<div class="col-md-3"><label for="quantity-<?= $i; ?>">Quantidade</label><input class="form-control" id="quantity-<?= $i; ?>" type="number" step="0.01" min="0.01" name="items[<?= $i; ?>][qty]" value="<?= e(isset($line['qty']) && is_scalar($line['qty']) ? $line['qty'] : '1'); ?>" required></div>
<div class="col-md-1"><button type="button" class="btn btn-default remove-rule-item tw-mt-6" aria-label="Remover item">×</button></div></div>
<?php } ?>
</div>
<button type="button" class="btn btn-default" id="add-rule-item">Adicionar item</button>
<hr>
<p><strong>Emitente:</strong> instalação atual (legacy). <strong>Integração:</strong> revisão manual. A regra será salva pausada. Ative somente depois de conferir o modelo e a prévia.</p>
<button class="btn btn-primary" id="save-rule">Salvar recorrência pausada</button>
<a class="btn btn-default" href="<?= admin_url('recurring_rules'); ?>">Cancelar</a>
<?= form_close(); ?>
</div></div></div></div>
<?php init_tail(); ?>
<script>
$(function () {
    init_ajax_search('customer', '#clientid.ajax-search');
    var nextItem = <?= count($lines); ?>;
    $('.rule-catalogue').each(function () { init_ajax_search('items', '#' + this.id, undefined, admin_url + 'items/search'); });
    $('#add-rule-item').on('click', function () {
        if ($('#rule-items .rule-item').length >= 100) return;
        var index = nextItem++;
        var row = $('<div class="row rule-item tw-mb-4"><div class="col-md-8"><label>Item do catálogo</label><select class="ajax-search rule-catalogue" data-live-search="true" data-width="100%" required data-none-selected-text="Selecione um item"></select></div><div class="col-md-3"><label>Quantidade</label><input class="form-control" type="number" step="0.01" min="0.01" value="1" required></div><div class="col-md-1"><button type="button" class="btn btn-default remove-rule-item tw-mt-6" aria-label="Remover item">×</button></div></div>');
        row.find('select').attr({id: 'catalogue-' + index, name: 'items[' + index + '][id]'});
        row.find('label').first().attr('for', 'catalogue-' + index);
        row.find('input').attr({id: 'quantity-' + index, name: 'items[' + index + '][qty]'});
        row.find('label').last().attr('for', 'quantity-' + index);
        $('#rule-items').append(row);
        init_ajax_search('items', '#catalogue-' + index, undefined, admin_url + 'items/search');
    });
    $('#rule-items').on('click', '.remove-rule-item', function () {
        if ($('#rule-items .rule-item').length > 1) $(this).closest('.rule-item').remove();
    });
    $('#recurring-rule-form').on('submit', function () { $('#save-rule').prop('disabled', true); });
    window.addEventListener('pageshow', function () { $('#save-rule').prop('disabled', false); });
});
</script></body></html>
