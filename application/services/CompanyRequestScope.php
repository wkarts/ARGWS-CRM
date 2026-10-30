<?php

declare(strict_types=1);
require_once __DIR__ . '/CompanyContext.php';
require_once __DIR__ . '/NativeCompanyRoutes.php';
require_once __DIR__ . '/NativeCompanyResourceId.php';
require_once __DIR__ . '/issuers/IssuerCore.php';

/** Pre-hydration browser boundary, deliberately separate from job/webhook service scopes. */
final class CompanyRequestScope
{
    private static ?CompanyContext $context = null;
    private static $owner = null;
    private static $release = null;
    private static bool $isolated = false;
    private static bool $nativePresentation = false;
    private static bool $nativeUpgradeOnly = false;
    private static bool $prepared = false;
    private static ?string $submittedToken = null;
    private static ?string $submittedNativeCsrf = null;

    public static function interactive($ci): bool
    {
        if ($ci->input->is_cli_request() || defined('API')
            || (class_exists('InstallationMigrationBootstrap', false) && InstallationMigrationBootstrap::isRunning())) { return false; }
        $directory = trim((string) $ci->router->fetch_directory(), '/');
        $class = strtolower((string) $ci->router->fetch_class());
        if ($class === 'authentication') { return false; }
        // Module AdminControllers also require the browser boundary. Public/job routes never inspect session.
        return $directory === 'admin' || $ci instanceof AdminController || self::companyBbRoute($ci)
            || self::staffCustomerDownloadRoute($ci);
    }

    /** The original public download controller also serves staff-owned files. */
    private static function staffCustomerDownloadRoute($ci): bool
    {
        return trim((string)$ci->router->fetch_directory(),'/') === ''
            && !(string)$ci->router->fetch_module()
            && strtolower((string)$ci->router->fetch_class()) === 'download'
            && strtolower((string)$ci->router->fetch_method()) === 'file'
            && isset($ci->uri) && in_array(strtolower((string)$ci->uri->segment(3)), ['client','ticket','project','sales_attachment'], true);
    }

    private static function companyBbRoute($ci):bool
    {
        // MX strips ADMIN_URI and records the module's controller directory, not admin/.
        $directory=trim((string)$ci->router->fetch_directory(),'/');
        return in_array($directory,['admin','../../modules/banco_brasil/controllers'],true)
            && strtolower((string)$ci->router->fetch_module())==='banco_brasil'
            && strtolower((string)$ci->router->fetch_class())==='banco_brasil'
            && in_array(strtolower((string)$ci->router->fetch_method()),['index','local_noncash','preview_checkout','publish_checkout','save_channel_preferences','register_connection','register_layout','import_return','declare_contract','generate_remittance','retry_remittance','download_remittance','download_return','report_transmission','link_event','review_event','settle_event','settle_components_event','settle_tariff_event','declare_external_reversal','declare_api_profile','save_api_credentials','issue_api','resume_api_issue','query_api','cancel_api','settle_api','amend_api_due','amend_api_abatement','amend_api_reference','amend_api_address','amend_api_discount','amend_api_receipt_window','cancel_api_discount','assign_api_discount','configure_api_webhook','query_api_movements'],true);
    }

    /** Primary contracts keep legacy numeric records while typed owned records use the scoped adapter. */
    private static function ownedContractRoute($ci,string $class,string $method):bool
    {
        if($class!=='contracts')return false;
        if(in_array($method,['types','type','delete_contract_type'],true)&&$ci->input->get('source',false)==='company')return true;
        if($method==='file')return true;
        if(!in_array($method,['contract','pdf','send_to_email','renew','copy','delete','get_comments','add_comment','edit_comment','remove_comment','mark_as_signed','unmark_as_signed','clear_signature','save_contract_data','add_note','get_notes','add_external_attachment','delete_renewal','add_contract_attachment','delete_contract_attachment'],true))return false;
        if($method==='contract'&&$ci->input->get('source',false)==='company')return true;
        $argument='';
        if(isset($ci->uri)&&method_exists($ci->uri,'rsegment'))$argument=(string)$ci->uri->rsegment(3);
        if($method==='delete_renewal'&&isset($ci->uri)&&method_exists($ci->uri,'rsegment'))$argument=(string)$ci->uri->rsegment(4);
        if($method==='delete_contract_attachment'&&preg_match('/^[a-f0-9]{64}$/D',$argument))return true;
        if(in_array($method,['renew','add_comment','edit_comment','remove_comment','save_contract_data','add_external_attachment'],true))$argument=$ci->input->post('contract_id',false)??$ci->input->post('contractid',false)??'';
        if(!is_string($argument)&&!is_int($argument))return false;if($argument==='')return false;
        try{return NativeCompanyResourceId::decode('contract',$argument)['source_kind']==='company_contract';}
        catch(DomainException $error){
            // Legacy contract identifiers are signed INT values and therefore
            // below the typed namespace radix. A high numeric value is always
            // a typed-route attempt and must fail in the scoped adapter instead
            // of falling through to the historical controller.
            return preg_match('/^[1-9][0-9]{0,15}$/D',$argument)===1
                && strlen($argument)>=10 && (int)$argument>=4294967296;
        }
    }

    /**
     * The primary company keeps the historical Clients page by default.
     * A company-owned customer opened with the explicit source marker is
     * admitted to the same bounded native adapter used by secondary companies.
     * The marker is part of the server-attested route and is never accepted as
     * a generic request switch on unrelated Clients actions.
     */
    private static function ownedClientRoute($ci,string $class,string $method):bool
    {
        if ($class !== 'clients') return false;
        // Every company now uses the original Clients controller. The native
        // route catalogue is the allowlist and the context middleware is the
        // server-owned attestation; no query flag or parallel URL selects the
        // company adapter. This also covers DataTables and auxiliary POSTs,
        // whose transport does not carry a query string.
        return NativeCompanyRoutes::admits('admin','', $class, $method,
            strtoupper((string) $ci->input->method(true)));
    }

    /** Primary payment history keeps its original adapter; named-company records use the same owned actions. */
    private static function ownedPaymentsRoute($ci,string $class,string $method):bool
    {
        if($class!=='payments'||!NativeCompanyRoutes::admits('admin','',$class,$method,strtoupper((string)$ci->input->method(true))))return false;
        if(in_array($method,['index','list_payments','table','batch_payment_modal','add_batch_payment'],true))return true;
        if(!in_array($method,['payment','pdf','send_to_email','delete'],true))return false;
        $id=isset($ci->uri)&&method_exists($ci->uri,'rsegment')?(string)$ci->uri->rsegment(3):'';
        if(!preg_match('/^[1-9][0-9]*$/D',$id))return true;
        $row=IssuerStorage::query($ci->db,'SELECT i.issuer_id FROM '.db_prefix().'invoicepaymentrecords p JOIN '.db_prefix().'invoices i ON i.id=p.invoiceid WHERE p.id=?',[$id])->row_array();
        return !$row||$row['issuer_id']!==null;
    }

    /** Preserve historical primary routes while resolving typed operational records identically. */
    private static function ownedOperationalRoute($ci,string $class,string $method):bool
    {
        if(!in_array($class,['projects','tasks'],true))return false;
        if(!NativeCompanyRoutes::admits('admin','',$class,$method,strtoupper((string)$ci->input->method(true))))return false;
        if($class==='projects'&&in_array($method,['index','table','staff_projects'],true))return true;
        if($class==='tasks'&&in_array($method,['index','list_tasks','table','kanban','switch_kanban','update_order','tasks_kanban_load_more'],true))return true;
        $argument=isset($ci->uri)&&method_exists($ci->uri,'rsegment')?(string)$ci->uri->rsegment(3):'';
        if($class==='projects'){
            if($method==='project'&&$argument===''&&!$ci->input->get('via_estimate_id'))return true;
            require_once __DIR__.'/NativeCompanyProjectRuleCommands.php';
            $command=$ci->input->post('action',false);
            if(is_string($command)&&in_array($command,NativeCompanyProjectRuleCommands::ROUTES[$method]??[],true))return true;
            if($method==='milestone'&&$command==='milestone')return true;
            $candidates=[$argument,(isset($ci->uri)&&method_exists($ci->uri,'rsegment')?(string)$ci->uri->rsegment(4):''),$ci->input->get('project_id',false),$ci->input->post('project_id',false)];$kind='project';$sources=['company_project'];
        }else{
            $candidates=[$argument,$ci->input->post('taskid',false),$ci->input->post('task_id',false),$ci->input->post('timesheet_task_id',false),$ci->input->get('task_id',false)];$kind='task';$sources=['company_task','company_standalone_task'];
            if(in_array($method,['mark_as','change_priority'],true)&&isset($ci->uri)&&method_exists($ci->uri,'rsegment'))$candidates[]=(string)$ci->uri->rsegment(4);
            if($method==='task'&&$argument==='')return true;
            if($method==='detailed_overview'){ $project=$ci->input->post('project_id',false)??$ci->input->get('project_id',false); if($project!==null&&$project!==''){ try{if(NativeCompanyResourceId::decode('project',$project)['source_kind']==='company_project')return true;}catch(DomainException $error){} } }

        }
        foreach($candidates as$id){if((!is_string($id)&&!is_int($id))||$id==='')continue;
            try{if(in_array(NativeCompanyResourceId::decode($kind,$id)['source_kind'],$sources,true))return true;}
            catch(DomainException $error){if(preg_match('/^[1-9][0-9]{9,15}$/D',(string)$id)&&(int)$id>=4294967296)return true;}
        }
        return false;
    }

    public static function bootstrap($ci): void
    {
        if (!self::interactive($ci)) { return; }
        // Preserve the framework's database-before-session ordering (some
        // installations store sessions in the database). Public customers
        // without staff sessions continue through the legacy/portal path.
        $staffCustomerDownload = self::staffCustomerDownloadRoute($ci);
        $ci->load->database();
        $ci->load->driver('session');
        if ($staffCustomerDownload && !$ci->session->userdata('staff_logged_in')) { return; }
        self::beforeHydration($ci);
    }

    public static function beforeHydration($ci): void
    {
        if (!self::interactive($ci)) { return; }
        $staffCustomerDownload = self::staffCustomerDownloadRoute($ci);
        if ($staffCustomerDownload && (!isset($ci->session) || !$ci->session->userdata('staff_logged_in'))) { return; }
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
        $companyScopedBbRoute = self::companyBbRoute($ci);
        self::$isolated = ($native && $class === 'issuers' && in_array($method, ['lookup', 'select', 'onboarding'], true))
            || ($native && $class === 'clients' && in_array($method,['index','table','client','form_contact','contacts','all_contacts','consents','contact_email_exists','change_contact_status','lookup','check_duplicate_customer_name','change_client_status','delete_contact','confirm_registration','delete_contact_profile_image','upload_attachment','delete_attachment','add_external_attachment','update_file_share_visibility','assign_admins','delete_customer_admin','save_longitude_and_latitude','get_vault_entry','vault_entry_create','vault_entry_update','vault_entry_delete','vault_encrypt_password','share_identity','contact_invoice_access','groups','group','delete_group','import','get_customer_billing_and_shipping_details','contact_profile_image'],true))
            || ($native && $class === 'leads' && in_array($method,['index','file','export'],true))
            || ($native && (($class === 'projects' && in_array($method,['extras','portal_rules','billing_rates','fixed_billing','copies','time_billing'],true)) || ($class === 'tasks' && in_array($method,['copies','recurring','standalone','billing','time_batches'],true)) || ($class === 'tickets' && in_array($method,['ai','connect','connect_api'],true)) || ($class === 'invoices' && $method === 'standalone_billing') || ($class === 'expenses' && $method === 'purchases') || ($class === 'settings' && in_array($method,['pusher','asaas','imports','efi','microsoft_mail_oauth','google_mail_oauth','api_tokens'],true)) || ($class === 'staff' && in_array($method,['hr','hr_file','hr_pdf','hr_export','hr_mine','hr_mine_file'],true))))
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
            $nativeDashboard = $native && $class === 'dashboard' && $method === 'index' && in_array($verb, ['GET', 'HEAD'], true);
            $recover = $nativeDashboard || (self::$isolated && $method === 'index' && in_array($verb, ['GET', 'HEAD'], true));
            self::$context->resolve($recover, $nativeDashboard || (self::$isolated && $class === 'issuers' && $method === 'select'));
            // Principal-company historical files keep their original controller and
            // hydration path; only secondary-company files need the scoped reader.
            $downloadKind=$staffCustomerDownload?strtolower((string)$ci->uri->segment(3)):'';
            $downloadKey=$staffCustomerDownload?(string)$ci->uri->segment(4):'';
            $ownedStaffDownload=$staffCustomerDownload&&(
                (in_array($downloadKind,['ticket','project'],true)&&preg_match('/^[a-f0-9]{64}$/D',$downloadKey))
                ||($downloadKind==='sales_attachment'&&preg_match('/^[a-f0-9]{32}$/D',$downloadKey))
            );
            if ($staffCustomerDownload && self::$context->isPrimary() && !$ownedStaffDownload) { self::$context = null; return; }
            // Exact native actions use company data adapters, never the legacy hydration path.
            // Utilities' finite calendar actions share the same adapters for the
            // principal and named companies, including historical event lineage.
            // Other Utilities actions retain their existing admission policy.
            $sharedCalendar = $native && $class === 'utilities'
                && in_array($method,['calendar','get_calendar_data','view_event','delete_event'],true);
            // Recurring invoices have one canonical implementation on Invoices::recurring.
            // It is a shared native action for the principal and named companies alike;
            // the retired parallel invoice-recurring surface is deliberately not admitted.
            $sharedInvoiceRecurring = $native && $class === 'invoices' && $method === 'recurring';
            // Settings uses the same bounded, issuer-owned forms for the
            // principal and named companies, including asset actions.
            $sharedSettings = $native && $class === 'settings'
                && in_array($method,['index','remove_company_logo','remove_signature_image','remove_fv','company_asset','delete_tag'],true);
            // Customer import/export uses one owned writer and the original
            // Clients route in principal and named companies alike.
            $sharedClientImport = $native && $class === 'clients' && $method === 'import';
            $sharedClientLookup = $native && $class === 'clients' && $method === 'lookup';
            $sharedOwnedContract=$native&&self::ownedContractRoute($ci,$class,$method);
            $sharedOwnedClient = $native && self::ownedClientRoute($ci, $class, $method);
            $sharedOwnedOperational = $native && (self::ownedOperationalRoute($ci,$class,$method)||self::ownedPaymentsRoute($ci,$class,$method));
            $sharedOwnedLead = $native && $class === 'leads'
                && in_array($method, ['index', 'file', 'export'], true);
            // Estimates and proposals already dispatch to the bounded
            // NativeCompanySalesAdminHttp adapter. Mark their official list,
            // form, PDF and due-date actions as shared so the principal company
            // follows the same issuer-scoped path as secondary companies.
            $sharedOwnedSales = $native && in_array($class, ['estimates', 'proposals'], true)
                && in_array($method, ['index', 'list_estimates', 'estimate', 'pdf', 'get_due_date', 'list_proposals', 'proposal', 'table', 'proposal_relations', 'pipeline', 'get_pipeline', 'pipeline_load_more'], true);
            // The company selector is an isolated technical POST, not a native
            // application page. It has its own switch CSRF and must not be
            // forced through the native presentation CSRF contract.
            $technicalSelector = $native && $class === 'issuers'
                && in_array($method, ['lookup', 'select', 'onboarding'], true);
            self::$nativePresentation = !$technicalSelector && (!self::$context->isPrimary() || $companyScopedBbRoute || $sharedCalendar || $sharedInvoiceRecurring || $sharedSettings || $sharedClientImport || $sharedClientLookup || $sharedOwnedContract || $sharedOwnedClient || $sharedOwnedLead || $sharedOwnedSales || $sharedOwnedOperational || $ownedStaffDownload) && (
                NativeCompanyRoutes::admits((string) $ci->router->fetch_directory(), $module, $class, $method, $verb)
                || ($staffCustomerDownload && in_array($verb, ['GET','HEAD'], true))
            );
            if ($staffCustomerDownload && !self::$nativePresentation) self::deny($ci, 'native_customer_file_method_invalid', 405);
            if($companyScopedBbRoute&&!self::$nativePresentation)self::deny($ci,'company_context_route_not_isolated',403);
            if($sharedCalendar&&!self::$nativePresentation)self::deny($ci,'native_calendar_method_invalid',405);
            if($sharedInvoiceRecurring&&!self::$nativePresentation)self::deny($ci,'native_invoice_recurring_method_invalid',405);
            if($sharedSettings&&!self::$nativePresentation)self::deny($ci,'native_settings_method_invalid',405);
            if($sharedClientImport&&!self::$nativePresentation)self::deny($ci,'native_client_import_method_invalid',405);
            if($sharedClientLookup&&!self::$nativePresentation)self::deny($ci,'native_client_lookup_method_invalid',405);
            if($sharedOwnedContract&&!self::$nativePresentation)self::deny($ci,'native_contract_method_invalid',405);
            if($sharedOwnedClient&&!self::$nativePresentation)self::deny($ci,'native_client_method_invalid',405);
            if($sharedOwnedLead&&!self::$nativePresentation)self::deny($ci,'native_lead_method_invalid',405);
            if($sharedOwnedSales&&!self::$nativePresentation)self::deny($ci,'native_sales_method_invalid',405);
            // Route-specific adapters report an unsupported HTTP verb as 405.
            // The generic isolated-route denial remains the final fallback for
            // actions that are not part of one of those explicit contracts.
            if (self::$isolated && !self::$nativePresentation && !$technicalSelector) {
                self::deny($ci, 'company_context_route_not_isolated', 403);
            }
            if (self::$nativePresentation) { self::$isolated = true; }
            // Only the native Settings renderer proven to avoid legacy hooks/transports is admitted.
            if ($native && !self::$context->isPrimary() && (($class === 'issuers' && in_array($method, ['index', 'save'], true))
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
            $nativeHeader = $ci->input->get_request_header('X-Native-Company-CSRF', false);
            $nativeBody = $ci->input->post('_native_company_csrf', false);
            if ($nativeHeader !== null && $nativeHeader !== false && $nativeBody !== null && $nativeBody !== false && $nativeHeader !== $nativeBody) {
                throw new DomainException('native_company_csrf_mismatch');
            }
            $nativeCsrf = $nativeHeader ?: $nativeBody;
            self::$submittedNativeCsrf = is_string($nativeCsrf) ? $nativeCsrf : null;
            if (self::$nativePresentation && !in_array($verb, ['GET', 'HEAD'], true)) {
                self::assertNativeMutation($ci);
            }
            unset($_POST['_native_company_csrf']);
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
    /** Native presentation is a server-owned request attestation, not a query parameter. */
    public static function nativeFor($ci): bool
    {
        return self::sealedFor($ci) && self::$nativePresentation;
    }
    public static function nativeRouteAllowed($ci, string $route): bool
    {
        return self::nativeFor($ci) && NativeCompanyRoutes::navigation($route);
    }
    public static function nativeCsrfToken($ci): ?string
    {
        // An existing principal page may submit a native calendar/module action.
        // Deliver the context-bound token to that authenticated document too;
        // possession does not attest nativeFor() or admit any route. The target
        // still requires its native seal, verb, both tokens and current ACL.
        return self::$prepared && self::$owner === $ci && self::$context !== null
            ? self::$context->nativeCsrf() : null;
    }
    public static function assertNativeMutation($ci): void
    {
        if (!self::nativeFor($ci) || strtoupper($ci->input->method(true)) !== 'POST'
            || self::$submittedNativeCsrf === null
            || !hash_equals(self::$context->nativeCsrf(), self::$submittedNativeCsrf)) {
            throw new DomainException('native_company_csrf_invalid');
        }
        self::$context->assertToken(self::$submittedToken);
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
        self::$nativePresentation = false;
        self::$nativeUpgradeOnly = false;
        self::$prepared = false;
        self::$submittedToken = null;
        self::$submittedNativeCsrf = null;
    }
    private static function deny($ci, string $reason, int $status): void
    {
        if(PHP_SAPI!=='cli'&&strtolower((string)$ci->router->fetch_class())==='utilities'&&in_array(strtolower((string)$ci->router->fetch_method()),['calendar','get_calendar_data','view_event','delete_event'],true))error_log('Native calendar boundary rejected ['.(in_array($reason,['company_context_mismatch','native_calendar_method_invalid','native_company_csrf_invalid','native_company_csrf_mismatch','company_context_unavailable','company_context_route_not_isolated'],true)?$reason:'unclassified_boundary').']; no request values logged.');
        self::finish();
        $message = $reason === 'company_context_mismatch'
            ? 'A empresa desta aba mudou. Recarregue a página antes de enviar novamente. Nenhuma operação foi executada.'
            : 'Esta operação não está disponível no contexto de empresa atual. Selecione uma empresa autorizada.';
        if ($status === 503) { $message = 'O contexto de empresas está indisponível. Conclua a atualização do núcleo ou consulte o administrador.'; }
        $url = htmlspecialchars(self::adminUrl($ci), ENT_QUOTES, 'UTF-8');
        show_error($message . ' <a href="' . $url . '">Selecionar empresa</a>', $status);
        throw new CompanyRequestDenied($reason, $status); // Defensive stop if an alternative renderer returns.
    }
}

final class CompanyRequestDenied extends RuntimeException {}
