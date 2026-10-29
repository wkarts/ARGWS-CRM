<?php defined('BASEPATH') or exit('No direct script access allowed');
$labels=$kind==='estimates'?[1=>'Aberto',2=>'Enviado',3=>'Recusado',4=>'Aceito',5=>'Expirado']:[1=>'Aberta',2=>'Recusada',3=>'Aceita',4=>'Enviada',5=>'Revisada',6=>'Rascunho'];
foreach($columns as$column){$status=$column['status']; ?>
<ul class="kan-ban-col" data-col-status-id="<?= e($status); ?>" data-total-pages="<?= e($column['pages']); ?>" data-total="<?= e($column['count']); ?>"><li class="kan-ban-col-wrapper"><div class="panel_s no-mbot"><div class="panel-heading"><?= e($labels[$status]); ?> — <?= e($column['total']); ?></div><div class="kan-ban-content-wrapper"><div class="kan-ban-content"><ul data-status-id="<?= e($status); ?>"><?= $column['html']; ?>
<?php if($column['count']){ ?><li class="text-center not-sortable kanban-load-more" data-load-status="<?= e($status); ?>"><a href="#" class="btn btn-default btn-block<?= $column['pages']<=1?' disabled':''; ?>" data-page="1" onclick="kanban_load_more(<?= e($status); ?>,this,'<?= e($kind); ?>/pipeline_load_more',310,360);return false;"><?= e(_l('load_more')); ?></a></li><?php }else{ ?><li class="text-center not-sortable kanban-empty"><p><?= e(_l('no_'.$kind.'_found')); ?></p></li><?php } ?>
</ul></div></div></div></li></ul>
<?php } ?>
