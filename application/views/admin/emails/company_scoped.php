<?php defined('BASEPATH') or exit('No direct script access allowed');
$escape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$company_page_title='Modelos de e-mail';require APPPATH.'views/admin/companies/layout_head.php'; ?>
<h1>Modelos de e-mail</h1>
<p>Empresa selecionada: <strong><?= $escape($company_identity['legal_name']); ?></strong></p>
<section class="company-settings-card">
<?php if ($error!==null) { ?><div role="alert" class="alert alert-danger"><?= $escape($error); ?></div><?php } ?>
<?php if ($saved) { ?><div role="status" class="alert alert-success">Modelo salvo para esta empresa.</div><?php } ?>
<?php if (!$selected) { ?>
<ul><?php foreach($templates as $item) { ?><li><a href="<?= $escape($edit_url.(int)$item['emailtemplateid']); ?>"><?= $escape(EmailTemplateLocale::templateName($item)); ?></a> (<?= !empty($item['active'])?'Ativo':'Inativo'; ?>)</li><?php } ?></ul>
<?php } else { ?>
<a href="<?= $escape($list_url); ?>">Voltar aos modelos</a><h2><?= $escape(EmailTemplateLocale::templateName($selected)); ?></h2>
<p>Idioma: <?= $escape(EmailTemplateLocale::languageLabel((string) $selected->language)); ?></p>
<p>Use os campos entre chaves do modelo original. O conteúdo HTML é editado como texto e não executado nesta página.</p>
<form method="post" action="<?= $escape($edit_url.(int)$selected->emailtemplateid); ?>" autocomplete="off">
<input type="hidden" name="_company_context" value="<?= $escape($context_token); ?>">
<?php if ($this->config->item('csrf_protection')) { ?><input type="hidden" name="<?= $escape($this->security->get_csrf_token_name()); ?>" value="<?= $escape($this->security->get_csrf_hash()); ?>"><?php } ?>
<fieldset <?= !$can_edit?'disabled':''; ?>>
<?php foreach(['subject'=>'Assunto','fromname'=>'Nome do remetente','fromemail'=>'E-mail do remetente'] as $field=>$label) { ?><div class="form-group"><label for="template-<?= $escape($field); ?>"><?= $escape($label); ?></label><input class="form-control" id="template-<?= $escape($field); ?>" type="<?= $field==='fromemail'?'email':'text'; ?>" name="<?= $escape($field); ?>" value="<?= $escape($selected->$field); ?>" maxlength="<?= $field==='subject'?'998':'191'; ?>"></div><?php } ?>
<div class="form-group"><label for="template-message">Conteúdo</label><textarea class="form-control" id="template-message" name="message" rows="18"><?= $escape($selected->message); ?></textarea></div>
<label><input type="checkbox" name="active" value="1" <?= $selected->active?'checked':''; ?>> Ativo</label>
<label><input type="checkbox" name="plaintext" value="1" <?= $selected->plaintext?'checked':''; ?>> Enviar como texto simples</label>
<?php if ($can_edit) { ?><div class="company-settings-actions"><button class="btn btn-primary" type="submit">Salvar modelo</button></div><?php } ?>
</fieldset></form><?php } ?></section>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
