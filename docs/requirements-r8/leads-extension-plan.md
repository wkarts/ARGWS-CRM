# Leads — UI original e implementação compatível de campos

## Bases e limite deste registro

Referência: `v4.0.2` (`aa58b832b3c5ed04a599caacba125b056c081ba7`).
Destino: MAIN 4.8.8 (`3cd2ac577a969840c94b019f602cb69d710c0e78`), com
atualização 4.8.9 nesta PR. Este documento descreve trabalho autorizado de R10/R11;
não cria requisito novo nem transforma ausência de implementação em decisão do
usuário.

## Superfícies já conectadas

- `leads/index`, `leads/table`: estrutura original, 13 colunas, resumos por status,
  pesquisa, paginação, ordenação e filtros salvos no namespace `company_leads`.
- `leads/lead`: modal de perfil, edição/criação, notas, anexos privados e atividade
  real; arquivamento/restauração e conversão preservam os escritores atuais.
- `leads/kanban` e `leads/update_lead_status`: colunas próprias, paginação de
  25 registros por coluna e mudança de status com revisão/idempotência.
- `leads?workspace=1`: recursos recentes de inbox, importação, configuração,
  automação, arquivos e exportação permanecem disponíveis. Queries anteriores
  continuam selecionando esse fluxo.

Os IDs canônicos de Lead pertencem ao namespace `company_lead`. IDs de status,
origem e responsável não pertencem a esse namespace. O código não promove um
ID global histórico para registro próprio por coincidência numérica.

## Campos da referência e armazenamento atual

Fontes primárias do inventário: `application/views/admin/leads/profile.php`,
`application/models/Leads_model.php`, `application/services/CompanyLeads.php`,
`CompanyLeadsSchema.php` e as views de tabela/Kanban da mesma tag. A tabela usa
os nomes reais do formulário. As views legadas foram conservadas no repositório.

| Campo/controle 4.0.2 | Situação na base antes desta extensão | Implementação nesta PR |
| --- | --- | --- |
| `name`, `company`, `email`, `phonenumber`, `description` | Já gravados por `CompanyLeads`; telefone mapeado para `phone`. | Manter o writer, validação e ordem funcional do formulário. |
| `status`, `source`, `assigned` | Já gravados como `stage_id`, `source_id`, `assignee_id`, com catálogos/autoridade por empresa. | Manter IDs próprios de catálogo e nomes autorizados. Não reusar filtros legados com IDs de outra origem. |
| `title`, `website` | Ausentes do pai próprio. | Persistência opcional com os comprimentos reais da tabela 4.0.2; perfil/formulário escapados e link HTTP(S) validado. |
| `address`, `city`, `state`, `country`, `zip` | Ausentes do pai próprio. | Persistidos em `company_lead_metadata`; país verificado no catálogo público real. Criação reutiliza `customer_default_country` da empresa via `CompanySettingsStorage`. Não há adoção de endereço histórico. |
| `default_language` | Ausente do pai próprio. | Validado contra `NativeAvailableLanguages::all()`; vazio mantém o padrão. O catálogo atual contém `portuguese_br`. |
| `lead_value` | Ausente; coluna correspondente vazia. | `DECIMAL(15,2)` opcional, assinado e calculado/formatado sem float. Captura a moeda base do catálogo público **já existente** em `NativeCompanyCurrencyReference`; não é uma nova configuração monetária por empresa. Valor salvo conserva a captura após troca da moeda base, sem conversão implícita. `NULL` e zero permanecem distintos. |
| `tags` | Infraestrutura já resolve `company_lead`; faltava composição pelo formulário. | Criação/edição usa `NativeCompanyResourceTags` na transação do Lead. O widget tag-it da referência transmite `tags[]`, preservando vírgulas e rótulos literais, sem sugestões globais. |
| `lastcontact` | Ausente; coluna correspondente vazia. | Persistência opcional e conversão explícita do formato capturado no formulário. Lista, perfil e filtro de data usam o valor real. |
| `contacted_today`, `custom_contact_date` | Controles originais de criação. | Checkbox e data alternativa restaurados. O recibo guarda a instrução estável; o relógio UTC do writer resolve o contato uma vez no primeiro trabalho confirmado. Reenvio não recalcula o horário. |
| `custom_fields[leads]` | Leitores/definições existentes; faltava prova de criação. | Helper original renderiza os campos; writer existente valida obrigatórios e tipos, com prova privada de `CompanyLeads` para criação. Valores, tags e metadados são confirmados novamente após o último recibo. Não entram no DTO genérico da API. |
| `is_public` | Ausente; no legado participa de `staff_can_access_lead`. | Não permitir que o checkbox amplie permissões da empresa ou exponha registros a não membros. Metadado e semântica efetiva precisam ficar distintos; resolver o conflito de ACL antes de alterar o acesso atual. |
| `dateassigned` | Campo derivado legado sem equivalente próprio. | Se acrescentado, derivar exclusivamente de atribuição real; não inferir histórico desconhecido. |
| `dateadded` | Já corresponde a `created_at`. | Manter a data real, sem recriar na extensão. |

Os campos recentes `vat`, `notify_assignee`, `reminder_local`, `reminder_timezone`,
arquivamento, revisão e conversão permanecem. Não removê-los para imitar a
referência. Responsável que perdeu acesso conserva seu ID sem divulgar nome
privado; a edição deve escolher um responsável atualmente autorizado ou nenhum,
em vez de apagar o vínculo implicitamente.

## Composição implementada

`CompanyLeadMetadataSchema` acrescenta uma única tabela com PK `lead_id`, FK
composta `(issuer_id, lead_id)` para o Lead próprio e índices por empresa e último
contato. `ensure($db, $prefix)` é idempotente e valida tipos, precisão, collation,
chaves e engine; a chamada pertence exclusivamente à nova migration 489. Não há
backfill nem alteração da definição histórica de `CompanyLeadsSchema`. Uma
instalação que já registra a migration 489 falha fechada se essa tabela faltar.

`CompanyLeads::create/update/request/transaction` continuam sendo o único caminho
de gravação. `CompanyLeadsNativeFields` compõe o grupo opcional `native_fields`
dentro dessa transação, com as permissões e revisão do pai. A normalização só
acrescenta esse grupo quando ele foi enviado: workspace, API, importação e
automação sem o grupo preservam a forma e ordem do corpo/hash históricos. Uma
edição antiga não apaga os metadados novos por omissão.

O compromisso `form_revision` inclui empresa, ator, revisão do Lead, metadados,
definições/valores autorizados, etiquetas, moeda e opções de formato. Uma mudança
concorrente de campo personalizado ou tag invalida o formulário mesmo sem mudar
a revisão do pai. Datas do formulário usam o formato capturado e assinado por esse
compromisso; por isso um POST já confirmado continua idempotente após mudança da
localização. A renderização em buffer repete a leitura antes de publicar o HTML.

`NativeCompanyCustomFields::saveCreatedInOwnedTransaction` admite apenas o writer
final `CompanyLeads` e a prova de criação que existe dentro do intervalo de gravação.
`NativeCompanyResourceTags::createLeadTagsInOwnedTransaction` usa a mesma prova.
Não há permissão de edição concedida como efeito de criação. O perfil mostra os
valores administrativos autorizados, enquanto `get()`, `page()` e API mantêm o
DTO anterior sem esses valores privados.

`CompanyLeadsDirectory` faz JOIN por empresa/Lead para os campos e busca literal
em etiquetas com `EXISTS`; hidrata as etiquetas em um lote limitado para a página,
sem uma leitura por linha. A página/contagens e esse lote são relidos antes da
conclusão. Exportação acima de 10.000 registros ou 20.000 etiquetas na página é
recusada, nunca truncada silenciosamente. Ordenação de etiquetas permanece
desabilitada: não há comparação integral equivalente ao `GROUP_CONCAT` legado.
Valor e último contato têm ordenação real. O builder expõe as 12 regras originais
com armazenamento próprio: nome, telefone, país, cidade, estado, CEP, último
contato, criação, valor, status, origem e responsável.

## Ações adicionais e conflitos reais

| Recurso | Trabalho compatível | Limite que não deve ser decidido implicitamente |
| --- | --- | --- |
| Ordem dentro da coluna (`leadorder`) | Persistência própria opcional e CAS para restaurar a ordem após recarga. O gesto hoje é cancelado, sem sucesso fictício. | Não gravar ranks no armazenamento global nem trocar status silenciosamente. |
| Cor/ordem de status | Metadados opcionais sobre status próprios; writer atual já possui posição. | Categoria `open/won/lost` atual tem regras e imutabilidade reais; cor não redefine categoria. |
| Marcar perdido/lixo | Inventariar flags e seus efeitos, acrescentando controles próprios quando compatíveis. | Não equiparar `junk`, categoria `lost` e arquivamento automaticamente. |
| Conversão | A UI atual usa o writer que cria cliente privado e contato inativo e preserva notas/arquivos. | Conversão antiga pode transferir tarefas/notas, vincular cliente existente, enviar e-mail ou alterar portal; não ativar esses efeitos sem o contrato correspondente. |
| Leads históricos | Preservar registros globais e seus caminhos permitidos existentes. | Propriedade/migração dos registros globais não é determinada pela empresa selecionada ou igualdade de ID. |
| Visibilidade pública | Manter as autorizações existentes. | `is_public` legado amplia acesso de staff; introduzi-lo como bypass de ACL viola R11. |
| Propostas, tarefas, lembretes, GDPR, e-mail e captação | Inspecionar os vínculos reais e adaptar somente os contratos próprios encontrados. | Public forms, configuração/filas globais e integrações externas precisam de propriedade/equivalência de efeitos antes de admissão em outra empresa. |

As colunas opcionais que `show_on_table` acrescenta à tabela legada e sua busca/
ordenação ainda não foram acopladas à grade própria de 13 colunas; os campos
personalizados desta rodada estão conectados ao perfil e ao formulário. Rank da
coluna, cor de status e `dateassigned` também permanecem trabalho compatível
pendente, não conflitos de autorização do usuário. O uso dos writers atuais não
comprova equivalência dessas superfícies adicionais.

## Testes e transferência de responsabilidade

Rodada de campos compatíveis executada localmente:

- `tests/native_lead_metadata_input.php`: **55 verificações** do código puro real,
  incluindo DECIMAL assinado, limites, datas, captura de moeda, leitura corrompida,
  formatos, opções literais e links inertes.
- `tests/native_lead_metadata_schema.php`: **11 verificações SQLite** de DDL
  aditivo, idempotência, índices e FK. Este teste não executa o writer de Leads em
  SQLite; a exigência de MySQL de `CompanyLeadsSchema` permanece intacta.
- `tests/native_lead_metadata_transport.php`: **27 verificações adicionais** às
  31 do transporte original, com views e helper de data reais e serialização DOM.
  O renderer de campos personalizados é explicitamente vazio nessa prova portátil;
  não é evidência SQL desses campos.
- `tests/native_company/lead_transport.test.cjs`: **6 testes** de requisições
  antigas/troca de empresa, concorrência e recibos do cliente.

Gate novo **MySQL**, preparado e sintaticamente verificado, sem execução local:
`tests/companies/native_lead_metadata.php`. Inclui os gates reais de diretório e
HTTP e acrescenta helpers reais/POST, compatibilidade de hash, precisão, A/B/
principal, criação sem permissão de edição, campo/tag concorrente, replay após
mudança de formato/moeda, rollback por falhas/triggers tardios e privacidade do
DTO da API. O fixture `lead_metadata_setup.php` usa DDL original para dependências
e o schema novo. O resultado desse gate pertence ao próximo CI; ainda não é PASS.

O browser `tests/companies/leads_browser.cjs` foi ampliado para os campos,
etiquetas com vírgula, checkbox/multiselect, hyperlink e reenvio exato, com leitura
do resultado real no fixture dedicado. Apenas a sintaxe foi validada localmente.
Chromium local continua indisponível por restrição de sockets do ambiente. Não
há declaração de equivalência visual lado a lado nem de funcionamento integral
do browser/PWA com base nessas provas.

Checkpoint local: `tests/native_lead_original_transport.php` (31 checks) e
`tests/native_company/lead_transport.test.cjs` (6 testes) passaram. Navegação
(12), bootstrap (195 cumulativos), verbos (33) e gate real de rotas foram
retestados. Essas provas não usam MySQL/Chromium reais.

`tests/companies/native_lead_original_http.php` é o gate MySQL, incluindo a suite
`native_lead_directory.php`. `tests/companies/leads_browser.cjs` exercita lista,
modal, anexos e arraste real em A/B/principal, além dos fluxos atuais. Ambos
aguardam resultado do CI deste delta. Não duplicar o mesmo comando nas fases
SQLite e MySQL do manifesto.

A frente de Leads pode estender seus arquivos após este checkpoint; a frente
de áreas nativas passa a trabalhar na lista/preview/resumos de Despesas.
Compartilhados de rotas, assets, filtros e migration continuam coordenados pela
frente principal. Os testes devem preservar os recibos antigos, provar rollback
tardio e revogação, negar IDs de outra empresa e verificar que não houve perda de
precisão ou aceitação silenciosa de campos não reconhecidos.
