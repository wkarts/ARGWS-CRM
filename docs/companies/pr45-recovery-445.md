# Recuperação445 — reparo do CI444

Partida confirmada: `004c334c451707e19abcd68bf66a793f547116fb`, core4.4.4/444, BB1.2.2/122, Asaas1.5.3/153. Os16 workflows desseSHA terminaram14sucessos/2falhas; Stacks aprovado. Nenhum resultado436 foi reutilizado como aprovação atual.

A fixture SalesV4 apontava para `asaas_test`, embora Empresas prepare `issuer_tests`. O DSN selecionava corretamente o banco do job. A correção cria e remove seu próprio schema reservado, nunca adota banco existente, mantém DATABASE(), instala a FK real cross-schema e conserva todos os guards de metadata/ações/contagem. O erro1049 foi reproduzido com o papel root sintético usado peloCI; o teste corrigido passa semSUPER e sem novas permissões no workflow.

O reparo SQLite já existia localmente e foi recuperado byte a byte (SHA256d3f7c34870711f59093556a0501a228c657ecdce96e45aa9001ec25c5869fb49). A fixture negativa usa SQLite real só durante sua preparação e restaura exatamente os três envs herdados. Os asserts de driver/nenhumaOAuth/nenhuma mutação/nenhum schema fictício permanecem. A cobertura SQL positiva passa separadamente.

445 é exclusivamente testes/evidências e metadados oficiais da release: gerador nativo4.4.5/445, NOOP semSQL. Nenhum módulo funcionalmente alterado: BB122 e Asaas153 permanecem. Nenhuma migration histórica foi editada. Um comando novo do runner445 foi acrescentado ao workflow Empresas, preservando os379 comandos anteriores e sua ordem. Não há reparo repetido de Poppler/Asaas/HMAC/CI436.

O snapshot local anterior de tarefas/calendário chamado candidate445 foi preservado em checkpoint completo com19743arquivos. Seus recursos NÃO fazem parte deste incremento445 deCI; serão reconciliados em446, incluindo o500do cadastro principal. Parte dos fontes financeiros experimentais NoncashV2 e do overlaytarifa foi removida pela manutenção do workspace: logs/contratos/scripts parciais sobreviventes foram preservados; não alegamos recuperação fiel nem aprovação do código ausente. Implementação nova deve manter regressivos e rastreabilidade.

Sem merge, deploy, force-push, tag, release manual ou operação bancária real. A descrição da PR identifica o SHA publicado e o estado atual do seu CI.
