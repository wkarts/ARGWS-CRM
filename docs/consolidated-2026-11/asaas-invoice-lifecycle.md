# Ciclo nativo Asaas — cancelamento e recriação

O comando nativo Cancelar fatura agora coordena a exclusão de uma cobrança Asaas
não paga e o cancelamento local. A origem precisa ser única, canônica, da empresa
ativa e sem histórico financeiro incompatível. Mantém ACLs existentes de exclusão
remota, CSRF/contexto do controller, revisão da fatura e mutex financeiro.

Antes de qualquer DELETE, o coordenador verifica revisão, empresa, permissões,
module163 e concorrência com outros provedores. A remoção existente conserva uma
intenção durável e consulta carteira/cliente/cobrança; somente uma prova canônica
recente de exclusão autoriza a transação local. Provas com data futura são recusadas.
A autoridade é uma classe final específica: não há callback genérico que permita
ignorar a proteção das origens no escritor de faturas.

Cancelar preserva origem, snapshots, linhas e histórico. O journal financeiro e um
recibo de auditoria da coordenação são escritos na mesma transação do status5.
Retry do mesmo comando verifica esse recibo; não envia outro DELETE. A versão163
possui migration NOOP; nenhum backfill, segredo, cobrança ou registro de produção.

## Erros e recuperação

- Timeout/HTTP incerto: fatura continua ativa; a resposta nativa indica cancelamento
  pendente. Repetir a ação consulta o estado, sem repetir DELETE.
- Intenção nunca enviada: somente o dispatcher existente pode reivindicá-la.
- Outro processo enviando: permanece pendente; não sobrescreve sua observação.
- Exclusão remota confirmada seguida de falha local: a exclusão permanece registrada,
  a transação local reverte e uma nova ação pode concluir o cancelamento após GET.
- Cobrança restaurada no provedor: confirmação antiga não autoriza cancelamento;
  exige revisão, sem nova exclusão automática.
- Revogação, empresa incorreta, formulário obsoleto, origem múltipla ou fundos:
  bloqueados. Não há compensação por criação automática de cobrança.

O checkpoint163 implementou o cancelamento; a seção165 abaixo acrescenta a recriação PIX/BOLETO, **não todo ASA04**. Exclusão definitiva da fatura,
cartão na recriação, edição remota e configuração de recebimento manual integral
versus parcial continuam pendentes. Não ampliar a permissão de um operador para
contornar ACL nem apagar a origem para liberar reativação.

## Contratos oficiais consultados em6/11/2026

[Excluir cobrança](https://docs.asaas.com/reference/excluir-cobranca) remove a
cobrança; não representa estorno nem encerra sua assinatura/parcelamento.
[Restaurar](https://docs.asaas.com/reference/restaurar-cobranca-removida) reutiliza
o ID e exige consulta antes de retry. Isso é diferente da recriação pedida pelo
contrato consolidado; não foi substituído silenciosamente.
[Receber em dinheiro](https://docs.asaas.com/reference/confirmar-recebimento-em-dinheiro)
registra recebimento externo, sem crédito real no saldo Asaas. Esse endpoint não
foi conectado à baixa manual nesta etapa, nem usado para inferir quitação parcial.

## Evidências

MySQL8.4/PHP8.3:22 verificações adicionais sobre167 cumulativas anteriores, com
provedor simulado. Cobrem cancelamento/retry, preservação, isolamento, timeout e
recuperação por GET, rollback local/retomada, revisão, revogação e restauração remota.
Dois processos adicionais aguardam o mutex real e produzem um DELETE e um recibo:
seis verificações. Incluído no gate MySQL focused.

Controller nativo com SQLite e fronteira de requisição sintética:145 cumulativas
passaram, preservando fatura manual, proibição de GET, acesso cruzado e rascunho.
Runner MySQL real:243 assertions, incluindo falha de tracking163, retry, replay e
preservação das tabelas históricas. A primeira tentativa apontou um schema de teste
fora da lista permitida e foi recusada; repetida no schema descartável autorizado
sem alterar a proteção. Teste de header/runner com double de metadados:13 checks.

HTTP autenticado integral e homologação Asaas ainda pendentes. Sem chamada externa,
transação real, merge ou deploy. Em rollback, preservar ledger/recibos e marcadores;
faturas já canceladas não devem ser reativadas por código antigo ou reenvio remoto.

## Preparação interna da recriação164 — ainda sem envio

A migration164 acrescenta `asaas_company_recreations`, vinculando a intenção à
origem anterior, empresa, fatura, ator, revisão cancelada e prova canônica de
exclusão. Reservar exige cancelamento nativo comprovado, prova recente de cobrança
excluída/não paga, saldo, ausência de eventos financeiros adversos e permissões
atuais. Retry idêntico devolve a intenção; outra chave para a mesma origem falha.
A intenção preserva origem, remote_id, eventos, snapshots e status cancelado.
Não armazena credenciais, dados de cartão, nem envia HTTP.

Esta etapa é infraestrutura interna, sem botão ou ativação do fluxo. **Não recria
cobrança nem reabre fatura.** A restrição histórica UNIQUE(invoice_id) continua
intacta enquanto não estiver concluída a mudança coordenada dos consumidores.
Ela não pode ser removida isoladamente: `PaymentOriginStore::byInvoice`,
`CompanyService::existingIssuance`, seleção de eventos/jobs, publicação de links,
conciliação e cancelamento precisam distinguir origem atual de anteriores.
O próximo envio precisa reservar um sucessor imutável, preservar uma única origem
ativa e bloquear recebimento automático de eventos tardios da origem substituída.
Timeout continuará exigindo consulta, sem repetir POST; reabertura só após prova
canônica da nova cobrança e transação local confirmada.

Migration164 é aditiva e sem backfill. Reversão de código não pode apagar intenções,
origens ou provas, nem reduzir marcadores. O fluxo integral ASA04 segue pendente.

## Recriação165 — sucessor, envio único e reabertura nativa

A etapa165 substitui a limitação funcional descrita no checkpoint164 acima. A ação
nativa de reabrir uma fatura de empresa com cobrança Asaas cancelada usa agora a
intenção durável, reserva um sucessor para PIX/BOLETO e mantém a fatura cancelada
até consultar a nova cobrança canônica como não paga, não excluída e sem eventos
adversos. Cartão e origens de ponte com política manual continuam bloqueados para
revisão específica; não há captura de cartão ou alteração do SDK.

`active_slot` é nullable, com default1 e UNIQUE(invoice_id,active_slot). A migration
adiciona essa unicidade antes de remover a unicidade antiga por invoice_id; não
altera IDs, dados documentais, remote_id, provas, eventos ou recebimentos. Retirar
uma origem da posição ativa (`NULL`), inserir o sucessor com snapshot/perfil e
vincular a intenção ocorre numa única transação. Origens substituídas continuam
consultáveis como histórico, mas são recusadas por operações financeiras atuais;
seleção nativa, callbacks e links usam a origem ativa.

A transição prepared→sending é persistida sob o lock financeiro antes do POST.
Concorrentes não reenviam. Timeout/HTTP não confirmado permanece incerto; só GET
com ID canônico pode recuperar. `externalReference` não é tratado como garantia de
idempotência. Se a resposta se perdeu antes de guardar o ID, a identificação remota
exige revisão: a guia operacional Asaas existente aceita o ID remoto na consulta
canônica do sucessor. Depois dessa consulta, repetir a reabertura nativa apenas
consulta o ID já vinculado e conclui o diário; não repete POST. O formulário de
reabertura não recebe um ID arbitrário nem declara sucesso antes da confirmação.
Interrupção depois do claim e antes do envio também exige revisão, sem reset cego.
Uma preparação sem envio cuja prova ultrapassou cinco minutos bloqueia para revisão.

A reabertura valida uma autoridade tipada também no diário financeiro. Status,
auditoria, evento contábil e estado reopened da intenção são atômicos. Falha no
diário mantém a fatura cancelada e preserva o resultado remoto para recuperação.
Novo ciclo de cancelar/recriar acrescenta outra identidade, sem reutilizar recibos
antigos. Replay revalida ACL e conta/ambiente; eventos tardios da origem antiga ou
pagamento/rejeição da nova bloqueiam reabertura automática.

Validação: MySQL8.4/PHP8.3, 64 checks adicionais de gerações/fluxo sobre suites
anteriores; inclui rollback de metadata e diário, resposta perdida após aceite
simulado, recuperação sem segundo POST, dois processos reais concorrentes, duas
recriações sequenciais e revogação. Runner nativo de módulo: 277 verificações,
incluindo falhas de DDL/tracking e replay165. Provedor simulado; não homologação
Asaas. Fluxo HTTP autenticado integral da nova reabertura ainda depende do CI.

Rollout requer pausa de writers durante DDL e runner nativo até165. DDL MySQL pode
adquirir metadata locks; o ensaio não mede o volume de produção. Após gravar mais de
uma origem por fatura, código anterior à165 não é compatível: rollback deve
preservar dados/marcadores e usar correção progressiva ou restauração consistente
anterior a qualquer novo efeito remoto, com conciliação. Nunca apagar gerações nem
recriar a constraint antiga sobre dados novos para simular reversão.

## Recebimento manual — contrato verificado, implementação ainda pendente

Reconsulta oficial em6/11/2026: POST payments/{id}/receiveInCash recebe paymentDate,
value e notifyCustomer. É baixa de pagamento externo, sem crédito de saldo Asaas;
a cobrança passa a billingType RECEIVED_IN_CASH e gera PAYMENT_RECEIVED.
POST payments/{id}/undoReceivedInCash desfaz somente essa confirmação manual e
gera PAYMENT_RECEIVED_IN_CASH_UNDONE; não é devolução de Pix/boleto/cartão.

A referência somente leitura1.4.8 acumula o valor manual anterior ao quitar,
mantém cobrança aberta no parcial e distingue remover de confirmar dinheiro.
Essas regras não foram conectadas ao writer multiempresa atual: ele bloqueia
origens de provider em recebimento manual, e o normalizador Asaas atual exige
billing_type compatível com o snapshot. Não basta permitir receiveInCash no
transporte: isso poderia fazer o webhook virar uma segunda baixa ou aceitar uma
fonte que mudou de natureza. É necessária autoridade específica, política explícita
por empresa/ambiente, ledger da tentativa e recuperação canônica, composição com
recebimentos parciais e reversão/compensação sem tratar resultado incerto como
falha definitiva. Nenhuma dessas políticas foi ativada por inferência nesta etapa.

## Pós-reembolso sobre sucessores — módulo167

Cancelamento local e novo recebimento manual independente após reembolso integral
agora aceitam uma cadeia de recriações concluídas. Revisão e execução usam a mesma
validação sob transação nativa. Não basta filtrar active_slot: cada origem histórica
precisa ser um predecessor da ativa, com vínculo reopened da mesma empresa/fatura,
conta/ambiente/origem imutáveis, request_key do sucessor e prova canônica íntegra de
exclusão sem pagamento. Ciclos, órfãos, mudança de empresa, prova alterada, eventos
posteriores/adversos, recibo, intenção de reembolso ou job pendente de predecessor
bloqueiam a operação. Múltiplas gerações exigem tracking167; caso legado com uma
origem mantém o contrato existente.

As exigências anteriores de reembolso integral declarado, evidência independente,
revisão atual, grant/ACL, saldo, idempotência e integridade do diário continuam.
Falha do recibo de auditoria reverte status/pagamento. O recebimento manual aqui é
NOVO dinheiro independente depois da devolução integral, e não confirmação em
dinheiro no Asaas ou implementação do recebimento manual geral/parcial.

Migration167 NOOP: nenhuma DDL, adoção, apagamento, alteração de histórico ou ação
remota. Downgrade para166 volta a recusar essa composição; não reverter pagamentos
ou marcadores automaticamente. Permanece necessária a validação HTTP autenticada
e a homologação da instalação. Os testes usam somente transporte simulado.
