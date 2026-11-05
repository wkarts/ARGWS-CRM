# Leads — checkpoint de UI e extensão compatível de campos

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

| Campo/controle 4.0.2 | Situação no contrato próprio | Extensão compatível a implementar |
| --- | --- | --- |
| `name`, `company`, `email`, `phonenumber`, `description` | Já gravados por `CompanyLeads`; telefone mapeado para `phone`. | Manter o writer, validação e ordem funcional do formulário. |
| `status`, `source`, `assigned` | Já gravados como `stage_id`, `source_id`, `assignee_id`, com catálogos/autoridade por empresa. | Manter IDs próprios de catálogo e nomes autorizados. Não reusar filtros legados com IDs de outra origem. |
| `title`, `website` | Ausentes do pai próprio. | Persistência opcional de texto; exibição escapada e URL segura, sem executar conteúdo. |
| `address`, `city`, `state`, `country`, `zip` | Ausentes do pai próprio. | Endereço opcional e país validado na referência real de países. Preservar ausência, sem atribuir endereço histórico. |
| `default_language` | Ausente do pai próprio. | Valor opcional validado contra idiomas reais; vazio conserva a escolha padrão. |
| `lead_value` | Ausente; coluna correspondente está vazia. | Decimal exato opcional com moeda real da empresa. Nenhum zero inventado. Avaliar snapshot da moeda para não reinterpretar valor após mudança da moeda base. |
| `tags` | Infraestrutura `NativeCompanyWorkTags`/`NativeCompanyResourceTags` já resolve `company_lead`, mas o formulário não a escreve atomicamente. | Integrar labels/links próprios à mesma transação de criação/edição; origem e revisão corretas. |
| `lastcontact` | Ausente; coluna correspondente está vazia. | Data/hora opcional real, com conversão explícita do formato de entrada e preservação de vazio. |
| `contacted_today`, `custom_contact_date` | Controles de criação; modelo legado calcula `lastcontact` com a data atual ou entrada customizada. | Restaurar esses controles e calcular o valor uma vez no corpo normalizado, preservando replay idempotente. Não recalcular a data a cada replay. |
| `custom_fields[leads]` | Leitores/definições de `NativeCompanyCustomFields` já suportam `leads`; prova de criação própria ainda não está no mapping do writer. | Compor valores obrigatórios/opcionais na transação do pai e validar prova privada de criação, igual ao padrão de Despesas. |
| `is_public` | Ausente; no legado participa de `staff_can_access_lead`. | Não permitir que o checkbox amplie permissões da empresa ou exponha registros a não membros. Metadado e semântica efetiva precisam ficar distintos; resolver o conflito de ACL antes de alterar o acesso atual. |
| `dateassigned` | Campo derivado legado sem equivalente próprio. | Se acrescentado, derivar exclusivamente de atribuição real; não inferir histórico desconhecido. |
| `dateadded` | Já corresponde a `created_at`. | Manter a data real, sem recriar na extensão. |

Os campos recentes `vat`, `notify_assignee`, `reminder_local`, `reminder_timezone`,
arquivamento, revisão e conversão permanecem. Não removê-los para imitar a
referência. Responsável que perdeu acesso conserva seu ID sem divulgar nome
privado; a edição deve escolher um responsável atualmente autorizado ou nenhum,
em vez de apagar o vínculo implicitamente.

## Composição recomendada

Implementar os campos ausentes de forma aditiva e opcional. O conjunto exato deve
seguir os contratos reais já usados pelo projeto; uma tabela própria de metadados
com chave empresa/Lead é uma opção que não altera os recibos históricos do pai.
Se houver DDL, integrá-lo somente à migration 489 coordenada pela frente principal
e acrescentar o schema guard/manifesto correspondente. Não reescrever migrations
antigas nem a definição histórica de `CompanyLeadsSchema` para encobrir mudança.

`CompanyLeads::create/update/request/transaction` devem continuar sendo o único
caminho de gravação do Lead. A composição de metadados/tags/campos precisa ocorrer
na transação proprietária com locks, prova de criação quando aplicável, revisão,
validação de autoridade e rollback real. A ausência do novo conjunto deve manter
o hash/corpo/replay dos chamadores antigos (workspace, importação, API e
automação). Somente o envio explícito dos campos novos acrescenta conteúdo ao
recibo; não mudar todos os hashes por adicionar defaults a payloads antigos.

`CompanySalesNativeFields` fornece um exemplo recente de campos opcionais;
`NativeCompanyCustomFields::saveCreatedInOwnedTransaction` e os métodos
`create*TagsInOwnedTransaction` mostram a composição existente. Atualmente o
mapping de criação inclui Despesas/Propostas/Orçamentos, mas não Leads: a extensão
precisa validar o writer real e sua prova, nunca aceitar um objeto arbitrário.

Atualizar conjuntamente `NativeCompanyLeadInput`, o perfil do modal,
`CompanyLeadsDirectory`, a linha de tabela e os filtros admitidos quando houver
persistência real. Não preencher a view antes do writer. Datas/decimais devem
ter testes de serialização dos controles reais, não apenas chamadas de DTO.

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

## Testes e transferência de responsabilidade

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
