<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php if (!empty($native_customers_import)) {
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $url = fn(string $query = '') => CompanyRequestScope::adminUrl($this, 'clients/import' . $query);
    $hidden = function (string $action) use ($company_clients_csrf, $company_context_token): void {
        echo form_hidden('action', $action);
        echo form_hidden('_native_company_csrf', $company_clients_csrf);
        echo form_hidden('_company_context', $company_context_token);
        echo form_hidden('request_key', bin2hex(random_bytes(32)));
    };
    init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row"><div class="col-md-12">
            <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                <h4 class="tw-my-0 tw-font-bold tw-text-lg tw-text-neutral-700"><?= $escape(_l('import_customers')); ?></h4>
                <a class="btn btn-default" id="client-export" href="<?= $escape($url('?export=clients&after=' . (int) $client_page['after'])); ?>">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> Exportar esta página
                </a>
            </div>
            <?php if ($error) { ?><div role="alert" class="alert alert-danger"><?= $escape($error); ?></div><?php } ?>

            <div class="panel_s"><div class="panel-body">
                <h4 class="tw-font-semibold tw-mt-0">Clientes da empresa ativa</h4>
                <p class="text-muted">A exportação inclui apenas identidades pertencentes à empresa atual, em páginas de até 100 registros.</p>
                <div class="table-responsive"><table class="table dt-table">
                    <thead><tr><th>ID</th><th>Nome</th><th>CPF/CNPJ</th><th>Estado</th></tr></thead>
                    <tbody><?php foreach ($client_page['items'] as $row) { ?><tr>
                        <td><?= (int) $row['userid']; ?></td>
                        <td><a href="<?= $escape(CompanyRequestScope::adminUrl($this, 'clients/client/' . (int) $row['userid'])); ?>"><?= $escape($row['company']); ?></a></td>
                        <td><?= $escape($row['vat']); ?></td>
                        <td><?= (int) $row['active'] === 1 ? 'Ativo' : 'Inativo'; ?></td>
                    </tr><?php } ?></tbody>
                </table></div>
                <?php if ($client_page['next'] !== null) { ?>
                    <a id="client-export-next" class="btn btn-default" href="<?= $escape($url('?after=' . (int) $client_page['next'])); ?>">Próxima página</a>
                <?php } ?>
            </div></div>

            <?php if ($batch) { ?><div class="panel_s"><div class="panel-body">
                <h4 class="tw-font-semibold tw-mt-0">Revisão do lote</h4>
                <p><code><?= $escape($batch['batch_id']); ?></code> · <?= $batch['state'] === 'committed' ? 'Confirmado' : ($batch['expired'] ? 'Preview expirado' : 'Aguardando confirmação'); ?> · válido até <?= $escape($batch['expires_at']); ?> UTC</p>
                <div class="table-responsive"><table id="client-import-preview" class="table">
                    <thead><tr><th>Linha</th><th>Nome revisado</th><th>CPF/CNPJ revisado</th></tr></thead>
                    <tbody><?php foreach ($batch['items'] as $index => $item) { ?><tr><td><?= $index + 1; ?></td><td><?= $escape($item['company']); ?></td><td><?= $escape($item['vat']); ?></td></tr><?php } ?></tbody>
                </table></div>
                <?php if ($batch['result']) { ?>
                    <p role="status">Lote confirmado. Clientes criados:</p><ul><?php foreach ($batch['result']['client_ids'] as $id) { ?><li><a href="<?= $escape(CompanyRequestScope::adminUrl($this, 'clients/client/' . (int) $id)); ?>">Cliente <?= (int) $id; ?></a></li><?php } ?></ul>
                <?php } elseif (!$batch['expired'] && $can_import) { ?>
                    <?= form_open($url()); ?><?php $hidden('commit'); ?>
                    <?= form_hidden('batch_id', $batch['batch_id']); ?><?= form_hidden('revision', $batch['revision']); ?>
                    <p>Revise todas as linhas. Se uma linha falhar, o lote inteiro será revertido.</p>
                    <button id="client-import-commit" class="btn btn-primary" type="submit">Confirmar lote inteiro</button><?= form_close(); ?>
                <?php } ?>
            </div></div><?php } ?>

            <?php if ($can_import) { ?><div class="panel_s"><div class="panel-body">
                <h4 class="tw-font-semibold tw-mt-0">Gerar preview obrigatório</h4>
                <p>Até 100 linhas e 256 KiB em UTF-8. Use CSV com ponto e vírgula e cabeçalho <code>company;vat</code>, ou JSON com uma lista de objetos. O preview expira em uma hora e não cria clientes.</p>
                <?= form_open_multipart($url(), ['id' => 'import_form']); ?><?php $hidden('preview'); ?>
                <div class="row"><div class="col-md-3"><div class="form-group"><label for="client-import-format">Formato</label><select id="client-import-format" class="form-control" name="format"><option value="csv">CSV (;)</option><option value="json">JSON</option></select></div></div>
                <div class="col-md-6"><div class="form-group"><label for="client-import-file">Arquivo privado</label><input id="client-import-file" class="form-control" type="file" name="source_file" accept=".csv,.json" required></div></div></div>
                <button class="btn btn-primary" type="submit">Validar e visualizar</button><?= form_close(); ?>
                <?php if ($batches) { ?><hr><h5 class="tw-font-semibold">Lotes recentes nesta empresa</h5><ul><?php foreach ($batches as $row) { ?><li><a href="<?= $escape($url('?batch=' . $row['batch_id'])); ?>"><?= $escape($row['batch_id']); ?></a> · <?= (int) $row['row_count']; ?> linhas · <?= $escape($row['state'] === 'committed' ? 'Confirmado' : 'Preview'); ?> · <?= $escape($row['created_at']); ?> UTC</li><?php } ?></ul><?php } ?>
            </div></div><?php } ?>
        </div></div>
    </div>
</div>
<?php init_tail(); ?>
<script src="<?= $escape(base_url('assets/plugins/jquery-validation/additional-methods.min.js')); ?>"></script>
<script>$(function(){appValidateForm($('#import_form'),{source_file:{required:true,extension:'csv|json'}});});</script>
</body></html>
<?php return; } ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body tw-bg-gradient-to-l tw-from-transparent tw-to-neutral-50">
                        <?= $this->import->importGuidelinesInfoHtml(); ?>
                    </div>
                </div>
                <div class="tw-flex tw-justify-between tw-items-center tw-mb-3">
                    <h4 class="tw-my-0 tw-font-bold tw-text-lg tw-text-neutral-700 tw-flex tw-items-center tw-gap-x-2">
                        <?= _l('import_customers'); ?>
                    </h4>
                    <?= $this->import->downloadSampleFormHtml(); ?>
                </div>
                <div class="panel_s">
                    <div class="panel-body">
                        <?= $this->import->maxInputVarsWarningHtml(); ?>
                        <?php if (! $this->import->isSimulation()) { ?>
                        <?= $this->import->createSampleTableHtml(); ?>
                        <?php } else { ?>
                        <div class="tw-mb-6">
                            <?= $this->import->simulationDataInfo(); ?>
                        </div>
                        <?= $this->import->createSampleTableHtml(true); ?>
                        <?php } ?>
                        <div class="row">
                            <div class="col-md-4 mtop15">
                                <?= form_open_multipart($this->uri->uri_string(), ['id' => 'import_form']); ?>
                                <?= form_hidden('clients_import', 'true'); ?>
                                <?= render_input('file_csv', 'choose_csv_file', '', 'file'); ?>
                                <?php
                if (is_admin() || get_option('staff_members_create_inline_customer_groups') == '1') {
                    echo render_select_with_input_group('groups_in[]', $groups, ['id', 'name'], 'customer_groups', ($this->input->post('groups_in') ? $this->input->post('groups_in') : []), '<div class="input-group-btn"><a href="#" class="btn btn-default" data-toggle="modal" data-target="#customer_group_modal"><i class="fa fa-plus"></i></a></div>', ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                } else {
                    echo render_select('groups_in[]', $groups, ['id', 'name'], 'customer_groups', ($this->input->post('groups_in') ? $this->input->post('groups_in') : []), ['multiple' => true, 'data-actions-box' => true], [], '', '', false);
                }
echo render_input('default_pass_all', 'default_pass_clients_import', $this->input->post('default_pass_all')); ?>
                                <div class="form-group">
                                    <button type="button"
                                        class="btn btn-primary import btn-import-submit"><?= _l('import'); ?></button>
                                    <button type="button"
                                        class="btn btn-default simulate btn-import-submit"><?= _l('simulate_import'); ?></button>
                                </div>
                                <?= form_close(); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/clients/client_group'); ?>
<?php init_tail(); ?>
<script
    src="<?= base_url('assets/plugins/jquery-validation/additional-methods.min.js'); ?>">
</script>
<script>
    $(function() {
        appValidateForm($('#import_form'), {
            file_csv: {
                required: true,
                extension: "csv"
            },
            source: 'required',
            status: 'required'
        });
    });
</script>
</body>

</html>
