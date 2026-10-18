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
        $installed   = [];
        $hidden      = $this->hidden_resources();

        foreach ($this->app_modules->get() as $module) {
            $installed[$module['system_name']] = $module;
        }

        $data['resources'] = [];
        foreach ($definitions as $name => $definition) {
            if (isset($installed[$name]) && !isset($hidden[$name])) {
                // Os nomes funcionais do catálogo prevalecem sobre os cabeçalhos legados.
                $data['resources'][] = array_merge($installed[$name], $definition);
            }
        }
        $data['ideal_maintenance'] = null;
        $data['ideal_migration_csrf'] = '';
        if (isset($installed['ideal']) && $installed['ideal']['installed_version'] !== false) {
            $token = $this->session->userdata('ideal_module_migration_csrf');
            if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
                $token = bin2hex(random_bytes(32));
                $this->session->set_userdata('ideal_module_migration_csrf', $token);
            }
            $data['ideal_migration_csrf'] = $token;
            if (isset($hidden['ideal']) && version_compare($installed['ideal']['installed_version'], $installed['ideal']['headers']['version'], '<')) {
                // Maintenance is separate from activation, settings and checkout availability.
                $data['ideal_maintenance'] = $installed['ideal'];
            }
        }
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

        $definition = $this->get_resource_definition($name);
        if (!$definition || !$this->app_modules->get($name)) {
            show_404();
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

    public function upgrade_database($name)
    {
        if (!is_admin()) {
            access_denied('modules');
            return;
        }
        $module = $this->app_modules->get($name);
        $idealMaintenance = $name === 'ideal' && $module && $module['installed_version'] !== false;
        if ($this->input->method(true) !== 'POST'
            || (!$idealMaintenance && (!$this->get_resource_definition($name) || !$module || (int) $module['activated'] !== 1))) {
            show_404();
            return;
        }
        if ($idealMaintenance) {
            $expected = $this->session->userdata('ideal_module_migration_csrf');
            $provided = $this->input->post('ideal_module_migration_csrf');
            if (!is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D', $expected)
                || !is_string($provided) || !hash_equals($expected, $provided)) {
                show_error('Solicitação inválida. Atualize a página e tente novamente.', 403);
                return;
            }
            $current = $this->db->where('module_name', 'ideal')->get(db_prefix() . 'modules')->row();
            if (!$current || version_compare((string) $current->installed_version, $module['headers']['version'], '>')) {
                show_error('A versão instalada exige revisão; downgrade não é permitido nesta manutenção.', 409);
                return;
            }
        }
        try {
            $result = $this->app_modules->upgrade_database($name);
            if ($idealMaintenance) {
                // Native migration versions must be confirmed in storage, not inferred from its return value.
                $row = $this->db->where('module_name', 'ideal')->get(db_prefix() . 'modules')->row();
                if ($result !== true || !$row || $row->installed_version !== $module['headers']['version']) {
                    throw new RuntimeException('ideal_migration_version_unconfirmed');
                }
            }
        } catch (Throwable $error) {
            if (!$idealMaintenance) { throw $error; }
            $result = 'Atualização de versão do iDEAL não confirmada. Confira o histórico antes de tentar novamente.';
        }
        if (is_string($result)) {
            set_alert('danger', $result);
        } else {
            set_alert('success', 'Banco de dados do recurso atualizado.');
        }
        $this->to_modules();
    }

    public function update_version($name)
    {
        show_404();
    }

    /**
     * CRM_HIDDEN_RESOURCES oculta entradas do painel sem desinstalar ou
     * desativar funcionalidades. Não é uma restrição de autorização.
     * Exemplo: CRM_HIDDEN_RESOURCES=wiki,zillapage,products
     */
    private function hidden_resources()
    {
        $value = strtolower((string) (getenv('CRM_HIDDEN_RESOURCES') ?: ''));
        $hidden = [];
        if (!\app\services\PaymentGatewayCatalog::idealEnabled()) {
            $hidden['ideal'] = true;
        }
        foreach (explode(',', $value) as $name) {
            $name = trim($name);
            if ($name !== '' && preg_match('/^[a-z0-9_]+$/', $name)) {
                $hidden[$name] = true;
            }
        }
        return $hidden;
    }

    private function get_resource_definition($name)
    {
        if ($name === 'ideal' && !\app\services\PaymentGatewayCatalog::idealEnabled()) {
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
