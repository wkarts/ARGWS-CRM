<?php defined('BASEPATH') or exit('No direct script access allowed');
$company_page_title='Logotipo e assinatura';require APPPATH.'views/admin/companies/layout_head.php';
$esc=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$url=CompanyRequestScope::adminUrl($this,'company_assets'); ?>
<section class="company-settings-card"><h1>Logotipo e assinatura da empresa</h1>
<p>As imagens pertencem à empresa selecionada. Novas faturas guardam uma cópia da imagem no documento emitido; trocar ou remover a imagem não altera documentos anteriores. Assinatura é uma imagem de apresentação, sem certificado digital.</p>
<p>PNG ou JPEG, até512 KiB,1600 pixels por dimensão e2 milhões de pixels. O arquivo é convertido para PNG sem metadados ou conteúdo adicional.</p>
<?php if($error){ ?><p role="alert"><?= $esc($error); ?></p><a href="<?= $esc($url); ?>">Reabrir</a><?php } ?>
<?php if($state){foreach(['logo'=>'Logotipo','signature'=>'Assinatura'] as $kind=>$label){ ?>
<article><h2><?= $label; ?></h2>
<?php if(isset($state['assets'][$kind])){ ?><img id="company-asset-<?= $kind; ?>" src="<?= $esc($state['assets'][$kind]); ?>" alt="<?= $label; ?> da empresa selecionada" style="max-width:280px;max-height:140px"><p><a href="<?= $esc($url.'?download='.$kind); ?>">Baixar <?= $label; ?></a></p><?php }else{ ?><p>Nenhuma imagem configurada.</p><?php } ?>
<?php if($can_edit){echo form_open_multipart($url); ?>
<input type="hidden" name="company_assets_csrf" value="<?= $esc($asset_csrf); ?>"><input type="hidden" name="kind" value="<?= $kind; ?>"><input type="hidden" name="revision" value="<?= $esc($state['revision']); ?>">
<label for="asset-upload-<?= $kind; ?>">Arquivo de <?= $label; ?></label><input id="asset-upload-<?= $kind; ?>" type="file" name="company_asset" accept="image/png,image/jpeg">
<button type="submit" name="action" value="upload">Salvar <?= $label; ?></button>
<?php if(isset($state['assets'][$kind])){ ?><button type="submit" name="action" value="remove">Remover <?= $label; ?></button><?php }echo form_close();} ?>
</article><?php }} ?></section><?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
