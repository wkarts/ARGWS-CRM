# PR52 — cadastro de cliente próprio no contexto principal

## Escopo

O cadastro de cliente pertencente à empresa principal usa o mesmo controller e
o mesmo writer nativo das empresas nomeadas quando a entrada oficial contém
`/admin/clients/client?source=company`.

O caminho histórico de Clientes continua disponível para páginas fora do
contexto nativo. Quando a requisição é atestada para uma empresa, o controller
oficial usa `NativeCompanyClients`, `CompanyClients` e o CSRF nativo vinculado
ao contexto; nenhum controller `Company_clients` paralelo é carregado.

## Alterações

- `CompanyRequestScope` admite as ações oficiais de Clientes por verbos e
  controller, mantendo listagem, perfil, contatos, arquivos, grupos, importação,
  vault e permissões no mesmo adapter quando carregam o CSRF nativo.
- `Clients::nativeCompanyClients()` permite o adapter para esse contexto
  atestado e nunca usa `clients_model` nessa operação.
- O formulário oficial preserva `source=company` na action e no redirecionamento
  pós-criação.
- A listagem histórica apresenta o botão de novo cliente com a URL oficial
  quando a empresa principal está selecionada.
- As abas do formulário preservam o marcador para não voltar silenciosamente ao
  renderer histórico; ações POST auxiliares continuam atestadas pelo token.

## Validação local

- `node --test tests/native_customers_contract.test.cjs`: 6 testes aprovados.
- `node --test tests/native_company/sales_form_shared.test.cjs tests/native_company/presentation.test.cjs`: 12 testes aprovados.
- Verificação `node --check` em todos os arquivos JavaScript/CJS de `tests` e
  `application`: concluída sem erro de sintaxe.
- 251 arquivos JSON foram analisados com `JSON.parse`.
- PHP, MySQL/MariaDB, Docker e Playwright não estão disponíveis neste runtime;
  lint PHP e HTTP autenticado permanecem validação pendente para o CI.

## Próximo incremento

Aplicar o mesmo contrato a uma família especializada que ainda possui
`Company_*` executável, removendo sua rota paralela somente depois de o
controller oficial, os adapters e os testes positivos/negativos estarem
completos.
