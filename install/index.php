<?php

require_once('install.class.php');
$install = new Install();

if (file_exists($install->config_path) &&
        (!isset($_POST['step']) || isset($_POST['step']) && $_POST['step'] !== Install::$last_step)) {
    echo '<h1>A instalação do ARGWS CRM já foi concluída.</h1>';
    echo '<p style="font-size:18px;font-family:monospace;">Para instalar novamente em um ambiente de testes, use uma base de dados vazia e faça uma cópia de segurança antes de prosseguir. Não apague tabelas nem arquivos de uma instalação que contenha dados de clientes.</p>';
    exit(0);
}

$install->go();
