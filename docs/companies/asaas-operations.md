# Asaas por origem de empresa

## Operação disponível

O adapter nativo `admin/company_asaas` é independente do gateway legado e de suas
seis abas. Ele exige contexto selado, associação ativa, ACL por recurso e módulo
Asaas instalado/ativo com o incremento149. Não remove o veto genérico de rollout
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
hospedado por empresa, assinaturas/parcelamento, alterações/remoções remotas, ajustes/parciais e estorno/refund financeiros
conforme seus contratos. Não atribuir essas lacunas à falta de credenciais.

Homologação externa é outra etapa: autorização, credenciais e conta/ambiente de teste,
registros e consultas aceitos pelo Asaas, callbacks reais, repetição/timeout e liquidação
observados. Ambientes e resultados devem ser registrados sem segredos. Implementação
sintética/CI não comprova essa homologação.
