# PR61: paridade parcial do menu Recursos

Patch recebido pela coordenação: `pr61-resource-menu.patch`, base `1d8dad50`,
SHA-256 `570c68d2d0b3dd12788b50b84573bf5a973e4f2d56e77e0ead519fd99c3b7b83`.
Conteúdo e hash conferidos; `git apply --check` aprovado sobre `c7a54d9d`.

## Causa reproduzida e correção

O helper legado registrava Recursos (`admin/modules`, posição 35) diretamente,
mas o catálogo usado pela apresentação nativa por empresa não continha esse
item. Assim, trocar do caminho legado para uma página com apresentação nativa
removia Recursos da sidebar. O problema não depende exclusivamente do BB.

O item agora usa o mesmo descritor nos dois caminhos. Na apresentação nativa,
`installation_only` exige empresa principal e administrador; não anuncia a
gestão global de módulos nas secundárias nem para não administradores. No
legado, mantém-se o bloco `is_admin()` e o badge de atualizações. Nenhum handler,
permissão de escrita, hook de módulos ou guard de contexto foi alterado.

O teste PHP adicionado foi executado antes da alteração dos fontes e falhou
com `resource management retains native label, destination and order after
banco_brasil`. Depois da alteração passou, percorrendo BB, dashboard, settings
e BB novamente, além dos casos principal não administradora e secundária.

## Mapeamento transversal e limites

`AdminController` usa `NativeCompanyAdminPresentation::prepare` quando a
requisição selada é nativa; as páginas legadas seguem o helper e hooks originais.
Os formulários da principal em `NativeSettingsCompatibility::PRIMARY_GROUPS`
incluem email e invoices e continuam usando seus escritores originais. Portanto
a navegação pode alternar entre dois produtores de menus dentro da mesma empresa.

Outra diferença está comprovada estaticamente: o catálogo nativo contém
Integrações com destinos `settings/asaas`, `settings/efi`,
`settings/google_mail_oauth` e `settings/microsoft_mail_oauth`; o helper legado
não consome esses descritores extras. Isso explica uma fonte de variação entre
os produtores, mas não identifica sozinho as telas relatadas como "fonte CRM"
e "PIG". Esses nomes não foram encontrados nos arquivos de navegação examinados.
A rota canônica dessas telas e seus hooks ainda precisam ser associados ao relato.

Esta correção é parcial: não declara resolvido o desaparecimento de todos os
menus. Não move PIG, não remove Integrações nem substitui os hooks por uma lista
global. A reorganização solicitada depende do mapeamento dos destinos e referências
e da especificação coordenada separadamente. Não houve navegação ou mutação em
produção para validar este incremento.

## Validação local

- `php tests/native_company/presentation.php`: aprovado, incluindo regressão anterior.
- `php tests/native_navigation_parity.php`: aprovado.
- `php tests/native_context.php`: aprovado.
- `node --test tests/native_company/presentation.test.cjs tests/native_shell_controls.test.cjs tests/native_navigation_tabs.test.cjs`: 18 aprovados.
- Lint PHP dos três fontes e do teste de apresentação; `git diff --check`.
- Inventários regenerados pelos scripts oficiais; fronteiras permanecem
  `unreviewed`, sem declaração de prontidão operacional.

Versão preservada em 4.8.9/489. Não houve migration, merge, deploy ou chamada a provedor.
Os testes são locais e não substituem homologação integrada das telas ainda não identificadas.
