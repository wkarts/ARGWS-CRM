<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Mods extends AdminController
{
    public function __construct()
    {
        parent::__construct();

        /**
         * Modules are only accessible by administrators
         */
        if (!is_admin()) {
            redirect(admin_url());
        }
    }

    public function index()
    {
        $this->config->load('argws_resources');
        $definitions = $this->config->item('argws_resources');
        $data['resources'] = [];
        $maintenance = $this->app_modules->database_maintenance_inventory();
        // Visibility is not activation or authorization. Show every available module,
        // including those absent from the commercial catalog or hidden by environment.
        foreach ($this->app_modules->get() as $module) {
            $name = $module['system_name'];
            $definition = $definitions[$name] ?? [
                'name' => $module['headers']['module_name'] ?? $name,
                'description' => $module['headers']['description'] ?? '',
                'requires' => [],
                'optional' => [],
            ];
            $resource = array_merge($module, $definition);
            $resource['can_manage_availability'] = (bool) $this->get_resource_definition($name);
            $resource['maintenance_pending'] = isset($maintenance[$name]);
            $resource['can_deactivate'] = (int) $module['activated'] === 1 && (bool) $this->get_resource_definition($name, true);
            $resource['availability_reason'] = $name === 'ideal' && !\app\services\PaymentGatewayCatalog::idealEnabled()
                ? 'Disponibilidade restrita pela configuração CRM_ENABLE_IDEAL.'
                : 'Recurso fora do catálogo de ativação; disponibilidade operacional preservada.';
            $data['resources'][] = $resource;
        }
        $data['maintenance_count'] = count($maintenance);
        $data['title'] = 'Recursos';
        $this->load->view('admin/modules/list', $data);
    }

    public function activate($name)
    {
        if ($this->input->method(true) !== 'POST') {
            show_404();
        }

        $definition = $this->get_resource_definition($name);
        if (!$definition || !$this->app_modules->get($name)) {
            show_404();
        }

        foreach ($definition['requires'] as $dependency) {
            $dependencyModule = $this->app_modules->get($dependency);
            if (!$dependencyModule || (int) $dependencyModule['activated'] !== 1) {
                set_alert('warning', 'Ative primeiro o recurso dependente: ' . $this->resource_name($dependency) . '.');
                $this->to_modules();
                return;
            }
        }

        try {
            if ($this->app_modules->activate($name)) {
                set_alert('success', 'Recurso ativado. Os dados existentes foram preservados.');
            } else {
                set_alert('danger', 'Não foi possível ativar o recurso. Verifique os logs da aplicação.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao ativar ' . $name . ': ' . get_class($e) . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
            set_alert('danger', 'A ativação falhou. Verifique as dependências do recurso e os logs.');
        }
        $this->to_modules();
    }

    public function deactivate($name)
    {
        if ($this->input->method(true) !== 'POST') {
            show_404();
        }

        $definition = $this->get_resource_definition($name, true);
        if (!$definition || !$this->app_modules->get($name)) {
            show_404();
        }

        if ($this->app_modules->is_essential($name)) {
            show_error('Emitentes é um recurso essencial e não pode ser desativado.', 409);
            return;
        }

        $this->config->load('argws_resources');
        foreach ($this->config->item('argws_resources') as $dependentName => $dependent) {
            if (in_array($name, $dependent['requires'], true)) {
                $dependentModule = $this->app_modules->get($dependentName);
                if ($dependentModule && (int) $dependentModule['activated'] === 1) {
                    set_alert('warning', 'Desative primeiro o recurso dependente: ' . $dependent['name'] . '.');
                    $this->to_modules();
                    return;
                }
            }
        }

        try {
            if ($this->app_modules->deactivate($name)) {
                set_alert('success', 'Recurso desativado. Os dados existentes foram preservados.');
            } else {
                set_alert('danger', 'Não foi possível desativar o recurso.');
            }
        } catch (\Throwable $e) {
            log_message('error', 'Falha ao desativar ' . $name . ': ' . get_class($e) . ' em ' . basename($e->getFile()) . ':' . $e->getLine());
            set_alert('danger', 'Não foi possível desativar o recurso. Consulte os logs.');
        }
        $this->to_modules();
    }

    public function uninstall($name)
    {
        show_404();
    }

    public function upload()
    {
        show_404();
    }

    public function maintenance()
    {
        if (!is_admin()) { access_denied('modules'); return; }
        $token = $this->session->userdata('module_migration_csrf');
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            $token = bin2hex(random_bytes(32));
            $this->session->set_userdata('module_migration_csrf', $token);
        }
        $this->load->view('admin/modules/maintenance', [
            'title' => 'Manutenção de recursos instalados',
            'pending' => $this->app_modules->database_maintenance_inventory(),
            'module_migration_csrf' => $token,
        ]);
    }

    public function upgrade_database($name)
    {
        if (!is_admin()) { access_denied('modules'); return; }
        $module = $this->app_modules->get($name);
        if ($this->input->method(true) !== 'POST' || !$module || $name === 'issuers'
            || !preg_match('/^[a-z0-9_]+$/D', (string) $name) || $module['installed_version'] === false) {
            show_404(); return;
        }
        $expected = $this->session->userdata('module_migration_csrf');
        $provided = $this->input->post('module_migration_csrf');
        if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D', $expected)
            || !is_string($provided) || !hash_equals($expected, $provided)) {
            show_error('Solicitação inválida. Atualize a página e tente novamente.', 403); return;
        }
        // DDL can commit implicitly: use a connection lock rather than a misleading transaction.
        $key = 'module-upgrade:' . substr(hash('sha256', ($this->db->database ?? '') . ':' . db_prefix() . ':' . $name), 0, 48);
        $locked = false;
        try {
            $lock = $this->db->query('SELECT GET_LOCK(?, 0) AS acquired', [$key]);
            if (!$lock || (int) $lock->row()->acquired !== 1) {
                throw new RuntimeException('module_migration_busy');
            }
            $locked = true;
            $row = $this->db->where('module_name', $name)->get(db_prefix() . 'modules')->row();
            $target = (string) $module['headers']['version'];
            if (!$row || !preg_match('/^[0-9]\.[0-9]\.[0-9]$/D', (string) $row->installed_version)
                || !preg_match('/^[0-9]\.[0-9]\.[0-9]$/D', $target)
                || version_compare($row->installed_version, $target, '>')) {
                throw new RuntimeException('module_migration_version_invalid');
            }
            // Validate every pending native step before any migration runs. The legacy runner
            // checks internal gaps but does not reject a missing first pending step.
            foreach (App_modules::maintenance_native_steps($name, $row->installed_version, $target) as $step) {
                $files = glob(APP_MODULES_PATH . $name . '/migrations/' . sprintf('%03d', $step) . '_*.php');
                if (!$files || count($files) !== 1 || !preg_match('/^\d{3}_(\w+)$/', basename($files[0], '.php'))) {
                    throw new RuntimeException('module_migration_sequence_invalid');
                }
            }
            if (App_modules::maintenance_missing_dependencies($name, $row->installed_version, $target)) {
                throw new RuntimeException('module_migration_runtime_unavailable');
            }
            $active = (int) $row->active;
            $result = $this->app_modules->upgrade_database($name);
            $after = $this->db->where('module_name', $name)->get(db_prefix() . 'modules')->row();
            if ($result !== true || !$after || (string) $after->installed_version !== $target
                || (int) $after->active !== $active) {
                throw new RuntimeException('module_migration_unconfirmed');
            }
            set_alert('success', 'Banco de dados do recurso atualizado e versão confirmada.');
        } catch (Throwable $error) {
            $reason = preg_match('/^module_migration_[a-z_]+$/D', $error->getMessage()) ? $error->getMessage() : get_class($error);
            log_message('error', 'Module maintenance failed: ' . $name . ' [' . $reason . ']');
            set_alert('danger', 'Atualização não confirmada. Confira as versões, arquivos e logs antes de tentar novamente. Não ative o recurso para corrigir esta pendência.');
        } finally {
            if ($locked) { $this->db->query('SELECT RELEASE_LOCK(?)', [$key]); }
        }
        redirect(admin_url('modules/maintenance'));
    }

    public function update_version($name)
    {
        show_404();
    }

    private function get_resource_definition($name, $forDeactivation = false)
    {
        if (!$forDeactivation && $name === 'ideal' && !\app\services\PaymentGatewayCatalog::idealEnabled()) {
            return false;
        }
        $this->config->load('argws_resources');
        $definitions = $this->config->item('argws_resources');
        return $definitions[$name] ?? false;
    }

    private function resource_name($name)
    {
        $definition = $this->get_resource_definition($name);
        return $definition ? $definition['name'] : $name;
    }

    private function to_modules()
    {
        redirect(admin_url('modules'));
    }
}
