<?php defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title='Certificado A1 da empresa';
require APPPATH.'views/admin/companies/layout_head.php';
$esc=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$url=CompanyRequestScope::adminUrl($this,'company_certificates/index/'.$page['issuer_id']);
$hidden=static function(string $action)use($csrf,$page,$esc){
    echo '<input type="hidden" name="issuer_certificate_csrf" value="'.$esc($csrf).'"><input type="hidden" name="revision" value="'.(int)$page['revision'].'"><input type="hidden" name="action" value="'.$esc($action).'">';
};
$hasMaterial=$page['revision']>0&&!in_array($page['status'],['removed','unconfigured'],true);
$status=['integrity_failed'=>'Integridade não confirmada: substitua ou remova após revisão administrativa','unconfigured'=>'Nenhum certificado cadastrado','stored'=>'Certificado armazenado','removed'=>'Material removido','expired'=>'Certificado expirado','not_yet_valid'=>'Certificado ainda não válido','document_changed'=>'Documento da empresa alterado; vínculo precisa de revisão'];
?>
<section class="company-settings-card">
<a href="<?= $esc(CompanyRequestScope::adminUrl($this,'issuers/index/'.$page['issuer_id'])); ?>">Voltar ao cadastro da empresa</a>
<h1>Certificado A1</h1>
<p>O acesso a esta página e às alterações exige permissão de edição das definições desta empresa.</p>
<p>Empresa: <strong><?= $esc($page['company_name']); ?></strong></p>
<p id="certificate-status" role="status"><?= $esc($status[$page['status']]??'Indisponível'); ?> · revisão <?= (int)$page['revision']; ?></p>
<?php if($error){ ?><p class="alert alert-danger" role="alert"><?= $esc($error); ?></p><?php } ?>
<?php if($notice){ ?><p class="alert alert-success" role="status"><?= $esc($notice); ?></p><?php } ?>
<p>O PFX/P12 e sua senha ficam cifrados no armazenamento privado desta empresa. Esta etapa prepara o cadastro para integrações futuras; não emite NF-e nem transmite o certificado a provedores.</p>
<?php if($page['metadata']){ $meta=$page['metadata']; ?>
<dl class="company-settings-grid" id="certificate-metadata">
<div><dt>Titular</dt><dd><?= $esc($meta['subject']); ?></dd></div>
<div><dt>Emissor</dt><dd><?= $esc($meta['issuer']); ?></dd></div>
<div><dt>Validade (UTC)</dt><dd><?= $esc($meta['valid_from']); ?> até <?= $esc($meta['valid_to']); ?></dd></div>
<div><dt>Número de série</dt><dd><?= $esc($meta['serial']); ?></dd></div>
<div><dt>CNPJ no certificado</dt><dd><?= $esc($meta['document']??'Não identificado'); ?></dd></div>
<div><dt>Vínculo documental</dt><dd id="certificate-document-status"><?= $page['status']==='document_changed'?'CNPJ divergente do cadastro atual':($meta['document_status']==='matched'?'CNPJ identificado no otherName e correspondente ao cadastro':'Não verificado: CNPJ não identificado no otherName'); ?></dd></div>
</dl>
<p>SHA-256: <span style="overflow-wrap:anywhere"><?= $esc($meta['fingerprint_sha256']); ?></span></p>
<?php } ?>
<p class="alert alert-info">As verificações locais conferem a leitura do arquivo, a presença da chave privada correspondente, a validade e o CNPJ quando identificado. Elas não comprovam confiança ICP-Brasil, revogação por OCSP/CRL ou habilitação para NF-e.</p>
<h2><?= $hasMaterial?'Substituir certificado':'Enviar certificado'; ?></h2>
<?= form_open_multipart($url,['id'=>'issuer-certificate-upload','autocomplete'=>'off']); $hidden('replace'); ?>
<div class="company-settings-grid">
<div class="form-group"><label for="certificate-file">Arquivo PFX/P12 (até 2 MiB)</label><input id="certificate-file" class="form-control" type="file" name="certificate_file" accept=".pfx,.p12" required aria-describedby="certificate-upload-help"></div>
<div class="form-group"><label for="certificate-password">Senha do arquivo</label><input id="certificate-password" class="form-control" type="password" name="certificate_password" value="" maxlength="4096" autocomplete="new-password" aria-describedby="certificate-upload-help"></div>
</div>
<p id="certificate-upload-help">Uma senha vazia só funciona se o arquivo tiver sido exportado sem senha. Em caso de erro, escolha o arquivo e informe a senha novamente. Uma substituição recusada preserva o certificado anterior.</p>
<button class="btn btn-primary" type="submit"><?= $hasMaterial?'Validar e substituir':'Validar e armazenar'; ?></button>
<?= form_close(); ?>
<?php if($hasMaterial){ ?>
<hr><h2>Remover material armazenado</h2>
<p>A remoção limpa o PFX/P12 e a senha do cadastro ativo. Ela não revoga o certificado na autoridade certificadora. Backups seguem a política de retenção do ambiente.</p>
<?= form_open($url,['id'=>'issuer-certificate-remove']); $hidden('remove'); ?>
<button class="btn btn-danger" type="submit">Remover certificado desta empresa</button>
<?= form_close(); ?>
<?php } ?>
</section>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
