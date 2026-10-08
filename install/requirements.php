<?php

$error = false;

if (version_compare(PHP_VERSION, '8.1') >= 0) {
    $requirement1 = "<span class='label label-success'>v." . PHP_VERSION . '</span>';
} else {
    $error        = true;
    $requirement1 = "<span class='label label-danger'>A versão do PHP é " . PHP_VERSION . '</span>';
}

if (! extension_loaded('mysqli')) {
    $error        = true;
    $requirement2 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement2 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('pdo')) {
    $error        = true;
    $requirement3 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement3 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('curl')) {
    $error        = true;
    $requirement4 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement4 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('openssl')) {
    $error        = true;
    $requirement5 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement5 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('mbstring')) {
    $error        = true;
    $requirement6 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement6 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('iconv') && ! function_exists('iconv')) {
    $error        = true;
    $requirement7 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement7 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('imap')) {
    $error        = true;
    $requirement8 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement8 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('gd')) {
    $error        = true;
    $requirement9 = "<span class='label label-danger'>Não habilitada</span>";
} else {
    $requirement9 = "<span class='label label-success'>Habilitada</span>";
}

if (! extension_loaded('zip')) {
    $error         = true;
    $requirement10 = "<span class='label label-danger'>A extensão Zip não está habilitada</span>";
} else {
    $requirement10 = "<span class='label label-success'>Habilitada</span>";
}

$url_f_open = ini_get('allow_url_fopen');
if ($url_f_open != '1'
    && strcasecmp($url_f_open, 'On') != 0
    && strcasecmp($url_f_open, 'true') != 0
    && strcasecmp($url_f_open, 'yes') != 0) {
    $error         = true;
    $requirement11 = "<span class='label label-danger'>A opção allow_url_fopen não está habilitada.</span>";
} else {
    $requirement11 = "<span class='label label-success'>Habilitada</span>";
}

?>
<table class="table table-hover tw-text-sm">
    <thead>
        <tr>
            <th><b>Requisitos</b></th>
            <th><b>Resultado</b></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="tw-font-medium">PHP >= 8.1</td>
            <td><?= $requirement1; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">MySQLi PHP</td>
            <td><?= $requirement2; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">PDO PHP</td>
            <td><?= $requirement3; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">cURL PHP</td>
            <td><?= $requirement4; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">OpenSSL PHP</td>
            <td><?= $requirement5; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">MBString PHP</td>
            <td><?= $requirement6; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">iconv PHP</td>
            <td><?= $requirement7; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">IMAP PHP</td>
            <td><?= $requirement8; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">GD PHP</td>
            <td><?= $requirement9; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">Zip PHP</td>
            <td><?= $requirement10; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">allow_url_fopen</td>
            <td><?= $requirement11; ?></td>
        </tr>
    </tbody>
</table>
<hr class="-tw-mx-4" />
<?php if ($error == true) {
    echo '<div class="text-center alert alert-danger tw-mb-0">Corrija os requisitos para continuar a instalação do ARGWS CRM.</div>';
} else {
    echo '<div class="text-center">';
    echo '<form action="" method="post" accept-charset="utf-8">';
    echo '<input type="hidden" value="true" name="requirements_success">';
    echo '<div class="text-right">';
    echo '<button type="submit" class="btn btn-primary">Verificar permissões de arquivos e pastas</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}
?>