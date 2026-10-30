<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h2 class="no-margin">Categorias Cadastradas</h2>
                <hr class="hr-panel-heading" />

                <!-- Botão de adicionar categoria -->
                <a href="<?php echo admin_url("finance/category/manage"); ?>" class="btn btn-primary mbot15">
                    <i class="fa fa-plus"></i> Adicionar Categoria
                </a>

                <!-- Tabela das categorias de entrada (verde) -->
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr style="background-color: #e0f7e9;">
                                <th colspan="3" class="text-center" style="color: green;">Categorias de Entrada</th>
                            </tr>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category["type"] === "entrada"): ?>
                                    <?php if (empty($category["father_category"])): ?>
                                        <!-- Categoria pai (apenas título) -->
                                        <tr style="background-color: #e0f7e9; font-weight: bold;">
                                            <td colspan="3"><?php echo $category["name"]; ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- Categoria filha com opções de editar e excluir -->
                                        <tr>
                                            <td><?php echo $category["name"]; ?></td>
                                            <td style="color: green;"><?php echo ucfirst($category["type"]); ?></td>
                                            <td>
                                                <a href="<?php echo admin_url("finance/category/manage/" . $category["id"]); ?>" class="btn btn-default btn-icon">
                                                    <i class="fa fa-pencil"></i> Editar
                                                </a>
                                                <a href="<?php echo admin_url("finance/category/delete/" . $category["id"]); ?>" onclick="return confirm('Tem certeza que deseja excluir esta categoria?');" class="btn btn-danger btn-icon">
                                                    <i class="fa fa-remove"></i> Excluir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>

                        <!-- Tabela das categorias de saída (vermelho) -->
                        <thead>
                            <tr style="background-color: #fdecea;">
                                <th colspan="3" class="text-center" style="color: red;">Categorias de Saída</th>
                            </tr>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <?php if ($category["type"] === "saida"): ?>
                                    <?php if (empty($category["father_category"])): ?>
                                        <!-- Categoria pai (apenas título) -->
                                        <tr style="background-color: #fdecea; font-weight: bold;">
                                            <td colspan="3"><?php echo $category["name"]; ?></td>
                                        </tr>
                                    <?php else: ?>
                                        <!-- Categoria filha com opções de editar e excluir -->
                                        <tr>
                                            <td><?php echo $category["name"]; ?></td>
                                            <td style="color: red;"><?php echo ucfirst($category["type"]); ?></td>
                                            <td>
                                                <a href="<?php echo admin_url("finance/category/manage/" . $category["id"]); ?>" class="btn btn-default btn-icon">
                                                    <i class="fa fa-pencil"></i> Editar
                                                </a>
                                                <a href="<?php echo admin_url("finance/category/delete/" . $category["id"]); ?>" onclick="return confirm('Tem certeza que deseja excluir esta categoria?');" class="btn btn-danger btn-icon">
                                                    <i class="fa fa-remove"></i> Excluir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<script>
    $(document).ready(function() {
        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();

            $.post($(this).attr('action'), $(this).serialize(), function(response) {
                if (response.success) {
                    alert_float('success', 'Categoria adicionada com sucesso!');
                    $('#categoryModal').modal('hide');
                    location.reload();
                } else {
                    alert_float('danger', 'Erro ao adicionar categoria.');
                }
            }, 'json').fail(function() {
                alert_float('danger', 'Erro ao processar a solicitação.');
            });
        });
    });
</script>