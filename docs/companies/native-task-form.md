# Formulário nativo de tarefas — composição por empresa

## Marco no formulário original e AJAX do projeto

O campo `milestone` usa a tabela existente `company_task_milestones`, na mesma transação da tarefa, sem novo armazenamento. A revisão opaca inclui empresa/projeto, marcos, detalhes declarados e vínculos existentes. Criar, manter, trocar ou limpar a seleção usa somente o projeto autorizado; alterações de vínculo preservam a autoridade `projects.edit` já exigida pelo serviço de marcos. Detalhes ausentes continuam ausentes, sem data inicial inventada. Detalhes existentes têm sua revisão avançada; hashes de publicação antigos não são renovados implicitamente.

`Projects::get_rel_project_data` mantém a rota e as opções HTML originais, com rótulos escapados, membros reais e prazo formatado pela empresa. O formulário captura a nova revisão ao trocar projeto e a remove ao sair da relação. Metadados de faturamento historicamente não declarados permanecem `null`, sem converter isso em modalidade fixa. Testes executam o controller original e o escritor real em SQLite; não representam navegação autenticada nem aceitação MySQL/browser.

## Preferência de workflow original

`CompanyTaskPreferences::fromNativeStatus()` traduz exclusivamente os valores do
formulário original (`auto`, 1–5), não IDs de transporte do catálogo. O documento
persistido mantém as strings canônicas antigas e aceita os cinco estados sem
aproximação. `saveInOwnedTransaction()` permite ao grupo Settings ser salvo em
uma única transação autorizada; `save()` continua sendo o wrapper independente.

No formulário Tasks original, `auto` compara a data inicial declarada com o dia
atual, como o contrato nativo. O default concluído exige a data de conclusão no
mesmo formulário: nenhum timestamp histórico é inventado. A revisão da política
é confirmada dentro da criação e novamente depois da auditoria. Os antigos
writers sem data inicial mantêm seu contrato estrito; não fabricam uma data para
consumir `auto`. A adaptação Settings da interface pertence à composição central.
