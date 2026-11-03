# Staff e retorno da atualização nas rotas oficiais

Base964d0cf4dd11b53c2a01d0ab439a619d36348720. PR52 em rascunho; nenhum merge/deploy/force-push ou serviço externo.

O formulário original de Staff contém uma tabela que chama projects/staff_projects. Seu controller e leitor escopado já existem; a lista finita de NativeCompanyRoutes não admitia esse POST. Adicionado somente esse método. Não foram liberados index/view/project/delete nem navegação para o endpoint. ACL de Staff, associação explícita do projeto, identidade da empresa, validação de colunas e CSRF continuam no serviço existente.

App_Controller retorna antes de inicializar current_db_version no caminho isolado. O dashboard secundário usava essa propriedade nula para comparar o recibo emitido depois da atualização, portanto não mostrava a confirmação. AdminDatabaseUpgrade::contextVersion consulta somente a linha de metadata de migrations sob o selo exato do controller; exige uma única versão numérica. O dashboard usa a versão persistida para peek e consumo final após render/ACL. XHR/HEAD não consomem, ator/TTL/versão continuam obrigatórios. Não se adicionaram modelos ou hooks globais ao bootstrap.

Testes existentes ampliados: contrato finito POST da tabela; metadata válida/malformada/ausente/ambígua; recusa antes da consulta para owner não atestado; flags de consulta e sessão preservadas. O smoke autenticado exige200/aaData da tabela real e mantém criação Staff, ausência de admin global, pertencimento à empresa,409 para formulário antigo, três viewports e confirmação de upgrade de uso único. Marcadores distinguem essas fases, evitando atribuir o último marcador de navegação à causa posterior.

Evidência anterior: b88a96c passou no [focused pull_request40456654805](https://github.com/wkarts/ARGWS-CRM/actions/runs/40456654805) em PHP8.1/8.5. [Stack40456654897](https://github.com/wkarts/ARGWS-CRM/actions/runs/40456654897), job122031445783, passou pelo CRUD real de itens e formulário antigo; depois registrou5 Ajax errors da tabela de Staff e falhou na asserção de aviso de sucesso da atualização. A rejeição issuer_access_legacy_permissions_schema_unrecognized daquele log é o teste negativo intencional de schema inválido, não um diagnóstico automático de schema atual.

Antes do push: revisão conjunta de rota→controller→serviço→tabela e upgrade→recibo→dashboard; CJS compilado em V8,149 linhas com assertions (antes147), JSONs de inventário parseados, hash individual do dashboard atualizado sem mudar classificações. Não foi executado PHP/Node/banco/browser local: executor indisponível. Validação real depende dos workflows existentes no novo SHA e da reconciliação dos manifests gerados completos. Nenhum timeout/cobertura/guard reduzido.

PWAa5ba730 e arquivos bloqueados intactos. Há48 controllers paralelos/51 views ainda não removidos, CNAB400 integral e requisitos restantes abertos; operational_ready=false.
