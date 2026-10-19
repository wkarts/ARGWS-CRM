# PR45 — Banco do Brasil e Empresas no núcleo

A PR46 foi incorporada à branch `feat/banco-brasil-billing-cnab240`; PR45 continua
com destino main. Núcleo4.2.1/421, BB1.1.0/101–110, Asaas1.4.8/148. Migrations e
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
Perfil bancário restrito17/simples/BRL sem encargos/Pix; respostas isoladas não
criam pagamentos.109 acrescenta confirmação explícita de crédito16, integral sem
ajustes, por consulta recente com prova da conta e writer canônico.6 LIQUIDADO
continua observacional. Recibo/auditoria/pagamento/status são atômicos e deduplicados.
A fonte primária OpenAPI3.2.2 e o manual de boleto têm revisão/hash no handoff.
A indisponibilidade documental inicial da API foi resolvida.

Consultar a [matriz objetiva](companies/pr45-status.md), o [handoff](banco-brasil-handoff.md),
[wizard](companies/onboarding.md), [CNAB240](banco-brasil-cnab240.md) e
[CNAB400](banco-brasil-cnab400.md). Asaas, notificações, permissões e barreiras dos
consumidores incompletos permanecem.417 entregou descontos/ajustes e múltiplos
tributos nas faturas;418 adicionou imagens próprias, snapshot/PDF e preferências.
Rollout multiempresa universal, encargos/estornos bancários, alterações/webhook mTLS
e os demais perfis financeiros da API ainda
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

A revisão110 normaliza somente a comparação de Nosso Número API20/CNAB17 nos
vetos de crédito/histórico adverso. Não reescreve origens ou recibos.344 checks
acumulados de API/crédito/concorrência e131 de CNAB/concorrência passaram localmente
em MariaDB10.11.14. O teste HTTP exige recarga após a fixture CLI, pois fragmento
de URL não recarrega o DOM. Ver PR/CI do HEAD efetivo para a validação do navegador.
