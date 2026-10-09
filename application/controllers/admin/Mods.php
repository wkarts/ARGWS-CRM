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
        $module = $this->app_modules->get($name);
        if ($this->input->method(true) !== 'POST' || !$this->get_resource_definition($name)
            || !$module || (int) $module['activated'] !== 1) {
            show_404();
        }

        $result = $this->app_modules->upgrade_database($name);

        // Possible error
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
