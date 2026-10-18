const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const file = (pathname) => fs.readFileSync(path.join(root, pathname), 'utf8');

test('Asaas exige token no cabeçalho oficial e nunca aceita token por URL', () => {
  const security = file('modules/asaas/libraries/Asaas/WebhookSecurity.php');
  assert.match(security, /asaas-access-token/);
  assert.match(security, /hash\('sha256',\s*\$secret\)/);
  assert.match(security, /hash_equals\(\$digest,\s*hash\('sha256',\s*\$token\)\)/);
  assert.match(security, /error\(401,/);
  assert.match(security, /error\(503,/);
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
  assert.match(adapter, /\$terminal=\['processed','ignored','review_required','observed'\]/);
  assert.match(adapter, /\['state'=>'processed'\]/);
  assert.match(adapter, /\['state'=>'ignored'\]/);
  assert.match(adapter, /\$review=\['state'=>'review_required'\]/);
  assert.match(adapter, /webhookResult\(true,\$duplicate,'Evento armazenado\.',200\)/);
  assert.match(entry, /set_status_header\(\$status\)/);
  assert.match(entry, /method\(true\)\s*!==\s*'POST'/);
  assert.match(module, /after_cron_run.*asaas_process_pending_webhooks/);
  assert.match(module, /processPendingWebhookEvents\(20\)/);
  assert.doesNotMatch(entry, /echo \$result\['message'\]/);
});

test('Conciliação usa igualdade decimal exata, sem baixa dupla e com estorno em revisão', () => {
  const adapter = file('modules/asaas/libraries/Asaas/AsaasAdapter.php');
  assert.match(adapter, /invoicepaymentrecords/);
  assert.match(adapter, /transactionid.*paymentId/);
  assert.match(adapter, /PAYMENT_RECEIVED/);
  assert.match(adapter, /PAYMENT_CONFIRMED/);
  assert.match(adapter, /PAYMENT_REFUNDED/);
  assert.match(adapter, /PAYMENT_CHARGEBACK_REQUESTED/);
  assert.match(adapter, /review_required/);
  assert.match(adapter, /private function equalMoney\(\$a, \$b\): bool/);
  assert.ok(adapter.includes("!$this->equalMoney($p['value'],$origin['invoice_total'])"));
  assert.ok(adapter.includes("!$this->equalMoney($p['value'],$invoice['total'])"));
});

test('Configuração Asaas separa recebedor local do webhook remoto', () => {
  const controller = file('modules/asaas/controllers/Asaas.php');
  const view = file('modules/asaas/views/webhooks.php');
  assert.match(controller, /public function index\(\)[\s\S]*\$this->render_webhooks\(\)/);
  assert.match(controller, /public function register_integration\(\)/);
  assert.match(controller, /function require_webhook_management\(\): bool/);
  assert.match(controller, /setup_webhook\(\) \{ show_404\(\); \}/);
  assert.match(controller, /set_webhook_invoice\([^)]*\) \{ show_404\(\); \}/);
  assert.match(view, /form_open\(admin_url\('asaas\/register_integration'\)/);
  assert.match(view, /webhook_admin_csrf/);
  assert.ok(view.includes('Cadastro remoto:'));
  assert.ok(view.includes('não pode ser recuperado'));
  assert.doesNotMatch(view, /name=["'](?:api_key|webhook_token|token)["']/);
  assert.doesNotMatch(controller, /var_dump|suporte@wwsoftwares\.com\.br/);
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
