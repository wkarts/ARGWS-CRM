<?php

require_once('install.class.php');
$install = new Install();

if (file_exists($install->config_path) &&
        (!isset($_POST['step']) || isset($_POST['step']) && $_POST['step'] !== Install::$last_step)) {
    echo '<h1>A instalação do ' . crm_installer_display_name_html() . ' já foi concluída.</h1>';
    echo '<p style="font-size:18px;font-family:monospace;">Para instalar novamente em um ambiente de testes, use uma base de dados vazia e faça uma cópia de segurança antes de prosseguir. Não apague tabelas nem arquivos de uma instalação que contenha dados de clientes.</p>';
    exit(0);
}

session_name('argws_install');
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
if (!isset($_SESSION['installer_csrf']) || !is_string($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}
$install->csrfToken = $_SESSION['installer_csrf'];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && (!is_string($_POST['installer_csrf'] ?? null)
        || !hash_equals($install->csrfToken, $_POST['installer_csrf']))) {
    http_response_code(403);
    exit('Solicitação inválida. Atualize a página de instalação e tente novamente.');
}
$install->go();
