# Pull Request

## Branch
`fix/asaas-immutable-payment-origin`

## Título
`fix(asaas): persiste origem e protege cancelamento de cobranças`

## Descrição
Conecta a identidade imutável da cobrança à emissão e às operações posteriores, corrige cancelamento com o endpoint DELETE documentado e impede falso sucesso ou perda de vínculo em falhas. Publicação como **draft**, sem merge ou deploy.

## Contexto
Os hooks acessavam adaptador privado, não escutavam o cancelamento real, tratavam status 4 como cancelado e usavam POST /payments/{id}/cancel. Erros retornavam [] e podiam ser interpretados como sucesso. O mapa não preservava conta/ambiente de emissão.

## Objetivo
Operar cobranças pela origem verificada, preservar recebimentos e mapas, impedir duplicação após emissão incerta e oferecer conciliação administrativa do legado sem adivinhar origem.

## Escopo incluído e fora do escopo
Incluído: ledger, migration/instalação, emissão com reserva, validação de carteira/cliente, filtros core antes de cancelar/excluir, remoção segura, fila administrativa, CSRF, logs de auditoria, testes e CI.
Fora: merge/deploy, cobrança real, envio de mensagens/e-mails, credenciais, reembolso, CNAB, troca de banco/reemissão, múltiplas tentativas/parcelas por fatura, isolamento multiemitente completo. Issuer positivo continua bloqueado.

## Decisões técnicas e arquitetura
Uma origem por fatura, UNIQUE antes do POST e identidade sem setters. Conta verificada por Wallet ID e ambiente persistido, sem escolher configuração global corrente para histórico. API key não entra no ledger. Detalhes, limitações e contrato de rollout em `docs/asaas-origem-imutavel.md`.

## Alterações realizadas
- Getter público e evento correto; status 4 preservado como vencido.
- GET seguido de DELETE; validação explícita de ID/deleted e estados protegidos.
- Guardas retornáveis no modelo real impedem mutação de origens conhecidas se houver recebimento/falha.
- Legado sem origem conserva política local anterior, com pendência e nenhuma remoção remota automática.
- Reserva durável antes de emissão, sem repetição cega após timeout.
- Recuperação assistida, proteção contra reatribuição de origem e reparo de mapa após remoção remota confirmada.
- Erros deixam de virar lista vazia; UI recebe aviso e não prossegue com criação.

## Arquivos adicionados, modificados e removidos
Adicionados: PaymentOriginStore.php, PaymentOriginSchema.php, InvoicePaymentRemoval.php, migration 146, controller/view Payment_origins, três testes Asaas, workflow de CI, documentação operacional e esta descrição.
Modificados: modules/asaas/asaas.php, install.php, AsaasAdapter.php, AsaasSdkProvider.php, Asaas_gateway.php e somente dois guardas de application/models/Invoices_model.php.
Removidos: nenhum.

## Banco de dados
Duas tabelas novas InnoDB; índices únicos de fatura e identidade remota. Sem backfill, sem FK cascade e sem alteração destrutiva. Migração idempotente. Rollback preserva dados. O snapshot de invoice referencia ID/hash/valor/vencimento, sem credencial ou payload de cartão.

## APIs, contratos e integrações
GET /wallets/, GET /customers/{id}, POST /payments, GET e DELETE /payments/{id}. Preserva formas públicas de checkout com erro explícito no limite da UI. companyId explícito sem configuração própria falha, sem herança global. Novos filtros core retornam true/false. Conciliação usa GET externo e POST local administrativo autenticado, com CSRF próprio.

## Dependências, configurações e variáveis de ambiente
Nenhuma dependência runtime adicionada. Usa SDK vendorizado existente e PHP >=8.1. Não lê/altera credenciais reais. CI usa MySQL/DSN descartáveis, sem secrets. delete_charge permanece configurado pelo administrador.

## Segurança e privacidade
Admin obrigatório, POST e token de sessão verificado em tempo constante. Identidade de conta conferida no provedor; cliente remoto comparado por documento antes de emitir. Erros exibidos sem mensagens/payload sensíveis. Auditoria registra ator/hash de evidência. Nenhum código de estorno ou envio automático adicionado.

## Desempenho e observabilidade
Nova emissão acrescenta GET carteira e GET cliente; operação histórica verifica carteira. Erros/falhas ficam em ledger e eventos; tela mostra 250 registros de cada grupo. Índices suportam consulta por fatura/ID/estado. Latência e corrida GET/DELETE exigem homologação; nenhuma garantia de transação distribuída.

## Testes implementados
59 asserções puras; 102 integradas com SDK/DB simulados; contrato MySQL com schema, unicidade, imutabilidade e auditoria. Teste integrado usa provider/gateway/hooks/controller e guardas reais do modelo, incluindo CSRF, conta/ambiente trocados, recibos, timeout, retry e mapa falho.

## Testes executados
PHP WASM local: remoção 59 asserções e integração 102 asserções aprovadas. Lint dos arquivos alterados aprovado. git diff --check aprovado. MySQL local não executado por ausência de servidor. Resultados da CI devem ser verificados para o SHA remoto exato antes da conclusão.

## Testes recomendados e validação manual
Homologar migration/UI completa, fluxo boleto/cartão/Pix, credenciais sandbox, conta histórica, recebimento concorrente e atualização das configurações; usar banco/conta descartáveis autorizados. Validar nenhum efeito externo em erro/ambiguidade e nenhuma exclusão de recibos em origem conhecida.

## Impactos, compatibilidade, riscos, mitigações e breaking changes
Mudança deliberada: origem conhecida impede exclusão local quando delete_charge está desabilitado, há recibo ou remoto não confirmou. Legado sem origem não é bloqueado amplamente; pode permanecer cobrança externa aberta após exclusão local, com aviso/revisão. Exige CPF/CNPJ verificável para nova emissão. Uma origem por fatura bloqueia reemissão/troca de banco, que não estão implementadas. SDK/CRM completo e API real não foram homologados. GET/DELETE e lançamentos concorrentes não são uma transação distribuída; não alegar proteção absoluta de corrida.

## Procedimento de deploy
Não executado. Fazer backup, revisar PR draft e CI, validar em homologação, atualizar módulo para 1.4.6 e executar migration 146 pelo mecanismo existente. Conferir pendências/configuração antes de autorizar operação. Não alterar opções existentes automaticamente.

## Procedimento de rollback
Suspender emissão/removal, revisar pendências, restaurar código anterior somente com avaliação dos defeitos anteriores. Manter tabelas e auditoria; down não apaga dados nem faz novas cobranças.

## Build, release e distribuição
Não se aplica a esta alteração além de testes/CI e versão interna do módulo 1.4.6. Nenhuma imagem, tag, release ou deploy publicado.

## Documentação
`docs/asaas-origem-imutavel.md` contém política de legado, configuração, rollout, limites e roteiro de verificação. Fontes primárias Asaas de DELETE e Wallet ID estão vinculadas.

## Checklist
- [x] Escopo/diff isolado, guardas core coordenados com recorrências.
- [x] Nenhuma credencial, cobrança real, e-mail, merge ou deploy.
- [x] Testes locais e lint observados.
- [x] Legado sem backfill e sem veto amplo não revisado.
- [x] Contrato multiemitente permanece fail-closed.
- [ ] CI do SHA final verificada.
- [ ] Homologação CRM/MySQL/Asaas sandbox e UI real.

## Commit sugerido
`fix(asaas): persiste origem e protege cancelamento de cobrancas`

## Merge sugerido
Squash após revisão, homologação e autorização explícita. Não executado.

## Versão sugerida
Patch do módulo: 1.4.6. Não pressupõe uma nova versão global do CRM.
