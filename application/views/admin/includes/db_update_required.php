<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <link rel="stylesheet" type="text/css" id="roboto-css"
        href="<?= site_url('assets/plugins/roboto/roboto.css'); ?>">
    <style>
        body {
            font-family: Roboto, Geneva, sans-serif;
            font-size: 15px;
        }

        .bold,
        b,
        strong,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-weight: 500;
        }

        .wrapper {
            margin: 0 auto;
            display: block;
            background: #f0f0f0;
            width: 700px;
            border: 1px solid #e4e4e4;
            padding: 20px;
            border-radius: 4px;
            margin-top: 50px;
            text-align: center;
        }

        .wrapper h1 {
            text-align: center;
            font-size: 27px;
            color: red;
            margin-top: 0px;
        }

        .wrapper .upgrade_now {
            text-transform: uppercase;
            background: #82b440;
            color: #fff;
            padding: 15px 25px;
            border-radius: 3px;
            text-decoration: none;
            text-align: center;
            border: 0px;
            outline: 0px;
            cursor: pointer;
            font-size: 15px;
        }

        .wrapper .upgrade_now:hover,
        .wrapper .upgrade_now:active {
            background: #73a92d;
        }

        .wrapper .upgrade_now:disabled {
            cursor: not-allowed;
            pointer-events: none;
            box-shadow: none;
            opacity: .65;
        }

        .upgrade_now_wrapper {
            margin: 0 auto;
            width: 100%;
            text-align: left;
            margin-top: 35px;
        }

        .note {
            color: #636363;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <h1>A atualização do banco de dados é necessária</h1>
        <p>Atualize o banco de dados antes de continuar. A <b>migration incluída nos arquivos é
                <?= wordwrap($this->config->item('migration_version'), 1, '.', true); ?></b>
            e a <b>migration aplicada no banco é
                <?= wordwrap($this->current_db_version, 1, '.', true); ?>.</b>
        </p>
        <p class="bold">Faça um backup do banco de dados antes de iniciar a atualização.</p>
        <div class="upgrade_now_wrapper">
            <div style="text-align:center">
                <?= form_open($this->config->site_url($this->uri->uri_string()), ['id' => 'upgrade_db_form']); ?>
                <input type="hidden" name="upgrade_database" value="true">
                <button type="submit" id="submit_btn" onclick="upgradeDB(); return false;" class="upgrade_now">Atualizar agora</button>
                <?= form_close(); ?>
            </div>
            <br />
            <p style="text-align:center;">
                <small class="note">Esta tela aparece quando os arquivos ARGWS incluem uma migration mais recente que a aplicada no banco. Faça a atualização manual usando o pacote da mesma versão.</small>
            </p>
            <?php
     if ($copyData = get_last_upgrade_copy_data()) {
         if ($copyData->version == $this->config->item('migration_version')) { ?>
            <hr />
            <h3>Orientações após a atualização</h3>
            <p style="line-height:20px;">
                Confira seus arquivos personalizados, incluindo <b>my_functions_helper.php</b>, arquivos com
                prefixo <b>my_</b>, <b>hooks personalizados</b>, temas da área do cliente e integrações adicionais.
            </p>
            <p style="line-height:20px;"><b>Se nem todos os arquivos forem extraídos</b> por falta de permissão, o pacote foi copiado para <b><?= html_escape($copyData->path); ?></b>. Extraia-o manualmente pelo painel de hospedagem ou pela linha de comando, se necessário.</p>

            O pacote de atualização copiado ficará <b>disponível pelos próximos
                <?= _delete_temporary_files_older_then() / 60; ?>
                minutos</b>.

            <p>
                <b>Se precisar extrair os arquivos manualmente</b>, extraia

                o arquivo <b><?= html_escape(basename($copyData->path)); ?></b>
                no diretório <b><?= html_escape(FCPATH); ?></b>.
            </p>
            <small class="note">Guarde o caminho acima para localizar o pacote.</small>
            <?php
         }
     }
?>
        </div>
    </div>
    <script>
        function upgradeDB() {
            document.getElementById('submit_btn').disabled = true;
            document.getElementById('submit_btn').innerHTML = "Aguarde...";
            document.getElementById("upgrade_db_form").submit();
        }
    </script>
</body>

</html>
