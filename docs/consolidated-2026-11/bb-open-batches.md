# Lotes abertos CNAB240 — módulo 1.3.8

A migration138 acrescenta somente bb_batch_requests. Não altera arquivos, origens,
snapshots, pagamentos ou contratos publicados. Replay valida estrutura e FK;
falha de DDL/tracking mantém a atualização incompleta bloqueada. Não há backfill.

Origination::generate preserva sua validação de título e reserva permanente de
Nosso Número. Comandos diferentes da mesma empresa, conexão, conta, ambiente,
convênio, perfil e declaração de contrato compartilham o último lote aberto
compatível. Limite atual:100 títulos; ao exceder, abre outro lote. A data do arquivo
é a primeira emissão do grupo; datas das faturas/títulos continuam individuais.
Essa é uma decisão de implementação explícita, não uma escolha atribuída ao usuário.

A primeira retirada codifica e grava os bytes sob o mesmo mutex financeiro usado
na emissão. O lote deixa de aceitar títulos. Retirada repetida usa bytes persistidos.
Erro de codificação reverte somente a tentativa de retirada; a reserva anterior
permanece aberta, sem reutilizar número. Retry do comando usa seu hash próprio,
mesmo após fechamento; mesma chave com payload diferente é recusada.

A ação antiga de retomar geração não congela lotes abertos novos. Continua
recuperando reservas históricas. Arquivos antigos sem ledger novo continuam
baixáveis pelos bytes armazenados. Baixado não implica transmitido, registrado,
aceito ou liquidado. Nenhuma chamada ao banco foi adicionada.

Testes: banco_brasil_open_batches_mysql.php valida agrupamento, data diferente,
retry/conflito, bytes fixos e acesso por empresa. banco_brasil_origination_concurrency.php
usa dois processos SQL reais: retry simultâneo, emissão concorrendo com primeiro
download e revogação enquanto os writers aguardam o lock. O histórico de instalação
nativa inclui138, falha de DDL/tracking, replay e rejeição de transação externa.

Limites: o agrupamento é CNAB240. CNAB400 conserva aprovação e validação por
preparação; boleto anterior à transmissão e acionamento automático pela emissão
nativa/recorrência ainda precisam de implementação. Não há homologação bancária.
Não reduzir o marcador para rollback; interromper novas emissões e aplicar correção
adiante, preservando ledger e bytes. Um rollback de código deve manter leitor dos
lotes abertos138; a versão anterior não sabe congelá-los na retirada.
