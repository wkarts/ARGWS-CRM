# Asaas151 — pagador privado e checkout hospedado avulso

A versão1.5.1 exige núcleo4.2.7. É um incremento sobre149/150: não substitui gateway,
seis abas, callbacks ou checkout legados e não abre o veto genérico desses endpoints.
A migration151 confirma as bases149/150 e cria somente `asaas_company_payers` e
`asaas_company_hosted_links`. Nenhuma migração anterior é editada.

## Pagador da conta

Na seção Emissão, o operador com `invoices.view` e `customers.view/create` escolhe
uma conta verificada e informa o cliente privado ativo daquela empresa. Só nome fiscal,
CPF/CNPJ, referência opaca e `notificationDisabled=true` são enviados. Contatos,
e-mails, telefones, endereços e cópias não são herdados nem enviados. Um cliente legado
NULL ou de outra empresa não pode ser adotado pelo cadastro; visibilidade compartilhada
de identidade não autoriza esse envio de cadastro privado.

Há uma intenção durável única por conta/cliente, com empresa, ator, revisão da chave,
hash da identidade e referência externa. A consulta autenticada confirma a carteira e
lista a referência exata, no máximo cinco páginas de100 resultados. Duplicidade, resposta
fora do filtro ou paginação incompleta impedem criação e ficam em revisão; ausência
numa página isolada não comprova inexistência. Um resultado existente exige GET detalhado
com ID, referência, nome, CPF/CNPJ, `deleted=false` e notificações desabilitadas.

Quando não há resultado numa listagem completa, a transação revalida tudo e confirma
`sending` antes do único `POST /customers`. O ID só fica pronto após GET canônico.
Timeout permanece `uncertain`; registrar ou despachar novamente não repete POST enviado.
A recuperação usa referência externa ou ID explícito da mesma origem e nunca cria um
segundo cadastro. Somente `reserved`, que prova não ter iniciado envio, aceita retomada
expressa. Rotação da credencial, mudança de identidade ou revogação impede essa retomada.
Resposta antiga não pode reabrir revisão cadastral mais recente.

Não há atualização automática do pagador nem busca por e-mail/CPF para adivinhar vínculo.
Uma identidade local alterada exige revisão explícita, não novo cadastro silencioso.

## Checkout hospedado

O operador escolhe Cartão hospedado à vista no formulário da fatura própria. O serviço
151 cria `CREDIT_CARD` com valor decimal exato, vencimento e referência documental;
não envia parcelas, assinatura, PAN, CVV, titular, cartão tokenizado ou IP fictício.
Depois consulta novamente carteira, cliente e cobrança. A URL vem dessa prova canônica,
com HTTPS, host exato do ambiente e caminho `/i/` aprovado pela política já existente.
Não aceita aliases, credencial na URL, porta, query string, fragmento ou URL arbitrária.

O payload representa uma cobrança avulsa. A integração não oferece parcelamento,
assinaturas ou cartão salvo. Se evidência posterior indicar esses perfis, split, captura
recusada, reprovação de risco ou outro adverso, a origem exige revisão. A aplicação não
controla configurações da página externa do Asaas; sua aceitação deve ser homologada.

A criação e a abertura da URL não lançam recebimento. `CONFIRMED` não é `RECEIVED`.
Somente `RECEIVED` integral, sem adversos, com bruto/líquido/tarifa e datas compatíveis
passa pelo mesmo `CompanyReceivedReceipt` final e `InvoicePaymentWriter`. A tarifa
continua despesa separada, conforme149. Tokens de cartão presentes na resposta remota
não entram no JSON seguro, DTO, formulário ou log.

## Publicação no portal

O link começa privado ao operador. Sua publicação exige confirmação expressa, edição
/visão da fatura, visão do cliente e prova canônica recente. O ledger guarda origem,
empresa, prova, hash da URL, revisão da credencial e responsável. Ocultar revoga a
publicação mesmo se a conta estiver desativada.

O portal usa a sessão privada existente, o vínculo de proprietário, o cliente exato da
fatura e a permissão financeira nativa do contato. Compartilhamento de identidade,
login em outra empresa e conhecimento do ID não concedem acesso. A permissão do
publicador, conta ativa/verificada, revisão, documento, estado não pago, provas e
publicação são revalidados antes de liberar o link. Rotação exige republicação após
nova confirmação. Pagamento, crédito, remoção remota ou adverso impede a abertura.

O botão usa POST com CSRF próprio do portal e revalida novamente antes de retornar303
para a URL bancária validada, sem chamada financeira externa. A listagem não cria
cobranças/pagadores, não utiliza credenciais e não lança pagamentos. Nenhum endpoint
legado é usado como atalho.

## Contratos e prova

Fontes primárias capturadas em `asaas-151-contract.json` registram URL, data, versão
OpenAPI, bytes e SHA256 do Markdown oficial. Abrangem criação/listagem/GET de cliente,
orientação contra duplicidade/timeout e cobrança hospedada sem dados de cartão:

- https://docs.asaas.com/docs/criando-um-cliente
- https://docs.asaas.com/reference/criar-novo-cliente
- https://docs.asaas.com/reference/listar-clientes
- https://docs.asaas.com/reference/recuperar-um-unico-cliente
- https://docs.asaas.com/docs/cobrancas-via-cartao-de-credito
- https://docs.asaas.com/reference/criar-cobranca-com-cartao-de-credito

SQLite observado em cópia isolada, sem alterar426:

- `asaas_checkout_payers.php`:32 adicionais/149 cumulativos.
- `asaas_checkout_boundaries.php`:21 adicionais/170 cumulativos.
- `asaas_checkout_portal.php`:15 adicionais/164 cumulativos.
- `asaas_checkout_views.php`:7 adicionais/156 cumulativos, renderização nativa real.
- `asaas_callback_version.php`:13 checks com runner real e double de metadados.

Não somar os contadores cumulativos. Native engine151 foi executado em MariaDB10.11.14,48 checks e46 no callback,
e concorrência em duas conexões (`asaas_checkout_concurrency.php`) passou165
checks cumulativos. O resultado Chromium427 (`companies_asaas_checkout_browser.cjs`)
é registrado separadamente em [validação427](pr45-validation-427.md). O browser usa GUI/controller,
publicação e portal/CSRF reais; criação/consulta bancária é simulada pelo CLI restrito ao
banco descartável. Não existe hook de transporte de teste no controller de produção.

Não houve conta bancária real, credencial real, cobrança real, SMTP externo, registro
automático de webhook, merge ou deploy. Isso não comprova homologação externa.
Continuam técnicos: alterações de títulos, parcelas/assinaturas, parciais/encargos e
refunds/chargebacks com fonte e lançamento contrário no writer canônico.


## Recebimento nominal do cartão avulso (151)

O mesmo source final CompanyReceivedReceipt e o escritor financeiro central recebem
CREDIT_CARD apenas de origem própria criada no fluxo hospedado avulso. A consulta
canônica autenticada confirma wallet/ambiente/revisão, ID, pagador, referência, método,
vencimento e valor integral do snapshot. O webhook autenticado apenas registra o
inbox e, quando habilitado, agenda a consulta pelo worker com ator e origem persistidos.

RECEIVED é o estado disponível na conta; CONFIRMED e AUTHORIZED não lançam dinheiro.
Além do estado, exige-se creditDate efetiva, válida e já ocorrida, igual ou posterior
à paymentDate e à data da fatura. A baixa do cartão usa creditDate; estimatedCreditDate
não a substitui. O perfil canônico hosted_single_v1 comprova ausência de installment,
subscription e installmentNumber, além de anticipated=false e deleted=false explícitos.
Campos inesperados de parcelamento e qualquer escrow/custody impedem a baixa. O perfil
fica no JSON seguro e na evidência apenas do cartão. PIX/BOLETO preservam os campos,
a data e a fórmula de evidência149. Prova antiga de cartão sem perfil exige novo GET.

O recebimento é nominal bruto; líquido e tarifa observados não alteram a dívida. A
GUI oferece a despesa de tarifa separada e explícita, vinculada à mesma evidência e à
data de crédito, com escritor nativo, auditoria, permissões e repetição idempotente.
Rotação, revogação, evento adverso ou remoção remota pendente invalidam a tentativa no
limite final da transação. Nenhuma resposta de cartão completa ou token é persistido.

Fontes atuais da API v3, versões dos documentos, data de captura e hashes constam em
asaas-151-card-settlement-contract.json. São contrato de código, não homologação.
A suíte asaas_card_settlement.php exercita o source/escritor/worker/despesa reais com
protocolo bancário simulado; a suíte de concorrência usa duas sessões nativas quando
executada pelo runner MariaDB. O browser151 inclui confirmação nominal e tarifa A/B
pela GUI após observação canônica simulada em fixture CLI restrita ao banco descartável.

Parcelamento, assinatura, antecipação, escrow/custódia e contra-lançamentos de estorno
ou chargeback continuam fora deste perfil. Não se apresentam esses fluxos como
implementados nem como simples pendência de credenciais/homologação.


PAYMENT_ANTICIPATED é histórico adverso persistido: antecipação não pertence ao perfil
avulso suportado. Mesmo que o GET posterior volte a informar anticipated=false, a
origem continua em revisão. Antes da baixa não cria dinheiro; depois da baixa não
reescreve a chave/evidência, não altera o pagamento existente e não lança reversão.
Isso também protege títulos PIX/BOLETO sem mudar suas provas e chaves históricas.
A situação exige reconciliação própria, não uma repetição automática do recebimento.

## Entrada legada antes dos providers

Os quatro métodos públicos do Checkout legado rejeitam `issuer_id` nomeado antes
da hidratação. `AsaasLegacyCheckoutPreflight` também protege a fase autoload real,
antes de helpers de arquivos e catálogo de gateways. A prova usa o kernel
CI_Controller/App_Controller/ClientsController/HMVC real, não somente uma chamada
direta ao método;113 checks, incluindo caminhoNULL legado e falha fechada de DB.
A consulta mínima não concede autoridade e o controller revalidaNULL para a corrida.
O testeHTTP completo é `asaas_checkout_legacy_scope_http.cjs`; o resultado está
vinculado à rodada427, não às simulações de provider ou ao CI426 anterior.
