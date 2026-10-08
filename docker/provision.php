<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}

error_reporting(E_ALL);
ini_set('display_errors', 'stderr');

define('PHPASS_HASH_STRENGTH', 8);
define('PHPASS_HASH_PORTABLE', false);
require_once __DIR__ . '/sqlparser.php';
require_once __DIR__ . '/phpass.php';

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

    $password = prompt_secret('Senha do primeiro administrador');
    $confirmation = prompt_secret('Confirme a senha');
    return [
        'base_url' => prompt_value('URL pública da instalação'),
        'firstname' => prompt_value('Nome'),
        'lastname' => prompt_value('Sobrenome'),
        'admin_email' => prompt_value('E-mail do administrador'),
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
    if (strlen($input['admin_password']) < 12) {
        provision_error('A senha deve ter pelo menos 12 caracteres.');
    }
    if (isset($input['admin_password_repeat']) && $input['admin_password'] !== $input['admin_password_repeat']) {
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

function provision(array $arguments): void
{
    $configDirectory = required_environment('ARGWS_CONFIG_DIR');
    if (!is_dir($configDirectory) && !mkdir($configDirectory, 0700, true) && !is_dir($configDirectory)) {
        provision_error('Não foi possível acessar o volume persistente de configuração.');
    }

    $configPath = $configDirectory . '/app-config.php';
    $markerPath = $configDirectory . '/provisioned';
    $lock = fopen($configDirectory . '/provision.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        provision_error('Outro processo está provisionando esta instalação.');
    }

    try {
        if (is_file($markerPath)) {
            provision_error('Esta instalação já foi provisionada.');
        }
        if (is_file($configPath)) {
            $existing = file_get_contents($configPath);
            if ($existing === false || !contains_sample_placeholders($existing)) {
                provision_error('A instalação já tem configuração. O provisionador não altera instalações existentes.');
            }
        }

        $input = validate_input(collect_input($arguments));
        $database = [
            'host' => required_environment('ARGWS_DB_HOST'),
            'name' => required_environment('ARGWS_DB_NAME'),
            'user' => required_environment('ARGWS_DB_USER'),
            'password' => required_environment('ARGWS_DB_PASSWORD'),
        ];

        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = new mysqli($database['host'], $database['user'], $database['password'], $database['name']);
        $db->set_charset('utf8mb4');

        $tableResult = $db->query(
            'SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = DATABASE()'
        );
        $tableCount = (int) $tableResult->fetch_assoc()['table_count'];
        if ($tableCount !== 0) {
            $db->close();
            provision_error('O banco não está vazio. Nenhuma tabela foi alterada; use o fluxo de atualização para bancos existentes.');
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
            if (file_put_contents($markerPath, gmdate(DATE_ATOM) . PHP_EOL, LOCK_EX) === false) {
                provision_error('A configuração foi gravada, mas não foi possível registrar o estado do provisionamento.');
            }
            chmod($markerPath, 0600);
            $db->close();
        } catch (Throwable $exception) {
            $db->close();
            if ($schemaStarted) {
                throw new RuntimeException(
                    'O banco novo pode conter schema parcial. Não repita sobre esse banco; revise os logs e reinicie somente com uma base vazia após confirmar que não há dados de cliente.',
                    0,
                    $exception
                );
            }
            throw $exception;
        }

        fwrite(STDOUT, "Provisionamento concluído. Reinicie o serviço web para liberar a aplicação.\n");
        fwrite(STDOUT, 'Administrador criado: ' . $input['admin_email'] . PHP_EOL);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

try {
    provision($argv);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Erro no provisionamento: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
