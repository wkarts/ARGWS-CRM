<?php defined('BASEPATH') or exit('No direct script access allowed');
// Native public assets and structural containers, without hydrating legacy financial menus/hooks.
require_once APPPATH.'helpers/app_branding_helper.php';
$companyLayoutEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$companyLayoutContext = CompanyRequestScope::context();
$companyLayoutPrimary = $companyLayoutContext && $companyLayoutContext->isPrimary();
$companyLayoutVersion = defined('ARGWS_VERSION') ? ARGWS_VERSION : 'core';
$companyLayoutHasSettings = class_exists('CompanySettingsRuntime',false) && CompanySettingsRuntime::active();

// Presentation projection only: no staff model, module bootstrap or legacy menu hooks.
$companyLayoutStaff = ['firstname' => '', 'lastname' => '', 'email' => ''];
if ($companyLayoutContext) {
    $companyLayoutStaffFields = array_values(array_filter(array_keys($companyLayoutStaff), fn($field) => IssuerStorage::fieldExists($this->db, $field, db_prefix().'staff')));
    if ($companyLayoutStaffFields) {
        $companyLayoutStaff = array_replace($companyLayoutStaff, IssuerStorage::query($this->db, 'SELECT '.implode(',', $companyLayoutStaffFields).' FROM '.db_prefix().'staff WHERE staffid=? AND active=1', [$companyLayoutContext->staffId()])->row_array() ?: []);
    }
}
$companyLayoutName = trim($companyLayoutStaff['firstname'].' '.$companyLayoutStaff['lastname']) ?: 'Minha conta';
$companyLayoutCan = static fn($feature, $capability = 'view') => $companyLayoutHasSettings && CompanySettingsRuntime::canResource($feature, $capability);
$companyLayoutMenus = [
    'Clientes' => ['fa-users', [['customers','company_clients','Clientes'],['customers','company_client_imports','Importar / exportar clientes']]],
    'Leads' => ['fa-bullseye', [['leads','company_leads','Leads']]],
    'Vendas' => ['fa-file-invoice-dollar', [['invoices','company_invoices','Faturas'],['invoices','company_invoice_drafts','Rascunhos'],['estimates','company_sales?kind=estimates','Orçamentos'],['proposals','company_sales?kind=proposals','Propostas'],['credit_notes','company_credits','Notas de crédito'],['invoices','company_recurring','Recorrências']]],
    'Despesas' => ['fa-money-bill-transfer', [['expenses','company_expenses','Despesas'],['expenses','company_imports','Importar / exportar despesas']]],
    'Contratos' => ['fa-file-signature', [['contracts','company_contracts','Contratos']]],
    'Projetos' => ['fa-diagram-project', [['projects','company_projects','Projetos'],['projects','company_project_billing_rates','Taxas horárias'],['invoices','company_project_fixed_billing','Faturamento por preço fixo']]],
    'Tarefas' => ['fa-tasks', [['tasks','company_standalone_tasks','Tarefas avulsas'],['tasks','company_task_recurring','Recorrência de tarefas']]],
    'Faturamento' => ['fa-clock', [['invoices','company_task_billing','Faturamento de tarefas'],['invoices','company_standalone_billing','Faturamento de tarefas avulsas'],['invoices','company_time_billing','Faturamento de horas em lote'],['invoices','company_time_batches','Horas entre projetos e avulsas']]],
    'Suporte' => ['fa-life-ring', [['tickets','company_tickets','Chamados']]],
    'Compras' => ['fa-cart-shopping', [['purchases','company_purchases','Compras']]],
    'Calendário' => ['fa-calendar', [['calendar','company_calendar','Calendário']]],
    'Relatórios' => ['fa-chart-line', [['reports','company_reports','Relatórios']]],
    'Equipe' => ['fa-user-group', [['staff','company_staff','Colaboradores'],['hr','company_hr','Recursos humanos'],[null,'company_hr/mine','Meu RH']]],
    'Definições' => ['fa-gear', [[null,'issuers','Empresas'],['settings','settings?group=company','Configurações'],['settings','company_assets','Logotipo e assinatura'],['settings','company_certificates','Certificado A1','edit'],['email_templates','emails','Modelos de e-mail'],['settings','company_asaas','Asaas'],['company_api','company_api_tokens','Credenciais da API','manage'],['company-ai','company_ai','Assistente privado']]],
];
if ($companyLayoutCan('pusher') && $companyLayoutCan('tasks') && $companyLayoutCan('projects')) {
    $companyLayoutMenus['Projetos'][1][] = [null,'company_pusher','Avisos privados'];
}
if ($companyLayoutHasSettings && CompanySettingsRuntime::can('view') && $companyLayoutCan('connect-api-connector','send') && $companyLayoutCan('tickets') && $companyLayoutCan('tickets','edit') && $companyLayoutCan('customers')) {
    $companyLayoutMenus['Suporte'][1][] = [null,'company_ticket_connect','Avisos de chamados'];
}
// MX_Loader exposes controller services through __get, not __isset.
$companyLayoutRouter = $this->router;
$companyLayoutRoute = is_object($companyLayoutRouter) && method_exists($companyLayoutRouter, 'fetch_class') ? strtolower((string) $companyLayoutRouter->fetch_class()) : '';
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $companyLayoutEscape(crm_app_page_title($company_page_title ?? 'Empresas')); ?></title>
<?php // Explicit static assets only: never invoke the legacy admin hooks or hydration pipeline.
foreach (['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/plugins/bootstrap/css/bootstrap.min.css', 'assets/plugins/bootstrap-select/css/bootstrap-select.min.css', 'assets/plugins/font-awesome/css/all.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css', 'assets/css/company-workspace.css'] as $companyLayoutAsset) { ?>
<link rel="stylesheet" href="<?= $companyLayoutEscape(base_url($companyLayoutAsset).'?v='.$companyLayoutVersion); ?>">
<?php } ?>
<?php require APPPATH.'views/admin/includes/company_context_script.php'; ?>
</head><body class="admin company-context-shell">
<header id="header">
<button type="button" class="company-menu-toggle btn btn-default" aria-controls="menu" aria-expanded="true" aria-label="Alternar menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
<span class="installation-name"><?= $companyLayoutEscape(crm_app_display_name()); ?></span>
</header>
<aside id="menu" class="sidebar" aria-label="Menu do CRM">
<div class="company-sidebar-account">
<?php require APPPATH.'views/admin/includes/company_selector.php'; ?>
<div class="sidebar-user-profile company-user-profile">
<span class="company-user-avatar" aria-hidden="true"><i class="fa-regular fa-user"></i></span>
<span class="company-user-identity"><strong><?= $companyLayoutEscape($companyLayoutName); ?></strong><small><?= $companyLayoutEscape($companyLayoutStaff['email']); ?></small></span>
</div>
</div>
<ul class="nav metis-menu" id="side-menu">
<?php if ($companyLayoutPrimary) { ?><li><a class="company-dashboard" href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this)); ?>"><i class="fa-solid fa-house menu-icon" aria-hidden="true"></i><span class="menu-text">Painel</span></a></li><?php } ?>
<?php foreach ($companyLayoutMenus as $label => [$icon, $entries]) {
    $entries = array_values(array_filter($entries, static fn($entry) => $entry[0] === null || $companyLayoutCan($entry[0], $entry[3] ?? 'view')));
    if (!$entries) { continue; }
    $active = false;
    foreach ($entries as $entry) { if (explode('?', explode('/', $entry[1])[0])[0] === $companyLayoutRoute) { $active = true; } }
?>
<li class="company-menu-group<?= $active ? ' active' : ''; ?>">
<details <?= $active ? 'open' : ''; ?>><summary><i class="fa-solid <?= $companyLayoutEscape($icon); ?> menu-icon" aria-hidden="true"></i><span class="menu-text"><?= $companyLayoutEscape($label); ?></span><i class="fa-solid fa-angle-down company-menu-arrow" aria-hidden="true"></i></summary>
<ul class="nav nav-second-level">
<?php foreach ($entries as [, $route, $entryLabel]) { ?><li><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,$route)); ?>"><?= $companyLayoutEscape($entryLabel); ?></a></li><?php } ?>
</ul></details></li>
<?php } ?>
</ul>
</aside><div id="wrapper"><main class="content">
