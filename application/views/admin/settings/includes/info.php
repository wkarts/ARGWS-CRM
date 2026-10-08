<?php hooks()->do_action('before_system_info'); ?>
<h4 class="tw-my-0 tw-text-lg tw-font-medium">
    <a download="informacoes-sistema.xls" class="btn btn-default btn-sm tw-mr-2" href="#"
        onclick="return ExcellentExport.excel(this, 'system-info', 'Informações do sistema');">
        <i class="fa-regular fa-file-excel"></i>
    </a>
    Informações do sistema e do servidor
</h4>
<div class="table-responsive">
    <table class="table table-bordered" id="system-info">
        <thead>
            <tr>
                <th>Nome da variável</th>
                <th>Valor</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="bold">OS</td>
                <td>
                    <?php
                    echo PHP_OS;
                    ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Registros de sessão</td>
                <td>
                    <?php
                    $totalSessions = total_rows('tblsessions');
                    $class = $totalSessions <= 25_000 ? 'text-success' : 'text-warning';
                    ?>
                    <span class="<?= $class ?>"><?= number_format($totalSessions) ?></span>&nbsp;&nbsp;<a
                            class="text-danger" href="<?= admin_url('settings/clear_sessions') ?>">Encerrar sessões</a>&nbsp;&nbsp;(<span
                        >Se você encerrar as sessões, todos os usuários precisarão entrar novamente.</span>)
                </td>
            </tr>
            <tr>
                <td class="bold">Servidor web</td>
                <td>
                    <?php
                    echo isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Não disponível';
                    ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Usuário do servidor web</td>
                <td>
                    <?php
                    echo get_current_user();
                    ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Protocolo do servidor</td>
                <td>
                    <?php
                    echo isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'Não disponível';
                    ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Data de instalação</td>
                <td>
                    <?php
                    $date_installation = get_option('di');
                    echo !empty($date_installation) ? date('Y-m-d H:i:s', $date_installation) : 'Não disponível';
                    ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Versão do PHP</td>
                <td>
                    <?php
                    echo PHP_VERSION;
                    ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Extensão PHP "curl"</td>
                <td>
                    <?php
                    if (!extension_loaded('curl')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        $curlVersion = curl_version();
                        echo "<span class='text-success'>Ativado (versão: " . $curlVersion['version'] . ')</span>';
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Extensão PHP "openssl"</td>
                <td>
                    <?php
                    if (!extension_loaded('openssl')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        echo "<span class='text-success'>Ativado (versão: " . OPENSSL_VERSION_NUMBER . ')</span>';
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Extensão PHP "mbstring"</td>
                <td>
                    <?php
                    if (!extension_loaded('mbstring')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        echo "<span class='text-success'>Ativado</span>";
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Extensão PHP "iconv"</td>
                <td>
                    <?php
                    if (!extension_loaded('iconv') && !function_exists('iconv')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        echo "<span class='text-success'>Ativado</span>";
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Extensão PHP "IMAP"</td>
                <td>
                    <?php
                    if (!extension_loaded('imap')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        echo "<span class='text-success'>Ativado</span>";
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Extensão PHP "GD"</td>
                <td>
                    <?php
                    if (!extension_loaded('gd')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        echo "<span class='text-success'>Ativado</span>";
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Extensão PHP "zip"</td>
                <td>
                    <?php
                    if (!extension_loaded('zip')) {
                        echo "<span class='text-danger'>Não ativado</span>";
                    } else {
                        echo "<span class='text-success'>Ativado</span>";
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Versão do MySQL</td>
                <td>
                    <?php
                    echo $this->db->query('SELECT VERSION() as version')->row()->version;
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Máximo de conexões simultâneas do MySQL</td>
                <td>
                    <?php
                    echo $this->db->query("SHOW VARIABLES LIKE 'max_connections'")->row()->Value;
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">Tamanho máximo do pacote</td>
                <td>
                    <?php
                    echo bytesToSize('', $this->db->query("SHOW VARIABLES LIKE 'max_allowed_packet'")->row()->Value);
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">sql_mode</td>
                <td>
                    <?php
                    echo $this->db->query('SELECT @@sql_mode as mode')->row()->mode;
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">bcmath</td>
                <td>
                    <?php
                    echo extension_loaded('bcmath') ? 'Sim' : 'Não';
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">max_input_vars</td>
                <td>
                    <?php
                    $max_input_vars = ini_get('max_input_vars');
                    echo $max_input_vars ? $max_input_vars : 'Não disponível';
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">upload_max_filesize</td>
                <td>
                    <?php
                    $upload_max_filesize = ini_get('upload_max_filesize');
                    echo $upload_max_filesize ? $upload_max_filesize : 'Não disponível';
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">post_max_size</td>
                <td>
                    <?php
                    $post_max_size = ini_get('post_max_size');
                    echo $post_max_size ? $post_max_size : 'Não disponível';
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">max_execution_time</td>
                <td>
                    <?php
                    $execution_time = ini_get('max_execution_time');
                    echo $execution_time ? $execution_time : 'Não disponível';
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">memory_limit</td>
                <td>
                    <?php
                    $memory = ini_get('memory_limit');
                    echo $memory ? $memory : 'Não disponível';
                    if (floatval($memory) < 128 && floatval($memory) > -1) {
                        echo '<br /><span class="text-warning">O valor recomendado é 128 MB ou superior.</span>';
                    }
                    ?>
                </td>

            </tr>
            <tr>
                <td class="bold">allow_url_fopen</td>
                <td>
                    <?php
                    $url_f_open = ini_get('allow_url_fopen');
                    if ($url_f_open != '1'
                        && strcasecmp($url_f_open, 'On') != 0
                        && strcasecmp($url_f_open, 'true') != 0
                        && strcasecmp($url_f_open, 'yes') != 0) {
                        echo "<span class='bold'>allow_url_fopen não está ativado (valor: $url_f_open)</span>";
                    } else {
                        echo "<span class='text-success'>Ativado</span>";
                    }
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Suhosin</td>
                <td>
                    <?php
                if (!extension_loaded('suhosin')) {
                    echo 'Não utilizado';
                } else {
                    echo 'Carregado';
                }
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Ambiente</td>
                <td>
                    <?php
                echo ENVIRONMENT;
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Cloudflare</td>
                <td>
                    <?php
                    $CloudFlareHeader = $this->input->get_request_header('Cf-Ray');
                    echo($CloudFlareHeader && !empty($CloudFlareHeader) ? 'Sim' : 'Não');
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Permissões de pipe.php</td>
                <td>
                    <?php
                    echo octal_permissions(fileperms(FCPATH . 'pipe.php'));
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Tema da área de clientes</td>
                <td>
                    <?php
                    $clientTheme = active_clients_theme();
                    echo html_escape($clientTheme === 'perfex' ? 'Padrão ARGWS' : $clientTheme);
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Temas disponíveis para clientes</td>
                <td>
                    <?php
                    $clientThemes = array_map(function ($theme) {
                        return $theme === 'perfex' ? 'Padrão ARGWS' : $theme;
                    }, get_all_client_themes());
                    echo implode(', ', array_map('html_escape', $clientThemes));
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Cron executado pelo terminal</td>
                <td>
                    <?php
                    echo get_option('cron_has_run_from_cli') == 0 ? 'Não' : 'Sim';
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Proteção CSRF ativada</td>
                <td>
                    <?php
                echo $this->config->item('csrf_protection') ? 'Sim' : 'Não';
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Bloqueio de agentes de usuário inválidos</td>
                <td>
                    <?php
                    echo defined('APP_BAD_USER_AGENT_BLOCK') && defined('APP_BAD_USER_AGENT_BLOCK') ? 'Sim' : 'Não';
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Uso de my_functions_helper.php</td>
                <td>
                    <?php
                    echo file_exists(APPPATH . 'helpers/my_functions_helper.php') ? 'Sim': 'Não';
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Uso de custom.css</td>
                <td>
                    <?php
                    echo file_exists(FCPATH . 'assets/css/custom.css') ? 'Sim': 'Não';
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Última execução do cron</td>
                <td>
                    <?php
                    echo !empty(get_option('last_cron_run')) ? time_ago_specific(date('Y-m-d H:i:s', get_option('last_cron_run'))) : 'Não disponível';
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Total de recursos</td>
                <td>
                    <?php
                    echo count($this->app_modules->get());
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Recursos ativos</td>
                <td>
                    <?php
                    echo count($this->app_modules->get_activated());
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Recursos com atualização de banco pendente</td>
                <td>
                    <?php
                    echo $this->app_modules->number_of_modules_that_require_database_upgrade();
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Caminho da instalação</td>
                <td>
                    <?php
                    echo FCPATH;
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Diretório temporário (get_temp_dir())</td>
                <td>
                    <?php
                    echo get_temp_dir();
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">URL base</td>
                <td>
                    <?php
                    echo APP_BASE_URL;
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold"><b>my_</b> Arquivos de view com prefixo personalizado</td>
                <td>
                    <?php
                    $my_prefixed_files = _get_my_prefixed_files();
                    if (count($my_prefixed_files) > 0) {
                        echo implode('<br />', $my_prefixed_files);
                    } else {
                        echo 'Nenhum arquivo personalizado em uso';
                    }
                ?>
                </td>
            </tr>
            <tr>
                <td class="bold">Permissões dos arquivos</td>
                <td>
                    <?php
                $permissionsIssues = false;
                if (!is_writable(FCPATH . 'uploads/estimates')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/estimates gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/proposals')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/proposals gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/ticket_attachments')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/ticket_attachments gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/tasks')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/tasks gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/staff_profile_images')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/staff_profile_images gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/projects')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/projects gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/newsfeed')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/newsfeed gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/leads')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/leads gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/invoices')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/invoices gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/expenses')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/expenses gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/discussions')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/discussions gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/contracts')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/contracts gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/company')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/company gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/clients')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/clients gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/credit_notes')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/credit_notes gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'uploads/client_profile_images')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne uploads/client_profile_images gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'application/config')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne application/config/ gravável) — permissões 0755</span><br />";
                }
                if (!is_writable(FCPATH . 'application/config/config.php')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne application/config/config.php gravável) — permissões 0644</span><br />";
                }
                if (!is_writable(FCPATH . 'application/config/app-config.php')) {
                    $permissionsIssues = true;
                    echo "<span class='text-danger'>Não (torne application/config/app-config.php gravável) — permissões 0644</span><br />";
                }
                if (!is_dir(TEMP_FOLDER)) {
                    $permissionsIssues = true;
                    echo '<span class="text-danger">A pasta temporária não existe. Crie a pasta indicada abaixo. (<b>' . TEMP_FOLDER . '</b>)</span><br />';
                } else {
                    if (!is_writable(FCPATH . 'temp')) {
                        $permissionsIssues = true;
                        echo "<span class='text-danger'>Não (torne a pasta " . FCPATH . 'temp gravável) — permissões 0755</span><br />';
                    }
                }

                hooks()->do_action('after_system_info_files_permissions');

                $permissionsIssues = hooks()->apply_filters('system_info_files_permissions_issue', $permissionsIssues);

                if (!$permissionsIssues) {
                    echo 'Nenhum problema de permissão encontrado.';
                }
                ?>
                </td>
            </tr>
            <?php hooks()->do_action('after_system_last_info_row'); ?>
        </tbody>
    </table>
</div>
<script src="<?php echo base_url('assets/plugins/excellentexport/excellentexport.min.js'); ?>"></script>
<?php
// Internal function and should be used only here because it takes too much memory
function _get_my_prefixed_files()
{
    $ci                = get_instance();
    $my_prefixed_files = [];
    $view_files        = get_dir_contents(APPPATH . 'views');
    $modules           = $ci->app_modules->get();

    foreach ($modules as $module) {
        if (is_dir($module['path'] . 'views')) {
            $view_files = array_merge($view_files, get_dir_contents($module['path'] . 'views'));
        }
    }

    foreach ($view_files as $file) {
        $basename = basename($file);
        if (startsWith($basename, 'my_')) {
            $my_prefixed_files[] = $file;
        }
    }

    return $my_prefixed_files;
}
