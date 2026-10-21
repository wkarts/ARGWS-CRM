<?php
define('CLIENT_ISSUER_REAL_HELPERS',true);
require __DIR__ . '/support/client_issuer_test_support.php';
require __DIR__ . '/../application/helpers/clients_helper.php';
require __DIR__ . '/../application/helpers/upload_helper.php';
function total_rows($table,$where) { return count(get_instance()->db->where($where)->get($table)->result_array()); }
function base_url($path) { return '/'.$path; }
$pdo->exec("ALTER TABLE q_clients ADD leadid INT; UPDATE q_clients SET leadid=userid; ALTER TABLE q_contacts ADD profile_image TEXT; CREATE TABLE q_shared_customer_files(file_id INT,contact_id INT); INSERT INTO q_shared_customer_files VALUES(1,10),(2,20)");
$model->app_object_cache = new class {
    public array $data = [];
    public function get($key) { return $this->data[$key] ?? false; }
    public function add($key,$value) { $this->data[$key]=$value; }
    public function set($key,$value) { $this->data[$key]=$value; }
};
$model->clients_model = $model;
// Even stale request caches must not override persisted client privacy.
$model->app_object_cache->data['user-id-by-contact-id-20'] = 2;
$model->app_object_cache->data['contact-full-name-data-20'] = (object)['firstname'=>'Private','lastname'=>'Contact'];
$model->app_object_cache->data['2-is-customer-admin-7'] = ['retval'=>true];
$model->app_object_cache->data['staff-total-assigned-customers-7'] = 1;
$GLOBALS['client'] = (object)['userid'=>2,'company'=>'Private X'];
$GLOBALS['contact'] = (object)['id'=>20,'email_verified_at'=>'2026-01-01'];
check(get_client() === null, 'private customer cannot come from global cache');
check(is_contact_email_verified(20) === false, 'private verification cannot come from global cache');
check(get_user_id_by_contact_id(20) === false, 'private contact/client mapping cannot come from cache');
check(get_contact_full_name(20) === '', 'private full name cannot come from cache');
check(is_customer_admin(2,7) === false, 'cached customer-admin is not issuer access');
check(have_assigned_customers(7) === false, 'cached private assignment does not grant directory');
check(have_assigned_customers(8) === true, 'legacy assignment preserved');
check(get_company_name(2) === '', 'private name helper denied');
check(get_company_name(1) === 'Legacy', 'legacy name helper preserved');
check(get_primary_contact_user_id(2) === false && get_primary_contact_user_id(1) === 10, 'primary contact helper scoped with legacy control');
check(get_client_id_by_lead_id(2) === null && get_client_id_by_lead_id(1) === 1, 'lead conversion identity helper scoped');
check(is_primary_contact(20) === false && is_primary_contact(10) === true, 'primary contact flag scoped');
check(contact_profile_image_url(20) === '/assets/images/user-placeholder.jpg', 'private avatar URL not disclosed');
check(get_all_customer_attachments(2) === [], 'aggregate attachment helper fails closed');
check(get_customer_profile_file_sharing(['file_id'=>2]) === [], 'private file sharing metadata not disclosed');
check(array_column(get_customer_profile_file_sharing('file_id=1 OR file_id=2'),'file_id') === [1], 'file-sharing OR cannot escape');
check(login_as_client(2) === false, 'staff impersonation cannot bypass ownership');
check(handle_client_attachments_upload(2) === 0 && handle_contact_profile_image_upload(20) === false, 'upload helpers reject before hooks/files');
check($model->db->writes === 0 && $GLOBALS['effects'] === [], 'helper denials have no writes or effects');
$pdo->exec("CREATE TABLE q_contact_permissions(id INT,permission_id INT,userid INT); INSERT INTO q_contact_permissions VALUES(1,1,10),(2,1,20),(3,2,20)");
class app {} // Framework library registration only; the production permission helper is exercised below.
check(has_contact_permission('invoices',10)===true,'legacy native contact permission remains usable');
check(has_contact_permission('invoices',20)===false,'private native permission1 cannot open legacy invoice consumers');
check(has_contact_permission('estimates',20)===false,'other private permissions cannot open unreviewed legacy consumers');
check(has_contact_permission('invoices',999)===false,'unknown contact denied before module/catalog hydration');
echo "Client issuer helpers: $n checks passed (real helpers / SQLite)\n";
