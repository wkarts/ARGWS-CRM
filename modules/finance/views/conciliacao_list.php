<?php defined("BASEPATH") or exit("No direct script access allowed"); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <h2>Conciliação Bancária</h2>
        <a href="<?php echo admin_url(
            "finance/conciliacao/upload_ofx_form"
        ); ?>" class="btn btn-primary mbot15">
            <i class="fa fa-upload"></i> Fazer Upload de OFX
        </a>

        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                        <th>Tipo</th>
                        <th>Categoria</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($conciliacoes as $conciliacao): ?>
                        <tr>
                            <td><?php echo _d($conciliacao["data"]); ?></td>
                            <td><?php echo $conciliacao["descricao"]; ?></td>
                            <td><?php echo app_format_money(
                                $conciliacao["valor"],
                                'R$'
                            ); ?></td>
                            <td><?php echo ucfirst(
                                $conciliacao["tipo"]
                            ); ?></td>
                            <td>
                                <select name="categoria_id" class="form-control">
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category[
                                            "id"
                                        ]; ?>">
                                            <?php echo $category["name"]; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <a href="<?php echo admin_url(
                                    "finance/conciliacao/conciliar/" .
                                        $conciliacao["id"]
                                ); ?>" class="btn btn-success">
                                    Conciliar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php init_tail(); ?>
