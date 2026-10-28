# Orçamentos e propostas: fronteiras para a experiência oficial

Este documento descreve trabalho pendente; não habilita rotas nem atesta funcionamento por empresa.

## Atualização local r10 — composição e Settings oficiais aplicados

Os fluxos principais de `Estimates` e `Proposals` agora estão admitidos pelo
catálogo oficial para `index`, listagem, formulário, PDF e vencimento. Esses
endpoints já chamavam `NativeCompanySalesAdminHttp`; a alteração apenas fechou
o allowlist e marcou a atestação compartilhada para a empresa principal e as
secundárias. O adapter mantém criação/edição/cópia, conversão, status, anexos,
portal e envio como operações com CSRF, revisão, ACL e escopo do emitente.

Os testes `tests/native_sales_composition.test.cjs` e
`tests/native_sales_settings_composition.test.cjs` verificam a composição, os
verbos, os grupos oficiais e o contrato de sequência/revisão. A prova HTTP/PHP/
MySQL/Chromium continua pendente por falta de runtimes neste ambiente; não foram
abertas ações que ainda dependem do modelo legado sem adapter.

## Configurações oficiais

O formulário oficial `admin/settings?group=estimates` contém 17 opções: prefixo, próximo número, vencimento, política de exclusão e decremento, visualização atribuída, exigência de login, agente, projeto, conversão automática, rascunho no portal, formato numérico, dois controles de ordenação e limite do pipeline, nota e condições padrão. O formulário `group=proposals` contém 12 opções: prefixo, vencimento, limite e dois controles de ordenação do pipeline, projeto, rascunho no portal, duas conversões automáticas, visualização atribuída, formato dos dados do destinatário e condições padrão. Ele também consulta definições de campos personalizados.

Atualização local r10: os adaptadores `NativeCompanySalesSettings` e `NativeCompanySalesSequencePreferences` já cobriam esses campos, a revisão otimista e a sequência por empresa. A composição foi concluída ao incluir `estimates` e `proposals` em `NativeCompanySettingsForms::availableGroups()`, mantendo a view oficial e o controller Settings como única entrada.

As gravações mantêm ACL revalidada, CSRF, revisão otimista, transação, confirmação e tratamento distinto para valor ausente e valor vazio. Nenhuma configuração deve consultar opções de outra empresa. O teste de composição cobre a admissão dos grupos e a ligação ao controller/view; a prova HTTP/DB continua dependente dos runtimes ausentes.

`next_estimate_number` exige tratamento próprio: o writer nativo existente `Estimates_model::add()` incrementa diretamente `options`, e `delete()` pode decrementá-lo. Reutilizá-los para empresas secundárias sem adaptação não mantém sequências isoladas. O serviço `CompanySales` reserva `issuers_sequences` por empresa e série, mas a equivalência histórica, política de decremento e mudanças manuais de numeração precisam ser definidas e testadas; não é seguro simplesmente trocar a fonte ou renumerar documentos já emitidos.

## Operações oficiais

`NativeCompanyRoutes` admite os métodos compostos dos controllers `Estimates` e `Proposals` com adapters para os mesmos controllers e views existentes, identificação tipada de origem, autorização e controle de propriedade antes de consultar dados. O escopo admitido inclui listagem/tabela, criar/editar, leitura direta, anotações, status, cópia, anexos, PDF, conversão, mensagens e portal. Cada verbo e entrada segue o catálogo oficial; as demais famílias ainda devem ser migradas antes de qualquer declaração de fechamento integral.

Os serviços `CompanySales`, `CompanySalesPreferences`, `CompanySalesFiles` e `CompanySalesDelivery` fornecem partes úteis de propriedade, revisões, snapshots e conversão, mas não são adapters completos dos formulários oficiais. `CompanySalesBase::input()` aceita cliente, moeda, data, vencimento, itens, nota, desconto e ajuste; propostas nativas também podem se relacionar a lead, têm assunto, conteúdo, responsável, comentários, projeto, status e campos personalizados. O adaptador deve preservar esses dados e comportamentos; se o armazenamento escolhido não os comportar, a extensão requer migration nativa aditiva e uma estratégia de leitura compatível, sem alterar migrations existentes. A preferência capturada hoje cobre prefixo, termos, PDF e tipografia; as demais opções exigem consumidores próprios antes de serem anunciadas como operacionais.

## Provas mínimas

- Principal → A → B → principal, incluindo acessos diretos e colisões de IDs, sem misturar empresas ou alterar histórico.
- Campos válidos, inválidos e desconhecidos; ausência versus vazio; CSRF; revogação de ACL; revisão antiga; falha posterior com rollback e repetição segura.
- Numeração concorrente, configuração antes/depois da emissão, cópia, edição, exclusão e conversão sem renumerar histórico.
- Relação com cliente e lead, campos personalizados autorizados, itens, anexos, PDF, status, portal e permissões de acesso por recurso.
- HTTP e navegador reais com desktop e dispositivos móveis, além de MySQL/MariaDB e compatibilidade do upgrade histórico.

O teste `tests/settings_sales_documents_browser.cjs` usa o grupo legado `sales_documents` e `company_sales`; deve ser migrado junto com os fluxos oficiais sem apagar seus cenários de isolamento, snapshots, conversão e formulário antigo. Testes úteis existentes incluem `tests/settings/sales_documents.php`, `tests/settings/primary_sales_documents.php`, `tests/companies/sales_preferences.php`, `tests/companies/sales_preferences_concurrency.php`, `tests/companies/sales_mutations*.php`, `tests/companies/portal_sales.php` e `tests/companies/sales_document_files.php`. Esses testes não provam, por si, o novo caminho oficial HTTP.
