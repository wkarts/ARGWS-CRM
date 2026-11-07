# Referência Asaas 1.5.8 e adaptação multiempresa

A release 1.5.8 substitui 1.5.7 como contrato funcional de origem. Não equivale à versão instalada do módulo no CRM: o CRM preserva sua sequência própria, agora 2.0.4/204. Nenhuma migration 158 existente foi substituída. A integração completa da referência ainda não está concluída.

Fonte efetivamente baixada: [ZIP oficial](https://github.com/wkarts/asaas-payment-module-perfex-crm/releases/download/v1.5.8/asaas-module-v1.5.8.zip). SHA-256 `a8c5d140c6b099805d34758aaae6fb4187d0afd1495bf8f55d96f5cb74a05da3`, 660 arquivos (768 entradas incluindo diretórios). O manifesto `asaas-158-source-manifest.json` registra hash de cada arquivo, inclusive vendor. O hash do ZIP não é o hash de outro empacotamento. Comparação com a cópia local 1.5.7 identificou 49 arquivos não-vendor adicionados/alterados; isso não é contagem de todas as diferenças de dependências.

## Matriz atual da adaptação (206; seções seguintes preservam a evolução)

| Requisito/fonte | Código atual do CRM | Adaptação/evidência | Situação |
|---|---|---|---|
| Exportação integral paginada em `AsaasReports::export` | Monitor remoto validado por empresa/conta/revisão | `CompanyPaymentReport`, formulário nativo e ação POST com CSRF; CSV entregue somente após todas as páginas | Implementado para cobranças e filtros já suportados pelo monitor |
| Arquivo privado, orçamento de execução, rejeição de repetição e lista variável | Transporte limitado e DTO mínimo existentes | Temporário 0600 descartado em `finally`, 10 mil linhas/8 MiB/45s, duplicatas/total variável/ACL revogada rejeitados | Testes MySQL com provedor simulado |
| Autorização de relatórios | ACL nativa por empresa | Exportação exige asaas_reports.view/export da empresa, settings.view e invoices.view; sem settings.edit | Implementado187 para a exportação disponível; demais relatórios e delegação sem acesso à tela de configurações permanecem pendentes |
| Saldo/extrato/estatísticas e demais relatórios | Catálogo189 com20 recursos | CompanyReportEngine e permissões empresariais separadas, carteira/ambiente exclusivos; MySQL e apresentação testados | Implementado189; homologação individual no provedor e delegação fora de Configurações pendentes |
| Pix Automático: autorização, jobs, cron e rotas públicas | Monitor188; inbox190; adesão/portal194–196; retirada197; ciclos198; worker199; sucessão200 | Consentimento explícito, envio único, QR privado, baixa nativa, recuperação, jobs por vínculo e substituição revisada; testes MySQL/controller/Chromium e provedor simulado | Fluxos operacionais implementados nos incrementos; homologação bancária e aceites integrados completos permanecem pendentes |
| SDK 0.3.0/dependências da referência | Lock e runtime da release incorporados no186 | 534 assinaturas públicas preservadas, 14 novas; 23 suites de contrato do CRM passaram | Dependência adaptada; isso não habilita Pix Automático nem completa os relatórios |
| Edição/reembolso/recriação de grupos e alocações parciais | Associação/baixa182; remoção184; reembolso191; edição201; sucessão202–205 | Principal fixo, membros elegíveis, cadeia auditada, envio único/recuperação e testes reais MySQL; ver asaas-installment-groups.md | Parcial: sucessão preserva principal pago e recria saldo elegível; reembolso histórico integral validado. Redistribuição do saldo posteriormente reaberto e reversões parciais sucessivas ainda pendentes |
| Cartão com trânsito de dados | Decisão específica ainda pendente | Nenhum novo fluxo de coleta implementado | Aguarda decisão |

## Escopo da exportação de cobranças185

A tela permanece em Configurações > Meios de pagamento > Asaas, na consulta remota existente. Após consultar uma conta, um colaborador com as permissões empresariais de relatório pode exportar todos os resultados dos mesmos filtros, começando pela primeira página. Cada linha informa empresa, conta local, carteira, ambiente, início da consulta, filtros, ID remoto, situação, meio, valor nominal e vínculo local quando já comprovado. Campos privados adicionais do provedor não entram no CSV. Não há recebimento, associação de origem, atualização ou cobrança.

A conta precisa estar habilitada e verificada, com revisão atual. O cadastro nativo já impõe unicidade de carteira/ambiente entre empresas (`account_environment`); a API deve confirmar exatamente a carteira antes de cada página. Uma chave de outra carteira não pode ser usada como fallback. Identificadores remotos coincidentes em contas distintas não permitem associar a fatura de outra empresa. O recurso **não é uma solução para empresas que compartilham a mesma conta Asaas** e não deve ser ampliado a saldo/extrato mediante mero filtro local.

O exportador revalida ACL, conta, ambiente/revisão antes e depois das consultas; nenhum CSV parcial é enviado. Textos neutralizam fórmulas de planilhas. Downloads recebem no-store/private, nosniff e nome gerado com IDs locais e ambiente. Não há cache de CSV em sessão ou navegador. O usuário pode salvar o arquivo baixado como qualquer exportação explícita.

O provedor pagina dados vivos, sem snapshot remoto. Total variável ou IDs repetidos abortam; valores podem mudar entre páginas mantendo a mesma contagem. O arquivo identifica o início da consulta e a interface declara esse limite: não representa posição contábil atômica nem homologação financeira.

## Validação e operação

`tests/companies/asaas_payment_report.php` executa serviço real e MySQL descartável, respostas HTTP simuladas: paginação, ausência de campos sensíveis extras, colisão de IDs entre contas, conta/ator estrangeiros, carteira incorreta, revogação durante GET, duplicatas, paginação inconsistente, timeout, escape CSV e renderização da ação somente para editor. O teste não chama Asaas real. O controller existente ganha casos de CSRF ausente e entrada inválida. O runner nativo cobre falha de tracking, retry e replay até185. Resultados finais devem ser conferidos no CI do SHA publicado.

Migration185 é NOOP, sem seeds, backfill ou efeitos financeiros. Deploy não executado. Rollback de apresentação/exportação não altera registros financeiros; preservar markers/migrations e corrigir aditivamente, sem reduzir versão instalada. Não desativar gates para viabilizar distribuição.

Resultados locais desta etapa: 48 verificações adicionais do exportador em MySQL (além das suítes de origem/preferências/monitor carregadas pelo fixture); 23 guards do controller; 361 assertions do runner nativo; 13 de callback; 4 cenários Chromium em 1280/390px, editor/leitor. O fixture inicial do teste adicional de cadastro recebeu o schema nativo170/política157 necessário ao marcador185; nenhuma guarda de produção foi removida. CI Asaas do commit anterior `019cb8a0`: contratos8.1, contratos8.5, settings-ui e MySQL concluídos com sucesso no run42336593077. Isso não comprova CI deste próximo commit.

## Dependências186

Incorporados SDK0.3.0 fixado em `d0ede713d67d4a94d4edde66c0ff19fa4e2a4a7c`, Guzzle7.16.1, promises2.6.0, psr7 2.13.2 e polyfill-php80 1.43.0, conforme lock oficial1.5.8. Pacotes sem alteração de versão e documentação própria do CRM preservados. Classmaps Composer removem somente referências da release a classes que ainda não existem no CRM; PSR-4 permanece. Finais de linha textuais normalizados para LF; não afirmar ZIP/vendor byte a byte idênticos. Nenhuma mudança nos repositórios externos.

A comparação por Reflection entre534 métodos públicos antigos e548 atuais não encontrou remoções ou mudança de nomes/tipos/optionalidade/parâmetros/retornos; essa análise não prova equivalência semântica. `asaas_sdk_030.php` acrescenta353 assertions de versão/ref, existência de todos os alvos do classmap e consultas Pix sintéticas nos dois ambientes. Os23 scripts do job contracts do CRM passaram em PHP8.3.36, inclusive política de contato2316 assertions e ausência de retry mutante847. O teste já existente de captura por token continua rejeitando reenvio após500; nenhum PAN/CVV nem conta real usados. Runner MySQL365 assertions, inclusive tracking186/retry/replay; migration186 NOOP.

Testes adicionais executados **somente na fonte** obtida da tag oficial1.5.8: relatórios159 assertions, controller29, permissões15, API Pix156, serviço Pix109, controller Pix24, interlocks44. Dependências reais com transportes simulados. Esses resultados não validam multiempresa nem autorizam incorporar os controllers/cron globais. O arquivo `vendor/composer/installed.php` difere entre o ZIP oficial e a tag; demais arquivos compartilhados conferidos iguais.

Permanecem de implementação: Pix Automático com autorização/cron/rotas públicas escopados, restante do catálogo e permissões de relatórios, grupos/alocações e R01–R11. SDK não substitui essas camadas. Cartão continua aguardando decisão. Não houve merge, deploy, credencial persistente ou operação financeira real.

CI185 (run42337576279/job128297880853) parou em `asaas_module_migration_155.php:12`, expectativa antiga de header1.8.4. Reprodução local antes da correção confirmada; expectativa atualizada exatamente para1.8.6, sem remover o teste. Reexecução MySQL:48 checks154 e101 checks155 aprovados. Os23 testes de contrato foram repetidos após reconciliar o autoloader raiz e os classmaps Composer; todos passaram. Inventários continuam sem declarar prontidão operacional.

## Permissões empresariais187

`asaas_reports.view` e `asaas_reports.export` entram no catálogo nativo de concessões por empresa. O serviço e o botão exigem ambas, além de settings.view e invoices.view já necessários à consulta nativa. settings.edit deixa de ser requisito e deixa de ser autorização suficiente. A exceção continua apenas a do master da instalação, definida pelo núcleo; o flag global admin de outro usuário não substitui concessões da empresa. Não foram criadas linhas de permissão, credenciais ou consentimentos pela migration187 NOOP.

A concessão permite somente o CSV disponível; não permite editar a integração, revelar segredos, conciliar, cancelar ou emitir cobranças. Settings.view é um pré-requisito explícito da tela atual, não uma concessão inferida: acesso exclusivamente ao catálogo de relatórios sem a tela de configurações ainda requer adaptação de rota. A consulta de monitor já existente mantém seu contrato separado.

Testes MySQL:67 verificações adicionais de relatório, incluindo export sem view negado antes de HTTP, leitor/exportador sem settings.edit autorizado, empresaB negada, administrador global não-master sem concessões negado e revogação durante resposta descartando arquivo. Catálogo consultado sem escrita de grants. Chromium:4 cenários de apresentação1280/390 com exportador sem settings.edit e leitor sem botão. Runner187:369 assertions; callback13; históricos154/155:48/101. Sem homologação financeira ou ampliação do fluxo de cartão.

Controller187:24 verificações de guard após179 cumulativas das suítes antecedentes, incluindo HTTP403 simulado para administrador sem permissão empresarial de relatório; não houve chamada ao provedor. A tela recebeu somente o booleano de capacidade, sem listas de concessões ou segredos.

### 1.8.8 — consulta de autorizações Pix por conta

A guia nativa **Pix Automático** consulta uma página ou um ID remoto somente após validar empresa, conta, ambiente, revisão, carteira e permissões `settings.view`, `invoices.view` e `asaas_reports.view`. Revalida as condições entre chamadas e antes de entregar o resultado. O ID remoto não resolve uma empresa globalmente. A restrição de carteira/ambiente exclusivo já existente impede tratar uma conta compartilhada como relatório isolado.

Fonte contratual: [listar autorizações](https://docs.asaas.com/reference/listar-autorizacoes-pix-automatico) e [recuperar autorização](https://docs.asaas.com/reference/recuperar-uma-unica-autorizacao-pix-automatico), consultadas em 07/11/2026; implementação funcional 1.5.8 preservada como referência. Consultas são explícitas, não polling. O DTO contém apenas ID, situação e periodicidade; não retorna QR, cliente, dados bancários ou contrato. Paginação inconsistente, IDs duplicados ou resposta por outro ID são recusados. Nenhum POST/DELETE de Pix foi habilitado no transporte.

Migration188 é NOOP; não cria tabelas, consentimentos, jobs ou permissões. **Parcial:** esta consulta não implementa adesão/consentimento, emissão de instruções, processamento de webhooks Pix ou cron por conta. Esses fluxos ainda exigem armazenamento durável e vínculos imutáveis adaptados ao CRM. `ACTIVE` remoto não autoriza emissão local nem comprova pagamento. Edição/reembolso/recriação de grupos, alocações parciais e relatórios além do CSV de cobranças continuam pendentes.

Validação: teste MySQL `asaas_automatic_pix_monitor.php` com provedor simulado; cenários de empresas distintas, mesmo ID, ambientes distintos, revisão, ACL revogada durante chamada, conta incompatível, resposta inválida, paginação e ausência de baixas. Controlador testa CSRF e tipos; quatro cenários Chromium testam guia, permissões, escape e responsividade. Runner nativo188:373 asserts, incluindo tracking falho, retry e replay. Não é homologação bancária.

### 1.8.9 — catálogo completo de relatórios da referência

Os20 tipos de relatório da1.5.8 foram incorporados à guia nativa Relatórios da conta: extrato, cobranças, saldo, estatísticas, splits, transferências, antecipações, contas pagas, parcelamentos, assinaturas, transações Pix, autorizações/instruções Pix, chargebacks, estornos, notas fiscais, negativações e recargas. O catálogo funcional da fonte foi preservado. O motor foi adaptado para receber obrigatoriamente um leitor vinculado à empresa/conta/ambiente, sem fallback de SDK, gateway, configuração ou log global.

**Fronteira de autoridade:** `asaas_account_reports.view/export` são permissões novas, distintas de `asaas_reports`. Seu nome e a tela explicam que cobrem a conta inteira, inclusive operações externas ao CRM. Não se tenta isolar saldo/extrato mediante filtro de fatura. Uma concessão de leitura de faturas, relatório de cobranças ou status global de administrador não-master não concede esse acesso. A configuração mantém exclusividade de carteira/ambiente dentro da instalação; não se afirma conhecer a utilização dessa carteira por sistemas externos. Nenhuma migration concede permissões. A rota continua exigindo acesso de leitura à configuração e faturas.

Cada página verifica carteira remota, ACL, conta habilitada/verificada e revisão, sem manter transação SQL durante HTTP. Um ID de cobrança informado para relatório de estornos é consultado somente na conta autorizada. Todos os endpoints e filtros vêm do catálogo fixo; não há URL/SDK/método HTTP escolhido pelo navegador. Auditoria nativa registra empresa, ator, conta e tipo do relatório, sem conteúdo financeiro ou credenciais.

CSV inclui empresa, conta local e carteira, além de ambiente, filtros e horário. Arquivo temporário privado0600, limite10mil linhas/8MiB/45s, entrega só depois da conclusão e revalidação. Falhas fecham o arquivo; fórmulas são neutralizadas; somente colunas declaradas são projetadas. Valores monetários preservam decimal exato (sem conversão para float); valores fora do formato aceito falham. A API não fornece snapshot entre páginas. Relatórios consultam fatos remotos, sem aplicar baixa, autorização ou reembolso.

Migration189 NOOP. Histórico preservado. Sem mudança na SDK ou repositório Perfex. Testes contratuais locais cobrem os20 recursos e falhas de paginação/exportação; testes MySQL cobrem permissões separadas, A/B, ambientes, revogação e ausência de lançamentos financeiros; navegador cobre catálogo, formulários, escape e responsividade. Homologação de cada recurso com a disponibilidade da conta no provedor não foi executada.


### 2.0.5 — sucessão com principal pago preservado

Requisito → existente → lacuna → implementação → evidência: a sucessão202/204 admitia apenas saldo integralmente aberto; faltava preservar parcelas pagas. `CompanyInstallmentPaidRetention` ancora recibo nativo, recibo do provedor, prova canônica e hash do writer original; `CompanyInstallmentGenerations` conserva valor entre gerações; recriação envia somente saldo revisado. Edição/remoção das novas parcelas aceitam o principal anterior comprovado. Reembolso histórico conserva autorização atual e origem original.

Validação local: `asaas_installment_paid_succession.php`31 verificações MySQL adicionais, incluindo dois processos sob lock InnoDB, perda de resposta, envio único, rollback de auditoria, acesso cruzado, adulteração do recibo, nova baixa e revisão de reembolso histórico. `asaas_installment_retained_refund.php`7 verificações adicionais de envio único, rollback, reversão histórica, replay e bloqueio de saldo não alocado. Runner nativo444 asserts;14 cenários Chromium de grupos e8 de recriação. Provedor simulado; as views executam PHP real, mas não equivalem a homologação HTTP completa.

CI204 falhou no teste de remoção: após204, um evento de reembolso sem reversão nativa produz `asaas_installment_full_refund_required`, antes do antigo erro genérico de vínculo. O teste mantém a rejeição exata e acrescenta que não há chamada remota nem mudança no journal; nenhum guard foi removido.

Migration205 NOOP; histórico preservado. Rollback: suspender novas sucessões, preservar provas/recibos e aplicar correção aditiva; não reduzir marcador nem apagar grupos já criados. Uma reversão posterior de pagamento retido pode criar saldo fora do grupo ativo: a igualdade exigida com o saldo nativo bloqueia nova recriação até revisão de alocação, ainda pendente. Reversões parciais múltiplas continuam trabalho de implementação, não limitação atribuída ao provedor. Aceites R01–R11 e CI final continuam separados destas evidências.


### 2.0.6 — consulta de confirmação congelada

A retenção agora distingue o recibo original da prova canônica de confirmação: ambos possuem ID e hash verificados. Um PAYMENT_UPDATED legítimo entre essas provas não invalida a cadeia após o envio. A consulta congelada não pode ser substituída por uma consulta posterior; eventos adversos e eventos posteriores ao seu horário permanecem sujeitos a revisão. Compatibilidade205 sem reescrever journals. Migration206 NOOP. As demais pendências da matriz permanecem abertas.

Validação206:31 verificações de sucessão e7 de reembolso histórico MySQL com atualização intermediária legítima e provedor simulado;448 asserts de migration nativa;13 de callback e sintaxe PHP. CI remoto final registrado na PR, sem presumir conclusão.
