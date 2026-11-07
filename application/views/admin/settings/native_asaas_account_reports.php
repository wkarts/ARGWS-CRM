<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<section id="account-reports"><h2>Relatórios da conta Asaas</h2>
<p>Os relatórios abrangem a conta inteira selecionada, inclusive movimentações feitas fora do CRM. Não representam uma consolidação contábil da empresa. A consulta não registra baixas, estornos ou cobranças.</p>
<?php if(!($can_view_account_reports??false)){ ?><p>Solicite a permissão específica de consultar relatórios da conta inteira. A permissão de consultar cobranças não concede este acesso.</p><?php }else{
require_once APP_MODULES_PATH.'asaas/libraries/Asaas/AsaasReportCatalog.php';
foreach(\Asaas\AsaasReportCatalog::publicDefinitions() as $definition){$kind=$definition['id']; ?>
<details class="panel panel-default" <?= ($account_report['kind']??null)===$kind?'open':''; ?>><summary class="panel-heading"><?= $e($definition['title']); ?></summary><div class="panel-body"><p><?= $e($definition['description']); ?></p>
<?php foreach($accounts as $reportAccount){if(!(int)$reportAccount['enabled']||$reportAccount['verified_at']===null)continue;$rid=(int)$reportAccount['id'];$formId='report-'.$kind.'-'.$rid; ?>
<form method="post" action="<?= $e($action_url); ?>#account-reports"><h4><?= $e($reportAccount['account_id'].' · '.$environmentLabel($reportAccount['environment'])); ?></h4>
<?php $hidden(['action'=>'account_report','account_row'=>$rid,'account_revision'=>$reportAccount['revision'],'report_kind'=>$kind,'report_filters[offset]'=>0,'company_asaas_preferences_csrf'=>$preferences_csrf]); ?>
<div class="row">
<?php foreach($definition['filters'] as $field){$fieldId=$formId.'-'.$field['key']; ?><div class="col-md-6 form-group"><label for="<?= $e($fieldId); ?>"><?= $e($field['label']); ?></label>
<?php if($field['type']==='select'){ ?><select class="form-control" id="<?= $e($fieldId); ?>" name="report_filters[<?= $e($field['key']); ?>]"><option value="">Todas as opções</option><?php foreach($field['options'] as $value=>$label){ ?><option value="<?= $e($value); ?>"><?= $e($label); ?></option><?php } ?></select><?php }else{ ?><input class="form-control" id="<?= $e($fieldId); ?>" name="report_filters[<?= $e($field['key']); ?>]" maxlength="200" <?= ($field['required']??false)?'required':''; ?>><?php } ?></div><?php } ?>
<?php if($definition['dates']){ ?><div class="col-md-4 form-group"><label for="<?= $e($formId); ?>-date">Data de</label><select class="form-control" id="<?= $e($formId); ?>-date" name="report_filters[date_field]"><?php foreach($definition['dates'] as $value=>$label){ ?><option value="<?= $e($value); ?>"><?= $e($label); ?></option><?php } ?></select></div><?php foreach(['date_from'=>'Desde','date_to'=>'Até'] as $key=>$label){ ?><div class="col-md-4 form-group"><label for="<?= $e($formId.'-'.$key); ?>"><?= $e($label); ?></label><input class="form-control" type="date" id="<?= $e($formId.'-'.$key); ?>" name="report_filters[<?= $e($key); ?>]"></div><?php }} ?>
</div><button class="btn btn-default" type="submit">Consultar esta conta</button></form>
<?php } ?>
<?php if(($account_report['kind']??null)===$kind){$reportPage=$account_report['page']; ?>
<p><?= $e('Conta #'.$account_report['account_row'].' · '.$reportPage['environment_label'].' · Consulta '.$reportPage['started_at']); ?></p>
<div class="table-responsive"><table class="table"><thead><tr><?php foreach($reportPage['columns'] as $column){if(!$column['visible'])continue; ?><th><?= $e($column['label']); ?></th><?php } ?></tr></thead><tbody>
<?php foreach($reportPage['rows'] as $reportRow){ ?><tr><?php foreach($reportPage['columns'] as $column){if(!$column['visible'])continue;$value=$reportRow[$column['key']]; ?><td><?= $e(is_bool($value)?($value?'Sim':'Não'):($value??'—')); ?></td><?php } ?></tr><?php } ?></tbody></table></div>
<?php foreach(['account_report'=>'Próxima página','export_account_report'=>'Exportar todas as páginas em CSV'] as $reportAction=>$label){if($reportAction==='account_report'&&$reportPage['next_offset']===null)continue;if($reportAction==='export_account_report'&&!($can_export_account_reports??false))continue; ?>
<form method="post" action="<?= $e($action_url); ?>#account-reports"><?php $hidden(['action'=>$reportAction,'account_row'=>$account_report['account_row'],'account_revision'=>$account_report['account_revision'],'report_kind'=>$kind,'company_asaas_preferences_csrf'=>$preferences_csrf]);foreach(array_replace($account_report['filters'],['offset'=>$reportAction==='account_report'?$reportPage['next_offset']:0]) as $key=>$value){ ?><input type="hidden" name="report_filters[<?= $e($key); ?>]" value="<?= $e($value); ?>"><?php } ?><button class="btn btn-default" type="submit"><?= $e($label); ?></button></form><?php } ?>
<p class="text-muted">Exportação limitada a 10 mil linhas e 45 segundos. A API não oferece snapshot entre páginas; os valores correspondem ao período da consulta. Erros descartam a exportação inteira.</p>
<?php } ?></div></details><?php }} ?>
</section>
