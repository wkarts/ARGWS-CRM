# Recuperação administrativa de credenciais Asaas

## Escopo

Os recebedores legados e as contas por empresa continuam separados. Produção e
homologação possuem tokens distintos. Nenhuma leitura de tela, migration ou
recuperação consulta o provedor, cadastra webhook remoto ou rotaciona credenciais.

A interface mostra máscaras por padrão. Uma ação POST autenticada recupera o
segredo, que pode ser revelado ou copiado na resposta protegida. Um GET posterior
não contém o segredo. Respostas usam `Cache-Control: no-store` e
`Referrer-Policy: no-referrer`; tokens não entram em URLs, sessão, alertas ou logs.

## Schema e upgrade

A migration nativa 154 carrega `WebhookTokenSchema.php` e executar
`Asaas\WebhookTokenSchema::ensure($this->ci->db, db_prefix(), true)` adaptando o
acesso ao banco ao runner nativo. Ambas as tabelas existentes precisam estar
presentes antes da chamada:

- `asaas_webhook_integrations.token_cipher`: TEXT NULL, utf8mb4_bin no MySQL
- `asaas_company_accounts.token_cipher`: TEXT NULL, utf8mb4_bin no MySQL

O helper é idempotente, valida os campos e não modifica linhas existentes. Não
substitui migrations históricas. A DDL aditiva pode adquirir locks de metadata;
planeje o upgrade conforme o volume e a versão do banco. A validação SQLite não
prova comportamento de locks ou DDL do MySQL em produção.

Novas ações administrativas exigem confirmação nativa de `installed_version >=
1.5.4`. Ter a coluna pronta após falha de tracking não habilita revelação ou
rotação. Os callbacks existentes continuam autenticando por hash sem esse gate.

Registros antigos continuam com hash válido e cifra nula. O texto original não
pode ser recuperado de um hash. A tela solicita regeneração explícita, que
invalida imediatamente o token anterior. Não existe período de sobreposição.

## Cifra, ACL e auditoria

A biblioteca nativa CI Encryption cifra um envelope que vincula o token ao tipo
de recebedor, empresa, conta, ambiente e identificador público. Ao recuperar,
todos esses vínculos e o SHA-256 de verificação precisam coincidir. Alteração,
transplante, chave indisponível ou falha de decriptação impedem a recuperação.
A autenticação dos callbacks continua usando o hash, sem precisar decriptar.

Novos hashes e cifras são gravados na mesma transação com auditoria. Falha de
cifra, escrita ou auditoria não confirma a rotação. Preserve a chave de
criptografia da instalação nos backups protegidos existentes; perdê-la impede
recuperação, mas não desfaz o hash que já autentica os callbacks. Nunca publique
essa chave nem copie credenciais entre empresas.

A recuperação/rotação nominal preserva a administração contextual existente e
também permite administradores globais ativos vinculados à empresa secundária
com concessão explícita `settings:edit`. Revisão de conta e empresa/ator do
contexto ativo são conferidos novamente. Essa exceção é específica desta
capacidade e não altera a política geral `IssuerResourceAccess`. O legado
fica restrito à instalação principal. As chaves API nominais também podem ser
recuperadas por POST administrativo, com auditoria, usando sua cifra já existente;
a recuperação não substitui, verifica nem reativa a chave.

## Rotas

- POST `admin/asaas/reveal_integration_token`: ID de recebedor e CSRF próprio
- POST `admin/asaas/rotate_integration_token`: rotação explícita, sem token externo
- POST `admin/company_asaas`, ação `reveal_webhook` ou `reveal_api_key`: contexto,
  ID da conta, revisão e CSRF próprio
- POST `admin/company_asaas`, ação `rotate`: os mesmos campos e confirmação
  explícita da revogação

URLs de callback vêm exclusivamente de APP_BASE_URL e index_page validados.
A rota nominal continua `company_asaas_callback/integration/{public_id}`; a
legada continua `asaas/gateways/callback/integration/{public_id}`.

## Verificação local

`tests/asaas_webhook_token_schema.php` cobre adição, replay e preservação de
legado. `tests/asaas_webhook_tokens.php` cobre o store legado.
`tests/companies/asaas_webhook_tokens.php` cobre cifra nativa, recuperação,
isolamento, transplante, adulteração, legado e rollback.
`tests/companies/asaas_secret_controller.php` cobre CSRF, replay, máscaras,
cache e ausência de segredos nos DTOs GET/sessão. Os testes usam dados sintéticos
e transporte simulado, sem homologação financeira ou chamadas reais ao Asaas.

O rollback de código não exige apagar colunas: preservar ciphertext e hashes
permite retorno seguro à versão anterior que os ignora. Remover a nova coluna
é destrutivo para a recuperação e não faz parte desta mudança.
