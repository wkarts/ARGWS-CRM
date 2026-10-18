# Pull Request (rascunho local, não publicada)

## Branch
`feat/issuer-client-privacy`

## Título
`fix(issuers): close legacy client mutation and projection boundaries`

## Descrição
Checkpoint de defesa em profundidade para clientes privados do módulo de emitentes. Protege fronteiras legadas adicionais e evita reabrir o cadastro por campos financeiros ou caches. Mantém o rollout bloqueado e impede adoções novas enquanto o portal e os demais leitores não têm contrato scoped seguro. Não representa a entrega final de criação privada por padrão.

## Contexto e objetivo
Uma instalação e um banco; clientes privados por proprietário e compartilhamento explícito. Vínculo cadastral não transfere finanças. O checkpoint anterior restringia getters centrais, porém mutações e projeções ainda ignoravam a barreira.

## Escopo incluído e fora do escopo
Incluídos: guards de clientes/contatos/vault/grupos/arquivos, ações administrativas selecionadas, helpers/caches, tabelas, projeções API e financeiras mínimas, testes SQLite e gate de adoção fase 2.
Fora desta entrega: sessão/autenticação/portal scoped, criação privada completa, cobertura total de módulos e leitores diretos, previews genéricos/storage, documentos financeiros positivos, integração de recorrência, merge e deploy.

## Decisões técnicas e arquitetura
Predicados de propriedade persistida independem de módulo ativo ou empresa selecionada. IDs e relações não podem ser reparentados pelo payload legado. Negativos ocorrem antes de hooks, custom fields, hash, e-mail ou arquivo. Schema parcialmente ausente falha fechado. Não há exceção global para PDF/extrato; só a identidade mínima de fatura legada autorizada tem fallback dedicado.

## Alterações e arquivos
- `application/services/ClientIssuerScope.php`: contato, schema parcial e gate de adoção.
- `application/models/Clients_model.php`, `Client_groups_model.php`, `Client_vault_entries_model.php`: leituras/mutações e identidades.
- `application/models/Misc_model.php`, `modules/api/models/Api_model.php`: busca e projeções limitadas.
- `application/controllers/admin/Clients.php`, `application/controllers/Download.php`, `modules/api/controllers/Contacts.php`: controles de ações e vínculos da rota.
- `application/helpers/clients_helper.php`, `upload_helper.php`: caches, identidade, anexos e upload.
- `application/views/admin/tables/all_contacts.php`, `contacts.php`: predicados em tabelas.
- `modules/issuers/services/IssuerRegistry.php`, `views/manage.php` e idiomas: gate de adoção e aviso visível.
- `tests/client_issuer*.php`, `tests/support/client_issuer_test_support.php`: cobertura sintética; fixtures financeiras ajustadas para a dependência real.
- `.github/workflows/client-issuer-scope.yml`: matriz PHP 8.1/8.5 SQLite.
- `docs/issuer-isolation-inventory.json` e documentos desta pasta: revisão parcial e bloqueadores.
Nenhum arquivo de produção removido.

## Banco de dados
Não há migration, seed ou alteração de dados reais. Fixtures sintéticas incluem propriedade, contatos, links, arquivo, vault e administração. Não foram executados MySQL/MariaDB, carga, locks ou recuperação de backup.

## APIs, contratos e integrações
API de contatos não retorna senha ou tokens de recuperação/verificação. Busca de contatos não devolve cadastro completo do cliente. Busca de fatura legada só projeta identidade mínima do cliente. Isso reduz campos historicamente expostos e exige revisão dos consumidores antes da publicação. Rotas administrativas privadas passam a retornar indisponível/404; novos registros privados ficam bloqueados nesta fase.

## Dependências, configuração e ambiente
Nenhuma dependência de produção nova nem segredo. CI usa extensão PDO SQLite. Runtime local: PHP 8.5 WASM e SQLite; CI nativo ainda não rodou neste commit local.

## Segurança e privacidade
Negativos cobrem IDs adivinhados, OR, reparenting, caches obsoletos, destinatário de arquivo de outro cliente, conta administrativa sem autorização scoped e schema parcial. Não se afirma isolamento total. Portal de cliente já adotado seria afetado pelo filtro e impede rollout até composição segura.

## Desempenho e observabilidade
Guards acrescentam consultas de existência/propriedade. Não há benchmark; medir listas grandes e perfis com muitos contatos. Não foram adicionados logs de dados pessoais nem efeitos externos nas negativas. O inventário é auxiliar e continua com estados parciais/não revisados.

## Testes implementados, executados e recomendados
Implementados/executados localmente: suítes `client_issuer*`, regressão `api_issuer_scope`, core `invoice_issuer_scope`, `invoice_issuer_reports`, `issuers_controller`, lint dos PHP alterados e testes Python do inventário. A evidência detalhada está em `client-issuer-privacy-phase2.md`.
Tentada, bloqueada: `invoice_issuer_recurring_lock.php` depende de `RecurringInvoiceLock.php`, ausente no checkpoint-base.
Recomendados e pendentes: PHP 8.1/8.5 nativo, MySQL/MariaDB, sessão/login/reset/logout, usuário legado com contato, cliente já adotado, navegador real, storage/preview, autorização de extensões, efeitos de hooks e concorrência.

## Validação manual
Em ambiente sintético composto, comparar conta de emitente X, Y, admin sem vínculo e cliente legado. Validar cadastro, contato, edição, anexos, compartilhamento/revogação, buscas e PDF de fatura `NULL`. Repetir login/recuperação/portal antes e depois de adoção somente após construir caminho scoped. Esse roteiro ainda não foi executado em browser.

## Impactos, compatibilidade, riscos e breaking changes
O legado sem ownership mantém controles positivos testados. Projeções API ficam menores. Clientes já adotados deixam de ser acessíveis pelo core global; a sessão atual do portal não suporta essa mudança, portanto o rollout permanece bloqueado. Nenhuma adoção automática, migração por cliente ou liberação financeira ocorre.

## Deploy
Não implantar este checkpoint isolado. Compor mudanças financeiras, sessão/portal e cobertura restante, revisar dados já adotados, testar CI e obter autorização de publicação/deploy. Nada foi publicado ou implantado.

## Rollback
Não voltar automaticamente a código que reabra clientes privados. Definir rollback consistente de aplicação/dados mantendo a barreira ou restaurando backup aprovado. Não executado.

## Build, release e distribuição
Não se aplica a esta alteração local. Nenhuma imagem, pacote, release, tag ou PR remota criada.

## Documentação
`docs/security/client-issuer-privacy-phase2.md` detalha contrato, prova limitada e bloqueadores de integração.

## Checklist
- [x] Guards e testes locais com dados sintéticos
- [x] Negativos antes de efeitos; controles legados positivos
- [x] Nenhum segredo, deploy ou publicação remota
- [x] Adoção nova e operação financeira positiva mantidas bloqueadas
- [ ] Portal/sessão scoped e criação privada por padrão
- [ ] Leitores externos, storage e concorrência fechados
- [ ] MySQL/MariaDB, browser e CI nativo no commit composto
- [ ] Revisão independente e aprovação para publicação

## Commit sugerido
`fix(issuers): guard legacy client mutations and projections`

## Merge sugerido
Não fazer merge deste checkpoint isolado. Depois da composição validada: `feat(issuers): isolate client ownership and explicit sharing`.

## Versão sugerida
Não avançar versão neste checkpoint. A funcionalidade completa tende a incremento minor; qualquer mudança de contrato API deve ser analisada antes da versão final.
