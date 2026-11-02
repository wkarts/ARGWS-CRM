# Complemento da PR #52

**Branch:** `fix/native-company-experience`.

**Título:** `fix(ci): corrigir validações e reduzir fila e trabalho repetido da PR #52`

A validação demorava mais de uma hora e interrompia cenários posteriores em falhas sucessivas. Este complemento corrige os erros reproduzidos, distribui os 101 comandos HTTP entre oito bancos isolados e limita a concorrência das matrizes SQL para deixar capacidade aos navegadores. Todos os comandos, versões PHP/banco e checks obrigatórios permanecem.

## Correções

- POST de faturas legadas e validador remoto usam o mesmo escopo de numeração. O driver comprova o novo destino persistido sem repetir o POST legado. PDF legado segue a opção global atual; PDF nativo conserva o snapshot, com dimensões verificadas nos bytes reais.
- Rotas exatas da API precedem o wildcard legado. Os aliases públicos de compras reconhecem o nome de controller resolvido pelo CI, mantendo a validação da URI original e os vetos de módulo, método e autorização. Contas a pagar renderizam o link tipado da despesa original.
- Contexto da principal preserva origens legadas e encaminha registros próprios aos adapters delimitados. Callbacks Google/Microsoft negados removem o protocolo da URL sem trocar códigos. Asaas carrega a criptografia no contexto isolado.
- Leitura de equipes respeita atribuições nativas ativas, revogação e ACL. Upload de chamado usa o status nativo existente. Pusher considera atribuições explícitas e invalida notificações após remoção ou mudança de época.
- Editor de fatura preserva o algoritmo de emissão e os impostos históricos da própria linha; novas seleções continuam verificadas no catálogo. Confirmação de Settings é vinculada a empresa, ator e grupo.
- Banco do Brasil corrige o fechamento excedente da view e a recusa isolada que chamava helper global. Versão 1.3.6, nova migration nativa 136 NOOP; histórico preservado.
- Fixtures carregam classes transitivas com `require_once`, fornecem a sessão e o PDO reais de seus transportes simulados e restauram exatamente os dados modificados pelos novos casos. Navegadores conferem os controles e links atuais, com as assertivas financeiras e de segurança preservadas.

- Importação privada aceita os tipos MIME CSV/JSON reais, conserva a extensão e a validação de conteúdo e recusa sufixos desconhecidos com 404. O botão existente da lista de Clientes também fica disponível com a permissão nativa de criação.
- Edição de uma fatura própria da principal não carrega a mesclagem exclusiva de secundárias. Despesas tipadas e a seleção explícita da coleção própria chegam ao adapter existente da principal; registros legados conservam seu caminho, inclusive a cópia com ID físico na URI. POSTs do protocolo de cópia próprio, mesmo malformados, não caem no modelo legado. Formulários próprios mantêm o seletor na URL e os três níveis de proteção.
- Chamados comprovadamente abertos por contatos recuperam resposta interna, responsável e estado na rota original. O adapter `CompanyPortalSupportStaff` exige a origem persistida e ausência de metadados nativos, faz CAS/recibo/auditoria na mesma transação e não atribui autoria de funcionário ao cliente. Históricos sem essa origem continuam sem adquirir autoridade de escrita.
- Recorrência resolve a atribuição nativa ativa na leitura e congela o responsável confirmado sem preencher a coluna legada. Remoção, perda de ACL ou múltiplos responsáveis bloqueiam novas execuções; projeto e tarefa avulsa têm provas MySQL e Chromium.
- Calendário aceita a ausência histórica de fonte externa (`calendar_id IS NULL`) da principal, mantendo o frame bruto na revalidação e o armazenamento intacto. IDs não vazios inválidos continuam negados; quatro novos checks cobrem a fronteira. Recusas da página registram somente códigos delimitados.
- A atribuição em lote de Tarefas exibe nomes de funcionários como texto literal antes do helper de selects que aceita HTML. A prova de equipe também navega nessa tela com o nome contendo `<script>`, preservando o rótulo e recusando execução de JavaScript.
- O controller original de equipe converte recusas de ACL/CSRF/proteção em 403, revisão/conflito em 409, entrada inválida em 422 e armazenamento não confirmado em 503, sem expor exceções. Sete casos adicionais exercitam o tratamento; a principal conserva seu CSRF dedicado e o formulário legado.
- Fixtures posteriores verificam IDs tipados e atribuições ativas atuais; datas de projetos/tarefas são preenchidas no formato da empresa. As provas de recorrência configuram explicitamente o transporte sintético e a preferência de notificações do contato.

## CI, GHCR e desempenho

Uma imagem da árvore Git exata é construída e exportada para os oito workers HTTP; cada um provisiona sua própria stack e banco. Rust/Compose executam ao lado dos navegadores. Pré-requisitos repetidos sem dependência real foram removidos do planejamento. Marcos/horas/Gantt, compras e RH foram redistribuídos entre grupos; as sequências dependentes continuam juntas. Cinco pré-requisitos sem consumidores foram retirados de tasks-domains; os cenários independentes 92–95 passaram para sales-pdf.

Os 60 comandos MySQL e 72 MariaDB do Banco do Brasil são conferidos por contagem e SHA-256 e distribuídos em quatro bancos por variante. Verificador e checkout permanecem no mesmo worker. Os checks `mysql`/`mariadb` exigem os quatro comprovantes vinculados ao commit/run atual. Núcleo continua com 472 comandos e sua matriz PHP 8.1/8.5.

Núcleo usa até quatro workers simultâneos e cada variante bancária até dois. Com oito HTTP, duas versões focadas, tooling e monitor, o patamar longo reserva até 20 runners. Grupos demorados aparecem primeiro na matriz. Os quatro workflows de diagnóstico terminam seus shards do mesmo head após uma falha; a rodada continua reprovada, e workflows auxiliares e heads obsoletos são cancelados. Matrizes também são inspecionadas quando o GitHub reporta o workflow como queued. Matrizes SQL têm limite de 30 minutos; suíte focada mantém 20. Falha, cancelamento ou cobertura incompleta não passam.

Sete workflows adicionais reutilizam as bases GHCR PHP 8.1/8.5 existentes por digest. Bases são reconstruídas quando seus inputs mudam ou por atualização manual explícita, sempre com smoke real. Caches npm/Chromium/Rust/Buildx e compilação paralela reduzem preparação. A construção da imagem levou 46 segundos na rodada 6829ea87 e 183 segundos na rodada 2151777b; esses números não representam o tempo global.

Os dois servidores HTTP PHP usam o OPcache já incluído na imagem, com verificação de timestamps ativa e intervalo zero. Uma prova HTTP verificou o cache compilado e observou imediatamente a alteração de um arquivo, inclusive mantendo seu tamanho. Não há nova dependência nem reconstrução de base por essa configuração. Os testes de temporizadores preparam explicitamente o estado inicial e usam o namespace real de cada modalidade; a criação repetida reaproveita a página original, fechando o modal pelo controle da GUI, sem excluir pares ou assertivas.

## Validação

- 76 contratos Python de CI e 13 do monitor aprovados; planejamento confere 101 invocações HTTP e orçamento de concorrência.
- Contratos rápidos: 12 casos de numeração,4 de formato PDF,47 de roteamento API,45 de leitor por origem persistida,21 de callback negado e 59 de fronteira de compras.
- Fase SQLite local: 443 execuções completas e 199 verificações por inclusão, todas aprovadas. Os14 entrypoints MySQL focados passaram localmente. Os118 comandos do shard 4 do núcleo foram cobertos na execução e continuações direcionadas em PHP 8.3/MySQL8.0.
- Chromium/MySQL: provedores e TLS;15 alvos bancários; portal de tarefas, marcos, horas, Gantt e arquivos; preferências de chamados; configurações/recorrência; faturamento de despesas; relatórios/API; compras 96–98; RH 99–100. Tokens de RH incluem221 verificações.
- Novas fronteiras do editor: 239 checks SQLite/235 MySQL; Pusher: 35 adicionais em cada banco; leitura atômica do portal: 140 SQLite/142 MySQL. Contagens cumulativas não devem ser somadas como cenários únicos.
- Correções posteriores: estornos204/258 checks por banco, importação118 SQLite/121 MySQL, atendimento136 SQLite/139 MySQL e recorrência164 cumulativos MySQL. Chromium completo: importação43, suporte30–32, recorrência45, despesas77/78 e recorrência de tarefas92 (seis combinações A/B/principal × projeto/avulsa). Equipe93 também passou integralmente em113,8s, leads94 em97,1s e95 em2,3s/58 checks bearer. São execuções direcionadas, sem comprovante de rodada completa.
- Os quatro inventários gerados são reconciliados e conferidos, preservando a classificação de prontidão operacional.

Execuções locais direcionadas não equivalem ao CI completo. A matriz remota do commit final e o ganho global ainda precisam concluir. Evidências, rodadas e limites em [pr52-validation.md](pr52-validation.md); arquitetura em [standalone-browser-groups.md](standalone-browser-groups.md).

## Auditoria e compatibilidade

A coleta de 02/11/2026 às 07:19UTC encontrou 7.506 registros de workflows desde 25/10/2026 às 19:43:59UTC. São registros de workflows, não testes ou minutos faturados. A PR abriu em 25/10 às 21:02:18UTC. Fontes/contagem diária em [pr52-validation-audit.json](pr52-validation-audit.json).

Este complemento não adiciona DDL de produção: a migration136 é NOOP. O núcleo 4.8.4/484 já supera a base 4.7.3/473; seus passos históricos permanecem. A prova estática não substitui tracking real nem homologação financeira. Entrega na PR existente, sem merge, release ou implantação. Filas/caches variam; tempo global só será declarado após a rodada completa.
