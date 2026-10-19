<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Argws_provisioning extends CI_Controller
{
    public $installationMigrationResult;

    /** Called only inside the already-authorized standalone installation POST. */
    public function complete_installation()
    {
        if (!class_exists('InstallationMigrationBootstrap', false)
            || !InstallationMigrationBootstrap::isRunning()
            || !defined('ARGWS_INSTALLER_CREATED_STAFF_ID')
            || !InstallationMigrationBootstrap::consumeCompletionDispatchAuthorization()) {
            show_404();
            return;
        }
        $staff = $this->db->where('staffid', ARGWS_INSTALLER_CREATED_STAFF_ID)
            ->get(db_prefix() . 'staff')->row();
        if (!$staff || (int) $staff->active !== 1 || (int) $staff->admin !== 1) {
            $this->installationMigrationResult = ['success' => false];
            return;
        }
        $this->installationMigrationResult = $this->app->apply_pending_migrations_for_installation();
        if (!empty($this->installationMigrationResult['success'])) {
            $this->installationMigrationResult = $this->bindCreatedAdministrator(
                ARGWS_INSTALLER_CREATED_STAFF_ID, $this->installationMigrationResult);
        }

    }

    public function apply_migrations()
    {
        $bridgeToken = getenv('ARGWS_SETUP_MIGRATION_TOKEN');
        if (!is_cli()) {
            show_404();
            return;
        }

        if (!is_string($bridgeToken) || preg_match('/\A[a-f0-9]{64}\z/i', $bridgeToken) !== 1) {
            fwrite(STDERR, "Token interno de migrations ausente ou inválido." . PHP_EOL);
            exit(78);
        }

        $result = $this->app->apply_pending_migrations_for_provisioning();
        $createdStaffId = getenv('ARGWS_INSTALLER_CREATED_STAFF_ID');
        if (!empty($result['success']) && is_string($createdStaffId) && $createdStaffId !== '0' && $createdStaffId !== '') {
            $result = $this->bindCreatedAdministrator($createdStaffId, $result);
        }
        fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);

        if (empty($result['success'])) {
            exit(1);
        }
    }

    private function bindCreatedAdministrator($staffId, array $result): array
    {
        try {
            require_once APPPATH . 'services/issuers/IssuerCore.php';
            IssuerCore::bindInstallationMaster($this->db, db_prefix(), $staffId);
            return $result;
        } catch (Throwable $error) {
            log_message('error', 'Falha na identidade da instalação: ' . get_class($error));
            return ['success' => false, 'message' => 'Não foi possível confirmar o administrador principal da instalação.'];
        }
    }
}
