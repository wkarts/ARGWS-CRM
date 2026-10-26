# Contrato executável do calendário compartilhado

Base deste incremento: `8b53afcca2e035f4bd12a33da6f584b0992c797d`, PR52 em
rascunho. Sem alteração de PWA, bancos externos ou arquivos protegidos.

## Causa reproduzida e correções

A stack8b53afc abriu a página principal original com200, mas o feed retornou409.
O FullCalendar **5.6.0 distribuído no repositório**, executado localmente via
`DateEnv` real, produziu `2026-10-01T00:00:00` com
`timeZone=America/Sao_Paulo` e `canComputeOffset=false`. O endpoint aceita datas
civis ou instantes com offset, não timestamps ambíguos. O formulário/endpoint não
precisa ser relaxado: o callback compartilhado agora envia as datas civis do
intervalo visível, mantendo fim exclusivo. A documentação do fabricante descreve
[UTC-coercion sem plugin de timezone](https://legacy.fullcalendar.io/v5/timeZone).
É uma reprodução do mecanismo que explica a falha, não captura dos parâmetros
da requisição anterior nem alegação de novo browser aprovado.

O mesmo handler atende widgets de páginas principais ainda não adaptadas:

- Leituras de eventos usam GET; exclusão usa POST com revisão também nesse
  chamador. O bootstrap recusa verbos incompatíveis antes da hidratação legada.
- Links `native_path` retornados pelo feed são usados pelos dois chamadores.
- `form_open` e o bootstrap XHR/fetch entregam o token nativo vinculado ao
  contexto também ao documento principal já autenticado. Isso não concede
  `nativeFor`, não admite outra rota e não substitui ACL/CSRF do destino.
  Formulários GET, externos ou fora do caminho administrativo não recebem tokens.
- Os templates originais deixam de inserir um segundo CSRF já fornecido por
  `form_open`; o transporte mantém a rejeição de parâmetros duplicados. Formulários
  de criação/edição têm IDs distintos. Falhas do feed chegam ao callback de erro.
- O log de rejeição JSON registra somente método interno e código de domínio
  limitado a identificadores; nunca query, valores, headers ou conteúdo privado.

O teste autenticado foi ampliado para criar evento pelo widget original da
principal, abri-lo/editar/excluir no calendário oficial, conferir revisão e empresa
no banco, e depois trocar de empresa e negar o formulário antigo. Notificação0,
tráfego restrito ao CRM sintético; nenhum SMTP/provedor real. Essa execução nova
ainda depende do CI.

## Falha de tracking439 reconciliada

O shard8.1/2 de Empresas8b53afc falhou antes da falha DDL437 injetada.
`essentialFixture` já cria uma única linha de versão447, explicitamente sintética
e anterior às sidecars448. O filho do teste tentou criar a tabela novamente; o
adapter devolveu false e o teste inseriu436 atrás da linha447. O executor real
recusou corretamente447→439, sem chegar ao DDL.

O preparo agora exige a única linha447 documentada, atualiza somente esse marker
no banco descartável para o limite histórico436 e confirma que há exatamente uma
linha436. Todas as assertions de falha DDL, marker, metadata, retomada e replay
permanecem. Não muda o executor, a regra contra downgrade ou qualquer migration.

## Evidências e limites

- [Focused PR40413495681](https://github.com/wkarts/ARGWS-CRM/actions/runs/40413495681)
  passou em PHP8.1/8.5 na base8b53afc.
- [Interface40413495736](https://github.com/wkarts/ARGWS-CRM/actions/runs/40413495736)
  e [PWA40413495608](https://github.com/wkarts/ARGWS-CRM/actions/runs/40413495608)
  aprovados; geradores PHP oficiais confirmaram inventários.
- [Empresas40413496193](https://github.com/wkarts/ARGWS-CRM/actions/runs/40413496193):
  tipografia passou no shardMySQL/PHP8.1/4,159 verificações adicionais /454
  cumulativas, no mesmo128MiB. Checkpoint liberou60.723 comandos de diagnóstico,
  memória75.505.664→71.303.168 bytes. Falha posterior de tracking descrita acima;
  demais shards foram cancelados, não aprovados.
- [Stack40413495619](https://github.com/wkarts/ARGWS-CRM/actions/runs/40413495619):
  falhou409 no feed principal. Artefato12694485961 baixado e SHA256 conferido:
  `32121b0460c738b50aaecffd44dff45a46563fbd83f35088d99b6941886205a4`.
- Local: teste executa DateEnv distribuído e o callback real, com DOM/transporte
  dobrados; confirma datas/fim exclusivo/propagação da falha. Handlers anteriores
  e seis contratos de transporte passaram;22 casos dashboard/PWA/assets/sidebar
  e65 Python passaram. Sintaxe Node conferida.
- Runner Node completo interrompido no caso2 por `php ENOENT`, não por falha PHP
  da aplicação; não é aprovado localmente. PHP/Docker/banco não disponíveis e
  Chromium local bloqueado, sem contorno. O caso de calendário entra como43º
  comando Node do Focused, preservando os42 anteriores.

Ainda não há aceite integral. Permanecem48 controllers paralelos, os filtros e
configurações da tela `Company_calendar` por reconciliar antes da exclusão,
Staff e outros fluxos autenticados pendentes. BB continua com dois meios
independentes; CNAB400 operacional depende dos documentos/campos específicos
registrados em `docs/banco-brasil-cnab400.md` e implementação posterior.
`operational_ready=false`; PIGE360 não alterado. Nenhum run desta base foi
cancelado por este executor.
