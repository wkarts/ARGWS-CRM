# ARGWS CRM — execução da revisão 8 para `main`

## Autorização e base

Esta alteração atende à instrução posterior de **aplicar os requisitos e abrir
uma PR diretamente para `main`**. Essa instrução autoriza a execução antes
registrada como futura. A adaptação usa o multiempresa existente; não introduz
SaaS, topologia de tenants nem uma nova regra de propriedade presumida.

| Papel | Versão e identidade verificadas |
| --- | --- |
| Referência funcional, layout e navegação | Tag `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7` |
| Base efetiva de destino | `main` **4.8.8**, commit `3cd2ac577a969840c94b019f602cb69d710c0e78` |
| Núcleo proposto | **4.8.9**, migration nativa **489** |
| API | **2.0.4**, nova migration **204**, NOOP |
| Webhooks | **1.2.8**, nova migration **128**, NOOP |
| Branch de trabalho | `fix/native-402-experience-main`, base `main` |

A versão 4.8.7 era a referência informada, mas não era mais a versão da `main`
quando o trabalho começou. A frente 4.0.3 não foi usada como base nem mesclada
nesta branch. As migrations publicadas permanecem intactas.

## O que a alteração entrega

| Requisito | Alteração e evidência principal |
| --- | --- |
| R01/R03 — Propostas e Orçamentos | Recuperação da composição original de lista, colunas, filtros, totais, pipeline e preview sobre fontes autorizadas; adaptação dos formulários compatíveis e preservação de revisão/auditoria. Detalhes e limites em [sales.md](sales.md). |
| R02 — identidade/PWA | Shell, marca e contratos recentes conservados; hashes de 60 arquivos protegidos e testes próprios. [Baselines](baselines.json) e [evidências](payments-and-company-context.md). |
| R04 — Faturas | Preservação dos adaptadores das views originais, com regressões de formulário, POST, tabela, previews, créditos e pagamentos. [Faturas e Itens](invoices-and-items.md). |
| R05 — Faturas recorrentes | Diretório original com oito colunas, filtros e preview de séries, preservando modos, condições, filas e controles recentes. [Recorrências e créditos](credit-recurring.md). |
| R06 — Pagamentos | Corrigida falha HTTP 422 reproduzida na montagem da view; resposta HTML em buffer, protocolo original da tabela, Todos/exportação, formato e ordenação estável. [Diagnóstico e testes](payments-and-company-context.md). |
| R07 — Notas de crédito | Lista/filtros, preview e emissão com endereços, projeto tipado, referência, termos, unidade/descrição e campos personalizados reais; cálculo decimal exato, ledger, aplicação/estorno/reembolso conservados. Ações laterais ainda pendentes estão identificadas. [Detalhes](credit-recurring.md). |
| R08 — Itens | Corrigidas ordenação, busca e opção Todos, mantendo catálogo histórico/próprio, campos personalizados e formulário existente. [Consulta e testes](invoices-and-items.md). |
| R09 — todo Vendas | Inventário de todas as entradas encontradas, inclusive Asaas: origem e pendências, e de recorrências como subfluxo de Faturas. [Inventário](sales-inventory.json). |
| R10 — demais áreas | Adaptações de Contratos/Despesas, lista/modal/Kanban de Leads, vínculo exato de créditos ao projeto, kanban de Ajuda online, autorizações de API/Webhooks; inventários completos das áreas, integrações e utilidades reais. [Áreas nativas](native-areas.md) e [integrações](integrations.md). |
| R11 — empresa ativa | Invalidação de documentos antigos entre abas, proteção de respostas atrasadas e histórico do navegador; rotas restauradas passam pela fronteira antes de carregar dados. Testes de empresa/ACL por recurso, com lacunas identificadas. [Contexto](payments-and-company-context.md). |

O travamento relatado pelo usuário continua associado especificamente ao clique
em **Vendas → Pagamentos**. A reprodução local comprovou uma falha de montagem
com HTTP 422; não reproduziu a sessão completa da instalação do usuário nem
atribuiu travamento a outros módulos.

## Cobertura não é equivalência integral

[acceptance.md](acceptance.md) relaciona os **64 critérios de aceite**, suas
evidências e o que falta. Cada relatório distingue quatro situações: código
inventariado, comportamento executado em teste, recurso ainda sem adaptação e
conflito real que precisa de decisão. Um método listado no inventário não é,
por esse fato, um endpoint habilitado ou um cenário homologado.

Os testes locais usam dados sintéticos. Serviços, SQL, cálculos e views reais
são exercitados onde o relatório indica; dependências do framework são
controladas em diversos fixtures. Isso não substitui uma instalação completa
autenticada nem uma comparação visual lado a lado com dados equivalentes.
O primeiro checkpoint passou no gate focado de PHP 8.1/8.5, MySQL e
Chromium. A suíte HTTP completa encontrou uma resposta Ajax 409 durante a
navegação/troca de empresa. No segundo checkpoint, esse cenário passou,
assim como os testes de apresentação em navegador; outras etapas encontraram
expectativas antigas e dependências ausentes nas fixtures, com correções
identificadas para a execução seguinte. Resultados por SHA constam em
[checkpoints](validation-checkpoints.md). PWA instalado/offline e integrações externas reais
exigem homologação própria.

## Diferenças que precisam de decisão funcional

Estas questões decorrem do código encontrado e dos critérios AC47, AC53 e
AC63; não são requisitos adicionais inferidos.

| Diferença concreta | Estado preservado nesta PR | Decisão necessária para equivalência integral |
| --- | --- | --- |
| Propostas a Leads e envio de documentos ainda abertos | O documento privado atual exige cliente autorizado; a entrega usa portal autenticado, estados e transporte explícitos. | Definir o contrato desejado para Lead e entrega antes do aceite, preservando histórico e identidade financeira. |
| Alterar/excluir/reabrir documentos financeiros emitidos | Writers recentes continuam exigindo revisão, autoria, snapshots e movimentos de correção. | Definir quais operações antigas devem existir e como se conciliam com a imutabilidade e a trilha atuais. |
| Manager, Conector, Chat, API/Webhooks legados globais | Recursos, credenciais, históricos e contratos permanecem; rotas globais não são liberadas para empresas secundárias por suposição. | Mapear titularidade de instâncias/tokens/filas/históricos e distinguir administração compartilhada de operação da empresa. |
| Assinaturas Stripe, Pedido de orçamento, regras recorrentes e utilidades globais sem adapter | Recursos originais e dados são conservados; o inventário distingue seu escopo dos serviços canônicos novos. | Definir propriedade e contratos de entrada/saída antes de adaptar esses fluxos ao seletor de empresa. |
| Semelhança de nomes sem equivalência de dados | API, Connect API, Manager, Conector e Chat continuam separados; assinatura Stripe não é série de fatura recorrente. | Resolver cada correspondência pelo recurso real, sem fusão automática de módulos. |

Enquanto essas diferenças e a homologação integral estiverem abertas, a PR deve
permanecer **em rascunho**. O pedido autoriza sua abertura para revisão e CI;
esta entrega não executa merge, publicação de release ou implantação.

A ausência de um adaptador ou campo no writer atual, por si só, não constitui
decisão pendente do usuário. Os formulários compatíveis de Propostas/Orçamentos e os campos opcionais
de emissão de crédito foram implementados e exercitados em testes de
roundtrip. Campos complementares privados de Leads e Contratos, a listagem,
categorias, totais e lote de Despesas possuem implementação e verificações
locais nesta composição. Colunas personalizadas/ordenação/lote de Leads e
conteúdo das prévias/pipeline de Vendas seguem em implementação autorizada;
ausência de adapter não é tratada como conflito do usuário. O resultado e os
limites de cada frente constam nos relatórios específicos.

## Banco, compatibilidade e atualização

A migration 489 cria a projeção aditiva `company_sales_projection` e a
reconcilia com `company_sales_documents`. Acrescenta `company_lead_metadata` e
`company_contract_metadata`, sem popular dados privados por suposição; admite
`due_date` nula em contratos e acrescenta descrição opcional às categorias de
despesas. A conversão do DDL de Contratos compara os registros antes/depois.
Nenhuma dessas mudanças renumera documentos, cria empresa, emite fatura, muda
saldo ou atribui propriedade aos registros históricos `issuer_id IS NULL`.
Fonte, hashes e cálculo decimal são conferidos durante o backfill de Vendas.
Os writers atualizam sua projeção/metadados na mesma transação de suas operações.

O backfill usa o bloqueio/transação já previstos no domínio; duração e volume
de produção precisam ser medidos em uma cópia representativa. MySQL não desfaz
DDL como uma transação de dados: se a validação falhar, a tabela pode existir
enquanto o marcador nativo continua pendente. Corrigir a causa e repetir a
migration é o fluxo previsto; não avançar o marcador manualmente.

As migrations API 204 e Webhooks 128 são NOOPs documentados para registrar as
correções funcionais de autorização. Nenhuma migração histórica é editada.

Depois de aprovação e homologação, usar o processo normal descrito em
[Distribuição ARGWS](../distribuicao-argws.md),
[Operação PHP e containers](../operacao-php-e-containers.md) e
[migrations de módulos](../module-migrations.md). Conferir, na atualização
nativa, núcleo 489 e versões dos módulos ativos correspondentes; repetir os
cenários de empresa, Pagamentos, Sales e documentos desta revisão.

O retorno ao estado anterior exige plano consistente de código, banco,
configuração e uploads. Reverter apenas código não desfaz documentos emitidos,
movimentos, notas ou filas gravadas após a atualização. `down()` de 489 conserva
as tabelas aditivas, os campos e o histórico; o atualizador não implementa downgrade automático.
Não reduzir marcadores ou apagar dados para simular retorno de versão.

## Índice de evidências

- [Critérios AC01–AC64](acceptance.md).
- [Resultados de CI por checkpoint](validation-checkpoints.md).
- [Vendas, Propostas e Orçamentos](sales.md) e [inventário de arquivos](sales-inventory.json).
- [Faturas e Itens](invoices-and-items.md).
- [Notas de crédito e Faturas recorrentes](credit-recurring.md).
- [Pagamentos, contexto e identidade](payments-and-company-context.md).
- [Áreas nativas e todas as utilidades](native-areas.md) e [inventário](native-areas-inventory.json).
- [API, Webhooks e Connect API completa](integrations.md) e [inventário](integrations-inventory.json).
- [Hashes de preservação de marca/PWA](baselines.json).

Os inventários gerais do projeto também são regenerados pelos scripts existentes,
sem converter automaticamente classificações pendentes em revisões aprovadas.
