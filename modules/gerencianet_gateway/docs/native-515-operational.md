# Release 5.1.5 — limite operacional auditável

Implementação em revisão, não homologação bancária. Não executa pagamentos reais,
não ativa módulo/gateway em produção e não migra credenciais silenciosamente.

## Fronteira

- Biblioteca é carregada pelo framework a partir do módulo; SDK 5.1.4 já incluído
  só é instanciado após autorização. Duas assinaturas nullable ficam explícitas
  para PHP8.4+; o adaptador declara options para evitar propriedade dinâmica, sem
  suprimir warnings. Rotas são exercitadas pelo SDK real com requester sintético. Mutações não são
  repetidas automaticamente após erro de autorização; redirects HTTP são negados
  para não reenviar credenciais ou POSTs fora da barreira de dispatch.
- Rotas antigas revisadas resolvem para o módulo. Cópias antigas em `application/`
  não são apagadas por instalador/desinstalador; o preflight do núcleo as nega.
- Checkout nativo aceita somente fatura com `issuer_id IS NULL`, ID/hash corretos,
  moeda BRL, gateway ativo e selecionável na fatura. Não concede acesso a empresas.
- Criação exige POST + token de sessão próprio, mesmo se o CSRF global estiver
  desabilitado. Notificações consultam o provedor; nunca confiam no corpo recebido.
- Conversão/conciliação exigem administrador. Conversão exige também POST/CSRF.

## Vínculo e liquidação

Cada cobrança nova registra kind, ID do provedor, aplicação (SHA256 do client_id,
sem secret), ambiente, fatura/owner, BRL e valor inteiro em centavos. Callback
sem esse registro é negado. `invoice.token` e `infoAdicionais.CÓDIGO` não comprovam
ambiente/conta: cobranças históricas sem vínculo exigem revisão manual individual;
esta release não as associa automaticamente nem apaga seu histórico.

A confirmação exige consulta autenticada à mesma aplicação/ambiente. Pix exige
CONCLUIDA, txid idêntico, valor original/pago exatos, um endToEndId e ausência de
devoluções. Múltiplos recebimentos ou valores parciais exigem revisão, não soma
inventada. Boleto/cartão exigem charge_id, paid e total em centavos exatos.

Contrato consultado: [API Pix — cobranças imediatas](https://dev.efipay.com.br/docs/api-pix/cobrancas-imediatas/)
e [API Cobranças — boleto](https://dev.efipay.com.br/docs/api-cobrancas/boleto/).
O código não transforma floats em centavos nem assume moeda de faturas estrangeiras.
`install/database.sql` define o total nativo como DECIMAL(15,2).

O lock consultivo inclui banco/prefixo/fatura, serializando cobranças Efí distintas.
O `Payments_model` continua usando o `InvoicePaymentWriter` canônico, com seu lock
FOR UPDATE, saldo/créditos e transação independente. Não há transação externa
aninhada. A referência determinística do recibo permite recuperar tracking após
falha posterior ao commit, sem segundo pagamento/segunda notificação. Essa
recuperação não repete efeitos de notificação que possam ter falhado depois do
commit; exige revisão desses efeitos separadamente. Uma segunda cobrança paga
externamente para fatura sem saldo é rejeitada localmente, não apagada nem baixada.

A criação agora tem intenção única por fatura, persistida com commit independente
antes de qualquer POST remoto. Uma segunda modalidade/conta/ambiente não recebe
permissão para emitir. Pix só é criado após clique explícito, nunca ao abrir a
página. Estado bound permite apenas consultas GET; timeout/queda/estado
intermediário exige revisão, sem rePOST automático. Boleto retém o ID criado e
persiste uma segunda barreira antes de associar o meio de pagamento.

A reserva recusa transações externas (CI/PDO/raw SQL), autocommit0, storage não
transacional e commit não confirmado. Valida a fatura e saldo sob lock antes de
persistir a intenção. Rascunhos, faturas pagas/canceladas e faturas com créditos ou
recebimentos parciais não recebem cobrança pelo total. Troca de modalidade e
renovação de cobrança expirada exigem fluxo explícito de revisão; não existe um
reset automático da intenção. Não se promete transação distribuída ou homologação:
um provedor pode ter criado uma cobrança durante timeout; a intenção permanece
retida justamente para impedir outra cobrança cega.

## Certificados

P12 é validado offline (chave/certificado correspondente e validade) e armazenado
como ciphertext pela `CI_Encryption`. Não é prova de cadeia/revogação/aceitação
pelo banco. Não se grava chave nova em media/uploads. O SDK recebe arquivo
temporário 0600 com chave+certificado; limpeza ocorre no encerramento normal.
SIGKILL/queda pode deixar esse arquivo privado até limpeza do host: não se promete
apagamento absoluto. Certificados antigos públicos não são removidos ou lidos
pela adoção; sua migração/remoção requer revisão administrativa explícita. Arquivo
externo configurado só é aceito fora do webroot e sem permissão de grupo/outros.

## Evidência

- Python estrutura: `python3 tests/test_efi_operational_structure.py` (5 PASS local).
- PHP sintético, ainda requer executor CI:
  `tests/efi/operational_contract.php`, `controller_contract.php`,
  `certificate_contract.php`, `sdk_transport_contract.php`, `intent_contract.php`.
- MySQL/CI3 real + writer canônico/triggers e duas conexões:
  `tests/companies/efi_operational_mysql.php`, banco descartável
  `issuer_*module_audit`. Inclui dois processos reais concorrendo Pix/cartão, com
  espera de row lock comprovada no performance_schema, e apenas uma permissão de
  dispatch. Não rodar em banco produtivo.
- PHP/Docker ausentes no executor local. Nenhum resultado PHP/MySQL/provider
  foi declarado como executado localmente. Testes não equivalem a homologação.
