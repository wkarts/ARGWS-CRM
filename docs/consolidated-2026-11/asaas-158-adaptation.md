# Referência Asaas 1.5.8 e adaptação multiempresa

A release 1.5.8 substitui 1.5.7 como contrato funcional de origem. Não equivale à versão instalada do módulo no CRM: o CRM preserva sua sequência própria, agora 1.8.6/186. Nenhuma migration 158 existente foi substituída. A integração completa da referência ainda não está concluída.

Fonte efetivamente baixada: [ZIP oficial](https://github.com/wkarts/asaas-payment-module-perfex-crm/releases/download/v1.5.8/asaas-module-v1.5.8.zip). SHA-256 `a8c5d140c6b099805d34758aaae6fb4187d0afd1495bf8f55d96f5cb74a05da3`, 660 arquivos (768 entradas incluindo diretórios). O manifesto `asaas-158-source-manifest.json` registra hash de cada arquivo, inclusive vendor. O hash do ZIP não é o hash de outro empacotamento. Comparação com a cópia local 1.5.7 identificou 49 arquivos não-vendor adicionados/alterados; isso não é contagem de todas as diferenças de dependências.

## Matriz inicial da atualização

| Requisito/fonte | Código atual do CRM | Adaptação/evidência | Situação |
|---|---|---|---|
| Exportação integral paginada em `AsaasReports::export` | Monitor remoto validado por empresa/conta/revisão | `CompanyPaymentReport`, formulário nativo e ação POST com CSRF; CSV entregue somente após todas as páginas | Implementado para cobranças e filtros já suportados pelo monitor |
| Arquivo privado, orçamento de execução, rejeição de repetição e lista variável | Transporte limitado e DTO mínimo existentes | Temporário 0600 descartado em `finally`, 10 mil linhas/8 MiB/45s, duplicatas/total variável/ACL revogada rejeitados | Testes MySQL com provedor simulado |
| Autorização de relatórios | ACL nativa por empresa | Exportação exige settings.edit e invoices.view; consulta mantém settings.view e invoices.view | Granularidade específica de relatórios da referência ainda pendente |
| Saldo/extrato/estatísticas e demais relatórios | Não adaptados | Não são expostos pelo exportador de cobranças | Pendente; impedir uso de gateway global/conta compartilhada |
| Pix Automático: autorização, jobs, cron e rotas públicas | Não incorporados | Inventário da fonte; buscas por ID e cursores globais não foram copiados | Pendente implementação e testes por empresa/conta/ambiente |
| SDK 0.3.0/dependências da referência | Lock e runtime da release incorporados no186 | 534 assinaturas públicas preservadas, 14 novas; 23 suites de contrato do CRM passaram | Dependência adaptada; isso não habilita Pix Automático nem completa os relatórios |
| Edição/reembolso/recriação de grupos e alocações parciais | Vínculos e recebimentos por parcela, remoção individual/conjunta184 | Preservados integralmente | Ciclo restante pendente |
| Cartão com trânsito de dados | Decisão específica ainda pendente | Nenhum novo fluxo de coleta implementado | Aguarda decisão |

## Escopo da exportação de cobranças185

A tela permanece em Configurações > Meios de pagamento > Asaas, na consulta remota existente. Após consultar uma conta, um editor de configurações pode exportar todos os resultados dos mesmos filtros, começando pela primeira página. Cada linha informa empresa, conta local, carteira, ambiente, início da consulta, filtros, ID remoto, situação, meio, valor nominal e vínculo local quando já comprovado. Campos privados adicionais do provedor não entram no CSV. Não há recebimento, associação de origem, atualização ou cobrança.

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
