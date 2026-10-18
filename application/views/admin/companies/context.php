<?php defined('BASEPATH') or exit('No direct script access allowed');
$context = CompanyRequestScope::context();
$selected = $context->selected();
?>
<?php $company_page_title='Empresas'; require APPPATH.'views/admin/companies/layout_head.php'; ?>
<h1><?= $selected ? e($selected['legal_name']) : 'Selecione uma empresa'; ?></h1>
<?php if ($selected && $context->isPrimary()) { ?>
<p>Empresa principal selecionada.</p><p><a href="<?= e(CompanyRequestScope::adminUrl($this)); ?>">Abrir o CRM</a></p>
<?php } elseif ($selected) { ?>
<p>O contexto desta empresa foi validado. As telas ainda não adaptadas e as operações financeiras permanecem bloqueadas.</p>
<?php if (CompanySettingsRuntime::can('view')) { ?><p><a href="<?= e(CompanyRequestScope::adminUrl($this, 'settings?group=company')); ?>">Abrir definições desta empresa</a></p><?php } ?>
<?php } else { ?>
<p>Escolha uma empresa autorizada acima. Se a lista estiver vazia, peça ao administrador para revisar seus vínculos em Colaboradores.</p>
<?php } ?>
<p>Ao trocar de empresa, recarregue as outras abas antes de enviar formulários.</p>
<?php require APPPATH.'views/admin/companies/layout_tail.php'; ?>
