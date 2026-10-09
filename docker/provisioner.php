<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

define('PHPASS_HASH_STRENGTH', 8);
define('PHPASS_HASH_PORTABLE', false);
require_once __DIR__ . '/sqlparser.php';
require_once __DIR__ . '/phpass.php';

function sanitize_provisioning_diagnostic(string $message, array $additionalSecrets = []): string
{
    $secrets = array_merge([
        getenv('ARGWS_SETUP_TOKEN') ?: '',
        getenv('ARGWS_DB_PASSWORD') ?: '',
        getenv('ARGWS_SETUP_MIGRATION_TOKEN') ?: '',
    ], $additionalSecrets);
    foreach ($secrets as $secret) {
        if (is_string($secret) && $secret !== '') {
            $message = str_replace($secret, '[redigido]', $message);
        }
    }

    $message = preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}/', '[e-mail]', $message) ?? $message;
    $message = preg_replace('/[\\r\\n\\t]+/', ' ', $message) ?? $message;

    return mb_substr(trim($message), 0, 3000, 'UTF-8');
}

function provision_error(string $message): void
{
    throw new RuntimeException($message);
}

function required_environment(string $key): string
{
    $value = getenv($key);
    if ($value === false || trim($value) === '') {
        provision_error('Variável de ambiente obrigatória ausente: ' . $key);
    }
    return $value;
}

function prompt_value(string $label, string $default = ''): string
{
    fwrite(STDOUT, $label . ($default !== '' ? ' [' . $default . ']' : '') . ': ');
    $value = fgets(STDIN);
    if ($value === false) {
        provision_error('Não foi possível ler a resposta do terminal.');
    }
    $value = trim($value);
    return $value === '' ? $default : $value;
}

function prompt_secret(string $label): string
{
    if (!function_exists('exec') || !is_file('/dev/tty')) {
        provision_error('Use um terminal interativo para digitar a senha sem eco.');
    }

    fwrite(STDOUT, $label . ': ');
    exec('stty -echo < /dev/tty 2>/dev/null', $output, $status);
    if ($status !== 0) {
        provision_error('Use um terminal interativo para digitar a senha sem eco.');
    }

    try {
        $value = fgets(STDIN);
    } finally {
        exec('stty echo < /dev/tty 2>/dev/null', $restoreOutput, $restoreStatus);
        fwrite(STDOUT, PHP_EOL);
        if ($restoreStatus !== 0) {
            provision_error('Não foi possível restaurar a exibição do terminal.');
        }
    }

    if ($value === false) {
        provision_error('Não foi possível ler a senha do terminal.');
    }
    return rtrim($value, "\r\n");
}

function collect_input(array $arguments): array
{
    if (count($arguments) === 2 && $arguments[1] === '--input-json') {
        $raw = stream_get_contents(STDIN);
        try {
            $input = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            provision_error('Entrada JSON de provisionamento inválida.');
        }
        if (!is_array($input)) {
            provision_error('A entrada de provisionamento deve ser um objeto JSON.');
        }
        return $input;
    }

    if (count($arguments) !== 1) {
        provision_error('Uso interativo: php provision.php');
    }

    $baseUrl = prompt_value('URL pública da instalação');
    $firstname = prompt_value('Nome');
    $lastname = prompt_value('Sobrenome');
    $email = prompt_value('E-mail do administrador');
    $password = prompt_secret('Senha do primeiro administrador');
    $confirmation = prompt_secret('Confirme a senha');
    return [
        'base_url' => $baseUrl,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'admin_email' => $email,
        'admin_password' => $password,
        'admin_password_repeat' => $confirmation,
        'timezone' => prompt_value('Fuso horário', 'America/Sao_Paulo'),
    ];
}

function validate_input(array $input): array
{
    foreach (['base_url', 'firstname', 'lastname', 'admin_email', 'admin_password', 'timezone'] as $key) {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            provision_error('Campo obrigatório ausente ou inválido: ' . $key);
        }
    }

    $baseUrl = trim($input['base_url']);
    $parts = parse_url($baseUrl);
    if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false
        || !is_array($parts)
        || !isset($parts['host'])
        || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        provision_error('Informe uma URL absoluta válida, com protocolo HTTP ou HTTPS.');
    }

    $firstname = trim($input['firstname']);
    $lastname = trim($input['lastname']);
    $email = trim($input['admin_email']);
    if ($firstname === '' || mb_strlen($firstname) > 50 || $lastname === '' || mb_strlen($lastname) > 50) {
        provision_error('Nome e sobrenome são obrigatórios e devem ter até 50 caracteres.');
    }
    if (strlen($email) > 100 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        provision_error('Informe um e-mail válido com até 100 caracteres.');
    }
    if ($input['admin_password'] === '') {
        provision_error('A senha do administrador é obrigatória.');
    }
    if (!isset($input['admin_password_repeat']) || !is_string($input['admin_password_repeat'])) {
        provision_error('Confirme a senha do administrador.');
    }
    if ($input['admin_password'] !== $input['admin_password_repeat']) {
        provision_error('As senhas informadas não coincidem.');
    }

    $timezone = trim($input['timezone']);
    try {
        new DateTimeZone($timezone);
    } catch (Throwable $exception) {
        provision_error('Fuso horário inválido.');
    }

    return [
        'base_url' => rtrim($baseUrl, '/') . '/',
        'firstname' => $firstname,
        'lastname' => $lastname,
        'admin_email' => $email,
        'admin_password' => $input['admin_password'],
        'timezone' => $timezone,
    ];
}

function contains_sample_placeholders(string $contents): bool
{
    return preg_match('/\[(?:base_url|encryption_key|db_hostname|db_username|db_password|db_name)\]/', $contents) === 1;
}

function save_application_config(string $configDirectory, array $input, array $database): void
{
    $samplePath = '/app/application/config/app-config-sample.php';
    $sample = file_get_contents($samplePath);
    if ($sample === false) {
        provision_error('O modelo de configuração do ARGWS CRM não está disponível na imagem.');
    }

    $replacements = [
        "'[base_url]'" => var_export($input['base_url'], true),
        "'[encryption_key]'" => var_export(bin2hex(random_bytes(16)), true),
        "'[db_hostname]'" => var_export($database['host'], true),
        "'[db_username]'" => var_export($database['user'], true),
        "'[db_password]'" => var_export($database['password'], true),
        "'[db_name]'" => var_export($database['name'], true),
    ];
    $contents = str_replace(array_keys($replacements), array_values($replacements), $sample);
    if (contains_sample_placeholders($contents)) {
        provision_error('Não foi possível gerar uma configuração completa para a instalação.');
    }

    $temporaryPath = $configDirectory . '/.app-config-' . bin2hex(random_bytes(8)) . '.tmp';
    if (file_put_contents($temporaryPath, $contents, LOCK_EX) !== strlen($contents)) {
        @unlink($temporaryPath);
        provision_error('Não foi possível gravar a configuração persistente.');
    }
    chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, $configDirectory . '/app-config.php')) {
        @unlink($temporaryPath);
        provision_error('Não foi possível ativar a configuração persistente.');
    }
}

function write_provisioning_state(string $configDirectory, array $input): void
{
    $state = [
        'format' => 1,
        'stage' => 'base_ready',
        'base_url' => $input['base_url'],
        'admin_email_sha256' => hash('sha256', strtolower($input['admin_email'])),
    ];
    $path = $configDirectory . '/provisioning-state.json';
    $temporaryPath = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
    $contents = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($contents) || file_put_contents($temporaryPath, $contents . PHP_EOL, LOCK_EX) === false) {
        @unlink($temporaryPath);
        provision_error('Não foi possível registrar o estágio seguro do provisionamento.');
    }
    chmod($temporaryPath, 0600);
    if (!rename($temporaryPath, $path)) {
        @unlink($temporaryPath);
        provision_error('Não foi possível ativar o estágio seguro do provisionamento.');
    }
    chmod($path, 0600);
}

function read_provisioning_state(string $path): array
{
    $contents = file_get_contents($path);
    $state = $contents === false ? null : json_decode($contents, true);
    if (!is_array($state) || ($state['format'] ?? null) !== 1 || ($state['stage'] ?? '') !== 'base_ready') {
        provision_error('O estado de uma tentativa anterior está inválido. A instalação continua bloqueada para proteger os dados.');
    }
    return $state;
}

function verify_recovery_config(string $configPath, array $input, array $database): void
{
    if (!is_file($configPath)) {
        provision_error('A tentativa anterior não concluiu a configuração persistente. Nenhuma tabela foi alterada nesta tentativa.');
    }

    if (!defined('BASEPATH')) {
        define('BASEPATH', '/app/system/');
    }
    require $configPath;

    $expected = [
        'APP_BASE_URL' => $input['base_url'],
        'APP_DB_HOSTNAME' => $database['host'],
        'APP_DB_NAME' => $database['name'],
        'APP_DB_USERNAME' => $database['user'],
        'APP_DB_PASSWORD' => $database['password'],
    ];
    foreach ($expected as $constant => $value) {
        if (!defined($constant) || !is_string(constant($constant)) || !hash_equals($value, constant($constant))) {
            provision_error('Os dados deste ambiente diferem da tentativa anterior. A instalação não foi alterada.');
        }
    }
}

function run_application_migrations(array $sensitiveValues = []): array
{
    if (!function_exists('proc_open')) {
        provision_error('O ambiente não permite iniciar o executor interno de migrations.');
    }

    $bridgeToken = bin2hex(random_bytes(32));
    $logPath = tempnam(sys_get_temp_dir(), 'argws-crm-migration-');
    if ($logPath === false) {
        provision_error('Não foi possível iniciar a validação final do banco de dados.');
    }
    chmod($logPath, 0600);

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['file', $logPath, 'w'],
        2 => ['file', $logPath, 'a'],
    ];
    $phpBinary = defined('PHP_BINARY') && is_string(PHP_BINARY) && trim(PHP_BINARY) !== ''
        ? PHP_BINARY
        : 'php';
    $environment = getenv();
    $environment = is_array($environment) ? $environment : [];
    $environment['ARGWS_SETUP_MIGRATION_TOKEN'] = $bridgeToken;
    $process = proc_open(
        [$phpBinary, '/app/index.php', 'argws_provisioning', 'apply_migrations'],
        $descriptors,
        $pipes,
        '/app',
        $environment
    );
    if (!is_resource($process)) {
        @unlink($logPath);
        provision_error('Não foi possível iniciar a aplicação das migrations pendentes.');
    }
    fclose($pipes[0]);
    $exitCode = proc_close($process);
    $output = file_get_contents($logPath);
    @unlink($logPath);

    $result = null;
    if ($output !== false) {
        $lines = preg_split('/\\R/', trim($output)) ?: [];
        foreach (array_reverse($lines) as $line) {
            $candidate = json_decode(trim($line), true);
            if (is_array($candidate) && array_key_exists('success', $candidate)) {
                $result = $candidate;
                break;
            }
        }
    }
    if ($exitCode !== 0 || !is_array($result) || empty($result['success'])
        || !isset($result['to_version']) || !is_numeric($result['to_version'])) {
        $diagnostic = $output === false ? '' : sanitize_provisioning_diagnostic($output, array_merge($sensitiveValues, [$bridgeToken]));
        if ($diagnostic !== '') {
            error_log('[ARGWS CRM setup] Diagnóstico do executor de migrations: ' . mb_substr($diagnostic, -1800, null, 'UTF-8'));
        }
        error_log('[ARGWS CRM setup] O executor interno não concluiu as migrations (código ' . (int) $exitCode . ').');
        provision_error('MIGRATIONS_PENDING: Não foi possível concluir a atualização necessária do banco. A instalação não foi liberada; tente novamente após verificar os logs do serviço.');
    }

    return $result;
}

function provision_with_input(array $rawInput): void
{
    // O setup só libera o CRM depois de criar o administrador e aplicar todas as migrations.
    $timeLimitRemoved = function_exists('set_time_limit') && set_time_limit(0);
    if (!$timeLimitRemoved && (int) ini_get('max_execution_time') > 0) {
        provision_error('O PHP está limitando a duração do provisionamento. Habilite set_time_limit ou defina max_execution_time=0 e tente novamente.');
    }

    $input = validate_input($rawInput);
    $configDirectory = required_environment('ARGWS_CONFIG_DIR');
    if (!is_dir($configDirectory) && !mkdir($configDirectory, 0700, true) && !is_dir($configDirectory)) {
        provision_error('Não foi possível acessar o volume persistente de configuração.');
    }

    $configPath = $configDirectory . '/app-config.php';
    $markerPath = $configDirectory . '/provisioned';
    $statePath = $configDirectory . '/provisioning-state.json';
    $lock = fopen($configDirectory . '/provision.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        provision_error('Outro processo está concluindo o provisionamento desta instalação.');
    }

    $db = null;
    try {
        if (is_file($markerPath)) {
            provision_error('Esta instalação já foi provisionada.');
        }

        $database = [
            'host' => required_environment('ARGWS_DB_HOST'),
            'name' => required_environment('ARGWS_DB_NAME'),
            'user' => required_environment('ARGWS_DB_USER'),
            'password' => required_environment('ARGWS_DB_PASSWORD'),
        ];

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli($database['host'], $database['user'], $database['password'], $database['name']);
        $db->set_charset('utf8mb4');

        if (is_file($statePath)) {
            $state = read_provisioning_state($statePath);
            if (($state['base_url'] ?? '') !== $input['base_url']
                || !hash_equals((string) ($state['admin_email_sha256'] ?? ''), hash('sha256', strtolower($input['admin_email'])))) {
                provision_error('Uma tentativa anterior já criou o administrador. Para retomá-la, use o mesmo endereço e o mesmo e-mail; a senha inicial permanece inalterada.');
            }
            verify_recovery_config($configPath, $input, $database);

            $admin = $db->prepare('SELECT COUNT(*) AS admin_count FROM tblstaff WHERE email = ? AND admin = 1 AND active = 1');
            $admin->bind_param('s', $input['admin_email']);
            $admin->execute();
            $adminCount = (int) $admin->get_result()->fetch_assoc()['admin_count'];
            $admin->close();
            if ($adminCount !== 1) {
                provision_error('Não foi possível confirmar o administrador criado na tentativa anterior. Nenhuma conta foi duplicada.');
            }
        } else {
            if (is_file($configPath)) {
                $existing = file_get_contents($configPath);
                if ($existing === false || !contains_sample_placeholders($existing)) {
                    provision_error('A instalação já tem configuração. O provisionador não altera instalações existentes.');
                }
            }

            $tableResult = $db->query(
                'SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = DATABASE()'
            );
            $tableCount = (int) $tableResult->fetch_assoc()['table_count'];
            if ($tableCount !== 0) {
                provision_error('O banco já contém tabelas. Nenhuma tabela foi alterada; instalações existentes devem seguir o fluxo normal de atualização.');
            }

            $schemaStarted = false;
            try {
                $parser = new SqlScriptParser();
                $statements = $parser->parse(__DIR__ . '/database.sql');
                foreach ($statements as $statement) {
                    $statement = $parser->removeComments($statement);
                    if ($statement === '') {
                        continue;
                    }
                    $schemaStarted = true;
                    $db->query($statement);
                }

                $staffCount = (int) $db->query('SELECT COUNT(*) AS staff_count FROM tblstaff')
                    ->fetch_assoc()['staff_count'];
                if ($staffCount !== 0) {
                    provision_error('O schema contém funcionários inesperados; a criação do administrador foi interrompida.');
                }

                $hasher = new PasswordHash(PHPASS_HASH_STRENGTH, PHPASS_HASH_PORTABLE);
                $passwordHash = $hasher->HashPassword($input['admin_password']);
                if (!is_string($passwordHash) || $passwordHash === '') {
                    provision_error('Não foi possível gerar o hash da senha do administrador.');
                }

                $createdAt = (new DateTimeImmutable('now', new DateTimeZone($input['timezone'])))->format('Y-m-d H:i:s');
                $insert = $db->prepare(
                    'INSERT INTO tblstaff (firstname, lastname, password, email, datecreated, admin, active) VALUES (?, ?, ?, ?, ?, 1, 1)'
                );
                $insert->bind_param(
                    'sssss',
                    $input['firstname'],
                    $input['lastname'],
                    $passwordHash,
                    $input['admin_email'],
                    $createdAt
                );
                $insert->execute();
                $insert->close();

                $update = $db->prepare("UPDATE tbloptions SET value = ? WHERE name = 'default_timezone'");
                $update->bind_param('s', $input['timezone']);
                $update->execute();
                $update->close();

                $installTime = (string) time();
                $update = $db->prepare("UPDATE tbloptions SET value = ? WHERE name = 'di'");
                $update->bind_param('s', $installTime);
                $update->execute();
                $update->close();

                save_application_config($configDirectory, $input, $database);
                write_provisioning_state($configDirectory, $input);
            } catch (Throwable $exception) {
                if ($db instanceof mysqli) {
                    $db->close();
                    $db = null;
                }
                if ($schemaStarted) {
                    throw new RuntimeException(
                        'O banco novo pode conter schema parcial. O provisionador não remove nem sobrescreve dados; revise os logs antes de repetir.',
                        0,
                        $exception
                    );
                }
                throw $exception;
            }
        }

        if ($db instanceof mysqli) {
            $db->close();
            $db = null;
        }

        run_application_migrations([$input['admin_password'], $input['admin_email']]);

        $markerContents = gmdate(DATE_ATOM) . PHP_EOL;
        if (file_put_contents($markerPath, $markerContents, LOCK_EX) !== strlen($markerContents)) {
            provision_error('As migrations foram concluídas, mas não foi possível registrar o estado final do provisionamento.');
        }
        chmod($markerPath, 0600);
        @unlink($statePath);
    } finally {
        if ($db instanceof mysqli) {
            $db->close();
        }
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}


function provision(array $arguments): void
{
    $input = collect_input($arguments);
    provision_with_input($input);
    $validated = validate_input($input);
    fwrite(STDOUT, "Provisionamento concluído. O schema, a configuração persistente e o primeiro administrador foram preparados." . PHP_EOL);
    fwrite(STDOUT, 'Administrador criado: ' . $validated['admin_email'] . PHP_EOL);
}
