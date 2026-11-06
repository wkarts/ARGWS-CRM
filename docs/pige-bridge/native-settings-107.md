# Configuração nativa PIGE 1.0.7

A grade usa o inicializador de tabela inline do CRM quando disponível e permanece legível como tabela HTML. Nova integração e Editar abrem modal Bootstrap nativo com guias Conexão, Fatura local, Cobrança externa e Como conectar. Desativar exige confirmação visual e POST protegido. Não há exclusão de históricos, novo provider ou ativação automática.

O formulário de edição carrega apenas dados públicos autorizados; token nunca retorna. Escola/empresa/conta/ambiente e identificador existentes são imutáveis no servidor e bloqueados na apresentação. Revisões são transportadas em campos ocultos. Cada formulário salva separadamente e redireciona para uma lista nova; reabrir a conexão obtém a revisão atual. Salvar a conexão revoga a delegação local e torna obsoleto o consentimento externo. Consentimento externo de revisão antiga não aparece marcado como habilitado no modal.

A configuração continua administrativa no contexto primário e lista empresas autorizadas por settings.edit. Não muda a fronteira de rotas nem abre módulos globais em empresas secundárias. Funcionário, moeda e conta financeira continuam campos de ID validados pelos serviços, não seletores novos; essa melhoria fica pendente.

## Segurança e efeitos

`POST /admin/pige_bridge/settings/disable` aceita connection_key, revision, CSRF e contexto nativos. Scope vem do registro autorizado, não do request. O writer existente revalida ACL e revisão sob lock e preserva token, origem e histórico. Um formulário obsoleto falha. Sem chamada bancária, emissão, baixa ou efeito acadêmico. JSON embutido usa escape HEX para impedir fechamento de script e expõe somente escopo, revisão, flags e mapping público; nenhuma cifra ou hash de credencial.

## Contrato bilateral confirmado

PIGE360-self main f47b8916b92231ac29b56862b133c9d2b2eec851 já possui GET/PUT `/api/v1/schools/{school_id}/crm-bridge/configuration` e POST `/connection-test` em backend/app/crm_bridge.py. Exigem JWT administrativo, vínculo escolar e integrations.manage. PUT requer base_url, issuer_id, account_id, environment, secret, enabled e version. O segredo HMAC da ponte não é um token Bearer administrativo.

O teste usa POST `/pige_bridge/api/v1/diagnostics` no CRM, com scope completo, nonce e HMAC temporal vinculado ao caminho. Não descobre IDs nem cadastra a outra ponta. A documentação antiga INTEGRACAO-CRM.md no PIGE não retrata todas as rotas atuais.

Autoconfiguração por URL/token não foi implementada. O menor próximo passo é um contrato de descoberta limitado a uma conexão, credencial específica, resposta mínima e transporte HTTPS com bloqueio SSRF/redirect. Nunca ampliar o diagnóstico existente para scope incompleto ou reutilizar segredo financeiro como sessão administrativa. Configuração/consentimentos financeiros devem continuar explícitos.

## Validação deste checkpoint

Executados: cinco suites Python (pige_bridge_settings_ui, configuration_static, external_static, invoice_static e bb_static), quatro checks cada; node --check modules/pige_bridge/assets/settings.js; git diff --check. São inventário estático e sintaxe JS, não execução PHP/HTTP/browser.

Ampliados testes PHP de guards da ação disable e tracking/upgrade até 107. PHP não está instalado neste executor; PHP/MySQL/browser não executados. CI deve executar testes administrativos, migration e isolamento antes de publicação final. Homologar: Novo→Cancelar→Editar outra conexão; token apagado ao fechar; guia financeira após criação; revisão obsoleta; rotação; desativação com permissões revogadas; duas empresas e perfil restrito; busca/paginação/responsividade.

## Versão e operação

Módulo 1.0.7, migration 107 NOOP; nenhuma DDL, backfill ou seed. Migrations anteriores intactas. Integração prevista na PR61 pelo integrador; versão core e gates agregados pertencem à composição final. Sem merge/deploy.

Após aprovação, atualizar pelo runner nativo e confirmar marcador 1.0.7. Backup consistente continua obrigatório. Em falha, suspender uso e corrigir/reaplicar, sem reduzir marcadores. Reverter apresentação não restaura consentimentos revogados nem reconstrói estados gravados depois; preservar histórico.

## Revisão e validação na composição PR61

Patch do commit `924bec427ea413ec1a280f05bf968d8beeab5393` recebido pela
coordenação, com 38.873 bytes e SHA-256
`89d2b2415e32fdf800ef5cef4f2e4ddcf09e2613f05ef796a195fb52d0afebe9` conferidos.
`git apply --check` passou sobre o incremento de CI `dc633efd`.

Executados com PHP 8.4 local: guards administrativos, 31 checks do runner de
migration e 22 checks puros de conexão. Em MariaDB 11.8 descartável foram
executados `pige_bridge_mysql.php` e `pige_bridge_connections_mysql.php`,
incluindo schema, instalação repetida, upgrades, tracking, falha de schema,
criptografia, ACL/delegação, rotação, revisão e isolamento entre empresas.
MariaDB não substitui a matriz remota MySQL 8.4/PHP 8.1 e 8.5.

O teste MySQL de conexões foi ampliado com o controller real de Desativar,
I/O de framework sintético e writer real: GET 405, CSRF 403, não administrador,
permissão da empresa revogada, revisão obsoleta, sucesso único, replay rejeitado,
segredo/cifra/escopo/data de criação preservados, consentimento local revogado
e registro da outra empresa intacto mesmo com issuer_id forjado no POST.
Esse teste não substitui o bootstrap HTTP autenticado completo; o despacho por
contexto principal/secundário continua sendo responsabilidade do middleware
existente, que não foi alterado pelo incremento.

`PHP_BIN=/tmp/crm-bin/php CHROMIUM_PATH=/usr/bin/chromium node tests/pige_bridge_settings.browser.cjs`
passou em 1280 e 390 pixels: view real, Bootstrap/jQuery/DataTables e JS do módulo,
busca/paginação, formulário A/B, revisão/CSRF/contexto transportados, Novo após
Editar, token limpo após o evento de fechamento, consentimento externo obsoleto
desmarcado, cancelamento e escape de nomes. Bootstrap do framework, decoração
da tabela e dados são sintéticos; nenhum POST HTTP, provedor ou produção.
O teste agora integra o workflow de interface nativa; os 20 testes Python
estáticos do módulo e a sintaxe JS também passaram.

O contrato bilateral descrito acima é informação do checkpoint recebido;
esta revisão não acessou nem alterou PIGE360. Autoconfiguração bilateral,
homologação autenticada ponta a ponta e reorganização transversal de menus
continuam pendentes. Não se declara prontidão operacional ou aprovação de CI remoto.
