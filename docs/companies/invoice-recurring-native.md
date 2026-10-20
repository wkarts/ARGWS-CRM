# Agenda de faturas com intervalos nativos e notificação própria

A nova tela `admin/company_invoice_recurring` mantém séries em `company_invoice_schedules`; ela não altera nem adota as séries mensais 425 em `company_recurring`. A tela histórica continua disponível e oferece um link explícito para a agenda nova. O serviço cria somente faturas pelo `CompanyInvoices::create` existente, com chave canônica e veto interno final por origem. Não há novo writer de valores, pagamento, boleto ou assinatura bancária.

O perfil emite faturas válidas. Modos nativos de gerar rascunho e condicionar o ciclo ao pagamento anterior continuam exigindo adapters próprios; não são habilitados por esta agenda.

O operador informa o conteúdo da fatura, primeira data de emissão, primeiro vencimento, intervalo, ciclos e eventual contato. A primeira ocorrência é a data indicada; o vencimento de cada fatura preserva a diferença de dias original. Intervalos padrão são 1–12 meses. O modo personalizado permite 1–1000 dias/semanas/meses/anos. Ciclos 1–10000 limitam o número de emissões; 0 significa sem limite de ciclos. Isso nunca significa processar um lote ilimitado: o worker recebe limite 1–100 (padrão 25) e processa uma única ocorrência devida por série em cada chamada. Datas permanecem no intervalo DATE de quatro dígitos 1000–9999; o limite de calendário encerra a série preservando seu histórico. Não existe corte 2099 nem limite oculto 40000 para séries ilimitadas.

As datas mensais/anuais são projetadas a partir da âncora original. 31/janeiro vira28/fevereiro e31/março; 29/fevereiro pode reaparecer em ano bissexto. Atrasos são recuperados em invocações posteriores do worker, sem varrer todos os ciclos em uma transação aberta. Interface e worker não permitem antecipar emissões futuras.

## Autorização e origem

Criar/processar/controlar exige faturas visualizar/criar/editar e clientes visualizar na própria empresa. Cada ciclo usa o funcionário persistido da série; o worker não recebe empresa por sessão, seletor ou padrão. Outro operador autorizado pode pausar/cancelar, mas retomada/emissão ainda revalidam o responsável original. Funcionário/empresa/cliente/moeda, origem fiscal/destinatário/configuração e identidade do recibo são conferidos sob o mutex nativo antes/depois do writer. Mudança de origem ou autorização pausa o processamento e exige revisão; não substitui silenciosamente o snapshot histórico. O serviço de descoberta recusa contexto interativo ativo.

São persistidos payload/configuração/contexto/transporte imutáveis e hashes, além de um cabeçalho de criação em auditoria. Recalcular apenas hashes da própria linha não permite alterar sua intenção. Controles têm revisão concorrente e recibos próprios; replay de uma pausa antiga não desfaz uma retomada posterior. Cada execução concluída avança a revisão. Cancelar preserva faturas e etapas já confirmadas; inclusive uma série com ciclos concluídos pode cancelar mensagens ainda pendentes, sem estornar a fatura.

## Etapas retomáveis

1. Confirme a intenção de ocorrência sob lock, com empresa/ator/data e chave únicos.
2. Fora dessa transação, invoque o único writer de fatura com veto tipado interno e participantes completos. A chave é SHA256(`company-invoice-schedule:<issuer>:<schedule>:<date>`).
3. Confirme o vínculo e a revisão da fatura. Se isso falhar depois do commit, o leitor distingue o recibo canônico já confirmado do vínculo local pendente e oferece a mesma retomada. Retry não consome nova numeração.
4. Se explicitamente escolhido, invoque `CompanyInvoiceDelivery::queueInvoiceRecurring` e confirme seu recibo de fila na ocorrência. Falha depois da fila confirma uma intenção retomável, sem nova mensagem no retry.
5. Confirme a ocorrência e avance um único ciclo. Original monetário, custos e caixa não são alterados por registrar ou cancelar uma agenda.

O leitor verifica a sequência de datas, ator, chaves, recibo canônico e pai de cada ocorrência. Uma FK simples válida para uma fatura de outra empresa não permite devolver esse ID. Filas e recibos são apresentados como fatos separados do aceite/entrega SMTP. A limpeza nativa pode arquivar a fila sem apagar o recibo durável; retry não recria mensagens antigas.

## Notificação opt-in e transporte

O padrão é notificação manual. Para envio automático, o operador escolhe um contato ativo do cliente privado da empresa com permissão nativa1. Compartilhamento da identidade do cliente não disponibiliza contatos do proprietário. O remetente, template, assinatura, baseURL confiável e transporte são próprios da empresa; somente digests de configuração entram na série. Não existe PDF público, bearer nem envio bancário implícito.

O source final `CompanyInvoiceRecurringDeliverySource` é um veto fixo: ele não concede permissões à fatura/contato nem aceita callback arbitrário. A fila usa o mesmo serviço, recibo e política de documentos já existentes; a assinatura histórica `queue(...?CompanyExpenseRecurringDeliverySource)` é preservada. A nova entrada tipada delega ao mesmo núcleo de fila, mantendo a compatibilidade com o guard de reflexão da recorrência de despesas436.

O marcador `companyInvoiceOccurrence` e o recibo canônico permitem revalidar a série antes do transporte, mesmo se alguém remover os headers e `issuer_id` da linha da fila. O marcador é removido antes do SMTP. Pausa/cancelamento, contato/permissão/ator revogados e revisão de fatura/transporte alterada impedem envio. Sources Google/Microsoft repetem o veto depois do refresh antes de liberar a credencial. Rotação normal de access/refresh token não altera a identidade congelada; reconexão, revogação ou troca explícita de transporte bloqueiam os ciclos antigos. Não há rede dentro de transação financeira.

## Instalação futura e rollback operacional

A integração futura deve executar `CompanyInvoiceRecurringSchema::ensure($db,$prefix)` fora de transação, depois do núcleo de faturas/moeda/identidade. São três tabelas aditivas: schedules, runs e requests. O schema verifica InnoDB, tipos, nulidade/defaultSQLNULL, collation binária, PK/índices integrais, unicidades de ocorrência/fatura/fila e FK composta `(issuer_id,schedule_id)`. Nenhuma migration histórica, versão ou tracking foi criada por este pacote. O responsável pela integração gera o próximo passo nativo sobre o HEAD efetivo.

Os hunks de ligação são separados: rota exata/index; link na tela histórica; chamada delimitada no worker CLI existente; wrapper de fila e guards tipados em App_Email/Google/Microsoft. Não se abrem prefixos de rota ou endpointsHTTP de worker. Para suspender a operação, feche essas entradas/worker e preserve tabelas, documentos, números, recibos, auditoria e mensagens. Não faça downgrade destrutivo, purga de recibos ou recriação de uma série para compensar um resultado incerto.

## Evidência do pacote

- `invoice_recurring_native.php`: A/B/principal/NULL, calendário e ciclos, payload/owner, controles, retry após commit e preservação de série425 real já emitida.
- `invoice_recurring_delivery.php`: opt-in A/B, contato nativo1, fila idempotente, revogação antes da emissão, pausa/cancelamento e falha após fila.
- `invoice_recurring_worker.php`: App_Email e roteamento reais, sendSMTP final capturado, remoção de headers/origem, descoberta delimitada e funcionário revogado.
- `invoice_recurring_oauth.php`: fontes tipadasGoogle/Microsoft, RSA real e protocolo sintético, refresh estável, pausa/cancelamento durante refresh, revogação/reconexão e futuros ciclos.
- `invoice_recurring_integrity.php`: criação independente, pai de recibo, calendário e metadata/DDL/replay em MariaDB e participante de itens com engine não transacional.
- `invoice_recurring_concurrency.php`: processos independentes observados aguardando InnoDB em criação/emissão e revogação de ator/contato/origem.
- `invoice_recurring_late_concurrency.php`: reserva já commitada e dois writers aguardando o mutex real; pausa tardia veta ambos e preserva a intenção para retomada única.
- `invoice_recurring_controller.php`: controller/view reais, CSRF, contexto, revisão, retomada e mensagens de estado.
- `invoice_recurring_browser.cjs`: aplicaçãoHTTP/Chromium real A/B, PDFbinário, ambosCSRF, formulário obsoleto após troca de empresa, falhaSQLtardia e retomada. Copia explicitamente fixture+BbPdo para runtime sem testes herdados.

Execuções e hashes estão no manifesto/INTEGRATION do pacote. O browser de desenvolvimento reutiliza a instalação descartável nativa432 com o helper aditivo protótipo; não comprova migration futura ou publicação. A integração executará instalação/upgrade/tracking/CI no seu próprio SHA. ProtocoloOAuth/SMTP são simulados/capturados, não homologaçãoGoogle/Microsoft, chegada de e-mail ou operação bancária. Nenhum merge/deploy/operação real faz parte desta entrega.
