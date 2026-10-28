<?php defined('BASEPATH') or exit('No direct script access allowed');
// Native public assets and structural containers, without hydrating legacy financial menus/hooks.
require_once APPPATH.'helpers/app_branding_helper.php';
require_once APPPATH.'services/CompanyShellPresentation.php';
$companyLayoutTheme = CompanyShellPresentation::read($this->db, db_prefix());
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
    'Clientes' => ['fa-regular fa-user', [['customers','clients','Clientes'],['customers','clients/import','Importar / exportar clientes']]],
    'Vendas' => ['fa-solid fa-bolt', [['invoices','invoices','Faturas'],['invoices','drafts','Rascunhos'],['estimates','estimates','Orçamentos'],['proposals','proposals','Propostas'],['credit_notes','list_credit_notes','Notas de crédito'],['invoices','recurring','Recorrências']]],
    'Despesas' => ['fa-regular fa-file-lines', [['expenses','expenses','Despesas'],['settings','settings/imports','Importar / exportar despesas']]],
    'Contratos' => ['fa-regular fa-note-sticky', [['contracts','contracts','Contratos']]],
    'Projetos' => ['fa-solid fa-chart-gantt', [['projects','projects','Projetos'],['projects','projects/billing_rates','Taxas horárias'],['projects','projects/fixed_billing','Faturamento por preço fixo']]],
    'Tarefas' => ['fa-regular fa-circle-check', [['tasks','tasks/standalone','Tarefas avulsas'],['tasks','tasks/recurring','Recorrência de tarefas']]],
    'Faturamento' => ['fa-regular fa-clock', [['tasks','tasks/billing','Faturamento de tarefas'],['invoices','invoices/standalone_billing','Faturamento de tarefas avulsas'],['projects','projects/time_billing','Faturamento de horas em lote'],['tasks','tasks/time_batches','Horas entre projetos e avulsas']]],
    'Suporte' => ['fa-regular fa-life-ring', [['tickets','tickets','Chamados']]],
    'Leads' => ['fa-solid fa-crosshairs', [['leads','leads','Leads']]],
    'Compras' => ['fa-solid fa-cart-shopping', [['expenses','expenses/purchases','Compras']]],
    'Calendário' => ['fa-regular fa-calendar', [['calendar','utilities/calendar','Calendário']]],
    'Relatórios' => ['fa-solid fa-chart-line', [['reports','index','Relatórios']]],
    'Equipe' => ['fa-solid fa-user-group', [['staff','staff','Colaboradores'],['hr','staff/hr','Recursos humanos'],[null,'staff/hr_mine','Meu RH']]],
    'Definições' => ['fa fa-cog', [[null,'issuers','Empresas'],['settings','settings?group=company','Configurações'],['settings','settings?group=general','Logotipo e assinatura'],['settings','settings?group=certificates','Certificado A1','edit'],['email_templates','emails','Modelos de e-mail'],['settings','settings/asaas','Asaas'],['settings','settings/efi','Efí'],['company_api','settings/api_tokens','Credenciais da API','manage'],['company-ai','tickets/ai','Assistente privado']]],
];
if ($companyLayoutCan('pusher') && $companyLayoutCan('tasks') && $companyLayoutCan('projects')) {
    $companyLayoutMenus['Projetos'][1][] = [null,'settings/pusher','Avisos privados'];
}
if ($companyLayoutHasSettings && CompanySettingsRuntime::can('view') && $companyLayoutCan('connect-api-connector','send') && $companyLayoutCan('tickets') && $companyLayoutCan('tickets','edit') && $companyLayoutCan('customers')) {
    $companyLayoutMenus['Suporte'][1][] = [null,'tickets/connect','Avisos de chamados'];
}
// MX_Loader exposes controller services through __get, not __isset.
$companyLayoutRouter = $this->router;
$companyLayoutRoute = is_object($companyLayoutRouter) && method_exists($companyLayoutRouter, 'fetch_class') ? strtolower((string) $companyLayoutRouter->fetch_class()) : '';
$companyLayoutRouteGroup = $companyLayoutRoute === 'settings' ? 'Definições' : null;
// Prefer the first official group when a controller is intentionally linked from more than one group.
$companyLayoutActiveSeen = false;
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $companyLayoutEscape(crm_app_page_title($company_page_title ?? 'Empresas')); ?></title>
<?php // Explicit static assets only: never invoke the legacy admin hooks or hydration pipeline.
foreach (['assets/css/reset.min.css', 'assets/plugins/inter/inter.css', 'assets/builds/vendor-admin.css', 'assets/plugins/font-awesome/css/fontawesome.min.css', 'assets/plugins/font-awesome/css/brands.min.css', 'assets/plugins/font-awesome/css/solid.min.css', 'assets/plugins/font-awesome/css/regular.min.css', 'assets/builds/tailwind.css', 'assets/css/style.min.css', 'assets/css/company-shell.css', 'assets/css/company-workspace.css'] as $companyLayoutAsset) { ?>
<link rel="stylesheet" href="<?= $companyLayoutEscape(base_url($companyLayoutAsset).'?v='.$companyLayoutVersion); ?>">
<?php } ?>
<?php if ($companyLayoutTheme !== '') { ?><style data-company-native-theme><?= $companyLayoutTheme; ?></style><?php } ?>
<?php require APPPATH.'views/admin/includes/company_context_script.php'; ?>
</head><body class="admin company-context-shell">
<header id="header">
<button type="button" class="company-menu-toggle btn btn-default" aria-controls="menu" aria-expanded="true" aria-label="Alternar menu"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
<a class="logo company-installation-logo" href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this)); ?>"><img src="<?= $companyLayoutEscape(base_url('assets/images/argws/logo-dark.png')); ?>" alt="<?= $companyLayoutEscape(crm_app_display_name()); ?>"></a>
<div class="company-header-search"><i class="fa-solid fa-search" aria-hidden="true"></i><input type="search" data-company-menu-search placeholder="Procurar no menu..." aria-label="Procurar no menu desta empresa" autocomplete="off"></div>
<?php if ($companyLayoutCan('customers') || $companyLayoutCan('invoices') || $companyLayoutCan('tasks')) { ?>
<details class="company-quick-menu"><summary aria-label="Acessos rápidos da empresa" title="Acessos rápidos da empresa" class="tw-bg-primary-600 tw-text-white"><i class="fa-solid fa-plus" aria-hidden="true"></i></summary><ul>
<?php foreach ([['customers','clients','Clientes'],['invoices','invoices','Faturas'],['tasks','tasks','Tarefas']] as [$feature,$route,$label]) { if ($companyLayoutCan($feature)) { ?><li><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,$route)); ?>"><?= $companyLayoutEscape($label); ?></a></li><?php } } ?>
</ul></details>
<?php } ?>
<nav class="company-header-actions" aria-label="Atalhos da empresa">
<?php if ($companyLayoutCan('settings')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this, 'settings?group=company')); ?>">Configurações</a><?php } ?>
<?php if ($companyLayoutCan('tasks')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this, 'tasks/standalone')); ?>" aria-label="Tarefas da empresa"><i class="fa-regular fa-square-check" aria-hidden="true"></i></a><?php } ?>
<?php if ($companyLayoutCan('calendar')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this, 'utilities/calendar')); ?>" aria-label="Calendário da empresa"><i class="fa-regular fa-calendar" aria-hidden="true"></i></a><?php } ?>
</nav>
</header>
<aside id="menu" class="sidebar" aria-label="Menu do CRM">
<div class="company-sidebar-account">
<?php require APPPATH.'views/admin/includes/company_selector.php'; ?>
<div class="sidebar-user-profile company-user-profile">
<img class="company-user-avatar staff-profile-image-small" src="<?= $companyLayoutEscape(base_url('assets/images/user-placeholder.jpg')); ?>" alt="">
<span class="company-user-identity"><strong><?= $companyLayoutEscape($companyLayoutName); ?></strong><small><?= $companyLayoutEscape($companyLayoutStaff['email']); ?></small></span>
</div>
</div>
<ul class="nav metis-menu" id="side-menu">
<?php if ($companyLayoutPrimary) { ?><li><a class="company-dashboard" href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this)); ?>"><i class="fa-regular fa-object-group menu-icon" aria-hidden="true"></i><span class="menu-text">Painel</span></a></li><?php } ?>
<?php foreach ($companyLayoutMenus as $label => [$icon, $entries]) {
    $entries = array_values(array_filter($entries, static fn($entry) => $entry[0] === null || $companyLayoutCan($entry[0], $entry[3] ?? 'view')));
    if (!$entries) { continue; }
    $active = false;
    if (!$companyLayoutActiveSeen) {
        $active = $companyLayoutRouteGroup === $label;
        if (!$active) {
            foreach ($entries as $entry) {
                if (explode('?', explode('/', $entry[1])[0])[0] === $companyLayoutRoute) { $active = true; break; }
            }
        }
        if ($active) { $companyLayoutActiveSeen = true; }
    }
?>
<li class="company-menu-group<?= $active ? ' active' : ''; ?>">
<details <?= $active ? 'open' : ''; ?>><summary><i class="<?= $companyLayoutEscape($icon); ?> menu-icon" aria-hidden="true"></i><span class="menu-text"><?= $companyLayoutEscape($label); ?></span><i class="fa-solid fa-angle-down company-menu-arrow" aria-hidden="true"></i></summary>
<ul class="nav nav-second-level">
<?php foreach ($entries as [, $route, $entryLabel]) { ?><li><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,$route)); ?>"><?= $companyLayoutEscape($entryLabel); ?></a></li><?php } ?>
</ul></details></li>
<?php } ?>
</ul>
</aside><div id="wrapper"><main class="content">
