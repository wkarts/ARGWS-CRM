# PR 52 — checkpoint de recuperação integrado (rascunho, sem aceite funcional)

**Base Git remota conferida:** `cfd3e334dbc7d28f33aa4408b79b5de010c9bdb7` na branch `fix/native-company-experience`, PR 52, aberta e em rascunho. A captura provém do ZIP `ARGWS-CRM-PR52-recuperacao-local.zip`, cujo SHA-256 é `d859e3900d3e05787d945222bcd16e16bd521b836c3b5b7a07d4de684778d663`. O ZIP e todos os manifestos são backups imutáveis e não integram esta PR.

## Cobertura e proveniência

- Restaurado **exatamente um snapshot**, sem sobreposição do patch principal sobre o snapshot, e sem reaplicar checkpoints históricos equivalentes às árvores publicadas.
- **173 arquivos rastreados modificados**, **51 novos não rastreados relevantes**, **nenhuma remoção rastreada**. Seis assets ignorados de terceiros permanecem preservados no backup e aguardam validação do pipeline de build; o `application/config/my_hooks.php` ignorado é sintético e não foi publicado.
- Versão recuperada: `4.8.1`, nova migration `481_version_481.php`, sem alterar migrations históricas. Preserve dados e histórico.
- Todas as rotas, telas e menus oficiais devem ser compartilhados entre principal, A, B e usuário restrito, com ACL, CSRF, revisão/CAS, idempotência, rollback, PWA, Asaas, Efí e BB preservados. **A equivalência integral NÃO foi comprovada neste checkpoint.**

## Verificações pré-publicação

- SHA-256 e CRC do ZIP; 12.704 hashes dos fontes extraídos e confrontados com manifesto original antes das alterações desta continuação.
- PHP lint em 197 arquivos PHP recuperados: sem erros; `node --check` do smoke de projetos: sem erros.
- 14 casos Node focados de apresentação, timer e projetos aprovados.
- Busca por padrões fortes de chaves e tokens em 224 arquivos modificados/novos: nenhuma ocorrência.
- **Não foi possível executar SQLite/MySQL neste container**, porque o PHP CLI disponível não possui drivers PDO SQLite/MySQL. Não se atribui aprovação dos recibos históricos ao código atual.

## Diagnóstico das três falhas registradas

1. `native_timer_stale` (MySQL): incluída verificação de que a etapa anterior possui uma única origem de timer ativa com o ID original e revisão 1. A causa do estado obsoleto exige reprodução real; não se substituiu revisão esperada por revisão corrente.
2. `company_mail_dispatch_review_required` (SQLite): fixture corrigida para exigir a recusa do despacho ainda `pending`, confirmar fence inalterado, realizar a transição `pending → sending` que o worker faz e só então reivindicar o permit de despacho. Guard de produção mantido. Teste de banco ainda pendente.
3. DataTables/Ajax na aba de tarefas do projeto: teste agora coleta rota/método/status HTTP das falhas, mantendo a asserção estrita de ausência de alertas e erros. A causa funcional aguarda reprodução com navegador e banco.

## Pendências locais e externas

- Reexecutar as duas verificações históricas cujo hash de teste difere dos recibos: `issuers_browser_smoke.cjs`, `native_task_index_projection.php`.
- Concluir 114 ações necessárias e 20 callbacks classificados na matriz dos dez módulos; 5 ações classificadas não aplicáveis devem continuar negadas. Admissão de rota não implica equivalência.
- Validar faturas, projetos, tarefas, contratos, PDFs, pagamentos e fila financeira; principal/A/B/restrito; instalação limpa, upgrade com dados e workflows do SHA final.
- Preservar e testar política de cache, sem purge remoto; limpeza real e ativação na main não comprovadas.
- **CNAB400:** homologação externa separada e não bloqueante; manter código e operação desativada (`operational=false`), sem declarar homologação.
- Não fazer merge, deploy, release nem force-push. A PR permanece draft até aprovação explícita.

Evidências integrais, tentativas anteriores e relatórios estão no ZIP original, que não deve ser adicionado ao GitHub. Novas correções precisarão de testes focados e de outro commit; este checkpoint não é aceite final.
