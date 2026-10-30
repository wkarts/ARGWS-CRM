<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <h2>Transações Pendentes para Conciliação</h2>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Transação</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Banco</th>
                        <th>Conciliação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transacoes as $transacao): ?>
                        <tr>
                            <td><?php echo _d($transacao["data"]); ?></td>
                            <td><?php echo $transacao["transacao"]; ?></td>
                            <td><?php echo ucfirst(
                                $transacao["tipo_lancamento"]
                            ); ?></td>
                            <td><?php echo $transacao["descricao"]; ?></td>
                            <td><?php echo app_format_money(
                                $transacao["valor"],
                                'R$'
                            ); ?></td>
                            <td><?php echo $transacao["bank_id"]; ?></td>
                            <td>
                                <a href="<?php echo admin_url(
                                    "finance/conciliacao/conciliar/" .
                                        $transacao["id"]
                                ); ?>" class="btn btn-success">Conciliar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php init_tail(); ?>
