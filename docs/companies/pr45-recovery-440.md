# Composição440 após a recuperação439

Base publicada439: b9d7a2f74881e7f0faf01aa4b9f8e0365309a148. Fonte440 mantém4.4.0/440, BB1.2.0/120 e Asaas1.5.3/153. Os protótipos locais foram preservados; somente os componentes integrados e demonstrados entram nesta composição. Não se afirma recuperação byte a byte de todo o antigo overlay440.

## Implementação

- Rascunhos privados de fatura com edição/revisão, cancelamento e emissão explícita; nenhum número ou recibo financeiro antes de emitir. Scopes próprios opt-in daAPI.
- Recorrência com modo fatura/rascunho e condição explícita de quitação de uma fatura principal. Não se confunde principal com filho do ciclo anterior. Descoberta durável comcursor/high-water/lock garante avanço sob limites sem alterar série ou saldo. Corrigido checkbox inválido do modo e provadoDOM.
- Cópias revisadas de projeto/tarefa, contexto atual e novas identidades, sem duplicar tempo, arquivos, publicação, recibos ou faturamento. Scopes próprios não ampliam tokens existentes.
- Reversão LOCAL tipada do novo recebimento manual após refundAsaas; cancelamento posterior separado e cópia/emissão independente conservam os históricos. Sem transmissão financeira externa.
- SuporteConnect por empresa: política opt-in, native5/publicação/telefone, fila durável, worker e estados uncertain sem reenvio cego.
- BB120: janela de primeiro desconto após vencimento no perfilS declarado, alteração/cancelamento/primeira atribuição, com confirmação posterior e snapshots preservados.

## Migrations e coerência

A migration440 usa oito helpers e cria exatamente11tabelas:Drafts2, RecurringModes1, RecurringDrafts1, RecurringDiscovery1, ProjectCopies2, TaskCopies1, ManualReversal1 e TicketConnect2. O protótipo antigo de16tabelas não foi publicado/aplicado como se completo. Nenhuma migration já publicada é reescrita; down não destrói recibos. BB120 é uma migration nativa semDDL funcional. Asaas153 não mudou.

Nativebody completo passou1077checks cumulativas, com11tabelas/metadados/índices/FKs/replay e histórico byte-exato. Executor realCI_Migration/App_Migration/App passou9checks: falha parcialDDL, falha do marcador, metadados inválidos, retry/tracking439→440 e replay. A primeira tentativa do novo teste de metadados apontava coluna inexistente; o teste foi corrigido para data_json e exige sucesso da injeção/restauração. Não houve correção produtiva por esse erro de autoria.

## Provas da composição

Produção congelada:18.522arquivos, SHA256335a3203d495df04c9fb3d98349cb7c0eb8f83a7b23c5738646aa2fb987bc3ff. MariaDB10.11.14/binlogON/usuáriosemSUPER e SQLite; observação real de dois waiters por cenário.143testesPython passaram.

Drafts7suítesMaria/5SQLite; recorrência8Maria/6SQLite; operações25execuções; configurações22Maria/12SQLite; financeiro15Maria; BBcontrato/SQL/controller/concorrência e guards119sob120. Os contadores dos relatórios são cumulativos, não somáveis. Evidências recentes em docs/companies/evidence/ substituem alegações de overlays separados.

HTTP440 foi executado em instalação própria e upgrade real439→440, preservando identidade/master e oito tabelas históricas. Resultados completos e limites da rodada são registrados pelo executorHTTP em relatório próprio. Não atribuir uma prova local aoSHA remoto antes de publicar/conferir a árvore. CI remota e homologação externa são validações distintas.

## Trabalho ainda fora desta composição

API de despesas/categorias/arquivos, cópia de despesas, editar/copiar orçamento/proposta, ledgerLOCAL não-caixa eAI por empresa continuam incrementos próprios; protótipos/logs antigos não os publicam. A fila de consumidores/settings legado permanece delimitada, com adaptação por fluxo. Recorrência440 e os camposBB já demonstrados não são repetidos como ausentes.

CNAB400 depende de códigoBRL85–86CBR641, contrato de isenção/parâmetros de juros herdados e errata do pictureCBR64326–39; depois ainda precisará da vertical operacional. API18/19 depende da identificação individual estável e da semântica de valorCreditoCedente. Demais gaps de código e insumos são discriminados em banco-brasil-remaining-contracts.md. Sem merge/deploy/forcepush/tag/release/homologação externa.
