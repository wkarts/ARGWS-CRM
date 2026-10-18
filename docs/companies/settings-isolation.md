# Definições por empresa: contrato da primeira fatia

## Estado da entrega

Código local, sem publicação, merge, implantação, acesso a provedores ou dados reais. Esta fatia depende da composição do `CompanyRequestScope` pré-autoload, do retorno autenticado/selado no `AdminController` e do despacho dos helpers de opções. Não habilitar uma empresa secundária no fluxo legado sem essas fronteiras.

A rota continua sendo `admin/settings`. Para empresas secundárias, somente `group=company` e `group=email` possuem renderer adaptado neste incremento. As demais seções e operações administrativas globais permanecem explicitamente indisponíveis. O transporte e a fila de e-mail agora recebem origem explícita, com testes sintéticos A/B/legado; consulte mail-origin.md. Ainda não estão concluídos o envio de teste pela interface, a autorização OAuth visual Google/Microsoft, Connect operacional, bancos operacionais, identidade fiscal visual por empresa ou isolamento dos outros recursos do CRM.

## Fontes de dados

- `issuers_registry.legal_name` e `.tax_id` são a identidade canônica.
- `companyname` e `invoice_company_name` são aliases de `legal_name`; `company_vat` é alias de `tax_id`.
- O cadastro/wizard e Definições devem usar `CompanySettingsIdentity::read/save` para o mesmo perfil. Não copiar nome/documento para uma segunda autoridade em `options`.
- Perfil da principal continua nas opções legadas existentes, acessadas pelo adapter canônico. Perfil de secundárias fica em `settings_json.company_profile`.
- Opções privadas de secundárias ficam em `settings_json.company_options`, sem copiar nenhuma configuração da instalação. O documento JSON preserva propriedades não relacionadas.
- Opções comuns da principal continuam nas opções legadas, por ID principal explicitamente persistido. Nunca inferir principal pelo menor ID, usuário administrador, sessão ou valor default.
- Identidade principal `pending` preserva os valores brutos de `legacy_identity_json`, inclusive documento inválido. Correções explícitas passam por validação de nome/CPF/CNPJ e unicidade; o snapshot bruto não é apagado. Um documento válido sozinho não valida um nome legado inválido/placeholder.
- PDFs/documentos fiscais já emitidos não são reescritos por este incremento.

## APIs internas

`CompanySettingsRuntime::enterRequest($db, $prefix, $issuerId, $staffId): callable` valida identidade e membership ativa e retorna uma liberação idempotente, restrita ao dono. Segundo escopo ou troca durante a operação são recusados. `run(..., callable)` envolve esse lease em `finally`. O middleware fecha o lease em `post_controller` e shutdown.

O helper deve despachar para `active/get/update/add/delete/exists` apenas quando o contexto explícito estiver ativo. Erro/revogação de contexto secundário encerra a operação; nunca autoriza fallback legado. Não há setter via config, parâmetro HTTP ou sessão dentro desses serviços.

`issuerId/staffId/isPrimary/can/assertCan/canInfrastructure` permitem consultar o escopo. `can` usa a permissão completa `settings` por recurso/empresa, sem exigir simultaneamente permissões globais de staff.

`CompanySettingsIdentity($db,$prefix)->read($issuerId)` retorna `legal_name`, `tax_id`, `identity_status` e `profile`. `save($issuerId,$staffId,$input)` aceita somente `legal_name`, `tax_id` e mapa `profile`. A ACL é `settings.edit`; o cadastro que tiver outra autorização precisa de uma integração revisada, e não de um bypass arbitrário.

`CompanySettingsIdentity::legacyPrincipalOption($db,$prefix,$name)` atende leitores legados explícitos da principal para aliases/perfil reconhecidos por `handles`. Após core pronto, escritas de identidade sem contexto não devem atualizar opções paralelas. Chamadas diretas a `App::get_option/get_options`, além dos helpers, precisam respeitar o mesmo contrato canônico.

`CompanySettingsStorage($db,$prefix)->get($issuerId,$name)` é a leitura interna explícita para jobs/webhooks. O chamador deve validar previamente a ownership persistida do evento/job. O serviço nunca consulta sessão, seleção de navegador ou empresa default. Ausência de valor devolve string vazia; empresa inexistente/inativa, principal não resolvida e JSON inválido falham fechados.

## Exceções de infraestrutura

Somente as seguintes chaves são classificadas como instalação compartilhada: `cron_has_run_from_cli`, `last_cron_run`, `last_updated_date`, `update_info_message`. Escrita exige principal explícita e master confirmado ou a concessão administrativa legada registrada na migração. Essa compatibilidade nunca concede administração em outra empresa. Toda chave desconhecida continua privada da empresa. SMTP, credenciais OAuth, Connect e bancos não pertencem à allowlist.

Não há herança de credenciais, secrets, host SMTP, conta bancária ou tokens. Valor vazio e chave apagada não reativam nenhuma herança.

## Segurança e transações

- Escrita serializada por `issuers_lock`, em transação própria; não assume nem confirma transação do chamador.
- ACL/membership são rechecadas dentro da transação; confirmação de persistência antecede sucesso e audit. Falha do audit reverte a escrita.
- O audit contém ator, empresa e tipo de evento, sem nomes das opções, valores ou secrets.
- `IssuerStorage` impede SQL/bindings sensíveis em respostas/logs do framework.
- `Settings_model` preserva formatos serializados/JSON e a interface de criptografia já existente. Senhas não perdem espaços significativos; falha de criptografia recusa a escrita.
- O renderer nunca recebe a senha. Campo vazio mantém o secret; remoção exige checkbox explícito. Transporte real ainda bloqueado.
- O bypass de `App_Model`/gateways ocorre somente quando `CompanyRequestScope::sealedFor(get_instance())` atesta a requisição; sem selo, o constructor legado permanece.
- A view não usa `init_head`, menu global ou callbacks de módulos. Usa CSS nativo, token de contexto, CSRF nativo e escaping HTML.
- Catálogos públicos `IssuerRegistry::available/catalog/get` devem omitir `settings_json`; não retornar blobs de configuração junto da identidade. Essa composição pertence à camada de cadastro.

## Testes executados em 2026-10-18 UTC

PHP-WASM com PDO SQLite, versões 8.1 e 8.5:

- `tests/settings/company_settings.php`: 78 verificações.
- `tests/settings/settings_model_controller.php`: 108 verificações totais, incluindo as 78 acima. Executa classes reais de serviço/model/controller/view, com adaptadores explícitos para I/O do framework e a interface de criptografia.
- Cenários: A/B positivos, alteração da principal, ausência/vazio/delete sem fallback, revogação, escopo aninhado, release idempotente, falha de audit/rollback, transação externa, identidade canônica, pending/raw, correção parcial, documento duplicado, armazenamento corrompido, permissões globais ausentes, overposting, model_init/gateways ausentes, senha não exibida, token, CSRF e XSS.
- Lint PHP 8.1 dos seis arquivos PHP de produção alterados/adicionados: passou.
- Regressões PHP 8.1: core 72; registry 75; staff/resource 52 adicionais; master/self-profile 26 adicionais; storage boundary 11; core upgrade 72; idioma 33; catálogo de gateways 67.
- Node: `argws_platform_contract`, `crm_integrations_contract`, `crm_ui_policy`: 18 testes passaram.
- `git diff --check`: passou.

Os números de testes incluídos por outros arquivos não devem ser somados como casos independentes. SQLite traduz parte do SQL MySQL; não prova InnoDB/locks reais, concorrência MySQL, criptografia nativa OpenSSL, autoload HTTP composto ou integração de transporte. Esses testes permanecem pendentes antes de habilitar/implantar a composição.

## Roteiro manual de composição

1. Instalação sintética com principal persistida e duas empresas A/B; usuário com memberships e `settings.view/edit` em ambas, sem grant global settings.
2. Selecionar A no seletor autenticado; abrir Definições/Informações da Empresa e E-mail. Confirmar empresa visível e ausência de credenciais da principal/B.
3. Editar perfil e SMTP de A; alternar para B. B deve permanecer vazio/independente. Editar B, retornar para A e conferir estabilidade.
4. Conferir a mesma identidade/perfil no cadastro e em Definições. Corrigir principal pendente explicitamente; snapshot bruto permanece preservado.
5. Voltar para um formulário de A após alternar para B; envio com token antigo precisa falhar 409 antes de gravar.
6. Revogar membership, desativar empresa ou alterar token; toda nova leitura/escrita deve falhar antes da hidratação.
7. Abrir seção financeira, tags, branding, envio SMTP ou endpoint não adaptado em empresa secundária: negar, sem callback global.
8. Confirmar que principal mantém fluxos legados permitidos e que metadados/branding de instalação não são redefinidos silenciosamente.
