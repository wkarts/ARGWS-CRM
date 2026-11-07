<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<h3>Comparar ou conciliar origens selecionadas</h3>
<p>Selecione até 20 origens já vinculadas. Comparar consulta e registra a observação, sem baixa. Conciliar consulta novamente e só aplica recebimento elegível pelo escritor nativo. Valores parciais, divergências e origens restritas exigem revisão. Parar aguarda o item em andamento; não desfaz um recebimento confirmado.</p>
<form id="asaas-batch-form" method="post" action="<?= $e($action_url); ?>">
<?php $hidden(['company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<div class="form-group"><label for="asaas-batch-mode">Operação</label><select class="form-control" id="asaas-batch-mode" name="batch_mode"><option value="compare">Comparar, sem baixa</option><?php if($can_settle){ ?><option value="reconcile">Conciliar recebimentos elegíveis</option><?php } ?></select></div>
<div class="table-responsive"><table class="table"><thead><tr><th>Selecionar</th><th>Origem / fatura</th><th>Cobrança</th><th>Ambiente</th></tr></thead><tbody><?php foreach($origins as $origin){ ?><tr><td><input type="checkbox" name="batch_origin" value="<?= (int)$origin['id']; ?>" aria-label="Selecionar origem <?= (int)$origin['id']; ?>"></td><td>#<?= (int)$origin['id']; ?> / #<?= (int)$origin['invoice_id']; ?></td><td><?= $e($origin['remote_id']); ?></td><td><?= $e($environmentLabel($origin['environment'])); ?></td></tr><?php } ?></tbody></table></div>
<label><input type="checkbox" name="confirm" value="1"> Confirmo aplicar os recebimentos elegíveis das origens selecionadas ao escolher Conciliar.</label>
<div><button class="btn btn-primary" type="submit" data-batch-start>Iniciar seleção</button> <button class="btn btn-default" type="button" data-batch-stop disabled>Parar após este item</button></div>
<noscript>O processamento sequencial requer JavaScript. As operações individuais continuam disponíveis na guia Transações.</noscript>
<ul data-batch-log role="log" aria-live="polite"></ul>
</form>
