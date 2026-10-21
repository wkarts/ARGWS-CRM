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
<?php if($can_manage && $selected){?><h2><?= $selected?'Informações da empresa':'Nova empresa'; ?></h2>
<?php if($selected && ($selected['identity_status']??'')==='pending'){?><p class="alert alert-warning">Revise os dados cadastrais preservados da instalação.</p><?php } ?>
<?= form_open($url('issuers/save')); ?>
<input type="hidden" name="_company_context" value="<?= $esc(CompanyRequestScope::token()); ?>">
<input type="hidden" name="issuers_csrf" value="<?= $esc($issuers_csrf); ?>">
<input type="hidden" name="action" value="issuer"><input type="hidden" name="issuer_id" value="<?= $selected?(int)$selected['id']:''; ?>">
<?php $identity_mode='registry';$identity_values=['legal_name'=>$selected['legal_name']??'','tax_id'=>$selected['tax_id']??'','profile'=>$profile];$lookup_url=$url('issuers/lookup');$lookup_token=$issuers_csrf;$identity_profile_disabled=$selected&&(int)$selected['active']!==1;require __DIR__.'/identity_fields.php'; ?>
<div class="form-group"><label for="active">Situação</label><select class="form-control" id="active" name="active"><option value="1" <?= !$selected||(int)$selected['active']===1?'selected':''; ?>>Ativa</option><option value="0" <?= $selected&&(int)$selected['active']===0?'selected':''; ?>>Inativa</option></select></div>
<button class="btn btn-primary" type="submit">Salvar</button><?= form_close(); ?>
<?php } elseif($selected){?><h2><?= $esc($selected['legal_name']); ?></h2><p>Você pode consultar esta empresa. A alteração depende da permissão de Definições nesta empresa.</p><?php } else { ?><?php if($can_create){?><p><a class="btn btn-primary" href="<?= $esc($url('company_onboarding')); ?>">Cadastrar empresa passo a passo</a></p><?php } ?><p>Selecione uma empresa autorizada para consultar seus dados.</p><?php } ?>
</div></section></div>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
