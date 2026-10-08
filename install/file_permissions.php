<?php
$error = false;
if (!is_writable('../uploads/estimates')) {
    $error                 = true;
    $requirement_estimates = "<span class='label label-danger'>Não (torne uploads/estimates gravável) — permissões 0755</span>";
} else {
    $requirement_estimates = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/proposals')) {
    $error                 = true;
    $requirement_proposals = "<span class='label label-danger'>Não (torne uploads/proposals gravável) — permissões 0755</span>";
} else {
    $requirement_proposals = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/ticket_attachments')) {
    $error        = true;
    $requirement1 = "<span class='label label-danger'>Não (torne uploads/ticket_attachments gravável) — permissões 0755</span>";
} else {
    $requirement1 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/tasks')) {
    $error        = true;
    $requirement2 = "<span class='label label-danger'>Não (torne uploads/tasks gravável) — permissões 0755</span>";
} else {
    $requirement2 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/staff_profile_images')) {
    $error        = true;
    $requirement3 = "<span class='label label-danger'>Não (torne uploads/staff_profile_images gravável) — permissões 0755</span>";
} else {
    $requirement3 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/projects')) {
    $error        = true;
    $requirement4 = "<span class='label label-danger'>Não (torne uploads/projects gravável) — permissões 0755</span>";
} else {
    $requirement4 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/newsfeed')) {
    $error        = true;
    $requirement5 = "<span class='label label-danger'>Não (torne uploads/newsfeed gravável) — permissões 0755</span>";
} else {
    $requirement5 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/leads')) {
    $error        = true;
    $requirement6 = "<span class='label label-danger'>Não (torne uploads/leads gravável) — permissões 0755</span>";
} else {
    $requirement6 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/invoices')) {
    $error        = true;
    $requirement7 = "<span class='label label-danger'>Não (torne uploads/invoices gravável) — permissões 0755</span>";
} else {
    $requirement7 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/expenses')) {
    $error        = true;
    $requirement8 = "<span class='label label-danger'>Não (torne uploads/expenses gravável) — permissões 0755</span>";
} else {
    $requirement8 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/discussions')) {
    $error        = true;
    $requirement9 = "<span class='label label-danger'>Não (torne uploads/discussions gravável) — permissões 0755</span>";
} else {
    $requirement9 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/contracts')) {
    $error         = true;
    $requirement10 = "<span class='label label-danger'>Não (torne uploads/contracts gravável) — permissões 0755</span>";
} else {
    $requirement10 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/company')) {
    $error         = true;
    $requirement11 = "<span class='label label-danger'>Não (torne uploads/company gravável) — permissões 0755</span>";
} else {
    $requirement11 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/clients')) {
    $error         = true;
    $requirement12 = "<span class='label label-danger'>Não (torne uploads/clients gravável) — permissões 0755</span>";
} else {
    $requirement12 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../uploads/client_profile_images')) {
    $error         = true;
    $requirement13 = "<span class='label label-danger'>Não (torne uploads/client_profile_images gravável) — permissões 0755</span>";
} else {
    $requirement13 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../application/config')) {
    $error         = true;
    $requirement14 = "<span class='label label-danger'>Não (torne application/config/ gravável) — permissões 0755</span>";
} else {
    $requirement14 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../application/config/config.php')) {
    $error         = true;
    $requirement15 = "<span class='label label-danger'>Não (torne application/config/config.php gravável) — permissões 0644</span>";
} else {
    $requirement15 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../application/config/app-config-sample.php')) {
    $error         = true;
    $requirement16 = "<span class='label label-danger'>Não (torne application/config/app-config-sample.php gravável) — permissões - 0644</span>";
} else {
    $requirement16 = "<span class='label label-success'>Ok</span>";
}
if (!is_writable('../temp')) {
    $error         = true;
    $requirement17 = "<span class='label label-danger'>Não (torne a pasta temp gravável) — permissões 0755</span>";
} else {
    $requirement17 = "<span class='label label-success'>Ok</span>";
}

?>
<table class="table table-hover tw-text-sm">
    <thead>
        <tr>
            <th><b>Arquivo/Pasta</b></th>
            <th><b>Resultado</b></th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="tw-font-medium">uploads/proposals</td>
            <td><?php echo $requirement_proposals; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/estimates</td>
            <td><?php echo $requirement_estimates; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/ticket_attachments</td>
            <td><?php echo $requirement1; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/tasks</td>
            <td><?php echo $requirement2; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/staff_profile_images</td>
            <td><?php echo $requirement3; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/projects</td>
            <td><?php echo $requirement4; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/newsfeed</td>
            <td><?php echo $requirement5; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/leads</td>
            <td><?php echo $requirement6; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/invoices</td>
            <td><?php echo $requirement7; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/expenses</td>
            <td><?php echo $requirement8; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/discussions</td>
            <td><?php echo $requirement9; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/contracts</td>
            <td><?php echo $requirement10; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/company</td>
            <td><?php echo $requirement11; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/clients</td>
            <td><?php echo $requirement12; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">uploads/client_profile_images</td>
            <td><?php echo $requirement13; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">application/config — gravável</td>
            <td><?php echo $requirement14; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">config.php — gravável</td>
            <td><?php echo $requirement15; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">app-config-sample.php — gravável (renomeado durante a instalação)</td>
            <td><?php echo $requirement16; ?></td>
        </tr>
        <tr>
            <td class="tw-font-medium">/temp — gravável</td>
            <td><?php echo $requirement17; ?></td>
        </tr>
    </tbody>
</table>
<hr class="-tw-mx-4" />
<?php if ($error == true) {
    echo '<div class="text-center alert alert-danger tw-mb-0">Corrija as permissões indicadas para instalar o ARGWS CRM.</div>';
} else {
    echo '<div class="text-center">';
    echo '<form action="" method="post" accept-charset="utf-8">';
    echo '<input type="hidden" name="permissions_success" value="true">';
    echo '<div class="text-right">';
    echo '<button type="submit" class="btn btn-primary">Configurar banco de dados</button>';
    echo '</div>';
    echo '</form>';
    echo '</div>';
}