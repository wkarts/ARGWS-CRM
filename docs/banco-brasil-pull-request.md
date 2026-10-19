# PR45 — Banco do Brasil e Empresas no núcleo

A PR46 foi incorporada à branch `feat/banco-brasil-billing-cnab240`; PR45 continua
com destino main. Núcleo4.1.6/416, BB1.0.8/101–108, Asaas1.4.8/148. Migrations e
histórico publicados preservados; nenhum merge, deploy, tag ou release autorizado.

Foram ligados ao núcleo o responsável/master e wizard retomável, clientes privados
e opt-in apenas da identidade, contatos/portal/reset por empresa e transporte, lista/PDF privados com permissão
nativa explícita e revogação,
faturas nomeadas/numeração/snapshot/PDF e recebimentos manuais transacionais.
O BB possui originação240 ligada à fatura, reserva compartilhada e concorrente de
Nosso Número, geração/retry/download auditados e declaração manual de envio.
Retornos240 têm vínculo exato, revisão e recebimento assistido restrito pelo writer
nativo. Download não transmite; upload não autentica o banco; revisão de estorno
não executa estorno financeiro.

A API108 possui configuração nativa com credenciais criptografadas por perfil,
OAuth, registro com validação do boleto, consulta com confirmação da conta e
pedido de baixa distinto da confirmação final. Outbox durável preserva a origem
antiga após troca de conta, evita POST duplicado e não repete resultado desconhecido.
Perfil restrito17/simples/BRL sem ajustes/Pix; respostas API não criam pagamentos.
A fonte primária OpenAPI3.2.2 e o manual de boleto têm revisão/hash no handoff.
A indisponibilidade documental inicial da API foi resolvida.

Consultar a [matriz objetiva](companies/pr45-status.md), o [handoff](banco-brasil-handoff.md),
[wizard](companies/onboarding.md), [CNAB240](banco-brasil-cnab240.md) e
[CNAB400](banco-brasil-cnab400.md). Asaas, notificações, permissões e barreiras dos
consumidores incompletos permanecem. Rollout multiempresa universal, ajustes e
estornos financeiros, alterações/webhook mTLS e aplicação financeira da API ainda
são pendências técnicas. Remessa400 permanece bloqueada pelo contrato BRL/juros;
o candidato não é arquivo operacional homologado.

## Validação e limites

O baseline412 `7aa56e60` passou nos16 workflows, incluindo instalação/upgrade,
MySQL8/PHP8.1/8.5 e HTTP/Chromium com o CRM real e fluxo BB assistido completo.
No413 `365a0bbb`, Empresas/MySQL8.1/8.5 passou, incluindo criptografia nativa e
API/concorrência com142 verificações acumuladas e provedor simulado. O novo job
MariaDB confirmou o codec/driver e detectou diferenças no cache de locks e metadata
SQL NULL. Os asserts foram preservados;414–415 corrigem núcleo/templates/fila e
adicionam testes de rejeição a DEFAULT0. A permissão do novo portal não abre
consumidores legados para contatos privados. Conferir resultados do SHA final.

Fixtures sintéticas, MySQL/MariaDB descartáveis e HTTP/browser não são homologação.
Nenhum canal foi homologado externamente e não houve uso de credenciais de cliente,
OAuth bancário ou transação real. Não apagar tabelas/migrations como rollback.
