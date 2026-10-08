<h4 class="bold text-success">Instalação concluída!</h4>

<?php if (isset($config_copy_failed)) { ?>
<p class="text-danger">
    Não foi possível copiar application/config/app-config-sample.php. Acesse application/config/,
    copie app-config-sample.php e renomeie a cópia para app-config.php.
</p>
<?php } ?>

<p>
    <b>Remova o diretório install</b> e entre como administrador em
    <a href="<?php echo htmlspecialchars((string) $_POST['base_url'], ENT_QUOTES, 'UTF-8'); ?>admin"
       target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars((string) $_POST['base_url'], ENT_QUOTES, 'UTF-8'); ?>admin</a>.
</p>

<hr />
<h4><b>Lembretes</b></h4>
<ul class="list-unstyled">
    <li>Administradores e funcionários acessam <a href="<?php echo htmlspecialchars((string) $_POST['base_url'], ENT_QUOTES, 'UTF-8'); ?>admin"
        target="_blank" rel="noopener noreferrer">a área administrativa</a>.</li>
    <li>Contatos de clientes acessam <a href="<?php echo htmlspecialchars((string) $_POST['base_url'], ENT_QUOTES, 'UTF-8'); ?>clients"
        target="_blank" rel="noopener noreferrer">a área do cliente</a>.</li>
</ul>
<hr />
<p>Consulte as versões e os pacotes oficiais do ARGWS CRM no
    <a href="https://github.com/wkarts/argws-crm/releases" target="_blank" rel="noopener noreferrer">canal de distribuição ARGWS</a>.
</p>
<p>O atendimento aos clientes da instalação pode ser configurado em <b>Configurações &gt; Plataforma ARGWS</b>.</p>
