# PR 52 — Compatibilidade limitada do filtro original de tarefas

## Evidência

O workflow **Integridade das stacks ARGWS CRM** no commit `1a485d02e35e771ac06d12ddf37bae28801b84a2`, job `123851991881`, registrou dez respostas **HTTP 422** ao endpoint oficial `POST /admin/tasks/table` ao abrir a interface nativa. Não houve resposta 503. O corpo HTTP não foi publicado; a causa detalhada ainda requer reexecução da suíte de navegação com a correção.

A view original de dashboard `application/views/admin/dashboard/widgets/user_data.php` declara o único hidden input `my_tasks=true` no painel **Minhas tarefas**. O componente DataTables original em `assets/js/main.js` transmite os hidden inputs como parâmetros POST individuais. `NativeCompanyRelatedTaskPage::request()` recusava qualquer chave fora da gramática `filters` antes de converter esse valor.

## Correção delimitada

O parser do backend continua recusando campos legados desconhecidos, `my_tasks=false`, tipos inesperados, operadores `or` conflitantes e filtros acima do limite. **Somente** `my_tasks` explicitamente verdadeiro é convertido em uma regra tipada `BooleanRule`, `equal`, `1`, para o identificador `my_tasks` já existente em `NativeCompanyRelatedTaskRules`. Essa regra consulta a atribuição do funcionário autenticado, sem aceitar IDs de usuário do navegador. Uma lista preexistente de regras `and` é preservada; regras `or` conflitantes são negadas. ACL, origem, CSRF e revisão não são alterados.

O teste `tests/companies/native_task_table_legacy_filter.php` verifica a tradução, as regras adicionais e rejeições. Ele foi incluído uma única vez na fase SQLite do manifesto focado. O container PHP local não possui `mbstring`, por isso somente lint e os contratos do manifesto foram executados localmente; a execução do PHP funcional depende do runtime oficial do CI.

## Verificações nesta retomada

- 10 contratos Python do manifesto: aprovados.
- 9 contratos Node do dashboard: aprovados, incluindo escopo de `dismiss_announcement` na seção oficial `misc` com método `POST` estrito.
- Sintaxe PHP do serviço e novo teste: aprovada.
- Banco/navegador completo e CI do SHA de publicação: **não comprovados neste registro**.

Não é aceite global de paridade dos 10 módulos. CNAB400 permanece não homologado e desativado.
