# Importação revisada e exportação de clientes privados

A revisão2 pede fluxos positivos para importadores/exportadores (seção5, “Projetos, tarefas, despesas e demais consumidores”). A importação `CompanyImports` é exclusiva de despesas. Clientes usam agora a rota oficial `Clients::import` com o writer transacional de identidades privadas; o importador global `Import_customers` permanece somente no caminho histórico da instalação principal.

## Operação

Rota administrativa exata: `admin/clients/import`, método `import`, selada por `CompanyRequestScope`. A página oferece upload CSV/JSON, preview obrigatório, confirmação de lote inteiro, reabertura do resultado e exportação paginada de identidades próprias. Não cria contatos, logins, permissões do portal, compartilhamentos ou mensagens. A política de CPF/CNPJ e de documento vazio permanece a do diretório `CompanyClients`.

Até **100 clientes e 256 KiB**, UTF-8. CSV com ponto e vírgula, aspas duplas com duplicação e cabeçalho exato:

```text
company;vat
```

JSON: lista de objetos com `company` obrigatório e `vat` opcional. Ambos são texto; CPF/CNPJ numérico JSON é recusado para preservar dígitos/zeros. Nome é normalizado e limitado pelo mesmo writer existente. CPF/CNPJ é validado/normalizado pela política atual; vazio mantém a possibilidade de identidades distintas. Documento não vazio duplicado no arquivo, em outra empresa ou no legado gera apenas conflito genérico, sem divulgar nome, ID ou proprietário em conflito. Não aceita IDs, owner, e-mail, dados de contato, campos extras, fórmulas ou controles.

Uma nova chave e uma nova confirmação representam uma nova solicitação: não há deduplicação artificial de identidades com documento vazio. Retry usa o lote e a chave duráveis, inclusive quando o cliente não tem documento.

## Preview e confirmação

Importar exige simultaneamente `imports/manage`, `customers/view` e `customers/create` na empresa. Preview e resultado pertencem ao ator e à empresa persistidos, inclusive para um master consultando o lote de outro funcionário. São até dez previews válidos por ator/empresa, com expiração em uma hora. O arquivo original não é guardado; dados normalizados, hash da origem, cabeçalho/revisão e datas são privados. Auditoria guarda contagens e hashes, sem nomes/documentos.

Preview chama `CompanyClients::previewInOwnedTransaction`, que usa o mesmo normalizador e verificação global de duplicidade do writer. Não consome ID, não cria cliente nem adota identidade existente. A GUI mostra todas as linhas antes da confirmação. Dados e grants são revalidados sob o lock antes do primeiro efeito.

`CompanyClients::create` e `createInOwnedTransaction` delegam ao mesmo escritor interno; não há segundo writer SQL para clientes. Cada linha recebe chave derivada de lote/índice. Clientes, owners, recibos, auditorias e confirmação final do lote compartilham uma única transação própria. Transação arbitrária do chamador continua recusada. Uma falha na segunda linha ou na auditoria final reverte o lote inteiro, que pode ser retomado com a mesma chave após a causa ser resolvida.

Resultado confirmado preserva IDs e comprova recibos canônicos, payload/ator/origem e owner atual. Abrir ou repetir um lote com recibo ausente não recria o cliente. Mudança de owner ou adulteração da chave/payload do lote falha fechada. Nome/identidade editados legitimamente depois da importação não alteram o recibo original do lote. Não se apagam históricos para tentar novamente.

## Exportação

`customers/view` permite listar/exportar identidades **pertencentes** à empresa, sem exigir poder de criação/importação. Identidades recebidas por compartilhamento não são incluídas automaticamente neste exportador. O diretório normal mantém sua política de leitura das identidades compartilhadas.

Paginação por chave de até100 registros, com `after=0` aceito tanto como inteiro quanto como query HTTP. CSV privado contém `issuer_id;client_id;company;vat;active`; não tem contatos, destinatários ou finanças. Campos potencialmente interpretados como fórmula recebem apóstrofo. O arquivo de exportação contém colunas de identificação adicionais e não é um arquivo de importação automática; para criar novas identidades, usar somente `company;vat` e revisar o preview. Preservar CPF/CNPJ como texto ao abrir em planilha.

A auditoria do hash/cursor/escopo precisa ser confirmada antes dos bytes. Não há URL pública, cache compartilhado ou adoção de clientes sem owner. Principal cria identidades explicitamente próprias; não apropria o histórico NULL.

## Integração nativa

- `CompanyClientImportSchema::ensure($db,$prefix)` cria somente `company_client_import_batches`, depois dos schemas nativos do núcleo/recibos de clientes. InnoDB, campos/defaults/collations, índices integrais, unicidade e FK de empresa são validados. Reexecução é segura; os testes recusam índice prefixado e default textual indevido.
- `CompanyClients::tables(bool $receipts=true)` fornece os participantes; `previewInOwnedTransaction` e `createInOwnedTransaction` exigem a fronteira própria de `IssuerTransaction`. A auditoria do writer confirma seu readback.
- Wiring integrador: adicionar somente `clients/import` ao selamento nativo, navegação condicionada a `customers/view`, e executar o novo ensure na migration futura. Nenhuma abertura genérica de rota, importador legado ou token é necessária.
- Não alterar migrations históricas. Futuro rollback operacional fecha a rota e conserva clientes/recibos/lotes; não apaga clientes importados ou suas relações.

## Evidência

Executado em cópia isolada, PHP8.3.6:

| Suite | SQLite | MariaDB10.11.14 |
|---|---:|---:|
| `tests/companies/client_imports.php` |101 cumulativos |104 cumulativos |
| `tests/companies/client_imports_controller.php` |115 cumulativos |118 cumulativos |
| `tests/companies/client_imports_concurrency.php` |não aplicável |116 cumulativos |

Os números incluem a fixture/regressão de clientes existente; não são casos independentes somáveis. MariaDB usa porta/datadir próprios48324, sem tocar ambientes dos demais executores. A concorrência observa duas conexões aguardando InnoDB e prova confirmação única, revogação de grant e mudança de owner do lote durante a espera. SQL com trigger real prova falha na segunda linha e na auditoria final, rollback total e retomada. Há prova de A/B/principal/NULL, VAT vazio, conflitos globais genéricos, metadados do schema, quota, expiração, paginação, CSV e auditoria antes dos bytes. O controller e as views reais são exercitados com I/O de framework sintético.

Regressões SQLite preservadas: diretório46, valores de fatura27, importação de despesas103, Asaas positivo117 e portal37. Lint PHP e `node --check tests/company_client_imports_browser.cjs` executados. A suite Chromium está implementada para upload multipart CSV/JSON, preview sem criação, confirmação/retry, CSV A/B, URL cruzada e contexto obsoleto; ainda exige execução na stack nativa após o wiring/migration da composição integradora. Não apresentar teste sintético de renderer como navegador real. Nenhum SMTP, provider ou operação de produção foi utilizado.
