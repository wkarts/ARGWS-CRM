<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="automatic-pix">
<h3>Pix Automático · autorizações da conta</h3>
<p>Consulta pontual do estado no Asaas. Uma autorização ativa no provedor não habilita cobranças automáticas nem comprova consentimento local para esta empresa.</p>
<?php if(!($can_view_asaas_reports??false)){ ?><p>É necessária a permissão de visualizar relatórios Asaas nesta empresa.</p><?php }else{foreach($accounts as $pixAccount){if(!(int)$pixAccount['enabled']||$pixAccount['verified_at']===null)continue;$pixAccountId=(int)$pixAccount['id']; ?>
<article class="panel panel-default" data-pix-account="<?= $pixAccountId; ?>"><div class="panel-body">
<h4><?= $e($pixAccount['account_id'].' · '.$environmentLabel($pixAccount['environment'])); ?></h4>
<form method="post" action="<?= $e($action_url); ?>#automatic-pix">
<?php $hidden(['action'=>'automatic_pix_monitor','account_row'=>$pixAccountId,'account_revision'=>$pixAccount['revision'],'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<label for="pix-state-<?= $pixAccountId; ?>">Situação no Asaas</label>
<select class="form-control" id="pix-state-<?= $pixAccountId; ?>" name="pix_filters[status]">
<?php foreach(['all'=>'Todas','CREATED'=>'Criada','ACTIVE'=>'Ativa','CANCELLED'=>'Cancelada','REFUSED'=>'Recusada','EXPIRED'=>'Expirada'] as $value=>$label){ ?><option value="<?= $e($value); ?>"><?= $e($label); ?></option><?php } ?>
</select><button class="btn btn-default tw-mt-2" type="submit">Consultar autorizações desta conta</button></form>
<?php if(($automatic_pix_monitor['account_row']??null)===$pixAccountId){$pixPage=$automatic_pix_monitor['page']; ?>
<div class="table-responsive"><table class="table"><thead><tr><th>Autorização</th><th>Situação consultada</th><th>Periodicidade</th><th>Consulta</th></tr></thead><tbody>
<?php foreach($pixPage['items'] as $pixItem){ ?><tr><td><?= $e($pixItem['remote_id']); ?></td><td><?= $e($pixItem['status']); ?></td><td><?= $e($pixItem['frequency']); ?></td><td><form method="post" action="<?= $e($action_url); ?>#automatic-pix"><?php $hidden(['action'=>'automatic_pix_monitor','account_row'=>$pixAccountId,'account_revision'=>$automatic_pix_monitor['account_revision'],'company_asaas_preferences_csrf'=>$preferences_csrf,'pix_filters[id]'=>$pixItem['remote_id']]); ?><button class="btn btn-default" type="submit">Consultar estado atual</button></form></td></tr><?php }if(!$pixPage['items']){ ?><tr><td colspan="4">Nenhuma autorização nesta página.</td></tr><?php } ?>
</tbody></table></div>
<?php if($pixPage['next_offset']!==null){ ?><form method="post" action="<?= $e($action_url); ?>#automatic-pix"><?php $hidden(['action'=>'automatic_pix_monitor','account_row'=>$pixAccountId,'account_revision'=>$automatic_pix_monitor['account_revision'],'company_asaas_preferences_csrf'=>$preferences_csrf]);foreach(array_replace($automatic_pix_monitor['filters'],['offset'=>$pixPage['next_offset']]) as $key=>$value){ ?><input type="hidden" name="pix_filters[<?= $e($key); ?>]" value="<?= $e($value); ?>"><?php } ?><button class="btn btn-default" type="submit">Consultar próxima página</button></form><?php } ?>
<?php } ?></div></article><?php }} ?>
</section>
