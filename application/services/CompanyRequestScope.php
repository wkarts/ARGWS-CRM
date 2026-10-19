<?php

declare(strict_types=1);
require_once __DIR__ . '/CompanyContext.php';
require_once __DIR__ . '/issuers/IssuerCore.php';

/** Pre-hydration browser boundary, deliberately separate from job/webhook service scopes. */
final class CompanyRequestScope
{
    private static ?CompanyContext $context = null;
    private static $owner = null;
    private static $release = null;
    private static bool $isolated = false;
    private static bool $nativeUpgradeOnly = false;
    private static bool $prepared = false;
    private static ?string $submittedToken = null;

    public static function interactive($ci): bool
    {
        if ($ci->input->is_cli_request() || defined('API')
            || (class_exists('InstallationMigrationBootstrap', false) && InstallationMigrationBootstrap::isRunning())) { return false; }
        $directory = trim((string) $ci->router->fetch_directory(), '/');
        $class = strtolower((string) $ci->router->fetch_class());
        if ($class === 'authentication') { return false; }
        // Module AdminControllers also require the browser boundary. Public/job routes never inspect session.
        return $directory === 'admin' || $ci instanceof AdminController;
    }

    public static function bootstrap($ci): void
    {
        if (!self::interactive($ci)) { return; }
        $ci->load->database();
        $ci->load->driver('session');
        self::beforeHydration($ci);
    }

    public static function beforeHydration($ci): void
    {
        if (!self::interactive($ci)) { return; }
        if (self::$prepared) {
            if (self::$owner !== $ci) { throw new LogicException('company_context_request_owner_mismatch'); }
            return;
        }
        self::$owner = $ci;
        self::$prepared = true;
        register_shutdown_function([self::class, 'finish']);
        $class = strtolower((string) $ci->router->fetch_class());
        $method = strtolower((string) $ci->router->fetch_method());
        $module = strtolower((string) $ci->router->fetch_module());
        $admin = trim((string) $ci->router->fetch_directory(), '/') === 'admin';
        $native = $admin && !$module;
        $companyScopedBbRoute = $admin && $module === 'banco_brasil' && $class === 'banco_brasil'
            && in_array($method, ['index', 'register_connection', 'register_layout', 'import_return'], true);
        self::$isolated = ($native && $class === 'company_context' && in_array($method, ['index', 'select'], true))
            || ($native && $class === 'company_onboarding' && $method === 'index')
            || ($native && $class === 'company_clients' && $method === 'index')
            || ($native && $class === 'company_invoices' && $method === 'index')
            || $companyScopedBbRoute;
        if (!IssuerCore::ready($ci->db, db_prefix())) {
            // Only native, non-module admin routes may enter the recovery gate.
            // Company selection and every module route remain unavailable until
            // the native migration repairs the company core.
            if (self::$isolated || !$native) {
                self::deny($ci, 'company_context_core_unavailable', 503);
            }
            self::$nativeUpgradeOnly = true;
            return;
        }
        // Autologin stays on Authentication. Never hydrate a requested company page before identity exists.
        if (!$ci->session->userdata('staff_logged_in')) {
            $ci->load->helper('url');
            redirect(self::adminUrl($ci, 'authentication'), 'location', 303);
            throw new RuntimeException('company_context_authentication_required');
        }
        try {
            self::$context = new CompanyContext($ci->db, db_prefix(), $ci->session, $ci->session->userdata('staff_user_id'));
            $verb = strtoupper($ci->input->method(true));
            $recover = self::$isolated && $method === 'index' && in_array($verb, ['GET', 'HEAD'], true);
            self::$context->resolve($recover, self::$isolated && $class === 'company_context');
            // Only the native Settings renderer proven to avoid legacy hooks/transports is admitted.
            if ($native && !self::$context->isPrimary() && (($class === 'settings' && $method === 'index')
                || ($class === 'issuers' && in_array($method, ['index', 'save'], true))
                || ($class === 'emails' && in_array($method, ['index', 'email_template'], true)))) {
                self::$isolated = true;
            }
            $token = $ci->input->get_request_header('X-Company-Context', false);
            $postedToken = $ci->input->post('_company_context', false);
            if ($token !== null && $token !== false && $postedToken !== null && $postedToken !== false && $token !== $postedToken) {
                throw new DomainException('company_context_mismatch');
            }
            $token = $token ?: $postedToken;
            if (!in_array($verb, ['GET', 'HEAD', 'OPTIONS'], true) || $token) { self::$context->assertToken($token); }
            self::$submittedToken = is_string($token) ? $token : null;
            // Middleware metadata must not enter legacy models that persist the complete POST array.
            unset($_POST['_company_context']);
            if (!self::$isolated && !self::$context->isPrimary()) { self::deny($ci, 'company_context_route_not_isolated', 403); }
            if (self::$context->issuerId() !== null) {
                require_once __DIR__ . '/CompanySettingsRuntime.php';
                self::$release = CompanySettingsRuntime::enterRequest($ci->db, db_prefix(), self::$context->issuerId(), self::$context->staffId());
            }
        } catch (DomainException $error) {
            self::deny($ci, $error->getMessage(), $error->getMessage() === 'company_context_mismatch' ? 409 : 403);
        } catch (Throwable $error) {
            if ($error instanceof CompanyRequestDenied) { throw $error; }
            log_message('error', 'Company request context unavailable; request denied before application hydration.');
            self::deny($ci, 'company_context_unavailable', 503);
        }
    }

    public static function isolated(): bool { return self::$isolated; }
    public static function nativeUpgradeOnly(): bool
    {
        return self::$prepared && self::$nativeUpgradeOnly;
    }
    /** Server-owned attestation; never derive this from a request flag. */
    public static function nativeUpgradeOnlyFor($ci): bool
    {
        return self::$prepared && self::$owner === $ci && self::$nativeUpgradeOnly;
    }
    /** Server-owned attestation; never derive this from a request flag. */
    public static function sealedFor($ci): bool
    {
        return self::$prepared && self::$owner === $ci && self::$isolated && self::$context !== null;
    }
    public static function context(): ?CompanyContext { return self::$context; }
    public static function submittedToken(): ?string { return self::$submittedToken; }
    public static function token(): ?string { return self::$context === null ? null : self::$context->token(); }
    public static function adminUrl($ci, string $path = ''): string
    {
        return $ci->config->site_url((defined('ADMIN_URI') ? ADMIN_URI : 'admin') . '/' . $path);
    }
    public static function invalidateCaches($ci): void
    {
        if (isset($ci->app) && method_exists($ci->app, 'clearCompanyCaches')) { $ci->app->clearCompanyCaches(); }
        if (isset($ci->app_object_cache)) { $ci->app_object_cache->flush(); }
        unset($GLOBALS['current_user']);
        // These are application caches only; the authentication identity is retained.
        foreach (['staff_permissions', 'company_permissions', '_prev_url'] as $key) { $ci->session->unset_userdata($key); }
    }
    public static function finish(): void
    {
        if (self::$release !== null) { $release = self::$release; self::$release = null; $release(); }
        self::$context = null;
        self::$owner = null;
        self::$isolated = false;
        self::$nativeUpgradeOnly = false;
        self::$prepared = false;
        self::$submittedToken = null;
    }
    private static function deny($ci, string $reason, int $status): void
    {
        self::finish();
        $message = $reason === 'company_context_mismatch'
            ? 'A empresa desta aba mudou. Recarregue a página antes de enviar novamente. Nenhuma operação foi executada.'
            : 'Esta operação não está disponível no contexto de empresa atual. Selecione uma empresa autorizada.';
        if ($status === 503) { $message = 'O contexto de empresas está indisponível. Conclua a atualização do núcleo ou consulte o administrador.'; }
        $url = htmlspecialchars(self::adminUrl($ci, 'company_context'), ENT_QUOTES, 'UTF-8');
        show_error($message . ' <a href="' . $url . '">Selecionar empresa</a>', $status);
        throw new CompanyRequestDenied($reason, $status); // Defensive stop if an alternative renderer returns.
    }
}

final class CompanyRequestDenied extends RuntimeException {}
