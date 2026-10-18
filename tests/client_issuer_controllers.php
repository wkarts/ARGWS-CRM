<?php
require __DIR__ . '/support/client_issuer_test_support.php';
#[AllowDynamicProperties]
class AdminController {}
#[AllowDynamicProperties]
class App_Controller {}
class ClientHttpStop extends RuntimeException {}
function show_404() { throw new ClientHttpStop('not_found',404); }
function is_client_logged_in() { return $GLOBALS['client_test_logged_in'] ?? false; }
function is_customer_admin($id) { return false; }
function total_rows($table,$where) { return count(get_instance()->db->where($where)->get($table)->result_array()); }
function force_download($path,$data) { $GLOBALS['downloaded_path'] = $path; }
require __DIR__ . '/../application/controllers/admin/Clients.php';
require __DIR__ . '/../application/controllers/Download.php';
class ClientInput {
    public array $data = [];
    public function post($name = null) { return $name === null ? $this->data : ($this->data[$name] ?? null); }
    public function get($name) { return $this->data[$name] ?? null; }
}
$controller = new Clients;
$controller->db = $model->db;
$controller->clients_model = $model;
$controller->input = new ClientInput;
$controller->input->data = ['customer_id'=>2];
foreach ([['save_longitude_and_latitude',[2]],['mark_as_active',[2]],['delete_customer_admin',[2,7]],['form_contact',[2]],['form_contact',[1,20]],['vault_entry_create',[2]],['vault_entry_update',[2]],['vault_entry_delete',[2]],['get_vault_entry',[2]],['export',[20]],['consents',[20]],['statement_pdf',[]],['send_statement',[]],['statement',[]]] as [$method,$args]) {
    try { $controller->$method(...$args); throw new RuntimeException('controller must deny '.$method); }
    catch (ClientHttpStop $e) { check($e->getCode() === 404, 'negative controller '.$method); }
}
$controller->input->data = ['file_id'=>2,'share_contacts_id'=>[10]];
try { $controller->update_file_share_visibility(); throw new RuntimeException('private file sharing allowed'); }
catch (ClientHttpStop $e) { check(true,'private file share denied'); }
$controller->input->data = ['file_id'=>1,'share_contacts_id'=>[20]];
try { $controller->update_file_share_visibility(); throw new RuntimeException('cross-client sharing allowed'); }
catch (ClientHttpStop $e) { check(true,'cross-client contact share denied before revocation'); }
check($model->db->writes === 0 && $GLOBALS['effects'] === [], 'controllers reject private before writes/hooks/encryption/export');
// Instantiate without its unrelated controller constructor, then run the actual download action.
$download = (new ReflectionClass(Download::class))->newInstanceWithoutConstructor();
$download->db = $model->db;
$download->load = new class { public function model($name) {} };
$pdo->exec("ALTER TABLE q_files ADD attachment_key TEXT; UPDATE q_files SET attachment_key='private-key' WHERE id=2");
try { $download->file('client','private-key'); throw new RuntimeException('private download allowed'); }
catch (ClientHttpStop $e) { check(true,'private attachment key denied before file access'); }
$pdo->exec("UPDATE q_files SET attachment_key='legacy-key' WHERE id=1");
$download->file('client','legacy-key');
check($GLOBALS['downloaded_path'] === '/no-fixtures-on-disk/1/legacy.txt', 'legacy authorized download positive control');
$GLOBALS['client_test_staff_can'] = false;
try { $download->file('client','legacy-key'); throw new RuntimeException('anonymous download allowed'); }
catch (ClientHttpStop $e) { check(true,'anonymous legacy download denied'); }
$GLOBALS['client_test_logged_in'] = true;
$pdo->exec("CREATE TABLE q_shared_customer_files(file_id INT,contact_id INT); INSERT INTO q_shared_customer_files VALUES(1,10)");
$download->file('client','legacy-key');
check($GLOBALS['downloaded_path'] === '/no-fixtures-on-disk/1/legacy.txt', 'legacy portal shared attachment preserved');
$pdo->exec('DELETE FROM q_shared_customer_files');
try { $download->file('client','legacy-key'); throw new RuntimeException('revoked contact sharing allowed'); }
catch (ClientHttpStop $e) { check(true,'legacy contact file-sharing revocation enforced'); }
$GLOBALS['client_test_staff_can'] = true;

// The actual phase-two registry must refuse new adoption, even for a real active admin.
require __DIR__ . '/../application/services/issuers/IssuerRegistry.php';
$pdo->exec('CREATE TABLE q_staff(staffid INT,admin INT,active INT); INSERT INTO q_staff VALUES(7,1,1),(8,0,1)');
$registry = new IssuerRegistry($model->db,db_prefix());
try { $registry->registerClient(11,1,8); throw new RuntimeException('non-admin adoption allowed'); }
catch (DomainException $e) { check($e->getMessage() === 'issuer_client_adoption_blocked','retired adoption denies non-admin'); }
try { $registry->registerClient(11,1,7); throw new RuntimeException('adoption must stay blocked'); }
catch (DomainException $e) { check($e->getMessage() === 'issuer_client_adoption_blocked','new adoption stops before transaction/mutation'); }
check($pdo->query('SELECT count(*) FROM q_issuers_clients WHERE client_id=1')->fetchColumn() === 0, 'legacy client stays legacy');
echo "Client issuer controllers: $n checks passed (real actions / SQLite; synthetic data)\n";
