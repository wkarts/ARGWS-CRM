<?php defined('BASEPATH') or exit('No direct script access allowed');
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$labels = [
    'pdf_font'=>'Fonte Unicode dos documentos novos','pdf_font_size'=>'Tamanho da fonte em pontos',
    'allow_contact_to_delete_files'=>'Permitir ao contato ocultar os próprios arquivos de projetos',
    'invoice_company_name' => 'Nome da empresa', 'company_vat' => 'CPF/CNPJ',
    'invoice_company_address' => 'Endereço', 'invoice_company_city' => 'Cidade', 'company_state' => 'Estado',
    'invoice_company_country_code' => 'País', 'invoice_company_postal_code' => 'CEP', 'invoice_company_phonenumber' => 'Telefone',
    'invoice_prefix'=>'Prefixo da numeração de faturas','predefined_terms_invoice'=>'Condições padrão das faturas',
    'estimate_prefix'=>'Prefixo da numeração de orçamentos','predefined_terms_estimate'=>'Condições padrão dos orçamentos',
    'proposal_number_prefix'=>'Prefixo da numeração de propostas','predefined_terms_proposal'=>'Condições padrão das propostas',
    'credit_note_prefix'=>'Prefixo da numeração de créditos','predefined_terms_credit_note'=>'Condições padrão dos créditos',
    'email_header'=>'Cabeçalho HTML','email_signature'=>'Assinatura HTML','email_footer'=>'Rodapé HTML',
    'pdf_format_invoice'=>'PDF de faturas','pdf_format_credit_note'=>'PDF de créditos','pdf_format_contract'=>'PDF de contratos',
    'pdf_format_estimate'=>'PDF de orçamentos','pdf_format_proposal'=>'PDF de propostas',
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
        <a href="<?= $escape(CompanyRequestScope::adminUrl($this,'company_assets')); ?>">Logotipo e assinatura</a>
        <?php foreach ($groups as $key=>$label) { ?><a href="<?= $escape($company_urls[$key]); ?>" <?= $group === $key ? 'aria-current="page"' : ''; ?>><?= $escape($label); ?></a><?php } ?>
    </nav>
    <section class="company-settings-card">
        <h2><?= $escape($groups[$group]); ?></h2>
        <?php if ($error !== null) { ?><div role="alert" class="alert alert-danger"><?= $escape($error); ?></div><?php } ?>
        <?php if ($saved) { ?><div role="status" class="alert alert-success">Definições salvas para esta empresa.</div><?php } ?>
        <?php if ($company_identity['identity_status'] === 'pending') { ?><div class="alert alert-warning">A identidade fiscal desta empresa precisa ser conferida.</div><?php } ?>
        <?php if ($group === 'email') { ?><p>As credenciais são exclusivas desta empresa. A configuração é usada pelas notificações de fatura e pela recuperação do portal desta empresa. Salvar não envia e-mail nem valida credenciais no provedor.</p><?php } ?>
        <?php if ($group === 'email_content') { ?><p>HTML de apresentação usado nos e-mails desta empresa. Cada campo é um fragmento completo. Formatação de texto, tabelas e links HTTPS são preservados; scripts, formulários e imagens remotas são removidos. As alterações valem para novos e-mails enfileirados; mensagens já enfileiradas mantêm seu conteúdo.</p><?php } ?>
        <?php if ($group === 'sales_documents') { ?><p>Preferências próprias para novos documentos. Prefixo vazio exibe somente o número. Condições são texto literal. Documentos e notificações já emitidos preservam seu snapshot. A conversão usa identidade, condições e numeração próprias da fatura.</p><?php } ?>
        <?php if ($group === 'sales_pdf') { ?><p>Formato e orientação capturados nos novos orçamentos e propostas. Documentos existentes mantêm sua escolha original; documentos anteriores a esta preferência mantêm A4 retrato. Outros tipos de PDF ainda não usam estas opções.</p><?php } ?>
        <?php if ($group === 'document_pdf') { ?><p>Formato e orientação capturados na emissão de novas faturas e créditos, e na ativação de novos contratos. Documentos existentes preservam seu snapshot; os anteriores a esta preferência mantêm A4 retrato. Formato de PDF não representa assinatura ou aceite jurídico.</p><?php } ?>
        <?php if($group==='pdf_typography'){?><p>Preferências próprias capturadas somente em novas faturas, créditos, contratos, orçamentos e propostas. PDFs já emitidos mantêm fonte e tamanho originais. Tamanho vazio conserva 9 pontos em faturas/créditos e 10 nos demais; o rodapé da fatura usa 8 pontos. São oferecidas apenas famílias Unicode já instaladas, sem fontes personalizadas.</p><?php }?>
        <?php if($group==='project_files'){?><p>Desativado por padrão em cada empresa, inclusive a principal. Permite ao contato ocultar somente arquivos próprios, quando o projeto e suas permissões atuais também autorizarem. Compartilhamento não concede exclusão. Ocultar preserva o conteúdo e os recibos; esta opção não remove arquivos de outras pessoas.</p><?php }?>
        <form method="post" action="<?= $escape($company_urls[$group]); ?>" autocomplete="off">
            <input type="hidden" name="_company_context" value="<?= $escape($context_token); ?>">
            <?php if ($this->config->item('csrf_protection')) { ?><input type="hidden" name="<?= $escape($this->security->get_csrf_token_name()); ?>" value="<?= $escape($this->security->get_csrf_hash()); ?>"><?php } ?>
            <?php if($group==='pdf_typography'){?><input type="hidden" name="pdf_typography_revision" value="<?= (int)$pdf_typography_revision; ?>"><?php }?>
            <?php if($group==='project_files'){?><input type="hidden" name="project_files_revision" value="<?= (int)$project_files_revision; ?>"><?php }?>
            <fieldset <?= !$can_edit ? 'disabled' : ''; ?>>
                <div class="company-settings-grid">
                <?php foreach ($values as $name => $value) { ?>
                    <div class="form-group"><label for="company-setting-<?= $escape($name); ?>"><?= $escape($labels[$name] ?? $name); ?></label>
                    <?php if($name==='pdf_font'){?><select class="form-control" id="company-setting-pdf_font" name="settings[pdf_font]" required><?php foreach(CompanyPdfTypography::fonts()as$key=>$label){?><option value="<?= $escape($key); ?>" <?= $value===$key?'selected':''; ?>><?= $escape($label); ?></option><?php }?></select>
                    <?php }elseif($name==='pdf_font_size'){?><input class="form-control" id="company-setting-pdf_font_size" name="settings[pdf_font_size]" type="number" min="6" max="24" step="1" value="<?= $escape($value); ?>"><small>Entre 6 e 24 pontos, ou vazio para manter o padrão de cada documento.</small>
                    <?php }elseif($name==='allow_contact_to_delete_files'){?><select class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" required><?php foreach(['0'=>'Não permitir','1'=>'Permitir nesta empresa']as$key=>$label){?><option value="<?= $key; ?>" <?= $value===(string)$key?'selected':''; ?>><?= $escape($label); ?></option><?php }?></select>
                    <?php }elseif(CompanySettingsCatalog::multiline($name)){ ?><textarea class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" maxlength="<?= $group === 'email_content' ? '60000' : '10000'; ?>" rows="6"><?= $escape($value); ?></textarea>
                    <?php }elseif (in_array($name,['dateformat','time_format'],true)) { ?>
                        <select class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" required>
                            <option value="">Não configurado</option>
                            <?php foreach ($name === 'dateformat' ? CompanySettingsFormats::dates() : ['24'=>'24 horas','12'=>'12 horas'] as $key=>$label) { ?><option value="<?= $escape($key); ?>" <?= $value === (string)$key ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php } ?>
                        </select>
                    <?php } elseif (in_array($name,['pdf_format_estimate','pdf_format_proposal','pdf_format_invoice','pdf_format_credit_note','pdf_format_contract'],true)) { ?>
                        <select class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" required>
                            <?php foreach (CompanyDocumentPdf::choices() as $key=>$label) { ?><option value="<?= $escape($key); ?>" <?= $value === $key ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php } ?>
                        </select>
                    <?php } elseif ($name === 'smtp_encryption') { ?>
                        <select class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]">
                            <?php foreach (['' => 'Nenhuma', 'ssl' => 'SSL', 'tls' => 'TLS'] as $key => $label) { ?><option value="<?= $escape($key); ?>" <?= $value === $key ? 'selected' : ''; ?>><?= $escape($label); ?></option><?php } ?>
                        </select>
                    <?php } else { ?>
                        <input class="form-control" id="company-setting-<?= $escape($name); ?>" name="settings[<?= $escape($name); ?>]" type="<?= $name === 'smtp_email' ? 'email' : 'text'; ?>" value="<?= $escape($value); ?>" maxlength="<?= in_array($name,['invoice_prefix','credit_note_prefix','estimate_prefix','proposal_number_prefix'],true)?'50':($name === 'invoice_company_name' ? '191' : '2000'); ?>">
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

<?php if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active()&&CompanySettingsRuntime::can('view')&&$group==='email'){?><p><a href="<?= htmlspecialchars(CompanyRequestScope::adminUrl($this,'company_google_mail_oauth'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>">Google SMTP próprio para notificações de documentos</a></p><?php } ?>

<?php if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active()&&CompanySettingsRuntime::can('view')&&$group==='email'){?><p><a href="<?= htmlspecialchars(CompanyRequestScope::adminUrl($this,'company_microsoft_mail_oauth'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>">Microsoft 365 SMTP próprio e seleção do transporte de documentos</a></p><?php }?>

<?php if(class_exists('CompanySettingsRuntime',false)&&CompanySettingsRuntime::active()&&CompanySettingsRuntime::can('view')&&$group==='email'&&(new IssuerResourceAccess($this->db,db_prefix()))->can(CompanyRequestScope::context()->issuerId(),CompanyRequestScope::context()->staffId(),'connect-api-connector','view')){?><p><a href="<?= htmlspecialchars(CompanyRequestScope::adminUrl($this,'company_connect_api'),ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); ?>">Connect|API WhatsApp próprio para avisos privados</a></p><?php } ?>

<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
