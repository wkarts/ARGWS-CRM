# PR45 — Banco do Brasil e Empresas no núcleo

A PR46 foi incorporada à branch `feat/banco-brasil-billing-cnab240`; PR45 continua
com destino main. Núcleo4.2.3/423, BB1.1.0/101–110, Asaas1.4.8/148. Migrations e
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

O422 acrescenta notificação privada de fatura pelo portal: recibo/fila/auditoria
atômicos e revalidação de contato, recurso, revisão e origem no worker. Estados
de agendamento e aceite do transporte são distintos. SMTP final é simulado nos
testes; não modifica o estado bancário ou a cobrança. Ver matriz do HEAD422.

O423 adiciona anexos privados de fatura com visibilidade explícita no portal,
sem URL pública, quotas, hash e auditoria/owner antes dos bytes. Não altera
originação, perfis, recibos ou homologação bancária. Matriz por SHA no guia Empresas.

## Incremento111 a integrar na mesma PR45

Alteração de vencimento API com PATCH oficial, intenção durável e confirmação por
consulta de título/conta, preservando fatura/snapshot. Receptor de baixa operacional
observacional com mTLS nativo, pin por perfil, GUI local, deduplicação e revogação;
cancelamento operacional10 veta novos créditos assistidos API/CNAB, sem estorno
automático. Tabelas aditivas e executor nativo111; histórico101–110 preservado.

A evidência local de transporte inclui três handshakes TLS reais com certificados
sintéticos. O ingresso implantado/cadeia BB e homologação externa continuam
pendentes; demais tipos de alteração/perfis/encargos/parciais/estornos API,
transmissão contratada240 e operação400 permanecem pendências técnicas explícitas.
Ver [guia operacional111](banco-brasil-api-amendments-webhook.md) para endpoints,
permissões, migration, testes e rollback futuro. Publicação, CI do HEAD e resultado
SQL definitivo devem ser registrados pelo integrador; este bloco não os presume.


## Incremento113 / núcleo427 — componentes no crédito assistido240

O novo perfil `settle_components_event` registra principal efetivamente recebido
em caixa separado de desconto/abatimento e encargos agregados observados. Requer
política explícita, confirmação independente do operador, origem íntegra e o mesmo
writer/recibo canônico, sem duplicidade entre o crédito240 anterior e o novo.
A confirmação não autentica o upload nem executa uma operação bancária.

Saldo, fatura/PDF, portal, API, relatórios, diário e guards de emissão usam
`CompanyInvoiceBalance`. Exemplo100 com pago95, encargos5 e extinções10 resulta
em caixa90 e dívida extinta100; não chama100 de recebimento. O perfil exige
tarifas/IOF/outros zero, líquido igual ao pago e caixa positivo.

[Contrato e consumidores](companies/bank-components-operations.md) e
[validação427](companies/pr45-validation-427.md) distinguem execução local/CI e
homologação externa. BB113 é migration NOOP nova; núcleo427 cria o schema aditivo,
sem modificar histórico. Crédito API parcial/ajustes, reversão própria dos novos
componentes e demais fórmulas/perfis continuam pendências técnicas, separadas dos
bloqueios documentais400 e da homologação externa de todos os canais.


## Incremento114 / núcleo428 — declaração de reversão externa

O operador pode registrar reversão externa integral de um recibo240 composto427,
com comprovação independente, referência, motivo, data válida, valor pago integral
restituído e principal integralmente restabelecido. Requer capacidade própria
external_reverse, conciliação, fatura/view e payments/delete. A fonte final e o
writer canônico confirmam recibo, status, revisãoBB, auditoria e journal juntos.
Native cash e componentes originais não são atualizados nem excluídos; o saldo
projeta valor efetivo e os leitores mostram original/revertido separadamente.

[Guia operacional](banco-brasil-declared-reversal.md) e
[relatórios](companies/declared-reversal-reporting.md) descrevem o perfil.
Nenhuma transação de estorno é enviada ao BB, e declaração não autentica banco.
CNAB44 permanece evento de cheque devolvido em revisão, sem autoridade genérica.
Reversão parcial, recibos API/outros canais e tarifas/IOF/outras equações seguem
pendências técnicas. Não há homologaçãoAPI/240/400. BB114 é NOOP funcional novo;
núcleo428 cria o recibo aditivo, preservando todo o histórico.
