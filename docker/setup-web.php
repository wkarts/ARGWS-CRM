<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once '/opt/argws-crm-provisioner/provisioner.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$configDirectory = getenv('ARGWS_CONFIG_DIR');
$configDirectory = is_string($configDirectory) ? rtrim($configDirectory, DIRECTORY_SEPARATOR) : '';
$markerPath = $configDirectory !== '' ? $configDirectory . '/provisioned' : '';
$statePath = $configDirectory !== '' ? $configDirectory . '/provisioning-state.json' : '';

if ($markerPath !== '' && is_file($markerPath)) {
    http_response_code(404);
    exit;
}

$setupSecret = getenv('ARGWS_SETUP_TOKEN');
$setupAvailable = is_string($setupSecret) && preg_match('/\A[a-f0-9]{64}\z/i', $setupSecret) === 1;
$resumingSetup = $statePath !== '' && is_file($statePath);

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
if (!$setupAvailable) {
    $error = 'A configuração inicial está temporariamente indisponível. Entre em contato com o responsável por esta instalação.';
    $httpStatus = 503;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && $setupAvailable) {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    $postedSecret = $_POST['setup_token'] ?? '';
    if (!is_string($postedCsrf) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)
        || !is_string($postedSecret) || !hash_equals($setupSecret, trim($postedSecret))) {
        $error = 'A chave de acesso ou a sessão expirou. Atualize a página e tente novamente.';
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
                header('Location: /admin/authentication', true, 303);
                exit;
            } catch (Throwable $exception) {
                $diagnostic = sanitize_provisioning_diagnostic($exception->getMessage(), [
                    is_string($_POST['setup_token'] ?? null) ? $_POST['setup_token'] : '',
                    is_string($_POST['admin_password'] ?? null) ? $_POST['admin_password'] : '',
                    is_string($_POST['admin_password_repeat'] ?? null) ? $_POST['admin_password_repeat'] : '',
                    is_string($_POST['admin_email'] ?? null) ? $_POST['admin_email'] : '',
                ]);
                error_log('[ARGWS CRM setup] Provisionamento não concluído: ' . get_class($exception) . ($diagnostic !== '' ? ': ' . $diagnostic : ''));
                $failure = $exception->getMessage();
                if (str_contains($failure, 'não contém tabelas') || str_contains($failure, 'já contém tabelas')) {
                    $error = 'O banco de dados já contém tabelas. Nenhum dado foi alterado. Para proteger os registros existentes, o assistente não pode reutilizá-lo como uma instalação nova.';
                    $httpStatus = 409;
                } elseif (str_contains($failure, 'schema parcial')) {
                    $error = 'A estrutura inicial começou, mas não foi concluída. O CRM continua bloqueado e nenhum dado foi removido. Revise os logs do serviço antes de retomar.';
                    $httpStatus = 409;
                } elseif (str_contains($failure, 'MIGRATIONS_PENDING')) {
                    $error = 'A base foi preparada, mas as atualizações necessárias não terminaram. O acesso ao CRM permanece bloqueado. Corrija o serviço e envie o formulário novamente para retomar sem duplicar o administrador.';
                    $httpStatus = 503;
                } else {
                    $error = 'Não foi possível concluir a configuração. Nenhum dado existente foi removido. Confira a conexão do serviço e tente novamente.';
                    $httpStatus = 503;
                }
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                $resumingSetup = $statePath !== '' && is_file($statePath);
            }
        }
    }
}

http_response_code($httpStatus);
$csrfToken = $_SESSION['csrf_token'];
$value = static function (string $key): string {
    return isset($_POST[$key]) && is_string($_POST[$key]) ? escape_html($_POST[$key]) : '';
};
$timezoneValue = $value('timezone') !== '' ? $value('timezone') : 'America/Sao_Paulo';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <link rel="icon" type="image/png" href="/assets/images/argws/favicon.png">
    <title>Configuração inicial | ARGWS CRM</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #142033;
            background: #f4f7fb;
            font-synthesis: none;
            text-rendering: optimizeLegibility;
            --navy: #11223d;
            --blue: #2265dc;
            --blue-dark: #174bb0;
            --line: #e1e7f0;
            --muted: #64748b;
        }
        * { box-sizing: border-box; }
        [hidden] { display: none !important; }
        body {
            min-height: 100vh;
            margin: 0;
            padding: clamp(12px, 2vw, 28px);
            display: grid;
            place-items: center;
            align-items: safe center;
            background:
                radial-gradient(circle at 85% 12%, rgba(44, 111, 218, .10), transparent 26rem),
                linear-gradient(135deg, #f7f9fc 0%, #edf2f8 100%);
        }
        .layout {
            width: min(1360px, 100%);
            display: grid;
            grid-template-columns: minmax(290px, .68fr) minmax(0, 1.32fr);
            overflow: hidden;
            border: 1px solid rgba(214, 223, 236, .9);
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 28px 80px rgba(21, 43, 77, .12);
        }
        .brand-panel {
            position: relative;
            padding: clamp(26px, 3vw, 42px);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            color: #fff;
            background:
                radial-gradient(circle at 15% 90%, rgba(49, 126, 246, .38), transparent 18rem),
                linear-gradient(155deg, #132947 0%, #0d1b32 100%);
        }
        .brand-panel::after {
            content: "";
            position: absolute;
            width: 350px;
            height: 350px;
            right: -180px;
            top: 34%;
            border: 1px solid rgba(255,255,255,.10);
            border-radius: 50%;
            box-shadow: 0 0 0 42px rgba(255,255,255,.025), 0 0 0 86px rgba(255,255,255,.02);
            pointer-events: none;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 16px;
            font-weight: 800;
            letter-spacing: .03em;
        }
        .brand-logo { display: block; width: min(180px, 65%); height: auto; }
        .brand-product {
            border-left: 1px solid rgba(255,255,255,.26);
            padding-left: 16px;
            color: rgba(228,237,250,.84);
            font-size: 11px;
            font-weight: 650;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .brand-copy { position: relative; z-index: 1; margin-top: clamp(26px, 5vh, 64px); }
        .eyebrow {
            margin: 0 0 10px;
            color: #a9c9ff;
            font-size: 12px;
            font-weight: 750;
            letter-spacing: .14em;
            text-transform: uppercase;
        }
        .brand-copy h1 {
            max-width: 360px;
            margin: 0;
            font-size: clamp(30px, 3vw, 42px);
            line-height: 1.12;
            letter-spacing: -.035em;
        }
        .brand-copy > p {
            max-width: 360px;
            margin: 14px 0 0;
            color: rgba(237,244,255,.76);
            font-size: 15px;
            line-height: 1.7;
        }
        .steps {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 17px;
            margin: 30px 0 0;
            padding: 0;
            list-style: none;
        }
        .steps li { display: grid; grid-template-columns: 30px 1fr; align-items: center; gap: 12px; color: rgba(230,239,252,.62); }
        .step-number {
            width: 30px;
            height: 30px;
            display: grid;
            place-items: center;
            border: 1px solid rgba(255,255,255,.25);
            border-radius: 50%;
            font-size: 12px;
            font-weight: 750;
        }
        .steps .active { color: #fff; }
        .steps .active .step-number { border-color: #83b1ff; background: #2468dc; box-shadow: 0 0 0 5px rgba(66,132,231,.18); }
        .steps .done .step-number { border-color: #60d3a2; color: #8cf0c4; }
        .step-label { font-size: 13px; font-weight: 650; }
        .brand-footer { position: relative; z-index: 1; margin-top: auto; padding-top: 24px; color: rgba(222,232,247,.58); font-size: 12px; }
        .content { min-width: 0; padding: clamp(24px, 3vw, 42px) clamp(26px, 4vw, 56px); align-self: center; }
        .content-header { margin-bottom: 20px; }
        .content-header .eyebrow { margin-bottom: 10px; color: var(--blue); }
        .content-header h2 { margin: 0; font-size: clamp(25px, 2.5vw, 32px); letter-spacing: -.035em; }
        .content-header p { max-width: 540px; margin: 10px 0 0; color: var(--muted); font-size: 14px; line-height: 1.6; }
        .alert {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin: 0 0 22px;
            padding: 14px 16px;
            border: 1px solid #f2c9c7;
            border-radius: 12px;
            color: #8f2723;
            background: #fff5f4;
            font-size: 13px;
            line-height: 1.55;
        }
        .alert-icon { flex: 0 0 auto; font-weight: 800; }
        .resume-alert { border-color: #bfdbfe; color: #1d4d89; background: #eff6ff; }
        form { display: grid; gap: 13px; }
        .section-heading { display: flex; align-items: center; gap: 12px; margin: 2px 0 0; color: #1e2d43; font-size: 13px; font-weight: 750; }
        .section-heading::after { content: ""; height: 1px; flex: 1; background: var(--line); }
        .field-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 16px; }
        .field { min-width: 0; }
        .field.full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 7px; color: #28364a; font-size: 12px; font-weight: 700; }
        .input-wrap { position: relative; }
        input {
            width: 100%;
            min-height: 44px;
            padding: 10px 13px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            outline: none;
            color: #16243a;
            background: #fff;
            font: inherit;
            font-size: 14px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        input:focus { border-color: #5591f3; box-shadow: 0 0 0 3px rgba(52,117,225,.13); }
        input::placeholder { color: #94a3b8; }
        .with-toggle { padding-right: 72px; }
        .toggle-password {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);
            border: 0;
            padding: 6px 8px;
            color: #48617f;
            background: transparent;
            font: inherit;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
        }
        .helper { margin: 6px 0 0; color: #718096; font-size: 11px; line-height: 1.45; }
        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 14px;
            border: 1px solid #d9e7fb;
            border-radius: 11px;
            color: #385777;
            background: #f5f9ff;
            font-size: 12px;
            line-height: 1.55;
        }
        .security-note strong { color: #24466e; }
        .submit-row { display: flex; align-items: center; gap: 16px; margin-top: 2px; }
        .submit-button {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border: 0;
            border-radius: 10px;
            padding: 0 20px;
            color: #fff;
            background: var(--blue);
            box-shadow: 0 6px 14px rgba(34,101,220,.18);
            font: inherit;
            font-size: 14px;
            font-weight: 750;
            cursor: pointer;
            transition: background .15s ease, transform .15s ease;
        }
        .submit-button:hover { background: var(--blue-dark); transform: translateY(-1px); }
        .submit-button:disabled { opacity: .72; cursor: wait; transform: none; }
        .submit-hint { margin: 0; color: #718096; font-size: 11px; line-height: 1.5; }
        .spinner {
            width: 16px;
            height: 16px;
            display: inline-block;
            border: 2px solid rgba(255,255,255,.45);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .unavailable {
            padding: 22px;
            border: 1px solid #e6ecf4;
            border-radius: 14px;
            background: #f8fafc;
        }
        .unavailable h3 { margin: 0 0 8px; font-size: 16px; }
        .unavailable p { margin: 0; color: var(--muted); font-size: 13px; line-height: 1.6; }
        @media (max-width: 860px) {
            body { padding: 18px; }
            .layout { grid-template-columns: 1fr; min-height: 0; }
            .brand-panel { padding: 28px 30px; }
            .brand-copy { margin-top: 36px; }
            .brand-copy h1 { max-width: 620px; font-size: 30px; }
            .brand-copy > p { max-width: 620px; }
            .steps { grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 28px; }
            .steps li { grid-template-columns: 30px 1fr; gap: 8px; }
            .step-label { font-size: 11px; }
            .brand-footer { padding-top: 24px; }
            .content { padding: 34px 30px; }
        }
        @media (max-width: 560px) {
            body { padding: 0; background: #fff; }
            .layout { width: 100%; border: 0; border-radius: 0; box-shadow: none; }
            .brand-panel { padding: 22px 22px 25px; }
            .brand-logo { width: min(152px, 60%); }
            .brand-copy { margin-top: 26px; }
            .brand-copy h1 { font-size: 27px; }
            .brand-copy > p { margin-top: 10px; font-size: 13px; }
            .steps { gap: 6px; margin-top: 21px; }
            .steps li { display: flex; align-items: center; }
            .step-label { display: none; }
            .content { padding: 28px 22px 34px; }
            .field-grid { grid-template-columns: 1fr; gap: 14px; }
            .field.full { grid-column: auto; }
            .submit-row { align-items: stretch; flex-direction: column; gap: 10px; }
            .submit-button { width: 100%; }
            .submit-hint { text-align: center; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="brand-panel" aria-label="Etapas da configuração">
        <div class="brand">
            <img class="brand-logo" src="/assets/images/argws/logo-light.png" alt="ARGWS Sistemas">
            <span class="brand-product">CRM</span>
        </div>
        <div class="brand-copy">
            <p class="eyebrow">Configuração inicial</p>
            <h1>Seu ambiente começa aqui.</h1>
            <p>Conclua os dados do administrador principal. O ARGWS CRM prepara a estrutura do banco e aplica as atualizações antes de liberar o acesso.</p>
        </div>
        <ol class="steps">
            <li class="done"><span class="step-number" aria-hidden="true">✓</span><span class="step-label">Serviços conectados</span></li>
            <li class="active" aria-current="step"><span class="step-number">1</span><span class="step-label">Administrador principal</span></li>
            <li><span class="step-number">2</span><span class="step-label">Acesso ao CRM</span></li>
        </ol>
        <div class="brand-footer">ARGWS CRM <span aria-hidden="true">·</span> Instalação segura</div>
    </aside>
    <main class="content">
        <header class="content-header">
            <p class="eyebrow">Etapa 1 de 2</p>
            <h2><?= $resumingSetup ? 'Concluir configuração' : 'Criar acesso principal' ?></h2>
            <p>Informe os dados da instalação e defina as credenciais do primeiro administrador. Ao finalizar, você será levado à tela de acesso.</p>
        </header>

        <?php if ($error !== ''): ?>
            <div class="alert" role="alert"><span class="alert-icon" aria-hidden="true">!</span><span><?= escape_html($error) ?></span></div>
        <?php endif; ?>

        <?php if ($resumingSetup): ?>
            <div class="alert resume-alert" role="status"><span class="alert-icon" aria-hidden="true">i</span><span>A base e o administrador desta tentativa já foram criados. Use o mesmo endereço e e-mail; a senha inicial não será alterada durante a retomada.</span></div>
        <?php endif; ?>

        <?php if (!$setupAvailable): ?>
            <section class="unavailable">
                <h3>Configuração temporariamente indisponível</h3>
                <p>O responsável por esta instalação precisa concluir a preparação do serviço antes de você continuar.</p>
            </section>
        <?php else: ?>
        <form method="post" action="/setup" autocomplete="off" id="setup-form">
            <input type="hidden" name="csrf_token" value="<?= escape_html($csrfToken) ?>">

            <div class="section-heading">Acesso de configuração</div>
            <div class="field-grid">
                <div class="field full">
                    <label for="setup_token">Chave de ativação</label>
                    <input id="setup_token" name="setup_token" type="password" maxlength="64" pattern="[A-Fa-f0-9]{64}" required autocomplete="off" aria-describedby="setup-token-help">
                    <p class="helper" id="setup-token-help">Chave de uso único para proteger a criação do primeiro administrador.</p>
                </div>
                <div class="field full">
                    <label for="base_url">Endereço público do CRM</label>
                    <input id="base_url" name="base_url" type="url" required placeholder="https://crm.exemplo.com/" value="<?= $value('base_url') ?>" autocomplete="url">
                    <p class="helper">Use o endereço HTTPS que seus usuários vão acessar.</p>
                </div>
            </div>

            <div class="section-heading">Administrador principal</div>
            <div class="field-grid">
                <div class="field">
                    <label for="firstname">Nome</label>
                    <input id="firstname" name="firstname" type="text" maxlength="50" required autocomplete="given-name" value="<?= $value('firstname') ?>">
                </div>
                <div class="field">
                    <label for="lastname">Sobrenome</label>
                    <input id="lastname" name="lastname" type="text" maxlength="50" required autocomplete="family-name" value="<?= $value('lastname') ?>">
                </div>
                <div class="field full">
                    <label for="admin_email">E-mail</label>
                    <input id="admin_email" name="admin_email" type="email" maxlength="100" required autocomplete="email" value="<?= $value('admin_email') ?>">
                </div>
                <div class="field">
                    <label for="admin_password">Senha</label>
                    <div class="input-wrap">
                        <input class="with-toggle" id="admin_password" name="admin_password" type="password" required autocomplete="new-password" aria-describedby="password-help">
                        <button class="toggle-password" type="button" data-password-toggle="admin_password" aria-label="Exibir senha">Exibir</button>
                    </div>
                    <p class="helper" id="password-help">Escolha qualquer senha que você queira usar. Não há tamanho mínimo.</p>
                </div>
                <div class="field">
                    <label for="admin_password_repeat">Confirmar senha</label>
                    <div class="input-wrap">
                        <input class="with-toggle" id="admin_password_repeat" name="admin_password_repeat" type="password" required autocomplete="new-password">
                        <button class="toggle-password" type="button" data-password-toggle="admin_password_repeat" aria-label="Exibir confirmação">Exibir</button>
                    </div>
                </div>
                <div class="field full">
                    <label for="timezone">Fuso horário</label>
                    <input id="timezone" name="timezone" list="timezone-options" type="text" required value="<?= escape_html($timezoneValue) ?>" autocomplete="off">
                    <datalist id="timezone-options">
                        <option value="America/Sao_Paulo"></option>
                        <option value="America/Bahia"></option>
                        <option value="America/Belem"></option>
                        <option value="America/Manaus"></option>
                        <option value="America/Rio_Branco"></option>
                        <option value="UTC"></option>
                    </datalist>
                </div>
            </div>

            <div class="security-note">
                <span aria-hidden="true">🔒</span>
                <span><strong>Seus dados ficam nesta instalação.</strong> O assistente não registra sua senha e só libera o CRM depois de preparar e validar o banco.</span>
            </div>

            <div id="provisioning-status" class="security-note" role="status" aria-live="polite" hidden>
                <span class="spinner" aria-hidden="true"></span>
                <span>Preparando a base, aplicando as atualizações e criando o acesso principal. Não feche esta página.</span>
            </div>

            <div class="submit-row">
                <button class="submit-button" id="submit-button" type="submit">
                    <span>Configurar e criar administrador</span>
                    <span aria-hidden="true">→</span>
                </button>
                <p class="submit-hint">O processo pode levar alguns minutos na primeira execução.</p>
            </div>
        </form>
        <?php endif; ?>
    </main>
</div>
<script>
    document.querySelectorAll('[data-password-toggle]').forEach(function (button) {
        button.addEventListener('click', function () {
            var input = document.getElementById(button.getAttribute('data-password-toggle'));
            var reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.textContent = reveal ? 'Ocultar' : 'Exibir';
            button.setAttribute('aria-label', reveal ? 'Ocultar senha' : 'Exibir senha');
        });
    });

    var setupForm = document.getElementById('setup-form');
    if (setupForm) {
        setupForm.addEventListener('submit', function (event) {
            if (!setupForm.reportValidity()) {
                event.preventDefault();
                return;
            }
            var submitButton = document.getElementById('submit-button');
            var status = document.getElementById('provisioning-status');
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Preparando instalação…</span>';
            status.hidden = false;
        });
    }
</script>
</body>
</html>
