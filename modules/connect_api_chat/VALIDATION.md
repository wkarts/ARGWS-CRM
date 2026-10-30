# Validação — Connect|API Chat 1.1.2

- Versionamento do Chat: **1.1.2**.
- Migration atual: `112_version_112.php`.
- Sequência `100` a `112` preservada.
- PHP lint: todos os arquivos do módulo devem passar sem erro.
- Mídia: navegador recebe apenas `/admin/connect_api_chat/media/<id>`; não recebe URL `mmg`/MinIO.
- Storage: mídia recebida não baixa automaticamente; carregamento manual persiste uma cópia local.
- Realtime: fila inbound local processada antes da leitura dos eventos realtime.
- Timeline: ordenação por `message_timestamp` + `id` e deduplicação por `api_message_id`.
