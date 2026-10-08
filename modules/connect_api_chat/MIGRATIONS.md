# Migrations — Connect|API Chat 1.1.2

```text
100_version_100.php
101_version_101.php
102_version_102.php
103_version_103.php
104_version_104.php
105_version_105.php
106_version_106.php
107_version_107.php
108_version_108.php
109_version_109.php
110_version_110.php
111_version_111.php
```

- `109_version_109.php`: fila local de eventos realtime e índices para lista/polling.
- `110_version_110.php`: idempotência de ticket por mensagem, metadados leves de mídia e auditoria do webhook.
- `111_version_111.php`: marca `live_received_at`, cria índice de reconciliação inbound e heartbeat operacional do último inbound.

Nenhuma migration anterior é removida.

- `112_version_112.php`: fila durável inbound, índice cronológico de mensagens e política de mídia local sob demanda.
