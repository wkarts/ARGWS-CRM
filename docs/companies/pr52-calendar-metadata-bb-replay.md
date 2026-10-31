# PR52 — metadado do framework no calendário e replay BB

Base conferida: d8a7b671b8cee1f6478fdb59fd2cbbc00582b33f. PWA a5ba730 e descendentes preservados. PR em rascunho; objetivo integral ainda incompleto.

## Calendário: evidência HTTP e correção do limite

Stack [40429519448](https://github.com/wkarts/ARGWS-CRM/actions/runs/40429519448), job121943513020, falhou409 no feed. O diagnóstico mostrou parser canonical, três chaves e fingerprints2b5b141660c1b079/d08045f41663b502/5caf754255b481b7. SHA256 truncado de ARGWS-calendar-key:csrf_token_name/start/end reproduz exatamente esses três valores. O navegador via apenas start/end; a inserção específica do metadado ainda não foi localizada. Não há prova de parâmetro arbitrário nem de erro nas datas.

O endpoint trata agora separadamente o metadado CSRF configurado do framework: só o consome se o nome for válido/não colidir com dados do calendário e o valor coincidir, em tempo constante, com o hash atual do framework. Valor incorreto é recusado; não é removido silenciosamente. Contexto autenticado/ACL permanecem antes do feed; CSRF/contexto obrigatórios dos POSTs não mudam. Tokens de empresa e CSRF nativo continuam proibidos na URL pelo transport e whitelist.

A query passa por decoder de campos planos, independente de arg_separator.input: nomes não são normalizados, campos duplicados e escapes inválidos são recusados antes do feed. Whitelist e limites civis/de fontes continuam intactos. Novas negativas cobrem token errado, colisão de configuração, campos duplicados, aliases por ponto/colchetes, escape inválido e tokens nativos na URL. Nenhum valor de token é registrado em log ou resposta pública.

Artefato de falha preservado: issuer-native-browser/artifact12701442580, ZIP SHA256 `1d507c06e3e32c1152af96e85c3536e1169e7f8f02015ba410f7cf948e0c82fa`; baixado e conferido, sem restaurar código. A correção ainda exige nova prova HTTP. Os novos passos do formulário de filtros não foram alcançados nesse run.

## Banco do Brasil: causa de DDL no replay

MariaDB [40426637870](https://github.com/wkarts/ARGWS-CRM/actions/runs/40426637870), job121933927388, falhou em banco_brasil_resume_443_module_tracking.php:43/94, `1.2.0 ignored marker executes no extra DDL`. O reconciliador do arquivo original fazia CREATE IF NOT EXISTS em toda chamada, inclusive depois da primeira criação130 com tracking posterior falho. A assertion foi preservada.

ReturnArchiveSchema consulta agora a metadata atual diretamente, sem depender de cache de table_exists. Se a tabela existe, valida o contrato completo e retorna sem DDL. Se é incompatível, recusa sem recriar/reparar; somente ausência real autoriza criação. Mantidos os guards de transação externa, autocommit, completion_type, InnoDB e metadata. Sem escrita de origens, contas, arquivos, eventos ou pagamentos nesse replay.

Conforme AGENTS.md, versão BB1.3.1/ModuleRelease131 e nova migration131 NOOP com validação. Migrations101–130 intactas. Testes existentes mantêm upgrade real120/121/122, falha/ignorado/readback/retry/replay e igualdade de todas as linhas/DDL históricos. Provas SQL adicionais cobrem repetição do reconciliador sem DDL, coluna incompatível recusada sem DDL/reparo e preservação de linhas. Protocolo/API/canais independentes e gates de homologação não foram ampliados. CNAB400 operacional completo continua pendente.

## Validação e limites

Na base d8a: Focused pull_request40429519402 passou PHP8.1/8.5; Interface40429519354 e PWA40429519471 passaram. Stack falhou conforme acima; Empresas e nova rodada BB ainda sem resultado integral na consulta anterior a este incremento. Esses resultados não validam o código novo.

Local: Node/FullCalendar DateEnv/callback, sintaxe JS/bash e dois testes Python executados antes da publicação. PHP/Docker/banco não disponíveis: lint PHP, novas negativas e provas SQL/HTTP/browser ainda não executados localmente. Inventários reconciliados por20 fontes existentes e uma nova migration, com hashes da base oficial76d conferidos;15083 fronteiras,4609 fontes operacionais e647 itens do auditor (613unreviewed/34partial). Novo campo de validação torna o endpoint candidato explícito no auditor. operational_ready=false;48 controllers paralelos/51 views com shell. Varredura física completa deve ser confirmada pelos geradores oficiais do CI.

Não se declara calendário aprovado, retirada integral, instalação/upgrade completos, homologação bancária ou PWA instalado. Arquivos protegidos intactos; nenhuma operação externa real, mudança no PIGE360, merge, deploy ou force-push.
