# Descoberta durável das séries vencidas

O modo condicionado à quitação da principal mantém uma série não quitada ativa e com a data original. Uma seleção sempre ordenada por data e limitada aos mesmos primeiros IDs podia bloquear indefinidamente séries elegíveis de outra empresa. Esse defeito foi reproduzido com duas séries e limite 1, sem alterar a regra de quitação.

A tabela aditiva `company_invoice_schedule_discovery` guarda somente o cursor de travessia da instalação. Cada lote reserva no máximo 100 referências duráveis (empresa, ator e ID); nenhum documento, dinheiro, segredo ou autorização financeira é copiado para o cursor. O processamento continua chamando `CompanyInvoiceRecurring::run`, que revalida o ator, os grants, a origem, a principal quitada, o saldo canônico e a mesma chave de ocorrência.

A reserva usa o mutex nativo da instalação e a PK do cursor. Cursor, limite superior da passada, revisão e hash do lote são ligados a um registro de auditoria interna, ator 0 e empresa NULL. Auditoria, cursor e confirmação por leitura pertencem à mesma transação. Uma falha de auditoria ou gravação não avança a descoberta; um cursor alterado sem a prova correspondente é recusado.

O limite superior é fixado durante cada passada. Novos IDs entram na passada seguinte, permitindo retornar a IDs antigos mesmo quando novas séries são criadas. O cursor avança por ID estável; o rodízio não depende de timestamps ou da resolução do relógio. Em uma passagem que chega ao fim, a consulta pode voltar ao início para preencher o lote, sem repetir um ID no mesmo lote. Há no máximo duas consultas de referências, com retorno total limitado ao parâmetro de 1 a 100.

A reserva termina antes de emitir. Se o processo cair depois de reservar e antes de executar, a referência será encontrada novamente na próxima volta. A reserva não é uma promessa de execução nem uma concessão de autoridade. Workers concorrentes podem encontrar a mesma referência ao completar uma volta; o recibo canônico da ocorrência continua impedindo documentos ou valores duplicados.

Séries que aguardam quitação conservam integralmente a principal, a próxima data, os ciclos, a revisão e seu estado. Somente o cursor interno de travessia muda. Revogação posterior à descoberta é reavaliada pelo escritor e produz o bloqueio de segurança já existente.

A integração do núcleo 440 precisa chamar `CompanyInvoiceRecurringDiscoverySchema::ensure($db, db_prefix())`, além dos schemas dos rascunhos e modos. A tabela acrescenta uma PK, uma FK para a auditoria e oito campos: `id`, `cursor_id`, `high_water_id`, `revision`, `batch_hash`, `audit_id`, `created_at` e `updated_at`. Não há backfill de séries, autorização ou finanças. O replay preserva o cursor existente. Com modos novos habilitados e schema de descoberta ausente, o worker falha explicitamente; a fonte anterior sem os modos mantém seu contrato histórico.

As provas próprias de serviço, limites, rollback, corrupção, replay e concorrência real não substituem a instalação e o tracking do núcleo 440 composto, a interface real nem homologação bancária ou SMTP. Nenhum transporte externo é executado por esta descoberta.
