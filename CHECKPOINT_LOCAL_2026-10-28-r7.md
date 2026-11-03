# Checkpoint local r7 — pacote íntegro e validação reconciliada

Data: 2026-10-28 UTC

## Implementado no estado local

- Recorrência, rascunhos/cópias de faturas e equipe usam somente as rotas e adaptadores oficiais (`admin/invoices/recurring`, `admin/invoices/drafts`, `admin/invoices/copy` e `admin/staff`).
- Os controllers/views paralelos dessas quatro famílias são tombstones inertes e não aparecem no inventário ativo.
- Links internos, testes de contrato e browsers dessas superfícies foram reconciliados com as URLs oficiais.
- O pacote r7 foi reconstruído sem checkpoints antigos, arquivos de upload temporários ou outros arquivos de trabalho que causavam pacotes grandes e downloads incompletos.

## Validação local reproduzida

- `node --test tests/*.test.cjs`: 219 aprovados, 3 skips explícitos por PHP ausente, 0 falhas (222 testes).
- `python3 tests/company_ui_surface_inventory.py`: PASS — 82 views ativas, 42 controllers/endpoints ativos.
- `python3 tests/ci/test_native_company_focused.py`: PASS — 10 testes.
- `python3 scripts/validate-local.py`: 4 gates aprovados, 0 falhas, 3 bloqueios de ambiente; evidência em `.continua-ai/evidence/local-validation-r7/local-validation.json`.
- Integridade do pacote: `unzip -t` e `zstd -t` aprovados; SHA-256 registrado em `ARTIFACT_MANIFEST_LOCAL_R7.txt`.

## Limitações verificadas

O workspace não possui PHP, Docker/MySQL, Chromium de integração, Ruby ou `node_modules/.bin/mix`; por isso os gates HTTP/browser, upgrade histórico, build Mix, lint PHP e YAML Ruby não podem ser certificados aqui. CNAB 400 continua condicionado à confirmação documental e homologação do Banco do Brasil. O inventário ainda contém famílias `Company_*` especializadas que precisam de migração funcional para as rotas oficiais; não há declaração falsa de que o contrato integral foi concluído.

Nada foi publicado no GitHub, mesclado ou implantado.

Arquivo persistente: Library versão 8, link nativo preservado no manifesto da entrega.
