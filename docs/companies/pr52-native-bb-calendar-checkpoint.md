# PR52 — continuação nativa e validação

## Publicações anteriores preservadas

- PWA `a5ba730aa853c4dcae780faa35d59a74ab6f27bd`: sem convites automáticos.
- Dashboard/calendário `f4ee432e8b6a76dc5de8c1edce73f3f7733f920f`:
  helper de cores, assets, DTO do calendário e modal condicionado à permissão.
- Canais BB `207d19325e93653a139f66f1cb8a84ea7d72456f`: API e CNAB
  independentes, opt-in explícito por empresa e bloqueio de nova emissão.

## Composição deste incremento

Banco do Brasil passa a herdar `init_head`, sidebar e `init_tail` oficiais,
inclusive na revisão financeira LOCAL. Removidos o uso de
`companies/layout_head/layout_tail` no módulo, o documento HTML/CSS independente
da revisão LOCAL e o segundo seletor de empresa dentro do BB. O seletor da
sidebar permanece a autoridade. A mesma apresentação vale para principal e
secundárias. A admissão do módulo é finita por ação/verbo, com CSRF nativo
adicional ao CSRF BB e ao token original de contexto.

O comportamento de guias existente foi movido para um único asset compartilhado
`native-admin-tabs.js`; removido `company-context-tabs.js`. O script atende o
layout oficial e, durante a remoção restante, os consumidores antigos; preserva
teclado, foco, ARIA e fragmentos. Não cria outra camada visual.

BB1.2.9/migration129 NOOP exige núcleo4.7.4 e preserva schema, credenciais,
decisões dos canais e origens históricas. Não há operação bancária real.

O bootstrap do calendário aceita a ausência de timezone numa empresa nova com
default público UTC, sem herdar a empresa principal nem o timezone mutável do
processo. Aceita os mesmos aliases IANA do validador de Definições; valor
explicitamente inválido permanece erro. Os formatos ausentes usam os defaults
públicos já existentes. A falha real de stack em207d193 foi
`native_calendar_configuration_invalid` após seleção de empresa nova.

O feed compartilhado ganha um leitor histórico separado por linhagem, com as
mesmas URLs e DTO nativo: documentos/eventos/lembretes principais só entram
após provar a empresa principal, escopo relacional e ACL. Consultas repetidas
confirmam o frame antes da entrega. Nenhum model global ou hook é usado para
completar lacunas. A admissão da página principal no renderer nativo completo
continua pendente; o teste desse leitor não equivale a esse aceite.

## Resultados conhecidos em207d193

| Validador | Evidência |
| --- | --- |
| Focused pull_request | [40397095873](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397095873): aprovado, PHP8.1/8.5, MySQL aditivo, SQLite e templates Chromium. |
| Focused push | [40397088642](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397088642): aprovado, mesmas matrizes. |
| Interface/inventários | [40397096206](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397096206): aprovado; o gerador PHP confirmou o inventário reconciliado. |
| PWA | [40397095926](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397095926): aprovado. |
| BB | [40397096305](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397096305): unitários8.1/8.5 e checkout aprovados; MySQL/MariaDB ainda ativos na consulta. |
| Stack | [40397095993](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397095993): reprovado no timezone vazio do calendário; artifact12688465040, SHA2561668b18fb9593dab25b76fcbcdd8174a377cb9e5de53748ddedc6fd2656d67e4. |
| Empresas | [40397095832](https://github.com/wkarts/ARGWS-CRM/actions/runs/40397095832): shard8.1/4 esgotou128MiB em TCPDF ao testar tipografias de vendas. Artifact12686813721. |

O teste de tipografias libera cada instância TCPDF antes da próxima construção,
incluindo a instância herdada do setup, e coleta ciclos após exportar os bytes.
Preservadas todas as combinações/fontes/conversões e verificações históricas.
Não aumenta memória/timeout nem modifica fontes ou biblioteca. Precisa de
confirmação no CI; não há aprovação antecipada da correção.

## Preflight e limites

Contratos Python de gateways, migrações, inventários e testes Node disponíveis
passaram. Lint PHP, bancos e Chromium não estão disponíveis neste executor.
O runner Node completo parou no teste que invoca PHP (`spawnSync php ENOENT`);
isso não é execução aprovada nem falha funcional demonstrada. Browser local
continua impedido pelo sandbox de sockets; não houve contorno.

Arquivos protegidos e implementação PWA preservados. Inventários continuam
`operational_ready=false`; a remoção dos49 controllers paralelos não foi
concluída. A migração visual BB não demonstra remoção dos demais fluxos.

Persistem o fluxo400 operacional e suas lacunas primárias específicas,
conservação do retorno original, integração completa nos fluxos de faturas,
restante da convergência nativa, administração de empresas e aceite autenticado
desktop/tablet/mobile. Homologação bancária, ingressos mTLS e contrato externo
CRM–PIGE360 devem permanecer distinguidos dos trabalhos internos pendentes.
Nenhuma cobrança, SMTP, webhook real, merge, deploy ou force-push foi realizado.
