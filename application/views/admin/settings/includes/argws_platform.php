<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="alert alert-info">As opções abaixo são guardadas no banco desta instalação. O idioma disponível é Português do Brasil (PT-BR).</div>

<h4>Contato de suporte</h4>
<p class="text-muted">Configure como o cliente fala com o responsável por esta instalação.
Por padrão, nenhum serviço externo é carregado. É possível usar valores do ambiente
<code>CRM_SUPPORT_WHATSAPP</code>, <code>CRM_SUPPORT_EMAIL</code> e
<code>CRM_SUPPORT_SITE_URL</code> quando o campo correspondente estiver vazio.</p>
<div class="form-group">
    <label for="support_contact_channel">Canal de atendimento</label>
    <select class="form-control selectpicker" name="settings[support_contact_channel]" id="support_contact_channel">
        <?php $channel = get_option('support_contact_channel') ?: (get_option('argws_support_enabled') === '1' ? 'widget' : 'none'); ?>
        <?php foreach (['none' => 'Sem botão de atendimento', 'whatsapp' => 'WhatsApp', 'email' => 'E-mail', 'website' => 'Site de suporte', 'widget' => 'Widget integrado (opcional)'] as $value => $label) { ?>
            <option value="<?= html_escape($value); ?>" <?= $channel === $value ? 'selected' : ''; ?>><?= html_escape($label); ?></option>
        <?php } ?>
    </select>
</div>
<div class="form-group">
    <label for="support_whatsapp">WhatsApp do suporte (país + DDD + telefone)</label>
    <input id="support_whatsapp" class="form-control" name="settings[support_whatsapp]" type="tel" maxlength="20"
        value="<?= html_escape(get_option('support_whatsapp')); ?>" placeholder="5575988881111">
</div>
<div class="form-group">
    <label for="support_email">E-mail do suporte</label>
    <input id="support_email" class="form-control" name="settings[support_email]" type="email" maxlength="254"
        value="<?= html_escape(get_option('support_email')); ?>" placeholder="atendimento@exemplo.com">
</div>
<div class="form-group">
    <label for="support_site_url">Site de atendimento (HTTPS)</label>
    <input id="support_site_url" class="form-control" name="settings[support_site_url]" type="url"
        value="<?= html_escape(get_option('support_site_url')); ?>" placeholder="https://suporte.exemplo.com">
</div>
<hr>
<h4>Widget integrado (opcional)</h4>
<p class="text-muted">Esta integração antiga permanece disponível apenas quando selecionada e habilitada; WhatsApp, e-mail e site não precisam dela.</p>

<input type="hidden" name="settings[argws_support_enabled]" value="0">
<div class="checkbox checkbox-primary">
    <input type="checkbox" id="argws_support_enabled" name="settings[argws_support_enabled]" value="1" <?= get_option('argws_support_enabled') == '1' ? 'checked' : ''; ?>>
    <label for="argws_support_enabled">Habilitar widget de suporte</label>
</div>
<div class="form-group">
    <label for="argws_support_base_url">Endereço HTTPS do serviço</label>
    <input type="url" class="form-control" id="argws_support_base_url" name="settings[argws_support_base_url]" value="<?= html_escape(get_option('argws_support_base_url')); ?>" placeholder="https://suporte.exemplo.com">
</div>
<div class="form-group">
    <label for="argws_support_public_token">Token público do widget</label>
    <input type="text" class="form-control" id="argws_support_public_token" name="settings[argws_support_public_token]" value="<?= html_escape(get_option('argws_support_public_token')); ?>" maxlength="255" autocomplete="off">
</div>
<div class="form-group">
    <label for="argws_support_position">Posição do botão</label>
    <select class="form-control selectpicker" id="argws_support_position" name="settings[argws_support_position]">
        <option value="left" <?= get_option('argws_support_position') === 'left' ? 'selected' : ''; ?>>Esquerda</option>
        <option value="right" <?= get_option('argws_support_position') === 'right' ? 'selected' : ''; ?>>Direita</option>
    </select>
</div>
<div class="form-group">
    <label for="argws_support_type">Apresentação</label>
    <select class="form-control selectpicker" id="argws_support_type" name="settings[argws_support_type]">
        <option value="expanded_bubble" <?= get_option('argws_support_type') === 'expanded_bubble' ? 'selected' : ''; ?>>Balão expandido</option>
        <option value="standard" <?= get_option('argws_support_type') === 'standard' ? 'selected' : ''; ?>>Botão compacto</option>
    </select>
</div>
<div class="form-group">
    <label for="argws_support_launcher_title">Texto do botão</label>
    <input type="text" class="form-control" id="argws_support_launcher_title" name="settings[argws_support_launcher_title]" value="<?= html_escape(get_option('argws_support_launcher_title') ?: 'Suporte'); ?>" maxlength="60">
</div>

<hr>
<h4>Idioma e terminologia</h4>
<p class="text-muted">Mantenha os termos que devem ser traduzidos, os que permanecem no idioma original, as traduções aprovadas e o contexto de uso em JSON.</p>
<div class="form-group">
    <label for="argws_terminology_policy">Política de terminologia</label>
    <textarea class="form-control" id="argws_terminology_policy" name="settings[argws_terminology_policy]" rows="16" spellcheck="false"><?= html_escape(get_option('argws_terminology_policy')); ?></textarea>
</div>
