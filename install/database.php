<?php echo '<form action="" method="post" accept-charset="utf-8">'; ?>
<?php echo '<input type="hidden" name="step" value="' . $current_step . '">'; ?>
<div class="form-group">
    <label for="hostname" class="control-label">Servidor do banco</label>
    <input type="text" class="form-control" name="hostname" value="localhost">
</div>
<div class="form-group">
    <label for="database" class="control-label">Nome do banco de dados</label>
    <input type="text" class="form-control" name="database">
</div>
<div class="form-group">
    <label for="username" class="control-label">Usuário</label>
    <input type="text" class="form-control" name="username">
</div>
<div class="form-group">
    <label for="password" class="control-label"><i class="glyphicon glyphicon-info-sign"
            title='Evite aspas simples e duplas na senha do banco'></i>
        Senha</label>
    <input type="password" class="form-control" name="password">
</div>
<hr class="-tw-mx-4" />
<div class="text-right">
    <button type="submit" class="btn btn-primary">Testar conexão</button>
</div>
</form>