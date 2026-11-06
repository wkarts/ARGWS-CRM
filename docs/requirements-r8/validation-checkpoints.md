# Checkpoints de validação da PR 61

Este registro vincula resultados a versões efetivamente executadas. Um resultado
de um checkpoint não é aprovação automática de código acrescentado depois.

## Primeiro conjunto publicado

- Base: `main` 4.8.8, `3cd2ac577a969840c94b019f602cb69d710c0e78`.
- Head executado: `bf0e2ce01b20ff4b635e96036af8bb9c762a69b0`.
- Gate focado: [run 42125848271](https://github.com/wkarts/ARGWS-CRM/actions/runs/42125848271).
- Resultado: preflight, contratos Node/build, Chromium e fases focadas PHP 8.1 e
  PHP 8.5 concluídos com sucesso. As fases PHP incluem MySQL real e serviços/SQL
  em SQLite descartável, conforme o manifesto daquele SHA.
- O gate HTTP completo [run 42125848624](https://github.com/wkarts/ARGWS-CRM/actions/runs/42125848624)
  encontrou erro Ajax no cenário comum `issuers_browser_smoke.cjs`: resposta 409
  de `tasks/table` durante a navegação/troca de empresa. As demais áreas desses
  shards não ficaram homologadas porque o cenário comum falhou antes delas.
- O monitor [run 42125848424](https://github.com/wkarts/ARGWS-CRM/actions/runs/42125848424)
  marcou a rodada como falha e conservou os shards diagnósticos independentes.

## Segundo conjunto publicado

Inclui formulários completos compatíveis de Propostas/Orçamentos, campos
opcionais de emissão de Notas de crédito, índice/modal/Kanban de Leads, transporte
de campos personalizados, relação exata de créditos ao projeto e cancelamento
de XHRs antigos antes da seleção. Build, testes de serviços/controladores/DOM e
contratos locais são registrados nos relatórios por área.

- Implementação: `953ed597b0e13418e42529a44dee60b40665ad5d`.
- Head executado após ajuste do driver de navegação:
  `c6ec9da024cf5a77846eff56ad0136b3b1846c5e`.
- [Interface nativa multiempresa, run 42130717185](https://github.com/wkarts/ARGWS-CRM/actions/runs/42130717185): sucesso, incluindo os cenários de apresentação desktop/mobile e modal desse workflow.
- [Gate focado, run 42130717205](https://github.com/wkarts/ARGWS-CRM/actions/runs/42130717205): preflight, Node e Chromium em PHP 8.1/8.5 passaram. A fase MySQL falhou no callback de cliente/projeto de `native_credit_metadata_form.php`; a fixture preparava somente `company_projects`, embora o leitor canônico valide também os demais schemas de Projetos/Tarefas. A composição seguinte instala essas dependências reais na fixture e separa a asserção do retorno; não relaxa o validator de produção.
- [HTTP completo, run 42130717261](https://github.com/wkarts/ARGWS-CRM/actions/runs/42130717261), jobs `127610662680` e `127610662706`: `issuers_browser_smoke.cjs`, onboarding, certificados, portal e login/revogação A/B passaram. O erro Ajax 409 anterior não voltou nesses cenários. Isso comprova o cenário executado, sem atribuir uma causa retroativamente à instalação do usuário.
- Esses jobs pararam depois no probe de Settings: o teste ainda procurava o formulário de crédito na lista e exigia `input` para a descrição. A adaptação agora navega pela ação original “Nova nota de crédito” e verifica a descrição multiline, o preço e a alíquota em campos distintos. A nova execução de CI permanece necessária.
- [Registro de empresas, run 42130717253](https://github.com/wkarts/ARGWS-CRM/actions/runs/42130717253): os shards 2/5 de PHP 8.1 pararam em uma expectativa antiga herdada de `native_registry_identity.php`. A base 4.8.8 já permite a um administrador ativo criar uma empresa sem obter acesso às existentes. O teste foi alinhado a esse contrato, conservando negativos de acesso e edição de outra empresa; a regra de produção não foi modificada.

As correções posteriores e os campos complementares de Leads/Contratos,
índice/categorias de Despesas e tarefas relacionadas precisam ser validados no
SHA em que forem publicados. Sucesso de uma etapa anterior não aceita uma
composição posterior por inferência.

Os cenários Chromium locais não foram executados: o ambiente bloqueou sockets e
a revisão automática recusou a escalada. O CI autorizado é o ambiente usado para
essas provas. Não há declaração de equivalência visual integral lado a lado,
PWA instalado, provedores externos reais, merge ou deploy.

## Composição de operações — 6 de novembro de 2026

Base desta composição: `c6ec9da024cf5a77846eff56ad0136b3b1846c5e`.
O código foi copiado para checkout isolado antes das extensões seguintes de
Leads, Crédito e pipeline, preservando exatamente os arquivos submetidos aos
comandos abaixo. Nenhum resultado do SHA anterior aprova este novo delta.

- Build de produção completo aprovado: `npm run build`.
- 54 comandos da fase Node aprovados, incluindo os novos handlers de Contratos
  e Despesas; o teste do índice cruza IDs com o encoder PHP real.
- 15 suítes PHP selecionadas aprovadas em SQLite, mais o novo teste de render
  de Despesas (16 verificações adicionais). Cobrem gravação tardia/rollback de
  Contratos, formulário, índice/lote/categorias de Despesas, Tarefas de Despesas,
  PDF real com TCPDF/Poppler, rótulos CF, crédito, metadados de Leads, identidade
  administrativa e admissão de rotas. Contagens cumulativas dos pré-requisitos
  não são somadas como casos novos.
- 13 contratos do runner CI aprovados; verificação de dependências JS, sintaxe
  dos roteiros alterados e `git diff --check` aprovados.
- Migration 489 ampliada com campos privados de Leads/Contratos, fim opcional
  em Contratos e descrição opcional de categoria. O teste MySQL executa `up()`
  duas vezes e compara dados anteriores; sua execução remota ainda é necessária.
- O manifesto contém 32 comandos MySQL e os roteiros reais de navegador.
  Teste escrito ou sintaxe aprovada não significa navegador/MySQL executado.

Correções de fixtures preservam os contratos atuais: projetos do callback de
Crédito instalam os três schemas reais; o probe de configurações abre a ação
original de nova nota; a prova de cadastro de emitentes segue a autorização de
administradores ativos já existente na 4.8.8. Nenhuma dessas correções amplia
permissões de produção.

As capturas lado a lado 4.0.2/destino, homologação completa do PWA e reprodução
da sessão afetada de Pagamentos continuam sem aprovação integral. A PR segue
em rascunho enquanto adaptações compatíveis e resultados da CI são concluídos.

A publicação desta composição foi reconciliada sobre o novo head remoto
`b47bbe1f4edd221d21d4da990a6ad482a8f923cf`, preservando os três commits
paralelos de configuração/OAuth/navegação. O único conflito foi o inventário
gerado, recalculado da união. As cinco suítes PHP de apresentação/configuração
afetadas e a suíte Node de apresentação passaram novamente; os testes de
inventário também passaram. Nenhum arquivo de produção do bundle JS foi
modificado por esses commits remotos.
