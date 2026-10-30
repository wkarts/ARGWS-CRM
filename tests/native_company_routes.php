<?php
/** Pure routing contract; no database, session, transport or application bootstrap. */
require dirname(__DIR__) . '/application/services/NativeCompanyRoutes.php';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
foreach (['GET', 'HEAD'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/', '', 'Dashboard', 'index', $verb), 'native dashboard read admitted');
}
$check(NativeCompanyRoutes::admits('admin/','','issuers','index','GET'),'original company registry page admitted through its issuer-scoped reader');
$check(NativeCompanyRoutes::admits('admin/','','issuers','index','HEAD'),'original company registry supports HEAD');
$check(NativeCompanyRoutes::admits('admin/','','issuers','save','POST'),'original company registry writer admitted through its membership and CSRF checks');
foreach(['POST','PUT','PATCH','DELETE'] as $verb)$check(!NativeCompanyRoutes::admits('admin/','','issuers','index',$verb),'company registry reads never mutate');
foreach(['GET','HEAD','PUT','PATCH','DELETE'] as $verb)$check(!NativeCompanyRoutes::admits('admin/','','issuers','save',$verb),'company registry mutations require POST');
foreach(['GET','HEAD','POST'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','calendar',$verb),'original Utilities calendar is connected to its scoped page or writer');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','get_calendar_data',$verb)===($verb==='GET'),'native calendar feed is read-only GET');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','view_event',$verb)===in_array($verb,['GET','HEAD'],true),'native calendar event detail is read-only');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin/','','utilities','delete_event',$verb)===($verb==='POST'),'native calendar event deletion requires POST and native CSRF');
foreach (['GET','HEAD','POST'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/','','settings','index',$verb),'existing Settings route admitted only for its explicit native adapter');
}
$settingsAssetActions=['remove_company_logo'=>['POST'],'remove_signature_image'=>['POST'],'remove_fv'=>['POST'],'company_asset'=>['GET','HEAD']];
foreach($settingsAssetActions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
    $check(NativeCompanyRoutes::admits('admin/','','settings',$action,$verb)===in_array($verb,$allowed,true),'official Settings asset action has finite verb: '.$action.'/'.$verb);
foreach(array_keys($settingsAssetActions)as$action)$check(!NativeCompanyRoutes::navigation('settings/'.$action),'Settings asset endpoints are never return destinations: '.$action);
foreach (['GET','HEAD','POST'] as $verb) {
    $check(NativeCompanyRoutes::admits('admin/','','departments','index',$verb),'original native Departments list and DataTables methods admitted');
}
foreach (['department','delete','email_exists','folders','test_imap_connection'] as $method) {
    $check(NativeCompanyRoutes::admits('admin/','','departments',$method,'POST'),'native Departments endpoint uses its original POST route: '.$method);
    foreach (['GET','HEAD','PUT','PATCH','DELETE','OPTIONS'] as $verb) {
        $check(!NativeCompanyRoutes::admits('admin/','','departments',$method,$verb),'native Departments endpoint denies alternate verb: '.$method.'/'.$verb);
    }
}
foreach (['GET','HEAD','PUT','PATCH','DELETE','OPTIONS'] as $verb) {
    $check(!NativeCompanyRoutes::admits('admin/','','emails','sent_smtp_test_email',$verb),'native SMTP test never runs without explicit POST');
}
$check(NativeCompanyRoutes::admits('admin/','','emails','sent_smtp_test_email','POST'),'native SMTP test endpoint is connected to its scoped writer');
$check(NativeCompanyRoutes::admits('admin/','','misc','dismiss_announcement','POST'),'native announcement dismissal uses the original scoped POST action');
$check(!NativeCompanyRoutes::admits('admin/','','misc','dismiss_announcement','GET'),'native announcement dismissal never mutates through GET');
foreach (['delete_queued_email','clear_queued_emails'] as $method) {
    $check(NativeCompanyRoutes::admits('admin/','','emails',$method,'POST'),'native queue deletion uses original POST route');
    $check(!NativeCompanyRoutes::admits('admin/','','emails',$method,'GET'),'native queue deletion never uses GET');
}
foreach (['POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $verb) {
    $check(!NativeCompanyRoutes::admits('admin/', '', 'dashboard', 'index', $verb), 'unimplemented mutation denied');
}
foreach ([['admin', 'third_party', 'dashboard', 'index'], ['public', '', 'dashboard', 'index'],
    ['admin', '', 'dashboard', 'unknown'],
    ['admin', '', 'company_context', 'index'], ['admin', '', 'authentication', 'index']] as $route) {
    $check(!NativeCompanyRoutes::admits(...array_merge($route, ['GET'])), 'unadapted or non-native route denied');
}
foreach (['', 'dashboard', 'dashboard/index','issuers','settings','departments','utilities/calendar'] as $route) {
    $check(NativeCompanyRoutes::navigation($route), 'canonical native navigation accepted');
}
foreach (['/dashboard', '//evil.test', 'https://evil.test', '../dashboard', 'dashboard/../invoices',
    'dashboard?issuer_id=2', 'dashboard#fragment', 'dashboard%2findex', "dashboard\0", "dashboard\n", 'dashboard\\index',
    'company_context', 'company_issuers', 'company_invoices', 'dashboard/ticket_widget', 'invoices/15'] as $route) {
    $check(!NativeCompanyRoutes::navigation($route), 'return URL cannot expand native route authority');
}
echo "Native company route contracts: {$checks} checks passed.\n";

$mutations=['todo'=>['todo','change_todo_status','update_todo_items_order','delete_todo_item'],'announcements'=>['delete'],'taxes'=>['manage','delete'],'invoice_items'=>['manage','add_group','update_group','delete_group','delete','bulk_action','copy']];
foreach($mutations as$controller=>$methods)foreach($methods as$method){$check(NativeCompanyRoutes::admits('admin','',$controller,$method,'POST'),'Explicit owned writer admitted');foreach(['GET','HEAD','PUT','DELETE']as$verb)$check(!NativeCompanyRoutes::admits('admin','',$controller,$method,$verb),'Mutation never admitted through read/foreign verb');}
foreach(['todo','announcements','taxes','tickets','invoice_items']as$controller){$check(NativeCompanyRoutes::admits('admin','',$controller,'index','GET'),'Original catalog page admitted');$check(NativeCompanyRoutes::navigation($controller),'Original navigation target admitted');$check(!NativeCompanyRoutes::admits('admin','module',$controller,'index','GET'),'Module cannot inherit native admission');$check(!NativeCompanyRoutes::admits('admin','',$controller,'unknown','POST'),'No wildcard actions');}
echo "Native finite auxiliary admission: {$checks} total route assertions PASS; authenticated HTTP remains required.\n";

foreach(['admin/','../../modules/banco_brasil/controllers/']as$directory){
 $check(NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil','index','GET'),'BB shares native presentation in both MX directory forms');
 foreach(['register_connection','generate_remittance','save_channel_preferences','issue_api','preview_checkout','publish_checkout','compare_cnab400','prepare_cnab400','recover_cnab400','reserve_cnab400','candidate_cnab400','settle_cnab400','declare_cnab400_approval','generate_cnab400','download_cnab400','report_cnab400']as$method){
  $check(NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil',$method,'POST'),'explicit BB writer uses native company CSRF');
  $check(!NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil',$method,'GET'),'BB writer cannot run through GET');
 }
 $check(!NativeCompanyRoutes::admits($directory,'banco_brasil','banco_brasil','unknown','POST'),'BB has no wildcard module action');
}
$check(NativeCompanyRoutes::navigation('banco_brasil'),'single native module navigation');
echo "BB native finite admission: {$checks} total assertions passed.\n";

$staffActions=[
 'index'=>['GET','HEAD','POST'],'member'=>['GET','HEAD','POST'],'role_changed'=>['GET'],'save_completed_checklist_visibility'=>['POST'],
 'save_dashboard_widgets_order'=>['POST'],'save_dashboard_widgets_visibility'=>['POST'],
 'reset_dashboard'=>['POST'],'save_hidden_table_columns'=>['POST'],'timesheets'=>['GET','HEAD'],
 'delete'=>['POST'],'edit_profile'=>['GET','HEAD','POST'],'remove_staff_profile_image'=>['POST'],
 'change_password_profile'=>['POST'],'profile'=>['GET','HEAD'],'change_staff_status'=>['POST'],
 'notifications'=>['POST'],'update_two_factor'=>['POST'],'verify_google_two_factor'=>['POST'],
];
$check(NativeCompanyRoutes::navigation('staff')&&NativeCompanyRoutes::navigation('staff/member'),'native original Staff navigation is available');
foreach($staffActions as$method=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','staff',$method,$verb)===in_array($verb,$allowed,true),'official Staff action has finite verb: '.$method.'/'.$verb);
foreach(['change_language','unknown']as$method)foreach(['GET','HEAD','POST']as$verb)
 $check(!NativeCompanyRoutes::admits('admin','','staff',$method,$verb),'unadapted Staff neighbor remains closed: '.$method.'/'.$verb);
foreach(array_keys($staffActions)as$method)if(!in_array($method,['index','member'],true))$check(!NativeCompanyRoutes::navigation('staff/'.$method),'Staff endpoint is not a return destination: '.$method);
$check(!NativeCompanyRoutes::admits('admin','foreign','staff','index','GET'),'other modules cannot reuse Staff workflows');
$check(NativeCompanyRoutes::admits('admin','','misc','staff_email_exists','POST'),'native member form can validate email under its current context');
echo "Native Staff admission: {$checks} total assertions passed.\n";

foreach(['add_note','edit_note','delete_note']as$method){$check(NativeCompanyRoutes::admits('admin','','misc',$method,'POST'),'scoped Staff notes have native mutation admission');$check(!NativeCompanyRoutes::admits('admin','','misc',$method,'GET'),'notes never mutate by GET');}

$check(NativeCompanyRoutes::admits('admin','banco_brasil','banco_brasil','download_return','POST'),'private original return download uses original module POST route');
$check(!NativeCompanyRoutes::admits('admin','banco_brasil','banco_brasil','download_return','GET'),'bank originals cannot be fetched by unprotected GET');

$projectActions=['index'=>['GET','HEAD'],'staff_projects'=>['POST'],'table'=>['POST'],'project'=>['GET','HEAD','POST'],'view'=>['GET','HEAD','POST'],'upload_file'=>['POST'],'remove_file'=>['POST'],'add_edit_members'=>['POST'],'remove_team_member'=>['POST'],'get_rel_project_data'=>['GET'],'get_staff_names_for_mentions'=>['GET'],'delete'=>['POST']];
foreach($projectActions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','projects',$action,$verb)===in_array($verb,$allowed,true),'official project action has finite verb: '.$action.'/'.$verb);
$check(NativeCompanyRoutes::navigation('projects')&&NativeCompanyRoutes::navigation('projects/project'),'official project directory and editor are return destinations');
foreach(['staff_projects','table','view','upload_file','remove_file','add_edit_members','remove_team_member','get_rel_project_data','get_staff_names_for_mentions']as$action)$check(!NativeCompanyRoutes::navigation('projects/'.$action),'project data endpoint is not a return destination: '.$action);
foreach(['unknown']as$action)foreach(['GET','HEAD','POST']as$verb)$check(!NativeCompanyRoutes::admits('admin','','projects',$action,$verb),'unadapted project neighbor remains closed: '.$action.'/'.$verb);
$check(!NativeCompanyRoutes::admits('admin','foreign','projects','project','GET'),'other modules cannot reuse project workflows');
echo "Official Projects routes: finite workflow admission preserved.\n";

foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb){
 $check(NativeCompanyRoutes::admits('admin','','clients','get_customer_billing_and_shipping_details',$verb)===in_array($verb,['GET','HEAD'],true),'private customer billing projection is read-only: '.$verb);
}
$check(!NativeCompanyRoutes::admits('admin','foreign','clients','get_customer_billing_and_shipping_details','GET'),'modules cannot reuse customer billing projection');
$check(!NativeCompanyRoutes::navigation('clients/get_customer_billing_and_shipping_details'),'billing projection is not a navigation destination');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb){
 $check(NativeCompanyRoutes::admits('admin','','clients','contact_profile_image',$verb)===in_array($verb,['GET','HEAD'],true),'native contact image admits only read verbs: '.$verb);
}
$check(!NativeCompanyRoutes::admits('admin','foreign','clients','contact_profile_image','GET'),'module cannot reuse native contact image reader');
$check(!NativeCompanyRoutes::navigation('clients/contact_profile_image'),'contact image is not a navigation destination');
foreach(['unknown']as$method)foreach(['GET','POST']as$verb)$check(!NativeCompanyRoutes::admits('admin','','clients',$method,$verb),'unadapted customer actions remain closed');

// Each customer action is independently bounded; no wildcard or financial action is opened.
$clientActions=['index'=>['GET','HEAD'],'table'=>['POST'],'client'=>['GET','HEAD','POST'],'import'=>['GET','HEAD','POST'],'form_contact'=>['GET','HEAD','POST'],'contacts'=>['POST'],'all_contacts'=>['GET','POST'],'consents'=>['GET','HEAD'],'contact_email_exists'=>['POST'],'change_contact_status'=>['POST'],'change_client_status'=>['POST'],'lookup'=>['POST'],'check_duplicate_customer_name'=>['POST'],'delete_contact'=>['POST'],'confirm_registration'=>['POST'],'delete_contact_profile_image'=>['POST'],'upload_attachment'=>['POST'],'delete_attachment'=>['POST'],'add_external_attachment'=>['POST'],'update_file_share_visibility'=>['POST'],'assign_admins'=>['POST'],'delete_customer_admin'=>['POST'],'save_longitude_and_latitude'=>['POST'],'get_vault_entry'=>['GET','HEAD'],'vault_entry_create'=>['POST'],'vault_entry_update'=>['POST'],'vault_entry_delete'=>['POST'],'vault_encrypt_password'=>['POST'],'share_identity'=>['POST'],'contact_invoice_access'=>['POST'],'groups'=>['GET','HEAD','POST'],'group'=>['POST'],'delete_group'=>['POST']];
foreach($clientActions as $action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin','','clients',$action,$verb)===in_array($verb,$allowed,true),'native customer action has finite verb: '.$action.'/'.$verb);
$check(NativeCompanyRoutes::navigation('clients')&&NativeCompanyRoutes::navigation('clients/client')&&NativeCompanyRoutes::navigation('clients/import')&&NativeCompanyRoutes::navigation('clients/groups'),'native client list, import and form are return destinations');
// Company-owned contracts live exclusively in the original Contracts controller.
$contractActions=['index'=>['GET','HEAD'],'contract'=>['GET','HEAD','POST'],'pdf'=>['GET','HEAD'],'file'=>['GET','HEAD']];
foreach($contractActions as $action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)
 $check(NativeCompanyRoutes::admits('admin','','contracts',$action,$verb)===in_array($verb,$allowed,true),'official company contract action has finite verb: '.$action.'/'.$verb);
$check(NativeCompanyRoutes::navigation('contracts')&&NativeCompanyRoutes::navigation('contracts/contract'),'official contract directory and editor are return destinations');
$check(!NativeCompanyRoutes::navigation('contracts/pdf')&&!NativeCompanyRoutes::navigation('contracts/file'),'private contract bytes are never return destinations');
$check(!NativeCompanyRoutes::admits('admin','','company_contracts','index','GET'),'removed parallel contracts controller has no native admission');
$check(!NativeCompanyRoutes::admits('admin','foreign','contracts','contract','GET'),'module cannot reuse official owned contract adapter');
foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin','','gdpr','contact_consent_opt_action',$verb)===($verb==='POST'),'contact consent writes admit only POST');
foreach(['estimates','proposals'] as $controller){foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'] as $verb)$check(NativeCompanyRoutes::admits('admin','',$controller,'get_due_date',$verb)===($verb==='POST'),'company due-date helper admits only POST: '.$controller.'/'.$verb);$check(!NativeCompanyRoutes::navigation($controller.'/get_due_date'),'due-date helper is not a navigation destination');}

$taskActions=['index'=>['GET','HEAD'],'list_tasks'=>['GET','HEAD'],'table'=>['POST'],'init_relation_tasks'=>['POST'],'task'=>['GET','HEAD','POST'],'get_task_data'=>['GET'],'timer_tracking'=>['POST'],'ajax_search_assign_task_to_timer'=>['POST'],'get_billable_tasks_by_project'=>['GET'],'get_billable_tasks_by_customer_id'=>['GET'],'task_tracking_stats'=>['GET'],'get_staff_names_for_mentions'=>['GET'],'get_task_by_id'=>['GET'],'get_staff_started_timers'=>['GET'],'get_billable_task_data'=>['GET'],'save_checklist_item_template'=>['POST'],'remove_checklist_item_template'=>['POST'],'init_checklist_items'=>['POST'],'checklist_visibility'=>['POST'],'checklist_command'=>['POST'],'add_task_comment'=>['POST'],'upload_file'=>['POST'],'remove_task_attachment'=>['POST'],'mark_as'=>['POST'],'change_priority'=>['POST']];
foreach($taskActions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','tasks',$action,$verb)===in_array($verb,$allowed,true),'official task action has finite verb: '.$action.'/'.$verb);
$check(NativeCompanyRoutes::navigation('tasks')&&NativeCompanyRoutes::navigation('tasks/task'),'official task directory and editor are return destinations');
foreach(array_diff(array_keys($taskActions),['index','list_tasks','task'])as$action)$check(!NativeCompanyRoutes::navigation('tasks/'.$action),'task data endpoint is not a return destination: '.$action);
foreach(['delete','unknown']as$action)foreach(['GET','HEAD','POST']as$verb)$check(!NativeCompanyRoutes::admits('admin','','tasks',$action,$verb),'unadapted task neighbor remains closed: '.$action.'/'.$verb);
$check(!NativeCompanyRoutes::admits('admin','foreign','tasks','task','GET'),'other modules cannot reuse task workflows');

$ticketActions=['index'=>['GET','HEAD','POST'],'add'=>['GET','HEAD','POST'],'ticket'=>['GET','HEAD','POST'],'delete'=>['POST'],'delete_attachment'=>['POST'],'delete_ticket_reply'=>['POST'],'edit_message'=>['POST'],'change_status_ajax'=>['POST'],'update_single_ticket_settings'=>['POST'],'update_staff_replying'=>['POST'],'check_staff_replying'=>['GET'],'block_sender'=>['POST'],'bulk_action'=>['POST'],'ticket_change_data'=>['POST'],'priorities'=>['GET','HEAD'],'priority'=>['POST'],'delete_priority'=>['POST'],'statuses'=>['GET','HEAD'],'status'=>['POST'],'delete_ticket_status'=>['POST'],'services'=>['GET','HEAD','POST'],'service'=>['POST'],'delete_service'=>['POST'],'predefined_replies'=>['GET','HEAD','POST'],'predefined_reply'=>['GET','HEAD','POST'],'get_predefined_reply_ajax'=>['GET'],'delete_predefined_reply'=>['POST']];
foreach($ticketActions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','tickets',$action,$verb)===in_array($verb,$allowed,true),'official ticket action has finite verb: '.$action.'/'.$verb);
foreach(['tickets','tickets/add','tickets/priorities','tickets/statuses','tickets/services','tickets/predefined_replies','tickets/predefined_reply']as$route)$check(NativeCompanyRoutes::navigation($route),'official ticket page is a return destination: '.$route);
foreach(['ticket','delete_attachment','delete_ticket_reply','edit_message','change_status_ajax','check_staff_replying','get_predefined_reply_ajax']as$action)$check(!NativeCompanyRoutes::navigation('tickets/'.$action),'ticket record/data endpoint is not a return destination: '.$action);
foreach(['merge','spam','unknown']as$action)foreach(['GET','POST']as$verb)$check(!NativeCompanyRoutes::admits('admin','','tickets',$action,$verb),'unadapted ticket neighbor remains closed: '.$action.'/'.$verb);
$check(!NativeCompanyRoutes::admits('admin','foreign','tickets','ticket','GET'),'other modules cannot reuse ticket workflows');

$invoiceActions=['index'=>['GET','HEAD'],'list_invoices'=>['GET','HEAD'],'table'=>['POST'],'invoice'=>['GET','HEAD','POST'],'drafts'=>['GET','HEAD','POST'],'copy'=>['GET','HEAD','POST'],'client_change_data'=>['GET'],'validate_invoice_number'=>['POST'],'get_invoice_data_ajax'=>['GET','HEAD'],'add_note'=>['POST'],'get_notes'=>['GET','HEAD'],'pause_overdue_reminders'=>['POST'],'resume_overdue_reminders'=>['POST'],'mark_as_sent'=>['POST'],'mark_as_cancelled'=>['POST'],'unmark_as_cancelled'=>['POST'],'get_invoices_total'=>['POST'],'record_invoice_payment_ajax'=>['GET','HEAD'],'record_payment'=>['POST'],'delete_attachment'=>['POST'],'pdf'=>['GET','HEAD'],'get_due_date'=>['POST']];
foreach($invoiceActions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','invoices',$action,$verb)===in_array($verb,$allowed,true),'official invoice action has finite verb: '.$action.'/'.$verb);
foreach(['invoices','invoices/list_invoices','invoices/invoice','invoices/drafts','invoices/copy']as$route)$check(NativeCompanyRoutes::navigation($route),'official invoice page is a return destination: '.$route);
foreach(array_diff(array_keys($invoiceActions),['index','list_invoices','invoice','drafts','copy'])as$action)$check(!NativeCompanyRoutes::navigation('invoices/'.$action),'invoice data endpoint is not a return destination: '.$action);
foreach(['upload_sales_file','add_sales_external_attachment','toggle_file_visibility']as$action)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','misc',$action,$verb)===($verb==='POST'),'invoice attachment mutation has finite verb: '.$action.'/'.$verb);
foreach(['create','update','delete','mark_as_default','unmark_as_default','validate_dynamic_date']as$action)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','','filters',$action,$verb)===($verb==='POST'),'official filter mutation has finite verb: '.$action.'/'.$verb);
$salesRoutes=['estimates'=>['index'=>['GET','HEAD','POST'],'list_estimates'=>['GET','HEAD','POST'],'estimate'=>['GET','HEAD','POST'],'pdf'=>['GET','HEAD'],'get_due_date'=>['POST']],
 'proposals'=>['index'=>['GET','HEAD','POST'],'list_proposals'=>['GET','HEAD','POST'],'proposal'=>['GET','HEAD','POST'],'pdf'=>['GET','HEAD'],'get_due_date'=>['POST']]];
foreach($salesRoutes as$controller=>$actions){foreach($actions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','',$controller,$action,$verb)===in_array($verb,$allowed,true),'official '.$controller.' action has finite verb: '.$action.'/'.$verb);
 foreach([$controller,$controller.'/list_'.$controller,$controller.'/'.rtrim($controller,'s')]as$route)$check(NativeCompanyRoutes::navigation($route),'official sales page is a return destination: '.$route);
 foreach(array_diff(array_keys($actions),['index','list_'.$controller,rtrim($controller,'s')])as$action)$check(!NativeCompanyRoutes::navigation($controller.'/'.$action),'sales data endpoint is not a return destination: '.$controller.'/'.$action);
}
$financeRoutes=[
 'credit_notes'=>['index'=>['GET','HEAD','POST'],'list_credit_notes'=>['GET','HEAD','POST']],
 'expenses'=>['index'=>['GET','HEAD','POST'],'list_expenses'=>['GET','HEAD','POST'],'copy'=>['GET','HEAD','POST']],
 'reports'=>['index'=>['GET','HEAD']],
 'payments'=>['post_refund'=>['GET','HEAD','POST'],'post_refund_manual'=>['GET','HEAD','POST']],
];
foreach($financeRoutes as$controller=>$actions){foreach($actions as$action=>$allowed)foreach(['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS']as$verb)
 $check(NativeCompanyRoutes::admits('admin','',$controller,$action,$verb)===in_array($verb,$allowed,true),'official finance action has finite verb: '.$controller.'/'.$action.'/'.$verb);
 foreach(array_keys($actions)as$action){$route=$action==='index'?$controller:$controller.'/'.$action;$check(NativeCompanyRoutes::navigation($route),'official finance page is a return destination: '.$route);}
 $check(!NativeCompanyRoutes::admits('admin','foreign',$controller,array_key_first($actions),'GET'),'other modules cannot reuse finance workflows: '.$controller);
 $check(!NativeCompanyRoutes::admits('admin','',$controller,'unknown','POST'),'finance controller has no wildcard action: '.$controller);
}
$check(!NativeCompanyRoutes::admits('admin','foreign','invoices','invoice','GET'),'other modules cannot reuse invoice workflows');
$check(!NativeCompanyRoutes::admits('admin','','invoices','delete','POST'),'unadapted invoice deletion remains closed');
$check(!NativeCompanyRoutes::admits('admin','','company_invoice_drafts','index','GET'),'retired invoice drafts controller has no native admission');
$check(!NativeCompanyRoutes::admits('admin','','company_invoice_copies','index','GET'),'retired invoice copies controller has no native admission');
echo "Official Tasks, Tickets, Projects, Invoices, Estimates and Proposals routes: {$checks} total assertions passed.\n";
