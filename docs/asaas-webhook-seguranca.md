# Recebimento Asaas por integração: autenticação e conciliação

## Resultado e limites

Esta mudança corrige o roteamento HMVC, separa recebedores por conta/ambiente e mantém o endpoint público sem dados de clientes. É código para revisão/homologação. Nenhuma URL de produção recebeu POST durante os testes; nenhuma credencial real, cobrança, cadastro remoto de webhook, mensagem, merge ou deploy foi realizado.

Logs antigos de payload/resposta e detalhes de exceção do provedor foram retirados. O cache de cobrança usa uma whitelist de metadados, sem objetos de cartão/token. Dados históricos não são inspecionados nem apagados; saneamento exige procedimento específico autorizado.

A tela administrativa continua no módulo Asaas existente. O módulo geral `webhooks` foi avaliado: ele envia eventos do CRM (`scheduled_webhooks`/`call_webhook`) e não fornece uma caixa de entrada autenticada de provedores. Não é reutilizado como URL universal de Asaas, Efí ou CNAB.

## Política financeira preservada

A composição preserva a política do CRM já publicado: eventos `PAYMENT_CONFIRMED` ou `PAYMENT_RECEIVED` são elegíveis à baixa, somente após GET canônico com estado `CONFIRMED`, `RECEIVED` ou `RECEIVED_IN_CASH` e todas as verificações de origem, conta, cliente, valor, moeda e documento. A revisão de segurança não muda silenciosamente essa escolha.

`verified_status` persiste o estado exato no evento e no recibo/outbox original. Confirmação não é disponibilidade de saldo e não autoriza confirmar uma operação na ponte PIGE. Um evento posterior RECEIVED não duplica o recibo; sua observação canônica permanece distinta. A ponte mantém sua própria reconciliação explícita.

## Rota e GET no navegador

`Modules::parse_routes` já acrescenta o nome do módulo. Os destinos antigos em `modules/asaas/config/routes.php` repetiam `asaas/`, produzindo `asaas/asaas/gateways/callback/index`. Agora os destinos são relativos ao módulo. O teste usa `MX_Router`/`Modules` reais, não uma interpretação de string isolada.

- Legado: `/asaas/gateways/callback`.
- Integração explícita: `/asaas/gateways/callback/integration/{public_id}`.
- `public_id` identifica o recebedor e não autentica o evento.
- GET, HEAD, OPTIONS, PUT e DELETE retornam 405, `Allow: POST`, texto fixo e `Cache-Control: no-store`, antes de carregar a biblioteca de pagamento.
- A nova URL é composta somente com `APP_BASE_URL` HTTPS validada e `index_page`, nunca com Host/X-Forwarded-Host.
- Um 404 numa instalação não demonstra exposição de dados nem funcionamento do POST. O roteamento corrigido deve ser homologado com os arquivos e configuração efetivamente instalados.
- Módulo desativado recusa POST; falha de inicialização recebe resposta genérica sem detalhes de exceção.

## Autenticação, configuração e rotação

Cada integração local contém `issuer_id=NULL`, provider `asaas`, carteira remota verificada e ambiente explícito. Cadastro exige administrador, POST e token CSRF próprio, mesmo se o CSRF global estiver desligado. O servidor gera o token com `random_bytes`; não aceita senha/token arbitrário da requisição. O segredo é exibido apenas na resposta protegida de gerar/regenerar, em campo readonly com cópia. Só SHA-256 é guardado, sem recuperação em GET. Não há segredo na URL, payload persistido, auditoria ou logs.

O Asaas usa o header documentado `asaas-access-token`. A comparação usa `hash_equals` com o digest do token, sem inventar um HMAC externo. Ausência/configuração inválida falha fechada. Token de API não é token de webhook. Regeneração invalida o anterior imediatamente, registra ator/data e exige atualização manual no painel Asaas; não há período de tolerância nem alteração remota automática. Desativar uma integração bloqueia a entrada e o processamento das pendências dela. A primeira versão não oferece reativação nem transferência de eventos já vinculados para outra integração; revisar pendências antes de desativar. O status “cadastro remoto não verificado” só pode mudar quando existir uma consulta real, que esta etapa não executa.

Não é permitido cadastrar issuer positivo: isolamento financeiro completo continua bloqueado. Seleção de ambiente do gateway aceita somente os valores persistidos `'0'` (produção) e `'1'` (homologação); ausente, inválido ou ambíguo exige revisão em vez de cair em produção. Origens históricas continuam usando seu ambiente salvo, independentemente do seletor atual.

## Entrada durável e privacidade

POST exige JSON, no máximo 1 MiB e profundidade de desserialização limitada. Identificadores e tipos são validados. Campos novos do provedor não são persistidos indiscriminadamente: ficam apenas ID do evento, tipo e ID da cobrança. CPF/CNPJ, nome, endereço, dados bancários, cartão, tokens e headers não entram na fila.

A unicidade é `(scope_key,event_id)`, com comparação binária para não fundir IDs que diferem por maiúsculas/minúsculas. O caractere `&` de IDs documentados é preservado como dado, nunca como parte de URL. Reentrega igual é ACK após verificar a linha persistida. ID reaproveitado para outro tipo/cobrança dá conflito sem sobrescrever o evento original. O fingerprint representa a identidade normalizada do evento, não todos os campos transitórios ignorados. A gravação do evento e sua auditoria de aceite são transacionais; ACK só ocorre depois de commit confirmado. Erro de persistência nunca recebe falso ACK. A entrada não consulta a API nem baixa fatura; o CRON processa depois, com tentativas limitadas e backoff. Rejeições incrementam contador de tamanho fixo, sem armazenar corpos não autenticados.

## Conciliação financeira

Uma baixa exige cumulativamente:

1. Integração autenticada e ativa; origem durável do mesmo provider, carteira, ambiente e issuer.
2. ID remoto mapeado para a mesma fatura. Nunca localizar fatura pelo hash informado no evento para criar um vínculo.
3. Carteira conferida via GET com o cliente da origem, seguida de GET canônico da cobrança e do cliente remoto.
4. ID, externalReference, valor decimal exato, vencimento e CPF/CNPJ coincidentes com origem/fatura/cliente. CNPJ alfanumérico é preservado; letras não são removidas. Moeda da fatura deve ser BRL.
5. Evento `PAYMENT_RECEIVED` e status canônico `RECEIVED`. `PAYMENT_CONFIRMED` permanece observação separada, pois pagamento confirmado e saldo disponível não são o mesmo estado.
6. `paymentDate` canônico obrigatório, calendário válido e não futuro; forma de pagamento canônica PIX/BOLETO/CREDIT_CARD. O registro guarda esta data efetiva e o tipo, enquanto `daterecorded` indica quando o CRM processou. `clientPaymentDate`, `creditDate` e a data atual não são usados como fallback.
7. Fatura aberta/vencida, origem emitida, sem recebimentos parciais, créditos aplicados, cancelamento, rascunho ou situação ambígua. Estornos e chargebacks exigem revisão e nunca apagam recibos.

Antes de mutação, todas as tabelas participantes precisam ser InnoDB. Entrada, administração, revisão e worker recusam transação externa, autocommit desativado e commit encadeado. Conferem a profundidade do driver/PDO e usam SET TRANSACTION para detectar uma transação SQL externa sem confirmá-la; somente desfazem transações próprias. `trans_begin`, cada escrita e o commit são verificados. Há lock por integração/cobrança, locks de fatura/origem e consulta bloqueante de recibos/créditos. Quantias são comparadas como centavos decimais, sem tolerância de arredondamento. A mesma cobrança já registrada não cria outro recibo, inclusive quando chega com outro ID de evento. Um transactionid coincidente, inclusive de registro manual ou gateway distinto, não basta: exige recibo Asaas, fatura paga e outbox da mesma origem/conta/ambiente/data/tipo. Sem essa prova, revisão assistida.

## Recebimento local e automações pendentes

O gravador dedicado escreve somente `invoicepaymentrecords`, status da fatura, auditoria local e `asaas_settlement_outbox`, dentro da mesma transação que atualiza mapa e evento. Ele preserva total, impostos, descontos, numeração, itens, cliente e recorrência. Somente o caso integral sem créditos/recebimentos anteriores é automatizado.

Não chama `Payments_model::add`: esse método cria PDF, pode enviar e-mails a funcionários/Pusher e executar filtros/hooks. O patch incremental desta PR garante commit antes dos efeitos posteriores dos caminhos genéricos; ainda assim, `do_not_send_email_template` sozinho não impediria todos os efeitos e o gravador canônico não os executa inline. Nenhum hook, e-mail, notificação ou chamada externa é disparado pelo gravador novo. Uma outbox durável única por fatura/origem/evento permanece **pendente e visível na administração**.

Consumidor seguro de automações pós-commit ainda não está implementado. Contabilidade, estoque/produtos, serviços e webhooks de saída não devem ser apresentados como sincronizados por esta entrega. Não limpar a outbox nem reexecutar a baixa para tentar provocar essas automações. Esta etapa não promete “exactly once” para sistemas externos. SQL direto, módulos terceiros ou outros escritores que não compartilham os mesmos locks também exigem homologação; não há garantia de transação distribuída.

## Escrita concorrente do core

O patch mínimo em `Payments_model::add`, `add_batch_payment` e `Credit_notes_model::apply_credits` usa `InvoicePaymentWriter` para compartilhar a trava da fatura com o gravador canônico, reler estado/saldo e impedir consumo concorrente duplicado. Créditos também travam e revalidam a nota, cliente/moeda e reembolsos já gravados. Destino não pode ser trocado por filtro; efeitos posteriores existentes só rodam após commit confirmado. O lote não é atomicamente único: itens podem falhar separadamente.

Para faturas com origem Asaas conhecida, os filtros recusam recebimento/crédito genéricos e indicam revisão. Isso inclui origem removida ou ambígua: não existe nesta entrega um fluxo automático de conversão de cobrança remota em recebimento manual. Para faturas sem origem Asaas, permanece o caminho genérico, agora exigindo saldo, estado e armazenamento transacional válidos. Recibos referenciados pelo outbox não podem ser editados/apagados por telas genéricas; reversão financeira exige fluxo próprio ainda não entregue. Não são bloqueados em massa recibos legados sem esse vínculo.

O contrato não abrange todos os escritores de estorno/reembolso ou módulos terceiros. A emissão herdada da PR40 ainda não compartilha a reserva curta sob trava com o pagamento manual: a concorrência entre nova emissão e registro manual pertence à orquestração de emissão/troca seguinte, não à garantia desta conciliação. Os testes MySQL exercitam duas conexões/processos e as duas ordens entre gravação canônica, pagamento manual e aplicação de crédito. Ver `docs/invoice-payment-writer.md`.

## Impacto deliberado no legado e ativação

A URL legada continua autenticando com a configuração existente e persistindo o evento. Porém eventos sem integração explícita, inclusive pendências antigas, vão à revisão e **não geram novas baixas automáticas**. Não há backfill de conta/ambiente com a configuração atual. Essa mudança de compatibilidade é intencional e deve ser comunicada antes de ativar a versão 1.4.8.

Roteiro assistido:

1. Backup e homologação da migration 148, com a origem 1.4.6 da PR40 e a correção de rota 1.4.7 da PR42 já aplicadas. A instalação mantém o DDL legado e executa 146 (origem), 147 (NOOP de rota) e 148 (webhook) pelo App_module_migration nativo, com conferência da versão registrada; upgrade usa o mesmo executor. Falha em 148 não deve ser marcada como concluída.
2. Revisar conta e ambiente, gerar um recebedor local e copiar sua URL/token para configuração manual no Asaas. Não presumir que cadastro local registrou o webhook remoto.
3. Conciliar a origem da cobrança pela tela `Asaas: origem e pendências`, que verifica evidência remota e não cria/cancela cobrança.
4. Selecionar explicitamente ID do evento em revisão e integração da mesma conta/ambiente. A ação administrativa registra ator/data e devolve à fila, sem efetuar baixa no clique.
5. O CRON repete todas as verificações canônicas. O `scope_key` original é preservado, com vínculo assistido auditado. Evento concluído não pode ser reaberto; um vínculo existente não pode ser transferido para outra integração.
6. Revisar separadamente as automações pendentes da outbox.

A política da PR de origem para cancelamento/exclusão de faturas legadas não é alterada por este fluxo. Rollback preserva tabelas, identidades, eventos, recibos e outbox. Suspender a conciliação antes de voltar código antigo: a versão anterior pode consumir payload legado e tratar CONFIRMED como baixa. Não realizar downgrade financeiro cego.

## Testes e homologação

Testes locais sem rede cobrem token ausente/incorreto, headers duplicados, schema/tamanho, dedup/conflito, duas integrações, conta/ambiente/issuer/cliente/referência/valor divergentes, CNPJ alfanumérico, CONFIRMED/RECEIVED fora de ordem, falha de escrita/commit, recibos/créditos, retries, rotação, revisão assistida e respostas públicas fixas. MySQL descartável na CI verifica migração repetível, índices únicos, duas conexões concorrentes, locks, rollback e gravação real do recibo/outbox. Homologação completa do CRM/Asaas sandbox e browser permanece necessária antes de deploy.

## Fontes primárias

- https://docs.asaas.com/docs/about-webhooks
- https://docs.asaas.com/docs/receive-asaas-events-at-your-webhook-endpoint
- https://docs.asaas.com/docs/webhooks-faq
- https://docs.asaas.com/docs/webhook-para-cobrancas
- https://docs.asaas.com/reference/recuperar-walletid
- https://docs.asaas.com/reference/listar-cobrancas
- https://docs.asaas.com/reference/recuperar-uma-unica-cobranca

Consulta em 2026-10-18. A documentação diferencia autenticação pelo header, entrega pelo menos uma vez, ACK após persistência, confirmação e disponibilidade de saldo.
