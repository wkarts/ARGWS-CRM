<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper"><main class="content"><div class="panel_s"><div class="panel-body">
    <h1>Responsável da instalação</h1>
    <?php if ($unresolved) { ?>
        <p role="status">Seu usuário ainda não possui a responsabilidade geral da instalação. A criação de empresas permanece restrita ao administrador original confirmado. Não é necessário informar senha ou obter código temporário de hospedagem nesta tela.</p>
    <?php } else { ?><p role="status">Sua responsabilidade pela instalação já está vinculada. O acesso às empresas segue as permissões atuais.</p><?php } ?>
    <p><a href="<?php echo html_escape(admin_url('issuers')); ?>">Voltar para Empresas</a></p>
</div></div></main></div>
<?php init_tail(); ?></body></html>
