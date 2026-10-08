<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="alert alert-info">As opções abaixo são guardadas no banco desta instalação. O idioma disponível é Português do Brasil (PT-BR).</div>

<h4>Atendimento ao cliente</h4>
<p class="text-muted">O widget é carregado somente na área de clientes. O token abaixo é o token público fornecido pelo serviço de suporte.</p>
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
