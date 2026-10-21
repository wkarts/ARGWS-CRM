# Despesas próprias por empresa

`Company_expenses` é uma tela administrativa selada, acessada com o contexto da
empresa ativa. O fluxo não carrega `Expenses_model`, hooks de módulos, uploads
legados, pagamentos ou permissões globais. O controlador requer o selamento do
`CompanyRequestScope`; registrá-lo não autoriza abrir o controlador legado.

## Contrato entregue

- Listar, buscar descrição/categoria, paginar por chave, consultar por ID e criar
  despesas para a empresa A, B e principal. `expenses/view` é exigido em todas as
  operações; criar exige `expenses/create`, editar `expenses/edit` e cancelar
  `expenses/delete`. Ser administrador global não substitui concessões por origem,
  exceto o master explicitamente vinculado da instalação conforme política do núcleo.
- Valores decimais de entrada são convertidos diretamente em centavos inteiros.
  Valor base positivo até 999.999.999,99, alíquota adicional entre 0 e 100%, com duas
  casas e arredondamento half-up. Moedas explícitas BRL, USD e EUR; sem conversão.
  Total é base mais tributo. O campo não representa retenções ou apuração fiscal.
- Categorias próprias por origem, criação/edição/arquivamento e nome único dentro
  da empresa. Categoria arquivada continua legível no histórico e impede novos
  registros/edições que a selecionem. Até 500 categorias por empresa.
- Cliente opcional exige `customers/view` e identidade pertencente ou explicitamente
  compartilhada com a empresa. Projeto opcional exige `projects/view`, origem
  persistida igual, cliente selecionado igual ao cliente do projeto e estado `active` em `company_projects`. Nomes são capturados
  como snapshots na despesa. Nenhuma busca em tabelas financeiras de outra origem.
- Edição conserva número e moeda. Número é sequencial por empresa e nunca reutiliza
  um registro cancelado. Cancelar conserva todos os valores e torna o registro
  imutável. O cancelamento não executa estorno ou baixa bancária.
- Cada criação/edição/cancelamento/categoria exige chave de requisição de 64
  caracteres hexadecimais. Um replay idêntico retorna o resultado original;
  reutilizar chave para outro ator/origem/operação/payload conflita. Formulários
  exibem revisão e rejeitam gravação baseada em estado desatualizado.
- Comprovantes PDF até 2 MiB ou PNG/JPEG até 512 KiB e limites de imagem do núcleo.
  Imagens são decodificadas e regravadas em PNG. Limites cumulativos de 20 uploads
  e 20 MiB por despesa incluem arquivos ocultos. Blobs são privados e imutáveis em
  `company_expense_files`; chave não é token de autorização e não aponta caminho
  público. Download exige `expenses/view`, origem e pai corretos, hash íntegro e
  auditoria confirmada antes dos bytes. Ocultar exige edit e conserva histórico;
  replay do upload não reativa arquivo oculto. Sem publicação no portal.

`CompanyExpenseSchema::ensure` cria exclusivamente tabelas novas `company_expenses`,
`company_expense_categories`, `company_expense_requests` e `company_expense_files`.
A migration aditiva do núcleo deve executá-lo depois do schema de projetos. Não
alterar migrations históricas ou adotar registros legados. O schema valida tipos,
nullability/defaults, collation, índices, FKs e engine InnoDB na execução real.

Writers usam `IssuerTransaction`, lock do núcleo e revalidação de identidade/ACL
após espera. Despesa, revisão, recibo de idempotência e auditoria são uma unidade
atômica; falha em qualquer escrita impede sucesso. Transações externas são
recusadas; não há commit/rollback da transação de um chamador. Arquivos têm a mesma
fronteira de autorização, transação e auditoria, sem chamadas externas.

## Verificação

- `tests/companies/expenses_operations.php`: teste positivo de criação/edição/
  cancelamento/categorias/cliente/projeto, valores e arredondamento, A/B/principal,
  preservação de linhas legadas NULL, recusas por origem, permissões/revogação,
  revisão, replay, falha de auditoria/recibo e propriedade da transação.
- `tests/companies/expenses_files.php`: inclui o anterior e testa comprovantes,
  integridade/reencode, upload idempotente, acesso por pai/origem, limite cumulativo,
  ocultação, ausência de reativação e falha de auditoria no download/upload.
- `tests/companies/expenses_concurrency.php`: usa banco MySQL/MariaDB descartável e
  espera real observada em InnoDB; cria concorrente com a mesma chave, edição por
  revisão e revogação de funcionário enquanto os writers aguardam o lock.

SQLite valida serviços e rollback com adaptador; não prova a DDL/locks do MySQL.
O teste concorrente exige execução real no pipeline ou runtime MySQL/MariaDB.

## Limites explícitos

Despesas são registros locais privados. Não executam pagamento bancário, CNAB de
pagamentos, contas a pagar, conciliação, estorno, retenções, recorrência, conversão
em fatura ou contabilidade. Esses recursos continuam faltantes neste domínio.
Não há homologação externa a alegar para as operações locais entregues.
A busca de clientes/projetos no formulário exibe até 100 opções autorizadas; uma
referência prévia indisponível precisa ser removida ou substituída explicitamente.
Relatórios, APIs e leitores legados permanecem bloqueados até sua implementação
isolada. O novo ledger não aparece automaticamente nos relatórios legados.
