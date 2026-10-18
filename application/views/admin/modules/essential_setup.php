<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Concluir configuração de Emitentes</title>
</head>
<body>
    <main>
        <h1>Concluir configuração obrigatória</h1>
        <p>Emitentes é um recurso essencial do CRM. Para concluir a instalação ou atualização, execute suas migrations locais antes de acessar o painel.</p>
        <p>A empresa principal e as configurações legadas serão preservadas. Esta etapa não cria outra empresa, não copia credenciais e não libera cobrança multiemitente.</p>
        <?php if ($error !== null) { ?><p role="alert"><?= html_escape($error); ?></p><?php } ?>
        <?= form_open(admin_url('essential_modules')); ?>
            <input type="hidden" name="essential_modules_csrf" value="<?= html_escape($token); ?>">
            <button type="submit">Concluir configuração de Emitentes</button>
        <?= form_close(); ?>
        <p><a href="<?= admin_url('authentication/logout'); ?>">Sair</a></p>
    </main>
</body>
</html>
