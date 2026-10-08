<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once '/opt/argws-crm-provisioner/provisioner.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_installation_ready(): bool
{
    $directory = getenv('ARGWS_CONFIG_DIR');
    if ($directory === false || trim($directory) === '') {
        return false;
    }

    $path = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'app-config.php';
    if (!is_file($path)) {
        return false;
    }
    $contents = file_get_contents($path);
    return $contents !== false && !contains_sample_placeholders($contents);
}

if (is_installation_ready()) {
    http_response_code(404);
    exit;
}

$setupSecret = getenv('ARGWS_SETUP_TOKEN');
if (!is_string($setupSecret) || preg_match('/\A[a-f0-9]{64}\z/i', $setupSecret) !== 1) {
    http_response_code(503);
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Configuração pendente</title>';
    echo '<h1>Configuração pendente</h1><p>Defina ARGWS_SETUP_TOKEN como um segredo aleatório de 64 caracteres hexadecimais no arquivo .env antes de abrir o assistente.</p>';
    exit;
}

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
session_name('argws_crm_setup');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/setup',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Strict',
]);
ini_set('session.use_strict_mode', '1');
session_start();

if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';
$httpStatus = 200;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    $postedSecret = $_POST['setup_token'] ?? '';
    if (!is_string($postedCsrf) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)
        || !is_string($postedSecret) || !hash_equals($setupSecret, trim($postedSecret))) {
        $error = 'A chave temporária ou a sessão do formulário não é válida. Atualize a página e tente novamente.';
        $httpStatus = 403;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } else {
        $input = [
            'base_url' => $_POST['base_url'] ?? null,
            'firstname' => $_POST['firstname'] ?? null,
            'lastname' => $_POST['lastname'] ?? null,
            'admin_email' => $_POST['admin_email'] ?? null,
            'admin_password' => $_POST['admin_password'] ?? null,
            'admin_password_repeat' => $_POST['admin_password_repeat'] ?? null,
            'timezone' => $_POST['timezone'] ?? 'America/Sao_Paulo',
        ];

        try {
            validate_input($input);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
            $httpStatus = 422;
        }

        if ($error === '') {
            try {
                provision_with_input($input);
                $_SESSION = [];
                session_destroy();
                header('Location: /', true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log('[ARGWS CRM setup] ' . $exception->getMessage());
                $error = 'Não foi possível concluir a instalação. Verifique o endereço e as credenciais do banco nos logs do serviço e tente novamente.';
                $httpStatus = 503;
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            }
        }
    }
}

http_response_code($httpStatus);
$csrfToken = $_SESSION['csrf_token'];
$value = static function (string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? escape_html($_POST[$key]) : '';
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Configurar ARGWS CRM</title>
    <style>
        :root { color-scheme: light; font-family: system-ui, sans-serif; background: #f3f4f6; color: #17202a; }
        body { margin: 0; padding: 2rem 1rem; }
        main { max-width: 42rem; margin: 2rem auto; padding: 2rem; background: #fff; border-radius: .75rem; box-shadow: 0 1rem 3rem #0001; }
        h1 { margin-top: 0; }
        p, label { line-height: 1.5; }
        .notice { padding: 1rem; background: #eff6ff; border-left: .25rem solid #2563eb; }
        .error { padding: 1rem; background: #fef2f2; color: #991b1b; border-left: .25rem solid #dc2626; }
        .field { margin: 1rem 0; }
        label { display: block; margin-bottom: .35rem; font-weight: 650; }
        input { box-sizing: border-box; width: 100%; padding: .7rem; border: 1px solid #9ca3af; border-radius: .35rem; font: inherit; }
        button { padding: .75rem 1rem; border: 0; border-radius: .35rem; background: #155eef; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
        small { color: #4b5563; }
    </style>
</head>
<body>
<main>
    <h1>Configurar ARGWS CRM</h1>
    <p class="notice">Esta tela aparece somente enquanto a instalação ainda não foi configurada. O schema será criado apenas se o banco estiver vazio.</p>
    <?php if ($error !== ''): ?><p class="error"><?= escape_html($error) ?></p><?php endif; ?>
    <form method="post" action="/setup" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">
        <div class="field">
            <label for="setup_token">Chave temporária de configuração</label>
            <input id="setup_token" name="setup_token" type="password" required autocomplete="current-password">
            <small>Copie ARGWS_SETUP_TOKEN do arquivo .env da implantação. Não compartilhe essa chave.</small>
        </div>
        <div class="field">
            <label for="base_url">URL pública da instalação</label>
            <input id="base_url" name="base_url" type="url" required placeholder="https://crm.exemplo.com/" value="<?= $value('base_url') ?>">
        </div>
        <div class="field">
            <label for="firstname">Nome do administrador</label>
            <input id="firstname" name="firstname" type="text" maxlength="50" required autocomplete="given-name" value="<?= $value('firstname') ?>">
        </div>
        <div class="field">
            <label for="lastname">Sobrenome do administrador</label>
            <input id="lastname" name="lastname" type="text" maxlength="50" required autocomplete="family-name" value="<?= $value('lastname') ?>">
        </div>
        <div class="field">
            <label for="admin_email">E-mail do administrador</label>
            <input id="admin_email" name="admin_email" type="email" maxlength="100" required autocomplete="email" value="<?= $value('admin_email') ?>">
        </div>
        <div class="field">
            <label for="admin_password">Senha do administrador</label>
            <input id="admin_password" name="admin_password" type="password" minlength="12" required autocomplete="new-password">
            <small>Use pelo menos 12 caracteres. A senha não será exibida nem registrada nos logs.</small>
        </div>
        <div class="field">
            <label for="admin_password_repeat">Confirme a senha</label>
            <input id="admin_password_repeat" name="admin_password_repeat" type="password" minlength="12" required autocomplete="new-password">
        </div>
        <div class="field">
            <label for="timezone">Fuso horário</label>
            <input id="timezone" name="timezone" type="text" required value="<?= $value('timezone') !== '' ? $value('timezone') : 'America/Sao_Paulo' ?>">
        </div>
        <button type="submit">Provisionar instalação</button>
    </form>
</main>
</body>
</html>
