<?php
/** Test-only render boundary: warnings never become layout text, even if a view catches them. */
function strictFixtureRender(callable $render): string
{
    $level = ob_get_level();
    $diagnostics = [];
    set_error_handler(static function (int $severity, string $message, string $file, int $line) use (&$diagnostics): bool {
        $error = new ErrorException($message, 0, $severity, $file, $line);
        $diagnostics[] = $error;
        throw $error;
    });
    ob_start();
    try {
        $render();
        if ($diagnostics) { throw $diagnostics[0]; }
        return ob_get_clean();
    } finally {
        while (ob_get_level() > $level) { ob_end_clean(); }
        restore_error_handler();
    }
}
