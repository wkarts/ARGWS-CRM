<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
<h1 class="tw-text-xl tw-font-semibold">Alteração aplicada</h1>
<p><?= count($result['ids']); ?> registro(s) processado(s) em <?= e($result['applied_at']); ?>.</p>
<p>As faturas emitidas foram preservadas. Reenviar esta confirmação retorna o mesmo resultado, sem repetir a alteração ou sua auditoria.</p>
<?php foreach ($result['rule_ids'] as $id) { ?><p><a href="<?= admin_url('recurring_rules/view/' . (int) $id); ?>">Abrir recorrência #<?= (int) $id; ?></a></p><?php } ?>
<a class="btn btn-primary" href="<?= admin_url('recurring_rules'); ?>">Voltar às recorrências</a>
</div></div></div></div><?php init_tail(); ?></body></html>
