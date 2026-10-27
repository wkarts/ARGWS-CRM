# PR52 — IDs dos gravadores nativos e diagnóstico do intervalo

Base15390975b5565e3c4a766637aa13a71d8af47764, aberta/em rascunho. PWAa5ba730 preservado. Não há merge/deploy, operações externas ou alteração dos arquivos protegidos.

## Correção funcional conjunta

A captura do insert_id antes de consultar ROW_COUNT já fez o widget principal criar seu evento no navegador autenticado2650e95: POST200 e exatamente uma linha da empresa principal. A inspeção conjunta encontrou o mesmo uso tardio do ID em outros gravadores nativos. Este incremento captura imediatamente os IDs de quatro inserções auxiliares (tarefas pessoais, avisos, notas e lembretes), dois cadastros de catálogo, filtro salvo, modo de pagamento e seu recibo de auditoria, e modelo de checklist:dez ocorrências. Não altera payloads, autorização, escopo, transação, revisão ou confirmação da gravação. Uma inserção ignorada continua recusada por ROW_COUNT antes de usar o ID. Não cria pagamento ou cobrança externa.

## Calendário ainda não aceito

No1539097, Focused PR40422558652/push40422550052 aprovaram PHP8.1/8.5, inclusive23 verificações SQLite adicionais do feed. PWA40422558716 e Interface40422558661 aprovaram. Stack40422558691 falhou409 no feed após passar a criação pelo widget. Sua captura segura mostrou exatamente start2026-09-27/end2026-11-08, sem campos extras: a hipótese de parâmetro extra não está comprovada e o parser não foi ampliado.

O mesmo erro nativo de intervalo é usado por validação de entrada, eventos e lembretes. O diagnóstico passa a distinguir a etapa por um marcador interno fechado, preservando a mensagem DomainException e a resposta pública genérica existentes. O logger só acrescenta um dos identificadores constantes permitidos; não imprime valores, tokens, cabeçalhos, SQL, conteúdo ou nomes arbitrários. Não suprime a falha e não muda limites de intervalo. O fixture real de fontes passa a executar também o exato intervalo de seis semanas observado, nas empresas principal/secundária, com os mesmos dados e assertions de isolamento. O navegador continua exigindo200.

BB40422558709 teve unitários8.1/8.5 e checkout aprovados, mas MySQL/MariaDB foram cancelados sem solicitação deste executor. Não aprovados integralmente. O novo preparo histórico BB ainda precisa completar o CI.

## Verificação e limites

Local: handlers compartilhados e DateEnv real5.6.0/callback passaram; sintaxe JS/bash, dois testes Python e reconciliação de inventários passaram. PHP/lint, SQL e navegador deste incremento não executados localmente. CI existente é obrigatório. Quinze diferenças acumuladas de fontes conferidas contra o artefato completo76d/artifact12694339060 (hash registrado nos checkpoints anteriores); sem alegar checkout físico completo. Geradores oficiais precisam confirmar novamente.15082 fronteiras/4608 fontes/646 itens, prontidão bloqueada.

Ainda48 controllers paralelos; não houve nova remoção de interface neste incremento. CNAB400 não é operacional completo. Demais fluxos nativos/integrações e instalação real/standalone PWA seguem pendentes. O documento de fechamento foi corrigido para distinguir contagens atuais e runtime histórico. Nenhum resultado de serviço/CI é apresentado como aceite integral.
