# Calendário original e validação da PR52

Base remota reconciliada: `113d6d30b3a4cb57f5b1642b3dd84daec4322cfb`.
Branch `fix/native-company-experience`; PR52 permanece em rascunho.
PWA `a5ba730` e descendentes preservados; sem merge, deploy ou operação externa.

## Código deste incremento

- A admissão finita de `Utilities::calendar`, `get_calendar_data`, `view_event` e
  `delete_event` passa a usar os mesmos adapters também na empresa principal.
  Outros métodos de Utilities não ganham admissão para empresas secundárias.
- A leitura de calendários departamentais mantém os registros históricos somente
  na principal comprovada. O administrador vê os departamentos dessa empresa;
  os demais usuários precisam de vínculo departamental. Vínculo de outra empresa
  ou ID coincidente não autoriza leitura. Identidade, ACL e linhas são conferidas
  novamente; nenhum modelo global de departamentos é hidratado.
- A página e o modal oficiais são renderizados em buffer. Antes da entrega,
  configuração, conteúdo do evento, autor, permissões e revisão são relidos e
  comparados. Revogação ou alteração durante a composição impede a resposta
  privada preparada. Respostas usam `no-store`; falhas omitem HTML e dados dos logs.
- Assets usam o catálogo existente de apresentação nativa, com FullCalendar,
  CSS e locale originais. Não chama `app_scripts` do bootstrap legado nem registra
  outro calendário. Avatar do autor usa a apresentação existente com DTO autorizado.
- Provas acrescentadas: bootstrap principal com dois tokens, página principal e
  calendários históricos/autoridade departamental, revisão após renderização e
  revogação; navegador autenticado principal/secundária e formulário antigo.

O controller/tela `Company_calendar` ainda existe nesta base. Não se declara
retirada integral: seus filtros e testes precisam ser reconciliados com o fluxo
oficial antes da remoção. Continuam48 controllers paralelos no inventário,
`operational_ready=false`; este incremento não reduz esses números artificialmente.

## Evidência anterior conferida

No SHA113d6d3:

- Focused [pull_request40409571209](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409571209)
  e [push40409566622](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409566622)
  passaram nos PHPs8.1/8.5.
- [Interface40409571255](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409571255)
  e [PWA40409571257](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409571257)
  passaram, incluindo geradores PHP oficiais e browser PWA.
- [Empresas40409571403](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409571403)
  falhou no shard8.1/4: TCPDF ultrapassou128MiB ao renderizar a tipografia de vendas.
  O fixture requerido anteriormente exercita20MiB de anexos e retém seus comandos
  SQL/bindings no processo. Este incremento libera somente esse diagnóstico após
  suas assertions, antes das renderizações. Mantém banco, blobs, limite128MiB,
  todas as fontes, casos, bytes PDF, conversões e negativas. O novo checkpoint
  registra contagem/memória, nunca os parâmetros. Correção ainda depende da execução.
- [BB40409571400](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409571400)
  passou unitários/lint8.1/8.5 e checkout; jobs SQL foram cancelados antes do fim.
  MySQL avançou até concorrência API16/CNAB17 e PDF tipográfico; MariaDB até
  recuperação de workers interrompidos e mudanças de cobrança. Transportes
  bancários eram simulados. Isso não é aprovação integral, travamento comprovado
  nem homologação bancária.
- [Stack40409571325](https://github.com/wkarts/ARGWS-CRM/actions/runs/40409571325)
  foi cancelada após build/lint, storage e prova SQL de instalação. Não concluiu
  os testes autenticados das novas rotas.

Cancelamento solicitado por este executor: somente o run obsoleto
[40407126988](https://github.com/wkarts/ARGWS-CRM/actions/runs/40407126988),
SHA9ceec2e, após reconferir PR52/branch e HEAD113d6d3.
A interface confirmou a solicitação e a API confirmou conclusão`cancelled`.
[Captura](evidence/ci-9ce-cancel.png). Os cancelamentos113d acima não foram
solicitados por este executor; não se presume seu motivo ou autoria.

## Validação deste incremento

Local: Node22 casos dashboard/PWA/sidebar/assets e contrato de handlers do
calendário; Python65 casos convenção/diferenças de migrations, inventário,
runner focado e gateways BB; sintaxe Node do browser; contratos estáticos da UI,
inventários reconciliados e conferidos. Sem exclusão de casos/guards ou aumento
de memória/timeout. Geradores PHP precisam confirmar a reconciliação no novo CI.

PHP/Composer/Docker/MySQL não disponíveis localmente. Chromium local bloqueado
no socket; restrição não contornada. Testes novos de PHP, SQL, HTML e navegador
autenticado ainda não executados neste executor. Não há alegação de instalação
standalone nem aceite integral de aplicação única, Staff, integrações ou CNAB400.

BB segue um módulo, com dois meios independentes por empresa;1.3.0/migration130
inalterados. Retenção original240/400 não equivale a originação/conciliação400
operacional. Permanecem os bloqueios documentais/contratuais precisos em
`docs/banco-brasil-cnab400.md`, as pendências das demais integrações e as48
retiradas de controllers. Arquivos protegidos intactos.
