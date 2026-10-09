<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin">Recursos</h4>
                        <p class="text-muted mtop10">Ative ou desative os recursos desta instalação. Desativar um recurso não exclui tabelas nem dados salvos.</p>
                        <hr />
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Recurso</th>
                                        <th>Descrição</th>
                                        <th>Dependências</th>
                                        <th>Versão</th>
                                        <th>Estado</th>
                                        <th class="text-right">Ação</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($resources as $resource) {
                                        $systemName = $resource['system_name'];
                                        $isActive   = (int) $resource['activated'] === 1;
                                        $needsDb    = $isActive && $this->app_modules->is_database_upgrade_required($systemName);
                                        $dependencies = array_map(function ($dependency) use ($resources) {
                                            foreach ($resources as $item) {
                                                if ($item['system_name'] === $dependency) {
                                                    return $item['name'];
                                                }
                                            }
                                            return $dependency;
                                        }, $resource['requires']); ?>
                                        <?php $optionalDependencies = array_map(function ($dependency) use ($resources) {
                                            foreach ($resources as $item) {
                                                if ($item['system_name'] === $dependency) {
                                                    return $item['name'] . ' (opcional)';
                                                }
                                            }
                                            return $dependency . ' (opcional)';
                                        }, $resource['optional'] ?? []); ?>
                                        <tr class="<?= $needsDb ? 'warning' : ''; ?>">
                                            <td>
                                                <strong><?= html_escape($resource['name']); ?></strong>
                                            </td>
                                            <td><?= html_escape($resource['description']); ?></td>
                                            <td><?= ($dependencies || $optionalDependencies) ? html_escape(implode(', ', array_merge($dependencies, $optionalDependencies))) : 'Nenhuma'; ?></td>
                                            <td><?= html_escape($resource['headers']['version'] ?? '—'); ?></td>
                                            <td>
                                                <?php if ($needsDb) { ?>
                                                    <span class="label label-warning">Atualização local do banco necessária</span>
                                                <?php } elseif ($isActive) { ?>
                                                    <span class="label label-success">Ativo</span>
                                                <?php } else { ?>
                                                    <span class="label label-default">Desativado</span>
                                                <?php } ?>
                                            </td>
                                            <td class="text-right">
                                                <?php if ($needsDb) { ?>
                                                    <?= form_open(admin_url('modules/upgrade_database/' . rawurlencode($systemName)), ['class' => 'tw-inline-block']); ?>
                                                        <button type="submit" class="btn btn-warning btn-sm">Atualizar banco</button>
                                                    <?= form_close(); ?>
                                                <?php } elseif ($isActive) { ?>
                                                    <?= form_open(admin_url('modules/deactivate/' . rawurlencode($systemName)), ['class' => 'tw-inline-block']); ?>
                                                        <button type="submit" class="btn btn-default btn-sm">Desativar</button>
                                                    <?= form_close(); ?>
                                                <?php } else { ?>
                                                    <?= form_open(admin_url('modules/activate/' . rawurlencode($systemName)), ['class' => 'tw-inline-block']); ?>
                                                        <button type="submit" class="btn btn-primary btn-sm">Ativar</button>
                                                    <?= form_close(); ?>
                                                <?php } ?>
                                            </td>
                                        </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>
