# PR 45 — RH por empresa e composição nativa 456

## Escopo integrado e preservação

Este incremento parte do HEAD remoto conferido `144956f439046febe3cbc17285c9b8a6ee1f8cca`, na mesma branch `feat/banco-brasil-billing-cnab240`. O gerador oficial elevou o núcleo para **4.5.6 / migration 456**. A composição conserva Leads 454, Compras 455, o master comprovado da instalação e a empresa principal. Todas as fontes dos módulos permanecem byte-idênticas ao 455, e nenhuma migration publicada foi reescrita.

O RH integrado possui estrutura, hierarquia, vínculos, perfil privado, registros, contratos, documentos, treinamento, acesso do participante, caixa de avisos locais, importação e relatórios por empresa. O contrato integral em `continuity-contract-2026-10-21.md` continua aberto. Ações legadas bloqueadas, arquivos candidatos de outras frentes e a presença de módulos no repositório não são apresentados como funcionalidades concluídas.

## Operação efetivamente disponível

O usuário autorizado configura unidades, cargos, locais e tipos de contrato próprios, vincula uma identidade já elegível naquela empresa e acompanha o organograma. A relação de trabalho é separada da identidade global de login. Mudanças de vínculo ou perfil privado não alteram nome, senha, administrador, papéis ou dados de outra empresa. Referências cruzadas, ciclos de hierarquia e desligamento incompatível com subordinados ou contratos ativos são recusados.

Há sete recursos de ACL, com permissões distintas para o diretório, dados sensíveis, registros, contratos, treinamento, arquivos e uso pelo próprio empregado. O diretório de um membro ordinário não hidrata os dados privados. Treinamentos e materiais atribuídos, submissão, resultado, repetição idempotente e leitura de avisos são operáveis pelo destinatário autorizado. O gabarito não é enviado ao participante.

Contratos conservam a versão ativada, geram PDF e recebem confirmação interna do destinatário. Essa confirmação registra ciência interna; não certifica assinatura digital com efeito jurídico. Convites públicos usam segredo protegido, validade, revogação e autorização restrita ao vínculo e ao objeto. Sessão mínima e CSRF permanecem ativos no acesso público; a API bearer tem escopos separados e não obtém autoridade de sessão administrativa.

A GUI inclui registros de escolaridade, dependentes com decisão, premiações/disciplinas, checklists de entrada/saída, equipamentos e mudanças de remuneração. Arquivos são privados e autorizados pela origem. A importação CSV de vínculos tem prévia e confirmação; não representa importação XLSX de todo o legado. Oito relatórios e suas exportações usam dados e ACL da empresa atual. Os contratos de campo, limites, 13 endpoints de API e 16 escopos estão detalhados em `hr-456.md` e nos relatórios M2/M3 preservados.

O worker compartilhado executa os seis processadores reais. O RH publica avisos locais opt-in, com origem, ator e credencial persistidos. Cada entrega revalida a autorização atual e confirma auditoria, recibo e estado antes do commit. Revogação, expiração ou substituição de token não transfere os avisos antigos para a credencial nova. Essa entrega não inclui envio SMTP.

## Provas executadas sobre a composição

| Prova | Resultado e limite |
| --- | --- |
| Domínio, API, bootstrap e caminho bruto | **628 verificações próprias + 80 herdadas = 708 cumulativas por runtime**, em dez suítes. MariaDB 10.11.14/PHP 8.3.6 e MySQL 8.0.46/PHP 8.1.2, ambos com 128 MiB. |
| Integridade e concorrência | Processos independentes, espera real por locks, revogação durante espera, conjuntos de linhas alterados tardiamente, origem e recibo reconferidos, SIGKILL após escrita real, rollback e repetição. |
| Migrador nativo 456 | **10.222 condições adicionais por runtime**: predecessor 455 realmente executado, falha em cada um dos 18 DDLs, seed falho/ignorado, marcador falho/ignorado, retry/replay e preservação histórica. A suíte própria de readback do seed contém 40 condições e já integra as 628 acima. |
| Upgrade HTTP 455→456 | Browser nativo original em 6,417 s; comparação somente de leitura em 0,186 s: **1.001 condições, 320 tabelas históricas preservadas, 17 novas tabelas de negócio vazias e cursor exato `[1,0,0,1]`**. O predecessor tinha operações reais de Compras da fixture 455. Master 17 e principal 1 preservados. |
| RH depois do upgrade | Browser completo em 98,870 s, com 12 grupos de cenários A/B/principal; teste adicional de tokens em 23,620 s, com **221 verificações**. |
| Instalação realmente vazia | MySQL independente, zero tabelas antes do instalador. POST nativo válido em 14,311 s, CSRF inválido 403 sem DDL, marcador 456 e rotas de bootstrap recusadas. Browser original em 16,532 s. PHP 8.1, funções de processo shell desabilitadas. |
| RH depois da instalação | Mesmos 12 grupos em 171,828 s e mesmas 221 verificações de tokens em 31,560 s. Produto idêntico ao usado no upgrade. |
| CLI positivo nativo | **223 verificações**, em 21,149 s, chamando `php index.php company_recurring_worker`: nove avisos novos publicados, 18 origens inválidas bloqueadas, nove avisos futuros aguardando, três atribuições opt-out sem aviso e repetição sem duplicação. Preservação de 333 tabelas e dos registros anteriores de auditoria. HTTP 404 sem efeito. |
| Worker compartilhado preservado | `expense_recurring_cli.php` passou nos dois motores, com **228 condições cumulativas**, seis processadores reais e os 18 schemas de RH presentes. |
| Estáticos | 75 comandos, incluindo 105 testes Python, versão canônica, sintaxe e gates de Compras/RH. Depois da inclusão do CLI, as duas asserções de ambiente alteradas foram executadas novamente e passaram. |

Não se somam motores, repetições, contadores herdados ou a suíte de seed repetida para inflar cobertura distinta. O teste CLI encurta apenas a validade de credenciais sintéticas já emitidas e atribuídas; espera o UTC real. Ele não chama o processador diretamente, injeta relógio, zera cursor nem omite os outros cinco processadores.

## Correspondência entre fontes e provas

SQL final, upgrade e instalação usam a árvore `fa76d0b285de678b1c1e3eea618d4029874548dc`. A execução nativa anterior usa `f6c8a57158fc483383e95b662eecf4a596a7304b`; o relatório de equivalência comprova que todos os arquivos de aplicação, módulos e gates nativos são idênticos. A diferença inclui evidências publicadas do 455, inventários e integração de CI.

O CLI positivo usa `a452443b8bd76d9703ec5d722252bbb0e79ed187`, que acrescenta os dois arquivos de prova CLI, o bloco de execução no wrapper e quatro inventários. Todos os arquivos de produto permanecem iguais. A árvore de publicação acrescenta a documentação, artefatos e a correção estrita da fixture Python de ambiente descrita abaixo. O manifesto final deve conferir essa equivalência antes da publicação.

Cada execução HTTP verificou **17.235 arquivos de aplicação e módulos**, antes e depois. O baseline 455 foi capturado antes de qualquer upgrade, com backup SHA256 `b243f3e345ab0e9b30d4777b7e4af762271ef3d5b372db41f3120c1d671af2f9`. Todas as linhas, DDLs, identidades e triggers das 320 tabelas coincidiram na restauração. A comparação histórica foi executada antes dos novos cadastros de RH; esses cadastros posteriores são efeitos positivos esperados, não divergências apagadas.

O harness local usa PHP-S, Chromium e um adaptador finito dos comandos de fixture. Não executa Docker e não comprova uma imagem de produção. O CI remoto conserva seu provisionamento próprio. Nenhuma prova local equivale a deploy, validação na instalação do usuário ou homologação bancária.

## Falhas reproduzidas, correções e limites

**Cursor do migrador.** A execução RED comprovou que um INSERT ignorado do seed podia permitir o avanço do marcador. `CompanyHrSchema` agora possui transação, locks e readback do singleton. Seed falho/ignorado, linha extra/corrompida e alteração tardia abortam a atualização. Um cursor válido que já avançou conserva seu progresso. Os 18 DDLs não foram alterados por essa correção. A execução GREEN cobre ambos os motores e o executor nativo completo.

**Fixture de ambiente do CI.** O teste Python anterior supunha que todo bloco posterior ao primeiro browser usaria a instalação atualizada. O novo CLI precisa da instalação fresca, onde as demais filas estão vazias. A falha foi preservada; a fixture agora reconhece explicitamente esse único bloco, mantendo os sete parâmetros de ambiente e as expectativas históricas dos demais comandos. Nenhum guard de produção foi relaxado para satisfazer o teste.

**Ambiente local.** A primeira execução das suítes finais foi impedida pelo sandbox ao abrir sockets locais, antes da conexão SQL. O diagnóstico independente observou `EPERM`; a execução autorizada passou com exatamente os mesmos bytes. As tentativas e saídas originais permanecem no JSON consolidado. A instalação fresca usa outra instância MySQL criada vazia, sem reaproveitar um marcador de uma fixture antiga.

Os screenshots selecionados documentam organograma em 390 px e uso pelo próprio empregado. O organograma não apresentou overflow horizontal; o menu completo no celular continua longo. Essa inspeção não é uma auditoria universal de usabilidade ou acessibilidade.

## Inventário e continuidade obrigatória

Os quatro inventários foram regenerados em uma composição exclusiva de 456, sem absorver os arquivos candidatos de Finance, Jornada, Folha, Tags ou Contratos de Compra. Há 38 módulos presentes nas fontes, 14.586 arquivos de fronteira, 12.064 candidatos a consumidores, 684 referências literais e 145 dinâmicas de opções de módulos. Esses números não comprovam módulos ativados em produção nem ações concluídas. `operational_ready=false` e o bloqueio de rollout permanecem.

O inventário `hr-remaining-action-inventory.json` conserva a enumeração de métodos legados e passa a vincular as cinco famílias positivas de RH às provas nativas 456. **Continuam internas:** Q&A/grupos/respostas e correio; tags/anexos/importação de cargos; procedimentos por função e campanhas de treinamento; templates HTML livres; XLSX; catálogos de remuneração e cálculo de Folha; Jornada e suas estatísticas; Metas. Artigos publicados não são paridade integral de Q&A, checklists não são automação completa por cargo e confirmação interna de contrato não é assinatura digital.

Também permanecem ações de Compras, Finance/Accounting, outros módulos, Settings e seus consumidores, paridade de portal/API/relatórios e as pendências bancárias independentes de documentação. As frentes candidatas passam pelos seus próprios gates antes da integração; seus resultados não são transferidos automaticamente para este SHA.

As seis dependências externas bancárias continuam específicas: BRL do CBR641 nas posições 85–86 por contrato; juros efetivos ou isenção inequívoca no CNAB400; largura/escala e totalizadores do CBR643 nas posições 26–39; identidade estável, composição, datas e reversões da API 18/19; domínios e exemplo normativo do PATCH de juros/multa; contrato de transporte com endpoint, protocolo, autenticação e recibo. O trailer CBR641 resolvido permanece preservado. Nenhuma dessas dependências impede o trabalho interno ainda enumerado.

Evidências completas: `evidence/resume456-current.json`, relatórios históricos M2/M3 e artefatos selecionados em `evidence/native-456/`. SHA, árvore, pai e ref serão conferidos após avanço normal da mesma branch. Não há merge, deploy, force-push, operação bancária real ou declaração de conclusão integral neste incremento.
