# PR52 — filtro no calendário oficial e provas históricas

Base remota conferida: `a72ae6d58783ce529561915f510776c0eb6e2509`, PR52 aberta/em rascunho, sem merge. PWA a5ba730 e todos os descendentes preservados.

## Alteração funcional

O formulário já existente `admin/utilities/calendar_filters.php` faz POST à mesma rota do calendário. O adapter nativo tratava todo POST como criação/edição de evento. Agora distingue o marcador explícito `calendar_filters`, valida contexto/CSRF/ACL e a seleção fechada de fontes, e renderiza a mesma página oficial com os filtros submetidos. Filtrar não grava preferências nem eventos. Campo desconhecido, ausência de marcador, GET ou contexto antigo são recusados. Acrescentado o checkbox nativo de lembretes de tarefas quando a opção existente o permite.

O smoke autenticado passa a submeter esse formulário real, conferir feed filtrado, ausência de escrita e rejeição do formulário após trocar a empresa. Não é alegada execução desse novo trecho antes do CI.

## Falha HTTP ainda aberta na base

- Stack [40426637872](https://github.com/wkarts/ARGWS-CRM/actions/runs/40426637872), job121933927069: criação do evento no widget passou; feed GET com start2026-09-27/end2026-11-08, sem parâmetros desconhecidos no navegador, foi recusado409 na etapa query_keys.
- A mudança para REQUEST_URI da base a72 não resolveu a falha. Este incremento não declara a causa nem a correção do feed comprovadas.
- Para discriminar o contrato no runtime web, o log do controller registra versão do contrato, classificação canonical/noncanonical de um parse_str sintético, quantidade de chaves e no máximo16 hashes truncados de nomes. Não registra nomes de chaves arbitrárias, valores, URL, tokens, descrição ou conteúdo financeiro. Resposta pública permanece genérica. ACL e parser fechado mantidos.

## Preparação histórica, não downgrade de produção

Empresas [40426637846](https://github.com/wkarts/ARGWS-CRM/actions/runs/40426637846), job121934418331, falhou ao tentar recriar a tabela migrations no executor443. A fixture pai já fornece exatamente uma linha447. Os seis filhos históricos440–445 compartilham agora um preparo explícito e restrito ao prefixo descartável essential_10hex_, CLI e DSN MySQL: exigem essa linha/coluna exatas, ajustam apenas o marcador sintético anterior antes da captura e confirmam leitura persistida. Não inserem uma segunda linha nem removem tabela/histórico. Mantidos todos os negativos de DDL, metadata, gravação falha/ignorada, retry, replay e comparação integral de linhas. Executores446 em diante continuam exigindo o marcador real produzido pelo executor anterior, sem esse preparo. Nenhuma migration histórica ou executor de produção alterado.

## Verificação e limites

Executados localmente: handlers compartilhados e callback real FullCalendar5.6.0/DateEnv, sintaxe Node do smoke, bash-n do provisionamento, dois testes Python de inventário e reconciliação de17 fontes contra artefato oficial76d9ba4 (proveniência nos checkpoints anteriores). Inventários mantêm15082 fronteiras,4608 fontes operacionais,646 itens no auditor e operational_ready=false. Não é alegada varredura física de checkout completo.

PHP/Docker/banco indisponíveis localmente: lint PHP e novas provas PHP/SQL/HTTP/browser dependem dos workflows existentes. Na base a72, Focused PR40426637819/push40426630959 passaram em PHP8.1/8.5; Interface40426638116 e PWA40426637880 passaram. BB40426637870: unitários8.1/8.5 e checkout passaram; jobs MySQL/MariaDB ainda em andamento na consulta anterior à publicação. Não são resultados deste incremento.

Remoção integral da aplicação paralela, Staff autenticado completo, instalação/upgrade/migrations de toda a matriz, ciclo PWA instalado e demais integrações continuam sem aceite integral. BB mantém dois canais independentes e opt-in por empresa; CNAB400 operacional completo depende das lacunas documentais e de implementação já descritas em banco-brasil-cnab400.md. Sem homologação, cobrança, SMTP, webhook real, merge, deploy ou force-push. Arquivos protegidos intactos.
