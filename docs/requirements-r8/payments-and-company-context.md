# R06, R11 e preservação de R02

## Bases efetivamente usadas

- Referência funcional: `v4.0.2`, commit `aa58b832b3c5ed04a599caacba125b056c081ba7`.
- Destino: `main` 4.8.8, commit `3cd2ac577a969840c94b019f602cb69d710c0e78`.
- Versão desta composição: 4.8.9, migration nativa 489.

A autorização de implementação e abertura de PR para `main` acompanha a revisão
8 do registro de requisitos. A frente 4.0.3 não foi usada como base.

## Pagamentos: relato e reprodução são evidências diferentes

O relato original descreve travamento e desconfiguração ao clicar em
**Vendas → Pagamentos**. Não houve acesso à sessão ou ao banco da instalação
afetada. Não se atribui esse relato a Notas de crédito, Itens ou outras áreas.

Foi reproduzida uma falha concreta no mesmo caminho do código: o handler real
`NativeCompanyPaymentsHttp`, a view `admin/payments/manage`, seu `table_html` e
o helper real de DataTables foram executados com SQLite e dependências de I/O
do framework controladas. A view solicita a preferência `payments`, mas o
catálogo `NativeCompanyTableOrder::TABLES` não admitia esse identificador.
O resultado anterior foi HTTP **422** antes da conclusão do documento HTML:

```text
FAIL: Payments index renders the full original table and tail for company 1 (HTTP status 422)
```

Esse resultado demonstra a falha de renderização; não é uma gravação do
travamento de um navegador na instalação do usuário.

### Correções

1. `payments` foi incluído no catálogo da preferência com a permissão existente
   `payments/view`. A preferência continua vinculada à empresa e ao funcionário.
   O formato histórico da preferência da empresa principal é preservado.
2. A página e o detalhe agora são renderizados em buffer antes de publicar a
   resposta. Uma falha durante a montagem não deixa um cabeçalho parcial seguido
   de uma página de erro dentro da interface.
3. A tabela aceita o protocolo DataTables usado pela interface original,
   incluindo `length=-1` para **Todos/exportação**. Antes, esse valor era recusado.
   Busca, ordenação, paginação e os sete campos originais são preservados.
4. Consultas de Todas as linhas são percorridas em blocos de até 500 registros.
   Contagens divergentes, IDs repetidos entre páginas e páginas vazias incompatíveis
   com a contagem são recusados com conflito 409; a ordenação adiciona o ID como
   desempate. Não existe truncamento silencioso em 500 resultados.
5. Valores e datas usam os formatadores da aplicação; a linha mantém suas
   ações e vínculos. A ordem só é persistida quando a opção existente
   `save_last_order_for_tables` está habilitada.

A seleção do leitor de recibos históricos permanece em
`CompanyRequestScope::ownedPaymentsRoute`: na empresa principal, um pagamento
cuja fatura tem proprietário `NULL` usa o leitor histórico. Um pagamento de
fatura com empresa identificada usa o leitor com validação de proprietário.
Não se tenta adotar documentos antigos no livro financeiro recente.

### Resultados locais

PHP 8.3.6, SQLite e Node 24.22.1; registros são sintéticos.

| Comando | Evidência observada |
| --- | --- |
| `php tests/companies/native_payments_table_view.php` | 11 verificações adicionais aprovadas: documento completo na principal/A/B, sete colunas, preferência por empresa, funcionário sem permissão, coluna inválida e falha de armazenamento sem HTML parcial. |
| `php tests/companies/native_payments_table_http.php` | 16 verificações adicionais aprovadas: handler e SQL reais, Todas as linhas, busca, empresa B sem resultados de A, ACL, CSRF, verbo, regex/ordenação/identificador inválidos, integridade e corrida entre páginas. |
| `php tests/companies/native_payment_records.php` | 19 verificações adicionais aprovadas: leitura, nota auditável, idempotência, revisão obsoleta, negação entre empresas, PDF real e rollback. |

Os testes incluem fixtures que executam verificações anteriores; esses números
adicionais não devem ser somados indiscriminadamente aos totais cumulativos.
Não foi enviado aviso a cliente nem efetuada operação bancária externa.

A revisão também reproduziu uma corrida em Todos: uma reversão parcial muda a
ordenação por valor sem alterar as contagens. A regressão usa 501 resultados SQL
e o limite real de 500; um proxy de armazenamento apenas intercala a reversão
real do writer antes de `OFFSET 500`. O saldo final é confirmado pelo leitor.
Sem o guard, uma linha se repetia e outra era omitida; com ele a resposta é 409.
Outro caso simula no proxy uma página vazia incompatível. Isso comprova a recusa
dessas inconsistências, sem alegar snapshot único de toda exportação concorrente.

## Troca de empresa e documentos antigos no navegador

O contexto e a autorização continuam sendo resolvidos pelo servidor com os
serviços existentes. A alteração acrescenta uma geração de apresentação por
sessão; ela avança quando a seleção de empresa é confirmada. Seleções negadas
não a alteram.

O cabeçalho transmite ao navegador somente a geração e um canal opaco derivado
da sessão e do funcionário. O armazenamento compartilhado e `BroadcastChannel`
não recebem tokens de autorização, dados de clientes ou identificadores da
empresa. Uma geração maior invalida o documento antigo, oculta seu conteúdo e
abre o início administrativo para resolver novamente o contexto.

Um documento antigo que termina de carregar depois da troca não pode substituir
a geração nova. O retorno de uma aba ao primeiro plano também verifica eventos
perdidos. Uma página restaurada pelo histórico é revalidada no servidor; se não
houve troca observável, conserva o destino original da navegação. O guard não
é uma fonte de autorização: os tokens e as permissões do servidor continuam
sendo exigidos em cada requisição.

Formulários, `fetch`/XHR legados e o transporte nativo recusam submissões de um
documento já invalidado. A alteração não troca silenciosamente o token de um
formulário da empresa A pelo token da empresa B.

| Comando | Evidência observada |
| --- | --- |
| `php tests/native_context.php` | 156 verificações cumulativas aprovadas: contexto real, seleção autorizada/negada, troca, tokens, invalidação de caches e execução dos scripts reais no probe Node. |
| `node --test tests/native_company_context.test.cjs` | Seis testes aprovados: abas antigas, resposta atrasada, geração/canal independentes, restauração/visibilidade, indisponibilidade de armazenamento e rejeição de destino externo. |
| `php tests/native_r8_routes.php` | Prova adicional de admissão anterior à hidratação para rotas restauradas, principal/secundária, verbos/CSRF e proveniência de recibos. O total final é registrado no relatório de execução. |
| `node tests/native_company/context_tabs.browser.cjs` | Teste Chromium adicionado ao CI: cabeçalho PHP real e guard publicado, duas abas, resposta antiga, formulário e restauração; usa sessão sintética. Não executado localmente. |

## Marca e PWA

[baselines.json](baselines.json) registra os hashes SHA-256 de 60 arquivos de
identidade e PWA contra a base exata: configurações, helpers, identidade nativa,
include, endpoint, worker, script de instalação, ícones, logotipos e folhas de
estilo da marca. Os 60 arquivos estavam byte a byte iguais na comparação.

Também foram executados `php tests/brand_assets_smoke.php` (aprovado),
`php tests/crm_ui_branding.php` (69 verificações aprovadas) e
`node --test tests/native_pwa.test.cjs` (11 testes aprovados). Isso comprova os
contratos cobertos e a preservação dos arquivos; não substitui comparação visual
lado a lado nem instalação/uso do PWA em dispositivo real.

## Validações que ainda precisam de ambiente real

O executor local recusou a abertura de sockets pelo Chromium. A revisão
automática de permissões também rejeitou sua execução fora desse ambiente.
Não foram usados mecanismos para contornar esse bloqueio. Os testes de
navegador e MySQL ficam nos jobs existentes da PR; seus resultados devem ser
consultados para o commit final.

Para AC26–AC32 e AC57–AC64, homologar com duas empresas autorizadas, um perfil
com acesso restrito e documentos de ambas:

1. Abrir Pagamentos, pesquisar, ordenar, usar Todos/exportação, abrir recibo/PDF
   e exercer as ações permitidas. Confirmar a navegação subsequente em Vendas.
2. Manter um detalhe e um formulário abertos em A; selecionar B em outra aba.
   Confirmar a retirada dos dados antigos e a impossibilidade de gravar por A
   usando o contexto de B. Repetir pelo histórico e com resposta lenta.
3. Comparar principal, A e B, incluindo recibos históricos da principal, permissões
   de leitura/edição e tentativas por URL direta.
4. Comparar a mesma tela/dados/largura da 4.0.2 e do destino, preservando marca
   recente. Registrar capturas, cenário, perfil e diferenças concretas.
5. Validar apresentação/instalação/navegação do PWA existente no dispositivo e
   repetir login, logout e troca de empresa.

R11 integral também depende do inventário e das decisões de propriedade das
integrações em [integrations.md](integrations.md). A atualização das abas não
prova, por si só, isolamento de todos os relatórios, filas, módulos ou APIs.
