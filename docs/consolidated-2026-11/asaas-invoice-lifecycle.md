# Ciclo nativo Asaas — cancelamento163

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

Isso implementa o cancelamento, **não todo ASA04**. Exclusão definitiva da fatura,
reativação/recriação, edição remota e configuração de recebimento manual integral
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
