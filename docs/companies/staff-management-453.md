# Equipe por empresa e composição atômica do Staff — incremento 453

## Entrega e limites

O núcleo oferece a página própria **Equipe da empresa** em `admin/company_staff`. A página lista identidades vinculadas à empresa ativa e permite criar uma identidade sem administração global, vincular uma identidade já existente pelo e-mail exato, editar os dados pessoais quando há autoridade sobre a identidade, alterar permissões da empresa e revogar somente seu vínculo.

O formulário nativo de Staff na empresa principal continua disponível nas condições de acesso já existentes. Quando recebe os campos de empresas, perfil, permissões nativas, departamentos, campos personalizados, leitura inicial de anúncios, vínculos, permissões por recurso, preferência e auditoria pertencem à mesma transação. A ausência dos campos de empresas conserva a compatibilidade das edições legadas; um novo colaborador ativo continua exigindo vínculo e empresa padrão explícitos.

Isso conclui o recorte de gestão de equipe descrito aqui. Não significa que todo módulo de RH, folha, comissões, fornecedor ou Purchase que escreve na identidade global esteja adaptado. A existência de uma permissão no catálogo não libera uma rota legada bloqueada. Não foram abertos CRUDs legados universais em empresas secundárias.

## Contrato de autorização

| Operação | Autoridade exigida | Estado preservado |
| --- | --- | --- |
| Listar e consultar | `staff.view` na empresa ativa | Nenhuma identidade de outra empresa é hidratada por ID substituído. O master já reconhecido pode ser exibido com seu acesso automático. |
| Criar ou vincular | `staff.view` e `staff.create` | Identidade nova nasce ativa, sem admin e sem papel global; vincular não reescreve nome, senha, administração ou outros vínculos. |
| Editar concessões | `staff.view` e `staff.edit` | Só as concessões da empresa atual são alteradas. Quem não é master pode conceder o que possui e conservar concessões já existentes. |
| Editar perfil global | Autoridade anterior, mais master ou identidade não administrativa, sem papel global e vinculada exclusivamente à empresa atual | Um gerente pode editar o perfil de sua identidade exclusiva. Quando ela é compartilhada, pode continuar gerindo o vínculo próprio, mas não seu nome, e-mail ou telefone. |
| Revogar | `staff.view` e `staff.delete` | Não permite revogar o próprio executor ou o master. Mantém a identidade, seus dados, outras empresas e seus históricos. |
| Empresa padrão | Escolha explícita e vínculo válido | Vincular sem marcar a opção conserva a preferência anterior. Revogar a empresa padrão limpa a preferência; não escolhe outra silenciosamente. |

As flags históricas `can_manage_clients`, `can_share_clients` e `can_manage_integrations` de vínculos retidos são preservadas. Essas flags não substituem a autorização por recurso. O contrato não transforma administradores legados em master, não redefine a empresa principal e não exige nova designação por console para o master já registrado.

## Escrita, concorrência e repetição

`CompanyStaff` valida o schema, exige participantes InnoDB, recusa uma transação do chamador e adquire o lock comum do registro. A autorização é conferida antes da espera, dentro da transação e novamente antes da confirmação. A revisão do alvo inclui a identidade global, todos os vínculos, concessões e preferência, para recusar um formulário que ficou obsoleto durante uma alteração em outra empresa.

Cada comando contém uma chave aleatória, a empresa e o ator. A tabela `company_staff_commands` guarda operação, hash da entrada, ID histórico do alvo e hash do resultado. Senhas em claro não integram recibos ou auditoria. Repetir exatamente o mesmo comando só confirma o resultado se a identidade e seus acessos ainda corresponderem ao resultado registrado; uma identidade apagada ou alterada não é recriada pelo replay.

O recibo tem FK para a empresa e deliberadamente **não** tem FK para `staff`. Seu `staff_id` é histórico, como o sujeito de uma auditoria. Isso mantém a exclusão nativa elegível sem impor uma nova restrição ou introduzir uma corrida entre o preflight de exclusão e a criação de recibos. O teste apaga a identidade e verifica que o replay não a ressuscita. Não existe exclusão global de identidade na página própria de equipe.

As gravações têm testemunhos de leitura: não basta um retorno verdadeiro, `affected_rows()` ou a existência de uma linha. Perfil, vínculos, ACL, preferência, auditoria e recibo são comparados ao estado esperado; uma escrita ignorada ou uma mutação posterior ao primeiro testemunho causa rollback. O schema exige colunas, defaults, EXTRA, engine, collation, índices e FK exatos. A migração usa o mecanismo nativo do core e suporta nova tentativa após interrupção, sem backfill de permissões.

## Staff nativo: composição e efeitos posteriores

`Staff::member()` e `Staff::edit_profile()` encaminham as solicitações com campos de empresa para `Staff_model::save_with_company_access()`. Não há fallback para um modelo fictício sem esse método nem retorno ao fluxo de sucesso parcial. A composição usa o modelo real, mantém as proteções do perfil pessoal e do master e normaliza papel vazio para `0` no caminho composto.

Além de `staff`, a unidade cobre `staff_permissions`, `staff_departments`, `customfieldsvalues`, `dismissed_announcements` e as tabelas do registro/ACL. `customfields`, `departments`, `roles` e `announcements` participam da verificação de armazenamento. Os escritores fornecem os valores esperados; os testemunhos são repetidos depois da auditoria de ACL. A comparação de `hourly_rate`, um `DECIMAL(15,2)` nativo, compara o valor decimal exato sem floats: `0` e `0.00`, assim como `12.5` e `12.50`, representam o mesmo valor. Uma escrita ignorada ou uma alteração numérica diferente continua falhando.

O inventário do código da base não encontrou callbacks registrados nos filtros `before_create_staff_member`, `before_update_staff_member` e `before_staff_update_profile`; as ocorrências locais são chamadas a `apply_filters`. Uma extensão que passe a registrar um desses filtros, ou o filtro global `all`, deve ter seus participantes transacionais e efeitos revisados. Até lá, o caminho composto a recusa antes da escrita com uma mensagem explícita. Isso evita prometer atomicidade sobre tabelas ou chamadas externas de terceiros desconhecidas.

Notificação de boas-vindas, callbacks posteriores, log de atividade e avatar são efeitos depois do commit. Uma falha de transporte ou exceção de callback/avatar não desfaz a identidade já confirmada e é apresentada separadamente. Não há reenvio automático: um callback pode ter realizado parte do efeito antes de falhar. A página própria de equipe cria a senha inicial sem envio de e-mail. Os mecanismos de recuperação de senha e autenticação permanecem os nativos.

## Provas executadas nesta reconstrução

O executor anterior perdeu fontes locais não publicados. Estes resultados foram produzidos novamente a partir do HEAD remoto real `543ff96fe2a49ceef48d8595a58e8400befc6266`, e não reaproveitam resultados do trabalho perdido.

| Prova | MariaDB 10.11.14 / PHP 8.3.6 / 128 MiB | MySQL 8.0.46 / PHP 8.1 / 128 MiB | Conteúdo distinto |
| --- | --- | --- | --- |
| `tests/companies/staff_management.php` | PASS | PASS | 56 verificações: A/B/principal, vínculo e padrão, autoridade exclusiva/compartilhada, master, falhas e SQL ignorado, fences finais, recibo após exclusão. |
| `tests/companies/staff_native_atomic.php` | PASS | PASS | 69 verificações: modelo nativo real, todos os participantes, papéis, decimal, falhas, testemunhos finais, efeitos depois do commit e proteção do master/perfil pessoal. |
| `tests/companies/staff_native_controller.php` | PASS | PASS | 30 verificações adicionais sobre o controlador/helper real: formulário, CSRF, composição, avatar, negativa de acesso e ausência de sucesso parcial. Inclui as 69 anteriores; elas não são contadas novamente. |
| `tests/companies/staff_concurrency.php` | PASS | PASS | 22 verificações: cinco corridas com espera de lock realmente observada e um SIGKILL depois do INSERT, seguido de retry. |
| `tests/issuers_staff_access.php` | PASS | PASS | As 54 verificações de acesso existentes, incluindo negativas por recurso; inclui a suite de registro, sem somá-la novamente. |
| `tests/issuers_staff_master.php` | PASS | PASS | As 26 verificações adicionais existentes de master e perfil pessoal, com arquivo original inalterado. |

São **177 verificações distintas novas** nos quatro primeiros grupos, executadas nos dois motores. A execução em dois motores não duplica essa contagem. O conjunto antigo de acesso/master é regressão adicional; os 76 checks de registro que ele inclui não são apresentados como novos checks de equipe.

As cinco corridas observadas são: mesmo comando por dois processos; criações independentes; duas revisões concorrentes da mesma identidade; concessão do gerente revogada enquanto ele espera; identidade apagada enquanto um vínculo aguarda. O SIGKILL ocorre após o INSERT efetivo do perfil, antes de commit; outro observador não o vê confirmado, o processo é interrompido e a repetição cria apenas uma identidade e um recibo.

O teste `staff_browser.cjs` passou no fixture HTTP nativo atualizado de 452 para 453, com Chromium `156.0.8078.47` e consultas SQL como testemunhos. Foram executados criação em A/B/principal, gerenciamento por colaborador sem admin global, perfil exclusivo editável, compartilhamento da mesma identidade com B, edição de ACL própria e recusa de perfil compartilhado, revogação A preservando B, CSRF nativo e próprio, revisão/contexto obsoletos, revogação de acesso de sessão já autenticada e formulário Staff nativo com papel vazio. Master17, principal1, vínculos anteriores e preferência do master permaneceram iguais. As imagens de desktop e mobile foram inspecionadas; a tabela tem rolagem interna no viewport pequeno, sem exceder a largura da página. O resultado estruturado está em [staff-management-453-results.json](evidence/staff-management-453-results.json).

A última execução registrou 61 navegações, três respostas reais de `tasks/table` HTTP200 com JSON válido e nenhum erro JavaScript. O harness agora espera terminar a renderização de rede iniciada no login, nas trocas de empresa e no retorno do formulário nativo antes de navegar de novo. Uma tentativa anterior completou os asserts de negócio e falhou por um aviso DataTables; o corpo que produziu esse aviso não foi recuperado. A captura seguinte confirmou respostas200 descartadas por navegação prematura, sem novo aviso. A repetição final, com a espera e os mesmos asserts de erro, leu os três corpos JSON válidos. Não se filtrou o erro ou alterou o produto para aceitar uma resposta inválida.

As duas primeiras tentativas foram interrompidas ao entrar na principal pelo diretório vazio `install/` recriado pelo sincronizador do executor; já tinham criado A/B sintéticos próprios. O router do fixture foi ajustado para retirar exclusivamente esse diretório **vazio**, e somente quando há prova de instalação concluída. Qualquer arquivo de instalador continua acionando o guard original. Esse ajuste não integra o produto. Uma lista de rotas ou uma resposta HTTP bloqueada não serve de prova positiva de gestão.

### Reprodução do defeito e expectativas anteriores preservadas

O arquivo antigo `tests/issuers_staff_native.php` usava um double do modelo e esperava o aviso de resultado parcial quando a gravação de ACL falhava. A cópia exata foi conservada em `tests/companies/fixtures/staff_native_452.php.txt`, SHA-256 `e49db65241ddec83d102b5a11adfe1f64eab89e7bfc40c2df1892b9ef36948da`.

O modelo real da 452, SHA-256 `04fcf08bff4b5ff252b6690be3f21100c9a3239d121354046ed82092292b4153`, foi extraído com `git show` do HEAD acima e executado com falha de auditoria de ACL. A reprodução RED confirmou perfil persistido sem vínculo utilizável. O teste não chama essa situação de sucesso atômico. O novo harness executa o modelo e controlador reais e exige rollback integral. As negativas de CSRF, autoridade, padrão, edição pessoal, catálogo e proteção do master continuam sendo verificadas; não se introduziu comportamento alternativo no produto para atender o mock antigo.

O teste antigo de acesso foi preservado em `tests/companies/fixtures/staff_access_452.php.txt`, SHA-256 `b0053776639725aebc1a7c0780bd855d1998ab28f19ac1969eb6cbbccb03ceb3`. A expectativa que zerava `can_manage_integrations` em vínculo retido passou a exigir sua preservação, conforme o contrato de manter entregas e vínculos anteriores. As negativas reais que impedem usar essa flag genérica como autorização de recurso permanecem. Não houve promoção de autoridade.

Também foi conservado o log RED da reconstrução que revelou o decimal nativo: incluir `hourly_rate="0"` no formulário real fazia a comparação ingênua com `0.00` rejeitar o commit. A prova GREEN inclui zero, taxa com uma casa decimal e UPDATE ignorado; a correção não afrouxa o testemunho de integridade.

## Reprodução local

As suites SQL aceitam `ISSUERS_TEST_DSN`, `ISSUERS_TEST_USER` e `ISSUERS_TEST_PASSWORD`, exigem MySQL/MariaDB e criam prefixos aleatórios isolados. Não há substituição por SQLite. A limpeza remove somente as tabelas da fixture. A suite de concorrência usa filhos PHP com as mesmas extensões e precisa consultar os metadados de lock: `INFORMATION_SCHEMA.INNODB_LOCK_WAITS` no MariaDB e `performance_schema.data_lock_waits` no MySQL. Os usuários sintéticos usados tinham PROCESS e leitura de `performance_schema`, sem SUPER.

```sh
php tests/companies/staff_management.php
php tests/companies/staff_native_atomic.php
php tests/issuers_staff_native.php
php tests/companies/staff_concurrency.php
php tests/issuers_staff_access.php
php tests/issuers_staff_master.php
```

Para reproduzir a situação antiga, extraia o modelo do HEAD 452 para um arquivo de evidência e forneça seu caminho em `STAFF_REPRO452_MODEL` ao teste nativo. Não altere o arquivo corrente do produto para fazer essa reprodução.

O browser exige URL loopback, banco `argws_upgrade_ci`, container sintético validado, credenciais sintéticas por ambiente e diretório de evidências explícito. Reutiliza o master instalado e as empresas A/B criadas pelo wizard; não redefine a identidade nem altera opções financeiras. Cria somente identidades e vínculos sintéticos próprios de sua execução. Registra provas JSON e capturas de desktop/mobile.

## Dependências restantes deste recorte

Não há dependência de documentação bancária ou credenciais externas para criar, vincular, gerir permissões e revogar equipe localmente. Uma instalação que deseje enviar as notificações nativas opcionais precisa de seu transporte de e-mail configurado; falha desse transporte é um efeito posterior identificável, não sucesso parcial de perfil/ACL. Callbacks mutáveis adicionados por extensões externas exigem revisão específica antes de sua participação na composição.

Importação/exportação de RH, folha, contratos de fornecedor e outros escritores globais de módulos não fazem parte da conclusão de equipe desta página. Sua adaptação continua sendo trabalho interno de integração da matriz, não uma dependência de documentação bancária externa.
