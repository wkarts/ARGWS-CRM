# Identidade global de equipe por e-mail

## Contrato

Uma identidade `staffid` tem um único e-mail de login global. A participação em várias empresas continua representada pelos vínculos existentes. Clientes e fornecedores não compartilham esta restrição. Nenhuma conta, identidade de instalação, papel, permissão ou vínculo é criado, fundido, excluído ou transferido pelo upgrade.

Novos inputs são limitados a 100 bytes ASCII imprimíveis, validados como e-mail e convertidos para minúsculas. Somente SPACE ASCII U+0020 nas bordas é removido; tabs, CR, LF, NUL e outros controles são rejeitados. Isso é intencional: `trim()` PHP sem segundo argumento diverge de `TRIM()` SQL. Não há remoção de pontos, `+tags` ou regras de provedores.

O banco mantém `staff_email_identity`, coluna gerada STORED `LOWER(TRIM(email))`, e índice global integral `uq_staff_email_identity`. A collation é explicitamente `utf8mb4_unicode_ci`, igual à coluna histórica de e-mail; não se muda silenciosamente a equivalência do login para binária. O preflight detecta também colisões adicionais dessa collation. O texto legado de `email` permanece intacto.

## Escopo técnico

- Core: cadastro, edição administrativa, self-profile e CompanyStaff; validação após hooks, antes de efeitos relacionados.
- Escritas diretas/API/HR usam transação de participantes InnoDB e notificações após commit. O wrapper já existente das empresas continua dono da sua transação; não se inicia uma transação aninhada.
- O índice impede colisões também em INSERT/UPDATE SQL direto e extensões fora do contrato. Isso não torna extensões arbitrárias transacionais; pre-hooks não revisados continuam bloqueados.
- O cadastro HR incrementa somente o contador de código de funcionário. Ele deixa de chamar o helper genérico que também redefinia o menu HR e iniciava uma transação de configurações aninhada; o helper de configurações permanece intacto.
- Login, recuperação de senha e 2FA exigem exatamente uma identidade, contando também contas inativas para detectar ambiguidade. O desafio 2FA permanece associado ao staffid e e-mail que o iniciaram.
- Timesheets utiliza a mesma resolução. Ramos staff do Purchase delegam ao núcleo; contatos de fornecedores permanecem no fluxo próprio.
- Instalador e provisionador executam o caminho de migrations nativas existente antes de declarar instalação concluída. Não é necessário reescrever migrations históricas ou o seed.

## Preflight somente leitura

Execute `php scripts/staff-email-preflight.php` com `STAFF_IDENTITY_DSN`, `STAFF_IDENTITY_USER`, `STAFF_IDENTITY_PASSWORD` e, opcionalmente, `STAFF_IDENTITY_PREFIX` no ambiente protegido do processo. DSN precisa iniciar com `mysql:` e incluir `charset=utf8mb4`. Não passe credenciais na linha de comando nem as imprima em logs. Use conta somente leitura para este diagnóstico.

O script abre uma transação somente leitura e imprime apenas contagens `rows`, `invalid_rows` e `duplicate_groups`. Saídas: 0 = nenhuma pendência encontrada; 2 = dados precisam de revisão; 1 = diagnóstico indisponível. Ele não aplica schema nem dados. O sucesso desse script não substitui o preflight repetido pela migration.

Se houver duplicatas ou dados inválidos, a migration para antes de qualquer DDL. Um administrador deve revisar as identidades no canal administrativo restrito e decidir separadamente como corrigir os dados; a ferramenta não escolhe uma conta sobrevivente nem renomeia ou remove registros. Logs de implantação não incluem e-mails, SQL, bindings, DSNs, senhas ou tokens.

## Instalação, upgrade e recuperação

1. Faça backup verificável e prepare janela de manutenção que suspenda todos os writers, inclusive integrações externas.
2. Execute preflight e resolva pendências em procedimento separado autorizado. Confirme MySQL/MariaDB e collation suportados.
3. Aplique a release pelo executor nativo. A migration valida InnoDB e a coluna histórica, executa preflight, adiciona coluna/índice e verifica as definições persistidas. Não aceita índice apenas pelo nome.
4. Só reabra writers depois do sucesso da migration e marcador nativo. Um conflito concorrente durante DDL faz o upgrade falhar; não é convertido em sucesso.
5. MySQL/MariaDB fazem commits implícitos em DDL. Interrupção após criação da coluna e antes do índice é recuperável por replay, sem reescrever os dados. A migration recusa transação externa para não confirmá-la implicitamente.
6. Confirme autenticação e edição de uma identidade de teste autorizada, a preservação dos vínculos e o tratamento controlado de email já utilizado.

Rollback de aplicação não deve remover automaticamente o índice: isso reabriria o risco de duplicação. Qualquer remoção de schema exige manutenção e plano separado. Dados legados não são transformados, portanto não há uma transformação de contas a desfazer.

## Evidências e limites

Testes sintéticos de normalização/autenticação podem usar SQLite com uma collation ASCII de fixture. Eles não provam collation Unicode, generated columns, locking ou concorrência MySQL.

A matriz dedicada roda MySQL 8.0/8.4 e MariaDB 10.11/11.4 com banco descartável e credenciais sintéticas. Ela cobre índice gerado, inserts/updates SQL diretos, duplicatas legadas, replay, definição adulterada e concorrência por processos/conexões independentes. Não usa produção, SMTP ou tokens reais. Consulte o resultado do CI do commit exato; workflow escrito não significa execução aprovada.

# Pull Request

- Branch de desenvolvimento: `fix/staff-email-global-identity`; integração destinada à PR48, core 4.6.6/466.
- Título: `fix(staff): enforce global canonical email identity`
- Descrição: fecha criação e alteração concorrente de identidades staff duplicadas e recusa autenticação/recuperação ambígua, preservando identidades e vínculos.
- Contexto: checks de formulário ou SELECT antes do INSERT não protegiam todos os writers nem a concorrência; autenticação selecionava a primeira row.
- Objetivo: identidade global única no banco e respostas controladas em writers/readers.
- Escopo incluído: core, HR, API staff, Timesheets, ramos staff Purchase, preflight e CI sintético.
- Fora do escopo: fusão de dados legados, autorização nova, mudança de papéis, deploy ou publicação.
- Decisões técnicas/arquitetura: coluna gerada e índice único; validação compartilhada e transações com participantes explícitos; fail-closed em ambiguidade.
- Alterações/arquivos: serviços StaffEmailIdentity, StaffEmailIdentitySchema e StaffIdentityTransaction; modelos/controladores de equipe/autenticação; script de preflight; testes/workflow; novas migrations dos módulos tocados. O diff final é a lista autoritativa.
- Banco: evolução aditiva descrita acima; bloqueios de DDL exigem manutenção. Nenhum seed de identidades ou modificação de memberships.
- APIs/contratos: conflito de identidade é rejeitado; métodos e rotas existentes preservados. Valores inválidos não geram SQL/debug público.
- Dependências/configuração: sem biblioteca runtime nova; preflight usa PDO MySQL, testes usam bancos descartáveis e extensões PHP documentadas no workflow.
- Segurança/privacidade: login/recovery/2FA fail-closed, logs operacionais sem dados pessoais; índice protege SQL direto.
- Desempenho/observabilidade: coluna/índice adicionais consomem espaço e manutenção de escrita; preflight pagina validação e agrupa duplicatas no banco. Contagens e erros de domínio identificam bloqueio sem PII.
- Testes implementados: suites de identidade, autenticação e banco real; regressões nativas conforme workflow.
- Testes executados localmente: `php tests/staff_email_identity_unit.php` (14 checks), `php tests/staff_email_authentication.php` (44 checks), `php tests/staff_identity_transaction_unit.php` (13 checks), lint PHP e `git diff --check`. O CLI sem configuração retorna 1 com diagnóstico genérico e sem stdout. MySQL/MariaDB não disponíveis no executor local; não declarar essa etapa aprovada antes do CI.
- Testes recomendados: homologação autenticada em instalação descartável e versão de banco efetivamente usada; instalação limpa e upgrade com marcador persistido.
- Validação manual: tentar reutilizar e-mail em outra empresa/self-profile; confirmar erro sem alterar perfil, senha, vínculos ou permissões; manter e-mail do próprio staff; entrar com caixa/espaços permitidos.
- Compatibilidade/riscos: legado inválido ou duplicado bloqueia upgrade; hooks não revisados e engines não transacionais bloqueiam escrita; módulos externos precisam tratar conflito de banco.
- Deploy/rollback: procedimento acima; nenhum deploy automático autorizado.
- Build/release/distribuição: core 4.6.6/466, com migration funcional chamando StaffEmailIdentitySchema::ensure; bump e nova migration por módulo funcional alterado; preservar migrations publicadas.
- Documentação: este arquivo e contratos dos serviços/testes.
- Checklist: revisar diff, lint, testes unitários, matriz real, tracking, convenções core/módulos e CI do commit final antes de integração/publicação.
- Commit sugerido: `fix(staff): enforce globally unique canonical email identities`.
- Merge sugerido: somente após revisão e autorização específicas; nenhuma ação realizada aqui.
- Versão da integração: core 4.6.6/466 após 4.6.5/465; preservar ambos os passos nativos.

## Complemento da integração PR48 / core466

O backup mysqli nativo preserva a DDL das colunas geradas e omite apenas seus valores da projeção INSERT. Metadados não verificáveis bloqueiam o dump; DEFAULT_GENERATED de colunas comuns continua incluído. O teste real de dump/restauração verifica valores, vínculos e índice único na matriz de quatro bancos.

`tests/staff_email_core_migration.php` usa App, App_Migration e o arquivo466 reais, com marcador465→466, preflight, DDL parcial, tracking falho e replay. Loader/configuração e a reconciliação IssuerCore posterior são adaptados; não é prova de instalação web completa desde394. A execução real dessas etapas continua dependente do CI.

API Staffs e Timesheets removem o campo técnico staff_email_identity somente da serialização pública, preservando os campos preexistentes.

Mensagens de conflito e falha usam chaves English/PT-BR no caminho direto e no atômico, com fallback genérico seguro quando o tradutor não está disponível. Os testes locais adicionais de serialização (11 checks) e mensagens (52 checks) passaram; a prova de backup/restauração e runner466 em MySQL/MariaDB permanece pendente do CI.
