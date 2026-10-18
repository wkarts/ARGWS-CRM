# Ponte opcional PIGE → CRM: contrato v1

Esta entrega é a primeira etapa de recepção durável, autenticação e idempotência. O módulo não emite, cancela, altera ou confirma cobranças. Toda resposta aceita é `status: blocked`, `reason: issuer_finance_not_isolated`, `capabilities.issue_charge: false`. HTTP 202 significa somente que uma solicitação bloqueada foi persistida; não significa cobrança criada. O financeiro nativo PIGE permanece independente.

## Instalação e habilitação explícita

1. Homologar e fazer backup. Instalar/ativar o módulo `pige_bridge`, que cria somente `tblpige_bridge_requests` (prefixo real da instalação). Não há backfill nem alteração de tabelas financeiras.
2. Por padrão a ação responde 503 e não persiste recibos; a inicialização normal do CRM permanece. Para habilitar a recepção em uma instalação isolada, configurar `APP_PIGE_BRIDGE_ENABLED` como booleano `true` em app-config.php; string `"true"` não habilita.
3. Configurar `APP_PIGE_BRIDGE_CONNECTIONS` como array associativo: chave estável da conexão (ASCII letras, números, `_` ou `-`, até 64), valor `{enabled: true, scope: {school_id, issuer_id, account_id, environment}, secret: <segredo protegido>}`. Não configurar segredos via repositório, logs ou URL. O segredo deve ter ao menos 32 bytes e ser distinto por conexão. IDs escola/cobrança são UUIDs minúsculos; issuer_id é string inteira positiva canônica; conta é identificador explícito, nunca credencial.
4. Se a proteção CSRF estiver habilitada, adicionar apenas `pige_bridge/api/v1/requests` a `$app_csrf_exclude_uris` no app-config.php antes do carregamento de config.php. Esse endpoint usa HMAC e não aceita sessão/cookie como autenticação. Não desabilitar CSRF globalmente.
5. Exigir HTTPS no proxy/servidor, limitar corpo a 16 KiB e limitar taxa. Configuração de infraestrutura não é executada por esta PR. Não liberar chaves de produção durante homologação.

Desativar o módulo ou remover a flag fecha o endpoint. Dados e recibos permanecem; não há desinstalação destrutiva. Nenhuma configuração real ou segredo foi criado nesta entrega.

## HTTP e assinatura

POST `/pige_bridge/api/v1/requests` com o envelope exato na fixture `tests/fixtures/pige-crm-v1.json`. Campos adicionais são rejeitados nos três níveis. Corpo JSON canônico UTF-8: sem espaços de formatação, Unicode e `/` não escapados, ordem de campos preservada. Em Python: `json.dumps(value, ensure_ascii=False, separators=(',', ':'), allow_nan=False)`. O CRM reserializa e compara bytes, rejeitando chaves duplicadas e representação ambígua. Reenvios preservam corpo/event_id literalmente; timestamp e assinatura são renovados.

Headers:
- X-Pige-Timestamp: segundos Unix positivos, tolerância ±300 segundos.
- X-Pige-Event-Id: UUID igual ao envelope.
- X-Pige-Signature: `sha256=` seguido do HMAC-SHA256 hexadecimal minúsculo.

Mensagem assinada: timestamp + LF + event_id + LF + `POST` + LF + caminho acima + LF + corpo exato. O método/caminho não são inferidos de headers encaminhados. Aliases diretos do controller são rejeitados. Assinatura comparada em tempo constante. Escopo precisa coincidir exatamente com uma única conexão habilitada; nenhuma herança de emitente/conta/ambiente.

`amount_cents` é inteiro positivo até 999999999999; currency é BRL; data deve existir no calendário; descrição tem até 500 caracteres Unicode/2000 bytes, sem controles. A descrição não é persistida. PIGE usa texto genérico para evitar dados de aluno/responsável. O corpo completo também não é persistido, apenas hash, IDs/escopo, valor, vencimento e data do recibo.

Respostas: 202 recibo bloqueado estável; 400 envelope inválido/não canônico; 401 autenticação/escopo inválido; 404 alias; 405 método; 409 conflito de idempotência ou outra solicitação para a mesma cobrança no mesmo escopo; 413 corpo excessivo; 503 integração desativada/armazenamento indisponível. Erros não expõem detalhes SQL ou credenciais. Respostas usam Cache-Control: no-store.

## Banco e concorrência

Uma inserção atômica `ON DUPLICATE KEY UPDATE id=id`, seguida de leitura, preserva a origem. Índices únicos por conexão+evento e por hash de escopo+charge_id impedem duplicação entre retries e eventos diferentes. Mesmo evento/corpo retorna request_id original. Alterar escopo, corpo ou chave da conexão para reutilizar uma cobrança não reescreve o registro. Não há promessa de execução financeira exactly-once; não existe executor financeiro nesta versão. Retentativas de uma cobrança bloqueada somente recuperam o recibo.

A tabela usa InnoDB e collation ASCII binária para IDs/hash. Instalação repetida é não destrutiva. Não executar o teste MySQL numa base CRM: ele exige banco descartável sem tabela de teste preexistente.

## Próximas etapas obrigatórias antes da emissão

Isolamento operacional por emitente em faturas, clientes, contas, numeração, documentos, permissões, jobs e webhooks; vinculação explícita de cliente/conta; orquestração da emissão com origem imutável; outbox de notificações e reconciliação. Só após esses contratos/testes a capacidade de emitir poderá ser habilitada. Eventos received/confirmed são distintos e nenhum deles altera matrículas nesta etapa.

## Validação e rollback

Testes puros e de controller usam stubs; o teste de storage em memória não comprova MySQL. `tests/pige_bridge_mysql.php` executa o schema/modelo em MySQL real quando DSN de teste está definido; sem DSN informa NOT RUN. CI executa versões PHP 8.1 e 8.5 com MySQL 8.4 isolado. Validação visual/HTTP completo em uma instalação CRM e homologação conjunta ainda são necessárias. Rollback: desativar módulo/flag e reverter código, preservando tabela e recibos; não há cobrança remota a desfazer nesta etapa.

## Relação com a API geral do CRM

O módulo `modules/api` continua disponível para integrações gerais. Seu controller de faturas oferece CRUD e encaminha gravações ao `Invoices_model`; a busca passa por `Api_model`. A autorização dessa API é por token e permissões de recursos. Não equivale ao contrato da ponte PIGE: assinatura do corpo com timestamp/evento, escopo escola/emitente/conta/ambiente, recibo durável e chave de idempotência.

A ponte específica não exige disponibilizar o CRUD geral ao PIGE nem o substitui. O futuro executor deve reutilizar serviços internos de emissão já isolados e autorizados, e não simular chamadas HTTP ao CRUD legado ou duplicar regras financeiras. Ambas as entradas precisam respeitar o mesmo isolamento de dados. Nesta versão a ponte apenas recebe solicitações bloqueadas; nenhum desses caminhos já representa emissão multiempresa pronta.

## Migração padrão do módulo 1.0.1

O DDL está em `modules/pige_bridge/migrations/101_version_101.php`, classe `Migration_Version_101 extends App_module_migration`. `install.php` chama o executor nativo `to_latest()`; não contém DDL nem usa um executor paralelo. Como o framework grava a versão do cabeçalho antes do hook de ativação, o instalador define a baseline 1.0.0 somente para este módulo e executa 101, confirmando a versão persistida 1.0.1 ao final. Reativação revalida o schema de forma não destrutiva.

Instalações que usaram os rascunhos 0.1.0/0.1.1 também usam a ação padrão de atualização de banco do módulo (`App_modules::upgrade_database`). A migration valida tabela existente, collation, nulabilidade, índices únicos e InnoDB antes de concluir; schema incompatível exige correção assistida e permanece pendente, sem apagar recibos. O contador do módulo é independente das migrations/release do core. Fazer merge não ativa nem migra automaticamente o módulo. Downgrade destrutivo é recusado; rollback operacional consiste em desativar e preservar recibos.

Convenção da plataforma: módulos novos começam em1.0.1 e possuem migration101, mesmo quando up() é um no-op documentado. Não se cria uma migration100 artificial. O runner nativo aceita101 como primeira migration disponível; o contador é independente do core.
