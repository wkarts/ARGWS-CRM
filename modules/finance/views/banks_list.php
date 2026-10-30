<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<style>
    .bank-icon {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #ddd;
    }
</style>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="panel_s">
                    <div class="panel-body">
                        <h4 class="no-margin"><?php echo _l(
                            "Bancos Cadastrados"
                        ); ?></h4>
                        <hr class="hr-panel-heading" />
                        <div class="clearfix"></div>
                        <a href="<?php echo admin_url(
                            "finance/bank/manage"
                        ); ?>" class="btn btn-primary mbot15">
                            <i class="fa fa-plus"></i> <?php echo _l(
                                "Adicionar Banco"
                            ); ?>
                        </a>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th><?php echo _l("Ícone"); ?></th>
                                        <th><?php echo _l("Nome"); ?></th>
                                        <th><?php echo _l(
                                            "Saldo Inicial"
                                        ); ?></th>
                                        <th><?php echo _l("Conta"); ?></th>
                                        <th><?php echo _l("Agência"); ?></th>
                                        <th><?php echo _l("Ações"); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($banks as $bank): ?>
                                        <tr>
                                            <td>
                                                <?php if (
                                                    !empty($bank["image"])
                                                ): ?>
                                                    <img src="<?php echo $bank[
                                                        "image"
                                                    ]; ?>" alt="Ícone do Banco" class="bank-icon">
                                                <?php else: ?>
                                                    <i class="fa fa-bank bank-icon" style="font-size: 1.5em; color: #aaa;"></i> <!-- Ícone padrão -->
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $bank[
                                                "name"
                                            ]; ?></td>
                                            <td><?php echo app_format_money(
                                                $bank["initial_balance"],
                                                isset($currency->symbol)
                                                    ? $currency->symbol
                                                    : 'R$'
                                            ); ?></td>
                                            <td><?php echo $bank[
                                                "account_number"
                                            ]; ?></td>
                                            <td><?php echo $bank[
                                                "agency"
                                            ]; ?></td>
                                            <td>
                                                <a href="<?php echo admin_url(
                                                    "finance/bank/manage/" .
                                                        $bank["id"]
                                                ); ?>" class="btn btn-default btn-icon">
                                                    <i class="fa fa-pencil"></i> <?php echo _l(
                                                        "Editar"
                                                    ); ?>
                                                </a>
                                                <a href="<?php echo admin_url(
                                                    "finance/bank/delete/" .
                                                        $bank["id"]
                                                ); ?>" onclick="return confirm('Tem certeza que deseja deletar este banco?');" class="btn btn-danger btn-icon">
                                                    <i class="fa fa-remove"></i> <?php echo _l(
                                                        "Deletar"
                                                    ); ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
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
