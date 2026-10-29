<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$labels=['draft'=>'Rascunho','active'=>'Ativo','cancelled'=>'Cancelado','deleted'=>'Removido'];
$indexUrl=admin_url('contracts');
$createUrl=admin_url('contracts/contract?source=company');
$detailUrl=$contract_id===null?admin_url('contracts/contract'):admin_url('contracts/contract/'.$contract_id);
$canonical=static fn($id)=>NativeCompanyResourceId::encode('company_contract',(string)$id);
$hidden=function(string $action,?string $id=null,$revision=null,array $extra=[])use($esc,$native_contracts_csrf):void{
    echo '<input type="hidden" name="action" value="'.$esc($action).'">';
    echo '<input type="hidden" name="native_contracts_csrf" value="'.$esc($native_contracts_csrf).'">';
    echo '<input type="hidden" name="request_key" value="'.bin2hex(random_bytes(32)).'">';
    if($id!==null)echo '<input type="hidden" name="contract_id" value="'.$esc($id).'">';
    if($revision!==null)echo '<input type="hidden" name="revision" value="'.$esc($revision).'">';
    foreach($extra as$name=>$value)echo '<input type="hidden" name="'.$esc($name).'" value="'.$esc($value).'">';
};
?>
<?php init_head(); ?>
<div id="wrapper">
  <div class="content">
    <div class="row"><div class="col-md-12">
      <div class="sm:tw-flex sm:tw-justify-between sm:tw-items-center tw-mb-3">
        <h4 class="tw-my-0 tw-font-bold tw-text-lg tw-text-neutral-700"><?= $esc(_l('contracts')); ?></h4>
        <?php if($can_create){ ?><a class="btn btn-primary" href="<?= $esc($createUrl); ?>"><i class="fa-regular fa-plus tw-mr-1"></i><?= $esc(_l('new_contract')); ?></a><?php } ?>
      </div>

      <?php if($contract){ ?>
      <div class="panel_s"><div class="panel-body">
        <div class="sm:tw-flex sm:tw-justify-between sm:tw-items-center">
          <div><h4 class="tw-mt-0 tw-font-semibold"><?= $esc($contract['name']); ?></h4><p class="text-muted"><?= $esc($labels[$contract['status']]??$contract['status']); ?> · <?= $contract['number']===null?'Sem numeração':'Contrato '.(int)$contract['number']; ?> · <?= $esc($contract['start_date'].' — '.$contract['due_date']); ?></p></div>
          <?php if($contract['number']!==null&&$contract['status']!=='deleted'){ ?><a class="btn btn-default" href="<?= $esc(admin_url('contracts/pdf/'.$contract_id)); ?>"><i class="fa-regular fa-file-pdf tw-mr-1"></i><?= $esc(_l('download')); ?></a><?php } ?>
        </div>
        <div class="well" style="white-space:pre-wrap"><?= $esc($contract['body']); ?></div>
        <?php if($contract['number']!==null){$snapshot=json_decode($contract['snapshot_json'],true,32,JSON_THROW_ON_ERROR); ?><p class="text-muted"><?= $esc(($snapshot['identity']['legal_name']??'').' → '.($snapshot['client']['name']??'')); ?></p><?php } ?>

        <?php if($can_edit&&$contract['status']==='draft'){ ?>
        <hr><h5 class="tw-font-semibold">Editar rascunho</h5>
        <?= form_open($detailUrl); ?><?php $hidden('update',$contract_id,$contract['revision']); ?><input type="hidden" name="contract[client_id]" value="<?= (int)$contract['client_id']; ?>">
        <?php $values=$contract;$formId='contract-edit'; ?>
        <div class="form-group"><label for="<?= $formId; ?>-name">Nome</label><input id="<?= $formId; ?>-name" class="form-control" name="contract[name]" maxlength="191" required value="<?= $esc($values['name']); ?>"></div>
        <div class="form-group"><label for="<?= $formId; ?>-body">Texto do contrato</label><textarea id="<?= $formId; ?>-body" class="form-control" name="contract[body]" maxlength="40000" rows="12" required><?= $esc($values['body']); ?></textarea></div>
        <div class="row"><div class="col-md-6 form-group"><label for="<?= $formId; ?>-start">Início</label><input id="<?= $formId; ?>-start" type="date" class="form-control" name="contract[start_date]" required value="<?= $esc($values['start_date']); ?>"></div><div class="col-md-6 form-group"><label for="<?= $formId; ?>-due">Fim</label><input id="<?= $formId; ?>-due" type="date" class="form-control" name="contract[due_date]" required value="<?= $esc($values['due_date']); ?>"></div></div>
        <button class="btn btn-primary" type="submit">Salvar rascunho</button><?= form_close(); ?>
        <div class="tw-mt-3 tw-flex tw-gap-2">
          <?= form_open($detailUrl); ?><?php $hidden('activate',$contract_id,$contract['revision']); ?><button class="btn btn-success" type="submit">Ativar contrato e preservar texto</button><?= form_close(); ?>
          <?= form_open($detailUrl); ?><?php $hidden('cancel',$contract_id,$contract['revision']); ?><button class="btn btn-default" type="submit">Cancelar e preservar o histórico</button><?= form_close(); ?>
        </div>
        <p class="text-muted tw-mt-2">A ativação registra a numeração e o snapshot. Ela não representa assinatura ou aceite jurídico.</p>
        <?php }elseif($can_edit&&$contract['status']==='active'){ ?><?= form_open($detailUrl); ?><?php $hidden('cancel',$contract_id,$contract['revision']); ?><button class="btn btn-default" type="submit">Cancelar e preservar o histórico</button><?= form_close(); ?><?php } ?>
        <?php if($can_create&&$contract['status']!=='deleted'){ ?><?= form_open(admin_url('contracts/copy/'.$contract_id)); ?><?php $hidden('copy',$contract_id,$contract['revision']); ?><button class="btn btn-default tw-mt-2" type="submit"><i class="fa-regular fa-copy tw-mr-1"></i><?= $esc(_l('contract_copy')); ?></button><?= form_close(); ?><?php } ?>
      </div></div>

      <?php if($can_edit&&$can_create&&$contract['status']==='active'){ ?>
      <div class="panel_s"><div class="panel-body"><h4>Renovar contrato</h4><p>A renovação gera um novo rascunho vinculado a este contrato. Revise e ative o novo documento para numerá-lo.</p>
      <?= form_open(admin_url('contracts/renew')); ?><?php $hidden('renew',$contract_id,$contract['revision']); ?>
      <div class="row"><div class="col-md-6 form-group"><label for="renew-start">Início</label><input type="date" name="new_start_date" id="renew-start" class="form-control" required></div><div class="col-md-6 form-group"><label for="renew-end">Fim</label><input type="date" name="new_end_date" id="renew-end" class="form-control" required></div></div><button class="btn btn-primary" type="submit">Criar renovação</button><?= form_close(); ?></div></div><?php } ?>
      <?php if($can_edit&&($mail_recipients??[])){ ?><div class="panel_s"><div class="panel-body"><h4>Enviar contrato por e-mail</h4><p>O contato recebe um link para o contrato publicado no portal autenticado desta empresa.</p>
      <?= form_open(admin_url('contracts/send_to_email/'.$contract_id)); ?><?php $hidden('send_to_email',$contract_id,$contract['revision']); ?><label for="contract-mail-contact">Contato autorizado</label><select id="contract-mail-contact" class="form-control" name="contact_id" required><?php foreach($mail_recipients as$recipient){ ?><option value="<?= (int)$recipient['id']; ?>"><?= $esc(trim($recipient['firstname'].' '.$recipient['lastname']).' — '.$recipient['email']); ?></option><?php } ?></select><button class="btn btn-primary mtop15" type="submit">Agendar envio</button><?= form_close(); ?></div></div><?php } ?>
      <?php if(($can_delete??false)&&$contract['status']!=='deleted'){ ?><div class="panel_s"><div class="panel-body"><?= form_open(admin_url('contracts/delete/'.$contract_id)); ?><?php $hidden('delete',$contract_id,$contract['revision']); ?><button class="btn btn-danger" type="submit">Remover da listagem</button><p class="text-muted">O documento e seu histórico serão preservados.</p><?= form_close(); ?></div></div><?php } ?>
      <?php if($history??[]){ ?><div class="panel_s"><div class="panel-body"><h4>Histórico</h4><ol><?php foreach($history as$event){$metadata=$event['metadata_json']?json_decode($event['metadata_json'],true,32,JSON_THROW_ON_ERROR):[]; ?><li><?= $esc($event['created_at']); ?> — <?= $esc(['native_contract_renewal_linked'=>'Renovação registrada','native_contract_mail_queued'=>'Envio agendado','company_contract_deleted'=>'Removido da listagem','native_contract_comment_revision'=>'Comentário revisado'][$event['event']]??'Alteração do contrato'); ?><?php if(isset($metadata['successor_id'])){ ?> <a href="<?= $esc(admin_url('contracts/contract/'.$canonical($metadata['successor_id']))); ?>">Abrir renovação</a><?php } ?><?php if(isset($metadata['before']['body'])){ ?><details><summary>Texto anterior</summary><p><?= $esc($metadata['before']['body']); ?></p></details><?php } ?></li><?php } ?></ol></div></div><?php } ?>
      <?php if(is_array($discussion)){ ?>
      <div class="panel_s" id="contract-discussion"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold">Conversa do contrato</h4><p class="text-muted">Mensagens não alteram o documento nem constituem assinatura. Cada mensagem da equipe permanece interna até publicação explícita.</p>
        <?php foreach($discussion['items'] as $comment){ ?><article class="well"><p style="white-space:pre-wrap"><?= $esc($comment['body']); ?></p><small><?= $esc(($comment['author_type']==='contact'?'Contato: ':'Funcionário: ').$comment['author_name']); ?> · <?= $esc($comment['created_at']); ?> · <?= (int)$comment['visible']===1?'Publicada':'Interna'; ?></small>
          <?php if($can_edit){ ?><?= form_open($detailUrl); ?><?php $hidden('discussion_visibility',$contract_id,$discussion['revision'],['comment_id'=>$comment['id']]); ?><label class="checkbox-inline"><input type="checkbox" name="visible" value="1" <?= (int)$comment['visible']===1?'checked':''; ?>> Publicar para contatos autorizados</label><button class="btn btn-default btn-sm" type="submit">Salvar publicação</button><?= form_close(); ?><?php } ?>
          <?php if($can_edit&&$comment['author_type']==='staff'&&((int)$comment['staff_id']===($comment_actor??0)||($comment_admin??false))){ ?><?= form_open(admin_url('contracts/edit_comment/'.$comment['id'])); ?><?php $hidden('edit_comment',$contract_id,$discussion['revision']); ?><label for="contract-comment-<?= (int)$comment['id']; ?>">Editar comentário</label><textarea id="contract-comment-<?= (int)$comment['id']; ?>" class="form-control" name="content" maxlength="8000" required><?= $esc($comment['body']); ?></textarea><button class="btn btn-default btn-sm" type="submit">Salvar comentário</button><?= form_close(); ?><?= form_open(admin_url('contracts/remove_comment/'.$comment['id'])); ?><?php $hidden('remove_comment',$contract_id,$discussion['revision']); ?><button class="btn btn-default btn-sm" type="submit">Remover comentário</button><?= form_close(); ?><?php } ?>
        </article><?php } ?>
        <?php if($can_edit){ ?><?= form_open(admin_url('contracts/add_comment'),['id'=>'contract-discussion-add']); ?><?php $hidden('discussion_add',$contract_id,$discussion['revision']); ?><div class="form-group"><label for="contract-discussion-body">Novo comentário interno</label><textarea id="contract-discussion-body" class="form-control" name="discussion_body" maxlength="8000" rows="5" required></textarea></div><button class="btn btn-primary" type="submit">Registrar comentário interno</button><?= form_close(); ?><?php } ?>
      </div></div>
      <?php } ?>

      <div class="panel_s"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold"><?= $esc(_l('contract_attachments')); ?></h4><p class="text-muted">PDF até 2 MiB e PNG/JPEG até 512 KiB. Imagens são normalizadas antes do armazenamento.</p><ul class="list-unstyled">
        <?php foreach($operation_files as$file){ ?><li class="tw-mb-2"><a href="<?= $esc(admin_url('contracts/file/'.$contract_id.'/'.$file['file_key'])); ?>"><?= $esc($file['filename']); ?></a> · <?= (int)$file['byte_size']; ?> bytes
          <?php if($file['mime']==='image/png'){ ?><a class="tw-ml-2" href="<?= $esc(admin_url('contracts/file/'.$contract_id.'/'.$file['file_key'].'?preview=1')); ?>">Visualizar</a><?php } ?>
          <?php if($can_edit){ ?><?= form_open($detailUrl,['class'=>'tw-inline tw-ml-2']); ?><?php $hidden('file_hide',$contract_id,$contract['revision'],['file_key'=>$file['file_key']]); ?><button class="btn btn-default btn-xs" type="submit">Ocultar anexo e preservar histórico</button><?= form_close(); ?><?php } ?></li><?php } ?>
      </ul><?php if($can_edit&&in_array($contract['status'],['draft','active'],true)){ ?><?= form_open($detailUrl,['enctype'=>'multipart/form-data']); ?><?php $hidden('file_upload',$contract_id,$contract['revision']); ?><div class="form-group"><label for="operation-file">Anexar arquivo privado</label><input id="operation-file" name="operation_file" type="file" accept="application/pdf,image/png,image/jpeg" required></div><button class="btn btn-default" type="submit">Anexar arquivo privado</button><?= form_close(); ?><?php } ?></div></div>

      <?php if(is_array($portal_controls)){ ?>
      <div class="panel_s" id="operation-portal-publication"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold">Portal do cliente</h4><p class="text-muted">O contato precisa da permissão de contratos e o registro deve ser publicado explicitamente. Arquivos continuam privados até publicação individual.</p>
        <?php if(!$portal_controls['private_client']){ ?><div class="alert alert-warning">O cadastro não pertence a esta empresa; seus contatos não podem receber este documento.</div><?php } ?>
        <?php if($can_edit&&$portal_controls['private_client']&&$portal_controls['eligible']){ ?><?= form_open($detailUrl); ?><?php $hidden('portal_publish',$contract_id,$contract['revision'],['publication_type'=>'record']); ?><label><input type="checkbox" name="visible" value="1" <?= $portal_controls['visible']?'checked':''; ?>> Publicar contrato para contatos autorizados</label><button class="btn btn-default" type="submit">Salvar publicação</button><?= form_close(); ?><?php } ?>
        <?php if($can_edit){foreach($portal_controls['files'] as$file){ ?><?= form_open($detailUrl); ?><?php $hidden('portal_publish',$contract_id,$contract['revision'],['publication_type'=>'file','file_key'=>$file['file_key']]); ?><label><input type="checkbox" name="visible" value="1" <?= (int)$file['visible']===1?'checked':''; ?>> Publicar anexo <?= $esc($file['filename']); ?></label><button class="btn btn-default btn-sm" type="submit">Salvar</button><?= form_close(); ?><?php }} ?>
        <?php foreach($portal_controls['permissions'] as$contact=>$permission){ ?><?= form_open($detailUrl); ?><?php $hidden('portal_permission',$contract_id,$contract['revision'],['contact_id'=>$contact,'contact_revision'=>$permission['revision']]); ?><label><input type="checkbox" name="allowed" value="1" <?= $permission['allowed']?'checked':''; ?> <?= $portal_controls['can_contact_edit']?'':'disabled'; ?>> <?= $esc($permission['name']); ?></label><?php if($portal_controls['can_contact_edit']){ ?><button class="btn btn-default btn-sm" type="submit">Salvar acesso</button><?php } ?><?= form_close(); ?><?php } ?>
      </div></div>
      <?php } ?>
      <?php } ?>

      <div class="panel_s"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold">Lista de contratos</h4><form class="form-inline tw-mb-3" method="get" action="<?= $esc($indexUrl); ?>"><div class="form-group"><label class="sr-only" for="native-contract-search">Buscar</label><input id="native-contract-search" class="form-control" name="search" maxlength="191" placeholder="Buscar pelo nome"></div><button class="btn btn-default" type="submit"><?= $esc(_l('search')); ?></button></form>
        <div class="table-responsive"><table class="table table-striped"><thead><tr><th>Número</th><th>Nome</th><th>Estado</th><th>Vigência</th></tr></thead><tbody><?php foreach($directory['items']as$row){$rowId=$canonical($row['id']); ?><tr><td><?= $row['number']===null?'Rascunho':(int)$row['number']; ?></td><td><a href="<?= $esc(admin_url('contracts/contract/'.$rowId)); ?>"><?= $esc($row['name']); ?></a></td><td><?= $esc($labels[$row['status']]??$row['status']); ?></td><td><?= $esc($row['due_date']); ?></td></tr><?php } ?></tbody></table></div>
        <?php if($directory['next']!==null){ ?><a class="btn btn-default" href="<?= $esc($indexUrl.'?after='.(int)$directory['next']); ?>">Próxima página</a><?php } ?>
      </div></div>

      <?php if($can_create&&!$contract){ ?>
      <div class="panel_s"><div class="panel-body"><h4 class="tw-mt-0 tw-font-semibold"><?= $esc(_l('new_contract')); ?></h4><form class="form-inline tw-mb-3" method="get" action="<?= $esc($createUrl); ?>"><input type="hidden" name="source" value="company"><div class="form-group"><label class="sr-only" for="native-contract-client-search">Cliente</label><input id="native-contract-client-search" class="form-control" name="client_search" maxlength="191" placeholder="Buscar cliente"></div><button class="btn btn-default" type="submit"><?= $esc(_l('search')); ?></button></form>
        <?= form_open($createUrl); ?><?php $hidden('create'); ?><div class="form-group"><label for="contract-client">Cliente autorizado</label><select id="contract-client" class="form-control" name="contract[client_id]" required><option value="">Selecione</option><?php foreach($clients as$client){ ?><option value="<?= (int)$client['userid']; ?>"><?= $esc($client['company']); ?></option><?php } ?></select></div>
        <div class="form-group"><label for="contract-new-name">Nome</label><input id="contract-new-name" class="form-control" name="contract[name]" maxlength="191" required></div><div class="form-group"><label for="contract-new-body">Texto do contrato</label><textarea id="contract-new-body" class="form-control" name="contract[body]" maxlength="40000" rows="12" required></textarea></div><div class="row"><div class="col-md-6 form-group"><label for="contract-new-start">Início</label><input id="contract-new-start" type="date" class="form-control" name="contract[start_date]" value="<?= gmdate('Y-m-d'); ?>" required></div><div class="col-md-6 form-group"><label for="contract-new-due">Fim</label><input id="contract-new-due" type="date" class="form-control" name="contract[due_date]" value="<?= gmdate('Y-m-d'); ?>" required></div></div><button class="btn btn-primary" type="submit">Criar rascunho de contrato</button><?= form_close(); ?>
      </div></div>
      <?php } ?>
    </div></div>
  </div>
</div>
<?php init_tail(); ?>
</body></html>
