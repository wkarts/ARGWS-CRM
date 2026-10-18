# Pull Request

## Branch

`feat/company-scoped-settings` (local; base `df09a741`).

## Título

`feat(settings): isola informações da empresa e SMTP na rota nativa`

## Descrição

Primeiro incremento funcional das Definições por empresa: serviço explícito sem fallback de credenciais, identidade/perfil canônicos compartilhados com cadastro, persistência isolada e renderer protegido para Informações da Empresa e SMTP dentro de `admin/settings`.

## Contexto e objetivo

O cadastro de empresas já existe no núcleo. As opções e a hidratação da aplicação ainda eram globais. O objetivo desta fatia é permitir ler/gravar configurações de duas empresas com isolamento verificável, preservando a principal por identificação explícita e recusando rotas ainda não adaptadas.

## Escopo incluído e fora do escopo

Incluído: storage, runtime com lease, adapter de identidade/perfil, Settings_model, renderer company/email e testes. Fora: transporte SMTP/OAuth, Connect/bancos operacionais, todos os demais grupos de Definições, isolamento de clientes/financeiro, branding, migrations de versão canônica, publicação, merge e deploy.

## Decisões técnicas e arquitetura

Reutiliza `issuers_registry.settings_json`; não cria schema paralelo. Nome/documento vivem no registro canônico. Perfil da principal usa opções legadas existentes; secundárias usam JSON privado. Nenhuma credencial é copiada da instalação. Contexto pré-autoload e selo de lifecycle são dependências da composição coordenada. Ver `settings-isolation.md`.

## Alterações e arquivos

Adicionados: `application/services/CompanySettingsStorage.php`, `CompanySettingsRuntime.php`, `CompanySettingsIdentity.php`; `application/views/admin/settings/company_scoped.php`; `tests/settings/company_settings.php`, `settings_model_controller.php`; esta documentação e `settings-isolation.md`.

Modificados: `application/models/Settings_model.php`, `application/controllers/admin/Settings.php`.

Removidos: nenhum.

## Banco de dados

Sem DDL, migration ou renumeração. Usa colunas já existentes e lock/transações nativos. Gravações no registro/JSON são serializadas; metadata de profile/options preserva chaves não relacionadas. Na principal, a fonte histórica é mantida, exceto identidade canônica já adotada pelo núcleo. Não foi executada alteração em banco real.

## APIs, contratos e integrações

Mesma rota `admin/settings`; grupos secundários permitidos: `company`, `email`. POST requer token do contexto e CSRF nativo. Jobs usam empresa explícita após validação de ownership, jamais sessão. Runtime/helper e aliases legados têm contrato documentado. Serviço de transporte não é chamado.

## Dependências, configurações e variáveis

Nenhuma dependência de produção ou variável nova. Usa CI Encryption existente; a chave permanece externa e não é modificada. Requer composição de `CompanyRequestScope`, gate de `AdminController` e dispatcher dos helpers pelo trabalho de contexto.

## Segurança e privacidade

ACL por recurso/empresa sem AND de staff global, revogação revalidada, negação sem fallback, identidade fiscal validada, secrets fora da view/audit, escaping, CSRF, token por contexto e bloqueio de sobrepostagem/recursos globais. Catálogos de empresas devem omitir `settings_json` na composição. Valores de teste são inteiramente sintéticos.

## Desempenho e observabilidade

Sem cache global de configuração. Leitura explícita revalida empresa e consulta a fonte correta. Escrita serializa pelo lock nativo; cargas altas precisam de medição antes de ampliar o rollout. Audit registra ator/empresa/evento, sem valores.

## Testes implementados e executados

108 verificações de serviços/model/controller/view passaram em PHP 8.1 e 8.5 via WASM/SQLite; inclui 78 de storage/identidade. Lint PHP passou; regressões PHP de núcleo/registro/ACL/upgrade/idioma/gateways passaram; 18 testes Node de contratos de plataforma/integrações/UI passaram; diffcheck passou. Comandos e limites estão em `settings-isolation.md`.

## Testes recomendados e validação manual

Pendente: MySQL/InnoDB real, concorrência, criptografia OpenSSL real, lifecycle HTTP composto e navegador com alternância/back/stale-form, além de transporte por empresa quando implementado. Roteiro reproduzível em `settings-isolation.md`.

## Impactos, compatibilidade, riscos e breaking changes

Principal preserva armazenamento de opções existente, mas aliases fiscais passam a uma autoridade canônica. Secundárias não acessam seções não adaptadas. Sem mudança de assinatura dos helpers legados. Não considerar implantação completa enquanto lifecycle, helper/App e sanitização dos catálogos não estiverem compostos/revisados. Arquivos fiscais históricos permanecem imutáveis.

## Deploy

Nenhum deploy realizado ou autorizado. Para futura implantação: compor contexto+settings+catálogo, executar regressões/MySQL/HTTP, confirmar versão/migration canônica definida para o núcleo e revisar backup/rollback. Não habilitar seleção secundária com apenas este patch.

## Rollback

Desabilitar acesso a escopos secundários antes de reverter código. Preservar `settings_json` e snapshot legado, sem DROP/DELETE nem cópia de secrets para opções globais. Reversão parcial do middleware enquanto o seletor estiver ativo é insegura. Como identidade passa a canônica, leitores legados precisam continuar via adapter ou de plano revisado; não restaurar silenciosamente opções fiscais desatualizadas.

## Build, release e distribuição

Não se aplica build adicional. Nenhuma release, imagem, ZIP, push ou PR remota publicada.

## Documentação

`docs/companies/settings-isolation.md` documenta fontes, APIs, segurança, evidências, limites e roteiro de composição.

## Checklist

- [x] Código local funcional e dados sintéticos
- [x] A/B positivos e negações sem fallback
- [x] Identidade/perfil compartilhados sem segunda autoridade
- [x] Secrets ausentes da view/audit e criptografia existente preservada
- [x] Sem DDL/migrations históricas alteradas
- [x] Lint, testes focados e regressões executados
- [ ] Lifecycle e helpers compostos end-to-end
- [ ] MySQL/InnoDB, concorrência, navegador e criptografia nativa
- [ ] Aprovação antes de publicar, mergear ou implantar

## Commit sugerido

`feat(settings): isola identidade, perfil e SMTP por empresa`

## Merge sugerido

`feat(core): incorpora a primeira fatia de definições por empresa`

## Versão sugerida

Incremento funcional minor, condicionado ao plano canônico de versão do núcleo. Não atribuir número ou criar migration artificial nesta fatia.
