# Configuração protegida por integração — nova implementação 102

Base: CRM main7c55d08a, módulo publicado1.0.1/101. Esta mudança é uma **nova implementação**, não recuperação dos bytes ou resultados perdidos do checkpoint anterior.

## Interface e comportamento

Painel nativo: `/admin/pige_bridge/settings`, item Configuração → Integração PIGE — ponte CRM. Mantém `AdminController`, `CompanyRequestScope`, sessão administrativa, CSRF nativo e token de formulário próprio; não abre bypass para empresas não primárias. No contexto administrativo primário, mostra somente empresas e conexões autorizadas por `settings.edit`.

Cada conexão tem identificador e scope imutáveis `{school_id,issuer_id,account_id,environment}`. O issuer precisa ser empresa cadastrada e autorizada. Não existe cliente/pagador fixo: o futuro pedido v2 identificará o cliente por solicitação. O token personalizado é fornecido explicitamente, cifrado pela biblioteca nativa, nunca devolvido na listagem ou preenchido no formulário. Rotação exige confirmação explícita na UI e invalida consentimento de fatura local. Nenhuma credencial de produção é criada pelo código ou fixture.

`PigeBridgeConnections(db,encryption,prefix)` oferece `active(deployment)`, `save`, `listed`, `saveMapping`, `mapping` e `assertMappingInOwned`. O runtime não lê empresa da sessão. `active` preserva configuração v1 de deployment enquanto a tabela não existir; colisões entre deployment e DB, scopes ou tokens (inclusive retidos/desabilitados) falham fechadas. Ao migrar um identificador do deployment para o painel, remover explicitamente a entrada antiga antes de operar; não há sobrescrita silenciosa de configuração.

Mapping local é exatamente `{enabled,actor_id,currency_id}`. Salvar/rotacionar token ou autorizar mapping exige também permissão de faturas e clientes do configurador; ator delegado precisa de `invoices.view`, `invoices.create`, `customers.view`, acesso ativo à empresa e BRL. Delegar outro funcionário requer master ativo. Alterações e rechecagens acontecem no lock transacional nativo; revisão otimista previne formulário obsoleto. Revogação mantém possível desabilitar conexão/mapping após expirar permissões do ator, sem depender de banco externo. Alteração da conexão desabilita mapping, preservando ator/moeda históricos.

## Schema, compatibilidade e limites

Migration102 aditiva cria `pige_bridge_connections`, com scope/token hash únicos, cifra, revisão, mapping nullable e metadados; enabled default0. Valida tipos, nulabilidade, chaves, collation, default e InnoDB no replay. Não toca recibos101 nem tabelas financeiras. Downgrade destrutivo é recusado. `install.php` mantém runner nativo e verifica o target do header, sem DDL próprio.

Salvar configuração não habilita gates globais, não emite faturas/instrumentos e não aplica pagamentos ou efeitos acadêmicos. V1 continua contrato bloqueado até implementação/capacidade própria validada. Não houve chamada bancária, ativação, merge, deploy ou publicação.

## Evidências

Implementados: testes PHP puros de scope/mapping/duplicatas/fallback; controller admin/método/CSRF/rotação; migration101→102/replay/defaultinseguro; MySQL descartável com cifragem nativa, scopeimutável, ACL/delegação, sessãoA/B, rotação/revogação e zero criação de fatura. A fixture MySQL executa a migration original com alias de classe apenas no teste, pois o runner essencial já ocupou o nome global102; não prova execução simultânea de módulos no mesmo request.

PHP/MySQL **não executados neste ambiente**. Verificações Python estáticas e inventário UI reportadas no checkpoint não comprovam render, autorização HTTP, concorrência, tracking nativo ou homologação. Nenhum teste histórico do checkpoint perdido é reutilizado como evidência desta implementação.
