# Revisão da PR #53 — 04/11/2026

Esta revisão mantém a branch `release/argws-crm-4-8-5-revisao`, destino `main`, sem merge, deploy, release, publicação de imagens ou alteração do estado de draft. Não houve chamadas a provedores financeiros reais. A equivalência funcional integral com 4.0.2 **não está concluída**.

## Reconciliação e referências

- HEAD remoto inicial confirmado: `d950852bb0009d5cb8daa285d5f82682c9475192`.
- Main confirmada: `3c35555a94e7ba7eaaad8abd7a78a59c48504d7b`.
- PR aberta, não draft, sem merge. Checkout inicial limpo; nenhum arquivo histórico foi restaurado sobre a branch.
- ZIP conferido novamente: `42c910b99c2e0306be5d50d5e401b0739ed54cc0b1f4488c980f09a798ac5e7d`, 191.386.872 bytes, 22.108 entradas.
- Os 78 arquivos originais da PR foram comparados: 77 idênticos ao ZIP; CSV de ações com diferença de quebras de linha. Os arquivos adicionais da main foram preservados. A árvore Git não equivale ao ZIP inteiro.
- `modules/einvoice/composer.lock` fixa Mustache v2.14.2. O `.gitignore` do módulo exclui `/vendor`; `release-packages.yml` executa Composer com lockfile, sem scripts/dev, e verifica o autoload das duas classes. Os 51 arquivos vendor do ZIP não devem ser copiados para Git por essa diferença. A instalação Composer/release completa não foi executada nesta rodada.

## Correções

1. Gerador oficial `python3 scripts/apply-release-version.py 4.8.5`: sincroniza `deploy/production/compose.yaml` e `deploy/production/.env.example`. Mantém VERSION 4.8.5/migration 485 e todas as migrations históricas.
2. Build oficial do workflow, `npm ci --ignore-scripts --no-audit --no-fund` e `mix --production`: regenera os seis banners de JavaScript. Conferência binária mostrou alteração somente no banner; nenhum minificado editado manualmente. Primeiro commit publicado: `c0e8efdc9f3eb8cb15297c94163bb67b12dabce2`.
3. Cron: `translate="no"` permanece limitado ao item técnico, agora no elemento de lista. O rótulo mantém o contrato de apresentação original; testes de PT-BR continuam ativos. Cronômetros de tarefas não foram renomeados.
4. Fixture de Settings: substitui a chamada obsoleta `render(array)` sobre renderizador de políticas por composição do **pai oficial** e da **view SMTP real**, com armazenamento PDO sintético. Testa navegação completa do catálogo admitido, escaping, revisão, CSRF, segredo não hidratado, formulário único, readonly e restauração de buffers após erro. O TypeError original foi reproduzido em checkout separado da main.
5. Browser da mesma fixture atualizado para os seletores oficiais; adicionadas verificações de erros JS/assets. Workflow de interface passa a executar a fixture PHP, a paridade de navegação e esse browser, sem retirar testes existentes.
6. Pesquisa de vendas: em MariaDB, JSON_REMOVE com caminho privado cujo pai não existe podia produzir NULL e eliminar resultados válidos. Remoção agora condicionada à presença de cada caminho; não há fallback que reintroduza valores privados. Novos casos verificam texto público com/sem campos privados e a pesquisa mantém issuer/kind, wildcard literal e lock.
7. Comparação de valores JSON de auditoria: resultados idênticos de JSON_EXTRACT podiam comparar como diferentes no MariaDB. Extração escalar com comparação binária nas conexões MySQL/MariaDB preserva a identidade exata; SQLite mantém sua extração nativa. Aplicada ao histórico de vendas e aos recibos de idempotência existentes. Teste de histórico rejeita outro tipo de documento e variação de caixa no mesmo issuer/subject.
8. Fixture MySQL de meios manuais: inclui a DDL original de despesas e o schema real de rascunhos, exigidos pela transação. Nenhuma exigência InnoDB foi removida. Incluída no agregado MySQL de ações originais; não foi duplicado comando entre fases do manifesto.

## Diagnóstico do CI inicial

Os 17 workflows iniciais tinham 4 sucessos, 6 falhas e 7 cancelamentos. Logs completos foram retidos durante o trabalho; as URLs abaixo preservam a origem:

- Containers [41821148255](https://github.com/wkarts/ARGWS-CRM/actions/runs/41821148255): metadados de distribuição 4.8.4.
- Núcleo [41821148260](https://github.com/wkarts/ARGWS-CRM/actions/runs/41821148260): mesma inconsistência. A mensagem de reparo sem `--base-dir` é saída esperada de teste negativo; 56 testes passam após a correção de metadados.
- Focused [41821148217](https://github.com/wkarts/ARGWS-CRM/actions/runs/41821148217): comparação dos assets gerados; preflights browser já aprovavam, mas não provavam os fluxos funcionais posteriores.
- Stacks e Empresas: gates reprovados pela dependência do preflight compartilhado.
- Monitor: cancelou a rodada após as falhas raiz. Cancelamento não foi contado como sucesso.
- Após o primeiro commit, Containers chegou a uma asserção de markup do rótulo Cron, corrigida preservando o teste e a proteção de tradução. Versão, build/Node e focused avançaram; o CI do commit final deve ser consultado separadamente, não herdado do primeiro commit.

## Matriz funcional

“Implementado” abaixo refere-se ao fluxo delimitado com evidência citada, não à homologação completa do módulo. Os testes locais usam dados sintéticos e I/O de framework substituído. Os fluxos explicitamente indicados como HTTP/Chromium abaixo foram executados em instalação descartável completa no CI; não representam homologação de provedores externos nem de todos os módulos.

| Recurso da 4.0.2 | Melhoria 4.8.4/4.8.5 preservada | Principal | Secundárias A/B | Evidência e pendência |
|---|---|---|---|---|
| Meios de pagamento manuais | IDs de origem, issuer_id, revisão e auditoria | Implementado no serviço; histórico preservado | Implementado no serviço; CRUD/negação cruzada | `native_payment_modes.php`, SQLite e MariaDB; UI HTTP completa não testada |
| Gateways | Credenciais e adaptadores próprios de Asaas/Efí/BB | Parcial: formulário de instalação preservado | Parcial: diretório somente leitura e integrações admitidas | `native_settings_controller.php`; sem operação de provedor real; não equivale a todos os gateways antigos |
| Definições gerais, empresa e localização | DTOs próprios, ACL, revisão e assets | Implementado nos adaptadores testados | Implementado nos adaptadores testados | `native_settings_forms.php`, `native_settings_controller.php`; sondagem HTTP dos grupos em principal/A/B aprovada no SHA aba7d33; gravações completas ainda sob a suíte |
| SMTP, Google/Microsoft e fila | Segredos isolados, estado OAuth vinculado, worker revalida | Parcial | Parcial | `settings_email.php`, fixture oficial e workers; sem autenticação/envio externo |
| Cron, atualização e plataforma | Controles de instalação protegidos; termo Cron | Preservados; homologação da instalação pendente | Não aplicável como configuração global da empresa | Não copiar credenciais ou controle global para secundárias |
| Assinaturas, notas de crédito, leads, E-Sign: configurações | Formulários globais recuperados na principal | Parcial | Ausente como conjunto equivalente de formulários próprios | Não confundir operação nativa de créditos/leads com todas as suas configurações |
| Vendas, contratos e histórico | Origem explícita, números/IDs separados, preservação do legado | Parcial; casos sintéticos de histórico/edição/PDF | Parcial; casos de CRUD/ACL/concorrência | Suítes `sales`, `native_sales_history`, `native_pr52_actions_mysql`; cobertura não abrange todas as ações |
| Faturas, despesas, pagamentos e PDFs | Livro financeiro e recibos próprios, snapshots e idempotência | Parcial | Parcial | Suítes focused SQLite/MySQL e HTTP/Chromium A/B: faturas, recibos manuais, créditos, despesas e PDF aprovados em aba7d33; nenhuma transação financeira externa |
| Anexos, exportações e tarefas em segundo plano | Proveniência, permissões e revalidação | Parcial | Parcial | Anexo privado upload/preview/download e revogação no portal aprovados por HTTP/Chromium em aba7d33; workers/exports no manifesto; mídia global/exportação em lote não equivalentes integralmente |
| Relatórios | Relatórios nativos por empresa | Parcial | Parcial | Catálogo/serviços preservados; subações legadas não integralmente adaptadas ou testadas |
| Contabilidade e financeiro legado | Código e menus principais conservados | Não testado em aplicação instalada | Ausente no despacho administrativo multiempresa | Controladores dos módulos sem adaptação integral; não liberados por simples menu |
| Frota, compras e RH completos | Correções de views e melhorias existentes mantidas | Não testado em aplicação instalada | Ausente no despacho administrativo multiempresa | `accounting`, `finance`, `fleet`, `purchase`, `hr_payroll`; funcionalidades nativas pontuais de compras/RH não substituem os módulos completos |
| ACL, CSRF e acesso cruzado | Política por issuer, ator, recurso e verbo | Parcial, com casos negativos executados | Parcial, com A/B e restrições executadas | Não é certificação de segurança de 2.743 ações |
| Identidade visual e PWA | Arquivos e mecanismos existentes preservados | Não testado como PWA instalado | Não testado como PWA instalado | Contratos do CI não substituem instalação desktop/mobile e cache/logout real |
| Instalação e atualização | Caminho de migrations preservado | Implementado nos cenários descartáveis executados | Onboarding e seleção A/B executados | CI aba7d33: instalação limpa, upgrade de schema antigo, login e navegação desktop/mobile aprovados; não cobre todo banco legado de produção |

## Inventário de ações

`matriz-acoes.csv` relaciona todas as 2.743 ações do levantamento da 4.0.2 ao arquivo atual e à admissão nativa. 2.742 métodos foram localizados; 425 ações são admitidas pelo catálogo inspecionado. A ausência de `Essential_modules::index` é histórica e vinculada ao onboarding, não remoção desta revisão.

A classificação por ação é deliberadamente conservadora: presença/rota não prova funcionamento. Portais/webhooks fora do catálogo administrativo ficam como não testados; métodos de controladores administrativos dos cinco módulos legados sem despacho multiempresa ficam como ausentes **nesse atendimento**, embora seu código continue na principal. A matriz não converte teste estático em homologação.

## Limites concretos

- Ainda faltam adaptações completas dos módulos legados e configurações indicados acima. Abrir suas rotas agora permitiria executar consultas/gravações globais; a revisão não fez essa liberação.
- Chromium local foi obtido, mas a inicialização falhou com `socket() failed: Operation not permitted` no processo singleton. O job browser remoto 41844529810 passou no SHA d453e8e, incluindo a nova fixture de Settings. No SHA aba7d33, os grupos HTTP avançaram por instalação/upgrade e navegação autenticada desktop/mobile; PWA instalado e paridade integral continuam sem homologação.
- MariaDB 10.11.7 foi executado em banco descartável `issuer_native_module_audit`, exclusivamente por TCP. Isso não comprova todos os comportamentos de MySQL 8, que permanecem sob o CI correspondente.
- PHP local 8.3.6; os jobs remotos usam as versões fixadas 8.1/8.5. Foram necessários pacotes de timezone legado para `Brazil/East`; a falha anterior ao ajuste era do runtime local, sem mudança na validação da aplicação.
- Resultados históricos em `docs/revisao-4.8.5/resultados-*` permanecem intactos. Os recibos nesta subpasta identificam somente execuções desta rodada.

## Execuções locais desta rodada

| Verificação | Resultado |
|---|---|
| Versão, pacote, reparo, deploy e provisionamento (Python) | 78 testes aprovados |
| Política de migrations dos módulos | 44 testes aprovados |
| Manifesto focused e seu fail-fast | 13 testes aprovados |
| Focused PHP/SQLite | 443 processos aprovados; 199 verificações adicionais executadas por inclusão estática de fixtures, totalizando os 642 comandos catalogados |
| Focused JavaScript | 45 comandos aprovados |
| Contratos complementares Node, inclusive Cron e Settings | 39 testes aprovados |
| Settings oficial PHP | 139 verificações acumuladas na fixture, aprovadas |
| Contexto/bootstrap | 137 verificações aprovadas |
| Sintaxe PHP | 6.901 arquivos rastreados próprios da aplicação, zero erros; PHPs alterados também conferidos após as últimas edições |
| Build Mix | Aprovado e reproduzido sem diferença nos assets após a geração inicial |
| MariaDB real | 20 comandos aprovados; recibos em `mariadb-execucoes.json` |
| Chromium local | Impedido pelo socket Unix do ambiente; não aprovado localmente |

A suíte SQLite foi seguida por regressões direcionadas do endurecimento final de tipos JSON; a rodada MariaDB final usa esse endurecimento. A validação remota da árvore publicada é obrigatória e pode revelar outras falhas. Os números acima não representam execução de cada ação da matriz, nem homologação externa.

## Reconciliação complementar do CI

A revisão dos 13 hashes, os inventários regenerados e a correção da fixture de contexto em MySQL estão documentados em [revisao-inventarios.md](revisao-inventarios.md). Todos os bloqueios de prontidão operacional permanecem ativos.

## Regressões HTTP após os preflights

No SHA `aba7d3321af1750644bb495a5c319fa5d3d5ec8d`, a execução real de stacks revelou três causas adicionais. Os logs completos de seis jobs falhos e do grupo banking aprovado estão em `http-intermediario.json.gz` (run 41847769895). Não são resultados do commit posterior.

- Cronômetros: o helper global selecionava o primeiro botão `timer_action`, que agora pertence ao dropdown oculto do cabeçalho. O helper foi direcionado ao controle da tarefa aberta, com seleção estrita. Timeout, resposta POST, sucesso e revisão continuam exigidos. Probe DOM sintético falha antes e passa depois; aprovação HTTP depende da nova rodada.
- Importação/faturamento: o contêiner do layout oficial foi trocado de `section` para `div`, preservando classes e `data-company-workspace`. Cada tela mantém sua seção funcional e seus formulários originais. A renderização real de importações com I/O sintético cobre 28 verificações, inclusive preview, confirmação, multipart, ownership de tokens e modo somente leitura; falha antes e passa depois.
- Preferências Asaas: o browser exigia a classe do antigo layout separado e o script Bootstrap avulso. Agora exige `body.native-company-admin`, o bundle oficial `vendor-admin.js`, plugin Bootstrap funcional e uma única inclusão de `native-admin-tabs.js`. Os testes de CSSOM, abas, teclado, viewport, CSRF, A/B e ausência de mutações permanecem; 20 testes locais do contrato passaram. Não foi acrescentado um asset duplicado à aplicação.

As alterações de layout tiveram seus hashes regenerados pelos três geradores oficiais; nenhum limite foi promovido para pronto. O gate de prontidão continua bloqueado (648 não revisados, 30 parciais). A verificação oficial dos inventários passou.

Foram executados oito comandos direcionados (todos aprovados), incluindo 39 testes Node e as fixtures PHP de Settings, navegação, layout, cabeçalho e contexto de rota. `http-layout-local.json.gz` preserva logs e resultados, inclusive as reproduções anteriores às correções.

Evidência HTTP adicional de aba7d33: onboarding, certificado, contatos/portal, faturas/PDF/recibos/anexos, créditos e projetos/Gantt passaram nos trechos anteriores às falhas. O job banking passou integralmente, incluindo adaptações BB/CNAB, callback e preferências de tarefas, com transportes sintéticos e sem homologação bancária. Isso não transforma os jobs de timers, despesas, projetos e provedores em aprovados.

## Meios de pagamento: correção funcional adicional

O callback real `manage_payment_modes` sempre executava `JSON.parse(response)`. O controller nativo responde `application/json`, portanto o jQuery entrega um objeto: o cadastro podia persistir, mas o callback lançava `SyntaxError` antes de atualizar a tabela e fechar o modal. A mesma decodificação incondicional já existe na main; não foi atribuída exclusivamente à PR.

A view agora decodifica somente respostas em texto, preservando o backend legado e o retorno JSON nativo. A reprodução executa a função real da view: antes, os dois casos de objeto falham; depois, os seis contratos de transporte/formulário passam. O controller e o serviço passaram por 154 verificações acumuladas em SQLite, incluindo ACL, issuer, revisão, rollback, referências históricas e CSRF.

Foi adicionada a invocação 103 (104 no total) ao grupo HTTP banking: `tests/companies/payment_modes_browser.cjs`. Ela usa somente os formulários reais e registros manuais descartáveis, sem criar pagamentos. Cobre principal/A/B, criação, descrição literal, edição, desativação, exclusão, atualização da tabela/modal, ausência de erros JS, contexto e revisão obsoletos, acesso cruzado, CSRF nativo ausente e rejeição de GET mutante. As consultas diretas são apenas SELECT; nenhuma gravação de fixture substitui o CRUD da interface. A execução HTTP desse teste continua pendente até a conclusão do CI do commit que o contém.

O plano mantém todas as 103 invocações anteriores e acrescenta a nova com um único responsável; 12 testes de cobertura/particionamento passam. Logs locais estão em `meios-pagamento-local.json.gz`.

## Mesmo catálogo protegido na empresa principal

A revisão do caminho real mostrou que a principal ainda entrava no controller legado de meios manuais. `CompanyRequestScope` agora admite o catálogo finito de `paymentmodes` pelo mesmo adaptador das secundárias; verbos não admitidos recebem 405, e POST exige contexto e CSRF antes da hidratação. Não se liberam rotas de módulos globais.

Para preservar consumidores antigos, a criação de meios na **principal comprovada** mantém `payment_modes` e IDs simples. Esse armazenamento de compatibilidade já pertence exclusivamente à identidade principal; leitura/gravação passam por ACL, lock, revisão e auditoria vinculada ao issuer. As secundárias continuam em `company_payment_modes` com `issuer_id` e IDs separados. Meios próprios já existentes na principal continuam visíveis. Não houve migração, renumeração, duplicação nem exclusão de dados. Essa compatibilidade é explícita: não se afirma que todas as tabelas legadas passaram a ter uma coluna `issuer_id`.

A escolha evita criar meios invisíveis aos leitores legados de faturas/despesas da principal. O teste real de navegador confere os dois armazenamentos, verifica negação principal→A e A→B, e continua sem transações financeiras. Contratos PHP passaram em SQLite e MariaDB 10.11.7: contexto 151 verificações e serviço/controller 160 verificações acumuladas. Os 12 contratos Node de formulário/transporte também passaram. Recibos em `meios-pagamento-principal.json.gz`; CI HTTP permanece exigido.

## Rodada MariaDB complementar

Uma execução adicional do catálogo de 472 comandos completou 208 com sucesso e parou no comando 209 (`asaas_refund_schema.php`) pelo limite local de 180 segundos. Os demais comandos não foram executados nessa rodada; ela também atravessou alterações intermediárias, portanto não é recibo da árvore final. O caso foi reexecutado em banco MariaDB novo e passou tanto no código atual quanto na main, sem ampliar timeout ou retirar verificações. Não foi reproduzida uma regressão nesse caso isolado; a suíte longa local permanece incompleta. Resultados e logs foram preservados em `mariadb-complementar.json.gz`. O CI MySQL/PHP 8.1/8.5 continua obrigatório para o SHA publicado.
