<?php

/** Internal bridge from the standalone installer to the native CI migrations. */
final class InstallationMigrationBootstrap
{
    private static bool $running = false;

    public static function isRunning(): bool
    {
        return self::$running;
    }

    public static function run(string $root, int $createdStaffId): bool
    {
        // This context is server-created only after the installer inserts its
        // administrator. An HTTP parameter, cookie or route cannot grant it.
        if (defined('BASEPATH') || self::$running || $createdStaffId < 1
            || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
            || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== realpath($root . '/install/index.php')) {
            return false;
        }
        if (session_status() === PHP_SESSION_ACTIVE) { session_write_close(); }
        self::$running = true;
        define('ARGWS_INSTALLER_CREATED_STAFF_ID', $createdStaffId);
        $directory = getcwd();
        $post = $_POST;
        $get = $_GET;
        $server = $_SERVER;
        $request = $_REQUEST;
        $bufferLevel = ob_get_level();
        // A framework exit/fatal bypasses finally. The output handler still
        // runs at shutdown and replaces internal diagnostics with a safe error.
        ob_start(static function ($output, $phase) {
            if (self::$running && ($phase & PHP_OUTPUT_HANDLER_FINAL) && !($phase & PHP_OUTPUT_HANDLER_CLEAN)) {
                if (!headers_sent()) { http_response_code(503); }
                return '<p role="alert">Não foi possível concluir a instalação. Consulte os logs da hospedagem para recuperar a atualização.</p>';
            }
            return '';
        });
        try {
            // Do not expose installer credentials to application hooks/logging.
            // The outer installation POST owns its CSRF token. This fixed
            // in-process dispatch has no new browser request or public grant.
            $_POST = [];
            $_GET = [];
            $_REQUEST = [];
            $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
            $_SERVER['SCRIPT_NAME'] = '/index.php';
            $_SERVER['PHP_SELF'] = '/index.php';
            $_SERVER['REQUEST_URI'] = '/argws_provisioning/complete_installation';
            $_SERVER['QUERY_STRING'] = '';
            unset($_SERVER['PATH_INFO'], $_SERVER['ORIG_PATH_INFO']);
            $assign_to_config = ['csrf_protection' => false];
            $routing = ['directory' => '', 'controller' => 'Argws_provisioning', 'function' => 'complete_installation'];
            chdir($root);
            // CI's front controller normally runs in global scope. MX and
            // App_Controller consume these same globals; keep that contract
            // when invoking it from this guarded same-process adapter.
            global $BM, $EXT, $CFG, $UNI, $URI, $RTR, $OUT, $SEC, $IN, $LANG, $CI;
            require $root . '/index.php';
            $ci = &get_instance();
            return isset($ci->installationMigrationResult)
                && $ci->installationMigrationResult['success'] === true;
        } catch (Throwable $error) {
            $safeReason = 'other';
            $safeMessages = [
                'Using $this when not in object context' => 'this_without_object_context',
                'Class "Modules" not found' => 'mx_modules_class_missing',
                'Call to a member function item() on null' => 'mx_config_null',
                'Call to a member function item() on bool' => 'mx_config_bool',
                'Call to a member function item() on string' => 'mx_config_string',
                'Call to a member function item() on array' => 'mx_config_array',
                'Call to a member function item() on int' => 'mx_config_int',
                'Call to a member function item() on float' => 'mx_config_float',
            ];
            if (isset($safeMessages[$error->getMessage()])) {
                $safeReason = $safeMessages[$error->getMessage()];
            } elseif (preg_match('/^Call to undefined method [A-Za-z_\\\\][A-Za-z0-9_\\\\]*::item\\(\\)$/D', $error->getMessage())) {
                $safeReason = 'mx_config_item_method_missing';
            }
            $sourceFile = basename((string) $error->getFile());
            if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $sourceFile)) { $sourceFile = 'unknown'; }
            $safeTrace = [];
            foreach (array_slice($error->getTrace(), 0, 8) as $frame) {
                $frameFile = isset($frame['file']) ? basename((string) $frame['file']) : 'internal';
                if (!preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $frameFile)) { $frameFile = 'unknown'; }
                $frameLine = isset($frame['line']) ? (int) $frame['line'] : 0;
                $frameClass = isset($frame['class']) ? (string) $frame['class'] : '';
                $frameFunction = isset($frame['function']) ? (string) $frame['function'] : '';
                $symbol = preg_replace('/[^A-Za-z0-9_\\\\:>-]/', '', $frameClass . $frameFunction);
                $safeTrace[] = $frameFile . ':' . $frameLine . ($symbol === '' ? '' : ' ' . $symbol);
            }
            error_log('[ARGWS CRM install] Falha na atualização nativa: ' . get_class($error)
                . ' reason=' . $safeReason
                . ' @ ' . $sourceFile . ':' . (int) $error->getLine()
                . ' trace=' . implode(' <- ', $safeTrace));
            return false;
        } finally {
            while (ob_get_level() > $bufferLevel) { ob_end_clean(); }
            $_POST = $post;
            $_GET = $get;
            $_SERVER = $server;
            $_REQUEST = $request;
            chdir($directory);
            self::$running = false;
        }
    }
}
