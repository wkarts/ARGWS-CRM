# Confirmação transacional de configurações — 453

## Comportamento

As gravações de opções privadas, lotes de preferências e identidade cadastral
confirmam o estado final depois da auditoria e antes do commit. O proprietário da
transação continua sendo `IssuerTransaction`; a composição do cadastro e do
wizard usa o mesmo writer, sem iniciar ou confirmar uma transação aninhada.

`CompanySettingsCommitGuard` captura a linha da empresa e a identidade da
instalação, além de todas as opções da principal quando ela é a origem explícita.
O writer declara somente as alterações permitidas. O guard verifica o valor,
`autoload` solicitado e as colunas não alteradas, revalida a autoridade e confere
novamente as linhas depois do último evento de auditoria. O evento criado também
é conferido, inclusive ator, empresa, assunto, nome, data e ausência de conteúdo
privado no campo de metadados.

Uma atualização SQL aceita, porém ignorada, ou uma alteração divergente feita por
trigger não pode produzir sucesso. Toda a unidade volta atrás. O retry usa o
mesmo caminho de validação e não confirma a alteração anterior malsucedida.
Valores de configuração, nomes de opções e segredos não entram no evento de
auditoria. O snapshot usado internamente pelo guard não é um DTO público.

Empresas secundárias continuam usando o documento privado da própria empresa.
Não existe fallback de integração, segredo ou configuração operacional para a
principal. As quatro opções explicitamente globais de infraestrutura permanecem
listadas em `CompanySettingsStorage::INFRASTRUCTURE_OPTIONS`, com escrita
administrativa apenas no contexto da principal.

## Regressão causal

`tests/settings/settings_commit_fencing.php` mantém os 78 checks da suíte
original de configurações e acrescenta 40 checks próprios. As provas executam
triggers reais depois da auditoria: alteram opções privadas, opções da principal,
lotes, identidade, concessões, vínculo ou metadados não solicitados. Cada cenário
confirma que a trigger foi atingida, que todas as linhas participantes foram
restauradas e que um retry limpo funciona. Há ainda a prova de atualização de
`autoload` ignorada quando o valor da opção permanece igual.

`tests/settings/settings_audit_integrity.php` mantém os mesmos 78 checks originais
e acrescenta seis próprios. Uma trigger altera o evento de auditoria nas
gravações de configuração e identidade. A evidência exige a execução da trigger,
o rollback integral e o retry confirmado.

Os modos observacionais `SETTINGS_FENCE_OBSERVE_BASELINE=1`, executados sobre o
código publicado `543ff96fe2a49ceef48d8595a58e8400befc6266`, demonstram o defeito
anterior: a trigger é atingida e o writer retorna sucesso apesar da divergência.
Esse modo termina depois do primeiro cenário e seu código de saída zero significa
que a observação foi registrada, **não** que a implementação antiga passou.

## Comandos e preservação

Com `ISSUERS_TEST_DSN`, `ISSUERS_TEST_USER` e `ISSUERS_TEST_PASSWORD` apontando para
um banco descartável de teste:

```sh
php tests/settings/settings_commit_fencing.php
php tests/settings/settings_audit_integrity.php
php tests/settings/company_identity_transaction.php
php tests/settings/settings_model_controller.php
php tests/settings/primary_document_pdf.php
php tests/issuers_onboarding.php
php tests/settings/company_registry_identity.php
php tests/settings/company_options_helpers.php
php tests/settings/company_presentation_delivery.php
php tests/settings/company_mail_queue.php
php tests/settings/document_pdf_concurrency.php
php tests/settings/primary_sales_preferences_concurrency.php
```

As provas de concorrência originais continuam exigindo waiters observados no
banco, coerência do snapshot emitido, uma única emissão por pedido e revogação
durante a espera. Nenhuma asserção ou prazo dessas provas foi reduzido. O usuário
sintético precisa de acesso ao inventário de locks do respectivo banco; isso não
é um requisito de permissão administrativa do usuário da aplicação.

Esta correção confirma os writers existentes. Ela não transforma um inventário
lexical em prova de todos os consumidores, não amplia o catálogo de configurações
por curinga e não declara encerrada a paridade de todos os módulos.
