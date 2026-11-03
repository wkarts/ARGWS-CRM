# Checkpoint local r1 — 2026-10-27

Este incremento continua o pacote local restaurado da Library. Nenhum arquivo foi enviado ao GitHub.

## Incremento concluído

- Entrada oficial para cadastro de cliente próprio da empresa principal em `/admin/clients/client?source=company`.
- `CompanyRequestScope` atesta o marcador somente no controller/método Clients correto e reusa a admissão nativa já limitada por verbo.
- `Clients::nativeCompanyClients()` usa `NativeCompanyClients` no contexto principal somente nessa entrada atestada; o fluxo não cai em `clients_model`.
- Formulário, retorno pós-criação, botão de novo cliente e abas preservam `source=company`; ações POST auxiliares entram no mesmo adapter quando carregam o CSRF nativo.
- Links de clientes convertidos no renderer de Leads para `/admin/clients/client/{id}`.
- Documento do incremento: `docs/companies/pr52-native-client-primary-adapter.md`.

## Evidências executadas

- `node --test tests/native_customers_contract.test.cjs`: 6 aprovados.
- `node --test tests/native_company/sales_form_shared.test.cjs tests/native_company/presentation.test.cjs`: 12 aprovados.
- `node --check` para todos os arquivos JavaScript/CJS de `tests` e `application`: concluído sem saída de erro.
- `JSON.parse` em 251 arquivos JSON: aprovado.

## Limitações atuais

- PHP não está instalado neste runtime; lint PHP e testes PHP não foram executados nesta rodada.
- Dependências de build (`node_modules`/`mix`), PHP, Docker, MySQL/MariaDB e Playwright não estão disponíveis; build, lint PHP, validação HTTP autenticada, upgrade e responsividade permanecem para o CI.
- O censo ainda contém controllers/views `Company_*` paralelos. O próximo incremento deve propagar o contrato para ações secundárias de clientes e migrar browsers que usam `company_clients` antes de tombstonar referências.
- Homologação externa do Banco do Brasil/CNAB 400 continua sem comprovação neste ambiente.

## Estado físico

- Arquivos PHP no projeto: 8957.
- Controllers `Company_*` ainda presentes no diretório administrativo: 49; a presença não é tratada como conclusão.
- Arquivos JS/CJS enumerados em `tests` e `application`: 216.

## Próxima ação

Migrar as ações secundárias do perfil de Clientes para o mesmo contrato oficial, atualizar os browsers dependentes e executar o lote de testes de Clientes antes de remover os paralelos sem referência.
