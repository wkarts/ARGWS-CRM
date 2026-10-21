# Validação HTTP do candidato 4.4.6 / migration nativa 446

O candidato fecha a recuperação de Task defaults e Calendar e corrige o roteamento nativo de Empresas. O roteador HMVC inferia o módulo arquivado `modules/issuers` pela existência da pasta, selecionando controller/helper antigos. `App_Router` agora resolve `/admin/issuers/...` no núcleo administrativo e devolve 404 para a rota pública aposentada. O teste usa os roteadores reais e o módulo arquivado presente no disco: RED antes, 29 verificações GREEN depois. Não houve inclusão artificial de helpers no menu.

O relatório estruturado é [http-resume446.json](evidence/http-resume446.json). As fontes de produção foram vinculadas ao manifesto de 18.558 caminhos, SHA256 `4edd4e9c37903f7a3c31731732fea41e39ee054aae2dd6ccba90122639cc37ef`. Cada runtime verificou 18.544 arquivos de produção antes/depois; os 14 arquivos do instalador foram removidos após sua conclusão, conforme o fluxo suportado. Não houve diferença de código.

| Cenário | Registry GUI | Task CJS | Calendar CJS | ACL restrita B |
|---|---:|---:|---:|---:|
| Instalação nativa fresh 446, mestre 17 | 9,289 s | 32,601 s | 77,647 s | 1,890 s |
| Upgrade nativo real 445 → 446, mestre 17 preservado | 9,547 s | 30,988 s | 79,348 s | 1,973 s |

Ambas as suítes completas terminaram com exit 0. Task e Calendar mantiveram as asserções originais e fixtures somente de leitura; no upgrade, uma cópia diagnóstica de Calendar apenas acrescentou captura de HTML/URL no `finally`. Todas as empresas, clientes, fontes financeiras, projetos, tarefas, contratos e o funcionário restrito foram criados pelos formulários nativos. Não houve limpeza SQL de preferências para reiniciar testes.

Calendar verificou 21 fontes GUI, 16 campos próprios, cinco visualizações, opt-out inicial, cores/primeiro dia/limites, filtragem de IDs e contagens por ACL, permissão de configuração separada e revogação atual. Task cobriu padrões próprios A/B/principal, criação em projeto/avulsa, campos omitidos, replay, atribuição explícita e revisão/contexto obsoletos. Os faults de última auditoria usaram testemunhas MyISAM observáveis e exigiram rollback exato seguido de retry. Os snapshots de fontes, finanças e todas as opções globais permaneceram íntegros.

O instalador fresh concluiu em 3,610 s, vinculando o mestre `17:installer`; CSRF inválido devolveu 403 antes de DDL. O cenário anterior executou realmente o instalador 445 e preparou empresas pela GUI (10,535 s), trocou o código e executou o formulário nativo de upgrade com CSRF (2,224 s). As 286 tabelas históricas tiveram hashes das linhas anteriores preservados. A comparação qualificou explicitamente o login acrescentado, quatro auditorias nativas e os metadados `upgraded_from_version`, `update_info_message` e `last_updated_date`; as imagens ROW do binlog confirmam os valores anteriores das opções, sem excluir tabelas inteiras da comparação.

O cadastro principal retornou 200 e salvou a identidade canônica sem redesignar o mestre; A/B usaram o shell isolado. O funcionário de B recebeu 403 ao consultar A/principal e ao omitir CSRF; um formulário válido de B não alterou A. Ao final do upgrade, um probe assertivo serial confirmou Calendar A/B/principal 200 com bindings de produção antes/depois iguais.

Foram preservados os REDs anteriores: um Calendar GET 503 não reproduzido na leitura isolada, um probe inicialmente não assertivo que registrou principal 503, e um fresh Task 500 acompanhado de SIGSEGV do MariaDB 10.11.14 em criação/remoção de tabela temporária de `SHOW COLUMNS`, seguido de conexão recusada. Não se atribui uma causa não comprovada a todos os episódios. O harness final usa webroot efêmera fora da árvore sincronizada e `--tmpdir` exclusivo por cluster, com execução serial. O mesmo banco fresh foi recuperado, sem reinstalar ou reescrever preferências, e a suíte original inteira passou. As tentativas e seus hashes constam do JSON.

Este documento atesta o conteúdo do candidato. A publicação e a verificação dos checks do SHA remoto pertencem ao responsável pela PR; estes resultados locais vinculados ao manifesto não afirmam aprovação de workflows remotos. Não houve merge ou deploy.
