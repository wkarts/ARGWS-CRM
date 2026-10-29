<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|   example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|   http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|   $route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|   $route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|   $route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples: my-controller/index -> my_controller/index
|       my-controller/my-method -> my_controller/my_method
*/

$route['default_controller']   = 'clients';
$route['404_override']         = '';
$route['translate_uri_dashes'] = false;

// Exact purchase namespace; the scope guard also checks the original native URI.
$route['company_purchase_portal']='purchase_portal/index';
$route['company_purchase_portal/index']='purchase_portal/index';
$route['purchase_portal']='purchase_portal/index';
$route['purchase_portal/index']='purchase_portal/index';
// Canonical controller files keep the historical machine namespaces as finite aliases.
foreach(['drafts','draft_change','draft_cancel','draft_issue','expenses','expense_categories','expense_files','local_noncash','payments','payment_reverse','credits','credit_pdf','credit_apply','credit_unapply','credit_refund','credit_refund_reverse','credit_void','invoices','invoice_pdf','financial'] as $machineAction){
    $route['company_api/'.$machineAction]='api/'.$machineAction;
    $route['company_api/'.$machineAction.'/([1-9][0-9]*)']='api/'.$machineAction.'/$1';
    $route['api/'.$machineAction]='api/'.$machineAction;
    $route['api/'.$machineAction.'/([1-9][0-9]*)']='api/'.$machineAction.'/$1';
}
unset($machineAction);
$route['company_api/expense_files/([1-9][0-9]*)/([a-f0-9]{64})']='api/expense_files/$1/$2';
$route['api/expense_files/([1-9][0-9]*)/([a-f0-9]{64})']='api/expense_files/$1/$2';
$route['company_asaas_callback/integration/([a-f0-9]{32})']='asaas_callback/integration/$1';
$route['asaas_callback/integration/([a-f0-9]{32})']='asaas_callback/integration/$1';
$route['company_hr_access/index/(hrco_[a-f0-9]{64})']='hr_access/index/$1';
$route['hr_access/index/(hrco_[a-f0-9]{64})']='hr_access/index/$1';
$route['company_portal/index/([1-9][0-9]{0,9})']='portal/index/$1';
$route['portal/index/([1-9][0-9]{0,9})']='portal/index/$1';
$route['company_recurring_worker/index']='recurring_worker/index';
$route['recurring_worker/index']='recurring_worker/index';
foreach(['suppliers','requests','quotes','orders','receipts','documents','payables','events','files','file_download','settings','reports','invites','order_pdf','export'] as $purchaseRoute){
    $route['purchases_api/index/'.$purchaseRoute]='purchases_api/index/'.$purchaseRoute;
    $route['purchases_api/index/'.$purchaseRoute.'/([1-9][0-9]{0,9})']='purchases_api/index/'.$purchaseRoute.'/$1';
}
unset($purchaseRoute);
foreach(['suppliers','requests','quotes','orders','receipts','documents','payables','events','files','file_download','settings','reports','invites','order_pdf','export'] as $machineAction){
    $route['company_purchases_api/index/'.$machineAction]='purchases_api/index/'.$machineAction;
    $route['company_purchases_api/index/'.$machineAction.'/([1-9][0-9]{0,9})']='purchases_api/index/'.$machineAction.'/$1';
}
unset($machineAction);


// Native operational bearer endpoints only. The controller scope independently
// attests raw URI, router method, canonical ID and persisted token origin.
foreach(['projects','tasks','standalone_tasks','contracts','tickets','task_schedules','standalone_schedules'] as $companyOperationRoute){
    $route['operations_api/'.$companyOperationRoute]='operations_api/'.$companyOperationRoute;
    $route['operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})']='operations_api/'.$companyOperationRoute.'/$1';
}
unset($companyOperationRoute);
foreach(['projects','tasks','standalone_tasks','contracts','tickets','task_schedules','standalone_schedules','project_files','task_files','contract_files','ticket_files','project_copies','project_milestones','task_dependencies','project_follow','task_follow','leads','lead_stages','lead_sources','lead_inbox','lead_assignees','lead_exports','lead_imports','lead_files','hr_structure','hr_employees','hr_profiles','hr_records','hr_contracts','hr_courses','hr_assignments','hr_accesses','hr_files','hr_reports','hr_imports','hr_articles','hr_documents'] as $machineAction){
    $route['company_operations_api/'.$machineAction]='operations_api/'.$machineAction;
    $route['company_operations_api/'.$machineAction.'/([1-9][0-9]{0,9})']='operations_api/'.$machineAction.'/$1';
}
unset($machineAction);
// Explicit reviewed subresources: opaque blob key is always subordinate to a canonical parent ID.
foreach(['project_files','task_files','contract_files','ticket_files'] as $companyOperationRoute){
    $route['operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})']='operations_api/'.$companyOperationRoute.'/$1';
    $route['operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})/([a-f0-9]{64})']='operations_api/'.$companyOperationRoute.'/$1/$2';
    $route['company_operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})']='operations_api/'.$companyOperationRoute.'/$1';
    $route['company_operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})/([a-f0-9]{64})']='operations_api/'.$companyOperationRoute.'/$1/$2';
}
$route['operations_api/project_copies/([1-9][0-9]{0,9})']='operations_api/project_copies/$1';
$route['company_operations_api/project_copies/([1-9][0-9]{0,9})']='operations_api/project_copies/$1';
foreach(['project_milestones','task_dependencies','project_follow','task_follow'] as $companyOperationRoute)$route['operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})']='operations_api/'.$companyOperationRoute.'/$1';
foreach(['project_milestones','task_dependencies','project_follow','task_follow'] as $companyOperationRoute)$route['company_operations_api/'.$companyOperationRoute.'/([1-9][0-9]{0,9})']='operations_api/'.$companyOperationRoute.'/$1';
unset($companyOperationRoute);

// Lead endpoints remain finite; import batches and file keys have distinct formats.
foreach(['leads','lead_stages','lead_sources','lead_inbox'] as $companyLeadRoute){
    $route['operations_api/'.$companyLeadRoute]='operations_api/'.$companyLeadRoute;
    $route['operations_api/'.$companyLeadRoute.'/([1-9][0-9]{0,9})']='operations_api/'.$companyLeadRoute.'/$1';
}
foreach(['lead_assignees','lead_exports','lead_imports'] as $companyLeadRoute)$route['operations_api/'.$companyLeadRoute]='operations_api/'.$companyLeadRoute;
$route['operations_api/lead_imports/([a-f0-9]{32})']='operations_api/lead_imports/$1';
$route['operations_api/lead_files/([1-9][0-9]{0,9})']='operations_api/lead_files/$1';
$route['operations_api/lead_files/([1-9][0-9]{0,9})/([a-f0-9]{64})']='operations_api/lead_files/$1/$2';
foreach(['lead_assignees','lead_exports','lead_imports'] as $companyLeadRoute)$route['company_operations_api/'.$companyLeadRoute]='operations_api/'.$companyLeadRoute;
$route['company_operations_api/lead_imports/([a-f0-9]{32})']='operations_api/lead_imports/$1';
$route['company_operations_api/lead_files/([1-9][0-9]{0,9})']='operations_api/lead_files/$1';
$route['company_operations_api/lead_files/([1-9][0-9]{0,9})/([a-f0-9]{64})']='operations_api/lead_files/$1/$2';
unset($companyLeadRoute);


/**
 * Dashboard clean route
 */
$route['admin'] = 'admin/dashboard';

/**
 * Misc controller routes
 */
$route['admin/access_denied'] = 'admin/misc/access_denied';
$route['admin/not_found']     = 'admin/misc/not_found';

/**
 * Staff Routes
 */
$route['admin/profile']           = 'admin/staff/profile';
$route['admin/profile/(:num)']    = 'admin/staff/profile/$1';
$route['admin/tasks/view/(:any)'] = 'admin/tasks/index/$1';

/**
 * Items search rewrite
 */
$route['admin/items/search'] = 'admin/invoice_items/search';

/**
 * In case if client access directly to url without the arguments redirect to clients url
 */
$route['/'] = 'clients';

/**
 * @deprecated
 */
$route['viewinvoice/(:num)/(:any)'] = 'invoice/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['invoice/(:num)/(:any)'] = 'invoice/index/$1/$2';

/**
 * @deprecated
 */
$route['viewestimate/(:num)/(:any)'] = 'estimate/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['estimate/(:num)/(:any)'] = 'estimate/index/$1/$2';
$route['subscription/(:any)']    = 'subscription/index/$1';

/**
 * @deprecated
 */
$route['viewproposal/(:num)/(:any)'] = 'proposal/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['proposal/(:num)/(:any)'] = 'proposal/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['contract/(:num)/(:any)'] = 'contract/index/$1/$2';

/**
 * @since 2.0.0
 */
$route['knowledge-base']                 = 'knowledge_base/index';
$route['knowledge-base/search']          = 'knowledge_base/search';
$route['knowledge-base/article']         = 'knowledge_base/index';
$route['knowledge-base/article/(:any)']  = 'knowledge_base/article/$1';
$route['knowledge-base/category']        = 'knowledge_base/index';
$route['knowledge-base/category/(:any)'] = 'knowledge_base/category/$1';

/**
 * @deprecated 2.2.0
 */
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'add_kb_answer') === false) {
    $route['knowledge-base/(:any)']         = 'knowledge_base/article/$1';
    $route['knowledge_base/(:any)']         = 'knowledge_base/article/$1';
    $route['clients/knowledge_base/(:any)'] = 'knowledge_base/article/$1';
    $route['clients/knowledge-base/(:any)'] = 'knowledge_base/article/$1';
}

/**
 * @deprecated 2.2.0
 * Fallback for auth clients area, changed in version 2.2.0
 */
$route['clients/reset_password']  = 'authentication/reset_password';
$route['clients/forgot_password'] = 'authentication/forgot_password';
$route['clients/logout']          = 'authentication/logout';
$route['clients/register']        = 'authentication/register';
$route['clients/login']           = 'authentication/login';

// Aliases for short routes
$route['reset_password']  = 'authentication/reset_password';
$route['forgot_password'] = 'authentication/forgot_password';
$route['login']           = 'authentication/login';
$route['logout']          = 'authentication/logout';
$route['register']        = 'authentication/register';

/**
 * Terms and conditions and Privacy Policy routes
 */
$route['terms-and-conditions'] = 'terms_and_conditions';
$route['privacy-policy']       = 'privacy_policy';

/**
 * @since 2.3.0
 * Routes for admin/modules URL because Modules.php class is used in application/third_party/MX
 */
$route['admin/modules']               = 'admin/mods';
$route['admin/modules/(:any)']        = 'admin/mods/$1';
$route['admin/modules/(:any)/(:any)'] = 'admin/mods/$1/$2';

// Public single ticket route
$route['forms/tickets/(:any)'] = 'forms/public_ticket/$1';

/**
 * @since  2.3.0
 * Route for clients set password URL, because it's using the same controller for staff to
 * If user addded block /admin by .htaccess this won't work, so we need to rewrite the URL
 * In future if there is implementation for clients set password, this route should be removed
 */
$route['authentication/set_password/(:num)/(:num)/(:any)'] = 'admin/authentication/set_password/$1/$2/$3';

// For backward compatilibilty
$route['survey/(:num)/(:any)'] = 'surveys/participate/index/$1/$2';

if (file_exists(APPPATH . 'config/my_routes.php')) {
    include_once(APPPATH . 'config/my_routes.php');
}

// Finite reviewed private expense-copy route.
$route['admin/company_expense_copies'] = 'admin/company_expense_copies/index';

// Finite native HR endpoints; the independent scope checks canonical IDs again.
$route['operations_api/hr_structure'] = 'operations_api/hr_structure';
$route['operations_api/hr_structure/([1-9][0-9]{0,9})'] = 'operations_api/hr_structure/$1';
$route['operations_api/hr_employees'] = 'operations_api/hr_employees';
$route['operations_api/hr_employees/([1-9][0-9]{0,9})'] = 'operations_api/hr_employees/$1';
$route['operations_api/hr_profiles'] = 'operations_api/hr_profiles';
$route['operations_api/hr_profiles/([1-9][0-9]{0,9})'] = 'operations_api/hr_profiles/$1';
$route['operations_api/hr_records'] = 'operations_api/hr_records';
$route['operations_api/hr_records/([1-9][0-9]{0,9})'] = 'operations_api/hr_records/$1';
$route['operations_api/hr_contracts'] = 'operations_api/hr_contracts';
$route['operations_api/hr_contracts/([1-9][0-9]{0,9})'] = 'operations_api/hr_contracts/$1';
$route['operations_api/hr_courses'] = 'operations_api/hr_courses';
$route['operations_api/hr_courses/([1-9][0-9]{0,9})'] = 'operations_api/hr_courses/$1';
$route['operations_api/hr_assignments'] = 'operations_api/hr_assignments';
$route['operations_api/hr_assignments/([1-9][0-9]{0,9})'] = 'operations_api/hr_assignments/$1';
$route['operations_api/hr_accesses'] = 'operations_api/hr_accesses';
$route['operations_api/hr_accesses/([1-9][0-9]{0,9})'] = 'operations_api/hr_accesses/$1';
$route['operations_api/hr_files'] = 'operations_api/hr_files';
$route['operations_api/hr_files/([1-9][0-9]{0,9})'] = 'operations_api/hr_files/$1';
$route['operations_api/hr_reports'] = 'operations_api/hr_reports';
$route['operations_api/hr_reports/([1-9][0-9]{0,9})'] = 'operations_api/hr_reports/$1';
$route['operations_api/hr_imports'] = 'operations_api/hr_imports';
$route['operations_api/hr_imports/([1-9][0-9]{0,9})'] = 'operations_api/hr_imports/$1';
$route['operations_api/hr_articles'] = 'operations_api/hr_articles';
$route['operations_api/hr_articles/([1-9][0-9]{0,9})'] = 'operations_api/hr_articles/$1';
$route['operations_api/hr_documents'] = 'operations_api/hr_documents';
$route['operations_api/hr_documents/([1-9][0-9]{0,9})'] = 'operations_api/hr_documents/$1';

// Reviewed finite Efí legacy URLs resolve to module code, never installer core copies.
$route['gateways/gerencia_net/invoice/([1-9][0-9]*)/([A-Za-z0-9_-]+)'] = 'gerencianet_gateway/gerencia_net/invoice/$1/$2';
$route['gateways/gerencia_net/gerar_cartao_credito/([1-9][0-9]*)/([A-Za-z0-9_-]+)'] = 'gerencianet_gateway/gerencia_net/gerar_cartao_credito/$1/$2';
$route['gateways/gerencia_net/gerar_pix_por_javascript/([1-9][0-9]*)/([A-Za-z0-9_-]+)'] = 'gerencianet_gateway/gerencia_net/gerar_pix_por_javascript/$1/$2';
$route['gateways/gerencia_net/callback_check_pix_pago/([A-Za-z0-9]+)/([1-9][0-9]*)/([A-Za-z0-9_-]+)'] = 'gerencianet_gateway/gerencia_net/callback_check_pix_pago/$1/$2/$3';
$route['gateways/gerencia_net/callback_boleto'] = 'gerencianet_gateway/gerencia_net/callback_boleto';
$route['gateways/gerencia_net/callback_cartao'] = 'gerencianet_gateway/gerencia_net/callback_cartao';
$route['gateways/gerencia_net/view_converter_certificado_p12_para_pem'] = 'gerencianet_gateway/gerencia_net/view_converter_certificado_p12_para_pem';
$route['gateways/gerencia_net/converterCertificado'] = 'gerencianet_gateway/gerencia_net/converterCertificado';
$route['gateways/gerencia_net/fetch_pixes'] = 'gerencianet_gateway/gerencia_net/fetch_pixes';
