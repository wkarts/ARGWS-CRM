# Recorrências independentes e alterações futuras em lote

## Contrato e limites desta etapa

A rota `admin/recurring_rules`, acessível em **Faturas → Faturas recorrentes → Gerenciar recorrências**, administra modelos próprios. Cliente, itens do catálogo, preços, impostos, valores e calendário são persistidos em `recurring_rules`; não é necessário criar uma fatura inicial. Criar salva a regra **pausada** e não gera documento ou chamada externa. A ativação exige prévia e confirmação.

A execução desta etapa é deliberadamente **rascunho local + revisão manual**, identificada por `integration_group=manual`. O agendador não emite, envia ou cobra automaticamente esses novos rascunhos. O administrador revisa o documento e escolhe o fluxo financeiro normal separadamente. Os meios de pagamento do modelo não são copiados; gerar um rascunho não pode chamar os hooks financeiros `before_invoice_added`/`after_invoice_added`, nem o Asaas. A recorrência legada não migrada mantém seu comportamento existente.

`emitter_key=legacy` identifica explicitamente o contexto da instalação já existente. Não significa uma empresa cadastrada, não deriva emitente do cliente/default e não estabelece isolamento multiempresa. Outros emitentes ou grupos de integração não são executados. Uma fatura que contenha `issuer_id` não nulo é inelegível para migração/conversão nesta etapa. Integração com cadastro de emitentes, contas separadas e autorização multiempresa depende de revisão própria e não foi habilitada por este recurso.

## Calendário e valores

- `days` continua sendo o default compatível. `fixed_day` é escolha explícita somente em intervalos mensais/anuais, com vencimento definido.
- Reutiliza `RecurringInvoiceDates` da correção de calendário: dias 28–31, fevereiro bissexto e recuperação da âncora original após mês curto; emissão e vencimento têm âncoras independentes.
- A primeira data e o primeiro vencimento informados definem as âncoras; não há inferência de um dia 31 a partir de uma data original no dia 28.
- Na nova regra, preços/impostos são lidos no servidor do catálogo, na moeda do cliente. Valores enviados pelo navegador não são usados. Quantidades e preços têm até duas casas decimais. Totais usam inteiros e arredondamento de centavos, com limites explícitos. Assim como `calculate_total()` do core, cada linha é arredondada antes do imposto e os grupos de imposto são somados sem arredondamento intermediário: dois tributos de meio centavo produzem um centavo de imposto total.
- O catálogo é copiado; alterações posteriores no catálogo ou no cliente não reescrevem a regra. O modelo atual não oferece edição de itens/preços/cliente de uma regra já criada. Para esse caso, pause/encerre a regra e crie uma substituta após revisar seu calendário; não faça migração em massa de valores.
- O primeiro ciclo fica na data inicial. A retomada pula datas anteriores a hoje e mantém a âncora original, sem cobrar períodos perdidos. O CRON executa no máximo um ciclo devido por regra e até 100 regras por chamada. Uma regra ativa atrasada pode produzir um rascunho por execução até se atualizar; a prévia mostra as próximas datas, inclusive datas passadas.
- Pausar/encerrar só altera a regra. Encerrar é terminal; faturas/rascunhos já gerados continuam acessíveis e inalterados.

## Migração de matrizes legadas

A aba **Matrizes legadas** oferece conversão de vencimento ou migração explícita. Migração copia os campos financeiros usuais, endereço, itens, impostos, opções de item e campos personalizados de fatura/itens para um modelo independente. Não copia anexos, tags, tarefas, despesas, pagamentos, vínculos de cobrança ou credenciais.

A nova regra fica pausada e mantém contagem/limite de ciclos e próxima data calculada a partir do histórico. Na matriz original, somente `recurring` passa a zero. Datas, valores, status, filhos e pagamentos não são alterados. O CRON exclui fontes presentes em `recurring_rules.source_invoice_id` mesmo se um formulário antigo tentar reativar a recorrência legada.

São inelegíveis: filhos, canceladas, rascunhos, matrizes inativas, ciclos esgotados, datas inválidas, emitente explícito e matrizes já migradas. Migração também é bloqueada quando a instalação exige matriz paga antes de cada renovação, pois o novo executor de rascunhos não presume equivalência desse contrato.

## Lote, autorização e idempotência

1. Seleção explícita de 1–100 IDs distintos, sem escolher silenciosamente todos os resultados de um filtro.
2. Prévia do estado, modo, âncoras e próximas três emissões/vencimentos, antes e depois. Inelegibilidade bloqueia o lote inteiro.
3. Prévia persistida por 15 minutos, vinculada ao colaborador, ação, conjunto exato de IDs e hash do snapshot. Mudança de dia também expira a prévia.
4. Confirmação explícita de que apenas ciclos futuros são afetados. O POST recebe somente token e confirmação; não pode trocar IDs/payload aprovado.
5. Aplicação revalida edição, criação quando migrando e visibilidade de cada ID. Linha do lote e registros são travados; o hash/versão é conferido antes de qualquer mudança. Divergência aborta tudo.
6. Transação única do lote e auditoria por registro. Replay retorna o resultado persistido sem repetir a alteração/auditoria. Retirada de permissão/visibilidade também bloqueia replay.
7. Todos os POSTs exigem token aleatório próprio, vinculado à sessão/colaborador e comparado em tempo constante, mesmo se o CSRF global estiver desabilitado. A validação ocorre antes de carregar o modelo.
8. Criação de regra usa chave de formulário vinculada ao autor/payload. Reenvio idêntico retorna o mesmo ID; reutilização com outros dados é rejeitada.

Nenhum lote chama `Invoices_model::update`, criação de fatura, envio ou provedor. Acesso segue as permissões atuais de faturas (ver todas, próprias ou atribuídas) e edição; não há permissão implícita para ver clientes fora do cadastro autorizado ao criar uma regra.

## Concorrência e geração

`RecurringInvoiceLock` usa `GET_LOCK` MySQL/MariaDB com chave por prefixo, sem espera. O CRON legado, o executor novo e as mutações de regras/lotes compartilham a mesma exclusão; lock ocupado produz adiamento/repetição explícita, sem aplicar parcialmente. Instalações com mesmo prefixo no mesmo servidor são serializadas também, um limite conservador conhecido.

Cada ciclo novo ocorre dentro de transação e row lock da regra: criação de rascunho, seus itens/campos, vínculo `rule_id + cycle_date`, avanço do calendário e auditoria são confirmados juntos. Há unicidade tanto de regra/data quanto de fatura gerada. Uma falha no meio é revertida; a próxima execução pode tentar de novo. Isso cobre os registros locais nesta conexão; não se afirma exactly-once de provedores externos.

O executor verifica InnoDB nas tabelas envolvidas e bloqueia operação se encontrar estrutura incompleta ou engine não transacional. Ele não converte engines automaticamente.

O editor legado pode alterar itens em etapas próprias. A evolução do controle de emitente/editor deve compartilhar o mesmo lock ao editar uma matriz ativa, para garantir snapshot coerente durante migração. Até essa composição ser homologada, migre matrizes em janela sem edição simultânea. O row lock/hash já protege os metadados da matriz e alterações feitas antes da confirmação; não substitui transação de um editor que grave múltiplas tabelas sem o lock compartilhado.

## Banco e instalação

Migration **383**, posterior ao marcador **382** da release 3.8.1. Quatro tabelas novas InnoDB:

- `recurring_rules`: modelo, calendário, estado, versão, origem opcional, contexto explícito e chave idempotente de criação.
- `recurring_rule_batches`: prévia, autor, prazo e resultado idempotente. Armazena hashes e metadados de seleção, sem copiar endereços/itens nos snapshots do lote.
- `recurring_rule_audit`: autor, horário, IDs e antes/depois de configuração. Não inclui template, credencial ou dados de pagamento.
- `recurring_rule_runs`: chave única regra/data e fatura gerada.

Instalador e migration têm definições equivalentes; `CREATE TABLE IF NOT EXISTS` permite reaplicação. Nenhuma conversão de dados ocorre na migration. Tokens/auditoria não são apagados automaticamente; definir retenção operacional com backup antes de eventual manutenção. O volume cresce por regra, prévia, alteração e ciclo. Não foram realizados benchmarks.

## Validação automatizada

- `php tests/recurring_invoice_dates.php`: calendário existente (4023 verificações).
- `php tests/recurring_invoice_cron.php`: método legado com colaboradores locais (30 verificações).
- `php tests/recurring_invoice_schema.php`: compatibilidade da migration 381.
- `php tests/recurring_rules.php`: política/model reais com banco e writer em memória, incluindo permissão, ID fora de escopo/inexistente, elegibilidade, vencimento, conflito, expiração, rollback, replay, emissão e limites de emitente (86 verificações na versão atual).
- `php tests/recurring_rules_controller.php`: controller real com sessão/input locais; token próprio obrigatório, vínculo de sessão/autor, rejeição antes de writes e token nos quatro formulários (23 verificações).
- `php tests/recurring_rule_draft_writer.php`: caminho real `Invoices_model::add` com colaboradores locais, negando hooks/numeração/envio (14 verificações).
- `php tests/recurring_rule_schema.php`: migration aditiva, repetível, InnoDB, unicidade e igualdade com instalador.
- `tests/recurring_rules_mysql.php`: teste de integração que exige banco vazio de nome exato `crm_recurrence_test`; importa schema real, repete migration e usa driver CodeIgniter, modelo e writer reais para transações, replay, geração, campos personalizados e disputa do lock entre conexões. Nunca apontar para uma instalação de CRM. Fora do ambiente explicitamente configurado termina sem executar, com código 2.

Executado localmente em PHP-WASM **8.1.34 e 8.5.10**: suites de calendário, CRON, schemas simulados, política/modelo, controller e writer; sintaxe de 22 arquivos. Também passaram 31 testes Node, 54 testes Python, `scripts/check-release-version.sh` e `git diff --check`. Não há PHP nativo ou MariaDB local; o teste com banco real foi escrito, mas aguarda execução no CI. Não se equiparam os testes com colaboradores locais a uma homologação integral da aplicação.

O workflow `recurring-rules.yml` usa PHP 8.1/8.5 e MariaDB 11.4 efêmero. Os testes não iniciam a aplicação de produção nem usam credenciais ou APIs reais. A configuração do banco do teste é exclusiva do serviço efêmero de CI.

## Homologação manual pendente

Em instalação isolada com integrações desligadas: criar cliente/itens sintéticos; criar regra sem fatura; conferir valores e campos; revisar/ativar; rodar CRON; verificar um único rascunho local, sem meio de pagamento/envio; repetir CRON; pausar/encerrar; conferir que documentos continuam iguais. Validar perfis ver próprias/atribuídas, formulários repetidos, Cancelar/Voltar, navegação/paginação, seleção com um inelegível e alteração concorrente. Testar matrizes 28–31, emissão 26/vencimento 5 no mês seguinte e modo legado. Comparar banco antes/depois de lotes com falha simulada.

Homologar concorrência entre editor legado, CRON e migração antes de liberar migração em produção. Conferir coexistência com alterações separadas de Asaas/emitentes. A UI não foi validada em navegador autenticado nesta implementação local.

## Deploy e rollback

Deploy não executado. Fazer backup verificado do banco/configuração/uploads, aplicar em homologação, revisar dependências e interromper o CRON durante atualização de código/schema. Aplicar migration 383 pelo fluxo normal antes de liberar as rotas e executar os testes de fumaça. A geração depende de ativação posterior de cada regra; não há ativação automática de contratos existentes.

Rollback preserva as tabelas, regras, vínculos e auditoria. Antes de reverter o código, pause as regras novas e interrompa o CRON. Não reative matrizes migradas automaticamente: isso pode duplicar cobranças se rascunhos já tiverem sido emitidos manualmente. Restaurar um contrato legado exige revisão explícita de última emissão, ciclos e documentos existentes. `down()` não apaga nenhum dado e não reativa matrizes. O schema aditivo é compatível com código anterior, mas regras novas deixam de executar quando esse código não existe.
