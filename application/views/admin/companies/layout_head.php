<?php defined('BASEPATH') or exit('No direct script access allowed');
// Native public assets and structural containers, without hydrating legacy financial menus/hooks.
require_once APPPATH.'helpers/app_branding_helper.php';
$companyLayoutEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$companyLayoutContext = CompanyRequestScope::context();
$companyLayoutPrimary = $companyLayoutContext && $companyLayoutContext->isPrimary();
$companyLayoutVersion = defined('ARGWS_VERSION') ? ARGWS_VERSION : 'core';
?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $companyLayoutEscape(crm_app_page_title($company_page_title ?? 'Empresas')); ?></title>
<link rel="stylesheet" href="<?= $companyLayoutEscape(base_url('assets/plugins/bootstrap/css/bootstrap.min.css')); ?>">
<link rel="stylesheet" href="<?= $companyLayoutEscape(base_url('assets/css/style.min.css').'?v='.$companyLayoutVersion); ?>">
<style>
body.company-context-shell{background:#f3f4f6;margin:0;color:#334155}
.company-context-shell #header{display:flex;align-items:center;justify-content:space-between;padding:0 16px;background:#fff;min-height:64px;height:auto;position:relative}
.company-context-shell .installation-name{font-size:22px;font-weight:600;color:#334155;white-space:nowrap}
.company-context-shell #menu{position:absolute;top:65px;bottom:0;width:230px;background:#fff;padding:20px 12px}
.company-context-shell #menu a{display:block;padding:12px;border-radius:6px;color:#334155;text-decoration:none}
.company-context-shell #menu a:focus,.company-context-shell #menu a:hover{background:#e2e8f0}
.company-context-shell #menu .company-dashboard{color:#16a34a}
.company-context-shell #wrapper{margin-left:230px;min-height:calc(100vh - 65px);padding:20px;background:#f3f4f6}
.company-context-shell #wrapper .content{padding:0;max-width:1150px;margin:auto}
.company-context-shell .company-selector-form{flex-wrap:wrap}
.company-context-shell .company-selector select,.company-context-shell .company-selector button{font:inherit;padding:6px}
.company-settings-card{background:#fff;border-radius:8px;padding:24px}
.company-settings-nav{display:flex;gap:16px;margin:20px 0;flex-wrap:wrap}
.company-settings-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:16px}
.company-settings-grid .form-group{margin:0}.company-settings-actions{margin-top:24px}fieldset[disabled]{opacity:.8}
@media(max-width:767px){.company-context-shell #header{align-items:flex-start;flex-direction:column;padding:12px}.company-context-shell #menu{position:static;width:auto;padding:8px 12px;display:flex;gap:6px;flex-wrap:wrap}.company-context-shell #menu a{padding:8px}.company-context-shell #wrapper{margin-left:0;padding:12px}.company-settings-card{padding:16px}}
</style>
<?php require APPPATH.'views/admin/includes/company_context_script.php'; ?>
</head><body class="admin company-context-shell">
<header id="header"><span class="installation-name"><?= $companyLayoutEscape(crm_app_display_name()); ?></span>
<?php require APPPATH.'views/admin/includes/company_selector.php'; ?>
</header>
<nav id="menu" aria-label="Menu do CRM">
<?php if ($companyLayoutPrimary) { ?><a class="company-dashboard" href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this)); ?>">Painel</a><?php } ?>
<a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'issuers')); ?>">Empresas</a>
<?php if (CompanySettingsRuntime::active() && CompanySettingsRuntime::can('view')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'settings?group=company')); ?>">Definições</a><?php } ?>
<?php if (CompanySettingsRuntime::active() && CompanySettingsRuntime::canResource('email_templates','view')) { ?><a href="<?= $companyLayoutEscape(CompanyRequestScope::adminUrl($this,'emails')); ?>">Modelos de e-mail</a><?php } ?>
</nav><div id="wrapper"><main class="content">
