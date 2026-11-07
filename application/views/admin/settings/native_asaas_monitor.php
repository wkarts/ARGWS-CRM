<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="monitor"><h2>Monitoramento local</h2>
<p>Consulte o histórico registrado nesta empresa. As listas não consultam o Asaas nem comprovam quitação atual. Um evento processado ou uma cobrança criada não significa pagamento recebido.</p>
<?php if(($monitor_error??null)!==null){ ?><p role="alert"><?= $e($monitor_error); ?></p><?php } ?>
<form method="get" action="<?= $e($action_url); ?>#monitor">
<div class="row">
<div class="col-md-4 form-group"><label for="asaas-monitor-kind">Histórico</label><select id="asaas-monitor-kind" class="form-control" name="monitor_kind"><?php foreach(['origins'=>'Cobranças vinculadas','events'=>'Webhooks recebidos','jobs'=>'Conciliações em segundo plano'] as $value=>$label){ ?><option value="<?= $e($value); ?>" <?= ($monitor['kind']??'origins')===$value?'selected':''; ?>><?= $e($label); ?></option><?php } ?></select></div>
<div class="col-md-4 form-group"><label for="asaas-monitor-account">Conta e ambiente</label><select id="asaas-monitor-account" class="form-control" name="monitor_account"><option value="">Todas as contas desta empresa</option><?php foreach($accounts as $account){ ?><option value="<?= (int)$account['id']; ?>" <?= ($monitor['account']??null)===(int)$account['id']?'selected':''; ?>><?= $e($account['account_id'].' · '.$environmentLabel($account['environment'])); ?></option><?php } ?></select></div>
<div class="col-md-4 form-group"><label for="asaas-monitor-state">Estado local (opcional)</label><input class="form-control" id="asaas-monitor-state" name="monitor_state" maxlength="32" pattern="[A-Za-z0-9_]*" value="<?= $e($monitor['state']??''); ?>"></div>
</div><button class="btn btn-default" type="submit">Consultar histórico local</button></form>
<?php if(($monitor??null)!==null){ ?>
<p>Até 50 registros por página, do mais recente para o mais antigo.</p>
<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Registro</th><th>Referência</th><th>Estado local</th><th>Tipo / diagnóstico</th><th>Conta</th><th>Ambiente</th><th>Data UTC</th></tr></thead><tbody>
<?php foreach($monitor['rows'] as $row){ ?><tr><td><?= (int)$row['id']; ?></td><td><?= $e($row['remote_id']??('Origem #'.($row['origin_id']??''))); ?><?php if(isset($row['invoice_id'])){ ?> · Fatura #<?= (int)$row['invoice_id']; ?><?php }if(isset($row['event_id'])){ ?> · <?= $e($row['event_id']); ?><?php } ?></td><td><?= $e($row['state']); ?></td><td><?= $e($row['billing_type']??$row['event_type']??$row['last_code']??''); ?></td><td><?= (int)$row['account_row']; ?></td><td><?= $e($environmentLabel($row['environment'])); ?></td><td><?= $e($row['created_at']); ?></td></tr><?php } ?>
<?php if(!$monitor['rows']){ ?><tr><td colspan="7">Nenhum registro para estes filtros.</td></tr><?php } ?>
</tbody></table></div>
<?php if($monitor['next_before']!==null){$next=http_build_query(['monitor_kind'=>$monitor['kind'],'monitor_account'=>$monitor['account']??'','monitor_state'=>$monitor['state'],'monitor_before'=>$monitor['next_before']],'','&',PHP_QUERY_RFC3986); ?><a class="btn btn-default" href="<?= $e($action_url.'?'.$next.'#monitor'); ?>">Próxima página</a><?php } ?>
<?php }else{ ?><p>Escolha os filtros e consulte para carregar os registros.</p><?php } ?>
<?php require APPPATH.'views/admin/settings/native_asaas_remote_monitor.php'; ?>
<?php if(($can_configure??false)&&($can_issue??false))require APPPATH.'views/admin/settings/native_asaas_legacy_binding.php'; ?>
<?php if(isset($origins,$can_settle)&&CompanySettingsRuntime::canResource('settings','view'))require APPPATH.'views/admin/settings/native_asaas_batch.php'; ?>
</section>

<?php require APPPATH.'views/admin/settings/native_asaas_installments.php'; ?>
