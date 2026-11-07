<?php
 defined('BASEPATH')or exit('No direct script access allowed');
 $esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
 $base=CompanyRequestScope::adminUrl($this,'invoices/recurring?reprice=1');
 $hidden=static function($action,$key=null,$revision=null)use($esc,$csrf){
     echo '<input type="hidden" name="action" value="'.$esc($action).'"><input type="hidden" name="company_invoice_reprice_csrf" value="'.$esc($csrf).'">';
     if($key!==null)echo '<input type="hidden" name="request_key" value="'.$esc($key).'">';
     if($revision!==null)echo '<input type="hidden" name="revision" value="'.$esc($revision).'">';
 };
 $states=['preview'=>'Simulação aguardando revisão','scheduled'=>'Agendado; execução ainda não confirmada','blocked'=>'Bloqueado; exige nova revisão','applied'=>'Aplicado às bases futuras','cancelled'=>'Cancelado'];
 init_head();
?>
<div id="wrapper"><div class="content" id="invoice-repricing">
<div class="panel_s"><div class="panel-body">
<h1 class="h3">Reajustes de faturas recorrentes</h1>
<p class="reprice-no-print"><a href="<?= $esc(CompanyRequestScope::adminUrl($this,'invoices/recurring')); ?>">Voltar às recorrências</a> · <a href="<?= $esc($base); ?>">Nova simulação</a></p>
<p>Revise os itens das bases futuras, simule e confirme a revisão. O reajuste não altera faturas emitidas, Produtos ou o calendário e não emite cobranças. A quitação da fatura principal não impede a seleção da base futura.</p>
<?php if($error){?><div class="alert alert-danger" role="alert"><?= $esc($error); ?></div><?php } ?>
<?php if($proposal){$report=$proposal['preview']; ?>
<section id="reprice-report"><h2 class="h4">Simulação preservada</h2>
<?php if($proposal['state']==='blocked'){ $reasons=['source_changed'=>'A base ou vínculo mudou desde a simulação.','access_changed'=>'A autorização do responsável mudou.']; ?><p class="alert alert-warning"><?= $esc($reasons[$proposal['block_reason']]??'Revisão necessária.'); ?> Nenhum alvo recebeu reajuste parcial. Cancele esta proposta e crie uma nova simulação após revisar as recorrências.</p><?php } ?>
<p><?= $esc($states[$proposal['state']]); ?> · Criada em <?= $esc($proposal['created_at']); ?> UTC</p>
<p>Revisão: <code><?= $esc($proposal['revision']); ?></code></p>
<p><?= $proposal['execute_at']!==null?'Agendamento: '.$esc($report['execute_local'].' '.$report['timezone'].' ('.$proposal['execute_at'].' UTC)'):'Aplicação imediata após confirmação'; ?>.</p>
<p>A aplicação usa a próxima ocorrência ainda não emitida de cada série. Se uma base, vínculo ou item de catálogo mudar após a simulação, a proposta inteira exige nova revisão. Emissões normais podem avançar o calendário antes do horário agendado.</p>
<table class="table"><thead><tr><th>Item revisado</th><th>Preço no catálogo na simulação</th><th>Novo preço da regra</th></tr></thead><tbody>
<?php foreach($report['rules']as$rule){?><tr><td><?= $esc($rule['catalog']['description']); ?> (#<?= $esc($rule['item_id']); ?>)</td><td><?= $esc($rule['catalog']['rate']); ?></td><td><?= $esc($rule['rate']); ?></td></tr><?php } ?></tbody></table>
<?php foreach($report['targets']as$target){?><h3 class="h4"><?= $esc($target['customer']); ?> · Recorrência #<?= (int)$target['schedule_id']; ?> · <?= $esc($target['currency']); ?></h3>
<table class="table"><thead><tr><th>Alteração</th><th>Linha</th><th>Quantidade anterior → futura</th><th>Preço anterior → futuro</th></tr></thead><tbody>
<?php foreach($target['changes']as$change){$n=$change['index'];$old=$target['before']['items'][$n]??null;$new=$target['after']['items'][$n];?><tr><td><?= $change['kind']==='addition'?'Inclusão explícita':'Reajuste'; ?></td><td><?= $esc($new['description']); ?></td><td><?= $esc($old['qty']??'—'); ?> → <?= $esc($new['qty']); ?></td><td><?= $esc($old['rate']??'—'); ?> → <?= $esc($new['rate']); ?></td></tr><?php } ?></tbody></table>
<p>Total da base: <?= $esc($target['before_amounts']['total']); ?> → <?= $esc($target['after_amounts']['total']); ?> <?= $esc($target['currency']); ?> (inclui tributos, descontos e ajustes preservados).</p>
<?php } ?>
<div class="reprice-no-print"><button class="btn btn-default" type="button" id="reprice-print">Imprimir simulação</button>
<?php if($can_manage&&(int)$proposal['actor_id']===$actor&&$proposal['state']==='preview'){echo form_open($base);$hidden('confirm',$proposal['request_key'],$proposal['revision']);?><p><label><input type="checkbox" name="reviewed" value="1" required> Revisei os alvos, itens, quantidades, valores e horário desta simulação.</label></p><button class="btn btn-primary" type="submit"><?= $proposal['execute_at']!==null?'Confirmar agendamento':'Aplicar esta revisão'; ?></button><?= form_close(); ?><?php } ?>
<?php if($can_manage&&in_array($proposal['state'],['preview','scheduled','blocked'],true)){echo form_open($base);$hidden('cancel',$proposal['request_key'],$proposal['revision']);?><p><button class="btn btn-default" type="submit">Cancelar proposta</button></p><?= form_close(); } ?></div></section>
<?php }elseif($can_manage&&!$error){ ?>
<div id="reprice-editor" class="reprice-no-print">
<p>Vínculos antigos exigem revisão explícita. Descrições parecidas nunca vinculam itens automaticamente. Use a busca no catálogo e depois selecione o item em cada regra ou vínculo.</p>
<div class="form-group"><label for="reprice-search">Buscar item no catálogo desta empresa</label><div class="input-group"><input class="form-control" id="reprice-search" maxlength="200"><span class="input-group-btn"><button class="btn btn-default" id="reprice-search-button" type="button">Buscar</button></span></div><p id="reprice-search-status" role="status"></p></div>
<?= form_open($base,['id'=>'reprice-form']);$hidden('preview',bin2hex(random_bytes(32))); ?><input type="hidden" name="proposal_input" id="reprice-input">
<h2 class="h4">Regras de preço</h2><div id="reprice-rules"></div><p><button type="button" id="reprice-add-rule" class="btn btn-default">Adicionar regra de item/preço</button></p>
<div class="row"><div class="col-md-6 form-group"><label for="reprice-item-filter">Filtrar por item revisado</label><select class="form-control reprice-item-select" id="reprice-item-filter"><option value="">Todos os itens</option></select></div><div class="col-md-6 form-group"><label for="reprice-mode-filter">Filtrar por meio de pagamento da base</label><select class="form-control" id="reprice-mode-filter"><option value="">Todos os meios</option><?php $modes=[];foreach($directory as$row)foreach($row['payment_modes']as$mode)$modes[(string)$mode]=true;foreach(array_keys($modes)as$mode){?><option value="<?= $esc($mode); ?>"><?= $esc($mode); ?></option><?php } ?></select></div></div>
<p>Filtros não selecionam linhas. Uma seleção oculta pelo filtro continua selecionada; confira todos os alvos na simulação. Quantidade em branco preserva a quantidade daquela linha.</p>
<div class="table-responsive"><table class="table" id="reprice-grid"><thead><tr><th>Selecionar</th><th>Cliente / recorrência / linha</th><th>Vínculo revisado</th><th>Preço atual</th><th>Quantidade atual / nova</th></tr></thead><tbody>
<?php foreach($directory as$row){$busy=!in_array($row['state'],['active','paused'],true);?><tr class="reprice-series" data-schedule="<?= (int)$row['id']; ?>"><td></td><th colspan="4"><?= $esc($row['customer']); ?> · #<?= (int)$row['id']; ?> · <?= $esc($row['currency']); ?> · Próxima <?= $esc($row['next_date']); ?> <?= $busy?'· Ocorrência em processamento; aguarde para reajustar':''; ?></th></tr>
<?php foreach($row['payload']['items']as$index=>$line){$link=$row['links'][$index]??null;?><tr data-schedule="<?= (int)$row['id']; ?>" data-line="<?= (int)$index; ?>"><td><input type="checkbox" class="reprice-selected" aria-label="Selecionar linha <?= (int)$index+1; ?> da recorrência <?= (int)$row['id']; ?>" <?= $busy||!$link?'disabled':''; ?>></td><td><?= $esc($line['description']); ?></td><td><span><?= $link?'Item #'.$esc($link['item_id']):'Sem vínculo revisado'; ?></span><select class="form-control reprice-item-select reprice-link" aria-label="Item revisado para esta linha"><option value="">Selecione para revisar</option></select><button type="button" class="btn btn-default reprice-review" <?= $busy?'disabled':''; ?>>Revisar vínculo</button></td><td><?= $esc($line['rate']); ?></td><td><?= $esc($line['qty']); ?><input class="form-control reprice-qty" inputmode="decimal" placeholder="Preservar" aria-label="Nova quantidade somente desta linha" <?= $busy?'disabled':''; ?>></td></tr><?php } ?>
<tr data-schedule="<?= (int)$row['id']; ?>"><td colspan="5"><div class="reprice-additions"></div><button type="button" class="btn btn-default reprice-plus" <?= $busy?'disabled':''; ?>>+ Incluir item somente na recorrência #<?= (int)$row['id']; ?></button></td></tr><?php } ?></tbody></table></div>
<div class="row"><div class="col-md-6 form-group"><label for="reprice-timezone">Fuso da empresa para este reajuste</label><select class="form-control" id="reprice-timezone" required><option value="">Selecione explicitamente</option><?php foreach(DateTimeZone::listIdentifiers()as$zone){?><option value="<?= $esc($zone); ?>"><?= $esc($zone); ?></option><?php } ?></select></div><div class="col-md-6 form-group"><label for="reprice-time">Agendar aplicação (opcional)</label><input class="form-control" id="reprice-time" type="datetime-local"><p>Deixe vazio para aplicar após a confirmação. Horários ambíguos ou inexistentes são recusados.</p></div></div>
<p id="reprice-form-error" class="text-danger" role="alert"></p><button type="submit" class="btn btn-primary">Simular alvos selecionados</button><?= form_close(); ?>
<?= form_open($base,['id'=>'reprice-link-form']);$hidden('review_link');foreach(['schedule_id','line_index','item_id','payload_hash','link_revision']as$field){?><input type="hidden" name="<?= $esc($field); ?>"><?php }echo form_close(); ?>
</div><?php } ?>
<section class="reprice-no-print"><h2 class="h4">Histórico de propostas</h2><table class="table"><thead><tr><th>Criada (UTC)</th><th>Estado</th><th>Execução prevista (UTC)</th><th>Revisão preservada</th></tr></thead><tbody><?php foreach($history as$row){?><tr><td><?= $esc($row['created_at']); ?></td><td><?= $esc($states[$row['state']]??$row['state']); ?></td><td><?= $esc($row['execute_at']??'Imediata'); ?></td><td><a href="<?= $esc($base.'&proposal='.rawurlencode($row['request_key'])); ?>">Consultar simulação</a></td></tr><?php } ?></tbody></table><p>Agendado não significa aplicado. A execução depende do worker de recorrências e revalida acesso e revisão. O histórico exibe as 100 propostas mais recentes.</p></section>
</div></div></div></div>
<script type="application/json" id="reprice-data"><?= json_encode(['directory'=>$directory,'url'=>$base],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR); ?></script>
<?php init_tail(); ?><script src="<?= $esc(base_url('assets/js/native-invoice-repricing.js')); ?>"></script>
<style>@media print{#side-menu,#setup-menu,.navbar,#header,.reprice-no-print{display:none!important}#wrapper{margin:0!important}#reprice-report{display:block!important}table{page-break-inside:auto}tr{page-break-inside:avoid}}</style>
</body></html>
