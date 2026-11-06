# Cartão direto por token — delta 1.6.2

Parte do requisito ASA03, ainda **não atendimento completo** do consolidado. Em
Configurações > Meios de pagamento > Asaas > Cartão, um operador autorizado pode
selecionar o contrato de pagamento de uma cobrança CREDIT_CARD nativa existente:

- `POST /payments/{id}/payWithCreditCard`, com `creditCardToken`;
- `POST /payments/{id}/payWithCard`, com `cardType=CREDIT` e `cardToken`.

Referências oficiais consultadas em 6/11/2026:
[payWithCreditCard](https://docs.asaas.com/reference/pagar-uma-cobranca-com-cartao-de-credito),
[payWithCard](https://docs.asaas.com/reference/pagar-uma-cobranca-com-cartao),
[PCI DSS](https://docs.asaas.com/docs/pci-dss).
O token deve pertencer ao cliente da cobrança; o provedor continua responsável por
validar essa associação. A operação pode cobrar imediatamente antes do vencimento.
O formulário exige confirmação explícita, HTTPS reconhecido pelo servidor, CSRF,
contexto empresarial e permissões de fatura, cliente e recebimento. Cabeçalhos de
proxy enviados pelo cliente não autorizam HTTPS. Uma implantação com terminação
TLS deve configurar corretamente o contexto HTTPS confiável do servidor.

A disponibilidade continua parametrizada por empresa/ambiente. O contrato é uma
escolha por operação, sem alterar silenciosamente a origem. Campos PAN/CVV não
existem nesta entrega, nem criação/armazenamento de tokens. Tokens de entrada não
são enviados de volta ao navegador, persistidos em sessão, banco ou auditoria. O
fingerprint de idempotência inclui SHA-256 do comando, nunca o token em claro.
O retorno bruto do pagamento também não é persistido.

Restrição expressa do usuário: PAN/CVV não podem ser armazenados em banco, logs,
sessão, cookies, localStorage, sessionStorage ou outros arquivos. Este fluxo não
coleta esses campos. O token informado existe transitoriamente no campo de senha
do navegador, no POST HTTPS ao CRM e na memória do processo durante o POST HTTPS
ao Asaas; não é salvo como credencial reutilizável. O ledger persiste identificadores
de origem/empresa/ator, revisão da conta, operação, prova, estado, códigos internos,
datas, chave de idempotência e hash do comando (que inclui o token), sem o valor
do token. Cookies de autenticação/CSRF nativos não recebem dados do cartão.
Isso não constitui homologação PCI; configurações externas de proxy/APM não foram
validadas e não devem capturar corpos dessas requisições. Tokenização segura e
a experiência completa de cartão ainda precisam da implementação descrita nas lacunas.

## Persistência, concorrência e resultados desconhecidos

A migration nativa 162 cria `asaas_company_card_payments`, com FK para origem,
empresa e prova canônica; não cria cobrança, backfill ou consentimento. A origem
só aceita uma tentativa de envio. O bloqueio financeiro nativo serializa a criação
do comando, confere ACL, revisão da conta, saldo, perfil ativo, origem e prova
canônica recente. HTTP acontece fora da transação. Uma consulta inicial verifica
carteira, cliente e cobrança antes de reservar o envio. Exclusão remota e pagamento
se bloqueiam por intenções persistidas; a origem do provedor já impede baixa manual.

Retry da mesma chave retorna a tentativa existente. Outra chave não libera novo
pagamento. Timeout, erro HTTP ou resposta inesperada permanecem incertos, e não
são convertidos em recusa definitiva ou ausência de cobrança. A recuperação faz
GET canônico; não repete POST. Uma resposta técnica é auditada mesmo se a permissão
for revogada durante HTTP; consultas posteriores exigem autorização vigente.
CONFIRMED não lança recebimento local. A conciliação existente continua exigindo a
prova financeira elegível, inclusive data de crédito para cartão.

Uma tentativa recusada ou incerta **ainda não tem fluxo de liberação de nova
tentativa**. Não apagar o ledger para contornar essa proteção. A interface mostra
o estado e oferece consulta, mas a revisão operacional completa permanece pendente.

## Evidências executadas

- MySQL real: migration pelo runner nativo, DDL falho, falha de tracking, retry,
  replay e preservação de dados anteriores: 227 assertions cumulativas.
- MySQL real + provedor simulado: 165 checks cumulativos de serviços; pagamento
  observado por GET, isolamento, retry, timeout, exclusão concorrente, veto manual,
  revogação durante HTTP e preservação da observação.
- Dois processos PHP + bloqueio observado em MySQL: 177 checks cumulativos;
  concorrentes enviam um único POST; revogação antes de adquirir lock envia zero.
- Controller real com requisição/sessão sintéticas e SQLite: 198 checks cumulativos,
  incluindo CSRF, confirmação, HTTPS e rejeição de arrays antes de acesso externo.
- Chromium com view PHP real e servidor sintético: guia, contratos, confirmação
  obrigatória, token vazio após reload, CSRF/contexto. Não é teste HTTP autenticado.
- Contrato de transporte: 19 checks, campos exatos, ausência de retry e rejeição de
  payloads misturados/PAN/CVV. VOUCHER é reconhecido pelo DTO de transporte, mas
  **não habilitado** pelo serviço financeiro nativo.

Contagens incluem os fixtures e testes anteriores requeridos; não representam
177 cenários novos. Sem credencial de sandbox, transação real ou homologação externa.

## Dependências e lacunas

SDK remoto main verificado em `170831639665082c3bf50422877942b04a4a5315` após o
usuário integrar PR21. O adaptador multiempresa CRM usa `CompanyClient`, com seus
próprios limites e evidências. Nenhum arquivo do SDK, vendor ou módulo Perfex foi
alterado nesta etapa. A ampliação do SDK permanece tarefa independente pausada.

Pendentes: experiência completa de coleta/tokenização, 3DS, parcelamento, parâmetros
adicionais e eventual portal do cliente; voucher/débito não habilitados; sandbox e
HTTP autenticado completo. Não inferir que checkout hospedado ou esta operação por
token concluem ASA03. Não há assinatura recorrente de cartão nesta entrega.

Deploy não executado. Antes de atualização: backup consistente e runner nativo;
falha de tracking mantém a capacidade indisponível. Rollback de código não pode
apagar tentativas nem diminuir marcadores; pagamentos já enviados exigem consulta
e conciliação, nunca reenvio baseado apenas no rollback.
