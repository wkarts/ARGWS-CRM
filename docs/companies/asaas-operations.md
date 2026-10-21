# Asaas por origem de empresa

## Operação disponível

O adapter nativo `admin/company_asaas` é independente do gateway legado e de suas
seis abas. Ele exige contexto selado, associação ativa, ACL por recurso e módulo
Asaas instalado/ativo. A versão1.5.0 exige núcleo4.2.6; as capacidades149 seguem preservadas e a remoção exige migration150 confirmada. Não remove o veto genérico de rollout
nem abre callbacks/checkout legados para empresas nomeadas.

1. Cadastre a carteira Asaas (`walletId`), ambiente explícito e chave API. A chave é
   protegida pela criptografia nativa e nunca volta à interface/listagem. Uma conta
   por empresa/ambiente; a carteira não pode ser trocada implicitamente. A mesma
   carteira/ambiente não pode ser atribuída a empresas diferentes por este adapter.
2. Verifique a carteira por API. O `GET /v3/wallets/` precisa comprovar exatamente a
   conta configurada. Trocar a chave invalida a verificação. Não há herança de chaves
   nem uso do toggle de sandbox legado.
3. Gere um token local de webhook. O hash fica no banco; o segredo aparece uma vez.
   Rotação invalida o anterior. O endereço deriva somente do `base_url` HTTPS
   configurado, sem aceitar host de request, credencial ou query string. Cadastro
   remoto é manual; nenhum endpoint de criação de webhook é chamado.
4. Abra a fatura da empresa e informe o ID do cliente já cadastrado naquela conta
   Asaas. O adapter consulta o cliente e confere CPF/CNPJ com o snapshot documental.
   Emite Pix ou boleto avulso em BRL pelo valor integral da fatura. Conta, ambiente,
   pagador, documento e payload ficam vinculados à origem persistida.
5. Consulte a cobrança para obter evidência canônica. Cada consulta verifica de novo
   carteira e pagador com a chave daquela origem. Nenhuma consulta usa a seleção
   interativa de outra empresa ou deduz conta pelo ID remoto isolado.
6. Um operador com `invoices.view` e `payments.create` confirma o recebimento nominal
   pela interface. O estado precisa ser `RECEIVED`, com consulta recente e sem
   divergências/adversos. O único escritor é `InvoicePaymentWriter`, admitindo apenas
   a classe final `Asaas\CompanyReceivedReceipt`. Não existe writer paralelo.

Administrar configuração exige `settings.edit`; visualizar apenas configurações não
concede listas de cobranças/eventos. Emitir exige faturas.view/edit e customers.view.
Revalidar/consultar exige invoices.view. A seleção de um ID cruzado é recusada antes
de rede, hidratação financeira ou escrita. POST de aba obsoleta falha no middleware
HTTP409 antes do controller. Os estados e capacidades exibidos na GUI são restritos
a este perfil; contas desativadas e permissões revogadas não reutilizam privilégios.

## Idempotência, falhas e evidência financeira

`asaas_payment_origins` é a origem imutável existente, preservando o veto dos escritores
manuais/BB e o isolamento do gateway legado. Os sete ledgers novos são aditivos:
`asaas_company_accounts`, `asaas_company_origin_meta`, `asaas_company_proofs`,
`asaas_company_events`, `asaas_company_receipts`, `asaas_company_jobs` e
`asaas_company_fee_expenses`. Não há backfill de NULL históricos.
Engine, tipos, defaults SQLNULL, índices integrais, collation e FKs são conferidos.
A migration149 deve criar e confirmar os ledgers, sem editar migrations anteriores.

A reserva é confirmada antes do único POST remoto. Repetição da solicitação retorna
sua origem; timeout fica `uncertain`, sem repetir o POST automaticamente. Para retomar,
o operador informa o ID encontrado no Asaas. O GET valida conta, cliente, referência,
valor, vencimento e snapshot antes de vinculá-lo à reserva. Uma divergência canônica
posterior deixa revisão durável e impede reaproveitar uma prova anterior ainda recente.

O callback público `company_asaas_callback/integration/{publicId}` valida
`asaas-access-token`, tipo JSON e tamanho, persiste somente identificadores/tipo/hash
e não faz consulta remota nem pagamento. Mesmo evento é idempotente; mesmo ID com
corpo divergente fica em conflito. Eventos adversos deixam revisão durável, inclusive
quando chegam antes da consulta. Um callback `PAYMENT_RECEIVED` não é baixa: a consulta
canônica continua necessária. A confirmação financeira é manual por padrão; uma
conta pode habilitar explicitamente a conciliação automática sob a identidade de um
colaborador com `settings.edit`, `invoices.view` e `payments.create`. Essa política
não concede permissões futuras nem recupera privilégios revogados. O callback tem
bootstrap mínimo sem sessão, gateways, modelos ou hooks legados.

A chave financeira canônica inclui empresa, carteira, ambiente e cobrança remota;
ela independe da chave de tela, evento ou usuário. Prova e recibo preservam valor bruto,
líquido e tarifa exatos. O pagamento da fatura quita a dívida nominal do cliente.
**Tarifa observada não é automaticamente despesa contabilizada.** O operador escolhe
uma categoria ativa da própria empresa e confirma o lançamento. `CompanyFeeExpense`
valida o recibo financeiro já aplicado e reutiliza `CompanyExpenses::createInOwnedTransaction`
na mesma transação do vínculo canônico. Valor e data vêm da prova, nunca do formulário.
Falha do vínculo reverte a despesa, request e audit. Retry retorna a mesma despesa; a
despesa vinculada não pode ser editada/cancelada por baixo da origem. A UI distingue
tarifa observada, despesa registrada e divergência de registro.
Não existe diferença nominal ignorada para fechar saldo. `CONFIRMED` não significa
fundos disponíveis; parciais, juros/descontos/multas, splits, antecipações, assinaturas,
refunds/chargebacks e estornos não são aplicados por este perfil.

Transações não contêm rede. Locks e revalidação abrangem autorização, origem, documento,
pagamentos, créditos, prova e auditoria. Falha tardia reverte pagamento/status/recibo.
A emissão incerta conserva a reserva; não é apresentada como remota não executada.
Retries equivalentes entre consultas preservam o mesmo pagamento. Eventos adversos
nunca executam reversão financeira automática.

## Conciliação em segundo plano

O opt-in gráfico persiste `automation_actor` na conta. Um evento recebido conhecido
é gravado atomicamente com o job que contém empresa, origem e ator. O cron nativo
chama `CompanyCron::run()`/`CompanyReconciliation::runOnce()` sem selecionar empresa
nem inicializar o gateway legado. Não processa um contexto interativo ativo.

Cada job usa lease de300segundos e até10tentativas com backoff limitado. O worker
revalida módulo, conta, opt-in e permissões antes de consultar; rede ocorre fora dos
locks. Depois de carteira/pagador/cobrança canônicos, a mesma classe final e o mesmo
writer aplicam o recebimento nominal. O source repete a validação do evento, lease,
ator e opt-in dentro da transação financeira, fechando a janela entre consulta e baixa. O estado `CONFIRMED` e adversos nunca ganham
baixa automática. Desativar a política ou revogar o ator interrompe pendências antes
de rede; divergências ficam em revisão. Nenhum diagnóstico do provedor ou secret é
persistido no job.

Uma queda após o commit do recibo e antes da confirmação do job retoma pelo lease;
a chave financeira canônica retorna o pagamento anterior. Não repete emissão remota.
Tarifas continuam exigindo categoria e confirmação explícitas; o job não inventa uma
classificação contábil ou aciona transporte bancário.

## Fontes oficiais verificadas em19/10/2026

- [Criar nova cobrança](https://docs.asaas.com/reference/criar-nova-cobranca): POSTpayments,
  cliente, forma de pagamento, valor, vencimento e referência; criação não é recebimento.
- [Recuperar uma cobrança](https://docs.asaas.com/reference/recuperar-uma-unica-cobranca):
  GETpontual para validação/conciliação.
- [Recuperar WalletId](https://docs.asaas.com/reference/recuperar-walletid): identidade da
  carteira da conta autenticada.
- [Eventos de cobrança](https://docs.asaas.com/docs/webhook-para-cobrancas): separação
  de CONFIRMED/RECEIVED e eventos adversos.
- [Webhook e autenticação](https://docs.asaas.com/docs/criar-novo-webhook-pela-api):
  headerdocumentado, persistência e idempotência. Nenhuma chamada de cadastro foi feita.

## Testes e limites

- `tests/companies/asaas_positive.php`:54 adicionais,117 cumulativos em PHP8.3.6/PDO
  SQLite. Produção de serviços/source/writer reais com respostas remotas sintéticas:
  A/B, segredo criptografado ausente do DTO, carteira/pagador, emissão única, timeout,
  recuperação, consulta, bruto/líquido/tarifa, recebimento canônico, retry, callback,
  rotação, conflito/adverso, bloqueio de prova antiga, falha de audit e legadoNULL.
- `tests/companies/asaas_concurrency.php`: executado pelo coordenador em MariaDB
  descartável,132 cumulativos. Dois processos observados aguardando o lock em retry,
  disputa manual/provedor e revogação durante a espera, sem pagamento órfão/duplicado.
- `tests/companies/asaas_exact_json.php`:10 adicionais/127 cumulativos em SQLite;
  tokens monetários brutos são preservados antes de validar, sem float. Abrange valor
  grande, precisão extra, exponencial, texto/escapes, chave duplicada e conflito durável.
- `tests/companies/asaas_jobs_fees.php`:39 adicionais/156 cumulativos em SQLite,
  A/B, opt-in, retry/backoff, lease expirado depois do pagamento, adverso/revogação,
  categoria própria, recibo de tarifa, imutabilidade, retry e rollback tardio.
- `tests/companies/asaas_job_fee_concurrency.php`: teste implementado para dois
  processos reais disputando job/fee, com revogação sob lock; execução precisa ser
  registrada no conjunto final.
- `tests/companies_asaas_browser.cjs`: teste implementado com CRM/HTTP/MySQL/Chromium.
  Credenciais, token de exposição única, recebimento, despesa da tarifa e callback
  usam a interface/HTTP reais; o worker utiliza transporte sintético explícito. Emissão/consulta são geradas pelo CLI `asaas_http_fixture.php`, que injeta apenas
  transporte sintético e restringe a execução ao banco descartável `argws_upgrade_ci`.
  Sua execução precisa ser comprovada pelo CI do SHA final; implementação do teste
  não equivale a resultado observado.

Não somar contadores cumulativos. A versão/CI exatos do conjunto integrado pertencem
à matriz da PR. Não houve Asaas real, homologação externa, credenciais reais, transação
bancária, registro remoto de webhook, merge ou deploy.

## Pendências técnicas e homologação

Continuam técnicos: cadastro automático de pagador por origem com recuperação, cartão
hospedado por empresa, assinaturas/parcelamento, alterações remotas (exceto remoção avulsa não paga descrita abaixo), ajustes/parciais e estorno/refund financeiros
conforme seus contratos. Não atribuir essas lacunas à falta de credenciais.

Homologação externa é outra etapa: autorização, credenciais e conta/ambiente de teste,
registros e consultas aceitos pelo Asaas, callbacks reais, repetição/timeout e liquidação
observados. Ambientes e resultados devem ser registrados sem segredos. Implementação
sintética/CI não comprova essa homologação.


## Remoção remota por origem — módulo150

A migration150 adiciona `asaas_company_remote_operations`, com chave primária por
origem, chave única de intenção, empresa, ator, revisão de credencial, prova inicial,
estado e instantes. Não altera as sete tabelas149 nem a remoção legada148.
O serviço `CompanyRemoteOperations` exige módulo ativo1.5.0, schema válido, empresa
ativa e permissões `invoices.view/edit/delete` e `settings.edit` revalidadas sob lock.
A GUI oferece a operação na seção Cobranças da origem, com confirmação expressa.

O perfil suportado é cobrança avulsa própria em `PENDING`/`OVERDUE`, sem pagamento,
crédito, recibo, histórico adverso, outra origem/provedor ou job de conciliação pendente.
Carteira, conta/ambiente, pagador, referência, vencimento, valor e snapshot são
reconferidos por GET autenticado antes de reservar. Uma transação durável reserva a
intenção; outra revalida direitos/revisão/prova e grava `sending` antes do único DELETE.
A origem e a fonte financeira final ficam bloqueadas contra recebimento após a intenção.

Um timeout preserva `uncertain`; uma resposta positiva preserva
`awaiting_confirmation` até GET canônico comprovar `deleted=true`, ainda não pago.
404, resposta DELETE isolada e callback `PAYMENT_DELETED` não comprovam exclusão.
A ação de consulta nunca repete DELETE. O único envio retomável é `reserved`, que
prova que o estado `sending` nunca foi confirmado; rotação de credencial ou revogação
impede esse envio. Intenções já enviadas não aceitam outro DELETE, mesmo com nova chave.
Divergências permanecem em revisão; não há remoção manual do ledger ou botão de exceção.
Se a API nunca confirmar o efeito remoto, o operador precisa resolver o estado na conta
Asaas e consultar novamente; isso não autoriza repetição cega nem perda do histórico.

A exclusão remota não cancela a fatura local, não apaga origem/recibos, não devolve
valores e não produz estorno, refund ou chargeback. A emissão local continua protegida
pela origem preservada. Restauração remota não tem operação local adaptada nesta versão.

Contrato oficial consultado em2026-10-19:
- [Excluir cobrança](https://docs.asaas.com/reference/excluir-cobranca): DELETE
  `/v3/payments/{id}`, sem devolução de valores; exclusão individual não encerra
  assinatura/parcelamento, perfis que este adaptador recusa.
- [Recuperar cobrança](https://docs.asaas.com/reference/recuperar-uma-unica-cobranca):
  GET `/v3/payments/{id}` para confirmação autenticada da cobrança da mesma origem.

Provas técnicas próprias:
- `tests/companies/asaas_remote_operations.php`:49 adicionais/166 cumulativos em
  SQLite observados; A/B, NULL legado, histórico financeiro, status recebido sem baixa,
  job pendente, callback excluído seguido de GET, timeout/retry, rotação, revogação,
  falha antes do envio, retomada de intenção nunca enviada e fatura local preservada.
- `tests/companies/asaas_remote_concurrency.php`: implementa duas conexões/processos
  reais, intenção única/DELETE único e revogação sob lock; resultado MariaDB deve ser
  registrado na matriz integrada após execução.
- `tests/companies_asaas_remote_browser.cjs`: GUI/controller/CSRF/contexto A/B reais;
  GET/DELETE canônicos do serviço usam transporte simulado pelo CLI restrito ao banco
  descartável. O POST HTTP prova retry de intenção já confirmada sem rede externa;
  não existe hook de transporte de teste no controller de produção. Executar depois
  da suíte `companies_asaas_browser.cjs`; migration150 aplicada pelo fixture nativo.
  Resultado do SHA final deve ser observado e registrado na matriz integrada.

Essas provas não equivalem a homologação no Asaas. Não foram usadas credenciais reais,
rede financeira real, valores reais, merge ou deploy.


## Coerência150 e moeda documental426

O cabeçalho1.5.0 exige núcleo4.2.6. A migration150 valida a base149 e cria somente
o ledger remoto adicional. As migrations146–149 permanecem históricas. As provas de
tracking exercitam149 explicitamente,150 como HEAD, falha de gravação/readback sem
avançar149, upgrade, replay e recusa de downgrade. As fixtures nativas removem o ledger
remoto antes das provas/origens/emitentes, sem desabilitar FKs nem tocar tabelas externas
aos prefixes descartáveis autorizados.

O adapter participa de `company_currency_units`. Depois de conferir ACL e propriedade,
o ISO de leitura vem de `CompanyCurrencyUnits::resolveRead`; não lê o nome mutável do
catálogo para reinterpretar a moeda. A posição/chaves/valorISO do snapshot149 foram
preservados. Emissão revalida `assertWrite` antes de consultar carteira/pagador e outra
vez dentro da reserva, antes do POST. Um catálogo alterado durante o GET veta a emissão
sem deixar origem reservada. O writer central compõe as tabelas do journal e da moeda;
o recebimento nominal continua único e a despesa da tarifa continua separada.

- `tests/asaas_callback_version.php`:12 assertions observadas com runner nativo e
  double de metadados; não é prova de engine MySQL.
- `tests/companies/asaas_currency_units.php`:10 adicionais/127 cumulativos SQLite
  observados, snapshot149 estável, catálogo adulterado antes/durante emissão, evidência
  ISO inválida, barreira de propriedade antes da leitura e legadoNULL preservado.
- `tests/companies/asaas_environments.php`:10 adicionais/127 cumulativos SQLite
  observados; mesma carteira/ID remoto em sandbox e produção, origem/inbox/credencial
  distintos, token do outro ambiente recusado, remoção apenas na origem correta.
  Ambos os ambientes usam transporte simulado; não houve conta real de produção.

## Próximos recortes técnicos por origem

O documento de continuidade exige preservar os contratos existentes e adaptar cada
capacidade antes de abrir seu gate. As capacidades já positivas149/150 são configuração,
emissão Pix/boleto avulsos, consulta/recuperação, callback local, conciliação canônica,
recebimento nominal, despesa de tarifa e remoção avulsa não paga. As seis abas legadas,
tokens locais, antigos endpoints404, checkout à vista legado e avisoOI continuam separados.
Cadastro remoto automático de webhook não é pendência: o documento exige mantê-lo manual.

Permanecem técnicos os seguintes recortes; não são problemas de homologação:

| Recorte | Implementação que ainda falta por origem |
| --- | --- |
| Checkout hospedado à vista para empresa nomeada | Origem `CREDIT_CARD`, URL de checkout autenticada/validada, GUI/portal isolados e conciliação pelo mesmo source. Reaproveitar a política de URL legada sem importar seu cálculo monetário float. Não receber PAN/CVV nem prometer cartão salvo. |
| Cadastro de pagador na conta | Reserva/idempotência/recuperação por conta e ambiente, validação do cliente privado e prova do ID remoto. O fluxo atual exige ID existente e confere CPF/CNPJ antes de emitir. |
| Alterações de cobrança | Intenção/prova/versionamento de alterações aceitas pela API, sem sobrescrever o snapshot inicial ou reusar prova anterior.150 implementa apenas exclusão não paga. |
| Parciais/encargos e ajustes de recebimento | Composição exata de principal/encargos/tarifas e evidência cumulativa, saldo/journal e revalidação no writer central. Hoje o source aceita somente nominal integral. |
| Refunds/chargebacks e estornos financeiros | Source confiável de evento e lançamento contrário com conta/moeda, saldo, idempotência e evidência canônica suficiente. Hoje observação adversa bloqueia; não existe aplicação financeira automática desses eventos. |
| Parcelamento/assinaturas/cartão reutilizável | Mapas por título/parcela/ciclo e reconciliação cumulativa; o ledger atual representa uma cobrança avulsa. O checkout legado à vista não implementa esses perfis. |
| Exportações financeiras por provedor | Levar bruto/líquido/tarifa/estado de contabilização já existentes no DTO próprio aos consumidores e arquivos globais que os exigirem, preservando o recebimento nominal e a despesa vinculada. |

A próxima entrega vertical recomendada é o checkout hospedado à vista por origem,
apoiado na política de URL existente, seguido do cadastro de pagador. Ambos podem ser
implementados/testados sem credenciais reais e exigem novo incremento/capability antes
de liberar a GUI. Parciais/refunds exigem primeiro o contrato do lançamento contrário
no writer central, não apenas adicionar um status ao callback.
