# Banco do Brasil: API e CNAB independentes

O módulo 1.2.8 registra `banco_brasil_api` (Banco do Brasil — API) e
`banco_brasil_cnab` (Banco do Brasil — CNAB). A identidade antiga
`banco_brasil` não é convertida em dois canais. Nenhuma origem, fatura,
credencial, remessa ou recebimento histórico é reescrito pela migration128.

Cada empresa habilita os canais separadamente na guia existente de
autenticação. A ausência de uma decisão explícita significa ambos desabilitados,
inclusive para a empresa principal. Configurações anteriores são preservadas;
o administrador deve revisar e habilitar somente o canal que pretende usar.
Habilitação não equivale a configuração válida ou homologação bancária.

O serviço `ChannelPreferences` exige vínculo ativo e ACL `banco_brasil/view`;
alterações exigem também `manage`, contexto compatível, revisão do documento de
configuração, transação própria e auditoria. O POST conserva CSRF do módulo e o
token original da empresa. Formulários anteriores à troca não autorizam a
empresa seguinte. Configurações privadas não são devolvidas na projeção.

O seletor da fatura nativa oferece os dois IDs e revalida o canal na transação
da fatura. CNAB exige seu contrato/layout e `generate`, sem carregar credenciais
API. API exige seu perfil e credencial. Selecionar um meio não emite cobrança.
Os IDs novos também são recusados antes de qualquer hidratação de fatura no
checkout global legado; esta entrega não libera esse checkout.

A geração CNAB e a reserva de nova cobrança API verificam o respectivo canal
no serviço. API também verifica a habilitação antes de iniciar uma tentativa e
antes do marcador de envio após OAuth. Uma reserva impedida não recicla número
comprometido anteriormente. Repetições de operações já confirmadas, download,
consulta e conciliação histórica não dependem da habilitação atual. O bloqueio
existente de uma obrigação já vinculada continua compartilhado entre canais.

## Verificação e limites

Adicionados testes reais de armazenamento/ACL/transações para defaults,
coexistência, desativação independente, isolamento, revisão antiga, revogação,
configuração inválida e rollback de auditoria. Os testes MySQL existentes passam
a habilitar explicitamente os canais no fixture descartável, verificando que
um canal desativado não reserva outra obrigação nem faz HTTP. O browser BB
existente passa a habilitar pelos formulários reais e verificar POST capturado
antes da troca de empresa. Nenhum transporte bancário real é usado.

Os testes PHP, MySQL/MariaDB e browser desta alteração precisam ser confirmados
nos workflows do SHA publicado. Não foram executados neste executor sem PHP.
Contratos Python/Node, manifestos e integridade de diferenças são verificáveis
localmente. O inventário mantém `operational_ready=false`.

O fluxo operacional de remessa continua limitado ao CNAB240 documentado. O
codec candidato400 e a importação observacional400 existentes **não** são
remessa/retorno400 conciliado às faturas. Falta integrar esse fluxo; sua liberação
também exige resolver os campos documentais específicos descritos em
[banco-brasil-cnab400.md](banco-brasil-cnab400.md): BRL85–86 do CBR641,
juros efetivos do convênio e picture26–39 do CBR643. Não se presume código ou
isenção para contornar essas lacunas. A unificação integral das telas/controller
e o aceite da empresa principal também permanecem trabalhos distintos e
pendentes; dois registros no catálogo não demonstram sua conclusão.
