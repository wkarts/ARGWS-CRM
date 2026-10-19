<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><main class="content"><div class="panel_s"><div class="panel-body">
    <h1>Responsável da instalação</h1>
    <?php if ($error) { ?><p class="alert alert-danger" role="alert"><?php echo html_escape($error); ?></p><?php } ?>
    <?php if ($success) { ?><p class="alert alert-success" role="status">Responsável designado. O acesso às empresas atuais e futuras é automático. As funcionalidades ainda indisponíveis continuam bloqueadas.</p><?php } ?>
    <?php if ($unresolved) { ?>
        <p>A instalação legada não possui prova suficiente para identificar o administrador original. Este procedimento designa um responsável; ele não comprova quem realizou a instalação histórica.</p>
        <p>Solicite ao responsável autorizado pela hospedagem uma autorização temporária para seu colaborador de ID <strong><?php echo (int)$actor_id; ?></strong>. Ela vale por 15 minutos, pode ser usada apenas por você e é revogada ao emitir outra.</p>
        <?php echo form_open(admin_url('company_master')); ?>
        <input type="hidden" name="company_master_csrf" value="<?php echo html_escape($company_master_csrf); ?>">
        <div class="form-group"><label for="authorization_code">Código de autorização da instalação</label><input class="form-control" id="authorization_code" name="authorization_code" required pattern="[a-f0-9]{64}" minlength="64" maxlength="64" autocomplete="off" spellcheck="false"></div>
        <div class="form-group"><label for="current_password">Sua senha atual</label><input class="form-control" type="password" id="current_password" name="current_password" required maxlength="4096" autocomplete="current-password"></div>
        <p>Após cinco senhas incorretas, solicite uma nova autorização. Códigos e senhas não são reapresentados no formulário.</p>
        <button type="submit" class="btn btn-primary">Confirmar designação de responsável</button>
        <?php echo form_close(); ?>
    <?php } elseif (!$success) { ?><p role="status">A instalação já possui um responsável definido. Este procedimento não permite substituí-lo.</p><?php } ?>
    <p class="tw-mt-4"><a href="<?php echo admin_url('issuers'); ?>">Empresas</a> · <a href="<?php echo admin_url('staff'); ?>">Colaboradores e permissões</a></p>
</div></div></main></div>
<?php init_tail(); ?></body></html>
