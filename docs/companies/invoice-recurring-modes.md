# Escolhas explícitas da agenda de faturas

Na agenda própria de faturas, cada série nova pode emitir uma fatura ou preparar um rascunho privado por ocorrência. O modo e a fatura principal são escolhas imutáveis, vinculadas à auditoria original. As séries438 existentes conservam exatamente seu contrato e não recebem escolhas novas por backfill.

Ao preencher o ID de uma fatura principal, a série somente processa ocorrências quando essa fatura pertence à mesma empresa, cliente e moeda e está com status quitado e saldo canônico zero. O ID zero desliga essa condição. A condição sempre verifica a principal declarada; uma fatura gerada no ciclo anterior pode continuar em aberto. Enquanto aguarda a quitação, a série permanece ativa e não avança a próxima data nem reserva uma ocorrência. O escritor revalida a condição após adquirir seus locks; dados inválidos ou autorização revogada não liberam efeitos.

No modo rascunho, a ocorrência cria um documento privado com ID próprio e vínculo de origem. Ela não cria uma fatura com ID zero, não consome numeração, não registra caixa, não gera cobrança bancária e não agenda notificação de fatura. A GUI de rascunhos permite revisar, editar, cancelar ou emitir explicitamente pela operação canônica. Depois da emissão, a agenda projeta o ID real da fatura a partir do mesmo rascunho; seu vínculo histórico não é reescrito.

Se a criação do rascunho foi confirmada mas o vínculo com a agenda falhou, a página apresenta o rascunho existente e o vínculo pendente. Retomar a mesma ocorrência usa a mesma chave e o mesmo ID. Uma edição explícita posterior do rascunho permanece preservada: o vínculo confere a criação original imutável, sem substituir o conteúdo revisado. Cancelar ou pausar a série conserva os documentos já criados e impede novas ocorrências.

Emissão com notificação mantém o fluxo próprio de contato, fila, transporte e revogação. A fila permanece distinta de entrega SMTP; os rascunhos exigem notificação manual após emissão. Nenhum desses modos registra recebimento ou comprova operação bancária externa.

A integração exige os schemas `CompanyInvoiceDraftsSchema`, `CompanyInvoiceRecurringModesSchema` e `CompanyInvoiceRecurringDraftsSchema`, nessa ordem, por nova migration nativa. Não editar a migration438 ou adotar suas linhas como escolhas novas. As tabelas novas preservam FK, engine e índices próprios; ensure/replay validam metadata e invalidam o cache de nomes após DDL. As provas de tracking do núcleo440 e do fonte final composto pertencem à integração final.
