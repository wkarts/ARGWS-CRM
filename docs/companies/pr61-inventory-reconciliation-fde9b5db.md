# PR61: reconciliação de inventário sobre fde9b5db

Base exata: `fde9b5db15a598a66519482bf86aa93e1fccc5ce`, incremento concorrente
de Leads, Contratos e Despesas. Nenhum fonte dessa frente foi alterado nesta
reconciliação. Core 4.8.9/489 e módulo PIGE 1.0.7/107 preservados.

## Evidência e limite da revisão

O CI Barreiras de escopo de faturas, run 42138685649 / job 127633200664,
e a reprodução local de `python3 scripts/audit-issuer-isolation.py` registraram
28 erros: 25 fingerprints desatualizados e três fronteiras não inventariadas.
O delta remoto já corrigia o inventário UI do PIGE (8 botões, 26 inputs e ação
`disable`) e o inventário operacional. A correção local anterior desses dois
arquivos não foi publicada sobre a versão concorrente.

Revisados o delta de arquivos, a classificação lexical e a correspondência dos
28 registros com o conteúdo atual. As três novas fronteiras são
`application/services/NativeCompanyExpenseIndex.php`,
`application/services/NativeCompanyExpenseIndexHttp.php` e
`application/views/admin/expenses/native_preview.php`.
Elas pertencem à frente de Despesas, não à configuração PIGE.

Gerador oficial: `python3 scripts/audit-issuer-isolation.py --write`.
Verificação adicional comparou cada SHA-256 aos bytes do arquivo e exigiu
exatamente 28 registros modificados, nenhuma remoção, todos com
`state=unreviewed` e evidência vazia. Não foram alterados assert, exclusões,
descoberta ou critérios de aprovação. Resultado: 687 arquivos, 658 unreviewed,
29 partial, zero erros, `operational_rollout=blocked`.

Isso é reconciliação de inventário, **não auditoria de segurança concluída**.
Não certifica os novos writers, a migration, a paridade funcional nem o
isolamento completo do incremento concorrente. A revisão funcional e as
provas de MySQL/HTTP/concorrência dessa frente permanecem de responsabilidade
da implementação e da matriz CI correspondente.

## Validação local do HEAD reconciliado

- Inventário UI: 16 views e 6 controllers, aprovado.
- Inventário operacional: 38 módulos e 4.783 fontes, aprovado sem regeneração adicional.
- Inventário amplo: 15.268 arquivos, aprovado, todos não resolvidos e operational_ready=false.
- Censo de ações nativas: saída gerada idêntica ao arquivo versionado.
- Contratos CI: 93 testes Python aprovados.
- Contratos Node da raiz: 252 aprovados.
- Testes Python de inventários/anexos/consumidores: 16 aprovados.
- Manifestos focados SQLite/Node/MySQL/browser: `--check` aprovado; não significa executar todas as suites funcionais.
- Browser PIGE: desktop/mobile aprovado com dados e bootstrap sintéticos, sem POST HTTP ou provedor.
- Gates de versão core e migration de módulos: aprovados.

A geração separada do consumer register já falhava antes deste incremento com
evidência órfã `application/controllers/admin/Settings.php::isolated_index`.
Essa evidência não foi apagada nem transferida para simular aprovação.
Autoconfiguração bilateral PIGE e reorganização geral dos menus continuam pendentes.
CI remoto deve ser acompanhado no novo SHA; cancelamentos não são aprovação.
