<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <h2><?php echo isset($bank)
            ? "Editar Banco"
            : "Adicionar Banco"; ?></h2>

        <?php echo form_open(
            admin_url(
                "finance/bank/manage" . (isset($bank) ? "/" . $bank->id : "")
            )
        ); ?>

            <div class="form-group">
                <label>Nome do Banco</label>
                <input type="text" name="name" class="form-control" value="<?php echo isset(
                    $bank
                )
                    ? $bank->name
                    : ""; ?>" required>
            </div>
            <div class="form-group">
                <label>Saldo Inicial</label>
                <input type="number" name="initial_balance" class="form-control" step="0.01" value="<?php echo isset(
                    $bank
                )
                    ? $bank->initial_balance
                    : ""; ?>" required>
            </div>
            <div class="form-group">
                <label>Conta Corrente</label>
                <input type="text" name="account_number" class="form-control" value="<?php echo isset(
                    $bank
                )
                    ? $bank->account_number
                    : ""; ?>" required>
            </div>
            <div class="form-group">
                <label>Agência</label>
                <input type="text" name="agency" class="form-control" value="<?php echo isset(
                    $bank
                )
                    ? $bank->agency
                    : ""; ?>" required>
            </div>
            <div class="form-group">
                <label>URL do Ícone</label>
                <input type="url" name="image" class="form-control" placeholder="https://exemplo.com/icone.png" value="<?php echo isset(
                    $bank
                )
                    ? $bank->image
                    : ""; ?>">
            </div>

            <button type="submit" class="btn btn-primary"><?php echo isset(
                $bank
            )
                ? "Atualizar"
                : "Cadastrar"; ?></button>
            <a href="<?php echo admin_url(
                "finance/bank"
            ); ?>" class="btn btn-default">Cancelar</a>

        <?php echo form_close(); ?>
    </div>
</div>
<?php init_tail(); ?>
