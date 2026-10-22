<?php defined('BASEPATH') or exit('No direct script access allowed');
// Native public assets and structural containers, without hydrating legacy financial menus/hooks.
require_once APPPATH.'helpers/app_branding_helper.php';
$companyLayoutEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$companyLayoutContext = CompanyRequestScope::context();
$companyLayoutPrimary = $companyLayoutContext && $companyLayoutContext->isPrimary();
$companyLayoutVersion = defined('ARGWS_VERSION') ? ARGWS_VERSION : 'core';
$companyLayoutHasSettings = class_exists('CompanySettingsRuntime',false) && CompanySettingsRuntime::active();
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $companyLayoutEscape(crm_app_page_title($company_page_title ?? 'Empresas')); ?></title>
<?php // Explicit static assets only: never invoke the legacy admin hooks or hydration pipeline.
foreach (['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/plugins/bootstrap/css/bootstrap.min.css', 'assets/plugins/bootstrap-select/css/bootstrap-select.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css'] as $companyLayoutAsset) { ?>
<link rel="stylesheet" href="<?= $companyLayoutEscape(base_url($companyLayoutAsset).'?v='.$companyLayoutVersion); ?>">
<?php } ?>
<?php require APPPATH.'views/admin/includes/company_context_script.php'; ?>
</head><body class="admin company-context-shell">
<header id="header"><span class="installation-name"><?= $companyLayoutEscape(crm_app_display_name()); ?></span>
<?php require APPPATH.'views/admin/includes/company_selector.php'; ?>
</header>
<nav id="menu" aria-label="Menu do CRM">
<?php if ($companyLayoutPrimary) { ?><a class="company-dashboard" href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this)); ?>">Painel</a><?php } ?>
<a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'issuers')); ?>">Empresas</a>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('hr','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_hr')); ?>">RH da empresa</a><?php }?>
<a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_hr/mine')); ?>">Meu RH</a>

<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('staff','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_staff')); ?>">Equipe da empresa</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('purchases','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_purchases')); ?>">Compras da empresa</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('leads','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_leads')); ?>">Leads da empresa</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('tasks','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_task_recurring')); ?>">Recorrência de tarefas</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('calendar','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_calendar')); ?>">Calendário da empresa</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('company-ai','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_ai')); ?>">Assistente privado</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource('pusher','view')&&CompanySettingsRuntime::canResource('tasks','view')&&CompanySettingsRuntime::canResource('projects','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_pusher')); ?>">Avisos privados</a><?php }?>
<?php if($companyLayoutHasSettings&&CompanySettingsRuntime::can('view')&&CompanySettingsRuntime::canResource('connect-api-connector','send')&&CompanySettingsRuntime::canResource('tickets','view')&&CompanySettingsRuntime::canResource('tickets','edit')&&CompanySettingsRuntime::canResource('customers','view')){?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_ticket_connect')); ?>">Avisos de chamados</a><?php }?>
<?php if ($companyLayoutHasSettings && CompanySettingsRuntime::can('view')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'settings?group=company')); ?>">Definições</a><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_assets')); ?>">Logotipo e assinatura</a><?php } ?>
<?php if ($companyLayoutHasSettings && CompanySettingsRuntime::canResource('email_templates','view')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'emails')); ?>">Modelos de e-mail</a><?php } ?>
<?php if ($companyLayoutHasSettings && CompanySettingsRuntime::canResource('customers','view')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_clients')); ?>">Clientes da empresa</a><?php } ?>
<?php foreach([['credit_notes','company_credits','Créditos da empresa'],['projects','company_projects','Projetos da empresa'],['tasks','company_standalone_tasks','Tarefas avulsas'],['invoices','company_task_billing','Faturamento de tarefas'],['invoices','company_standalone_billing','Faturamento de tarefas avulsas'],['invoices','company_time_billing','Faturamento de horas em lote'],['invoices','company_time_batches','Horas entre projetos e avulsas'],['projects','company_project_billing_rates','Taxa horária de projetos'],['invoices','company_project_fixed_billing','Preço fixo de projeto concluído'],['expenses','company_expenses','Despesas da empresa'],['expenses','company_imports','Importação e exportação de despesas'],['customers','company_client_imports','Importação e exportação de clientes privados'],['contracts','company_contracts','Contratos da empresa'],['tickets','company_tickets','Atendimento da empresa'],['estimates','company_sales?kind=estimates','Orçamentos da empresa'],['proposals','company_sales?kind=proposals','Propostas da empresa'],['invoices','company_invoice_drafts','Rascunhos privados de faturas'],['invoices','company_recurring','Recorrências da empresa'],['reports','company_reports','Relatórios da empresa'],['settings','company_asaas','Asaas da empresa'],['company_api','company_api_tokens','Credenciais da API']] as [$feature,$route,$label]){if($companyLayoutHasSettings&&CompanySettingsRuntime::canResource($feature,$feature==='company_api'?'manage':'view')){ ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,$route)); ?>"><?= $companyLayoutEscape($label); ?></a><?php }} ?>
<?php if ($companyLayoutHasSettings && CompanySettingsRuntime::canResource('invoices','view')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'company_invoices')); ?>">Faturas da empresa</a><?php } ?>
</nav><div id="wrapper"><main class="content">
