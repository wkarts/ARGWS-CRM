<?php defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title='Empresas';require APPPATH.'views/admin/companies/layout_head.php';
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=fn($path)=>CompanyRequestScope::adminUrl($this,$path);
?>
<h1>Empresas</h1>
<?php if(is_array($company_notice??null)){?><div class="alert <?= ($company_notice['type']??'')==='success'?'alert-success':'alert-danger'; ?>" role="status"><?= $esc($company_notice['text']??''); ?></div><?php } ?>
<div class="row"><section class="col-md-4"><div class="company-settings-card"><h2>Cadastro de empresas</h2>
<ul class="list-unstyled"><?php foreach($catalog as $entry){?><li><a href="<?= $esc($url('issuers/index/'.(int)$entry['id'])); ?>"><?= $esc($entry['legal_name']); ?></a> <small><?= (int)$entry['active']===1?'Ativa':'Inativa'; ?></small></li><?php } ?></ul>
<?php if($can_create){?><a class="btn btn-default" href="<?= $esc($url('company_onboarding')); ?>">Nova empresa</a><?php } ?>
</div></section><section class="col-md-8"><div class="company-settings-card">
<?php if($can_manage){?><h2><?= $selected?'Informações da empresa':'Nova empresa'; ?></h2>
<?php if($selected && ($selected['identity_status']??'')==='pending'){?><p class="alert alert-warning">Revise os dados cadastrais preservados da instalação.</p><?php } ?>
<?= form_open($url('issuers/save')); ?>
<input type="hidden" name="_company_context" value="<?= $esc(CompanyRequestScope::token()); ?>">
<input type="hidden" name="issuers_csrf" value="<?= $esc($issuers_csrf); ?>">
<input type="hidden" name="action" value="issuer"><input type="hidden" name="issuer_id" value="<?= $selected?(int)$selected['id']:''; ?>">
<div class="form-group"><label for="legal_name">Nome / razão social</label><input class="form-control" id="legal_name" name="legal_name" maxlength="191" required value="<?= $esc($selected['legal_name']??''); ?>"></div>
<div class="form-group"><label for="tax_id">CPF / CNPJ</label><input class="form-control" id="tax_id" name="tax_id" maxlength="20" required value="<?= $esc($selected['tax_id']??''); ?>"></div>
<div class="form-group"><label for="active">Situação</label><select class="form-control" id="active" name="active"><option value="1" <?= !$selected||(int)$selected['active']===1?'selected':''; ?>>Ativa</option><option value="0" <?= $selected&&(int)$selected['active']===0?'selected':''; ?>>Inativa</option></select></div>
<fieldset <?= $selected&&(int)$selected['active']!==1?'disabled':''; ?>><legend>Endereço e contato</legend>
<?php foreach(['invoice_company_address'=>'Endereço','invoice_company_city'=>'Cidade','company_state'=>'Estado','invoice_company_country_code'=>'País','invoice_company_postal_code'=>'CEP','invoice_company_phonenumber'=>'Telefone'] as $name=>$label){?><div class="form-group"><label for="profile_<?= $esc($name); ?>"><?= $esc($label); ?></label><input class="form-control" id="profile_<?= $esc($name); ?>" name="company_profile[<?= $esc($name); ?>]" maxlength="2000" value="<?= $esc($profile[$name]??''); ?>"></div><?php } ?></fieldset>
<button class="btn btn-primary" type="submit">Salvar</button><?= form_close(); ?>
<?php } elseif($selected){?><h2><?= $esc($selected['legal_name']); ?></h2><p>Você pode consultar esta empresa. A alteração depende da permissão de Definições nesta empresa.</p><?php } else { ?><p>Selecione uma empresa autorizada para consultar seus dados.</p><?php } ?>
</div></section></div>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
