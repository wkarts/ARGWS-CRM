# Asaas157 — extensão nativa para origem protegida

Implementação nova, não recuperação de código antigo. Migration157 cria apenas `asaas_company_origin_policy` (origin_id PK, issuer_id, origin_reference SHA256 único, mode manual_only, created_at). Nenhum backfill, exclusão ou alteração de origens antigas; downgrade destrutivo negado. Cabeçalho1.5.7. A migração preserva156 e requer core473 da composição para release integrada, sem modificar core nesta frente.

`CompanyService::issue` mantém argumentos existentes e acrescenta `originGuard=null, guardTables=[], manualOnly=false, originReference=null`. Manual-only exige callback interno confiável e referência SHA256 ao journal da integração. Não é input livre de controller. Callback somente veta: permissões nativas, conta verificada, identidade do cliente remoto existente, invoice/balance e perfil continuam exigidos. Guard é executado antes/depois das transações de preflight/replay, perfil, reserva, pre-send e confirmação. Transporte não permanece sob transação. Recusa após POST mantém origem incerta, sem reemitir automaticamente.

Política é gravada na mesma reserva antes do POST. Webhook/repair enqueue rejeitam manual-only; worker rejeita inclusive job antigo antes do GET. `receiptClaim` também nega manual-only, então configuração futura da automação da conta não concede baixa. Reconciliação explicitamente autorizada exigirá API futura com guard confiável e ledger canônico; não existe flag pública para contornar esta negação.

`observe(issuer,actor,origin,decrypt,transport=null,recoveryId='',originGuard=null,guardTables=[])` realiza GET remoto e persiste prova canônica sob guard, mas não enfileira baixa. Não é read-only de banco; use apenas comando explícito. `query` nativa de outras origens conserva comportamento anterior. Nenhum upsert/POST de cliente. Tokens nunca retornados.

Testes adicionados: fixture PHP real `tests/companies/asaas_origin_policy.php` reutiliza PDO/native writer com transporte simulado; migração155 preserva checks históricos156 e agora testa157/DDL/replay/sem backfill; Python `tests/asaas_origin_policy_static.py` tem3 checks estruturais. PHP/MySQL NÃO executados localmente (runtime ausente). CI deve executar fixtures e lint, no banco descartável, com gd/mbstring/openssl/pdo_mysql. Política após ativação pode deixar origens pendentes em revisão, não “corrigir” com reemissão.

Instrumentos PIX/boleto são escopo posterior; esta etapa não afirma instrumentos disponíveis, conciliação financeira completa, produção ativada ou chamada real.

## Instrumentos — segundo checkpoint157

`CompanyService::instruments(issuer,actor,origin,decrypt,transport=null,originGuard=null,guardTables=[])` usa origem emitida PIX/BOLETO, conta/cliente verificados e GET de pagamento antes/depois da recuperação. Revalida guard e snapshot após HTTP. Retorna texto limitado (Pix copia-cola/expiração ou linha digitável/código/nosso número), sem URL externa, HTML ou imagem. Não persiste prova, jobs ou pagamentos. Mudança de estado durante obtenção nega retorno. É ação explícita de rede, não invocar implicitamente em status GET da ponte.

Contratos conferidos no OpenAPI versionado do SDK e referências oficiais [Pix](https://docs.asaas.com/reference/obter-qr-code-para-pagamentos-via-pix) e [boleto](https://docs.asaas.com/reference/obter-linha-digitavel-do-boleto), consultadas em2026-10-24. Apenas dois sufixos GET foram adicionados à allowlist do cliente. Sem POST de instrumento/cliente.

Fixtures PHP novas: `tests/asaas_company_instruments.php` (validação/transport allowlist) e `tests/companies/asaas_instruments.php` (native source, guard revogado durante HTTP, corrida de estado e zero escrita). Não executadas localmente. Testes Python estruturais agora4 PASS; não equivalem a PHP/MySQL/HTTP real. Instrumentos não são confirmação de recebimento nem liberam conciliação manual-only ainda fechada.
