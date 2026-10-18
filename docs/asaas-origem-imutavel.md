# Origem imutável e remoção segura de cobranças Asaas

## Entrega e limites

O módulo 1.4.6 conecta a origem persistida à emissão, consulta, atualização e remoção de cobranças. A identidade é conta (Wallet ID), ambiente, provedor, canal e emitente. `issuer_id = NULL` representa o escopo histórico da instalação; não é substituído por um cliente nem por uma empresa inferida. Emitentes positivos permanecem bloqueados até a integração multiemitente completa.

A implementação suporta uma origem/tentativa por fatura. Não implementa troca de banco, reemissão após remoção, múltiplas parcelas remotas na mesma fatura, CNAB, estorno, Pix Automático ou implantação multiemitente. O campo `batch_id` é reservado e permanece NULL. Essas operações exigem futura orquestração explícita de tentativas, aprovações e conciliação. Não trocar IDs por UPDATE nem apagar o ledger para permitir nova cobrança.

## Contratos e decisões

- `PaymentOriginStore::reserve()` insere antes de POST /payments. UNIQUE invoice_id impede nova emissão concorrente ou repetição após resultado incerto. A reserva não armazena credenciais nem payload de cartão.
- Wallet ID é lido por GET /wallets/ com o mesmo cliente que emitirá. GET /customers/{id} valida o candidato da tabela legada contra o CPF/CNPJ do cliente da fatura, na conta autenticada. Sem documento verificável não há emissão automática.
- `attachRemote()` permite somente NULL → ID remoto. Conta, ambiente, emitente, canal, provedor e referência não têm operação pública de alteração. Atualizações operacionais escrevem apenas estado e motivo; cada transição gera evento auditável.
- Chaves continuam na configuração existente, fora do ledger. Para histórico, `clientForOrigin()` usa o ambiente persistido e consulta a carteira novamente. Troca de chave para outra conta bloqueia a operação antes de consultar/remover a cobrança. Rotação na mesma conta é compatível.
- Configuração explícita `companyId` sem valores próprios não herda chave ou sandbox global. Issuer positivo tem namespace próprio `_issuer_<id>` e exige rollout operacional; a integração de cobrança permanece bloqueada nesta etapa.
- Falhas da API/transporte não viram listas vazias. Fluxos de checkout e atualização de fatura capturam falhas na fronteira de UI, mostram aviso e não continuam criando cobrança.

## Cancelamento e exclusão

Os filtros `before_invoice_cancel_allowed` e `before_invoice_delete_allowed` são executados no modelo real antes da mutação. Retorno diferente de true veta a alteração. O módulo usa o getter público do adaptador; registra o evento real `invoice_marked_as_cancelled`. Status 4 (vencida) nunca dispara remoção.

Somente origem conhecida e cobrança PENDING/OVERDUE sem recebimentos locais podem ser removidas. O serviço consulta GET /payments/{id} e chama DELETE /payments/{id}; não usa POST /cancel, refund ou desfazimento de recebimento. Sucesso exige resposta com ID correspondente e deleted=true. Empty/401/404/timeout não provam remoção. Uma nova tentativa começa por consulta; não existe repetição cega de DELETE.

Recebido, confirmado, parcial, estorno, chargeback, divergência e estado desconhecido exigem revisão. Falha mantém a fatura e o mapa. Se o provedor confirmou remoção e a gravação do mapa falhou, a próxima tentativa repara o mapa antes de autorizar a mutação local, sem novo DELETE.

`delete_charge=0` continua impedindo chamadas remotas. Para origem conhecida, a exclusão local fica bloqueada com aviso de revisão; a opção não é alterada por migration. Cancelamento usa o fluxo seguro independentemente desse toggle, como pretendido pelo hook anterior.

Legado sem origem não sofre veto local amplo nesta PR: nenhuma conta é adivinhada e nenhum DELETE é enviado. A política local anterior permanece, o mapa não é excluído e uma pendência é registrada. Logo faturas legadas ainda podem ser excluídas localmente deixando cobrança externa aberta; o aviso e a fila são deliberados, e devem ser revisados antes de usar exclusão em massa.

GET/DELETE não são uma transação distribuída. Um pagamento remoto pode acontecer entre as chamadas. O Asaas e uma base CRM ativa não foram homologados nesta entrega; não há promessa de proteção absoluta contra todas as corridas externas/lançamentos manuais concorrentes. Homologar essas condições antes de produção.

## Conciliação assistida

Acessar Vendas → Asaas: origem e pendências, somente administrador. A tela mostra até 250 registros recentes de cada grupo (origens e mapas sem origem), incluindo vínculos de faturas excluídas; não é uma fila paginada completa.

Informar ID da fatura, ID remoto, Wallet ID previamente conferido e ambiente. O POST exige token sincronizador de sessão próprio além da proteção global disponível. O servidor autentica a carteira e consulta a cobrança. ID, referência/hash, valor e vencimento devem conferir. A associação não é automática, não depende do banco selecionado na tela e não altera configurações. O ledger grava ator, instante e hash dos campos de evidência, sem dados pessoais completos.

A mesma operação recupera emissão incerta quando a cobrança existe no provedor. Se a fatura já foi excluída, há múltiplos candidatos, o valor diverge ou a chave antiga não está disponível, a pendência exige revisão operacional; não há reemissão automática nem associação aproximada.

Webhooks de recebimento para origens conhecidas verificam conta e cobrança com GET antes da baixa; conta indisponível/divergente vai a revisão. Fluxo de webhook para issuer positivo não é habilitado. O restante do processamento legado é preservado.

## Banco, implantação e rollback

Migration 146 cria `asaas_payment_origins` e `asaas_payment_origin_events`, InnoDB/utf8mb4, índices únicos de invoice e (provider, account_id, environment, remote_id). Nenhum backfill e nenhuma alteração destrutiva. `install.php` usa o mesmo instalador idempotente. Não existe FK com exclusão em cascata: o histórico precisa sobreviver à fatura.

Antes de deploy: backup do banco e arquivos; validar versão 1.4.6 em homologação, aplicar migration pelo mecanismo de módulos, conferir tabelas e direitos administrativos. A geração exige schema disponível. Verificar configuração delete_charge e comunicar a mudança para origens conhecidas. Conferir contas/ambientes com credenciais de homologação inseridas pelo responsável. Não executar testes contra produção.

Rollback: interromper novas emissões/cancelamentos, restaurar código anterior somente após revisar pendências e riscos do hook anterior. `down()` é propositalmente não destrutivo: mantém ledger e eventos. Nunca apagar tabelas de auditoria para reverter. Não houve deploy, alteração de credenciais, chamada real Asaas, envio de e-mail, merge ou release nesta tarefa.

## Verificação

- `php tests/asaas_invoice_payment_removal.php`: contrato puro de remoção, 59 asserções.
- `php tests/asaas_payment_origin_integration.php`: provider/gateway, callbacks registrados, métodos reais do modelo e controller; 102 asserções com SDK/DB simulados, zero rede.
- `php tests/asaas_payment_origin_schema.php`: exige banco MySQL descartável por `ASAAS_TEST_MYSQL_DSN`; cobre persistência, índices, emissão incerta, imutabilidade na API e auditoria. Executado na CI, não presumido aprovado localmente.
- Workflow `.github/workflows/asaas-payment-origin.yml`: contratos em PHP 8.1/8.5, MySQL 8.4 com PHP 8.3. Permissão contents:read, sem secrets de produção, sem publicação/deploy.
- Validação manual pendente: navegação/alerts de admin, token CSRF, migração na instalação CRM e conta sandbox autorizada; cancelamento concorrente com recebimento; comportamento de bancos/chaves históricos; checkout boleto/cartão/Pix com falhas de rede.

## Fontes primárias

- https://docs.asaas.com/reference/excluir-cobranca
- https://docs.asaas.com/reference/recuperar-walletid
- Modelo de faturas e gateway do main e09ada85e107724f48881bb47857b3c605e127be.
