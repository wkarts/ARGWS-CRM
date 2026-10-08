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

        foreach ($this->app_modules->get() as $module) {
            $installed[$module['system_name']] = $module;
        }

        $data['resources'] = [];
        foreach ($definitions as $name => $definition) {
            if (isset($installed[$name])) {
                // Preserve ARGWS catalog wording over legacy module headers.
                $data['resources'][] = array_merge($installed[$name], $definition);
            }
        }
        $data['title'] = 'Recursos ARGWS';
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

        $this->app_modules->activate($name);
        set_alert('success', 'Recurso ARGWS ativado. Os dados existentes foram preservados.');
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

        $this->app_modules->deactivate($name);
        set_alert('success', 'Recurso ARGWS desativado. Os dados existentes foram preservados.');
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
