<?php echo '<form action="" method="post" accept-charset="utf-8" id="installForm">'; ?>
<?php echo '<input type="hidden" name="step" value="' . (int) $current_step . '">'; ?>
<?php foreach (['hostname', 'username', 'password', 'database'] as $field) { ?>
    <input type="hidden" name="<?= htmlspecialchars($field, ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars((string) ($_POST[$field] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
<?php } ?>
<div class="form-group">
    <label for="base_url" class="control-label">Endereço base</label>
    <p class="text-muted">Informe a URL pública desta instalação, incluindo o protocolo e o caminho, se houver.</p>
    <input type="url" class="form-control" value="<?= htmlspecialchars($this->guess_base_url(), ENT_QUOTES, 'UTF-8'); ?>" name="base_url" id="base_url" required>
</div>
<hr />
<h4>Acesso administrativo</h4>
<hr />
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="firstname" class="control-label">Nome</label>
            <input type="text" class="form-control" name="firstname" id="firstname" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="lastname" class="control-label">Sobrenome</label>
            <input type="text" class="form-control" name="lastname" id="lastname" required>
        </div>
    </div>
</div>
<div class="form-group">
    <label for="admin_email" class="control-label">E-mail</label>
    <input type="email" class="form-control" name="admin_email" id="admin_email" required>
</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="admin_password" class="control-label">Senha</label>
            <input type="password" class="form-control" name="admin_password" id="admin_password" required>
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="admin_passwordr" class="control-label">Confirme a senha</label>
            <input type="password" class="form-control" name="admin_passwordr" id="admin_passwordr" required>
        </div>
    </div>
</div>
<h5>Outras configurações</h5>
<hr />
<div class="form-group">
    <label for="timezone" class="control-label">Fuso horário</label>
    <select name="timezone" data-live-search="true" id="timezone" class="form-control" required data-none-selected-text="Selecione o fuso horário">
        <option value=""></option>
        <?php foreach ($this->get_timezones_list() as $key => $timezones) { ?>
            <optgroup label="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8'); ?>">
                <?php foreach ($timezones as $timezone) { ?>
                    <option value="<?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars($timezone, ENT_QUOTES, 'UTF-8'); ?></option>
                <?php } ?>
            </optgroup>
        <?php } ?>
    </select>
</div>
<hr class="-tw-mx-4" />
<div class="text-right">
    <button type="submit" class="btn btn-success" id="installBtn">Instalar</button>
</div>
</form>
