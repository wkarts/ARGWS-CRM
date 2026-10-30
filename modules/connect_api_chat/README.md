# Connect|API Chat 1.1.2

Cliente de atendimento WhatsApp integrado ao **Connect|API Conector/Manage**, com interface desktop/PWA, multi-instância e individualização por usuário.

## 1.1.2 — mídia local, fila inbound e linha do tempo

- URLs internas `mmg.whatsapp.net` e `minio-*` nunca são usadas como fonte do navegador nem como fallback do CRM.
- Mídia recebida continua sem download automático; ao clicar em **Carregar mídia**, o Connect|API devolve o binário e o Chat grava uma cópia local em `uploads/connect_api_chat/<instância>`.
- Próximas visualizações/plays usam somente o storage local do CRM.
- Webhook grava `MESSAGES_UPSERT` primeiro em uma fila durável local; se qualquer regra síncrona falhar, a PWA reprocessa a fila no ciclo realtime de ~1s, sem consultar novamente a API.
- Watchdog remoto deixa de ser o mecanismo que causa espera de ~40s e passa a ser apenas terceira camada de segurança.
- Mensagens são ordenadas por `message_timestamp` + `id`, e backfill histórico não substitui mais o `last_message_at` da conversa.
- Separadores de atendimento aparecem uma única vez por ticket na janela atual, preservando a linha do tempo.

## 1.1.1 — persistência inbound desacoplada

A mensagem recebida é persistida antes de qualquer criação/alteração de ticket. O atendimento é reconciliado em uma etapa transacional separada, garantindo que uma inconsistência de ticket nunca interrompa o recebimento. Mensagens live recebem `live_received_at` e podem ser reparadas localmente sem consultar a API externa.


## Integração

O Chat não mantém URL/token próprios. Ele utiliza os contextos autorizados do Conector e, quando disponível, do Manage.

## Atendimento

```text
Contato / conversa permanente
├── Atendimento #1  [encerrado]
│   └── histórico preservado
├── Atendimento #2  [encerrado]
│   └── histórico preservado
└── Atendimento #3  [aberto / NOVO / não atribuído]
    └── criado por mensagem recebida após o encerramento do #2
```

Encerrar nunca é desfeito automaticamente. Uma mensagem recebida depois do encerramento cria **outro atendimento**. Reabrir é uma ação manual sobre o ticket encerrado.

## Realtime 1.0.9

O webhook grava a mensagem/ticket e publica um pequeno evento na tabela local de realtime. O navegador/PWA consulta apenas esse cursor local aproximadamente a cada 1 segundo. Isso evita executar `findChats`, `findMessages`, `fetchProfile` ou `fetchInstances` continuamente.

A sincronização remota completa fica disponível pelo botão **Sincronizar** e como recuperação explícita, não como mecanismo normal de navegação.

## Mídias

Mídias recebidas não são baixadas automaticamente. Após o usuário clicar em **Carregar mídia**, o browser abre o endpoint autenticado same-origin do Chat, que recupera/descriptografa o conteúdo pelo Connect|API. Conteúdo recebido não é persistido automaticamente no disco do CRM.

## Diagnóstico

O modo de desenvolvimento registra webhooks, chamadas externas, tickets e falhas. O painel de Diagnóstico permite baixar JSON sanitizado.

## Versionamento

O pacote mantém todas as migrations `100_version_100.php` até `109_version_109.php`.


## 1.1.1 — recebimento, tickets e Baileys

A versão 1.1.1 usa o webhook como fonte autoritativa de mensagens novas. Eventos repetidos são reprocessados com persistência idempotente; uma mensagem inbound após ticket fechado cria outro atendimento sem reabrir o anterior. A normalização de payload e mídia adota os mesmos princípios técnicos observados no projeto Node.js/Baileys de referência fornecido para análise, sem copiar sua nomenclatura ou arquitetura de produto.
