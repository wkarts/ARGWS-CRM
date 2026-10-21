<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><div class="content"><div class="panel_s"><div class="panel-body">
    <h4>Manutenção de recursos instalados</h4>
    <p>Esta lista técnica inclui recursos ocultos, desativados e fora do catálogo comercial. Atualizar executa somente as migrations nativas; não ativa recursos nem altera sua disponibilidade comercial.</p>
    <p>Faça backup antes de atualizar. Uma falha pode deixar migrations parcialmente aplicadas; confira os logs antes de repetir.</p>
    <p><a href="<?= admin_url('modules'); ?>">Voltar aos recursos</a></p>
    <?php if (!$pending) { ?><div class="alert alert-success">Nenhuma pendência encontrada.</div><?php } ?>
    <div class="table-responsive"><table class="table table-hover">
        <thead><tr><th>Identificador técnico</th><th>Versão instalada</th><th>Versão dos arquivos</th><th>Estado</th><th>Ação</th></tr></thead>
        <tbody><?php foreach ($pending as $item) { ?>
        <tr>
            <td><?= html_escape($item['system_name']); ?></td>
            <td><?= html_escape($item['installed_version']); ?></td>
            <td><?= html_escape($item['code_version'] ?? 'Arquivos ausentes'); ?></td>
            <td><?= $item['activated'] ? 'Ativo' : 'Desativado'; ?></td>
            <td><?php if ($item['can_upgrade']) { ?>
                <?= form_open(admin_url('modules/upgrade_database/' . rawurlencode($item['system_name']))); ?>
                <input type="hidden" name="module_migration_csrf" value="<?= html_escape($module_migration_csrf); ?>">
                <button class="btn btn-warning btn-sm" type="submit">Atualizar banco explicitamente</button>
                <?= form_close(); ?>
            <?php } else {
                $reasons = [
                    'downgrade' => 'Bloqueado: banco mais recente que os arquivos. Restaure a distribuição compatível; não faça downgrade.',
                    'missing_files' => 'Bloqueado: arquivos do módulo ausentes. Restaure a distribuição compatível antes de atualizar.',
                    'missing_runtime' => 'Bloqueado: esta migration legada depende de helpers indisponíveis. Requer um bootstrap de manutenção específico; não ative o recurso para contornar.',
                    'missing_migration' => 'Bloqueado: sequência não reconhecida ou arquivo ausente/duplicado. Revise o caminho histórico e a distribuição com o suporte; não invente etapas.',
                    'invalid_version' => 'Bloqueado: versão incompatível com o executor nativo. Revise a distribuição e o histórico.',
                ]; ?>
                <span class="text-warning"><?= html_escape($reasons[$item['status']] ?? 'Revisão necessária.'); ?></span>
            <?php } ?></td>
        </tr><?php } ?></tbody>
    </table></div>
</div></div></div></div>
<?php init_tail(); ?>
</body></html>
