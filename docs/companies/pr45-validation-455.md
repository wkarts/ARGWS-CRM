# PR 45 — cadeia de compras por empresa e preservação das regressões (455)

## Escopo publicado e continuidade

Este incremento parte do HEAD remoto conferido `5b1a7b6ff880082f339e4dbc43066acd79de460b`, na branch `feat/banco-brasil-billing-cnab240`. O gerador oficial elevou o núcleo para **4.5.5 / migration 455**. Todos os arquivos de módulos são byte-idênticos ao 454: Finance permanece 1.0.1/101, Accounting 1.3.6/136, BB 1.2.3/123 e Asaas 1.5.3/153. Nenhuma migration publicada foi reescrita.

A entrega implementa a cadeia própria de Purchase e corrige três fixtures históricas que falharam no CI 454. O contrato integral continua em `continuity-contract-2026-10-21.md`; este incremento não encerra RH, Finance/Accounting, os outros módulos ou as pendências bancárias. Rotas recusadas não são contadas como ações disponíveis. Os inventários permanecem com `operational_ready=false` e rollout bloqueado enquanto houver consumidores e ações pendentes.

## Compra efetivamente operável

A equipe pode configurar numeração e câmbio explícitos, cadastrar fornecedores, criar e aprovar requisições, registrar ou receber cotações pelo portal restrito, emitir pedidos e registrar recebimentos parciais. O fechamento do documento do fornecedor seleciona recebimentos ainda não faturados e cria, na mesma transação, **uma despesa canônica e um título a pagar**. Recibos, auditoria, revisão e vínculos são reconferidos antes do commit.

O pedido conserva snapshots da empresa, fornecedor, itens, preços e taxa declarada. O documento e o AP preservam a origem até os recebimentos. Uma despesa vinculada a Purchase não pode ser alterada ou cancelada pelo CRUD genérico de despesas. O veto cobre inclusive AP com empresa inconsistente e associação inserida ou alterada depois da última auditoria/recibo de API, com rollback de todos os efeitos.

A declaração de quitação externa registra um pagamento que já ocorreu fora desse fluxo; sua correção acrescenta uma reversão vinculada ao evento original. Essas ações não enviam ordem bancária, devolvem fundos ou duplicam custo, caixa ou contas a receber. O saldo deriva do histórico de eventos. A interface apresenta essa semântica, a moeda e os limites de cada ação.

O convite do fornecedor usa segredo protegido por hash, vencimento e revogação, limitado à empresa, fornecedor e requisição. O segredo chega pelo fragmento do link e é trocado em POST. Portal e GUI preservam CSRF; anexos são privados e passam pela autorização da origem. Há PDF do pedido, posição de AP por data, CSV e API com nove escopos separados. Nenhum envio SMTP é inferido da criação do convite.

O contrato por ação, a precisão monetária, a política de anexos, os verbos, os escopos e as limitações encontram-se em `purchase-455.md` e `purchase-455-api-matrix.md`. O inventário legado está em `purchase-455-legacy-inventory.json`.

## Evidência reproduzível

| Prova | Resultado e alcance |
| --- | --- |
| Domínio SQL final | **292 verificações distintas por runtime**, nas mesmas nove suítes: cadeia 46, superfícies 28, schema 13, integridade 39, concorrência 22, API 38, coleções alteradas tardiamente 17, matriz de origem 61 e veto de despesas 28. MariaDB 10.11.14/PHP 8.3.6 e MySQL 8.0.46/PHP 8.1.2, com 128 MiB. |
| Concorrência e recuperação | Processos independentes, espera de locks observada, revogação durante a espera e SIGKILL depois do INSERT real da despesa. Retry confirma uma única composição; rollback não é confundido com ausência de gaps no AUTO_INCREMENT. |
| Executor nativo 455 | **6.775 condições adicionais por runtime**, a partir do predecessor 454 realmente executado: falha em cada um dos 13 DDLs, marker falho/ignorado, readback, retry, replay e preservação de todas as tabelas e linhas históricas. |
| Upgrade HTTP final | Browser original, login e POST nativo com CSRF em 5,453 s; comparação somente de leitura em 0,291 s: **957 condições, 307 tabelas históricas e 13 novas tabelas vazias**. Master 17 e principal 1 preservados. |
| Compra por HTTP final | Browser completo em 125,496 s, com A/B/principal, formulários, conta ordinária, portal do fornecedor, arquivos, AP, CSV/PDF, API e revogação. Usa os arquivos finais do produto, conferidos antes e depois. |
| Bearer sem sessão | Prova adicional em 3,296 s, com jar vazio em cada requisição, GET/HEAD/POST/replay, negativa sem bearer, revogação e tabela de sessões intacta. Cookie CSRF nativo de resposta não é autoridade e não é reenviado nas chamadas positivas. |
| Eventos AP por HTTP | Prova adicional em 4,296 s, com lista, detalhe, cursor, HEAD, positivos A/B, negativa cruzada e sessões preservadas. |
| Instalação vazia | Instalador nativo 455 em 14,880 s e browser original fresco em 16,032 s, sobre MySQL 8: CSRF inválido 403 sem DDL, POST válido 200, master do instalador, principal e bootstrap HTTP recusado. O schema e o código de migração são os mesmos do gate final. |
| Bootstrap e rotas | 285 verificações adicionais / 480 cumulativas; 36 casos de caminho bruto; execução real de 32 rotas e seis negativas. PHP 8.1 e 8.3. |
| Validação estática | 105 testes Python, versão canônica, sintaxe de shell/PHP/JavaScript e invariância das fontes durante a execução. |

Não se somam motores, repetições e contadores herdados para aumentar a cobertura distinta. As nove suítes SQL finais e o HTTP completo usam a árvore de runtime `569eecc5aaabc4ae4fe0bf27ab07f96ea63629a3`. A árvore `805a8e0ead780224a138c890639cdc94b9018545` acrescenta documentação e inventários; todos os arquivos de aplicação, módulos, testes e workflow são iguais aos da árvore de runtime.

O gate nativo SQL foi congelado antes das últimas correções de negócio. Migration, schema e testes nativos permanecem byte-exatos. Depois da instalação fresca inicial, mudaram somente `CompanyExpenses.php`, `CompanyPurchases.php` e a view `purchases.php`; as provas finais de upgrade e cadeia de compras já usam essas correções. Não se apresenta o browser fresco anterior como uma execução integral de todos os arquivos posteriores.

O harness local usa PHP-S, Chromium e um adaptador finito de comandos de fixture. Ele não executa Docker nem comprova uma imagem de produção. O workflow remoto executa seu próprio provisionamento. Nenhuma dessas provas constitui homologação bancária, deploy ou teste na instalação real do usuário.

## Defeitos reproduzidos e corrigidos

As evidências próprias conservam RED/GREEN para rotas equivocadamente inseridas em comentário, aliases do caminho bruto, alterações tardias no conjunto de eventos AP, veto incompleto à edição da despesa, overflow de texto longo no celular, lista de eventos anunciada mas rejeitada e detalhe de evento sem reconferência do conjunto AP pai. A correção de responsividade mantém o texto e passou em sete telas, nas larguras de 390 e 320 px.

Também foram corrigidos o recibo de comando sem vínculo obrigatório ao objeto e a auditoria canônica capturada depois de adulterada. Há provas negativas, inclusive com trigger SQL real; onde não foi preservada uma execução anterior falhando, ela não é inventada no relatório. Correções do harness — cache de existência de tabela, preservação do insert_id observado, ALTER de FK compatível com MySQL e prefixo de fixture dentro do maxlength — estão distinguidas das correções do produto.

### Três regressões históricas do CI 454

- **Privacidade:** a fixture passou a definir APPPATH antes de carregar o modelo contábil e seu serviço de origem. O erro foi reproduzido nos dois PHPs; as 50 invocações de lint/teste desse workflow passaram em cada um. Nenhum guard ou modelo de produção foi relaxado. O PHP 8.5 do workflow hospedado não é apresentado como runtime local testado.
- **Subrecursos operacionais:** a fixture seleciona os cinco namespaces que declara, em vez de qualquer escopo global com dois separadores. A asserção original de 21 escopos permanece; seis novas negativas impedem incluir escopos de arquivos de Leads nos tokens operacionais de A/B/principal. As quatro suítes pertinentes passaram nos dois motores.
- **Recorrência e seguidores:** o teste anterior pressupunha sempre zero seguidores no filho. A suíte de preferências anterior havia habilitado auto-follow na principal, e o contrato existente cria o seguidor do ator persistido. O mesmo erro foi reproduzido com a sequência original. A correção confere política própria 0/1, IDs exatos de seguidores, JSON de preferências intacto e todos os demais históricos vazios. Passaram as seis combinações A/B/principal × tarefa de projeto/avulsa. Produto e preferências não foram alterados para satisfazer o teste.

Resultados do CI 454 e dessas correções são registrados separadamente. Eles não antecipam o resultado do CI do novo commit nem certificam a conclusão integral do multiempresa.

## Incidente do ambiente e recuperação verificável

A primeira rodada final de MariaDB falhou ao validar metadata após recriar uma tabela. O diagnóstico encontrou tabelas de teste já descartadas que reapareciam em information_schema, enquanto SELECT e SHOW CREATE retornavam erro 1932, sem a tabela no InnoDB. Os arquivos físicos tinham ctime posterior ao mtime, e havia sincronização ativa no workspace. O servidor posteriormente deixou de responder; as falhas e os logs foram preservados. A coluna específica da primeira divergência não foi capturada, portanto não se atribui a ela uma causa exata retrospectiva.

Foi criada outra instância MariaDB, com datadir fora desse diretório, mesmas versões e privilégios limitados. As nove suítes passaram sem modificar um byte do produto ou das asserções. O HTTP interrompido não foi declarado concluído: A/B haviam terminado, e a principal falhou na leitura de metadata.

O backup genuíno 454, SHA256 `5cb967692c034a3faacf519be1e068445118a5e60721e1ac223469d024ded0be`, foi restaurado em dois bancos novos. DDL e hashes completos das linhas das 307 tabelas, identidade e triggers coincidiram. Um banco permaneceu como baseline somente de leitura; o outro percorreu novamente o upgrade nativo 455 e toda a cadeia HTTP. Os bancos antigos não foram reiniciados, limpos ou apresentados como um clone exato da instalação interrompida.

A conferência integral de fontes antes do novo HTTP detectou ainda 12 arquivos de Finance/Accounting já publicados no 454 que não tinham sido copiados para a antiga aplicação de teste. Essa tentativa foi interrompida antes de qualquer HTTP. A cópia recebeu exatamente os bytes publicados, e a nova execução conferiu todos os arquivos de aplicação e módulos antes/depois. Isso corrige a preparação da fixture, sem alteração do produto versionado e sem apagar a qualificação das provas anteriores.

## Inventários, publicação e trabalho seguinte

Os inventários foram regenerados em composição exclusiva de 455, sem os arquivos em elaboração de RH, Finance, Worktime, Tags ou contratos de compra. Registram 38 módulos presentes no código, 14.563 arquivos de fronteira, 11.971 candidatos a consumidores, 684 referências literais e 145 dinâmicas de opções. Esses números não verificam quais módulos estão habilitados na instalação do usuário, nem contam funcionalidades concluídas.

As provas e hashes estão em `evidence/resume455-current.json`, com referências a `purchase-455-results.json`, `privacy-bootstrap-455.json`, `scope-fixture-455.json` e `recurring-follow-455.json`. O SHA do commit, sua árvore, seu pai e a ref da PR devem ser conferidos após a publicação por avanço normal. A publicação não autoriza merge, deploy, tag ou force-push.

**Pendências internas:** contratos de compras, catálogo/estoque, devoluções, recorrência de compras, contatos de fornecedor e notificações; RH e seus submódulos; operação positiva restante de Finance/Accounting; demais módulos instalados; paridade por ação de núcleo, portal, relatórios e APIs; Settings, tags e seus consumidores. As dependências de transporte externo não justificam adiar esses componentes locais.

**Pendências internas bancárias:** reversão da base tarifária 240; composição de tarifa, juros, descontos, abatimento, IOF, outros e parciais; adapters; alteração nominal com revisão financeira/fiscal; protesto, sustação, cancelamento e negativação com estados, outbox e confirmação independente.

**Dependências externas específicas:** código BRL do CBR641 nas posições 85–86 aplicável a conta/convênio/carteira/variação; taxa efetiva ou isenção inequívoca de juros herdados CNAB400; errata da largura/escala e totalizadores do CBR643 nas posições 26–39; identidade estável do crédito e composição/datas/reversões da API 18/19; domínios e exemplo normativo do PATCH de juros/multa; contrato do transporte, incluindo endpoint, protocolo, autenticação e recibo. A solução já entregue do trailer CBR641 permanece preservada.
