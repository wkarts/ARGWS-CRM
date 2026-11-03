# PR 52 — consolidação local e aceite verificável

## Revisão e alcance

Repositório `wkarts/ARGWS-CRM`, PR 52, branch `fix/native-company-experience`.
Baseline conferido: `1de21abe115741f709b82ce5ee3dae1ab9d177b0`.
A cópia inicial foi comparada integralmente com os 21.870 arquivos rastreados
do checkout. O histórico, inclusive o PWA de `a5ba730`, foi preservado.
Núcleo deste complemento: **4.8.0 / migration 480**, gerado pelo executor de
versionamento do projeto. A migration nova é NOOP; migrations anteriores não
foram modificadas.

**Este complemento não aprova a paridade integral da PR nem seu rollout.**
A matriz completa dos dez módulos solicitados encontra 331 métodos públicos:
192 admitidos pelo catálogo para empresas secundárias e 139 não admitidos.
Admissão de rota, presença de adaptador e aprovação de uma suíte são evidências
diferentes. Os 139 métodos não admitidos não são contabilizados como entregues,
mesmo quando há uma operação semelhante em outro endpoint. Essas diferenças
são pendências locais de equivalência; não são homologação bancária externa.

## Implementação deste complemento

### Histórico de estimativas e propostas da principal

`NativeCompanySalesDirectory` compõe a listagem de documentos próprios e
históricos, com contagem, busca, filtros e paginação antes da apresentação.
Somente a principal comprovada pode ler a origem histórica. Identificadores
canônicos separam as duas origens, inclusive quando o ID numérico coincide.
As URLs históricas e os vínculos com faturas permanecem identificáveis.

`NativeCompanyLegacySales` preserva tabelas, números, sequência, itens, totais,
pagamentos e atividade existente. A edição permitida é descritiva: notas,
termos e referência de estimativa, ou assunto/conteúdo de proposta, conforme
estado e conversão do documento. A alteração exige revisão corrente,
permissão e CSRF; a auditoria registra valores anteriores e novos. Documentos
convertidos não são reescritos financeiramente para viabilizar a edição.

Os PDFs históricos usam os valores armazenados e a identidade da origem,
sem recalcular a operação. O serviço revalida a origem antes de entregar os
bytes. A composição visual integral do template legado, incluindo todos os
tratamentos de logo e assinatura, ainda é uma pendência local; a extração
correta dos valores não comprova equivalência visual completa.

### Ações originais de contratos

Foram conectados `table`, `send_to_email`, `renew`, `copy`, `delete`,
`get_comments`, `add_comment`, `edit_comment` e `remove_comment` ao controller
original. A tabela fornece as colunas do contrato HTTP original. Mutações
exigem contexto, CSRF, revisão e chave de requisição.

A renovação cria um sucessor em rascunho e registra sua relação com o contrato
emitido. A exclusão conserva um tombstone, número, snapshot, arquivos e
discussões. Edição/remoção de comentários conserva auditoria e verifica autor
ou autoridade administrativa. Não foi introduzida exclusão física em cascata.

O envio cria uma entrada durável na fila existente para um contato ativo e
autorizado. O worker real `App_Email` revalida origem, destinatário, template,
permissão e configuração antes de usar o transporte. A confirmação incerta
permanece fechada para repetição automática. Os testes capturam os transportes
CodeIgniter e PHPMailer; não enviam e-mails externos. Cópias CC arbitrárias,
anexo opcional solicitado pelo payload legado e alteração de valor na
renovação não foram implementados nesse contrato e são recusados, sem sucesso
fictício. Os demais métodos de contratos estão discriminados na matriz.

### Cópia pelo POST original de projetos

`POST projects/copy/{id}` aceita o formulário original e compõe os escritores
existentes em uma transação: cliente de destino, datas, metadados, membros,
marcos, tarefas, responsáveis, seguidores, checklist, tags e campos
personalizados, conforme as opções. A resposta redireciona para o projeto
criado pela rota original. Dados históricos sem metadados continuam ausentes;
a cópia não inventa taxas nem incorpora horas, arquivos ou faturamento passado.

Foram cobertos sucesso, opções desmarcadas, tarefa sem responsável, repetição
idempotente, CSRF, acesso cruzado, usuário restrito, datas e rollback. A revisão
da origem é conferida novamente antes do commit.

### Pipeline, PDFs e políticas

`pipeline_load_more` entrega HTML por GET e corpo vazio por HEAD. As colunas
originais usam o mesmo reader de origem e aplicam status, busca, ordenação e
paginação por empresa. As mutações continuam exigindo CSRF.

O grupo original `Settings?group=pdf` compõe cinco formatos de documento,
fonte/tamanho e assinatura por empresa, com revisão da página inteira e
rollback. Os testes antigos de PDF, vendas e políticas passaram a usar os
controllers, grupos e parciais atuais. O recorte não declara implementada
toda opção visual histórica ausente da página adaptada.

### Regressões e fixtures preservadas

O modal original de tarefas apresenta autoria de contato comprovada, sem
inventar um colaborador de ID zero. A origem é revalidada antes da leitura.
Foram reconciliados fixtures de projetos, portal, contratos, configurações,
recorrência de despesas, ciclo de faturas e reembolso Asaas. Fixtures usam
as classes reais de runtime onde o controller as carrega; não redeclaram a
classe para ocultar suas regras. Os testes de preferências de tarefas passaram
a exercitar `Tasks::task`, `mark_as` e `change_priority`, com formulário real,
principal/A/B, estado, prioridade, texto escapado e revisão. O teste de onboarding
conserva o formulário completo anterior para verificar o conflito de revisão
após uma gravação.

O formulário original de edição de cliente conserva seu ID na ação e a origem
`source=company`. O seletor de paginação da tabela de administradores continua
usável sem ser enviado como campo de perfil; a allowlist do servidor permanece
estrita. O onboarding agora comprova gravação, avanço de revisão e conflito 409.

A tabela e os totais originais de faturas usam o helper real de vendas e o
contrato de filtros corrente, com respostas HTTP 200 verificadas no navegador.
No painel, barras de estimativas/propostas usam as cores do estado próprio e
links numéricos compatíveis com os filtros das rotas oficiais.

O demonstrativo de liquidação foi reposto dentro da prévia oficial da fatura.
Ele apresenta os valores financeiros já verificados, inclusive o principal
restabelecido quando a redução vigente e o estorno externo são ambos zero.
A regressão Banco do Brasil prova no MySQL, no PDF extraído e no parcial real
os saldos de 100,00 e 78,00, conservando itens, identidade e total fiscal.

## Matriz dos módulos oficiais

Fonte reproduzível: `php scripts/native-action-matrix.php --check`.
O arquivo `pr52-official-action-matrix.json` contém cada método, linha, verbos
admitidos e hashes das fontes. O censo original continua com 47 controllers e
710 métodos `review_required`; não foi convertido artificialmente em aprovação.

| Módulo | Métodos públicos | Admitidos para secundárias | Não admitidos |
|---|---:|---:|---:|
| Clientes | 48 | 35 | 13 |
| Contratos | 27 | 13 | 14 |
| Despesas | 19 | 4 | 15 |
| Faturas | 32 | 24 | 8 |
| Pagamentos | 11 | 2 | 9 |
| Projetos | 58 | 19 | 39 |
| Configurações | 15 | 12 | 3 |
| Equipe | 25 | 23 | 2 |
| Tarefas | 66 | 30 | 36 |
| Chamados | 30 | 30 | 0 |
| **Total** | **331** | **192** | **139** |

Exemplos que impedem aceite global: listagem/edição/PDF original de pagamentos,
várias ações originais de despesas, ações de marcos/horas/discussões de
projetos, kanban e mutações auxiliares de tarefas, extratos/exports de clientes,
assinaturas/tipos/anexos originais de contratos e opções de PDF não adaptadas.
Os serviços especializados existentes não são tratados como prova automática
de equivalência desses URLs e payloads.

## Evidências locais

Ambiente observado: PHP 8.3.6, MySQL 8.0.46, SQLite, Node 24.22.1 e
Playwright 1.55.1 / Chromium 140. Não havia Docker nem runtimes PHP 8.1/8.5
locais. Os testes usam dados sintéticos. Resultados anteriores de outro
workspace não foram promovidos a prova deste pacote.

Recibo reproduzível: `pr52-local-validation-2026-10-29.json`.
As contagens abaixo são comandos ou testes conforme indicado; há sobreposição
entre fases e elas não devem ser somadas como uma cobertura única:

- Focused SQLite: 439 comandos concluídos após correções, com 198 comandos
  adicionais cobertos por inclusões dos fixtures; alterações posteriores recebem
  reexecução focalizada registrada separadamente: 117 comandos SQLite
  aplicáveis passaram, além dos testes finais do painel e de tarefas.
  Três testes de concorrência recusam SQLite por exigir MySQL; todos são
  tratados como exclusões de motor, nunca como aprovação SQLite.
- Node: 43/43 comandos aprovados.
- Browser focused: 13/13 comandos aprovados. São os testes locais disponíveis
  de componentes/HTTP controlado, não a prova integral de instalação Docker.
- Python: 74/74 testes de cache, prefixos, anexos, versões e runners aprovados.
- Build: `npm ci --ignore-scripts` e `npm run production` aprovados; bundles
  rastreados recompilados com a versão 4.8.0.
- MySQL amplo: **399/399 comandos com resultado final aprovado**, reconciliando
  18 falhas intermediárias com reexecuções aprovadas. A retomada após interrupção
  conferiu o prefixo já executado do plano. A bateria consolidada das dez ações
  e configurações novas também passou no MySQL.
  Leads/Compras exigem 128 MiB também nos processos filhos; o limite foi
  configurado por INI temporário local, mantendo as asserções.
- Upgrade HTTP real e onboarding: os dois scripts canônicos passaram com a
  aplicação real e MySQL local. A base importada de `install/database.sql`
  recebeu as migrations reais até 480; dados anteriores não foram apagados
  para conseguir o upgrade. Apenas transporte de processos do smoke e o alias
  do banco descartável foram adaptados de Docker para processos locais.
  Foram exercitados login/upgrade com CSRF, identidade principal, empresa e
  colaborador, edição de cliente, replay/revisão, acesso cruzado, compartilhamento,
  revogação e tabela/totais de faturas; zero erros JavaScript nas execuções finais.
  Isso não substitui a instalação limpa Docker nem todos os stacks HTTP.
- Banco do Brasil: 333 verificações cumulativas no cenário de saldo restabelecido,
  com TCPDF real e `pdftotext`; o cenário exige MySQL e recusa SQLite com código 2.
- Preferências/operações de tarefas: 200 verificações cumulativas em cada motor,
  incluindo 37 verificações adicionais de padrões e 42 de prioridade.
- Painel: 55 verificações cumulativas da renderização PHP e regressão Node.
- Sintaxe: **114/114 arquivos PHP** alterados ou novos aprovados.
- Prefixos, versões e consistência dos inventários: recibos dos gates
  finais no mesmo JSON. Zero arquivos rastreados com prefixo `company_` e nenhuma
  migration histórica modificada. O inventário de fronteiras foi conferido
  numa exportação limpa do índice Git, sem configurações e logs temporários
  locais, preservando todos os arquivos-fonte preparados para o commit.

Os resultados intermediários com falha são preservados e vinculados às
reexecuções aprovadas. O diagnóstico amplo pode continuar depois de uma falha
para identificar o lote; o runner versionado do CI permanece fail-fast.
Uma negação 503 esperada em teste negativo não significa operação positiva
aprovada. A ausência de erro de sintaxe não comprova o fluxo funcional.

## Homologação externa e aceite ainda pendente

Asaas, Efí, BB API/CNAB240, conciliação idempotente e suas origens financeiras
foram preservados. Testes com respostas sintéticas não homologam credenciais,
contas, convênios, transporte bancário, cobrança ou liquidação reais.
CNAB400 permanece `operational=false`: exige documentação do layout/convênio,
aprovação do canal e arquivos de remessa/retorno homologados.

SMTP/OAuth real e a identidade visual dos PDFs reais do cliente precisam de
aceite em ambiente apropriado, após completar as composições locais ainda
ausentes. PHP 8.1/8.5 e a matriz completa Docker/HTTP dependem do CI ou de outro
runtime disponível; não foram executados localmente nesta máquina.
A falta dessas evidências não transforma os 139 métodos fechados em dependência
externa: a equivalência desses métodos continua sendo trabalho de software.

Os nove testes offline de cache foram preservados. Não houve inventário/purge
remoto, ativação na main ou exclusão de artifacts/releases/imagens/logs.
A política só pode ser avaliada em dry-run por fluxo autorizado; sua corrida
residual com o uso de caches continua documentada.

## Publicação e reversão

Publicação autorizada somente nesta branch, por push normal após reconciliar
o HEAD remoto. Não fazer merge, deploy, release, reset destrutivo ou force-push.
Manter a PR draft enquanto os critérios globais acima estiverem pendentes.
Uma reversão usa commits de revert, conservando histórico e dados; não remove
migrations anteriores nem reutiliza sua numeração.


## Critérios de aceite

- [x] Branch, histórico, PWA e entregas financeiras anteriores preservados.
- [x] Histórico de vendas: leitura/lista, edição descritiva permitida, vínculos e PDF
  com dados armazenados exercitados; equivalência visual integral ainda pendente.
- [x] POST original de cópia de projetos e pipeline GET/HEAD compostos e testados.
- [x] Novas ações de contratos com isolamento, revisão e CSRF exercitadas.
- [x] Settings PDF/políticas nos grupos atuais, com CAS e rollback testados.
- [x] Upgrade HTTP com dados anteriores e onboarding real aprovados localmente.
- [x] Matriz dos dez módulos publicada sem converter admissão em paridade aprovada.
- [ ] Contratos: payloads e opções originais completos, inclusive CC/anexo opcional
  e novo valor de renovação, além dos métodos não admitidos discriminados.
- [ ] Equivalência integral de rotas, telas e ações dos dez módulos.
- [ ] Composição integral de PDF/configurações e aceite visual dos documentos reais.
- [ ] Instalação limpa e matriz completa PHP 8.1/8.5/Docker/HTTP no SHA publicado.
- [ ] CI final do SHA publicado e homologação externa das integrações.

Os itens abertos acima impedem declarar a PR integralmente concluída.

## Reconciliação do inventário após publicação

O pacote funcional foi publicado em `adf322b60a4add87a659305a5508d034f1a0c3ad`,
com a mesma árvore `c021c6bf4d2961e948093c99970279a73cd27f3e` do checkpoint
local validado. O CI do núcleo, job `123639424867` do run `40927983817`,
identificou que o inventário operacional incluía um log PHP de execução local
não rastreado. A contagem correta da exportação limpa é **4.647 arquivos de
origem**, mantendo todos os outros contadores, candidatos e estados de revisão.

O snapshot foi regenerado a partir da exportação limpa, após conferir os 127
hashes de fontes do recibo. O manifesto resultante
`7d262cdb88688330ca9b70601d8a680f02f76bbb5e8a4585c0c292912a88698d`
coincide exatamente com o esperado pelo checkout limpo do CI. O comando
`php scripts/company-surface-inventory.php --check` passou nessa exportação.
Essa primeira reconciliação alterou somente o snapshot e sua documentação.
As correções funcionais e de fixtures encontradas em seguida estão discriminadas
abaixo; exclusões do gerador, guards e estados de prontidão foram preservados.
O resultado final do CI deve ser consultado no SHA final informado na PR.


## Complemento após os resultados do CI

O pacote inicial em `adf322b6` terminou com **20 execuções aprovadas e três
reprovadas, de 23 execuções** (incluindo duas reexecuções de versionamento).
Banco do Brasil, PWA, builds de contêineres e os demais fluxos aprovados são
evidência desse SHA inicial. Nenhum resultado anterior é atribuído
automaticamente ao SHA do complemento.

### Correções implementadas e verificadas

- **Contatos no controller original:** o grupo de contatos agora carrega
  `NativeCompanyCustomFields` antes de usá-la. O erro HTTP 503 foi reproduzido
  com a aplicação e o banco reais do fixture local.
- **Formulário original de contatos:** a apresentação nativa carrega a função
  existente `custom_fields_hyperlink` e inicializa seu catálogo de templates.
  O helper aceita o tradutor já atestado da apresentação; o caminho legado
  conserva seu tradutor padrão. A mensagem JavaScript é codificada como JSON.
  A ausência do helper interrompia a preparação do envio AJAX e terminava em
  HTTP 422. A allowlist de entrada não foi afrouxada.
- **Smoke de contatos/portal:** usa o grupo e o modal originais para criar e
  editar contatos. Mantém as verificações de e-mail duplicado entre A/B,
  recuperação, troca de senha, CSRF, replay, isolamento e revogação de sessão.
  O botão de saída é selecionado explicitamente, sem confundi-lo com o de
  abertura de chamado.
- **Memória dos fixtures:** três testes liberam os rastros SQL já verificados;
  o teste de formatos libera também os objetos PDF encerrados. As asserções,
  os dados do banco e o limite de **128 MiB** foram mantidos.

O smoke HTTP completo de contatos/portal passou em **34,07 segundos**, com
PHP/MySQL e Chromium locais, sem entrega externa de e-mail. O componente de
apresentação passou em 1280, 768 e 390 pixels, sem erros JavaScript. O fixture
PHP de apresentação continua recusando hooks/opções globais, e os contratos
Node e as views de cliente também passaram.

No PHP 8.3 local, os picos MySQL dos três fixtures ficaram em aproximadamente
**80 MiB** (cópia de projeto), **88 MiB** (controller de recorrência de despesa)
e **58 MiB** (PDFs de vendas). Os três passaram também no SQLite. O wrapper
MySQL das dez ações da PR passou em **166,257 segundos**, com 128 MiB herdados
pelos processos filhos. Esses números não substituem a reexecução do CI no
PHP 8.1 que apresentou a falha original.

Os cinco inventários foram regenerados e conferidos numa exportação limpa do
índice Git. O manifesto operacional após as correções funcionais é
`ab1cf1522cf5dfa7462e287b75247c4579aa707995719e1bbcecf659a94eef89`.
O manifesto `7d262cdb…` acima identifica a reconciliação anterior, antes dessas
correções de fonte. Nenhuma regra de prontidão ou método não admitido foi
promovido a aprovado.

### Falha técnica remanescente na suíte HTTP ampla

A continuação canônica `tests/companies_invoices_browser.cjs` foi executada
no mesmo banco sintético após o portal. Ela **falhou** ao esperar
`#invoice-client` na listagem original de faturas. O script ainda usa o
editor embutido anterior; as rotas atuais apresentam a listagem e o formulário
originais separadamente. As asserções antigas foram preservadas, sem excluir
o teste, reintroduzir a tela removida ou converter o timeout em sucesso.

A migração completa desse smoke para os controles/payloads atuais é uma
**pendência local de validação**. Os cenários seguintes da cadeia HTTP ampla
não receberam resultado nesta retomada. Isso não invalida os testes focados
já aprovados, mas impede declarar a suíte de stacks e a PR integralmente
concluídas. Não depende de banco, SMTP ou homologação externa.

O recibo JSON preserva os resultados anteriores, as tentativas com falha,
os hashes de fontes do pacote inicial e os hashes atualizados do complemento.
Os resultados do SHA final publicado devem ser consultados na própria PR.
