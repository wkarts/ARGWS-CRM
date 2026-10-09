const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const file = (pathname) => fs.readFileSync(path.join(root, pathname), 'utf8');

test('Asaas exige token no cabeçalho oficial e nunca aceita token por URL', () => {
  const security = file('modules/asaas/libraries/Asaas/WebhookSecurity.php');
  assert.match(security, /asaas-access-token/);
  assert.match(security, /hash_equals\(\$secret,\s*\$provided\)/);
  assert.match(security, /'status' => 401/);
  assert.match(security, /'status' => 503/);
  assert.doesNotMatch(security, /\$_GET|x-webhook-token/);
});

test('Asaas persiste antes de responder HTTP 200 e reprocessa eventos em CRON', () => {
  const adapter = file('modules/asaas/libraries/Asaas/AsaasAdapter.php');
  const entry = file('modules/asaas/controllers/gateways/Callback.php');
  const module = file('modules/asaas/asaas.php');
  assert.match(adapter, /INSERT IGNORE INTO/);
  assert.match(adapter, /getLock|GET_LOCK/);
  assert.match(adapter, /processPendingWebhookEvents/);
  assert.match(adapter, /FOR UPDATE/);
  assert.match(adapter, /RELEASE_LOCK/);
  assert.match(adapter, /'processed', 'ignored', 'review_required'/);
  assert.match(adapter, /'Evento armazenado\.', 200/);
  assert.match(entry, /set_status_header\(\$status\)/);
  assert.match(entry, /method\(true\) !== 'POST'/);
  assert.match(module, /after_cron_run.*asaas_process_pending_webhooks/);
  assert.match(module, /processPendingWebhookEvents\(20\)/);
  assert.doesNotMatch(entry, /echo \$result\['message'\]/);
});

test('Conciliação não dá baixa dupla e mantém estornos para conferência', () => {
  const adapter = file('modules/asaas/libraries/Asaas/AsaasAdapter.php');
  assert.match(adapter, /invoicepaymentrecords/);
  assert.match(adapter, /transactionid.*paymentId/);
  assert.match(adapter, /PAYMENT_RECEIVED/);
  assert.match(adapter, /PAYMENT_CONFIRMED/);
  assert.match(adapter, /PAYMENT_REFUNDED/);
  assert.match(adapter, /PAYMENT_CHARGEBACK_REQUESTED/);
  assert.match(adapter, /review_required/);
  assert.match(adapter, /abs\(\$amount - \(float\) \$invoice->total\) > 0\.01/);
});

test('Setup Asaas usa v3/webhooks e não expõe chaves', () => {
  const controller = file('modules/asaas/controllers/Asaas.php');
  const view = file('modules/asaas/views/webhooks.php');
  assert.match(controller, /requestStructured\('POST', '\/webhooks'/);
  assert.match(controller, /requestStructured\('GET', '\/webhooks'/);
  assert.match(controller, /authToken/);
  assert.match(controller, /'sendType' => 'SEQUENTIALLY'/);
  assert.match(controller, /method\(true\) !== 'POST'/);
  assert.doesNotMatch(controller, /var_dump|suporte@wwsoftwares\.com\.br/);
  assert.match(view, /form_open\(admin_url\('asaas\/setup_webhook'\)\)/);
  assert.doesNotMatch(view, /value="<\?= html_escape\(\$token\)/);
});

test('Rotas de diagnóstico de Pix são protegidas contra GET e acesso anônimo', () => {
  const main = file('modules/asaas/controllers/Main.php');
  assert.match(main, /public function index\(\)[\s\S]*show_404\(\)/);
  assert.match(main, /function requireAdministrativeRequest\(bool \$write = false\)/);
  assert.match(main, /is_staff_logged_in\(\)/);
  assert.match(main, /method\(true\) !== 'POST'/);
  assert.doesNotMatch(main, /var_dump/);
});

test('O CRM opera notificações apenas pelo Conector, sem remover dados legados', () => {
  const sms = file('application/helpers/sms_helper.php');
  const base = file('application/libraries/sms/App_sms.php');
  const connector = file('modules/connect_api_connector/connect_api_connector.php');
  assert.doesNotMatch(sms, /sms\/sms_twilio|sms\/sms_msg91|sms\/sms_clickatell/);
  assert.match(sms, /connect_api_connector\/sms_connect_api_connector/);
  assert.match(sms, /!is_admin\(\)/);
  assert.match(sms, /sms_gateway_test/);
  assert.match(base, /\$id !== 'connect_api_connector'/);
  assert.match(base, /'verify'\s*=>\s*true/);
  assert.match(base, /get_gateways\(\)/);
  assert.match(connector, /CONNECT_API_CONNECTOR_TRIGGER_INVOICE_SENT/);
  assert.match(connector, /CONNECT_API_CONNECTOR_TRIGGER_PAYMENT_RECORDED/);
  assert.match(connector, /connect_api_connector_ensure_sms_takeover/);
});
