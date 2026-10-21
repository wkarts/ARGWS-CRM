# Finance 1.0.1 e Accounting 1.3.6 — entradas nativas legadas

## Entrega e classificação

Este incremento corrige a seleção de faturas, pagamentos e despesas nativas em leitores específicos dos módulos Finance e Accounting. A principal conserva a leitura dos registros explicitamente legados. Registros empresariais da própria principal, de A ou de B não são adotados pelo módulo legado. IDs órfãos e origem ambígua também não são tratados como legado.

**Classificação: correção de preservação e isolamento de entradas legadas, com alcance limitado.** Não constitui implantação multiempresa integral de Finance ou Accounting. O bloqueio administrativo já existente nas empresas secundárias permanece uma proteção, sem ser contabilizado como funcionalidade entregue. Nenhuma opção de escopo, menu ou regra global é alterada por este recorte.

Fontes de produto: `modules/finance/libraries/FinanceLegacyScope.php`, `modules/finance/models/Finance_model.php`, `modules/finance/libraries/Finance_module.php`, `modules/accounting/libraries/AccountingLegacyScope.php`, `modules/accounting/models/Accounting_model.php`, `modules/accounting/controllers/Accounting.php` e as duas views `accounting/views/{bills,checks}/table_bills.php`.

## Reprodução anterior à correção

O teste executa o Query Builder nativo do CodeIgniter 3 com PDO/MySQL e modelos reais, em banco sintético exclusivo. A fixture contém registros legados, empresariais da principal/A/B, `issuer_id=0`, referências de faturas inexistentes e históricos contábeis existentes. Não há serviços bancários ou produção envolvidos.

Na primeira execução RED, 16 das 18 verificações falharam. O Finance retornou receita paga de 1500, despesa de 450 e previsão de 2460, quando os respectivos conjuntos legados válidos totalizam 100, 160 e 60. A seleção para conciliação agregou 18 IDs, em vez dos 5 legados válidos. Accounting listou 9 faturas e 10 pagamentos em vez de 2 e 2; a contagem de pagamentos incluía um órfão. A consulta direta de uma despesa empresarial retornou dados.

Uma reprodução adicional isolou efeitos em Accounting: as conversões automáticas de fatura, pagamento e despesa apagaram o histórico existente antes de tentar carregar o modelo nativo. A fixture interrompe intencionalmente essa carga posterior e confere o banco: os três históricos já tinham desaparecido. O callback de alteração de status também mudou o marcador `paid` do histórico empresarial de 0 para 1. O RED válido é `modules-accounting-side-effects-red2-maria`; a tentativa anterior, com metadados incompletos da fixture PDO, não é considerada prova de defeito do produto.

As observações completas, hashes e contagens constam em `docs/companies/evidence/finance-accounting-454.json`. Os testes reproduzíveis ficam em `tests/companies/legacy_financial_module_{fixture,readers,tables,migrations}.php`.

## Contrato da origem

| Entrada nativa | Regra aplicada |
| --- | --- |
| Fatura | `issuer_id IS NULL` quando a coluna existe; zero e IDs empresariais são excluídos. |
| Pagamento | Exige fatura existente e explicitamente legada por `EXISTS`; um `LEFT JOIN` sem correspondência não concede acesso. |
| Despesa | Aplica `CustomerRelationBoundary` ao cliente e exige que uma fatura vinculada não nula/não zero exista e seja explicitamente legada. Despesas legitimamente não vinculadas continuam admitidas. |
| Mapeamento de conta a pagar | Exige a despesa correspondente e a mesma origem legada. |
| Schema parcial | Existência de registry, ownership ou links sem coluna de origem necessária não autoriza fallback global. A consulta não retorna registros ambíguos. |
| Instalação genuinamente anterior à fundação | Sem coluna de origem e sem nenhuma tabela da fundação, preserva o comportamento legado de seleção, ainda exigindo existência das referências vinculadas. |

Os helpers validam prefixos e aliases SQL. As consultas modificadas usam `db_prefix()`. O teste repete as provas com `tbl` e `module454_`. Isso não afirma que todos os demais caminhos dos módulos já suportam prefixo configurável: caminhos antigos fora do recorte ainda contêm nomes `tbl` fixos.

O helper não concede autorização pelo contexto de sessão. Uma empresa selecionada não transforma dados de origem privada em dados legados. Os helpers são independentes entre módulos, sem dependência do Finance sobre Accounting ou vice-versa.

## Superfícies modificadas

### Finance

Os leitores nativos de `Finance_model` cobrem a seleção de faturas/despesas para conciliação, previsão de faturas, despesas, soma de receita paga, soma de despesas, faturas pagas transformadas em entradas e previsão por dia nos próximos 30 dias. `Finance_module` aplica a mesma origem ao agregar faturas e despesas. A ordem `FROM` antes de `JOIN` preserva os aliases sob prefixo configurado no Query Builder real.

A tabela própria de lançamentos do Finance, contas bancárias, categorias, OFX, criação manual e gravação de conciliação não são particionadas por este incremento.

### Accounting

Os predicados foram aplicados à seleção de faturas, indicadores de conversão, parcelas de faturas/despesas dos gráficos, contagens de itens ainda não convertidos, relatórios de vencimento de contas a receber/pagar e listas/detalhes de despesas usadas como bills. As seis seleções nativas DataTables cobertas são `sales_table`, `sales_invoice_table`, `expenses_table`, `bill_payment_information_table` e as views de bills e checks.

As três conversões automáticas e `invoice_status_changed` validam a origem antes de apagar ou alterar histórico. A seleção de mapeamentos e os detalhes de pagamento de bill exigem a origem da despesa. Operações diretas de aprovação, alteração de status, void, criação/edição, exclusão e exclusão de anexos de bill recebem a mesma guarda de entrada. Criação/edição validam também as novas referências de cliente e fatura. O upload administrativo de anexo de bill exige a permissão `accounting_bills/edit` e a origem antes de chamar o helper de arquivo.

Essas guardas rejeitam IDs empresariais antes dos efeitos observados. Elas **não convertem os escritores legados em transações empresariais completas** com revisão, idempotência e conferência final de todas as referências.

### Preservação da limpeza de históricos

`check_transaction_exists`, `remove_invalid_entries`, `remove_wrong_mapping` e as rotinas genéricas de exclusão/conversão não recebem um filtro que faça um registro empresarial existente parecer inexistente. Isso evitaria um vazamento de leitura à custa de apagar seu histórico, o que seria incorreto.

A prova executa a limpeza real com históricos de fatura, pagamento e despesa empresariais existentes, um registro legado e um órfão. Somente o órfão é removido; os quatro históricos válidos permanecem com os mesmos bytes. A guarda das entradas novas ocorre antes da conversão, sem reclassificar nem apagar históricos existentes.

## Versionamento e migração

| Módulo | Marcador de origem | Versão no header | Migration oficial |
| --- | --- | --- | --- |
| Finance | 1.0.0 / 100 | 1.0.1 | `101_version_101.php` |
| Accounting | 1.3.5 / 135 | 1.3.6 | `136_version_136.php` |

As duas migrations são NOOP explícitas. A alteração é de código; não há DDL, backfill, adoção de dados, reclassificação, recálculo, reescrita de livro ou limpeza financeira. O teste usa `App_modules::get_headers`, `upgrade_database` e `App_module_migration` nativos com o adaptador de metadados de módulo da fixture, lê a versão de destino e repete a operação. Um trigger real que ignora a atualização de versão deve produzir `module_migration_version_unconfirmed`; o marcador antigo e os dados são preservados, e a repetição após retirar o trigger conclui corretamente.

Os instaladores originais completos dos módulos em banco vazio não foram executados nesta prova. O teste de migração cobre os marcadores existentes 100 e 135 e a preservação das tabelas de negócio sintéticas.

## Validação executada

| Suíte | MariaDB 10.11.14 + PHP 8.3.6 | MySQL 8.0.46 + PHP 8.1.2 | Alcance |
| --- | ---: | ---: | --- |
| `legacy_financial_module_readers.php` | 122/122 | 122/122 | Positivos legados, principal privada/A/B, órfãos, callbacks antes dos efeitos, arquivos por guarda de ID, limpeza preservada, prefixos e schema parcial. |
| `legacy_financial_module_tables.php` | 12/12 | 12/12 | Seis seleções reais de DataTables, repetidas com dois prefixos. |
| `legacy_financial_module_migrations.php` | 24/24 | 24/24 | Headers, upgrade nativo, replay, trigger de escrita ignorada, readback e preservação de dados. |
| Total | **158/158** | **158/158** | **316 verificações executadas**, sem SUPER no usuário de teste. |

Todos os 16 arquivos PHP do recorte passaram na análise sintática com PHP 8.1. Os dois motores são instâncias locais sintéticas independentes. MySQL usa binlog e permite triggers na instância de teste; a permissão não é uma configuração de produção entregue pelo produto.

As provas DataTables executam os métodos de controller/views e o `data_tables_init` real até o hook imediatamente posterior ao SELECT. Esse hook captura as linhas e interrompe o restante. Há doubles de input, loader, idioma e autorização de visualização. Portanto, elas não são prova de HTTP, autenticação/ACL real, contagens paginadas ou renderização HTML. Nenhum browser dos módulos foi contabilizado como executado.

Para reproduzir, forneça `ISSUERS_TEST_DSN`, `ISSUERS_TEST_USER` e `ISSUERS_TEST_PASSWORD` de **um banco sintético descartável exclusivo** e execute os três testes PHP sequencialmente. A fixture cria/remove suas tabelas; não deve ser usada em banco operacional nem compartilhada em paralelo com outra suíte.

## Pendências que permanecem internas

Finance ainda necessita origem persistida e operações empresariais completas para lançamentos, categorias, contas bancárias, importação OFX/CSV, conciliação, relatórios sobre seu livro, arquivos, jobs, API e configuração por empresa. Cada leitor e escritor precisa da cadeia ownership/ACL/transação/revisão/idempotência/readback e das provas positivas A/B/principal.

Accounting ainda necessita adaptação do plano de contas, livros, lançamentos manuais, fechamento, reconciliação, contas bancárias, pay bills compostos, cheques/anexos, importação/exportação, relações com Purchase/estoque/projetos e configuração/consumidores de integrações. Filtrar as entradas nativas desta entrega não atribui origem aos dados existentes dessas estruturas nem resolve seus escritores compostos.

Essas são pendências de implementação e prova locais, **não dependências de documentação bancária externa**. Conexões reais com bancos, Plaid ou outros provedores podem exigir contrato, ambiente, credenciais e homologação próprios; nenhuma conexão foi tentada e nenhuma certificação externa é alegada por este incremento.
