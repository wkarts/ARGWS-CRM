# Orçamentos, propostas e recorrência por empresa

Os adaptadores `CompanySales` e `CompanyRecurring` usam tabelas próprias e o escritor
canônico `CompanyInvoices::create`; não invocam modelos, hooks ou transportes do
financeiro legado. Documentos legados com origem `NULL` não são adotados.

## Operação gráfica

- `admin/company_sales?kind=estimates`: emitir orçamento privado com cliente
  autorizado, moeda, itens, múltiplos tributos, desconto e ajuste. Numeração própria
  da empresa/série `EST`. O conteúdo emitido permanece imutável.
- `admin/company_sales?kind=proposals`: mesmo fluxo para propostas, série `PROP`.
- O operador registra aceite, recusa ou cancelamento de documento aberto. Esse
  aceite é uma decisão interna registrada e não uma assinatura eletrônica do cliente.
- Somente documento aceito pode converter em fatura. O serviço preserva a fonte,
  reserva intenção durável, chama o escritor canônico e confirma o vínculo. A ação
  de retomada reutiliza a mesma chave e não consome outra fatura/numeração.
- PDF privado é renderizado por TCPDF com texto escapado e dados da emissão;
  autorização e auditoria são confirmadas antes de enviar os bytes.
- O detalhe permite conceder/revogar acesso a contatos privados do cliente, usando
  permissões nativas 2 para orçamentos e 4 para propostas. O portal lista/baixa
  somente documentos `accepted`/`converted`, do cliente e empresa autenticados.
  Estado aberto, IDs cruzados, compartilhamento de identidade ou sessão antiga não
  concedem acesso. A decisão de converter continua exclusiva do funcionário.

Emitir exige os recursos `estimates` ou `proposals`, com capacidades `view/create`,
e acesso `customers/view`. Decisões exigem `edit`; cancelamento exige `delete`.
Conversão exige também `invoices/create` e o escritor exige `invoices/view`.
Administrar acesso do contato exige `customers/view` ou `edit`, o recurso do tipo
em `view` e propriedade privada do cliente. O destino de uma identidade compartilhada
não administra os contatos do proprietário.

## Recorrência

`admin/invoices/recurring` agenda entre 1 e 120 faturas, a cada 1 a 12 meses.
O vencimento preserva o intervalo em dias da primeira emissão. O dia original do
mês é uma âncora: 31/janeiro vira 28/fevereiro e depois 31/março; ano bissexto é
tratado pelo calendário. Cada ocorrência tem chave única por empresa/série/data.

A série persiste empresa, funcionário responsável, entrada monetária normalizada,
identidade, destinatário, moeda, configuração por digest e transporte escolhido.
O transporte é explicitamente `manual_notification`: a série não envia e-mail,
não cria assinatura bancária, boleto nem débito. Depois da emissão, o funcionário
pode abrir a fatura e usar a notificação privada já existente.

O worker `php index.php company_recurring_worker` descobre somente IDs/origens
persistidos, sem sessão, empresa padrão ou herança de outro tenant. A rota CLI
precisa do bootstrap mínimo `CompanyRecurringWorkerScope`; HTTP é recusado.
Cada emissão revalida grants, funcionário ativo, empresa, cliente e configuração.
Cada execução processa até 25 séries e uma ocorrência devida de cada série. Uma
série atrasada avança em execuções seguintes, evitando lote não delimitado.

O job persiste diagnóstico e pausa de segurança quando a origem ou autorização
muda. Pausa por revogação requer restauração legítima e retomada explícita. Mudança
na origem exige revisão e nova série; a anterior conserva seu histórico. Uma
intenção em `processing` conserva a chave e deve ser retomada antes de pausar.
A interface distingue intenção, fatura confirmada e tracking da ocorrência.

## Atomicidade e recuperação

Schema, ACL, numeração e estados são protegidos pelo lock nativo da instalação e
transações independentes. Não se introduz uma transação externa em torno do writer.
O parâmetro opcional `originGuard` de `CompanyInvoices::create` apenas veta uma
emissão: roda depois da autorização, antes dos efeitos e antes da confirmação,
inclusive em retries; não confere autoridade.

A conversão/recorrência usa uma intenção confirmada antes de invocar o writer.
Se a confirmação do vínculo falhar depois do commit da fatura, ela permanece
rastreável pelo recibo canônico. Retry confere empresa, ator e snapshot imutável
antes de finalizar o mesmo documento. Uma alteração de configuração posterior ao
commit não exige reemitir nem adulterar a identidade original. Uma chave previsível
pré-ocupada por fatura com snapshot diferente é recusada.

A migration integradora deve chamar `CompanySalesSchema::ensure`. São três tabelas
aditivas, InnoDB, UTF-8 binário, PKs/índices integrais, unicidade e FK composta da
ocorrência ao pai/empresa. `assertValid` confere tipos, defaults, engine, collation,
índices e FKs. Rollback operacional futuro deve fechar as rotas/job e preservar
essas tabelas/recibos; não apagar faturas, números ou histórico para tentar novamente.

## Evidência

Executado localmente com PHP 8.3.6/SQLite:

```sh
python3 /tmp/run-crm-php-tests.py tests/companies/sales.php
python3 /tmp/run-crm-php-tests.py tests/companies/portal_sales.php
```

`sales.php`: 139 checks cumulativos, dos quais 63 pertencem à fixture/regressão de
faturas e 76 cobrem vendas/recorrência/guardas. `portal_sales.php`: 158 cumulativos,
incluindo mais 19 cenários de portal/permissões/PDF. Cobrem A/B/legado `NULL`,
retries, conflito de entrada, decisões, conversão, calendário, pausa, worker sem
sessão, revogação, falha tardia, recuperação, origem divergente, pesquisa e PDF
real. Isso não comprova locks/engine MySQL/MariaDB ou HTTP real.

Implementados para execução pela integração:

```sh
php tests/companies/sales_concurrency.php
node tests/companies_sales_browser.cjs
```

A concorrência exige `ISSUERS_TEST_DSN` MySQL/MariaDB descartável, duas conexões e
observação real de espera no lock. Chromium exige a stack descartável de upgrade
com login/fixtures A/B, executado após as suítes existentes de portal/faturas.
A sintaxe JS foi validada; execução HTTP/Chromium e matrizes MySQL/MariaDB devem ser
registradas no SHA efetivamente integrado. Não houve e-mail externo, transação
bancária nem homologação externa nesta vertical.

A extensão de [anexos e notificação privada](sales-extras-operations.md) acrescenta
publicação explícita por arquivo e fila/worker com origem própria. Recorrência
permanece com notificação manual explicitamente escolhida. Assinatura eletrônica
do cliente e aceite público não são efeitos implícitos de emitir PDF ou registrar
decisão interna.
