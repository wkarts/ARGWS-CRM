# Evidência do checkpoint local r3

- Data: 2026-10-27
- Alteração: retirada da execução duplicada de `native_header_overflow` do
  workflow focado; skip explícito para fixtures PHP quando o runtime local não
  existe; validador local reproduzível.
- Resultado agregado: 219 Node tests passaram, 3 foram bloqueados como skip por
  ausência de PHP; inventário e contrato focado passaram.
- Relatório executável:
  `.continua-ai/evidence/local-validation-r3/local-validation.json`
- Limitações: PHP, MySQL/MariaDB, Chromium, Ruby e Mix não estão disponíveis no
  workspace; nenhuma homologação bancária foi alegada.
