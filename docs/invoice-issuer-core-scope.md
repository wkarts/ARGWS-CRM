# Barreiras de escopo no core de faturas

Esta entrega é uma etapa de isolamento, não a liberação operacional multiemitente. O cadastro `issuers` é essencial e não desativável desde1.0.2; `issuers_assert_operational_scope()` permanece fechado. Nenhuma opção, sessão, empresa padrão ou vínculo do cliente habilita emissão para outra empresa.

## Contrato de propriedade

- `invoices.issuer_id IS NULL`: somente o histórico e os fluxos legados. Não representa uma empresa cadastrada e não pode ser inferido do cliente.
- `issuer_id` positivo: propriedade explícita, sem coerção de `0`, booleanos ou valores inválidos. O predicado SQL é parametrizado e exige um alias validado.
- `InvoiceIssuerScope` representa um valor de escopo, não uma concessão de permissão. `forIssuer()` não autoriza consultar ou cobrar; não há endpoint operacional que o exponha nesta etapa.
- Escritores legados recusam propriedade positiva antes de alterações e novamente depois dos hooks. O campo não pode ser alterado pela edição normal.
- Antes da migration, os predicados legados são `1=1`, preservando a instalação existente. Depois dela, o mesmo endpoint passa a aceitar exclusivamente `NULL`.

## Banco e ordem da integração

A migration core **384** adiciona `issuer_id INT UNSIGNED NULL DEFAULT NULL` e índice `(issuer_id,id)`. É idempotente e não altera valores históricos. O instalador novo contém a mesma coluna/índice. A propriedade financeira não depende do estado ou disponibilidade do cadastro; não há backfill nem interpretação automática de NULL como emitente principal.

A integração exige a migration **383** de recorrências antes de avançar o marcador de release para 384. O arquivo isolado sobre uma base 382 não é uma atualização implantável. A branch final deve incluir/rebasear 383, executar a sequência completa e seguir o processo de release do repositório. Não subir o marcador para ignorar a dependência. A composição local inclui explicitamente PR38 essencial102 (22473c18) e PR41 (a170c4e0), com o marcador384; isso não substitui revalidar a sequência de releases no main antes da publicação.

`down()` preserva os metadados. Apagá-los reinterpretaria faturas nomeadas como legadas. Reverter somente o código para uma versão sem filtros também pode expor esses registros; não é um rollback seguro após operações multiemitente. Nesta etapa nenhuma fatura nomeada é criada pelo aplicativo.

## Fronteiras implementadas

- `Invoices_model`: listagem/busca por ID, totais e opções de filtro; cancelamento, edição, exclusão, cópia, envio, atividade, anexos, itens, filhos recorrentes e numeração legada. Condições de busca com `OR` ficam agrupadas sob o predicado de propriedade.
- Revalidação de merge/`is_recurring_from` e de itens existentes/removidos impede usar uma fatura legada como entrada para IDs pertencentes a outra fatura. O teste inclui referências X, Y e inexistentes.
- `Payments_model`: lista/ID, transação, criação, edição, exclusão, envio de recibo e entrada de gateway. A propriedade é verificada antes de hidratar dados ou chamar provedor e depois de hooks relevantes. Pagamentos órfãos são ocultos após a migration.
- Alterar `invoiceid` de pagamento existente é recusado. Um lote com alvo não legado é recusado antes de sua primeira gravação; esta validação **não transforma o lote legado em transação atômica**. Hooks e falhas posteriores ainda exigem o tratamento legado de resultados parciais.
- Helpers de visualização e saldo, atualização de status, modos de pagamento, impostos de itens, última fatura e percentuais não abrem o escopo de outra empresa. Uma permissão global de administrador não remove o predicado.
- Tabelas administrativas de faturas/pagamentos/recorrências, contadores do portal e exportação GDPR recebem filtro. A subconsulta da última ocorrência também é filtrada.
- Busca global de faturas/tags, pagamentos, itens e campos personalizados de fatura filtra `NULL`, incluindo referências órfãs. Calendário, gráfico do portal, filhos de assinatura, relatórios HTTP de clientes/faturas/itens e seletor de impostos de faturas recebem o mesmo predicado. A busca ainda pode projetar dados de cliente adotado por empresa; a proteção completa desse cadastro depende de `ClientIssuerScope`, em implementação separada.
- Exportações CSV de pagamentos, PDF em lote de faturas/pagamentos e consulta de exportação eletrônica selecionam somente faturas `NULL` antes da renderização. CSV de despesas omite vínculos com faturas nomeadas/órfãs após a migration, preservando despesas não faturadas; isso não atribui emitente à própria despesa. A seleção SQL foi testada; criação completa de arquivos/ZIP continua pendente.
- Cron legado filtra a seleção de matrizes recorrentes, os dois lotes de avisos e a fila de e-mails agendados antes de consultar status ou hidratar conteúdo; cron não adota uma empresa a partir do cliente.
- Agendamento de e-mail de fatura valida o alvo antes de criar, consultar ou editar; o relacionamento de agendamento existente não pode ser trocado por POST. O controlador recusa ID oculto e tipo de relação diferente antes de construir a tela ou gravar.
- API legada: listagem/busca por tags de faturas, ID/listagem/busca com `OR` de pagamentos e verificação de numeração respeitam `NULL`.
- Entradas HTTP que gravavam prefixo, pausa de avisos, créditos, anexos e visibilidade diretamente passam a validar o alvo antes dos efeitos. A página pública revalida a identidade depois dos hooks. Ocultar o pagamento resulta em 404 nos controladores de PDF/envio.
- Anexos de faturas: download por chave, preview por caminho, upload antes de hook externo, registro de anexo externo e listagem agregada do cliente recebem barreira. O preview resolve o caminho e recusa travessia para fora da aplicação.
- Extrato do cliente filtra linhas/saldos de faturas e pagamentos, inclusive saldo anterior, e referências de créditos aplicados. `Credit_notes_model` bloqueia aplicar/derivar/remover crédito de fatura nomeada e valida os IDs da alocação armazenada, sem confiar no `invoice_id` fornecido. O domínio das próprias notas de crédito ainda não é multiemitente e seus totais/estornos não são reclassificados nem inferidos.
- Consultas de receitas em `Dashboard_model`/`Reports_model`, filtros de anos e presença de modos de pagamento recebem predicado legado. Não se afirma isolamento de despesas, créditos ou estornos, que ainda têm domínio próprio sem `issuer_id`.
- `Clients_model` limita a propagação de endereço às faturas legadas e recusa exclusão/GDPR do cliente enquanto houver qualquer fatura nomeada. As rotinas de anonimização/remapeamento também são filtradas. Isso protege a referência financeira; não implementa a privacidade integral do cadastro.
- Quando a classe de lock da fase 383 está presente, edição de matriz recorrente existente ou ativada pela requisição usa o mesmo `RecurringInvoiceLock` do scheduler/importação durante toda a edição. A classe não é duplicada nesta entrega. Mudanças de recorrência injetadas silenciosamente por extensões em uma edição inicialmente não recorrente precisam de revisão adicional.

Não se afirma cobertura integral de todos os métodos, extensões ou objetos relacionados. Por exemplo, créditos, despesas/tarefas, privacidade integral de clientes, outros relatórios/dashboards e controladores com SQL próprio, extensões de pesquisa global, controladores que gravam diretamente, cron/provedores externos e importadores ainda precisam de auditoria e testes específicos. Propriedade de uma fatura não torna o cadastro do cliente privado no core.

## Inventário executável

`python3 scripts/audit-issuer-isolation.py` verifica que o inventário JSON corresponde aos arquivos atuais. Um arquivo novo, removido ou modificado exige revisão; `--write` atualiza hashes e reinicia a classificação dos arquivos alterados para `unreviewed`.

As classificações `partial`, `closed_legacy`, `closed_scoped` e `not_applicable` devem apontar evidência. Fechar uma parte de um arquivo não fecha o arquivo inteiro. A busca é lexical conservadora: localiza SQL/modelos, pagamentos, clientes, documentos e numeração em `application` e `modules`. Não é parser SQL, prova de autorização ou grafo completo de chamadas. SQL dinâmico, aliases e extensões podem escapar do inventário. As exclusões (vendor/assets/language/migrations/install) estão registradas explicitamente.

`--assert-ready` sempre falha nesta versão, mesmo se alguém marcar todos os arquivos como revisados. Inventário atualizado não é autorização de rollout. A abertura exige implementação, revisão independente e testes de todos os caminhos e permissões, inclusive cenários sem sessão (cron/webhook/portal/API).

## Evidência de teste

- `tests/invoice_issuer_scope.php` executa métodos reais dos modelos/helpers e produtores de tabela/exportação contra fixtures sintéticas PDO. Três faturas compartilham cliente: legado `NULL`, empresa X, empresa Y. As tabelas executam o `WHERE/JOIN` efetivamente produzido, não o render visual ou o DataTables completo.
- `tests/api_issuer_scope.php` executa métodos reais da API com a mesma separação, pagamentos órfãos e buscas `OR`. Nenhuma conexão de provedor é feita.
- `tests/invoice_issuer_schema.php` executa a migration real duas vezes, verifica `NULL`, índice, preservação no rollback e instalador.
- `tests/invoice_issuer_recurring_lock.php` compõe o editor real com a classe de lock da fase 383: aquisição condicional, recusa quando ocupado e liberação em exceção. No MySQL há duas conexões reais; localmente SQLite usa um double explícito do protocolo.
- `tests/invoice_issuer_reports.php` executa gráficos reais, opções de filtro e manutenção de cliente com as mesmas fixtures X/Y/NULL; verifica receita legada, não divulgação de modos/anos exclusivos e preservação do endereço/referência das faturas nomeadas.
- `tests/invoice_issuer_http_files.php` executa os controladores/serviços de arquivo reais: IDs/chaves X/Y, página pública, uploads, visibilidade, prefixo/avisos/créditos, previews e travessia. O arquivo sintético legado continua passando pelo download PHP. Não substitui browser/servidor integrado.
- `tests/invoice_issuer_statement.php` verifica extrato real (linhas, totais e saldo anterior), vínculo dos créditos aplicados, seletor de faturas creditáveis e tentativas de apagar alocação usando `invoice_id` forjado.
- `tests/invoice_issuer_discovery.php` executa a busca real (inclusive tags e `OR`), gráfico do portal, calendário e produtores de relatórios; verifica projeções/agregados SQL de X/Y/NULL. O adaptador não simula paginação completa ou autenticação de navegador.
- `tests/invoice_issuer_upgrade.php` ensaia 382→383→384, replay e preservação de dados/tokens em MySQL CI. Ele recusa SQLite para não apresentar transformação de DDL como teste MySQL.
- `tests/invoice_issuer_exports.php` executa consultas reais dos exportadores com X/Y/NULL/órfãos e compatibilidade pré-migration. Não substitui teste dos arquivos finais nem das permissões de download do ZIP.
- `tests/invoice_issuer_cron.php` executa `Cron_model` e o query builder/driver PDO SQLite reais do CI3 com X/Y/NULL/zero e cenário pré-migration. São doubles explícitos apenas transporte/status e funções de named lock; não existe envio real.
- `tests/invoice_issuer_schedule.php` executa o modelo real de fila e entradas HTTP com X/Y/NULL, órfãos e relacionamento forjado; nenhum transporte é chamado.
- Testes Python validam o comportamento do inventário e a impossibilidade de ele habilitar o rollout.
- A workflow `invoice-issuer-scope.yml` executa PHP 8.1/8.5 com MySQL 8 isolado e as regressões de calendário. SQLite local não prova sintaxe/locks MySQL; somente a execução CI no SHA publicado deve ser citada como essa evidência.

## Homologação ainda necessária

Instalação nova e upgrade 382→383→384, banco MySQL real, UI/portal/API com sessões distintas, paginação/totalização/filtros, exportação, anexos e IDs adivinhados; concorrência de editor/scheduler; integrações bancárias com origem imutável; número/documento por empresa; compartilhamento/revogação de cliente sem compartilhar finanças. Usar somente dados sintéticos. Não fazer cobrança real como teste.

## Armazenamento de anexos e Caddy

A inspeção encontrou `Deny from all` em `uploads/invoices/.htaccess`, mas Caddy não interpreta `.htaccess`. As duas configurações Caddy agora negam explicitamente `/uploads/invoices` e seus descendentes antes de `php_server`. As views de preview administrativo, envio e portal já utilizam `download/file/sales_attachment/{chave}`; não dependem de link estático local e foram preservadas. Anexos externos mantêm seus links externos e requerem revisão própria de permissões no provedor.

O teste de contrato valida ambas as configurações. O smoke Docker passa a criar um marcador sintético e exigir HTTP404 para caminho normal e codificado, após provisionar e após reiniciar. Esse teste precisa rodar em CI/container antes de afirmar proteção no runtime; nenhum servidor real foi inspecionado ou alterado, e não foi confirmada exposição de arquivos reais.

Outras pastas também têm negação Apache e precisam de revisão de armazenamento/rotas Caddy antes do rollout completo: `expenses`, `credit_notes`, `proposals`, `estimates`, `contracts`, `tasks`, `leads`, `ticket_attachments`. Esta alteração não bloqueia logos ou diretórios públicos indiscriminadamente. A pendência permanece explícita no inventário; não existe promessa de isolamento de todo o armazenamento nesta fatia.

## Manutenção de referências e contagens

A remoção/transferência de profissional e a remoção de projeto recusam referências a faturas não NULL antes dos hooks e revalidam depois deles. As atualizações financeiras em massa também recebem predicado legado; créditos órfãos não são adotados por esse caminho após384. Contagens de faturas e tarefas vinculadas a faturas no perfil do cliente usam o mesmo predicado. `tests/invoice_issuer_maintenance.php` executa os métodos e SQL com X/Y/NULL/zero, referência adicionada por hook e compatibilidade pré384 (24 verificações locais SQLite).

Essa verificação não transforma a exclusão inteira de profissional/projeto em transação atômica, nem isola os demais domínios associados (tarefas gerais, despesas, propostas e contratos). Escritores externos que ignorem as barreiras continuam fora do contrato; o gate operacional segue fechado.

## Privacidade de anexos e extratos de clientes

A composição fecha também a rota de preview de `uploads/clients`: cliente privado, arquivo órfão e contato sem compartilhamento explícito são recusados antes dos bytes. O funcionário precisa da permissão de cliente ou vínculo administrativo, e o contato precisa pertencer ao cliente, com arquivo visível e compartilhado para ele. Originais e thumbnails usam o mesmo vínculo armazenado. As views existentes continuam no endpoint protegido; logos públicos permanecem fora desse bloqueio. As duas configurações Caddy negam os caminhos estáticos de clientes, inclusive no smoke sintético após reinício. A execução Docker continua pendente de CI.

`Statement_model` recusa explicitamente cliente privado antes de consultar finanças. O DTO mínimo de identidade usado por fatura legada autorizada não é permissão para extrato. Testes executáveis: `client_issuer_preview.php` (32 verificações SQL/arquivo sintético), `invoice_issuer_statement.php` (24) e contrato de armazenamento Python (3).

A quarta consulta de Cron (`send_scheduled_emails`) exclui filas de faturas nomeadas ou órfãs antes dos modelos, transportes e exclusão. As linhas excluídas permanecem intactas para tratamento futuro com contexto explícito; outros tipos e pré-migration preservam o comportamento legado. `invoice_issuer_cron_queue.php` executa a consulta real do CI3/PDO SQLite, com 100 verificações e transportes explicitamente simulados.


A rota `sales_attachment` restringe os tipos a `invoice`, `estimate`, `proposal`, `credit_note` e `debit_note` (este último já usado pelo módulo purchase). Uma chave de cliente, tarefa ou outro domínio não pode selecionar o caminho de armazenamento por esse endpoint, mesmo com `visible_to_customer=1`. A regressão executa o controller real com cliente legado e privados X/Y, anônimo e staff, e confirma rejeição antes do filtro de caminho/output. Plugins que introduzam outro tipo devem fornecer autorização própria em vez de reutilizar essa rota genérica.
