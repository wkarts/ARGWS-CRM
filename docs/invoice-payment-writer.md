# Escrita local coordenada de recebimentos e créditos

Esta alteração é incremental sobre o core da PR de origem Asaas. Não depende da fase de emitentes nem altera o modelo de identificação de uma instalação.

## Contrato

- `Payments_model::add` e `add_batch_payment` capturam a fatura antes de `before_payment_recorded`. O filtro não pode trocar o destino; todos os destinos do lote são conferidos antes da primeira inserção.
- `InvoicePaymentWriter` abre uma transação própria e trava a fatura com `SELECT … FOR UPDATE`. Relê estado, total, recebimentos e créditos e calcula centavos inteiros conforme `DECIMAL(15,2)`. Faturas não elegíveis ou saldo insuficiente recusam a escrita.
- `Credit_notes_model::apply_credits` usa a mesma trava de fatura, trava também a nota de crédito e verifica cliente, moeda, saldo aplicado e reembolsos já registrados. Emitente positivo é recusado nesta etapa; a ausência da coluna conserva o escopo legado.
- Os filtros `before_invoice_payment_insert_allowed` e `before_invoice_credit_insert_allowed` recebem `(true, invoice, record)` dentro da trava. São vetos: somente `true` estrito permite prosseguir; não devem executar efeitos, rede ou modificar registros. O módulo Asaas recusa inserções genéricas para uma origem Asaas conhecida.
- Commit confirmado precede os efeitos existentes do core: atualização de estado, PDF, notificações, e-mail e hooks posteriores. O gravador canônico Asaas continua separado, com outbox transacional e sem executar esses efeitos inline.
- `before_invoice_payment_mutation_allowed(true, receiptId, invoiceId, operation)` impede alteração/exclusão genérica de um recibo referenciado pelo outbox Asaas. Prova inconsistente também exige revisão; o usuário recebe aviso. Registros legados sem esse vínculo mantêm a política existente.

## Pré-condições e falhas

Requer MySQL/MariaDB, tabelas envolvidas InnoDB, autocommit ativo e `completion_type=NO_CHAIN`. Recusa uma transação externa/nested em vez de executar hooks antes do commit real. Além da profundidade do driver e de `PDO::inTransaction`, um `SET TRANSACTION ISOLATION LEVEL REPEATABLE READ` sem escopo SESSION detecta uma transação SQL externa sem implicitamente confirmá-la. O gravador só desfaz a transação que abriu.

Uma inserção ou commit não confirmado retorna falha e não dispara efeitos posteriores. Não há retry automático genérico: uma falha de confirmação ou um efeito posterior interrompido exige conferir o registro antes de nova tentativa.

O lote conserva resultados por item; não é uma transação única para todo o lote. A pré-validação inicial cobre os destinos; uma regra financeira, campo inválido ou saldo concorrente pode recusar um item enquanto outros já confirmaram. O total retornado conta apenas inserções confirmadas.

## Limites explícitos

O bloqueio coordena estes caminhos do core e o gravador canônico. Não cobre escritores externos que ignorem a trava, SQL direto, todos os módulos de terceiros ou uma reimplementação geral de reembolsos/estornos. A edição de valores de recibos manuais e o gravador legado de reembolsos de notas de crédito não foram migrados para esta trava; por isso não se declara fechamento universal das corridas entre escritores genéricos. Não habilita liquidação parcial automática, parcelamento 1:N ou reversão financeira de um recibo canônico.

## Evidências

- `tests/asaas_payment_writer.php`: valores exatos, estado/saldo sob trava, créditos, veto, transação externa e rollback.
- `tests/asaas_payment_writer_entrypoints.php`: executa métodos reais; substituição do destino, falhas de insert/commit, veto de edição/exclusão e primeiro efeito somente após commit.
- `tests/asaas_payment_writer_mysql.php`: duas conexões/processos em MySQL descartável; manual e crédito em ambas as ordens contra canônico, mais rollback e transações externas. A execução é exigida no job MySQL do CI, não simulada como validação real local.

Referências técnicas: [MySQL: SET TRANSACTION](https://dev.mysql.com/doc/refman/8.4/en/set-transaction.html), [MariaDB: SET TRANSACTION](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/set-commands/set-transaction), [PHP: PDO::inTransaction](https://www.php.net/manual/en/pdo.intransaction.php).
