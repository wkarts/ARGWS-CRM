'use strict';
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const read = name => fs.readFileSync(path.join(root, name), 'utf8');

// Exercise the native handler itself, rather than a second implementation of its POST URL logic.
const view = read('application/views/admin/settings/all.php');
const handler = view.match(/        \$\('a\[data-toggle="tab"\]'\)\.on\('shown\.bs\.tab', function\(e\) \{([\s\S]*?)\n        \}\);/);
if (!handler) throw new Error('Native settings tab handler not found');
// horizontalTabs uses both dependencies shipped in common.js. Keep the actual delay
// implementation without bootstrapping unrelated application event handlers.
const nativeDelay = read('assets/js/app.js').match(/var delay = \(function \(\) \{[\s\S]*?\n\}\)\(\);/);
if (!nativeDelay) throw new Error('Native horizontal tabs delay helper not found');
const handlerBody = handler[1].replace(/<\?= site_url\(\$this->uri->uri_string\(\)\); \?>/g, 'https://crm.example.test/admin/settings');

exports.html = extra => {
  const scripts = [
    'assets/plugins/jquery/jquery.js',
    'assets/plugins/bootstrap/js/bootstrap.js',
    'assets/plugins/waypoint/jquery.waypoints.min.js',
    'assets/plugins/internal/bootstrap-nav-tabs-scrollable/bootstrap-nav-tab-scrollable.js',
  ].map(name => '<script>' + read(name).replace(/<\/script/gi, '<\\/script') + '</script>').join('')
    + '<script>' + nativeDelay[0] + '</script>'
    + '<script>' + read('modules/asaas/assets/js/operations-panel.js') + '</script>'
    + '<script>var module={exports:{}};</script><script>' + read('modules/asaas/assets/js/settings-tabs.js') + '</script>';
  const styles = ['assets/builds/vendor-admin.css', 'assets/css/style.css', 'assets/builds/tailwind.css']
    .map(name => '<style>' + read(name) + '</style>').join('');
  return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' + styles + '</head><body>'
    + scripts + `<script>
window.fixture = function(valid, value, profiled, admin) {
 document.body.innerHTML = '<main class="container-fluid"><h1>Meios de pagamento</h1><form id="settings-form" action="https://crm.example.test/admin/settings?group=payment_gateways"><input type="hidden" name="csrf_test" value="synthetic-csrf"><div class="panel_s"><div class="panel-body"><ul class="nav nav-tabs" role="tablist"><li class="active" role="presentation"><a id="general-tab" href="#payment_modes_general" data-toggle="tab" role="tab">Geral</a></li><li role="presentation"><a id="gateway-tab" href="#online_payments_asaas_tab" data-toggle="tab" role="tab">Asaas</a></li><li role="presentation"><a id="other-tab" href="#online_payments_other_tab" data-toggle="tab" role="tab">Outro gateway</a></li></ul><div class="tab-content"><div class="tab-pane active" id="payment_modes_general">Preferências gerais</div><div class="tab-pane" id="online_payments_asaas_tab"><h4>Asaas</h4><a href="https://crm.example.test/admin/asaas" data-asaas-webhook-admin-url data-asaas-environment-valid="'+valid+'" data-asaas-environment-value="'+value+'">Gerenciar recebedores e segurança dos webhooks Asaas</a><hr /></div><div class="tab-pane" id="online_payments_other_tab"><input name="settings[paymentmethod_other_active]" value="1"></div></div></div><div class="panel-footer"><button type="submit" class="btn btn-primary">Salvar configurações</button></div></div></form></main>';
 const pane = document.getElementById('online_payments_asaas_tab');
 pane.querySelector('[data-asaas-environment-valid]').setAttribute('data-asaas-settings-admin', admin === false ? '0' : '1');
 const profileKeys = ['active','label','default_selected','debug','file_log_enabled','sync_enabled','sync_days','sync_limit','currencies','description','interest_value','fine_value','discount_type','discount_value','discount_days','installmentCount','diasdevencimento','billet_only','card_only','pix_only','delete_charge','update_charge','disable_charge_notification','send_customer_email','send_customer_mobile','ultima_atualizacao_servicos_municipais'];
 const keys = profiled ? ['sandbox','api_key','api_key_sandbox'].concat(['production','sandbox'].flatMap(env => profileKeys.map(key => 'profile_'+env+'_'+key))) : ['sandbox','debug','file_log_enabled','api_key','api_key_sandbox','card_only','sync_limit','unknown_future_field','installmentCount'];
 keys.forEach(key => {
  const group = document.createElement('div'); group.className='form-group';
  group.innerHTML='<label>'+key+'</label><input class="form-control" name="settings[paymentmethod_asaas_'+key+']" value="0" '+(key==='sandbox'?'type="radio" checked':key.indexOf('api_key')===0?'type="password"':'')+'>';
  pane.appendChild(group);
 });
 const summary=document.createElement('div');summary.innerHTML='<div data-asaas-settings-section="authentication"><div class="form-group" data-asaas-profile-fragment="production"><label for="fixture-api-production">URL API Produção</label><input id="fixture-api-production" class="form-control" type="url" readonly value="https://api.asaas.com/v3"><button type="button" class="btn btn-default" data-asaas-copy="fixture-api-production">Copiar URL</button><span role="status" data-asaas-copy-status></span></div><div class="form-group" data-asaas-profile-fragment="sandbox"><label for="fixture-api-sandbox">URL API Homologação</label><input id="fixture-api-sandbox" class="form-control" type="url" readonly value="https://api-sandbox.asaas.com/v3"></div></div><div data-asaas-settings-section="webhook"><h4>URLs de recebimento por conta e ambiente</h4><div class="form-group"><label for="fixture-callback">Webhook Homologação</label><input id="fixture-callback" class="form-control" type="url" readonly value="https://crm.example.test/asaas/gateways/callback/integration/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"><button type="button" class="btn btn-default" data-asaas-copy="fixture-callback">Copiar URL</button><span role="status" data-asaas-copy-status></span></div></div>';pane.appendChild(summary);
 return pane;
};
window.bindNativeSettings = function() {
 const settingsForm = $('#settings-form'), slug = 'payment_gateways';
 $('a[data-toggle="tab"]').on('shown.bs.tab', function(e) {${handlerBody}
 });
 $('.horizontal-scrollable-tabs').horizontalTabs();
};
window.prepareFixture = function(valid, value, profiled, admin) {
 const pane = fixture(valid, value, profiled, admin); module.exports.initialize(document); bindNativeSettings(); return pane;
};
</script>` + (extra ? '<script>' + extra + '</script>' : '') + '</body></html>';
};
