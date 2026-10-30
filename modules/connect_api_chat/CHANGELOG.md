# Changelog — Connect|API Chat

## 1.1.2

- Remove fallback por URL presignada do MinIO interno do Connect|API.
- Mídia só é recuperada por `getBase64FromMediaMessage`; após carregamento manual, fica armazenada localmente no CRM.
- Adiciona fila durável `connect_api_chat_inbound_queue` para `MESSAGES_UPSERT`.
- Fila é processada imediatamente pelo webhook e reprocessada localmente pelo realtime em caso de falha, eliminando a espera aproximada de 40 segundos do watchdog.
- Watchdog remoto reduzido a fallback de segurança, sem voltar ao polling pesado de mensagens.
- Ordenação de mensagens passa a usar `message_timestamp` e `id`; sincronizações tardias não quebram mais a cronologia.
- `last_message_at`/preview só avançam quando a mensagem persistida é realmente mais recente.
- Renderização deduplica mensagens por `api_message_id` e exibe cada separador de atendimento apenas uma vez.


## 1.1.1

- Corrige regressão iniciada na 1.0.9 em que a regra de ticket podia abortar a persistência de uma mensagem inbound.
- Persistência de `MESSAGES_UPSERT` passa a ocorrer antes da criação/associação do atendimento.
- Ticket inbound é tratado em transação separada e idempotente; falha de ticket não elimina a mensagem recebida.
- Adiciona `live_received_at` e reconciliador local de mensagens inbound já persistidas sem ticket consistente.
- Reconciliador roda somente no banco local durante lista/realtime, sem consultar o Connect|API.
- Nova mensagem após atendimento fechado cria ticket novo e não atribuído; o ticket anterior permanece encerrado.
- UI muda automaticamente de **Meus** para **Não atribuídos** ao receber `ticket.new`, tornando o novo atendimento imediatamente visível.
- Diagnóstico passa a exportar `last_inbound_at` e `last_inbound_message_id` mesmo com debug desativado.
- Webhook processa registros individualmente: um payload problemático não impede os demais registros do mesmo evento.

## 1.1.0

- Corrige recebimento do webhook `MESSAGES_UPSERT` quando uma tentativa anterior já havia sido registrada: retries são reprocessados de forma idempotente.
- Mensagem inbound em atendimento encerrado cria novo ticket, inclusive quando a mensagem já existia localmente por sincronização anterior.
- Adiciona auditoria `webhook.message`, `ticket.inbound`, `webhook.retry` e estado processado/falha do evento.
- `CHATS_*` deixa de criar ticket por replay de `lastMessage`; esse caminho agora é apenas backfill.
- Normalizador desempacota `ephemeralMessage`, `viewOnceMessage`, `viewOnceMessageV2`, `documentWithCaptionMessage` e `editedMessage`, usando o tipo real do conteúdo interno.
- Captura metadados de mídia (thumbnail, waveform, duração, dimensões) para exibição leve sem download automático da mídia recebida.
- Remove a barra horizontal dos filtros de atendimento.
- Preserva realtime local alimentado pelo webhook e redução de consultas remotas introduzidos na 1.0.9.

## 1.0.9

### Realtime e performance

- Novo barramento local `connect_api_chat_realtime_events`, alimentado diretamente pelo webhook.
- PWA/CRM consulta apenas deltas locais a cada ~1 segundo; não consulta o Connect|API a cada ciclo.
- Sincronização remota automática removida da navegação e da troca de conversas; **Sincronizar** continua disponível de forma manual.
- Polling completo da conversa passa a ser apenas fallback de 15 segundos.
- `fetchProfile` / `fetchInstances` da identidade da conta ficam em cache por 5 minutos.
- Logs de `findMessages`, `findChats` e `fetchInstances` foram compactados para não persistir respostas gigantes no modo debug.
- Índices novos para lista de conversas e polling de mensagens.
- Corrigida condição de corrida quando um evento realtime chega durante atualização da lista/conversa.

### Atendimento / tickets

- Encerrar move imediatamente o atendimento para **Encerrados**.
- Uma nova mensagem **inbound ao vivo** depois do fechamento sempre cria outro ticket numerado, sem depender de comparação de timezone.
- O ticket anterior permanece encerrado e o histórico é preservado.
- O novo ticket nasce aberto, não atribuído e marcado como **NOVO**.
- Se o novo ticket pertencer à conversa atualmente aberta, a interface muda para a fila **Não atribuídos** e atualiza o cabeçalho sem recarregar a página.
- Contadores nas filas Meus / Não atribuídos / Todos / Encerrados.
- Notificação do ARGWS CRM/Pusher para novo atendimento quando disponível.
- Consolidação LID/JID pesada removida do caminho quente do webhook; limpeza local de aliases antigos executada apenas uma vez por instância na atualização.
- `CHATS_UPDATE` com LID sem `remoteJidAlt` não cria mais conversa duplicada; o `MESSAGES_UPSERT` autoritativo faz a associação.

### Mídia

- Mídias continuam sob demanda, sem download automático de conteúdo recebido.
- Após o clique, imagem/áudio/vídeo usam diretamente o endpoint same-origin autenticado do Chat, permitindo ao navegador tratar cookies, streaming e Range nativamente.
- Não há uso de `fetch(blob)` para montar player/preview.

### Diagnóstico

- Novos logs `ticket.status`, `ticket.inbound`, `identity.cleanup` e transporte realtime identificado no diagnóstico.
- Health check atualizado para 1.0.9.

## 1.0.8

- Modelo conversa permanente + tickets numerados.
- Recuperação de mídia por proxy autenticado.

## 1.0.7

- Recuperação de mídia por `messageId` e fallbacks de chave/JID.

## 1.0.6

- Mídia recebida sob demanda e download do diagnóstico.

## 1.0.5

- Encerrar/reabrir atendimento e pesquisa na conversa.

## 1.0.4

- Composer, áudio, anexos, emoji, reply e melhorias de performance.

## 1.0.3

- Rolagem/composer e multi-instância por usuário.

## 1.0.2

- LID/`remoteJidAlt`, correções SQL e polling.

## 1.0.1

- Diagnóstico, logs, PWA e webhook health check.

## 1.0.0

- Estrutura inicial do Connect|API Chat.
