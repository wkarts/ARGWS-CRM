<?php
require __DIR__ . '/support/client_issuer_test_support.php';

// No private mutation reaches hooks, hashing, custom fields, mail, files, or another model.
$before = $pdo->query('SELECT * FROM q_contacts')->fetchAll(PDO::FETCH_ASSOC);
$cases = [
    ['update', [['company'=>'changed','custom_fields'=>['x'=>'y']], 2]],
    ['update_company_details', [['address'=>'changed'], 2]],
    ['update_contact', [['password'=>'new','userid'=>1], 20]],
    ['add_contact', [['email'=>'new@example.test'], 2]],
    ['add_contact_via_customers_area', [[], 2]],
    ['assign_admins', [[], 2]],
    ['change_contact_status', [20,0]],
    ['change_client_status', [2,0]],
    ['change_contact_password', [20,'old','new']],
    ['delete_contact', [20]],
    ['delete_contact_profile_image', [20]],
    ['mark_email_as_verified', [20]],
    ['send_verification_email', [20]],
    ['require_confirmation', [2]],
    ['confirm_registration', [2]],
    ['delete_attachment', [2]],
    ['delete_attachment', [3]],
    ['vault_entry_create', [[],2]],
    ['vault_entry_update', [2,[]]],
    ['vault_entry_delete', [2]],
    ['send_statement_to_email', [2,[], '2026-01-01','2026-12-31']],
    ['send_notification_customer_profile_file_uploaded_to_responsible_staff', [20,2]],
];
foreach ($cases as [$method,$args]) check($model->$method(...$args) === false, 'private denied: '.$method);
check($model->db->writes === 0, 'negative methods made no SQL writes');
check($GLOBALS['effects'] === [], 'negative methods had no hooks/files/hash/mail effects');
check($pdo->query('SELECT * FROM q_contacts')->fetchAll(PDO::FETCH_ASSOC) === $before, 'private contacts unchanged');
check($vault->create([],2) === false && $vault->update(2,[]) === false && $vault->delete(2) === false, 'low-level vault writes guarded');
check($groups->sync_customer_groups(2,[]) === false, 'low-level groups write guarded');
foreach (['get_customer_billing_and_shipping_details','get_customer_files','get_customer_groups','get_vault_entries','get_staff_members_that_can_access_customer'] as $method) check($model->$method(2) === [], 'private related read denied: '.$method);
check($model->get_statement(2,'2026-01-01','2026-12-31') === [], 'no full customer fallback for statement');
check($model->get_vault_entry(2) === null && $vault->get(2) === null, 'vault ID cannot bypass');
check($vault->get_by_customer_id(2) === [] && $groups->get_customer_groups(2) === [], 'low-level relation lists scoped');
check($model->get_customer_default_currency(2) === false, 'private currency hidden');
check($model->get_customers_admin_unique_ids() === [['staff_id'=>8]], 'admin selector excludes private assignment');
check(array_column($model->get_clients_distinct_countries(),'country_id') === [1], 'country selector excludes private customer');
check(array_column($model->get_customer_files(1),'id') === [1], 'legacy files preserved');
check(array_column($model->get_customer_files(1, 'id=1 OR id=2 OR id=3'),'id') === [1], 'file OR cannot bypass customer/type');
check(array_column($vault->get_by_customer_id(1,'id=1 OR id=2'),'id') === [1], 'vault OR cannot bypass customer');
check($model->change_client_status(1,0) === true && $pdo->query('SELECT active FROM q_clients WHERE userid=1')->fetchColumn() === 0, 'legacy status positive control');
check($model->change_contact_status(10,0) === true && $pdo->query('SELECT active FROM q_contacts WHERE id=10')->fetchColumn() === 0, 'legacy contact status positive control');
check($model->update_contact(['firstname'=>'Updated','userid'=>2,'id'=>20],10,true) === true, 'legacy contact update preserved');
check($pdo->query('SELECT userid FROM q_contacts WHERE id=10')->fetchColumn() === 1, 'contact cannot reparent into private client');
check($model->update(['company'=>'Updated legacy','userid'=>2,'issuer_id'=>22,'owner_issuer_id'=>22],1) === true, 'legacy customer update preserved');
check($pdo->query('SELECT company FROM q_clients WHERE userid=1')->fetchColumn() === 'Updated legacy' && $pdo->query('SELECT company FROM q_clients WHERE userid=2')->fetchColumn() === 'Private X', 'customer identity not overwritten/reparented');
check($vault->update(1,['description'=>'Updated vault','customer_id'=>2,'id'=>2,'last_updated_from'=>7]) === true, 'legacy vault update positive control');
check($pdo->query('SELECT customer_id FROM q_vault WHERE id=1')->fetchColumn() === 1, 'vault cannot reparent');
check($groups->sync_customer_groups(1,[10]) === true, 'legacy group addition preserved');
check($pdo->query('SELECT groupid FROM q_customer_groups WHERE customer_id=2')->fetchColumn() === 9, 'private groups preserved');
check($model->update([], '1 OR 1=1') === false && $model->add_contact([],0) === false, 'invalid/missing customer IDs fail closed');
// Inactive or deactivated issuers are still private: no module-loaded/selected-company switch is consulted.
$pdo->exec('DELETE FROM q_issuers_client_links');
check($model->get(2) === null && $model->change_client_status(2,1) === false, 'revocation never falls back to global scope');
echo "Client issuer mutations: $n checks passed (real models / SQLite; synthetic data)\n";
