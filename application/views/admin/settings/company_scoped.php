<?php defined('BASEPATH') or exit('No direct script access allowed');
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$labels = [
    'invoice_company_name' => 'Nome da empresa', 'company_vat' => 'CPF/CNPJ',
    'invoice_company_address' => 'Endereço', 'invoice_company_city' => 'Cidade', 'company_state' => 'Estado',
    'invoice_company_country_code' => 'País', 'invoice_company_postal_code' => 'CEP', 'invoice_company_phonenumber' => 'Telefone',
    'dateformat' => 'Formato da data', 'time_format' => 'Formato da hora',
    'smtp_host' => 'Servidor SMTP', 'smtp_port' => 'Porta SMTP', 'smtp_encryption' => 'Criptografia',
    'smtp_email' => 'E-mail do remetente', 'smtp_username' => 'Usuário SMTP',
];
?>
<?php $company_page_title='Definições'; require APPPATH.'views/admin/companies/layout_head.php'; ?>
    <a href="<?= $escape($company_urls['companies']); ?>">Empresas</a>
    <h1>Definições</h1>
    <p>Empresa selecionada: <strong><?= $escape($company_identity['legal_name']); ?></strong></p>
    <nav class="company-settings-nav" aria-label="Seções de definições">
        <a href="<?= $escape($company_urls['company']); ?>" <?= $group === 'company' ? 'aria-current="page"' : ''; ?>>Informações da Empresa</a>
        <a href="<?= $escape($company_urls['email']); ?>" <?= $group === 'email' ? 'aria-current="page"' : ''; ?>>E-mail</a>
        <a href="<?= $escape($company_urls['localization']); ?>" <?= $group === 'localization' ? 'aria-current="page"' : ''; ?>>Data e hora</a>
    </nav>
    <section class="company-settings-card">
        <h2><?= $group === 'company' ? 'Informações da Empresa' : ($group === 'email' ? 'Servidor SMTP' : 'Formato de data e hora'); ?></h2>
        <?php if ($error !== null) { ?><div role="alert" class="alert alert-danger"><?= $escape($error); ?></div><?php } ?>
        <?php if ($saved) { ?><div role="status" class="alert alert-success">Definições salvas para esta empresa.</div><?php } ?>
        <?php if ($company_identity['identity_status'] === 'pending') { ?><div class="alert alert-warning">A identidade fiscal desta empresa precisa ser conferida.</div><?php } ?>
        <?php if ($group === 'email') { ?><p>As credenciais são exclusivas desta empresa. O envio de teste estará disponível após a integração do transporte de e-mail por empresa.</p><?php } ?>
        <form method="post" action="<?= $escape($company_urls[$group]); ?>" autocomplete="off">
            <input type="hidden" name="_company_context" value="<?= $escape($context_token); ?>">
            <?php if ($this->config->item('csrf_protection')) { ?><input type="hidden" name="<?= $escape($this->security->get_csrf_token_name()); ?>" value="<?= $escape($this->security->get_csrf_hash()); ?>"><?php } ?>
            <fieldset <?= !$can_edit ? 'disabled' : ''; ?>>
                <div class="company-settings-grid">
                <?php foreach ($values as $name => $value) { ?>
                    <div class="form-group"><label for="company-setting-<?= $escape($name); ?>"><?= $escape($labels[$name] ?? $name); ?></label>
                    <?php if (in_array($name,['dateformat','time_format'],true)) { ?>
                        <select class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" required>
                            <option value="">Não configurado</option>
                            <?php foreach ($name === 'dateformat' ? CompanySettingsFormats::dates() : ['24'=>'24 horas','12'=>'12 horas'] as $key=>$label) { ?><option value="<?= $escape($key); ?>" <?= $value === (string)$key ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php } ?>
                        </select>
                    <?php } elseif ($name === 'smtp_encryption') { ?>
                        <select class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]">
                            <?php foreach (['' => 'Nenhuma', 'ssl' => 'SSL', 'tls' => 'TLS'] as $key => $label) { ?><option value="<?= $escape($key); ?>" <?= $value === $key ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php } ?>
                        </select>
                    <?php } else { ?>
                        <input class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" type="<?= $name === 'smtp_email' ? 'email' : 'text'; ?>" value="<?= $escape($value); ?>" maxlength="<?= $name === 'invoice_company_name' ? '191' : '2000'; ?>">
                    <?php } ?></div>
                <?php } ?>
                <?php if ($group === 'email') { ?>
                    <div class="form-group"><label for="company-smtp-password">Senha SMTP</label><input class="form-control" id="company-smtp-password" name="settings[smtp_password]" type="password" value="" autocomplete="new-password"><small><?= $password_configured ? 'Senha já configurada. Deixe em branco para manter.' : 'Nenhuma senha configurada para esta empresa.'; ?></small><div class="checkbox"><label><input type="checkbox" name="clear_smtp_password" value="1">Apagar a senha configurada</label></div></div>
                <?php } ?>
                </div>
                <?php if ($can_edit) { ?><div class="company-settings-actions"><button class="btn btn-primary" type="submit">Salvar definições</button></div><?php } ?>
            </fieldset>
        </form>
    </section>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
