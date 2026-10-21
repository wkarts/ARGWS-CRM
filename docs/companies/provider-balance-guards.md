# Guardas financeiros dos provedores — 427

Esta extensão exige `CompanyInvoiceBalance`, os recibos imutáveis de componentes e a migration427. Nenhuma configuração, número de versão ou autoridade de instalação é criada por estes guards.

Antes de nova emissão, alteração de vencimento ou baixa remota BB, o serviço confirma a posição exata da fatura sob o lock financeiro, a moeda congelada e o catálogo vigente. O veto anterior a qualquer histórico nativo é conservado, inclusive registros com projeção atual zero. A preparação e a confirmação do marcador `sending` verificam novamente a posição para que um recebimento registrado durante OAuth impeça a chamada que alteraria a cobrança. Retries de operações já terminais retornam o recibo anterior antes de criar novo efeito. Consulta e movimentos continuam observações; não se tornam escrituração.

Asaas confirma a posição na preparação e reserva da emissão, no claim de recebimento e na origem das operações remotas/links hospedados. Os composers de pagador, link e remoção incluem todos os participantes necessários; a criação de um pagador não vira um cálculo de saldo nem concede acesso adicional. Portal mantém identidade nativa e publicação autorizada; nenhum contato é tratado como funcionário.

A presença de principal recebido positivo já impedia as novas operações nos casos normais. A nova conferência cobre também vínculos de componentes órfãos, prova/hash divergente, extinções sem caixa e inconsistência entre o status e o saldo. Nenhum componente é descartado para liberar uma operação. Sem evidência monetária válida, o serviço exige revisão e não usa a ausência de um pagamento na fatura como prova de ausência de extinção.

## Evidência local

`tests/companies/provider_balance_guards.php` acrescenta 11 verificações sobre fixtures reais PDO, incluindo prova de componente com referência nativa indevida e hashes internos coerentes. Esse caso não possui pagamento na fatura alvo: mesmo assim, emissão Asaas, link hospedado e remoção remota são bloqueados antes do transporte. O guard BB de emissão/cancelamento/alteração recusa a mesma prova inválida; um recebimento legítimo acrescentado entre a primeira e a segunda conferência veta o envio subsequente.

SQLite isolado: guardas160 cumulativos, Asaas positivo117, remoção166, pagadores/checkout149 e portal hospedado164. Os sete arquivos alterados passaram lint. O teste dos guards BB chama o método interno dentro de `IssuerTransaction`; não substitui o teste da fonte final, as duas fases HTTP reais ou os waiters InnoDB preparados nas suítes BB. Essas provas precisam entrar na matriz integrada do SHA final.

Não houve transporte bancário real, confirmação externa de crédito, teste de credencial real ou homologação de conta/convênio/carteira. `paid`, `net_observed`, principal em dinheiro e extinção sem caixa são conceitos distintos. A equação local inicial não autoriza inferir tarifas/IOF/outros encargos ou disponibilizar saldo bancário. Fixtures com master sintético não demonstram o provisionamento da instalação real; a prova de autoridade escolhida deve ser confirmada no fluxo próprio da instalação.
