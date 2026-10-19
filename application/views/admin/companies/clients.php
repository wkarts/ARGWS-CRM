<?php defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title='Clientes da empresa';require APPPATH.'views/admin/companies/layout_head.php';
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=fn($query='')=>CompanyRequestScope::adminUrl($this,'company_clients'.$query);
$hidden=function()use($esc,$company_clients_csrf){echo '<input type="hidden" name="company_clients_csrf" value="'.$esc($company_clients_csrf).'">';};
?>
<section class="company-settings-card" aria-labelledby="clients-title">
<h1 id="clients-title">Clientes da empresa</h1>
<p>Clientes novos são privados. Compartilhar permite consultar somente nome e documento fiscal. Contatos, anexos e histórico financeiro não são compartilhados.</p>
<?php if($error){?><p role="alert" class="alert alert-danger"><?= $esc($error); ?></p><a href="<?= $esc($url()); ?>">Reabrir lista e formulário</a><?php } ?>
<?php if($details){$client=$details['client'];$owned=$client['owner_issuer_id']===$issuer;?>
<h2><?= $esc($client['company']); ?></h2>
<p><?= $owned?'Cadastro próprio desta empresa.':'Identidade compartilhada pelo proprietário; consulta somente.'; ?></p>
<?php if($owned&&$capabilities['edit']){?>
<?= form_open($url());$hidden(); ?>
<input type="hidden" name="action" value="update"><input type="hidden" name="client_id" value="<?= $client['userid']; ?>"><input type="hidden" name="revision" value="<?= $esc($details['revision']); ?>">
<div class="form-group"><label for="edit_company">Nome / razão social</label><input class="form-control" id="edit_company" name="client[company]" required maxlength="191" value="<?= $esc($client['company']); ?>"></div>
<div class="form-group"><label for="edit_vat">CPF / CNPJ (opcional)</label><input class="form-control" id="edit_vat" name="client[vat]" maxlength="20" value="<?= $esc($client['vat']); ?>"></div>
<button type="submit" class="btn btn-primary">Salvar cliente</button><?= form_close(); ?>
<?php }else{?><dl><dt>CPF / CNPJ</dt><dd><?= $esc($client['vat']?:'Não informado'); ?></dd></dl><?php } ?>
<?php if($owned){?>
<h3>Contatos privados e portal</h3><p>Somente esta empresa consulta os contatos. O mesmo e-mail em empresas distintas possui acesso e senha independentes.</p>
<p>Forneça ao contato este <a href="<?= $esc($portal_url); ?>">link do portal da empresa</a>. O contato solicita ali o link para definir ou recuperar a senha. Configure antes o SMTP e o modelo de recuperação desta empresa.</p>
<?php foreach($contacts as $contact){?>
<?php if($capabilities['edit']){echo form_open($url());$hidden(); ?>
<input type="hidden" name="action" value="contact"><input type="hidden" name="client_id" value="<?= $client['userid']; ?>"><input type="hidden" name="revision" value="<?= $esc($details['revision']); ?>"><input type="hidden" name="contact_id" value="<?= $contact['id']; ?>"><input type="hidden" name="contact_revision" value="<?= $esc($contact['revision']); ?>">
<?php foreach(['firstname'=>'Nome','lastname'=>'Sobrenome','email'=>'E-mail'] as $field=>$label){?><label for="contact-<?= $contact['id'].'-'.$field; ?>"><?= $esc($label); ?></label><input class="form-control" id="contact-<?= $contact['id'].'-'.$field; ?>" name="contact[<?= $field; ?>]" value="<?= $esc($contact[$field]); ?>" maxlength="<?= $field==='email'?100:191; ?>" <?= $field==='email'?'type="email"':'type="text"'; ?> <?= $field!=='lastname'?'required':''; ?>><?php } ?><button type="submit">Salvar contato</button><?= form_close(); ?>
<?php }else{?><p><?= $esc($contact['firstname'].' '.$contact['lastname'].' · '.$contact['email']); ?></p><?php } ?>
<?php } ?>
<?php if($capabilities['edit']){?>
<h4>Novo contato privado</h4><?= form_open($url());$hidden(); ?>
<input type="hidden" name="action" value="contact"><input type="hidden" name="client_id" value="<?= $client['userid']; ?>"><input type="hidden" name="revision" value="<?= $esc($details['revision']); ?>">
<label for="contact-firstname">Nome</label><input class="form-control" id="contact-firstname" name="contact[firstname]" required maxlength="191"><label for="contact-lastname">Sobrenome</label><input class="form-control" id="contact-lastname" name="contact[lastname]" maxlength="191"><label for="contact-email">E-mail</label><input class="form-control" id="contact-email" name="contact[email]" type="email" required maxlength="100"><button type="submit">Criar contato</button><?= form_close(); ?>
<?php } ?>
<?php } ?>
<?php if($owned&&$capabilities['share']){?>
<h3>Compartilhar identidade</h3><p>Selecione os destinos. Desmarcar revoga o acesso à identidade; não transfere o cadastro.</p>
<?= form_open($url());$hidden(); ?>
<input type="hidden" name="action" value="share"><input type="hidden" name="client_id" value="<?= $client['userid']; ?>"><input type="hidden" name="revision" value="<?= $esc($details['revision']); ?>">
<?php $known=[];foreach($sharing_options as $option){$known[]=$option['id'];?><div class="checkbox"><label><input type="checkbox" name="targets[]" value="<?= $option['id']; ?>" <?= in_array($option['id'],$details['targets'],true)?'checked':''; ?>><?= $esc($option['legal_name']); ?></label></div><?php } ?>
<?php foreach(array_diff($details['targets'],$known) as $target){?><div class="checkbox"><label><input type="checkbox" name="targets[]" value="<?= (int)$target; ?>" checked>Empresa <?= (int)$target; ?> (acesso ao destino indisponível; você pode revogar)</label></div><?php } ?>
<?php if(!$sharing_options&&!$details['targets']){?><p>Nenhum destino com permissão de inclusão disponível.</p><?php } ?>
<button type="submit" class="btn btn-default">Salvar compartilhamento</button><?= form_close(); ?>
<?php } ?><p><a href="<?= $esc($url()); ?>">Voltar à lista</a></p>
<?php } ?>
<h2>Lista de clientes</h2>
<form method="get" action="<?= $esc($url()); ?>"><label for="client-search">Buscar nome ou documento</label><input id="client-search" name="search" maxlength="191" value="<?= $esc($search); ?>"><button type="submit">Buscar</button></form>
<div class="table-responsive"><table class="table"><thead><tr><th scope="col">Nome</th><th scope="col">CPF / CNPJ</th><th scope="col">Acesso</th></tr></thead><tbody>
<?php foreach($directory['items'] as $client){?><tr><td><a href="<?= $esc($url('?client='.$client['userid'])); ?>"><?= $esc($client['company']); ?></a></td><td><?= $esc($client['vat']); ?></td><td><?= $client['owner_issuer_id']===$issuer?'Próprio':'Compartilhado (consulta)'; ?></td></tr><?php } ?>
</tbody></table></div>
<?php if(!$directory['items']){?><p>Nenhum cliente encontrado nesta empresa.</p><?php } ?>
<?php if($directory['next']!==null){?><a href="<?= $esc($url('?'.http_build_query(['after'=>$directory['next'],'search'=>$search]))); ?>">Próxima página</a><?php } ?>
<?php if($capabilities['create']){?>
<h2>Novo cliente privado</h2><p>CPF/CNPJ repetido é recusado, inclusive quando já existe no legado. O conflito não identifica o proprietário.</p>
<?= form_open($url());$hidden(); ?>
<input type="hidden" name="action" value="create"><input type="hidden" name="request_key" value="<?= $esc($request_key); ?>">
<div class="form-group"><label for="new_company">Nome / razão social</label><input class="form-control" id="new_company" name="client[company]" required maxlength="191" autocomplete="organization"></div>
<div class="form-group"><label for="new_vat">CPF / CNPJ (opcional)</label><input class="form-control" id="new_vat" name="client[vat]" maxlength="20"></div>
<button type="submit" class="btn btn-primary">Criar cliente privado</button><?= form_close(); ?>
<?php } ?>
<p>Este diretório administra identidade. Operações financeiras e demais recursos ficam disponíveis conforme a conclusão e autorização de cada fluxo.</p>
</section>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
