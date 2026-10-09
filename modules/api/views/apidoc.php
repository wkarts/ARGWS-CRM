<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
/**
 * Guia gerado a partir dos controladores e rotas disponíveis nesta distribuição.
 * Os nomes das rotas e campos JSON são identificadores de contrato: não traduzir.
 */
$endpoints = [
    ['Clientes', 'customers', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Contatos', 'contacts', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Faturas', 'invoices', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Orçamentos', 'estimates', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Propostas', 'proposals', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Projetos', 'projects', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Tarefas', 'tasks', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Chamados', 'tickets', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Oportunidades', 'leads', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Colaboradores', 'staffs', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Contratos', 'contracts', ['GET', 'POST', 'DELETE'], false],
    ['Notas de crédito', 'credit_notes', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Despesas', 'expenses', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Itens', 'items', ['GET'], true],
    ['Pagamentos', 'payments', ['GET'], true],
    ['Marcos de projeto', 'milestones', ['GET', 'POST', 'PUT', 'DELETE'], true],
    ['Campos personalizados', 'custom_fields', ['GET'], false],
    ['Consultas auxiliares', 'common', ['GET'], false],
];
?>
<?php init_head(); ?>
<style>
.api-guide { max-width: 1280px; margin: 0 auto; }
.api-guide .guide-summary { font-size: 14px; line-height: 1.65; }
.api-guide code, .api-guide pre { font-family: Consolas, Menlo, Monaco, monospace; }
.api-guide pre { white-space: pre-wrap; word-break: break-word; margin-bottom: 0; }
.api-guide .api-guide-table td { vertical-align: middle !important; }
.api-guide .api-guide-table code { white-space: normal; overflow-wrap: anywhere; }
.api-guide .http-method { display: inline-block; padding: 3px 7px; margin: 2px; border: 1px solid #d9e1e6; border-radius: 4px; font-size: 11px; font-weight: bold; }
.api-guide .guide-section { margin-top: 26px; }
@media (max-width: 767px) { .api-guide .guide-section { margin-top: 16px; } .api-guide pre { font-size: 11px; } }
</style>
<div id="wrapper">
  <div class="content api-guide">
    <div class="row">
      <div class="col-md-12">
        <div class="panel_s">
          <div class="panel-body">
            <div class="clearfix">
              <h3 class="pull-left" style="margin-top:0;">Documentação da API</h3>
              <a class="btn btn-default pull-right" href="<?= admin_url('api/api_management'); ?>">Gerenciar acessos</a>
            </div>
            <div class="clearfix"></div>
            <p class="guide-summary text-muted">
              Guia local, fornecido com o CRM, sem dependência de site externo ou CDN.
              Esta documentação descreve rotas implementadas. As operações exigem permissões e um token válido.
              Utilize HTTPS nas integrações e nunca exponha chaves ou tokens no navegador, em URLs ou em repositórios.
            </p>
            <div class="guide-section">
              <h4>Autenticação e formato</h4>
              <p>Crie e administre as credenciais em <strong>API → Gerenciar acessos</strong>. Envie o token autorizado no cabeçalho
              <code>Authorization</code>, conforme as permissões configuradas na instalação. O formato padrão de resposta é JSON.</p>
              <p class="text-muted">A configuração também prevê <code>X-API-KEY</code>, mas a validação de API key independente está desabilitada
              por padrão nesta distribuição. Ela não substitui a autenticação por token.</p>
              <pre>GET /api/customers/123 HTTP/1.1
Authorization: Bearer SEU_TOKEN
Accept: application/json</pre>
            </div>
            <div class="guide-section">
              <h4>Recursos e operações</h4>
              <label for="api-guide-filter">Localizar recurso</label>
              <input id="api-guide-filter" type="search" class="form-control" autocomplete="off"
                placeholder="Ex.: clientes, faturas, contratos" aria-label="Filtrar recursos">
              <div class="table-responsive mtop15">
                <table class="table table-hover api-guide-table" id="api-guide-table">
                  <thead><tr><th>Recurso</th><th>Rota base</th><th>Métodos implementados</th><th>Pesquisa</th></tr></thead>
                  <tbody>
                    <?php foreach ($endpoints as $endpoint) { ?>
                      <tr>
                        <td><strong><?= html_escape($endpoint[0]); ?></strong></td>
                        <td><code>/api/<?= html_escape($endpoint[1]); ?></code></td>
                        <td><?php foreach ($endpoint[2] as $method) { ?><span class="http-method"><?= html_escape($method); ?></span><?php } ?></td>
                        <td><?= $endpoint[3] ? '<code>/search/{termo}</code>' : '—'; ?></td>
                      </tr>
                    <?php } ?>
                  </tbody>
                </table>
              </div>
              <p class="text-muted">GET admite consulta por ID onde implementada; POST cria, PUT atualiza e DELETE remove,
              sujeitos às regras e permissões de cada controlador. Consulte os contratos do recurso antes de enviar dados.
              Para exclusões, algumas rotas existentes usam <code>/api/delete/{recurso}/{id}</code>.</p>
              <p>Consultas auxiliares: <code>/api/common/expense_category</code>,
              <code>/api/common/payment_mode</code> e <code>/api/common/tax_data</code>.</p>
            </div>
            <div class="guide-section">
              <h4>Respostas e segurança</h4>
              <p>Respostas HTTP típicas: <strong>200</strong> operação concluída,
              <strong>201</strong> recurso criado, <strong>400</strong> requisição inválida,
              <strong>401/403</strong> autenticação ou permissão insuficiente,
              <strong>404</strong> recurso não encontrado, <strong>500</strong> falha interna.
              O código efetivo depende do controlador e de suas validações.</p>
              <p class="text-muted">Se uma rota ou campo não existir nesta instalação, não pressuponha que ele está disponível.
              A documentação acompanha os controladores distribuídos e não constitui uma promessa de endpoints futuros.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
(function () {
  'use strict';
  var filter = document.getElementById('api-guide-filter');
  var table = document.getElementById('api-guide-table');
  if (!filter || !table) { return; }
  filter.addEventListener('input', function () {
    var term = filter.value.toLocaleLowerCase('pt-BR').trim();
    Array.prototype.forEach.call(table.tBodies[0].rows, function (row) {
      row.style.display = row.textContent.toLocaleLowerCase('pt-BR').indexOf(term) >= 0 ? '' : 'none';
    });
  });
})();
</script>
<?php init_tail(); ?>
