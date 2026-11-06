# Histórico de alterações — integração Asaas

## 1.6.5 — 2026-11-06

- Recriação PIX/BOLETO na reabertura nativa: sucessor ativo distinto, POST com claim durável, recuperação por GET e reabertura atômica com diário.
- Migration165 preserva identidades e substitui unicidade por fatura por uma única posição ativa; histórico continua imutável.
- Concorrência real MySQL, perda de resposta, falhas locais e ciclos repetidos cobertos com provedor simulado. Cartão e ponte manual permanecem sujeitos a revisão.

## 1.6.4 — 2026-11-06

- Registro durável interno de intenção de recriação após cancelamento nativo e prova recente de exclusão.
- Migration164 aditiva; preserva origens/eventos e a restrição atual de uma origem por fatura.
- Não envia POST, não recria cobrança nem reabre fatura; ativação do fluxo completo ainda pendente.

## 1.6.3 — 2026-11-06

- Cancelamento nativo de fatura após exclusão canônica de cobrança não paga, preservando origem e histórico.
- Timeout permanece pendente; recuperação por GET, sem repetir DELETE. Falha local não apaga sucesso remoto.
- Recibo e journal locais atômicos; ACL, revisão, módulo, estado financeiro e concorrência revalidados.
- Migration163 NOOP; sem reativação automática, exclusão histórica, baixa manual ou mudança do SDK.
- Contrato e evidências: `docs/consolidated-2026-11/asaas-invoice-lifecycle.md` no CRM.

## 1.6.2 — 2026-11-06

- Pagamento direto por token de cobrança CREDIT_CARD existente, com seleção dos contratos payWithCreditCard/payWithCard na guia Cartão.
- Migration nativa 162: ledger de tentativa única, consulta canônica de recuperação, ACL/CSRF/HTTPS e bloqueio de reenvio incerto.
- Sem captura de PAN/CVV, criação de token, voucher/débito, parcelamento ou lançamento financeiro implícito.
- Evidências e lacunas: `docs/consolidated-2026-11/asaas-direct-card.md` no repositório CRM.

## 1.4.5

- Renomeia o identificador local do cliente para `client_id` com migration aditiva e cópia dos vínculos existentes.
- Remove cabeçalhos de identificação e correlação enviados ao provedor; os registros operacionais permanecem locais.
- Distribuição integrada ao pacote ARGWS CRM.

# Changelog

## 1.5.0 (2026-02-26)


### Features

* adicionar log opcional em arquivo para debug do envio Asaas (9860602)
* atualizar SDK argws e implementar módulo Prefaces no adapter (4f6c73a)
* atualizar SDK e implementar módulo Prefaces no adapter (5adfc16)


### Bug Fixes

* alinhar release com versão do módulo e migration da integração (64b6d25)
* estabilizar hook de fatura e blindar json_decode no Asaas (92ad347)
* impedir salto de versão no release-please usando release-as (c4a6a10)
* normalizar tag de release e forçar sequência patch (a775985)
* reforçar sdk-first, wrappers do gateway e logging resiliente (ef2137a)
* sincronizar release-please com versão do integração ARGWS CRM (d508938)
* travar próximo release em patch sequencial (1.4.1) (ea605c2)
* usar SDK no Main e ajustar scan de padrões no CI/Release (793f0c9)

## 1.5.0 (2026-02-26)


### Features

* adicionar log opcional em arquivo para debug do envio Asaas (9860602)
* atualizar SDK argws e implementar módulo Prefaces no adapter (4f6c73a)
* atualizar SDK e implementar módulo Prefaces no adapter (5adfc16)


### Bug Fixes

* alinhar release com versão do módulo e migration da integração (64b6d25)
* estabilizar hook de fatura e blindar json_decode no Asaas (92ad347)
* normalizar tag de release e forçar sequência patch (a775985)
* reforçar sdk-first, wrappers do gateway e logging resiliente (ef2137a)
* sincronizar release-please com versão do integração ARGWS CRM (d508938)
* travar próximo release em patch sequencial (1.4.1) (ea605c2)
* usar SDK no Main e ajustar scan de padrões no CI/Release (793f0c9)

## 1.5.0 (2026-02-26)


### Features

* adicionar log opcional em arquivo para debug do envio Asaas (9860602)

## 1.4.0 (2026-02-26)


### Features

* atualizar SDK argws e implementar módulo Prefaces no adapter (4f6c73a)
* atualizar SDK e implementar módulo Prefaces no adapter (5adfc16)


### Bug Fixes

* alinhar release com versão do módulo e migration da integração (64b6d25)
* estabilizar hook de fatura e blindar json_decode no Asaas (92ad347)
* normalizar tag de release e forçar sequência patch (a775985)
* reforçar sdk-first, wrappers do gateway e logging resiliente (ef2137a)
* sincronizar release-please com versão do integração ARGWS CRM (d508938)
* usar SDK no Main e ajustar scan de padrões no CI/Release (793f0c9)

## 1.4.0 (2026-02-26)


### Features

* atualizar SDK argws e implementar módulo Prefaces no adapter (4f6c73a)
* atualizar SDK e implementar módulo Prefaces no adapter (5adfc16)


### Bug Fixes

* alinhar release com versão do módulo e migration da integração (64b6d25)
* estabilizar hook de fatura e blindar json_decode no Asaas (92ad347)
* reforçar sdk-first, wrappers do gateway e logging resiliente (ef2137a)
* sincronizar release-please com versão do integração ARGWS CRM (d508938)
* usar SDK no Main e ajustar scan de padrões no CI/Release (793f0c9)

## 1.0.2 (2026-02-26)


### Bug Fixes

* reforçar sdk-first, wrappers do gateway e logging resiliente (ef2137a)

## 1.0.1 (2026-02-26)


### Bug Fixes

* alinhar release com versão do módulo e migration da integração (64b6d25)

## 1.0.0 (2026-02-26)


### Features

* atualizar SDK argws e implementar módulo Prefaces no adapter (4f6c73a)
* atualizar SDK e implementar módulo Prefaces no adapter (5adfc16)


### Bug Fixes

* estabilizar hook de fatura e blindar json_decode no Asaas (92ad347)
* usar SDK no Main e ajustar scan de padrões no CI/Release (793f0c9)
