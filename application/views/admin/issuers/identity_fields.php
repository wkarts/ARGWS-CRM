<?php defined('BASEPATH') or exit('No direct script access allowed');
// Shared canonical fields for creation and both registry renderers.
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$identity_labels=['company_trade_name'=>'Nome fantasia','company_state_registration'=>'Inscrição estadual (ou ISENTO, quando aplicável)','invoice_company_postal_code'=>'CEP','invoice_company_address'=>'Logradouro / endereço','company_address_number'=>'Número','company_address_complement'=>'Complemento','company_address_district'=>'Bairro','invoice_company_city'=>'Cidade','company_state'=>'UF / estado','invoice_company_country_code'=>'País (código, ex.: BR)','invoice_company_phonenumber'=>'Telefone'];
?>
<div class="company-identity-editor" data-company-identity data-company-context="<?= $esc(CompanyRequestScope::token()); ?>" data-lookup-url="<?= $esc($lookup_url); ?>" data-lookup-token="<?= $esc($lookup_token); ?>" data-lookup-mode="<?= $esc($identity_mode); ?>" data-issuer-id="<?= $esc($selected['id']??''); ?>">
<link rel="stylesheet" href="<?= $esc(base_url('assets/css/company-identity.css')); ?>">
<ul class="nav nav-tabs company-identity-sections" aria-label="Seções do cadastro" tabindex="0"><li><a href="#company-identification">1. Identificação</a></li><li><a href="#company-address">2. Endereço</a></li><li><a href="#company-contact">3. Contato</a></li></ul>
<section id="company-identification" data-identity-panel><h3>Identificação da empresa</h3>
<p class="text-muted">Consulte o CNPJ ou preencha manualmente. Razão social e documento fiscal são obrigatórios.</p>
<div class="company-identity-grid">
<div class="form-group"><label for="tax_id">CPF / CNPJ</label><input class="form-control" id="tax_id" data-company-field="tax_id" name="<?= $esc($identity_mode==='onboarding'?'wizard[tax_id]':'tax_id'); ?>" maxlength="20" required value="<?= $esc($identity_values['tax_id']??''); ?>"><button type="button" class="btn btn-default" data-company-lookup="cnpj">Consultar CNPJ</button></div>
<div class="form-group"><label for="legal_name">Razão social / nome</label><input class="form-control" id="legal_name" data-company-field="legal_name" name="<?= $esc($identity_mode==='onboarding'?'wizard[legal_name]':'legal_name'); ?>" maxlength="191" required autocomplete="organization" value="<?= $esc($identity_values['legal_name']??''); ?>"></div>
<?php foreach(['company_trade_name','company_state_registration'] as $field){require __DIR__.'/identity_input.php';} ?>
</div><p class="text-muted">A inscrição estadual depende da atividade e da UF. A consulta não confirma isenção nem fornece todas as inscrições.</p></section>
<fieldset id="company-address" data-identity-panel <?= !empty($identity_profile_disabled)?'disabled':''; ?>><legend>Endereço</legend><p class="text-muted">Se o endereço antigo já inclui número, complemento ou bairro, separe os dados nos campos abaixo para evitar duplicidade nos documentos.</p><div class="company-identity-grid">
<?php foreach(['invoice_company_postal_code','invoice_company_address','company_address_number','company_address_complement','company_address_district','invoice_company_city','company_state','invoice_company_country_code'] as $field){require __DIR__.'/identity_input.php';} ?>
</div></fieldset>
<section id="company-contact" data-identity-panel><h3>Contato</h3><?php $field='invoice_company_phonenumber';require __DIR__.'/identity_input.php'; ?></section>
<p class="text-muted">Ao consultar, o CNPJ será enviado à ReceitaWS ou o CEP ao ViaCEP, serviços terceiros. Confira os dados retornados antes de aplicar. A consulta não salva o cadastro.</p>
<p data-lookup-status role="status" aria-live="polite"></p>
<section data-lookup-preview hidden aria-label="Revisar dados consultados"><h3>Revise os dados da consulta</h3><p>Selecione os campos que deseja aplicar. Campos já preenchidos ficam desmarcados; você pode revisar e editar cada valor.</p><div data-lookup-fields></div><button type="button" class="btn btn-primary" data-lookup-apply>Aplicar campos selecionados</button> <button type="button" class="btn btn-default" data-lookup-cancel>Descartar consulta</button></section>
</div>
<script src="<?= $esc(base_url('assets/js/company-identity.js')); ?>" defer></script>
