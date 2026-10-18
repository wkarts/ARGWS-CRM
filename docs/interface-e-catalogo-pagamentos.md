# Interface do CRM e catálogo de pagamentos

## Identidade e idioma

O produto aparece como **CRM**. **ARGWS** identifica o grupo fornecedor e seu canal de distribuição. Logotipos enviados pela instalação, nomes de empresas/clientes, dados legais, caminhos de arquivos, variáveis de ambiente e identificadores das integrações não são renomeados.

Português do Brasil é o único idioma selecionável. O runtime normaliza configurações legadas, os carregadores administrativo/cliente/PDF/formulário e a rota/cookie de idioma. A normalização em leitura não reescreve dados legados. Inglês continua no repositório e é carregado primeiro como fallback para chaves ainda sem tradução; PT-BR e customizações locais têm precedência. Essa política não traduz textos escritos por usuários ou modelos personalizados.

## Gateways nativos ocultos

O catálogo administrativo e novas seleções ocultam estes IDs exatos:

| Integração | ID existente |
| --- | --- |
| Authorize.net Accept.js | `authorize_acceptjs` |
| Instamojo | `instamojo` |
| Mollie | `mollie` |
| Braintree | `paypal_braintree` |
| PayU Money | `payu_money` |
| Stripe Checkout | `stripe` |
| Stripe iDEAL nativo | `stripe_ideal` |
| 2Checkout | `two_checkout` |

A implementação Stripe Checkout do core usa o ID `stripe`. Isso não remove `Stripe_core`, SDK, assinaturas, cartões existentes ou integrações de módulo com outros IDs. Asaas, Efí, PayPal e os demais provedores ficam fora desse filtro.

A política é de catálogo. Os gateways continuam registrados, com seus códigos, configurações, credenciais já salvas e históricos. Relatórios, recibos, lookup por ID, callbacks e conciliação continuam acessíveis como antes.

### Faturas e links existentes

- Novas faturas, cópias, conversões e novas ocorrências não recebem os IDs ocultos, mesmo se forem enviados por POST/API.
- A edição conserva IDs ocultos que já estavam em `allowed_payment_modes` da própria fatura. Ocultá-los da seleção não apaga a autorização já emitida. Mesmo uma seleção vazia conserva os IDs ocultos anteriores; para retirá-los de uma fatura, restaure temporariamente o gateway no catálogo e então faça uma edição explícita.
- Links de faturas existentes que já continham um desses IDs podem continuar iniciando o pagamento. Sessões de checkout já abertas no provedor e callbacks não são cancelados.
- Uma tentativa de iniciar um gateway oculto em fatura que não o possuía é recusada antes de criar a tentativa de pagamento.
- Os modos já usados em lançamentos/recibos permanecem disponíveis na edição correspondente e nos filtros de relatório.

Esta compatibilidade se baseia nos IDs salvos da fatura, sem migration/data de corte e sem chamar o provedor. Não implica que um provedor ainda ofereça o serviço: disponibilidade e credenciais continuam sendo responsabilidade da instalação.

### Reversão do catálogo nativo

Para restaurar opções nativas sem alterar dados, um administrador técnico pode definir `CRM_HIDDEN_NATIVE_PAYMENT_GATEWAYS` como um array em `application/config/app-config.php`. O padrão corresponde aos oito IDs acima. Um array vazio restaura todas essas opções; um subconjunto mantém ocultos apenas os IDs listados. Valores fora desses oito IDs são ignorados. Esta configuração não altera a flag do módulo iDEAL.

```php
define('CRM_HIDDEN_NATIVE_PAYMENT_GATEWAYS', []);
```

Não edite configuração real durante revisão de código. Faça backup e teste a alteração em homologação antes de disponibilizar um provedor.

## Módulo iDEAL separado

O módulo `modules/ideal`, com gateway `Ideal_gateway`, é separado do gateway nativo `stripe_ideal`. Sua implementação também usa Stripe internamente, mas tem catálogo e identificadores próprios.

`CRM_ENABLE_IDEAL=false` é o padrão. Nesse estado, o recurso e sua configuração ficam ocultos, sua ativação e gestão de pagamentos/webhooks são bloqueadas e novas faturas não o selecionam. Módulos anteriormente ativos continuam carregados para atender pagamentos legados, sem alteração de estado no banco ou exclusão de webhooks.

Para disponibilizá-lo, configure `CRM_ENABLE_IDEAL=true` no ambiente do processo PHP. Os exemplos de Compose de raiz, desenvolvimento e produção já repassam a variável. Recrie o serviço web após alterar o `.env` do Compose. Em PHP-FPM/hospedagem tradicional, exponha a variável ao processo PHP conforme o mecanismo do provedor.

A flag apenas disponibiliza o módulo: não ativa o registro, não configura chaves e não concede permissões. A ativação continua restrita a administradores em Recursos. A gestão de webhooks exige sessão de funcionário, permissão de edição de Configurações, POST e token CSRF próprio vinculado à sessão (inclusive se o CSRF global estiver desligado). Os botões usam formulários externos ao formulário de Configurações, evitando formulários aninhados. URLs GET antigas de criar/habilitar webhook deixam de aceitar alterações; o endpoint receptor de webhook e os retornos de pagamento mantêm seus contratos. O controle opcional `CRM_HIDDEN_RESOURCES` continua prevalecendo para ocultação adicional da listagem.

## Validação

- `php tests/crm_ui_language.php`: carregador real de idiomas CodeIgniter, fallback EN, PT-BR, customização, cookies e leitura de opções legadas.
- `php tests/payment_gateway_catalog.php`: catálogo, sanitização dos hooks, checkout bloqueado para fatura nova, preservação de faturas existentes, flag e RBAC. Colaboradores de banco/integração são simulados em memória; nenhum pagamento é executado.
- `php tests/ideal_management_forms.php`: helper de formulários CodeIgniter real, POST, tokens CSRF global/próprio e associação dos botões sem formulários aninhados.
- `php tests/brand_assets_smoke.php`: identidade de fallback e precedência de logos personalizados.
- `node --test tests/crm_ui_policy.test.cjs`: pontos de integração e propagação do ambiente.

Em homologação, conferir login/painel/cliente/formulários com preferências antigas; nova fatura e edição de uma legada; recibos/relatórios; módulo iDEAL com flag ausente/false/true e funcionário sem permissão. Um teste real de checkout deve usar o ambiente de testes do provedor, nunca disparar cobrança real como validação automática.

## Publicação e rollback

Há uma migration NOOP de versão do módulo iDEAL, sem alteração de schema ou nova dependência. Publique os arquivos revisados e os templates de Compose, preservando configurações/volumes locais, e execute a manutenção nativa do módulo quando indicada. A flag é desabilitada por padrão. Para rollback, reverta o commit e recrie o serviço com a imagem/código anterior. As opções, históricos e IDs legados foram preservados; não há schema a desfazer. O marcador de versão do módulo deve ser revisado pelo fluxo nativo antes de qualquer rollback; esta manutenção não executa downgrade automático.

## Destino do menu Suporte

Configurações > Suporte reutiliza o mesmo destino já configurável do atalho de suporte: `support_contact_channel` e campos locais WhatsApp/e-mail/site, com fallback nas variáveis `CRM_SUPPORT_WHATSAPP`, `CRM_SUPPORT_EMAIL` e `CRM_SUPPORT_SITE_URL`. Sem destino válido, o link não é exibido. Não há fallback para o GitHub pessoal/repositório. O botão de versões do painel e o link de distribuição na conclusão do instalador também não são exibidos. URLs internas de versões/atualização, metadados técnicos de SDK e licenças permanecem inalterados. Nenhum endereço real foi configurado ou contato enviado.

## Versão do módulo iDEAL e manutenção local

A correção do módulo `ideal` sobe sua versão de **1.0.0 para 1.0.1**, com `modules/ideal/migrations/101_version_101.php`, classe `Migration_Version_101 extends App_module_migration` e `up()` explicitamente NOOP. Não há migration 100 artificial nem mudança de schema, cobrança, segredo ou cadastro remoto. Os gateways nativos filtrados são core; `modules/api` e outros módulos não foram alterados nesta PR.

A ativação chama `install.php`, que executa o migrador nativo e confirma `tblmodules.installed_version`. Como o core registra o header antes do hook, a ativação reaplica a etapa aditiva a partir da versão histórica 1.0.0 somente quando o marcador já coincide com o header. Uma versão anterior conserva seu caminho nativo de upgrade; versões incompatíveis ou superiores são recusadas. Falha do marcador interrompe a ativação. Atualizações existentes usam `App_module_migration::to_latest()` e verificam o marcador persistido antes de informar sucesso.

Com `CRM_ENABLE_IDEAL=false`, a configuração, ativação e novas seleções continuam ocultas. Um administrador pode fazer somente a manutenção versionada de um iDEAL já instalado, inclusive desativado, sem habilitar o recurso: Recursos mostra uma ação de manutenção enquanto houver versão anterior. Ela exige POST e token CSRF próprio, também quando o CSRF global estiver desligado. Não permite downgrade; não altera o estado ativo nem a variável de ambiente. Os callbacks e registros legados continuam preservados.

Publicar os arquivos não executa a migration: a atualização local precisa ser solicitada pelo administrador. `tests/ideal_module_migrations.php` executa o migrador nativo real com armazenamento determinístico para instalação, upgrade, replay, erro de tracking, manutenção oculta, POST/CSRF e negação a não administradores.
