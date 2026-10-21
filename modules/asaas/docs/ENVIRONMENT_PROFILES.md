# Perfis de ambiente do gateway legado

## Contrato e alcance

O gateway da instalação primária/legada possui dois conjuntos independentes de 26
parâmetros. Os nomes são `paymentmethod_asaas_profile_production_<campo>` e
`paymentmethod_asaas_profile_sandbox_<campo>`. O seletor existente
`paymentmethod_asaas_sandbox` continua único: `0` produção, `1` homologação.
Alterná-lo não copia nem apaga os valores dos conjuntos.

As chaves continuam nos campos criptografados nativos `paymentmethod_asaas_api_key`
e `paymentmethod_asaas_api_key_sandbox`. Nenhuma chave, token ou senha é migrada
para os parâmetros operacionais. O gerenciamento de webhooks é separado.

O framework continua obtendo `active`, `label` e `default_selected` através de
`App_gateway`; o resolver do Asaas devolve os valores do ambiente escolhido.
Seleção inválida desabilita o modo no catálogo, permitindo corrigir a tela sem
escolher produção silenciosamente. Demais operações que exigem ambiente falham.

## Aplicabilidade dos parâmetros

| Grupo | Campos | Uso no gateway legado |
| --- | --- | --- |
| Gateway nativo | active, label, default_selected | Registro/disponibilidade no catálogo de pagamento |
| Diagnóstico | debug, file_log_enabled | Diagnósticos legados e log de requisições; requisições de cobrança usam a origem |
| Valores e emissão | currencies, description, interest_value, fine_value, discount_type, discount_value, discount_days, diasdevencimento | Fluxos de emissão/atualização existentes; novas cobranças só em BRL |
| Meios | billet_only, card_only, pix_only | Opções de checkout e seleção pelos consumidores legados existentes |
| Alterações remotas | delete_charge, update_charge | Política de remover/atualizar cobranças vinculadas, conforme ambiente da origem |
| Cliente | disable_charge_notification, send_customer_email, send_customer_mobile | Consumidores legados existentes de cadastro/notificação; o perfil não cria novos canais de envio |
| Preservados sem efeito | sync_enabled, sync_days, sync_limit | Sem consumidor que controle a rotina atual; campos desabilitados |
| Restrição atual | installmentCount | Preservado e desabilitado; cartão continua somente à vista |
| Informativo | ultima_atualizacao_servicos_municipais | Preservado e desabilitado; sem alteração manual |

A separação não amplia a capacidade dos fluxos existentes. Por exemplo, o checkout
hospedado de cartão utiliza seu contrato de valor exato e não passa a oferecer
parcelamento ou todas as regras de encargos dos fluxos legados. O fluxo legado
comum pode emitir `BOLETO` com Pix associado mesmo quando a opção de link de
boleto está oculta; o catálogo não altera esse contrato de emissão.

## Origem de uma cobrança

`getSettingForEnvironment(campo, ambiente)` faz leitura explícita de um perfil.
`getSettingForOrigin(campo, origem)` valida que a origem é Asaas/API e não pertence
a empresa nominal, depois usa seu ambiente imutável.

Os pontos de emissão legados executam em um contexto limitado à fatura, restaurado
em `finally`. Assim, quando já existe origem, preferências, credenciais e consultas
auxiliares não seguem o novo seletor global. Atualização, remoção, apresentação do
checkout e verificação de cartão também resolvem a origem antes de ler flags.
Novas emissões persistem o ambiente selecionado na origem antes de enviar o POST.

## Empresas nominais: fronteira explícita

`CompanyService` usa `CompanyClient` e contas próprias em
`asaas_company_accounts`, isoladas por `issuer_id + environment`. Não usa estes
perfis globais. As APIs legadas que recebem um `companyId` explícito não foram
removidas nem reinterpretadas por esta alteração.

Na implementação nominal atual existem configurações reais por conta para chave
criptografada, conta/ambiente, habilitação, webhook e ator da automação. A emissão
escolhe uma conta explicitamente, exige BRL e suporta os meios que seus métodos
específicos permitem; o cartão permanece à vista. A descrição é derivada do número
da fatura, juros/multa/desconto são zero e a prova canônica recusa encargos ou
descontos diferentes de zero.

Os demais 26 parâmetros deste catálogo não ganharam consumidores nominais nem uma
persistência nominal nesta alteração. Não apresentar estes perfis globais como
configuração pronta para empresas secundárias. Adicionar esse suporte exigiria
armazenamento próprio por empresa/ambiente, autorização da empresa ativa, seletor
por empresa e revisão dos contratos de emissão e conciliação. Configurações sem
consumidor devem continuar indisponíveis.

## Integração com a migration nativa nova

O integrador deve chamar `Asaas\EnvironmentSettingsSchema::ensure($CI->db,
db_prefix())` em `up()` da nova migration. Antes, carregar
`../libraries/Asaas/EnvironmentSettingsSchema.php` relativo à pasta de migrations.
O helper exige a tabela nativa `options` e métodos usuais do query builder
`table_exists`, `field_exists`, `get_where`, `insert` e `row_array` no resultado.

A evolução é somente aditiva: para cada campo e ambiente ainda ausente, copia a
linha legada se ela existe; senão usa o default do catálogo. `''` e `'0'` existentes
são preservados. Linhas de perfil já existentes nunca são sobrescritas. Erro de
insert/verificação aborta a execução; replay completa somente as ausentes. O
marcador `paymentmethod_asaas_profile_schema=1` só é escrito depois da verificação.
Enquanto o marcador está ausente ou o tracking nativo não confirma `installed_version
>= 1.5.4`, o gateway não se registra para novas operações
e não chama o `add_option` nativo, impedindo que defaults ocultem configurações
legadas. POST de configurações e operações dependentes dos perfis ficam bloqueados
até confirmar a migration nativa. Falha no write/readback da versão mantém
as capacidades novas fechadas mesmo quando a estrutura já está completa.
Requisições GET não semeiam dados.

Não há DDL, exclusão de opções antigas ou alteração de migrations históricas.
Não há alteração do marcador `installed_version` pelo helper: cabe ao executor
nativo da migration. O integrador controla header/versão/migration final.

## Rollback e validação

Rollback de código não deve remover os perfis nem os registros financeiros.
Versões antigas voltam a ler opções legadas preservadas, que não recebem as novas
edições feitas nos perfis. Portanto, revisar a configuração antes de reativar
código antigo; não copiar automaticamente um ambiente para o outro.

`tests/asaas_environment_profiles.php` cobre catálogo nativo, defaults,
alternância sem escrita, campos ausentes/vazios, allowlist e read-only, contexto da
origem, restauração após erro, nova emissão e restrições de ativação/BRL. Também executa
o bloqueio pré-migration e o `initMode` nativo pós-migration, além de persistência SQLite com falha parcial e replay idempotente.
Os testes usam chaves sintéticas e transporte simulado, sem chamadas Asaas.

As suítes existentes de origem, cartão hospedado, preflight/escopo de checkout,
webhooks, writer canônico e rotas foram executadas localmente. Testes que exigem
MySQL e homologação financeira/API real permanecem pendentes; SQLite e doubles
não provam o executor de produção nem a integração financeira real.

`tests/asaas_module_migration_154.php` executa o runner real e `CI_DB_result` real
em SQLite (ou MySQL CI explícito), cobrindo NULL de opção ausente, falha parcial
de DDL/seed, tracking, readback falso, retry/replay e preservação após rollback.
