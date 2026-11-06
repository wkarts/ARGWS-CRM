<?php defined('BASEPATH') or exit('No direct script access allowed'); init_head();
$issuerNames=[]; foreach($issuers as $issuer){$issuerNames[(string)$issuer['id']]=$issuer['legal_name'];}
$publicConnections=[]; foreach($connections as $connection){$ext=$external[$connection['connection_key']]??null;$publicConnections[]=['connection_key'=>$connection['connection_key'],'scope'=>$connection['scope'],'revision'=>$connection['revision'],'enabled'=>$connection['enabled'],'mapping'=>$connection['mapping'],'choices'=>$choices[$connection['connection_key']]??null,'external'=>$ext===null?null:['revision'=>(int)$ext['revision'],'connection_revision'=>(int)$ext['connection_revision'],'mapping'=>$ext['mapping']]];}
?>
<div id="wrapper"><div class="content"><div class="row"><div class="col-md-12">
<div class="tw-mb-2"><button type="button" class="btn btn-primary" id="pige-new"><i class="fa fa-plus" aria-hidden="true"></i> Nova integração PIGE</button></div>
<div class="panel_s"><div class="panel-body">
<h4><?=html_escape($title);?></h4>
<p>Gerencie várias integrações por empresa. Salvar não ativa gates, cria faturas ou envia cobranças. O cliente/pagador é validado por solicitação.</p>
<p class="text-muted">Configuração administrativa primária. A empresa proprietária de cada conexão é explícita; a seleção em outra tela não muda essa propriedade.</p>
<div class="table-responsive"><table class="table table-striped" id="pige-connections"><thead><tr><th>Conexão</th><th>Empresa</th><th>Escola / conta</th><th>Ambiente</th><th>Estado</th><th>Ações</th></tr></thead><tbody>
<?php foreach($connections as $index=>$connection){?><tr>
<td><?=html_escape($connection['connection_key']);?><br><small>Revisão <?=(int)$connection['revision'];?></small></td>
<td><?=html_escape($issuerNames[$connection['scope']['issuer_id']]??$connection['scope']['issuer_id']);?></td>
<td><?=html_escape($connection['scope']['school_id']);?><br><?=html_escape($connection['scope']['account_id']);?></td>
<td><?=html_escape($connection['scope']['environment']);?></td><td><?=$connection['enabled']?'Habilitada':'Desabilitada';?><br><small>Token protegido</small></td>
<td><button type="button" class="btn btn-default btn-sm pige-edit" data-index="<?=(int)$index;?>">Editar</button>
<?php if($connection['enabled']){?>
<?=form_open(admin_url('pige_bridge/settings/disable'),['class'=>'pige-disable tw-mt-2']);?>
<input type="hidden" name="pige_csrf" value="<?=html_escape($pige_csrf);?>"><input type="hidden" name="_company_context" value="<?=html_escape($company_context_token??'');?>">
<input type="hidden" name="connection_key" value="<?=html_escape($connection['connection_key']);?>"><input type="hidden" name="revision" value="<?=(int)$connection['revision'];?>">
<button type="submit" class="btn btn-danger btn-sm">Desativar</button><?=form_close();?>
<?php }?></td></tr><?php }?>
</tbody></table></div>
<?php if(!$connections){?><p class="text-muted">Nenhuma integração cadastrada. Use Nova integração PIGE para começar.</p><?php }?>
<noscript><p class="alert alert-warning">Ative JavaScript para abrir e editar as integrações.</p></noscript>
</div></div></div></div></div></div>
<div class="modal fade" id="pige-editor" tabindex="-1" role="dialog" aria-labelledby="pige-editor-title"><div class="modal-dialog modal-lg" role="document"><div class="modal-content">
<div class="modal-header"><button type="button" class="close" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button><h4 class="modal-title" id="pige-editor-title">Integração PIGE</h4></div>
<div class="modal-body">
<p class="alert alert-info" id="pige-editor-help">Salve a conexão antes de configurar as permissões financeiras. O token nunca é preenchido de volta.</p>
<p class="alert alert-warning" id="pige-choices-warning" role="status" style="display:none">Seletores indisponíveis. Reabra a configuração ou revise a atualização e as permissões. Valores históricos não confirmam autorização atual.</p>
<ul class="nav nav-tabs" role="tablist">
<li role="presentation" class="active"><a href="#pige-tab-connection" aria-controls="pige-tab-connection" role="tab" data-toggle="tab">Conexão</a></li>
<li role="presentation" class="pige-existing-tab"><a href="#pige-tab-mapping" aria-controls="pige-tab-mapping" role="tab" data-toggle="tab">Fatura local</a></li>
<li role="presentation" class="pige-existing-tab"><a href="#pige-tab-external" aria-controls="pige-tab-external" role="tab" data-toggle="tab">Cobrança externa</a></li>
<li role="presentation" class="pige-existing-tab"><a href="#pige-tab-prepare" aria-controls="pige-tab-prepare" role="tab" data-toggle="tab">Preparar PIGE</a></li>
<li role="presentation"><a href="#pige-tab-setup" aria-controls="pige-tab-setup" role="tab" data-toggle="tab">Como conectar</a></li>
</ul><div class="tab-content mtop20">
<div role="tabpanel" class="tab-pane active" id="pige-tab-connection">
<div id="pige-discovery" data-url="<?=html_escape(admin_url('pige_bridge/settings/discover'));?>" class="well well-sm">
<h5>Consultar configuração existente no PIGE</h5>
<p>Selecione a empresa abaixo e informe a URL pública do PIGE e uma credencial administrativa JWT com acesso à instituição e integrations.manage. O HMAC financeiro não serve para esta consulta. A leitura não salva nem habilita a ponte.</p>
<div class="form-group"><label for="pige-discovery-url">URL HTTPS do PIGE</label><input id="pige-discovery-url" class="form-control" type="url" autocomplete="off" placeholder="https://pige.exemplo.com"></div>
<div class="form-group"><label for="pige-discovery-credential">Credencial administrativa temporária</label><input id="pige-discovery-credential" class="form-control" type="password" autocomplete="new-password" maxlength="16386"></div>
<button id="pige-discovery-schools" type="button" class="btn btn-default">Consultar instituições</button>
<div class="form-group mtop10"><label for="pige-discovery-school">Instituição autorizada</label><select id="pige-discovery-school" class="form-control"><option value="">Consulte as instituições</option></select></div>
<button id="pige-discovery-read" type="button" class="btn btn-default">Preencher pela configuração selecionada</button>
<p id="pige-discovery-status" class="mtop10" role="status" aria-live="polite"></p>
<p>Continuam manuais: identificador local, HMAC compartilhado e consentimentos financeiros. Uma ponte ainda não configurada no PIGE precisa ser criada naquela aplicação. Esta consulta exige destino público IPv4 e não segue redirecionamentos.</p>
</div>

<?=form_open(admin_url('pige_bridge/settings/save'), ['id'=>'pige-connection-form']);?>
<input type="hidden" name="revision" value="0">
<input type="hidden" name="pige_csrf" value="<?=html_escape($pige_csrf);?>"><input type="hidden" name="_company_context" value="<?=html_escape($company_context_token??'');?>">
<?php foreach(['connection_key'=>'Identificador da conexão','school_id'=>'UUID da escola no PIGE','account_id'=>'Identificador público da conta vinculada'] as $field=>$label){?><div class="form-group"><label for="pige-<?=$field;?>"><?=html_escape($label);?></label><input class="form-control" id="pige-<?=$field;?>" name="<?=$field;?>" maxlength="128" required></div><?php } ?>
<div class="form-group"><label for="pige-issuer">Empresa cadastrada que será proprietária financeira</label><select class="form-control" id="pige-issuer" name="issuer_id" required><?php foreach($issuers as $issuer){?><option value="<?=(int)$issuer['id'];?>"><?=html_escape($issuer['legal_name'].' (#'.$issuer['id'].')');?></option><?php }?></select></div>
<div class="form-group"><label for="pige-environment">Ambiente</label><select class="form-control" id="pige-environment" name="environment"><option value="sandbox">Sandbox</option><option value="production">Produção — não ativa automaticamente</option></select></div>
<div class="form-group"><label for="pige-secret">Token HMAC (32 a 512 caracteres; vazio preserva o atual)</label><input class="form-control" type="password" id="pige-secret" name="secret" autocomplete="new-password" maxlength="512"></div>
<p>O token nunca é devolvido por esta tela. Guarde-o de forma segura no PIGE. Scope e identificador existentes não podem migrar para outra empresa/conta; crie outra conexão. Rotação invalida o consentimento de faturas até revalidação explícita.</p>
<div class="checkbox"><label><input type="checkbox" name="confirm_rotation" value="1"> Confirmo a rotação explícita do token desta conexão existente</label></div>
<div class="checkbox"><label><input type="checkbox" name="enabled" value="1"> Habilitar conexão (demais gates continuam obrigatórios)</label></div>
<button class="btn btn-primary" type="submit">Validar e salvar conexão</button><?=form_close();?></div>
<div role="tabpanel" class="tab-pane " id="pige-tab-mapping"><h5 class="mtop20">Delegação de fatura local v2</h5>
<p>Não é emissão de boleto/Pix. O funcionário precisa de acesso à empresa, faturas e clientes; delegar a outro funcionário exige master ativo. Moeda deve ser BRL. Não existe cliente fixo na conexão.</p>
<?=form_open(admin_url('pige_bridge/settings/mapping'), ['id'=>'pige-mapping-form']);?>
<input type="hidden" name="connection_key"><input type="hidden" name="revision">
<input type="hidden" name="pige_csrf" value="<?=html_escape($pige_csrf);?>"><input type="hidden" name="_company_context" value="<?=html_escape($company_context_token??'');?>">
<?php foreach(['actor_id'=>'Funcionário delegado','currency_id'=>'Moeda'] as $field=>$label){?><div class="form-group"><label for="pige-map-<?=$field;?>"><?=html_escape($label);?></label><select class="form-control" id="pige-map-<?=$field;?>" name="<?=$field;?>" required><option value="">Selecione</option></select></div><?php } ?>
<div class="checkbox"><label><input type="checkbox" name="mapping_enabled" value="1"> Autorizar fatura local após validação e gates da instalação</label></div>
<button class="btn btn-default" type="submit">Salvar mapping sem criar fatura</button><?=form_close();?></div>
<div role="tabpanel" class="tab-pane " id="pige-tab-external"><h5 class="mtop20">Consentimento externo v3 — Asaas ou Banco do Brasil</h5>
<p>Conta nativa existente da mesma empresa/ambiente: wallet Asaas verificada ou conexão BB com perfil API e credencial cadastrados. O identificador público deve coincidir com o scope da conexão. O funcionário deve ser o mesmo da delegação local. Cliente e registro de pagador existente variam por solicitação; não há criação de pagador remoto. Rotação de token ou revisão invalida este consentimento.</p>
<?=form_open(admin_url('pige_bridge/settings/external'), ['id'=>'pige-external-form']);?>
<input type="hidden" name="connection_key"><input type="hidden" name="connection_revision"><input type="hidden" name="external_revision" value="0">
<input type="hidden" name="pige_csrf" value="<?=html_escape($pige_csrf);?>"><input type="hidden" name="_company_context" value="<?=html_escape($company_context_token??'');?>">
<?php foreach(['account_row'=>'Conta do provedor nesta empresa e ambiente','actor_id'=>'Funcionário delegado'] as $field=>$label){?><div class="form-group"><label for="pige-ext-<?=$field;?>"><?=html_escape($label);?></label><select class="form-control" id="pige-ext-<?=$field;?>" name="<?=$field;?>" required><option value="">Selecione</option></select></div><?php }?>
<div class="form-group"><label for="pige-ext-provider">Provedor vinculado</label><select class="form-control" id="pige-ext-provider" name="provider"><option value="asaas">Asaas — PIX ou boleto</option><option value="banco_brasil">Banco do Brasil — boleto API</option></select></div>
<div class="form-group"><label for="pige-ext-profile">Perfil API BB (obrigatório somente para BB)</label><select class="form-control" id="pige-ext-profile" name="profile_row"><option value="">Selecione</option></select></div>
<div class="checkbox"><label><input type="checkbox" name="external_enabled" value="1"> Autorizar comandos externos após gates e validação do serviço</label></div>
<div class="checkbox"><label><input type="checkbox" name="allow_reconcile" value="1"> Autorizar conciliação explícita integral, com prova canônica e ACL; nunca baixa automática por webhook ou consulta</label></div>
<p>As opções respeitam a empresa, conta e ambiente da conexão. A seleção é revalidada ao salvar; não ativa consentimentos automaticamente. Se uma opção histórica estiver indisponível, revise a configuração antes de habilitar.</p>
<p>Parcial, estorno, juros e desconto permanecem em revisão. Nenhum efeito acadêmico é aplicado. BB exige espécie, aceite e endereço explícitos por cobrança; não há Pix BB, juros, desconto, cessão ou recebimento parcial neste contrato.</p>
<button class="btn btn-default" type="submit">Validar consentimento sem efeito financeiro</button><?=form_close();?></div>
<div role="tabpanel" class="tab-pane" id="pige-tab-prepare">
<h5>Preparar uma nova conexão remota desativada</h5>
<p>Desative e salve esta conexão local antes de preparar a outra ponta. O servidor enviará o HMAC já cadastrado à URL informada, usando uma credencial administrativa temporária. Nenhuma conexão existente no PIGE será sobrescrita.</p>
<?=form_open(admin_url('pige_bridge/settings/prepare_remote'),['id'=>'pige-prepare-form','autocomplete'=>'off']);?>
<input type="hidden" name="connection_key"><input type="hidden" name="revision">
<input type="hidden" name="pige_csrf" value="<?=html_escape($pige_csrf);?>"><input type="hidden" name="_company_context" value="<?=html_escape($company_context_token??'');?>">
<div class="form-group"><label for="pige-prepare-url">URL HTTPS pública do PIGE</label><input id="pige-prepare-url" type="url" name="pige_url" class="form-control" required autocomplete="off"></div>
<div class="form-group"><label for="pige-prepare-credential">JWT administrativo temporário com integrations.manage</label><input id="pige-prepare-credential" type="password" name="admin_credential" class="form-control" required autocomplete="new-password" maxlength="16386"></div>
<div class="checkbox"><label><input type="checkbox" name="confirm_transfer" value="1" required> Confirmo o destino e o envio do HMAC desta conexão para preparar o PIGE desativado.</label></div>
<p>Preparação não comprova autenticação HMAC. O teste atual do PIGE exige habilitação explícita posterior. Se houver erro ou timeout, consulte a outra ponta antes de tentar novamente. Não há retry automático nem alteração de consentimentos financeiros.</p>
<button type="submit" class="btn btn-default">Preparar sem habilitar</button><?=form_close();?>
</div>
<div role="tabpanel" class="tab-pane" id="pige-tab-setup">
<p>Cadastre no PIGE a URL HTTPS deste CRM, o mesmo identificador, escola, empresa, conta, ambiente e token. A consulta acima pode preencher escola, conta e ambiente de uma configuração existente destinada a este CRM e à empresa selecionada. Não cria a configuração na outra ponta.</p>
<p>O teste autenticado existente é enviado pelo PIGE para o endpoint de diagnóstico do CRM e confirma o escopo e as capacidades disponíveis. Ele não cadastra a outra ponta nem ativa permissões financeiras.</p>
<p>Após salvar ou rotacionar a conexão, reabra Editar e revalide Fatura local; depois revalide Cobrança externa. Cada salvamento atualiza a revisão e fecha esta janela.</p>
</div></div></div><div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button></div>
</div></div></div>
<script type="application/json" id="pige-settings-data"><?=json_encode($publicConnections,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR);?></script>
<?php init_tail(); ?>
<script src="<?=html_escape(module_dir_url('pige_bridge','assets/settings.js'));?>?v=110"></script>
