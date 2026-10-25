<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (class_exists('CompanyRequestScope', false) && ($companyContext = CompanyRequestScope::context())) {
    $companies = $companyContext->available();
    $selectedCompanyId = $companyContext->issuerId();
    $primaryId = (new IssuerResourceAccess($this->db, db_prefix()))->primaryId();
    require_once APPPATH . 'services/CompanySettingsIdentity.php';
    $companyDisplayIdentity = new CompanySettingsIdentity($this->db, db_prefix());
    $companySelectorNames = [];
    foreach ($companies as $company) {
        $companyDisplayName = (string) $company['legal_name'];
        // Project only the already-authorized catalog; an empty profile keeps its canonical name.
        if (method_exists($companyDisplayIdentity, 'displayName')) {
            try {
                $displayName = $companyDisplayIdentity->displayName($company['id']);
                if (trim($displayName) !== '') { $companyDisplayName = $displayName; }
            } catch (DomainException $exception) { /* A missing profile must not hide company selection. */ }
        }
        if (trim($companyDisplayName) === '') { $companyDisplayName = 'Empresa ' . (int) $company['id']; }
        $companySelectorNames[(int) $company['id']] = $companyDisplayName;
    }
    $companySelectorName = $companySelectorNames[(int) $selectedCompanyId] ?? 'Selecione uma empresa';
    $companySelectorDark = !empty($companySelectorSidebarDark);
    $companySelectorVersion = defined('ARGWS_VERSION') ? ARGWS_VERSION : 'core';
?>
<link rel="stylesheet" href="<?= e(base_url('assets/css/company-selector.css') . '?v=' . $companySelectorVersion); ?>">
<div class="company-selector dropdown sidebar-user-profile" data-company-current="<?= e($selectedCompanyId ?? ''); ?>" aria-label="Empresa atual">
    <?= form_open(CompanyRequestScope::adminUrl($this, 'company_selection/select'), ['class' => 'company-selector-form']); ?>
    <button type="button" id="company-selector-toggle" data-company-selector-toggle data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-controls="company-selector-menu" <?= !$companies ? 'disabled' : ''; ?>
        class="dropdown-toggle profile -tw-mt-1 tw-font-medium tw-border tw-border-solid tw-rounded-lg tw-bg-white tw-py-2 tw-px-2.5 tw-block tw-shadow-xs <?= $companySelectorDark ? 'tw-text-white tw-border-white/10 hover:tw-border-white/30 focus:tw-border-white/30 hover:tw-text-white focus:tw-text-white hover:tw-bg-neutral-900/10 focus:tw-bg-neutral-900/10' : 'tw-border-neutral-300 tw-text-neutral-700 hover:tw-text-neutral-800 focus:tw-text-neutral-800 hover:tw-bg-neutral-900/5 focus:tw-bg-neutral-900/5'; ?>">
        <span class="company-selector-card tw-inline-flex tw-items-center tw-gap-x-3 tw-pt-0.5">
            <span class="company-selector-avatar tw-bg-neutral-100 tw-text-neutral-600 tw-rounded-full" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 21V5h10v16M14 11h6v10M2 21h20M7 8h1m3 0h1M7 12h1m3 0h1M7 16h1m3 0h1M17 14h1m-1 3h1"/></svg></span>
            <span class="company-selector-identity"><span class="tw-truncate tw-block tw-font-semibold" data-company-selected-name data-company-current-label title="<?= e($companySelectorName); ?>"><?= e($companySelectorName); ?></span><span class="tw-font-normal tw-truncate tw-block tw-text-sm <?= $companySelectorDark ? 'tw-text-neutral-300' : 'tw-text-neutral-500'; ?>">Empresa atual</span></span>
            <span class="caret" aria-hidden="true"></span>
        </span>
    </button>
    <input type="hidden" name="_company_switch_csrf" value="<?= e($companyContext->csrf()); ?>">
    <ul id="company-selector-menu" data-company-selector-menu class="dropdown-menu tw-w-full" role="menu" aria-labelledby="company-selector-toggle">
        <?php foreach ($companies as $company) { $companySelectorId = (int) $company['id']; ?>
        <li role="presentation"><button type="submit" name="company_id" value="<?= e($companySelectorId); ?>" data-company-choice="<?= e($companySelectorId); ?>" role="menuitem" <?= $companySelectorId === (int) $selectedCompanyId ? 'aria-current="true"' : ''; ?>><?= e($companySelectorNames[$companySelectorId]); ?><?= $companySelectorId === (int) $primaryId ? ' (principal)' : ''; ?></button></li>
        <?php } ?>
    </ul>
    <?= form_close(); ?>
</div>
<script src="<?= e(base_url('assets/js/company-selector.js') . '?v=' . $companySelectorVersion); ?>" defer></script>
<?php } ?>
