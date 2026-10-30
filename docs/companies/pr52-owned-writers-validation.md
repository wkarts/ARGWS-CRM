# PR52 — composição dos gravadores e validação pendente

Base conferida: `76d9ba40e2c7eed687a53c9d63a0d0143a57714b`, PR52 aberta, em rascunho. Preservado o PWA `a5ba730` e seus descendentes. Sem merge/deploy, chamadas financeiras externas ou alteração dos arquivos protegidos.

## Código funcional

O criador de eventos captura o ID imediatamente depois do INSERT, antes da consulta ROW_COUNT usada para confirmar a gravação. Mantidos confirmação da inserção, leitura por empresa/autor, revisão, auditoria e transação. A falha409 do navegador em76d ainda não tem causa confirmada: este ajuste de uso do driver precisa passar no mesmo teste autenticado. O diagnóstico agora envia apenas método e identificador de domínio limitado ao stderr, sem formulário, cabeçalhos ou valores; a coleta da stack aceita somente esse padrão.

As cópias de tarefas individuais e de projetos incluem os participantes declarados pelos próprios gravadores canônicos. Evita manter outra lista incompleta quando o gravador passa a exigir campos nativos, temporizadores e cercas financeiras. Não removidos assertOwned, ACL, isolamento, bloqueios financeiros, revisão e provas de preservação do original. A falha efetiva em76d era `issuer_owned_transaction_required` na cópia individual composta440, antes da gravação. O teste existente continua criando/repetindo cópias A/B e comparando dados financeiros.

## Upgrade BB histórico

O teste de instalação120/121/122 usava o importador atual antes da instalação130. A recusa `bb_innodb_required` é correta: o importador atual exige retenção privada do arquivo na mesma transação. A preparação histórica passa a semear dados sintéticos no schema efetivamente antigo usando o parser400 distribuído. Isso é preparação, não prova de execução do importador retirado. Uma negativa adicional exige que o importador atual recuse esse schema sem escrever.

A prova de upgrade exige exatamente as três tabelas novas124/125/130, preserva todos os bytes de registros/definições anteriores, falhas de tracking, metadata inválida, retry e replay. Não inventa originais antigos: somente a reimportação dos bytes exatos após130 pode retê-los, sem outro evento/pagamento. Reimportação subsequente deve manter todas as linhas idênticas. Nenhuma lógica de produção BB ou guard foi afrouxado.

## Evidências da base76d

- Focused pull_request40416809181 e push40416802073: aprovados, PHP8.1/8.5.
- Interface40416809249 e PWA40416809291: aprovados.
- Stack40416809354: falhou ao salvar evento pelo widget principal, HTTP409. Não há aceite do fluxo criar/editar/excluir.
- Empresas40416809196: falhou no shard8.5/3, cópia de tarefa; demais shards cancelados pela rodada. Não aprovados.
- BB40416809290: cancelado, não aprovado. Este executor não solicitou esse cancelamento. MariaDB da rodada anterior8b40413495699 falhou na preparação histórica descrita acima.

## Verificação local deste incremento e limites

Árvore física anterior ficou indisponível; não foi recuperada nem substituída por ZIP antigo. Fontes modificadas lidas diretamente do SHA76d. Inventários reconciliados exclusivamente pelas seis diferenças de fontes, contra o artefato oficial completo do mesmo SHA: run40416809196, artifact12694339060, ZIP SHA256 `3933746dcac1bb74c25282114829ab05cbcafcb733eca72dae5c30370c3ac7f8`. Cada fonte base conferida por hash antes da alteração; agregados antigos reproduzidos antes da atualização. Não é uma geração sobre checkout físico completo. Os geradores oficiais sem alterações precisam confirmar o novo SHA no CI.

Executados: bash -n do smoke, dois testes Python do inventário de fronteiras, validação de hashes/manifestos e comparação das mudanças de candidatos lexicais. Contagens mantidas15082 fronteiras/4608 fontes operacionais/646 itens no auditor, operational_ready=false. Assets e PWA não alterados neste incremento.

Não executados localmente: lint PHP, novas provas SQL/upgrade, navegador autenticado. PHP, Docker e banco ausentes; nenhuma permissão contornada. Validação real requerida nos workflows existentes8.1/8.5/MySQL/MariaDB/stack. O resultado da base não aprova este incremento.

## Escopo ainda aberto

Aplicação única ainda incompleta:48 controllers paralelos e51 views com shell, sem promoção de prontidão. CNAB400 ainda não operacional completo, além das pendências precisas de CBR641/CBR643 e contrato bancário descritas em docs/banco-brasil-cnab400.md. Retenção de arquivos rejeitados, demais integrações, ciclo autenticado integral por empresa e instalação real/standalone PWA continuam pendentes. API/CNAB permanecem meios independentes, sem ativação automática. Manter PR em rascunho.
