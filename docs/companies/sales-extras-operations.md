# Anexos e notificação de documentos de vendas

Extensão aditiva do fluxo `CompanySales`, sem adoção de documentos `NULL`, sem
rota pública de arquivo e sem segundo escritor financeiro. A migration integradora
chama `CompanySalesExtrasSchema::ensure` depois da base de vendas: duas tabelas
InnoDB (`company_sales_files`, `company_sales_deliveries`) e índice único de
propriedade para as FKs compostas `(issuer_id,document_id)`. O upgrade é aditivo;
rollback operacional futuro fecha as ações e conserva blobs, recibos e numeração.

## Anexos privados

No detalhe do orçamento/proposta (`admin/company_sales`), o funcionário autorizado
pode enviar PDF até 2 MiB ou PNG/JPEG até 512 KiB, limitado a 1600 pixels por
largura/altura. Imagens são validadas e regravadas em PNG pelo normalizador já
usado por faturas; metadados originais não são publicados. PDF é validado por
assinatura/MIME e disponibilizado como download com `sandbox`/`nosniff`; isso não
representa sanitização, análise antimalware ou garantia de PDF sem conteúdo ativo.

Cada arquivo tem bytes imutáveis, hash, tamanho, ator, empresa, pai e recibo.
Upload repetido com a mesma chave não duplica; conteúdo, pai, ator ou visibilidade
divergentes conflitam. Ocultar é uma ação auditada que conserva bytes/recibo e não
permite ressuscitar o arquivo por retry antigo. A quota inclui arquivos ocultos:
20 uploads e 20 MiB por documento no histórico. Não há purga para contornar quota.

O checkbox de publicação é explícito por arquivo e inicia desmarcado. No portal,
somente documento `accepted`/`converted` do cliente e empresa autenticados, com
permissão nativa 2 (orçamento) ou 4 (proposta), pode listar/baixar arquivos
publicados e ativos. O reader revalida o destinatário capturado, a origem e os
hashes do documento; a mera alteração de um campo relacionado não transfere o
arquivo. Lote de IDs com pai de outra empresa é recusado por inteiro.

Uploads, downloads, preview PNG e ocultação exigem o recurso específico do pai;
edição não é inferida de visualização. Download confere conteúdo e hash e confirma
auditoria antes dos bytes. Compartilhar identidade de cliente não compartilha
contatos ou anexos de outra empresa.

## Notificação privada

A ação “Agendar notificação privada” exige documento aceito/convertido, recurso do
tipo em `view/edit`, `customers/view`, cliente privado próprio ativo e contato
ativo com a permissão nativa do tipo. O mesmo e-mail em outra empresa não é um
destinatário alternativo. Documento para identidade compartilhada não autoriza
usar contatos do proprietário pela empresa destinatária.

O serviço usa `CompanyEmailTemplates::findForIssuer` e SMTP/configuração da
empresa efetiva. Os defaults existentes são `estimate-send-to-client` e
`proposal-send-to-customer`; o catálogo e customizações não são copiados da
principal. Os corpos de fábrica conhecidos são adaptados em memória para o link
do portal autenticado, sem afirmar envio de PDF em anexo ou comentários públicos.
Customizações próprias permanecem próprias. O template precisa estar ativo e
preservar seu campo `{estimate_link}` ou `{proposal_link}`.

Fila, recibo e auditoria são confirmados em uma transação, sem rede. A mensagem
contém link para login no portal, sem bearer, PDF público, CC/BCC ou anexo de
mensagem. A solicitação tem chave durável; limpar a fila não torna um retry antigo
uma nova notificação. Nova solicitação explícita usa uma nova chave.

O worker nativo `App_Email::send_queue` faz claim e revalida ator, empresa,
documento/revisão, contato, permissão e destinatário antes de inicializar o
transporte. Digests do conteúdo/envelope e da configuração SMTP impedem alteração
de mensagem ou troca de conta/segredo entre agendamento e envio. O header interno
`companySalesDelivery` é removido antes de SMTP. A configuração é reconstruída
pela origem persistida, sem sessão ou herança de outra empresa.

A GUI distingue fila, processamento, falha, aceite do transporte e registro
arquivado. Aceite SMTP não comprova chegada/leitura. Um crash depois do aceite
externo continua exigindo revisão; não se promete exactly-once externo. Testes
usam o worker de produção e capturam somente a chamada final de envio.

## Evidência e comandos

Executado localmente com PHP 8.3.6 e SQLite descartável:

```sh
python3 /tmp/run-crm-php-tests.py tests/companies/sales_extras.php
python3 /tmp/run-crm-php-tests.py tests/companies/sales_extras_worker.php
```

- `sales_extras.php`: 230 checks cumulativos; 158 da fixture/regressões anteriores
  e 72 da extensão. A/B/principal/legado, upload real de bytes, PDF/PNG, limites,
  quota histórica, hide, corrupção, auditoria, rollback/readback via trigger,
  permissão específica, destinatário capturado, configuração própria, idempotência,
  revisão obsoleta e rotação de SMTP.
- `sales_extras_worker.php`: 241 cumulativos, com mais 11 cenários do worker nativo,
  stripping de header, transporte por origem, replay, revisão obsoleta, revogação
  e envelope alterado; SMTP final capturado.
- Lint PHP e `node --check tests/companies_sales_extras_browser.cjs` executados.

Implementados para a integração executar nas matrizes reais:

```sh
php tests/companies/sales_extras_concurrency.php
node tests/companies_sales_extras_browser.cjs
```

A suíte de concorrência exige MySQL/MariaDB descartável e observa duas conexões
esperando o lock; cobre retry de upload/fila, disputa do último slot e revogação
na espera. A suíte Chromium roda na stack descartável após os testes de
vendas/portal existentes e cobre multipart, PNG/preview, download, publicação,
ocultação, 409 de aba antiga, IDs cruzados e fila/retry. Registre o SHA e resultados
efetivamente integrados antes de reivindicar execução desses cenários.

Nenhum SMTP externo, credencial real, transação bancária ou homologação bancária
foi executado por esta extensão. Notificação e anexos não representam assinatura
eletrônica nem aceite público do documento.
