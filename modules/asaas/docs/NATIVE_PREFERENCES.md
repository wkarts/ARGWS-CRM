# Preferências operacionais nominais (Asaas 1.5.5)

## Capacidade real

A empresa ativa tem uma seleção única para novas cobranças: produção ou
homologação. Cada ambiente mantém nome de apresentação, descrição com o único
placeholder `{invoice_number}`, e flags independentes de Pix, boleto e cartão
hospedado à vista. O cartão ainda depende do contrato nativo do checkout.
A tela usa abas nativas e catálogos PT-BR/English.

Não há herança do gateway primário, cópia de credenciais, atualização remota ao
salvar preferências nem parâmetros financeiros fictícios. Moeda BRL, pagamento
integral, vencimento da fatura e encargos/descontos zero permanecem no contrato
existente. Prorrogação, parcelamento e novos recursos financeiros exigem outro
plano. A API de [criar cobrança](https://docs.asaas.com/reference/criar-nova-cobranca)
aceita descrição, mas isso não equivale a implementar todos os parâmetros da API.

## Migração e defaults

A migration nativa155 acrescenta três tabelas InnoDB/utf8mb4_bin (SQLite nos testes):

- `asaas_company_preferences`: PK empresa; seleção ativa e revisão otimista.
- `asaas_company_profiles`: PK empresa + ambiente; cinco valores e revisão.
- `asaas_company_origin_profiles`: PK origem; snapshot de emissão e hash, com
  vínculos de empresa, conta e origem. FKs restritivas, sem exclusão em cascata.

Nenhuma linha de configuração, conta ou origem antiga é copiada ou reescrita.
GET apresenta defaults sem persistir: nome Asaas, descrição
`Fatura {invoice_number}`, três meios habilitados. Esses defaults não autorizam
emissão: nenhuma seleção é criada. Um operador autorizado deve escolher
explicitamente uma conta habilitada e verificada. Uma conta antiga continua
existindo com suas credenciais, webhook, histórico e automação intactos.

O schema é conferido por colunas, tipos, null/default, PKs, FKs/regras, engine e
collation. Nova emissão nominal exige estrutura completa e tracking nativo ativo
`installed_version >= 1.5.5`. Tabela parcial, tracking154, falha na escrita ou no
readback da versão não autorizam emissão. O runner nativo é o único writer do
tracking; replay da migration completa apenas DDL ausente. Não se toca migration
histórica.

## Contexto, concorrência e origem

Salvar/selecionar exige contexto selado da empresa ativa, vínculo do ator,
permissões de definições e módulo ativo. Formulários carregam CSRF nativo,
CSRF da preferência e revisões de perfil/seleção/conta. O serviço revalida ACL e
revisões dentro de transação própria. Um administrador sem vínculo/grants não
obtém acesso por ser administrador global.

Nova emissão revalida seleção, meios, conta habilitada/verificada e revisões
antes de consultas externas e novamente antes de reservar a origem. Mudanças
concorrentes de perfil/conta/seleção abortam antes do POST. O snapshot e seus
vínculos são persistidos antes da única tentativa de criação remota. Inclui
ambiente, IDs, revisões, meios, apresentação e descrição já renderizada.

A âncora `asaas_profile_contract=company_profile_v1` fica no snapshot canônico da
fatura, coberto por seu hash. Origem155 sem o snapshot complementar (ou com hash,
empresa, conta, ambiente ou meio divergente) falha fechada. Isso impede tratar
perda da nova tabela como origem histórica. A resposta canônica deve confirmar
exatamente a descrição enviada.

Repetir uma intenção já persistida retorna a mesma origem sem outro POST.
Consulta/replay não seguem preferências atuais. Origens anteriores sem âncora nem
snapshot complementar mantêm o contrato histórico; não são adotadas pelo perfil155.

## Rollback e operação incerta

`down()` é preservador: não remove configuração nem evidência financeira. Reduzir
tracking bloqueia novas emissões, mas consulta/replay de origem existente continua
com seu snapshot. Restaurar código antigo exige revisão operacional separada; não
há promessa de que código anterior interprete o novo contrato de evidência.
Timeout/5xx em mutações não é rejeição definitiva e não autoriza recriar a cobrança
ou o cliente. A intenção/origem permanece disponível para conciliação.

## Testes e limites

- `tests/asaas_module_migration_155.php`: runner nativo; DDL parcial, falha de
  tracking/readback, retry/replay, downgrade e preservação.154 permanece explícito.
- `tests/companies/asaas_preferences.php`: A/B × produção/homologação, contexto e
  grants, seleção explícita, revisões, métodos, descrições e rollback de auditoria.
- `tests/companies/asaas_preferences_recovery.php`: revogação durante GET,
  snapshots perdidos/adulterados e consulta/replay histórico149 após155/rollback.
- `tests/companies/asaas_preferences_controller.php`: controller/DTO/view reais,
  CSRF, controle de edição, escape HTML e catálogos PT-BR/English.
- `tests/asaas_customer_contact_policy.php` e `tests/asaas_mutation_no_retry.php`:
  SDK/Guzzle reais com transporte sintético; envio negativo, logs, métodos,
  timeout/500, redirects e origem fixa.

SQLite, transporte sintético e HTML renderizado não provam MySQL, browser
DockerCI nem operação financeira no provedor. Os testes não ativam conta real e
não enviam credenciais ou requisições ao Asaas. Testes de emissão antigos usam um
bootstrap155 explicitamente sintético, restaurando seus checkpoints históricos;
fixtures HTTP usam o runner nativo antes da seleção e não forjam tracking.
