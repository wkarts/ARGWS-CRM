<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (class_exists('CompanyRequestScope', false) && ($companyContext = CompanyRequestScope::context())) {
    $companies = $companyContext->available();
    $selectedCompanyId = $companyContext->issuerId();
    $primaryId = (new IssuerResourceAccess($this->db, db_prefix()))->primaryId();
?>
<div class="company-selector" style="display:flex;align-items:center;gap:.5rem;padding:.5rem" aria-label="Empresa atual">
    <?= form_open(CompanyRequestScope::adminUrl($this, 'company_context/select'), ['class' => 'company-selector-form', 'style' => 'display:flex;align-items:center;gap:.5rem;margin:0']); ?>
    <label for="company-context-select">Empresa</label>
    <select id="company-context-select" name="company_id" style="max-width:230px" required aria-label="Selecionar empresa">
        <?php if ($selectedCompanyId === null) { ?><option value="">Selecione uma empresa</option><?php } ?>
        <?php foreach ($companies as $company) { ?>
        <option value="<?= e($company['id']); ?>" <?= (int) $company['id'] === $selectedCompanyId ? 'selected' : ''; ?>><?= e($company['legal_name']); ?><?= (int) $company['id'] === $primaryId ? ' (principal)' : ''; ?></option>
        <?php } ?>
    </select>
    <input type="hidden" name="_company_switch_csrf" value="<?= e($companyContext->csrf()); ?>">
    <button type="submit" <?= !$companies ? 'disabled' : ''; ?>>Trocar</button>
    <?= form_close(); ?>
</div>
<?php } ?>
