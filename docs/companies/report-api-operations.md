# Relatório financeiro e API por empresa

## Posição atual e CSV privado

`CompanyReports` e a tela selada `admin/company_reports` apresentam uma posição
financeira atual por moeda, sem conversão: faturas emitidas, recebimentos líquidos
atuais, créditos aplicados, saldo a receber, créditos emitidos/disponíveis,
reembolsos líquidos registrados e despesas/tributos registrados. Cancelamentos
permanecem contabilizados separadamente no histórico, sem entrar nos valores ativos.

O relatório exige simultaneamente `reports/view`, `invoices/view`, `payments/view`,
`credit_notes/view` e `expenses/view` na mesma empresa. Autorização é verificada antes
da hidratação e novamente sob lock. Todas as consultas partem de owner persistido;
a empresa principal não apropria linhas legadas `issuer_id=NULL`.

Os valores são centavos inteiros. Créditos e estornos são comparados às projeções
nativas; recebimentos são conferidos pelo recibo canônico, reconstruindo o valor
original a partir de estornos manuais antes de verificar seu hash. Divergências
impedem exibir totais ou exportar. CSV tem apenas dados numéricos e moedas validadas,
sem campos de texto do cliente que possam executar fórmulas de planilha. A auditoria
do hash do CSV precisa confirmar antes dos bytes. Não há URL pública.

Este relatório representa a posição líquida **atual**, incluindo estornos já
registrados. Não reconstrói fechamento histórico por período, fluxo de caixa,
resultado contábil, lucro ou escrituração fiscal. Despesa registrada não comprova
pagamento. Esses relatórios adicionais ainda precisam de implementação específica.

## Tokens exclusivos da API

A tela selada `admin/company_api_tokens` exige `company_api/manage`. Cada token é
vinculado de forma imutável à empresa e ao funcionário que o criou. Apenas os tokens
desse próprio ator/origem podem ser administrados. O segredo contém 256 bits
aleatórios e aparece somente na resposta original da criação/rotação; o banco
armazena SHA-256, identificador público, rótulo, scopes, datas e estado. Nenhum segredo
é gravado em sessão, auditoria ou log. Repetir a criação retorna o identificador e
não revela novamente o segredo. Perda da resposta exige rotação/revogação explícita.

Scopes disponíveis são uma lista fechada:

| Scope | Autorizações vivas exigidas |
|---|---|
| `invoices:read` | `invoices/view` |
| `invoices:pdf` | `invoices/view` |
| `invoices:create` | `invoices/view`, `invoices/create`, `customers/view` |
| `reports:read` | Todas as capacidades do relatório acima |

Além disso, `company_api/manage`, funcionário ativo e acesso ativo à empresa continuam
obrigatórios em cada uso. Perder uma capacidade impede o scope correspondente, mesmo
que ele continue listado no token. Token não herda empresas/recursos futuros ou todos
os poderes de um master. A empresa vem exclusivamente do token; IDs de recursos têm
owner conferido novamente. Cookies, seletor da GUI e tokens da API legada não
concedem autoridade nestes endpoints.

Validade entre 1 e 90 dias, datas UTC, até 20 tokens ativos não expirados por ator e
empresa. Rotação preserva empresa/ator/scopes e revoga o anterior na mesma transação
que cria o novo. Revogação é idempotente. Não existem endpoints públicos de criação
ou ampliação de scopes.

## Contrato HTTP v1 disponível

Todos exigem `Authorization: Bearer <token>`. Transportar somente por HTTPS nas
instalações de produção; configurar o proxy para não registrar cabeçalhos/segredos.
Não colocar token em URL, query string ou exemplos versionados.

| Método/caminho | Scope | Contrato |
|---|---|---|
| `GET /company_api/invoices` | read | Lista própria, cursor `?after=<id>`; próximo cursor no JSON |
| `GET /company_api/invoices/<id>` | read | DTO validado da fatura própria |
| `GET /company_api/invoice_pdf/<id>` | pdf | PDF binário privado e auditado |
| `POST /company_api/invoices` | create | JSON do contrato de `CompanyInvoices::create` e `Idempotency-Key` de 64 hex minúsculos |
| `GET /company_api/financial` | reports | Posição atual por moeda |
| `GET /company_api/financial?format=csv` | reports | CSV privado auditado |

POST aceita `application/json`, opcionalmente charset UTF-8, até 256 KiB e profundidade
32. Dados incluem `client_id`, `currency`, `date`, `due_date`, `items` e os campos
monetários/observação já suportados pelo writer nativo. Não aceita `issuer_id` ou
outros campos desconhecidos. A chave da API é derivada com empresa/ator antes de
usar o recibo durável do writer; permanece válida após rotação do token. Repetir com
payload idêntico retorna o mesmo ID e `replayed=true`/HTTP 200; criação nova retorna
201. Payload divergente com a mesma chave retorna 409.

JSON de sucesso: `{"api_version":1,"data":...}`. Erros são genéricos: 401 para
credencial ausente/inválida/revogada/expirada; 403 para scope/recurso/origem sem acesso;
409 para conflito idempotente; 422 para validação ou saldo exigindo revisão; 503 para
falha de infraestrutura. Nenhum erro devolve o corpo submetido ou o token.

A API possui bootstrap público próprio, restrito aos segmentos/métodos exatos acima,
sem módulos, sessão de funcionário ou contexto global. A exceção CSRF é somente o
POST bearer JSON canônico; não abre administração ou endpoints legados.

Criação passa pelo `CompanyInvoices::create` já existente, com guard de veto executado
sob o lock antes/depois de escrever. Relatórios executam o guard antes/depois da
montagem/exportação na mesma transação. Leitura/PDF usa a autorização do reader nativo
e auditoria revalidada antes de retornar dados. Revogação durante espera do lock
impede escrita/exportação; não basta autenticar um token uma única vez fora do writer.

## Evidência e limites

- `tests/companies/reports_financial.php`: valores A/B/principal/NULL, créditos,
  estornos, despesas, CSV auditado, negação sem capabilities e guard antes/depois.
- `tests/companies/api_tokens.php`: criação com hash, não reexibição, scopes, A/B,
  JSON/PDF/CSV, writer idempotente, rotação/revogação, expiração e grants revogados.
- `tests/companies/api_scope.php`: segmentos exatos, negação de prefixo admin e
  parâmetros extras, reset de estado estático.
- `tests/companies/api_concurrency.php`: duas conexões reais aguardando InnoDB;
  revogar enquanto aguardam veta emissão e exportação sem efeitos parciais.
- `tests/companies_reports_api_browser.cjs`: GUI de relatório/tokens e API HTTP real.

SQLite comprova regras/rollback com adaptador; não comprova engine, DDL ou locks.
MySQL/MariaDB, HTTP/Chromium e CI do SHA final precisam ser executados na matriz.
Não houve operação bancária, SMTP, homologação ou deploy nesta implementação.

A API não disponibiliza todos os domínios do CRM: edição/cancelamento de faturas,
pagamentos, créditos, despesas, portal e operações bancárias ainda não têm endpoints
públicos neste contrato. Fechamentos históricos, análises e exportações detalhadas
adicionais continuam pendências de código. A superfície legada permanece fechada.
