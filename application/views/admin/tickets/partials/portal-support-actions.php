<?php defined('BASEPATH') or exit('No direct script access allowed');
$supportHidden=function(string $action)use($native_ticket_page,$company_tickets_csrf,$portal_revision):void{
    foreach(['_company_context'=>$native_ticket_page['context_token'],'_native_company_csrf'=>$native_ticket_page['csrf_token'],'company_tickets_csrf'=>$company_tickets_csrf,'action'=>$action,'revision'=>$portal_revision,'request_key'=>bin2hex(random_bytes(32))] as $key=>$value)echo form_hidden($key,$value);
};$supportUrl=admin_url('tickets/ticket/'.$ticket->ticketid); ?>
<div class="panel_s"><div class="panel-body">
<h4>Atendimento do chamado aberto pelo contato</h4>
<?php if($portal_support_staff['row']['status']==='open'){ ?>
<?= form_open($supportUrl);$supportHidden('portal_support_reply'); ?>
<label for="ticket-reply">Mensagem interna</label><textarea id="ticket-reply" class="form-control" name="reply" required maxlength="12000"></textarea>
<button class="btn btn-primary" type="submit">Registrar mensagem</button><?= form_close(); ?>
<?php } ?>
<?= form_open($supportUrl);$supportHidden('portal_support_state'); ?>
<label for="ticket-assignee">Responsável</label><select id="ticket-assignee" class="form-control" name="assignee_id" required>
<option value="">Selecione</option><?php foreach($portal_support_staff['staff'] as $staff){ ?><option value="<?= (int)$staff['staffid']; ?>"<?= (int)$staff['staffid']===(int)$portal_support_staff['row']['assignee_id']?' selected':''; ?>><?= e(trim($staff['firstname'].' '.$staff['lastname'])); ?></option><?php } ?></select>
<label for="ticket-state">Estado</label><select id="ticket-state" class="form-control" name="status"><?php foreach(['open'=>'Aberto','resolved'=>'Resolvido','closed'=>'Fechado'] as $value=>$label){ ?><option value="<?= $value; ?>"<?= $portal_support_staff['row']['status']===$value?' selected':''; ?>><?= $label; ?></option><?php } ?></select>
<?= form_hidden('priority',$portal_support_staff['row']['priority']); ?>
<button class="btn btn-default" type="submit">Salvar estado e responsável</button><?= form_close(); ?>
</div></div>
