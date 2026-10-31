# PR 52 — correção 4.8.4

Base reconciliada: `6bbb7b4a1e4c3b01c204deaadaeabaf49791881f`. A branch remota é a fonte canônica; não foi sobreposta pelo ZIP divergente.

## Correções
- Closure de consulta das seções de Clientes mantém o contexto da instância.
- Aba Pagamentos seleciona a view paginada correspondente aos dados preparados.
- Relações JSON comparam IDs exatos, respeitando a empresa e a linhagem `native_project`; não usam prefixos textuais. Assinaturas vinculadas a projetos usam essa mesma linhagem.
- Salvar/remover identidade nas configurações atualiza a projeção PWA. Imagens passam a ter nomes derivados do hash; somente o manifesto é trocado para publicar o conjunto, preservando a projeção anterior se uma gravação intermediária falhar.
- Fixture PWA inclui o helper requerido, fixture de empresas define o bootstrap de caminhos e o cenário de dependências reabre o quadro após a navegação para a tarefa.
- Censo/matriz e inventários regenerados contra o código, sem declarar paridade/rollout aprovados. CNAB400 não foi desativado nem alterado.

## Evidências antes de publicar
PHP 8.4 e SQLite reais: regressão de seções/IDs exatos passou; controller Issuers passou 45 verificações. PWA HTTP: 3 testes passaram. Node paridade/PWA: 16 testes passaram. Sintaxe dos controllers/serviços alterados verificada.
A suíte SQLite focada completa está em execução; não se declara sucesso antecipado. MySQL, navegador e CI do commit final devem concluir antes do merge. Esta entrega permanece em rascunho, sem merge/deploy/release de produção.

## Compatibilidade e atualização
Núcleo 4.8.4 com migration 484 aditiva NOOP; migrations anteriores preservadas. Nenhuma tabela, dado, driver bancário, porta, bind ou credencial foi removido/alterado. Faça backup de banco e arquivos antes da futura atualização. Não use downgrade de banco como rollback; restaure o backup compatível se necessário.

## Correção após CI HTTP/MySQL/Chromium
O cenário real de Projetos revelou que o quadro chamava o writer legado para tarefas criadas no formulário nativo. O writer corretamente recusava a mudança, deixando `todo`. O quadro agora seleciona o writer nativo quando há metadados nativos, preserva campos, revisão, dependências, checklist e bloqueios financeiros. O teste verifica a conclusão do pré-requisito antes do dependente; nenhuma asserção de negócio foi removida. Regressão SQLite dedicada cobre conclusão, metadados, dependência aberta, reabertura inválida, revisão obsoleta e outra empresa.

Os seis bundles divergentes foram reconstruídos com `npm ci` e `npm run build`. O gate de assets continua exigindo igualdade com o build do repositório. A suíte PWA do SHA d9bfdc passou em navegador e PHP 8.1/8.5. O restante do CI deve ser reexecutado no novo SHA; não se declara aprovação global.
