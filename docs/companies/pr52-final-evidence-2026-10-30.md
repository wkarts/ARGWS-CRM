# PR 52 — evidência consolidada da candidatura

Data da consolidação: 2026-10-31 (UTC). Repositório: `wkarts/ARGWS-CRM`.
PR: `#52`, branch `fix/native-company-experience`, base `main`.

## Escopo concluído

O complemento preserva o histórico da branch e fecha as 44 ações necessárias
que permaneciam fora do catálogo multiempresa: Clientes (11), Despesas (2),
Faturas (3), Pagamentos (1), Projetos (21) e Tarefas (6). Cada ação continua
na URL/controller nativo e passa por `NativeCompanyParityHttp`, com contrato
de dados próprio, revisão/CAS, CSRF, permissão e filtro de empresa. O teste
`tests/native_pr52_parity_catalog.test.cjs` verifica a lista completa, o
catálogo e o adaptador delimitado, sem transformar uma simples rota liberada
em prova de equivalência.

A matriz atualizada contém 331 métodos nos dez módulos: 316 catalogados para
empresas secundárias e 15 mantidos fechados por serem internos ou não
aplicáveis (10 e 5, respectivamente). A matriz continua distinguindo admissão
de rota, adaptação funcional e homologação externa.

## Identidade, PWA e superfícies preservadas

Foi aplicada a identidade V2 nos pontos padrão de setup/instalação, login,
administração, portal, relatórios/PDF, favicon e ícones PWA, usando as
variantes dedicadas `logo-horizontal-colorido.svg`, `logo-horizontal-branco.svg`,
`icon-192.png`, `icon-512.png`, `icon-maskable-512.png`, `apple-touch-icon.png`
e `favicon.ico`. A precedência permanece instalação → empresa principal →
ARGWS V2; marcas personalizadas da instalação e das empresas continuam
preferenciais. O manifesto preserva `id`, `scope` e `start_url`, usa revisão
de assets e não adiciona cache privado, URL externa arbitrária ou convite
insistente.

## Dados, instalação e upgrade

Os gates remotos exercitam instalação limpa, login, seleção da empresa,
empresa principal, A/B, usuário restrito, upgrade, CSRF, isolamento,
idempotência, revisão/rollback, contratos, PDFs, projetos, tarefas,
cronômetros, pagamentos, anexos, portal e fila financeira em bancos
descartáveis. O smoke de upgrade explicita a única transformação histórica
observada: a migration 342 normaliza `tblclients.default_language` de `NULL`
para `portuguese_br`; todos os demais campos do cliente, identidade, vínculos
e tabelas de negócio são comparados sem essa coluna e permanecem preservados.
Nenhuma migration histórica foi reescrita.

## CNAB400

CNAB400 permanece disponível e configurável por empresa, sem habilitação
global automática. A configuração, convênio, layout, autorização, integridade,
idempotência e validação do candidato permanecem protegidos; `operational=false`
continua significando que não se declara transmissão/aceite bancário sem os
insumos e a homologação externa correspondentes. A ausência de homologação
não desativa o recurso nem bloqueia publicação ou candidatura à revisão.

## Limpeza segura

Foram removidos somente os sete descartáveis confirmados pela auditoria:

- `upload/image(7).png`
- `upload/image(8).png`
- `application/helpers/misc_helper.php.bak`
- `application/helpers/assets_helper.php.bak`
- `application/controllers/Cron.php.bak`
- `application/controllers/admin/Emails.php.bak`
- `application/language/portuguese_br/portuguese_br_lang.php.bak`

Uploads legítimos, `.htaccess`, vendor, templates, fontes e evidências foram
preservados. O pacote de fontes exclui descartáveis, segredos, bancos de
produção e caches reinstaláveis.

## Verificação

O HEAD remoto da branch contém o código, as fixtures, o contrato de rota e os
inventários reconciliados após os ciclos de focused. O SHA efetivo e os links de
checks são mantidos na descrição da PR para evitar registrar um SHA intermediário
quando a documentação é publicada junto com o último commit.
Em particular, `NativeCompanyParityHttp` só carrega a classe real de escopo
quando ela ainda não existe, preservando o bootstrap de produção e evitando
redeclaração quando a fixture fornece seu double controlado. A fixture de
saída aceita `null` no caminho de detalhe vazio e o teste de rotas reconhece
`save_completed_checklist_visibility` como POST nativo admitido e
`clients/delete` como a exclusão delimitada já implementada, porque a ação já
possui escopo de empresa, CSRF, permissão e teste próprio.

A fixture de exclusão de cliente foi alinhada ao contrato efetivo: requests
incompletos são recusados por verbo, ownership ou revisão, e a exclusão
delimitada nunca cai na cascata legada. A documentação de fronteira explicita
que dependências não inventariadas continuam bloqueando a operação.

O auditor `scripts/audit-issuer-isolation.py` fecha sem erros (`677` arquivos;
`647` unreviewed, `30` partial). O inventário lexical de boundaries está
reconciliado em `15151` arquivos, todos ainda unresolved e
`operational_ready=false`, com `source_sha256=41b9d406…`; o inventário de
superfícies operacionais mantém `4675` fontes, `operational_ready=false` e
`source_manifest_sha256=7948c2c6…`. Esses estados são deliberadamente
conservadores e não liberam rollout.

Validação local adicional: `PATH="$PWD/node_modules/.bin:$PATH" ./node_modules/.bin/mix --production`
reconstruiu os bundles compilados sem divergência; `node --test
tests/native_tasks_shared.test.cjs tests/native_workspace_driver.test.cjs` passou
13/13 e `python3 tests/ci/test_native_company_focused.py` passou 10/10.
Os IDs e o resultado do CI do SHA final são registrados na descrição da PR e no
relatório de evidências entregue com ela após a execução integral do CI final.

Limites explícitos: nenhuma credencial, transmissão bancária, e-mail externo,
webhook de produção ou homologação bancária foi executado. Isso não reduz a
disponibilidade/configurabilidade interna do CNAB400 nem transforma testes
sintéticos em homologação.
