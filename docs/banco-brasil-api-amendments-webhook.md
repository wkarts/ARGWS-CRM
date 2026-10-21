# Alteração de vencimento e webhook de baixa operacional

Este incremento preserva registro, consulta, baixa e crédito API integral restrito.
Não usa credenciais, certificados privados ou transações reais do Banco do Brasil.

## Fonte e recorte implementado

Contrato: OpenAPI oficial de Cobranças, catálogo 17395705/2, versão 3.2.2,
recuperada em 19/10/2026; 198948 bytes, SHA256
`6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.
Fonte primária: <https://api.externo.bb.com.br/docs/v1/openapi/17395705/2>.
A cópia conferida nesta continuação mantém exatamente esse hash. A ferramenta de
pesquisa não conseguiu reabrir essa rota; a tentativa HTTP direta retornou403.
O guia oficial referenciado pela OpenAPI
<https://apoio.developers.bb.com.br/guias-e-tutoriais/webhook/visao-geral>
retornou502 na tentativa desta continuação. Não foi inferida uma cadeia de
certificados, autoridade certificadora ou endpoint de cadastro a partir disso.

A tela Banco do Brasil permite solicitar novo vencimento para origem API própria.
A instrução usa `PATCH /boletos/{id}`, `numeroConvenio` e os18 indicadores
obrigatórios explícitos: somente `indicadorNovaDataVencimento=S`; os demais ficam
`N`, com `alteracaoData.novaDataVencimento=dd.mm.aaaa`.

Exige `banco_brasil/view`, `api_amend`, `invoices/view` e `invoices/edit`, título
NORMAL consultado com prova de conta nos últimos cinco minutos, data válida,
ausência de pagamento/crédito e de outra operação em andamento. A intenção e o
motivo são persistidos antes de OAuth/PATCH. Autorização é revalidada antes do
marcador de envio. Uma chave com conteúdo ou ator diferente conflita. Retry da
mesma instrução não repete PATCH enviado, confirmado, rejeitado ou incerto.

A resposta PATCH200 significa `amendment_requested`. Apenas consulta posterior
validada do título **e** listagem da conta com a data solicitada mudam a operação
para `confirmed`/`amendment_confirmed`. Timeout é incerto; consulta pode reconciliar
uma resposta perdida. Consulta com a data antiga não confirma a alteração e não
autoriza repetir a instrução. Um conflito exige revisão; não existe botão de
forçar confirmação.

O snapshot e a data da fatura original permanecem imutáveis. A data bancária
confirmada é uma projeção adicional da origem, preservada no histórico. O código
de barras/linha anterior ao pedido não deve ser reutilizado como documento de
cobrança com novo vencimento; este incremento não oferece reimpressão de boleto
alterado. Alteração de valor, descontos, juros, multa, protesto, endereço e demais
instruções continuam pendências técnicas, com contrato e critérios próprios.

## Recebedor local por perfil e certificado

A GUI cadastra fingerprint SHA256 do certificado **cliente** do BB, referência
contratual/de validação e estado ativo/inativo para um perfil/empresa/ambiente
persistido. Reenviar os mesmos perfil/fingerprint/referência altera somente o
estado. Rotação permite adicionar o novo pin e desativar o anterior, conservando
o histórico. Isso é cadastro local, sem chamada de registro remoto ao banco.

O endpoint POST `/banco_brasil/notifications` exige:

1. `BB_WEBHOOK_NATIVE_TLS=1`, configurado pelo responsável da infraestrutura.
2. HTTPS nativo e `SSL_CLIENT_VERIFY=SUCCESS` fornecidos pelo terminador TLS.
3. Certificado X509 cliente válido no tempo em `SSL_CLIENT_CERT`, fornecido pelo
   terminador TLS, e fingerprint SHA256 correspondente ao recebedor local ativo.
4. Origem API persistida, empresa ativa, módulo ativo e atualizado, convênio,
   carteira17, variação, total e perfil consistentes. Se houver mais de uma origem
   elegível para o mesmo número/certificado, o callback é recusado; ambiente não
   é adivinhado pelo corpo.

Nenhum cabeçalho `X-*`, `HTTP_SSL_CLIENT_*`, IP, HMAC, sessão, seletor ou parâmetro
HTTP substitui a prova TLS. A aplicação não instala confiança em uma CA sozinha.
O ingresso deve validar a cadeia cliente oficial, sua finalidade e revogação
conforme contrato. Pins adicionam delimitação por perfil; não substituem a cadeia
mTLS. Sem a infraestrutura configurada, toda chamada recebe401 antes do acesso
a banco de dados pelo controlador. Não habilitar a variável apenas para eliminar
o erro401.

Exemplo conceitual para futura implantação autorizada em Nginx/FastCGI, depois de
receber e verificar a cadeia/certificado públicos oficiais do ambiente contratado:

```nginx
# Servidor dedicado: cadeia BB e certificado servidor definidos pela operação.
ssl_client_certificate /run/secrets/bb-webhook-client-ca.pem;
ssl_verify_client on;
ssl_verify_depth 4; # Ajustar à cadeia oficial contratada.

# Location exata, encaminhada ao front controller PHP por configuração revisada.
# Não copiar valores de headers HTTP para estas variáveis FastCGI.
fastcgi_param HTTPS on;
fastcgi_param SSL_CLIENT_VERIFY $ssl_client_verify;
fastcgi_param SSL_CLIENT_CERT $ssl_client_raw_cert;
```

O exemplo não foi aplicado e não é uma stack pronta/homologada. Proteger o socket
PHP, impedir acesso direto que contorne TLS e configurar limitação de taxa/tamanho
no ingresso. Apenas os dois caminhos exatos da notificação são isentos de CSRF;
as ações administrativas continuam com CSRF e token de contexto da empresa.

A notificação usa o schema `WebhookBaixaBoleto` da OpenAPI. O recorte aceita
carteira17/modalidade1, estados operacionais1/2/10, identificadores, valores e
datas válidos. Mantém projeção mínima sem nome/documento do portador. A chave
canônica usa origem, operação, estado e data/hora; repetição equivalente retorna
200 sem duplicar, conteúdo divergente da mesma chave é rejeitado. Erro de
persistência retorna503, sem confirmação falsa. Campos opcionais de outros perfis
não ampliam o recorte financeiro.

Notificação autenticada **não** é crédito disponível, pagamento, baixa financeira
ou estorno. A GUI mostra somente observação operacional. Código10 (cancelamento
da baixa operacional) conserva o evento anterior e veta a aplicação financeira
API restrita para revisão. Estados fora de ordem não apagam histórico. Consultas
e ações explícitas de crédito mantêm as provas já existentes.

## Banco, upgrade e rollback futuro

`ApiWebhookSchema::ensure` cria aditivamente `bb_api_webhook_receivers` e
`bb_api_notifications`, InnoDB, collation binária, unicidade e FKs verificadas.
O executor nativo BB111 deve chamá-lo depois dos schemas API anteriores. A
alteração de vencimento reaproveita `bb_api_operations`; nenhuma migration
histórica muda. Métodos novos exigem marcador111; instalação incompleta falha
fechada. Não há adoção de documentos legadosNULL.

Rollback operacional futuro: desativar recebedores, retirar a habilitação do
endpoint e preservar as duas tabelas e o histórico de instruções/eventos. Uma
instrução já enviada não é revertida por rollback de código. Conferir seu estado
por origem antes de decidir outra instrução; não apagar registros para repetir
PATCH ou reutilizar Nosso Número. Aplicação de migration/deploy não foi realizada
nesta execução.

## Evidência e pendências distintas

- `tests/banco_brasil_mtls.php`: certificados sintéticos, variáveis TLS, rejeição de
  headers, projeção exata, privacidade, datas e indicadores PATCH.
- `tests/banco_brasil_mtls_transport.py`: handshake TLS local real com CA/cliente
  sintéticos; cliente válido alcança a evidência PHP, ausência de certificado e
  certificado fora da CA são recusados pelo TLS. Não testa o ingresso implantado.
- `tests/banco_brasil_api_amendment.php`: composição nativa SQL+API simulada,
  idempotência, A/B, confirmação por conta, resposta perdida, rejeição e revogação.
- `tests/banco_brasil_amendment_concurrency.php`: dois processos/conexões reais,
  lock observado, mesma chave/chaves distintas e revogação antes do envio.
- `tests/banco_brasil_webhook_mysql.php`: schema nativo, pins/GUI data, A/B,
  deduplicação/conflito, revogação, rollback e ausência de efeitos financeiros.

Os dois últimos dependem de banco descartável; resultados executados devem ser
registrados no commit efetivo. Teste de certificado/variável não comprova um
handshake mTLS real pelo ingresso, cadeia BB válida ou homologação externa.
Continuam externos: cadeia/pins oficiais por ambiente, contratação/configuração
da URL no BB, ingresso seguro, testes de entrega/retry real e homologação.
Continuam técnicos: demais alterações/perfis, encargos/parciais/estornos API,
transmissão CNAB contratada e operação CNAB400. Esses itens não são chamados de
“homologação” para ocultar código faltante.
