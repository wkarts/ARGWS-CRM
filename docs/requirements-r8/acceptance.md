# Matriz de aceite — revisão 8 (AC01–AC64)

## Situação da composição

Referência: **4.0.2**, `aa58b832b3c5ed04a599caacba125b056c081ba7`.
Destino efetivo: **MAIN 4.8.8**, `3cd2ac577a969840c94b019f602cb69d710c0e78`.
Composição preparada para a PR: **4.8.9**, migration nativa 489. A frente 4.0.3
continua separada. O pedido atual autoriza executar e abrir a PR; o texto antigo
de coleta não foi interpretado como bloqueio dessa autorização posterior.

**A revisão 8 não está integralmente homologada.** Esta matriz registra cada um
dos 64 critérios, sem converter inventário, teste de serviço ou inclusão em CI em
aceite de todo o requisito. O status pertence à composição em preparação; a PR
em rascunho é o ponto de revisão e execução remota, não uma confirmação de merge
ou implantação.

### Como ler os estados

- **Parcial — implementação/cobertura**: há código funcional e provas delimitadas;
  permanecem ações/campos/áreas sem adaptação ou sem teste completo.
- **Pendente — homologação visual**: o critério requer comparação real lado a
  lado; hashes/render/DOM simulado não a substituem.
- **Inventariado em código**: entradas/ações/fontes foram inspecionadas; ativação
  e configuração da instalação não são presumidas.
- **Documentado**: bases, cenários, resultados e diferenças estão registrados,
  incluindo limitações; isso não transforma os outros critérios em concluídos.
- **Conflito/decisão**: existe diferença real de propriedade, identidade,
  imutabilidade ou efeito externo. Não é somente falta de ambiente de teste.
- **Preservado no delta**: recursos atuais não foram apagados para forçar
  equivalência; sua disponibilidade/execução integral ainda depende das demais
  verificações descritas.

## Matriz


| Critério | Requisito | Verificação pedida | Estado | Evidência disponível | Pendência ou limite concreto |
| --- | --- | --- | --- | --- | --- |
| AC01 | R01 | Fluxo de Propostas | Parcial — implementação | [Vendas]: lista, filtros, pipeline, preview, notas e serviços reais da empresa. | Formulário compatível tem roundtrip real; ações laterais ainda pendentes. Vínculo direto a Lead, envio de rascunho, estados/conversões e edição de emitidos conflitam com contratos atuais. Não há paridade integral. |
| AC02 | R01 | Conteúdo de Propostas | Inventariado; adaptação parcial | [Vendas] e [Inventário Vendas]: views, métodos, dez colunas base, campos e filtros. | Inventário de código não comprova roundtrip de todos os campos/ações. Conferir formulário completo e diferenças de destinatário/assinatura/numeração. |
| AC03 | R01 | Layout funcional e identidade em comparação lado a lado | Pendente — homologação visual | [Vendas]: templates originais usados na lista/pipeline e shell recente; [Marca]: hashes dos ativos. | Não há capturas comparáveis de 4.0.2 e destino com mesma tela/dados; tampouco aceite de todos os estados do formulário. |
| AC04 | R01 | Contexto e regras multiempresa de Propostas | Parcial — testes de fronteira | [Vendas]: A/B/principal, origem/histórico, filtros e negação; [Contexto]: gate real e geração de abas. | Testar todos os fluxos de Propostas em HTTP/Chrome/MySQL, com troca real de empresa, perfis restritos e URLs diretas. |
| AC05 | R01 | Versões, cenários, resultados e diferenças de Propostas | Documentado; execução final pendente | [Vendas], [Inventário Vendas] e esta matriz identificam bases, testes e conflitos. | Anexar CI do SHA final e comparação visual; nenhuma equivalência integral declarada. |
| AC06 | R02 | Identidade visual recente preservada | Parcial — arquivos e testes | [Marca]: 60 arquivos iguais à base; [Contexto]: brand_assets_smoke e crm_ui_branding aprovados. | A igualdade dos ativos e contratos não comprova sua apresentação em todas as views alteradas; comparação visual permanece pendente. |
| AC07 | R02 | Apresentação, configuração e funcionamento do PWA | Parcial — contratos e ativos | [Contexto]: 11 testes native_pwa aprovados; [Marca]: worker, configuração, manifest/presentation e ícones preservados no conjunto inspecionado. | Instalar/usar o PWA real, conferir login/logout, navegação/offline quando previsto pela base e troca de empresa em dispositivo; não inferir do Node. |
| AC08 | R03 | Fluxo de Orçamentos | Parcial — implementação | [Vendas]: lista, filtros, estatísticas/totais, pipeline, preview, notas e writers existentes. | Formulário compatível tem roundtrip real; completar ações laterais. Envio/assinatura/alteração financeira de documentos emitidos não pode usar o modelo global por equivalência presumida. |
| AC09 | R03 | Conteúdo de Orçamentos | Inventariado; adaptação parcial | [Vendas]: onze colunas, formulários, endereços, números, tags/custom fields, itens e demais métodos; [Inventário Vendas]. | Roundtrip dos controles compatíveis executado (52 checks de view, 53 de HTTP e 16 de transporte); confirmar a interação real no CI e as ações laterais restantes, sem dados fabricados. |
| AC10 | R03 | Tela original de Orçamentos sem redesenho | Pendente — homologação visual | [Vendas]: manage/list/pipeline/quick_stats/totais originais adaptados. | Falta comparação lado a lado com a 4.0.2 nas mesmas condições de tela e dados, inclusive edição/preview/pipeline. |
| AC11 | R03 | Multiempresa em Orçamentos | Parcial — testes de fronteira | [Vendas]: união autorizada, A/B/principal, money por moeda/ano, referências e autorização; [Contexto]: gate. | Homologar operações completas e troca de empresa, não somente projeção e controller com bordas simuladas. |
| AC12 | R03 | Identidade e PWA em Orçamentos | Parcial — arquivos e testes | [Marca] e [Contexto] cobrem a preservação comum. | Mesma pendência visual/PWA de AC06 e AC07 aplicada ao fluxo de Orçamentos. |
| AC13 | R03 | Evidências de Orçamentos | Documentado; execução final pendente | [Vendas], [Inventário Vendas]: bases, escopo, resultados, diferenças e roteiro MySQL. | Confirmar CI do commit final e adicionar homologação visual/funcional de todos os cenários. |
| AC14 | R04 | Fluxo de Faturas | Parcial — testes funcionais | [Faturas e Itens]: dez scripts reais de controller/formulário/preview/pagamento; [Vendas] inventaria os recursos. | Não houve repetição de todas as ações/estados em instalação completa nem comparação integral 4.0.2; demais writers recentes preservados. |
| AC15 | R04 | Conteúdo de Faturas | Inventariado; testes parciais | [Inventário Vendas], [Faturas e Itens]: lista, formulário, linhas, tags, campos, créditos, recorrência e PDF. | Validar campo a campo e por estado o conjunto restante, inclusive recursos financeiros recentes sem equivalente antigo. |
| AC16 | R04 | View de Faturas com identidade recente | Pendente — homologação visual | [Faturas e Itens]: composição original já existente e render real exercitado; [Marca]. | Sem comparação lado a lado com dados/telas iguais; render de fixture não satisfaz esse critério visual. |
| AC17 | R04 | Multiempresa em Faturas | Parcial — testes de fronteira | [Faturas e Itens]: A/B/principal, créditos, pagamentos, recorrências, tags e IDs; [Contexto]. | Executar cenários completos com troca A→B→A em browser e dialeto MySQL; limites de outros módulos permanecem. |
| AC18 | R04 | Identidade e PWA em Faturas | Parcial — arquivos e testes | [Marca] e [Contexto]; leitores e PDFs atuais preservados. | Homologação visual e PWA aplicado a Faturas conforme AC06/AC07. |
| AC19 | R04 | Evidências de Faturas | Documentado; execução final pendente | [Faturas e Itens] lista dez suites e delimita fixtures/runtime; [Inventário Vendas]. | Confirmar todos os gates no SHA final e anexar comparações/cenários que ainda faltam. |
| AC20 | R05 | Fluxo de Faturas recorrentes | Parcial — implementação e conflito | [Créditos e Recorrências]: diretório original sobre séries próprias, preview e controles atuais, criação em new=1. | Editor de regras globais recurring_rules, recorrência legada da fatura e série canônica são contratos distintos; não foram tornados equivalentes. |
| AC21 | R05 | Conteúdo de Faturas recorrentes | Inventariado; adaptação parcial | [Créditos e Recorrências]: oito colunas, filtros, datas, ciclos, agente real e vínculo da última fatura filha. | Formulário detalhado recente permanece; conciliar campos/regras globais e fontes legadas antes de declarar conteúdo completo. |
| AC22 | R05 | View própria de Faturas recorrentes | Pendente — homologação visual | [Créditos e Recorrências]: list/list_template/table_html originais e preview próprio. | Comparação visual dos diretórios, formulários e estados das duas bases não executada; não copiar Faturas comuns como substituição. |
| AC23 | R05 | Multiempresa em Faturas recorrentes | Parcial — testes de fronteira | [Créditos e Recorrências]: A/B/principal, hashes, 102 séries, paginação, modos, contextos/CSRF, faturas e drafts. | MySQL/Chromium e todos os modos de execução/retomada nas empresas precisam do resultado final; regras globais ainda sem adapter. |
| AC24 | R05 | Identidade e PWA em Faturas recorrentes | Parcial — arquivos e testes | [Marca] e [Contexto]; série mantém snapshots/identidade recentes. | Aplicar homologação visual/PWA de AC06/AC07 também à recorrência. |
| AC25 | R05 | Evidências de Faturas recorrentes | Documentado; execução final pendente | [Créditos e Recorrências] identifica bases, testes, roteiros browser e diferenças de contratos. | Aprovação remota e equivalência visual seguem pendentes; incluir SHA final no resultado. |
| AC26 | R06 | Reproduzir e diagnosticar a falha de Pagamentos | Reproduzido em fixture; instalação pendente | [Contexto]: handler/view/helper reais reproduziram HTTP422 no catálogo de preferência payments antes da correção. | Não foi reproduzida a sessão do usuário nem gravado o travamento em browser; a falha concreta de render é distinguida do relato. |
| AC27 | R06 | Fluxo e conteúdo de Pagamentos | Parcial — inventário e implementação | [Contexto]: sete colunas, busca/ordem/ALL, detalhe, PDFs e preservação de leitores histórico/próprio; [Inventário Vendas]. | Exercitar integralmente operações e navegação do Pagamentos original para todos os tipos de origem autorizada. |
| AC28 | R06 | View, identidade e PWA de Pagamentos | Parcial — render; visual pendente | [Contexto]: documento original completo renderizado e sem HTML parcial; [Marca]. | Comparação lado a lado e funcionamento do PWA em uso real permanecem pendentes conforme AC06/AC07. |
| AC29 | R06 | Clique em Vendas > Pagamentos sem travamento | Correção testada; browser pendente | [Contexto]: HTTP422 reproduzido antes; depois testes do índice/tabela/recibo aprovados e resposta em buffer. | Executar clique e sequência de uso na instalação/browser real; não declarar todo travamento do relato resolvido apenas pelo fixture. |
| AC30 | R06 | Integridade da interface, dados e navegação após Pagamentos | Parcial — dados/render | [Contexto]: snapshot financeiro preservado nas leituras/negações e montagem atômica do HTML. | Falta navegação real posterior entre módulos e comprovação visual de ausência de desconfiguração em todos os cenários relatados. |
| AC31 | R06 | Multiempresa em Pagamentos | Parcial — testes de fronteira | [Contexto]: principal/A/B, preferência e ator, recibos históricos, propriedade, CSRF e IDs; gate real. | Validar perfis e troca de empresa em browser/MySQL, inclusive documentos/abas previamente abertos. |
| AC32 | R06 | Evidências antes/depois e limites de Pagamentos | Documentado; homologação pendente | [Contexto] contém erro anterior, causa específica, correção, testes e distinção do relato. | Anexar resultados remotos e reprodução/homologação da instalação afetada sem estender diagnóstico a outros módulos. |
| AC33 | R07 | Fluxo de Notas de crédito | Parcial — implementação e conflito | [Créditos e Recorrências]: lista/preview/editor e aplicação/estorno/reembolso/anulação canônicos. | Editar/excluir/reabrir emitida, criar a partir de fatura, envio e anexos não têm equivalência implementada; preservar imutabilidade é decisão explícita a conciliar. |
| AC34 | R07 | Conteúdo de Notas de crédito | Inventariado; roundtrip incompleto | [Créditos e Recorrências]: oito colunas, grade e campos do writer, filtros/custom fields de leitura. | Projeto/referência/número manual/termos/notas extras/long description/unidade/custom fields de emissão precisam de extensão real; não enviados para descarte silencioso. |
| AC35 | R07 | View de Notas de crédito | Pendente — homologação visual | [Créditos e Recorrências]: containers originais de lista/preview, editor em grade contábil e abas. | Sem comparação visual lado a lado; o formulário adaptado e diferenças de conteúdo exigem revisão contra a referência. |
| AC36 | R07 | Multiempresa em Notas de crédito | Parcial — testes de fronteira | [Créditos e Recorrências]: propriedade explícita, ledger, A/B/principal, opções de fatura e acesso estrangeiro negado. | Notas históricas sem dono/snapshot não são adotadas automaticamente; cobertura e navegador/MySQL reais permanecem pendentes. |
| AC37 | R07 | Identidade e PWA em Notas de crédito | Parcial — arquivos e testes | [Marca] e [Contexto]; PDF e identidade canônicos preservados. | Homologar visual/PWA conforme AC06/AC07; arquivo preservado não prova apresentação em todos os estados. |
| AC38 | R07 | Evidências de Notas de crédito | Documentado; execução final pendente | [Créditos e Recorrências] lista cenários/totais cumulativos, diferenças de campos/ações, scripts browser e limitações. | Confirmar CI final e a comparação visual, mantendo as diferenças funcionais abertas. |
| AC39 | R08 | Fluxo de Itens | Parcial — implementação/testes | [Faturas e Itens]: ALL/ordenação/busca corrigidos; modal, grupos, taxas, preços, importação e ações atuais preservados. | Executar integralmente todas as ações/cenários da referência em browser com empresas/perfis; testes focados não cobrem tudo. |
| AC40 | R08 | Conteúdo de Itens | Inventariado; tabela testada | [Faturas e Itens]: oito colunas base e campos autorizados; [Inventário Vendas]. | Comparar formulário/importação/ações em todos os estados e moedas, sem inferir equivalência apenas da coluna preservada. |
| AC41 | R08 | View de Itens | Pendente — homologação visual | [Faturas e Itens]: views originais e protocolo DataTables; [Marca]. | Comparação lado a lado com mesmas condições não executada. |
| AC42 | R08 | Multiempresa em Itens | Parcial — testes de fronteira | [Faturas e Itens]: A/B/principal, 505 registros, IDs de origem, custom fields e catálogo sem mutação. | Homologar troca de empresa, modal/importação e todas as ações por perfil na instalação/MySQL/Chromium. |
| AC43 | R08 | Identidade e PWA em Itens | Parcial — arquivos e testes | [Marca] e [Contexto]. | Aplicar homologação visual e PWA de AC06/AC07 ao catálogo e seus modais. |
| AC44 | R08 | Evidências de Itens | Documentado; execução final pendente | [Faturas e Itens]:20 verificações novas, oito regressões, inventário e limites. | Anexar execução MySQL/browser do SHA final e a comparação visual restante. |
| AC45 | R09 | Inventariar todas as entradas e subentradas de Vendas | Inventariado em código | [Vendas]: seis entradas core; Recorrência é subfluxo de Faturas; módulo Asaas acrescenta entrada própria; [Inventário Vendas]. | Ativação de módulos/hooks e menu efetivo da instalação não foram inferidos; validar a instalação alvo antes de fechar cobertura universal. |
| AC46 | R09 | Comparação, adaptação e testes por entrada real | Parcial — cobertura | [Vendas], [Faturas e Itens], [Créditos e Recorrências], [Contexto] discriminam entradas, mudanças e testes. | Há ações/campos não adaptados e testes visuais ausentes; Asaas e recursos laterais preservados não significam equivalência validada. |
| AC47 | R09 | Preservar recursos sem equivalente e pedir decisão sobre alterações indefinidas | Preservado no delta; conflitos documentados | [Vendas] e [Créditos e Recorrências]: snapshots, estados, históricos, cópia/portal/ledger e regras globais conservados. | Não foi tomada decisão que atribua propriedade ou destrua histórico para forçar paridade; resolver conflitos apontados antes dessas alterações. |
| AC48 | R09 | Multiempresa, marca e PWA em todo Vendas | Parcial — cobertura | [Contexto], [Marca] e testes por área demonstram fronteiras exercitadas. | Nenhuma prova de todos os estados/módulos/integrações em browser; aplicar as pendências funcionais e de AC06/07 ao inventário inteiro. |
| AC49 | R09 | Evidências do inventário completo e diagnóstico restrito a Pagamentos | Parcial — cobertura; relato preservado | [Vendas] cobre menu real em código; [Contexto] distingue relato/reprodução e mantém a falha específica de Pagamentos. | Completar homologação por todas as entradas/recursos; não estender a outros módulos o travamento relatado. |
| AC50 | R10 | Inventário de cada área, entradas e todos os recursos reais | Inventariado em código | [Áreas nativas]/[Inventário nativo] e [Integrações]/[Inventário integrações]:controllers, métodos, views e utilidades próprias. | Inventário não comprova módulos ativados ou dados externos da instalação. Não confundir API com Connect API nem Assinaturas com recorrência financeira. |
| AC51 | R10 | Equivalência de view/conteúdo/fluxo para cada correspondência | Parcial — implementação e conflitos | [Áreas nativas]: Contratos, formulário de Despesas, Projetos e lista/modal/Kanban de Leads; [Integrações]: ACL e Kanban da Ajuda; views existentes preservadas. | NA01–NA09 e contratos de integrações têm recursos ainda sem adaptador ou diferenças de propriedade/imutabilidade; falta homologação visual de todas as áreas. |
| AC52 | R10 | Preservar recursos, APIs, Webhooks e contratos existentes | Preservado no delta; regressão parcial | [Integrações]: payloads/endpoints/eventos mantidos, ACLs testadas; [Áreas nativas] e [Créditos e Recorrências]:writers/históricos preservados. | Testes não abrangem todos os provedores/consumidores reais. Abrir módulos globais em outra empresa requer mapear propriedade, sem substituir contratos. |
| AC53 | R10 | Conservar recursos novos e sinalizar ausência de equivalente | Documentado; decisão pendente | [Áreas nativas]: NA01–NA09; [Integrações]: perfis próprios versus módulos globais; [Créditos e Recorrências]: séries/ledger imutáveis. | Decidir propriedade de históricos/instâncias e compatibilidade de edição/envio/regras antes de alterações indefinidas; não há adoção/migração presumida. |
| AC54 | R10 | Contexto, autorização, marca e PWA em todas as áreas | Parcial — lacunas funcionais e de teste | [Contexto] mais testes por área; [Marca] preserva ativos. | Assinaturas, solicitação, campos/ações restantes de Leads, mídia/logs/utilidades e módulos de integração não possuem cobertura própria integral; também falta browser/PWA real. |
| AC55 | R10 | Comparação/testes por área/recurso, Connect API e utilidades completas | Parcial — evidências catalogadas | [Áreas nativas]/[Integrações] inventariam todas as entradas identificadas e declaram provas/limites. | Faltam execução das operações globais/externas com contexto definido e comparação visual. Recursos inventariados sem implementação estão nomeados, não marcados como validados. |
| AC56 | R10 | Não atribuir travamentos às áreas ampliadas sem evidência | Atendido no registro | [Áreas nativas], [Integrações] e [Contexto] preservam o relato específico de Vendas > Pagamentos. | Achados próprios de ACL/ID/kanban são descritos pela evidência específica, sem atribuir-lhes o travamento informado. |
| AC57 | R11 | Trocar empresa sem dados residuais | Parcial — servidor/JS testados | [Contexto]: geração de sessão, abas antigas, resposta atrasada, cache, eventos de storage/visibilidade e seleção negada. | Chromium real com dois perfis/empresas e todo inventário pendente. Guard visual de aba não fornece propriedade aos módulos globais. |
| AC58 | R11 | Listas, busca e filtros somente da empresa ativa | Parcial — cobertura | Testes de listas Vendas/Contratos/Itens/recorrência e catálogos nas evidências por área; [Contexto]. Lista/filtros/resumos de Leads implementados, com teste portátil executado e gate MySQL preparado. | Cobrir áreas sem adapter, buscas globais, utilidades/exportações e todos os módulos ativos; existe lacuna funcional além de teste. |
| AC59 | R11 | Detalhes/ações e URLs diretas autorizados | Parcial — cobertura | [Contexto] gate/CSRF; detalhes canônicos de Leads/Despesas/Contratos/Crédito e documentos nas suites respectivas. | Homologar abas/registros anteriores e todas as ações por área/perfil; recursos sem contrato de empresa continuam pendentes. |
| AC60 | R11 | Contadores, totais, indicadores e relatórios | Parcial — cobertura | Totais Orçamentos por moeda/ano, resumos Contratos, saldos Créditos e diretórios/estatísticas cobertos pelas respectivas suites. | Relatórios, objetivos, exports, logs e indicadores não cobertos não são considerados filtrados apenas pelo seletor; completar leitores próprios e testes. |
| AC61 | R11 | Contexto e autorizações em APIs/Webhooks/conectores | Parcial — contratos; decisão de propriedade | [Integrações]: inventário completo, ACLs reais reproduzidas/corrigidas, leitores legados testados, Connect API próprio preservado. | Tokens, instâncias, históricos, eventos/filas globais e scope=all exigem decisão. Não testadas chamadas externas reais; sessão de navegador não substitui autenticação da API. |
| AC62 | R11 | Permissões existentes de seleção/acesso, inclusive negações | Parcial — cobertura | [Contexto]: Context real/seleção negada e gate; suites financeiras/nativas e ACL API/Webhooks exercitam autorização sem novas capacidades. | Cobrir cada perfil e área na instalação, integrações externas e revogação durante uso; testes locais não provam universalidade. |
| AC63 | R11 | Layout, fluxo e navegação de referência durante a troca | Pendente — equivalência visual e conflitos | [Contexto] mantém navegação revalidada; [Marca] ativos recentes; views originais adaptadas por área. | Sem comparação lado a lado; conflitos reais de NA01–NA09, séries/créditos e Propostas/Orçamentos precisam de decisão antes de mudanças não definidas. |
| AC64 | R11 | Empresas/perfis, áreas, resultados e limites documentados | Documentado; validação integral pendente | [Contexto] e documentos por frente identificam fixtures A/B/principal/ator, resultados e limitações; esta matriz mantém todos os64AC. | Vincular CI ao SHA final e homologação aos perfis/dados reais autorizados. Não declarar isolamento integral sem a cobertura que falta. |

## Decisões funcionais abertas e homologação

As lacunas funcionais que exigem definição estão nos documentos por frente:
vínculo de Proposta a Lead e semântica de envio/decisão/assinatura;
regras globais de recorrência versus séries canônicas; edição/reabertura de
crédito/contrato emitido versus histórico imutável; propriedade de Assinaturas,
solicitações, arquivos, logs, templates, pesquisas, metas, tokens, instâncias e
filas compartilhadas. Seus recursos permanecem preservados. Não atribuir tudo à
principal, substituir uma modalidade por outra nem abrir uma rota global para a
empresa secundária sem resolver esse contrato.

As lacunas de homologação são distintas: comparação 4.0.2/destino com a mesma
massa e viewport; navegação e operação real em duas empresas autorizadas;
negações de perfil; URLs/abas antigas; MySQL concorrente; instalação/upgrade;
PWA instalado e uso real; provedores externos quando aplicáveis. Um ambiente
habilitado pode resolver essa categoria sem redefinir o domínio. O ambiente
local recusou sockets de browser; a revisão automática também negou a execução
fora desse limite. Os testes remotos precisam ser associados ao **SHA final**.

Os testes locais descritos usam SQLite/PHP e scripts Node com fronteiras
controladas. Alguns scripts reutilizam fixtures que executam suites anteriores;
os contadores cumulativos não devem ser somados como cenários independentes.
A inclusão de um script no manifesto não comprova que o CI passou.

## Fontes da matriz

- [Vendas]: inventário e implementação de Propostas/Orçamentos e todas as entradas.
- [Faturas e Itens]: recursos, correções de tabela e regressões específicas.
- [Créditos e Recorrências]: adapters, identidade das séries, ledger e conflitos.
- [Contexto]: Pagamentos, reprodução antes/depois, troca de empresa e marca/PWA.
- [Áreas nativas]: Contratos, Despesas, Projetos, Tarefas, Suporte, Leads,
  Assinaturas, Pedidos de orçamento e todas as utilidades identificadas.
- [Integrações]: API, Webhooks, Connect API/Manager/Conector/Chat e Ajuda online.
- [Marca], [Inventário Vendas], [Inventário nativo] e [Inventário integrações]:
  hashes e inventários estruturados das fontes exatas.

[Vendas]: sales.md
[Faturas e Itens]: invoices-and-items.md
[Créditos e Recorrências]: credit-recurring.md
[Contexto]: payments-and-company-context.md
[Áreas nativas]: native-areas.md
[Integrações]: integrations.md
[Marca]: baselines.json
[Inventário Vendas]: sales-inventory.json
[Inventário nativo]: native-areas-inventory.json
[Inventário integrações]: integrations-inventory.json
