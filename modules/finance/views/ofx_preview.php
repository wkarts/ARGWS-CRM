<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <h4 class="no-margin">Pré-visualização das Transações OFX</h4>
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
                            <?php if (!empty($transactions)): ?>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo $transaction["id"]; ?></td>
                                        <td><?php echo _d($transaction["data"]); ?></td>
                                        <td><?php echo $transaction["descricao"]; ?></td>
                                        <td style="color: <?php echo $transaction["valor"] > 0 ? "blue" : "red"; ?>">
                                            <?php echo app_format_money($transaction["valor"], $currency_symbol); ?>
                                        </td>
                                        <td>
                                            <span class="label label-<?php echo $transaction["tipo"] === "entrada" ? "success" : "danger"; ?>">
                                                <?php echo ucfirst($transaction["tipo"]); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-default btn-icon salvar-conciliacao"
                                                data-toggle="modal"
                                                data-target="#conciliacaoModal_<?php echo $transaction["id"]; ?>"
                                                <?php echo isset($transaction["conciliado"]) && $transaction["conciliado"] ? "disabled" : ""; ?>>
                                                <i class="fa fa-check-circle"></i>
                                                <?php echo isset($transaction["conciliado"]) && $transaction["conciliado"] ? "Conciliado" : "Conciliar"; ?>
                                            </button>

                                            <!-- Modal de Conciliação -->
                                            <div class="modal fade" id="conciliacaoModal_<?php echo $transaction["id"]; ?>" tabindex="-1" role="dialog" aria-labelledby="conciliacaoModalLabel">
                                                <div class="modal-dialog" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="conciliacaoModalLabel">Conciliar Transação</h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p><strong>ID:</strong> <?php echo $transaction["id"]; ?></p>
                                                            <p><strong>Data:</strong> <?php echo _d($transaction["data"]); ?></p>
                                                            <p><strong>Descrição:</strong> <?php echo $transaction["descricao"]; ?></p>
                                                            <p><strong>Valor:</strong> <span style="color: <?php echo $transaction["valor"] > 0 ? "blue" : "red"; ?>"><?php echo app_format_money($transaction["valor"], $currency_symbol); ?></span></p>
                                                            <p><strong>Tipo:</strong> <?php echo ucfirst($transaction["tipo"]); ?></p>

                                                            <!-- Formulário de Conciliação -->
                                                            <?php echo form_open("", [
                                                                "class" => "conciliacao_form",
                                                                "data-id" => $transaction["id"],
                                                            ]); ?>
                                                            <input type="hidden" name="ofx_id" value="<?php echo $transaction["id"]; ?>">
                                                            <input type="hidden" name="data" value="<?php echo $transaction["data"]; ?>">
                                                            <input type="hidden" name="descricao" value="<?php echo $transaction["descricao"]; ?>">
                                                            <input type="hidden" name="valor" value="<?php echo $transaction["valor"]; ?>">
                                                            <input type="hidden" name="banco_origem_id" value="<?php echo $bank_id; ?>">

                                                            <div class="form-group">
                                                                <label for="tipo">Tipo:</label>
                                                                <select name="tipo" class="form-control tipo-transacao" required>
                                                                    <option value="entrada">Entrada</option>
                                                                    <option value="saida">Saída</option>
                                                                    <option value="transferencia">Transferência</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group categoria-field">
                                                                <label for="categoria">Categoria:</label>
                                                                <select name="categoria" class="form-control categoria-select" required>
                                                                    <option value="">Selecione uma categoria</option>
                                                                    <?php foreach ($categorias as $categoria): ?>
                                                                        <option value="<?php echo $categoria["id"]; ?>" data-type="<?php echo $categoria["type"]; ?>" <?php echo $categoria["father_category"] ? '' : 'disabled'; ?>>
                                                                            <?php echo $categoria["name"]; ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="form-group banco-destino" style="display: none;">
                                                                <label for="banco_destino">Banco Destino (para Transferências):</label>
                                                                <select name="banco_destino" class="form-control">
                                                                    <option value="">Selecione um banco</option>
                                                                    <?php foreach ($bancos as $banco): ?>
                                                                        <option value="<?php echo $banco["id"]; ?>"><?php echo $banco["name"]; ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <?php echo form_close(); ?>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                                                            <button type="button" class="btn btn-primary salvar-conciliacao" data-id="<?php echo $transaction["id"]; ?>">Salvar Conciliação</button>
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
                <a href="<?php echo admin_url("finance/conciliacao/upload_ofx_form"); ?>" class="btn btn-default">Voltar</a>
            </div>
        </div>
    </div>
</div>

<?php init_tail(); ?>

<script>
    $(document).ready(function() {
        // Ajuste de exibição de campos conforme o tipo selecionado (mantendo a estrutura original)
        $('select[name="tipo"]').on('change', function() {
            var tipo = $(this).val();
            var form = $(this).closest('form');

            if (tipo === 'transferencia') {
                form.find('.banco-destino').show();
                form.find('.categoria-field').hide();
            } else {
                form.find('.banco-destino').hide();
                form.find('.categoria-field').show();
                
                // Filtra categorias conforme o tipo (entrada ou saída)
                form.find('select[name="categoria"] option').each(function() {
                    $(this).toggle($(this).data('type') === tipo);
                });
            }
        });

        // Clique no botão de salvar conciliação
        $('.salvar-conciliacao').on('click', function() {
            var transactionId = $(this).data('id');
            var form = $('.conciliacao_form[data-id="' + transactionId + '"]');
            var formData = form.serialize();

            // Envio do formulário via AJAX (mantendo estrutura original)
            $.post("<?php echo admin_url("finance/conciliacao/salvar_conciliacao"); ?>", formData, function(response) {
                if (response.status === 'success') {
                    alert_float("success", "Conciliação salva com sucesso!");
                    form.closest('.modal').modal('hide');
                    $('button[data-target="#conciliacaoModal_' + transactionId + '"]').prop('disabled', true).text('Conciliado');
                } else {
                    alert_float("danger", response.message ? response.message : "Erro ao salvar conciliação.");
                }
            }, "json").fail(function() {
                alert_float("danger", "Erro ao salvar conciliação. Verifique a conexão ou os dados enviados.");
            });
        });
    });
</script>