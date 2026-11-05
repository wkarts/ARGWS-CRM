<?php defined('BASEPATH') or exit('No direct script access allowed');
$escapeRemote=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$remoteAuditLabel=static function($value){
    if(!preg_match('/^(?:remote|asaas_webhook)_(inspect|create|update|delete|resume)_(started|confirmed|unconfirmed)$/D',(string)$value,$parts))return 'Operação registrada';
    return ['inspect'=>'Consulta','create'=>'Criação','update'=>'Atualização','delete'=>'Exclusão','resume'=>'Retomada'][$parts[1]].' — '.['started'=>'iniciada','confirmed'=>'confirmada','unconfirmed'=>'não confirmada'][$parts[2]];
};
?>
<?php if($remote_error??null){ ?><p class="alert alert-warning" role="alert"><?= $escapeRemote($remote_error); ?></p><?php } ?>
<?php if($remote_result??null){ ?><section aria-label="Resultado do webhook remoto"><h3>Webhook no Asaas</h3>
<?php $remoteRow=$remote_result['webhook'];if($remoteRow===null){ ?><p role="status"><?= $remote_result['operation']==='delete'?'Exclusão confirmada pelo Asaas.':'Nenhum webhook encontrado para a URL deste recebedor nesta conta.'; ?></p><?php }else{ ?>
<p role="status">Consulta ou operação confirmada pelo Asaas. Isso não comprova uma entrega de evento ao CRM.</p>
<dl><?php foreach(['id'=>'Identificador','name'=>'Nome','url'=>'URL','email'=>'E-mail de alertas','sendType'=>'Tipo de envio'] as $remoteKey=>$remoteLabel){ ?><dt><?= $remoteLabel; ?></dt><dd style="overflow-wrap:anywhere"><?= $escapeRemote($remoteRow[$remoteKey]??''); ?></dd><?php } ?>
<dt>Habilitado</dt><dd><?= ($remoteRow['enabled']??false)?'Sim':'Não'; ?></dd><dt>Fila interrompida</dt><dd><?= ($remoteRow['interrupted']??false)?'Sim':'Não'; ?></dd><dt>Eventos</dt><dd><?= $escapeRemote(implode(', ',$remoteRow['events']??[])); ?></dd></dl>
<?php } ?></section><?php } ?>
<?php if($remote_audit??[]){ ?><h3>Histórico de operações de webhook</h3><div class="table-responsive"><table class="table"><thead><tr><th>Operação</th><th>Usuário</th><th>Data</th></tr></thead><tbody><?php foreach($remote_audit as $remoteLog){ ?><tr><td><?= $escapeRemote($remoteAuditLabel($remoteLog['action']??$remoteLog['event']??'')); ?></td><td><?= (int)$remoteLog['actor_id']; ?></td><td><?= $escapeRemote($remoteLog['created_at']); ?></td></tr><?php } ?></tbody></table></div><?php } ?>
