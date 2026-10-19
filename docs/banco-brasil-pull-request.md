# PR45 — Banco do Brasil e Empresas no núcleo

A PR46 foi incorporada à branch `feat/banco-brasil-billing-cnab240`; a PR45
continua com destino main. Núcleo4.0.5/405, BB 1.0.4/101–104, Asaas 1.4.8/148.
As migrations históricas são preservadas. Não há autorização de merge ou deploy.

Consulte a [matriz de implementação/testes/homologação](companies/pr45-status.md),
o [handoff atual](banco-brasil-handoff.md), a [responsabilidade/wizard](companies/onboarding.md)
e os contratos [CNAB240](banco-brasil-cnab240.md) e [CNAB400](banco-brasil-cnab400.md).

O incremento `30fb0f1` implementa designação autorizada do responsável legado,
confirmação gráfica com senha e auditoria, proteção de acesso do novo colaborador
e wizard retomável/atômico de empresas. Os 16 workflows desse SHA passaram, incluindo
MySQL 8 com PHP 8.1/8.5 e Chromium com o CRM real, instalação/upgrade e isolamento.
Consultar o CI do SHA final: evidência de uma revisão não certifica outra.

Asaas, notificações e gates de domínios incompletos permanecem preservados. O
wizard não libera o financeiro nem transforma codecs ou tabelas em operações bancárias.
API BB, origem/reserva/lotes operacionais, baixa financeira e rollout universal
multiempresa ainda têm pendências técnicas. Homologação externa por canal não foi realizada.

A rechecagem dos PDFs canônicos em 19/10/2026 confirmou os hashes registrados.
O trailer CBR641 é definido; a divergência de picture é no retorno CBR643. BRL no
CBR641 e o contrato de juros ainda exigem confirmação. A OpenAPI oficial de
Cobrança não foi obtida; a URL pública de Swagger retornou 403 sem AppKey.

## Validação e reversão

PHP/lint/testes de contrato, fixtures sintéticas, MySQL descartável e cenário
HTTP/browser são separados de homologação. Os inventários não contam bugs nem
representam percentual de conclusão; estados sem evidência atual continuam pendentes.

Sem credenciais reais, chamadas bancárias de escrita, arquivos reais, merge, tags,
publicação de imagens ou deploy. Não apagar tabelas/migrations como rollback.
Revogar concessões/interromper uso preserva histórico; restauração de aplicação
e banco deve seguir manutenção com backup e compatibilidade revisados.
