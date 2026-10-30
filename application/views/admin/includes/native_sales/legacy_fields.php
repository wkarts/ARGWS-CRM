<?php defined('BASEPATH') or exit('No direct script access allowed');
if(!$detail||($detail['origin']??'owned')!=='legacy')return;
?>
<?php if($kind==='proposals'){ require_once APPPATH.'services/CompanySettingsCatalog.php'; ?><h3><?= $esc($detail['subject']); ?></h3><div class="well"><?= CompanySettingsCatalog::htmlFragment($detail['legacy_content']); ?></div><?php } ?>
<?php if($detail['history']){ ?><h3>Histórico do documento</h3><ol><?php foreach($detail['history']as$event){ ?><li><time><?= $esc($event['date']); ?></time> — <?= $esc($event['full_name']); ?>: <?= $esc($event['description']); ?> <?= $esc($event['additional_data']); ?></li><?php } ?></ol><?php } ?>
<?php if($detail['edit_history']??[]){ ?><h3>Revisões das informações</h3><ol><?php foreach($detail['edit_history']as$event){$change=json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR); ?><li><?= $esc($event['created_at']); ?> — Funcionário #<?= (int)$event['actor_id']; ?><details><summary>Informações anteriores e novas</summary><pre><?= $esc(json_encode(['antes'=>$change['before']??[],'depois'=>$change['after']??[]],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)); ?></pre></details></li><?php } ?></ol><?php } ?>
<?php if($detail['payments']){ ?><h3>Pagamentos da fatura vinculada</h3><div class="table-responsive"><table class="table"><thead><tr><th>ID</th><th>Data</th><th>Valor</th></tr></thead><tbody><?php foreach($detail['payments']as$payment){ ?><tr><td><?= (int)$payment['id']; ?></td><td><?= $esc($payment['date']); ?></td><td><?= $esc($payment['amount']); ?></td></tr><?php } ?></tbody></table></div><?php } ?>
<?php if($can_edit&&$detail['editable']){ ?>
<h3>Editar informações do documento</h3>
<?= form_open($url(),['id'=>'historical-sales-edit']);$hidden('legacy_update'); ?>
<input type="hidden" name="document_id" value="<?= $esc($detail['id']); ?>">
<input type="hidden" name="revision" value="<?= $esc($detail['revision_token']); ?>">
<?php $fields=$kind==='estimates'?['clientnote'=>['Observação',$detail['payload']['note']],'terms'=>['Condições',$detail['terms']],'reference_no'=>['Referência',$detail['reference_no']]]:['subject'=>['Assunto',$detail['subject']],'content'=>['Conteúdo',$detail['legacy_content']]];foreach($fields as$field=>[$label,$value]){ ?>
<div class="form-group"><label for="legacy-sales-<?= $esc($field); ?>"><?= $esc($label); ?></label><textarea id="legacy-sales-<?= $esc($field); ?>" class="form-control" name="legacy[<?= $esc($field); ?>]" maxlength="<?= match($field){'subject'=>191,'reference_no'=>100,default=>65535}; ?>" rows="4"><?= $esc($value); ?></textarea></div>
<?php } ?><button class="btn btn-primary" type="submit">Salvar informações</button><?= form_close(); ?>
<?php } ?>
