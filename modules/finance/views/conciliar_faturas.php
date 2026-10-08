<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="no-margin">Conciliação de Faturas e Despesas</h4>
                <hr>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover dt-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Valor</th>
                                <th>Tipo</th>
                                <th>Conciliação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($transacoes)): ?>
                                <?php foreach ($transacoes as $transacao): ?>
                                    <?php
                                        $color = $transacao["tipo"] === "entrada" ? "blue" : "red";
                                        $unique_modal_id = $transacao["id"] . "_" . strtotime($transacao["date"]) . "_" . $transacao["valor"];
                                        $conciliado = $transacao['conciliado'];
                                    ?>
                                    <tr>
                                        <td><?php echo $transacao["id"]; ?></td>
                                        <td><?php echo _d($transacao["date"]); ?></td>
                                        <td><?php echo $transacao["descricao"]; ?></td>
                                        <td style="color: <?php echo $color; ?>;">
                                            <?php echo app_format_money($transacao["valor"], $currency_symbol); ?>
                                        </td>
                                        <td>
                                            <span class="label label-<?php echo $transacao["tipo"] === "entrada" ? "success" : "danger"; ?>">
                                                <?php echo ucfirst($transacao["tipo"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($conciliado): ?>
                                                <button class="btn btn-success" disabled>Conciliado</button>
                                            <?php else: ?>
                                                <button class="btn btn-default btn-icon salvar-conciliacao" data-id="<?php echo $unique_modal_id; ?>" data-toggle="modal" data-target="#conciliacaoModal_<?php echo $unique_modal_id; ?>">
                                                    <i class="fa fa-check-circle"></i> Conciliar
                                                </button>
                                            <?php endif; ?>

                                            <!-- Modal de Conciliação -->
                                            <div class="modal fade" id="conciliacaoModal_<?php echo $unique_modal_id; ?>" tabindex="-1" role="dialog">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Conciliar Transação</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>ID:</strong> <?php echo $transacao["id"]; ?></p>
                                                            <p><strong>Data:</strong> <?php echo _d($transacao["date"]); ?></p>
                                                            <p><strong>Descrição:</strong> <?php echo $transacao["descricao"]; ?></p>
                                                            <p><strong>Valor:</strong> <span style="color: <?php echo $color; ?>;"><?php echo app_format_money($transacao["valor"], $currency_symbol); ?></span></p>
                                                            <p><strong>Tipo:</strong> <?php echo ucfirst($transacao["tipo"]); ?></p>

                                                            <!-- Conciliation Form -->
                                                            <?php echo form_open("", ["class" => "conciliacao_form", "data-id" => $unique_modal_id]); ?>
                                                                <input type="hidden" name="transacao_id" value="<?php echo $transacao["id"]; ?>">
                                                                <input type="hidden" name="data" value="<?php echo $transacao["date"]; ?>">
                                                                <input type="hidden" name="descricao" value="<?php echo $transacao["descricao"]; ?>">
                                                                <input type="hidden" name="valor" value="<?php echo $transacao["valor"]; ?>">
                                                                <input type="hidden" name="tipo" value="<?php echo $transacao["tipo"]; ?>">

                                                                <div class="form-group">
                                                                    <label for="banco">Banco:</label>
                                                                    <select name="banco_id" class="form-control" required>
                                                                        <option value="">Selecione um banco</option>
                                                                        <?php foreach ($bancos as $banco): ?>
                                                                            <option value="<?php echo $banco["id"]; ?>"><?php echo $banco["name"]; ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="categoria">Categoria:</label>
                                                                    <select name="categoria_id" class="form-control" required>
                                                                        <option value="">Selecione uma categoria</option>
                                                                        <?php 
                                                                            $categorias = $transacao["tipo"] === "entrada" ? $categorias_entrada : $categorias_saida;
                                                                            foreach ($categorias as $categoria): 
                                                                        ?>
                                                                            <option value="<?php echo $categoria["id"]; ?>"><?php echo $categoria["name"]; ?></option>
                                                                        <?php endforeach; ?>
                                                                    </select>
                                                                </div>
                                                            <?php echo form_close(); ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                                                            <button type="button" class="btn btn-primary salvar-conciliacao-btn" data-id="<?php echo $unique_modal_id; ?>">Salvar Conciliação</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Nenhuma transação encontrada.</td>
                                </tr>
                            <?php endif; ?>
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
        $('.salvar-conciliacao-btn').on('click', function() {
            var modalId = $(this).data('id');
            var form = $('.conciliacao_form[data-id="' + modalId + '"]').serialize();

            $.ajax({
                url: "<?php echo admin_url('finance/conciliarfaturas/salvar_conciliacao'); ?>",
                type: "POST",
                data: form,
                dataType: "json",
                success: function(response) {
                    if (response.status === 'success') {
                        alert_float('success', 'Conciliação salva com sucesso!');
                        $('#conciliacaoModal_' + modalId).modal('hide');

                        // Atualiza botão na tabela
                        $('button[data-target="#conciliacaoModal_' + modalId + '"]')
                            .removeClass('btn-default')
                            .addClass('btn-success')
                            .prop('disabled', true)
                            .text('Conciliado');
                    } else {
                        alert_float('danger', 'Erro ao salvar conciliação.');
                    }
                },
                error: function(xhr, status, error) {
                    alert_float('danger', 'Erro ao salvar conciliação. Verifique o console para mais detalhes.');
                    console.error("Erro no AJAX:", xhr.responseText || error);
                }
            });
        });
    });
</script>
