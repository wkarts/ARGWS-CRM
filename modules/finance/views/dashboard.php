<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>

<div id="wrapper">
    <div class="content">
        <div class="panel_s">
            <div class="panel-body">
                <!-- Botão para abrir o modal de inclusão manual -->
                <button class="btn btn-primary mb-4" data-toggle="modal" data-target="#incluirManualModal">Incluir Manual</button>

                <!-- Gráfico de Projeção de Faturamento e Despesas -->
                <div class="chart-container mt-4">
                    <h4>Projeção de Faturamento e Despesas (Janeiro a Dezembro)</h4>
                    <canvas id="projectionChart" style="height:400px; max-height: 400px; width:100%;"></canvas>
                </div>

                <!-- Gráfico de Previsão de Faturamento Próximos 30 Dias -->
                <div class="chart-container mt-4">
                    <h4>Previsão de Faturamento para os Próximos 30 Dias</h4>
                    <canvas id="invoiceForecastChart" style="height:400px; max-height: 400px; width:100%;"></canvas>
                </div>

                <!-- Informações do Dashboard Financeiro -->
                <h2 class="mt-4">Dashboard Financeiro</h2>
                <div class="row">
                    <div class="col-md-4">
                        <p><strong>Receita Total:</strong> <?php echo app_format_money($total_revenue, $currency_symbol); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Despesa Total:</strong> <?php echo app_format_money($total_expense, $currency_symbol); ?></p>
                    </div>
                    <div class="col-md-4">
                        <p><strong>Saldo Total:</strong> <?php echo app_format_money($total_cash, $currency_symbol); ?></p>
                    </div>
                </div>

                <!-- Tabela de Transações -->
                <div class="table-responsive mt-4">
                    <table class="table table-bordered dt-table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Descrição</th>
                                <th>Valor (R$)</th>
                                <th>Tipo</th>
                                <th>Banco Origem</th>
                                <th>Saldo (R$)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $saldo = 0.0;
                            foreach ($transactions as $transacao) {
                                $data = $transacao["data"] ?? "Data não disponível";
                                $descricao = $transacao["descricao"] ?? "Descrição não disponível";
                                $valor = $transacao["valor"] ?? 0;
                                $tipo = $transacao["tipo"] ?? "entrada";
                                $banco_nome = $transacao["banco_nome"] ?? "Banco não disponível";

                                $saldo += ($tipo === "entrada" ? $valor : -$valor);
                                $valor_color = $tipo === "entrada" ? "blue" : "red";
                                $formatted_value = '<span style="color:' . $valor_color . ';">' . app_format_money($tipo === "saida" ? -$valor : $valor, $currency_symbol) . "</span>";
                                $formatted_saldo = app_format_money($saldo, $currency_symbol);
                                $label_class = $tipo === "entrada" ? "success" : "danger";
                                $tipo_text = ucfirst($tipo);
                            ?>
                                <tr>
                                    <td><?php echo _d($data); ?></td>
                                    <td><?php echo $descricao; ?></td>
                                    <td><?php echo $formatted_value; ?></td>
                                    <td><span class="label label-<?php echo $label_class; ?>"><?php echo $tipo_text; ?></span></td>
                                    <td><?php echo $banco_nome; ?></td>
                                    <td><?php echo $formatted_saldo; ?></td>
                                </tr>
                            <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Inclusão Manual -->
<div class="modal fade" id="incluirManualModal" tabindex="-1" role="dialog" aria-labelledby="incluirManualLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="incluirManualLabel">Incluir Transação Manual</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php echo form_open('', ["id" => "formIncluirManual"]); ?>
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>" />

                    <div class="form-group">
                        <label for="descricao">Descrição</label>
                        <input type="text" class="form-control" id="descricao" name="descricao" required>
                    </div>
                    <div class="form-group">
                        <label for="valor">Valor</label>
                        <input type="text" class="form-control" id="valor" name="valor" required>
                    </div>
                    <div class="form-group">
                        <label for="data">Data</label>
                        <input type="date" class="form-control" id="data" name="data" required>
                    </div>
                    <div class="form-group">
                        <label for="tipo">Tipo</label>
                        <select class="form-control tipo-transacao" id="tipo" name="tipo" required>
                            <option value="">Selecione um Tipo</option>
                            <option value="entrada">Entrada</option>
                            <option value="saida">Saída</option>
                        </select>
                    </div>
                    <div class="form-group categoria-field">
                        <label for="categoria">Categoria</label>
                        <select class="form-control categoria-select" id="categoria" name="categoria" required>
                            <option value="">Selecione uma categoria</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo $categoria["id"]; ?>" data-type="<?php echo $categoria["type"]; ?>" <?php echo $categoria["father_category"] ? '' : 'disabled'; ?>>
                                    <?php echo $categoria["name"]; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="banco">Banco Origem</label>
                        <select class="form-control" id="banco" name="banco" required>
                            <option value="">Selecione o banco de origem</option>
                            <?php foreach ($bancos as $banco): ?>
                                <option value="<?php echo $banco['id']; ?>"><?php echo $banco['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php echo form_close(); ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="salvarTransacaoManual">Salvar</button>
            </div>
        </div>
    </div>
</div>
<?php init_tail(); ?>

<script>
$(document).ready(function() {
    $('#valor').on('input', function() {
        var value = $(this).val().replace(/[^0-9]/g, '');
        value = (value / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
        $(this).val(value);
    });

    $('#tipo').on('change', function() {
        var tipo = $(this).val();
        $('#categoria option').each(function() {
            $(this).toggle($(this).data('type') === tipo);
        });
    });

    $('#salvarTransacaoManual').on('click', function() {
        var formData = $('#formIncluirManual').serialize();
        $.post("<?php echo admin_url('finance/adicionar_manual'); ?>", formData, function(response) {
            if (response.status === 'success') {
                alert_float("success", "Transação adicionada com sucesso!");
                $('#incluirManualModal').modal('hide');
                location.reload();
            } else {
                alert_float("danger", "Erro ao adicionar transação.");
            }
        }, "json").fail(function() {
            alert_float("danger", "Erro ao processar a solicitação.");
        });
    });

    // Gráfico de Projeção de Faturamento e Despesas
    $.getJSON("<?php echo admin_url('finance/get_projection'); ?>", function(projection) {
        const labels = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];
        const faturamento = [];
        const despesa = [];

        for (let i = 1; i <= 12; i++) {
            faturamento.push(projection[i]?.faturamento || 0);
            despesa.push(projection[i]?.despesa || 0);
        }

        const ctx1 = document.getElementById("projectionChart").getContext("2d");
        new Chart(ctx1, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Faturamento",
                        backgroundColor: "rgba(54, 162, 235, 0.2)",
                        borderColor: "rgba(54, 162, 235, 1)",
                        data: faturamento,
                        fill: true
                    },
                    {
                        label: "Despesas",
                        backgroundColor: "rgba(255, 99, 132, 0.2)",
                        borderColor: "rgba(255, 99, 132, 1)",
                        data: despesa,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    });

    // Gráfico de Previsão de Faturamento para os Próximos 30 Dias
    $.getJSON("<?php echo admin_url('finance/get_invoice_forecast_data'); ?>", function(data) {
        const labels = Object.keys(data);
        const values = Object.values(data);

        const ctx2 = document.getElementById("invoiceForecastChart").getContext("2d");
        new Chart(ctx2, {
            type: "line",
            data: {
                labels: labels,
                datasets: [
                    {
                        label: "Faturamento Previsto",
                        backgroundColor: "rgba(75, 192, 192, 0.2)",
                        borderColor: "rgba(75, 192, 192, 1)",
                        data: values,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { 
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
                            }
                        }
                    },
                    x: { type: 'time', time: { unit: 'day', displayFormats: { day: 'DD/MM' }} }
                }
            }
        });
    });
});
</script>