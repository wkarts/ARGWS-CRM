<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<div class="row">
    <div class="col-md-6">
        <div class="alert alert-info">
            <h4 class="tw-font-bold">Migration aplicada no banco</h4>
            <p class="tw-font-semibold tw-mb-0"><?= html_escape(wordwrap((string) $current_version, 1, '.', true)); ?></p>
        </div>
    </div>
    <div class="col-md-6">
        <div class="alert <?= $latest_version > $current_version ? 'alert-warning' : 'alert-success'; ?>">
            <h4 class="tw-font-bold">Migration incluída no código</h4>
            <p class="tw-font-semibold tw-mb-0"><?= html_escape(wordwrap((string) $latest_version, 1, '.', true)); ?><br><small>ARGWS CRM <?= html_escape(ARGWS_VERSION); ?></small></p>
        </div>
    </div>
</div>
<div class="alert alert-info">
    As atualizações são distribuídas pelo canal oficial ARGWS. O painel não envia identificadores da instalação nem baixa código automaticamente.
</div>
<?php if ($latest_version > $current_version) { ?>
    <div class="alert alert-warning">
        Antes de atualizar, faça backup dos arquivos, do banco de dados e dos uploads. Aplique o pacote correspondente à versão instalada e depois confirme a atualização do banco na tela administrativa.
    </div>
<?php } else { ?>
    <div class="alert alert-success">O banco desta instalação já está atualizado para a migration incluída no código atual.</div>
<?php } ?>
<a class="btn btn-primary" href="<?= html_escape(ARGWS_RELEASES_URL); ?>" target="_blank" rel="noopener noreferrer">Abrir versões ARGWS</a>
<p class="text-muted mtop15">Para instalações PHP tradicionais, copie os arquivos do pacote incremental. Para FrankenPHP, use uma tag de imagem versionada publicada no GHCR. Preserve `application/config/app-config.php` e `uploads/`.</p>
