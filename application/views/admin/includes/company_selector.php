<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (class_exists('CompanyRequestScope', false) && ($companyContext = CompanyRequestScope::context())) {
    $companies = $companyContext->available();
    $selectedCompanyId = $companyContext->issuerId();
    $primaryId = (new IssuerResourceAccess($this->db, db_prefix()))->primaryId();
    require_once APPPATH . 'services/CompanySettingsIdentity.php';
    $companyDisplayIdentity = new CompanySettingsIdentity($this->db, db_prefix());
?>
<div class="company-selector" style="display:flex;align-items:center;gap:.5rem;padding:.5rem" aria-label="Empresa atual">
    <?= form_open(CompanyRequestScope::adminUrl($this, 'company_context/select'), ['class' => 'company-selector-form', 'style' => 'display:flex;align-items:center;gap:.5rem;margin:0']); ?>
    <label for="company-context-select" class="sr-only">Selecionar empresa</label>
    <select id="company-context-select" name="company_id" class="selectpicker company-context-select" data-width="230px" data-container="body" data-none-selected-text="Selecione uma empresa" data-style="btn-default" required aria-label="Selecionar empresa">
        <?php if ($selectedCompanyId === null) { ?><option value="">Selecione uma empresa</option><?php } ?>
        <?php foreach ($companies as $company) {
            $companyDisplayName = (string) $company['legal_name'];
            // Only the already-authorized catalog is consulted. Older adapters retain the canonical name.
            if (method_exists($companyDisplayIdentity, 'displayName')) {
                try { $companyDisplayName = $companyDisplayIdentity->displayName($company['id']); }
                catch (DomainException $exception) { /* A temporarily unavailable profile must not hide company selection. */ }
            }
        ?>
        <option value="<?= e($company['id']); ?>" <?= (int) $company['id'] === $selectedCompanyId ? 'selected' : ''; ?>><?= e($companyDisplayName); ?><?= (int) $company['id'] === $primaryId ? ' (principal)' : ''; ?></option>
        <?php } ?>
    </select>
    <input type="hidden" name="_company_switch_csrf" value="<?= e($companyContext->csrf()); ?>">
    <button type="submit" class="btn btn-default" title="Confirmar empresa selecionada" aria-label="Confirmar empresa selecionada" <?= !$companies ? 'disabled' : ''; ?>>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m5 12 4 4L19 6" /></svg>
    </button>
    <?= form_close(); ?>
</div>
<?php } ?>
