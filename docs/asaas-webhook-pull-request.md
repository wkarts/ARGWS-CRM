# Pull Request

## Branch
`fix/asaas-webhook-scope-auth`

Base proposta: `fix/asaas-callback-hmvc-route` (PR42, ancestral real da PR40). Se ambas já estiverem no main, revalidar e usar o main atual antes de publicar.

## Título
`fix(asaas): isola webhooks e migra cartão para checkout hospedado`

## Descrição
Corrige a entrada Asaas e sua gestão administrativa, exige conciliação canônica com origem imutável antes de registrar recebimento e retira coleta/armazenamento de cartão do CRM. PR em rascunho, dependente da fundação de origem da PR40 e compatível com a correção isolada de rota da PR42. Sem merge ou implantação.

## Decisão pendente antes de merge/ativação
A política de baixa local ainda não foi aprovada. Neste rascunho, CONFIRMED é observado sem baixa e somente RECEIVED canônico pode registrar o recebimento integral. Isso pode manter fatura aberta após o cliente pagar; a régua de lembretes precisa ser alinhada à decisão. Não anunciar baixa automática pronta nem ativar esta versão enquanto essa pendência e a homologação permanecerem abertas. A PR curta de rota/resposta é independente dessa mudança de política.

## Contexto
A rota de módulo repetia o prefixo Asaas e podia resolver o controller errado. A autenticação existente já rejeitava segredo ausente/incorreto, mas era global. A conciliação legada aceitava dados do payload e tratava CONFIRMED e RECEIVED como equivalentes. O método genérico anterior não compartilhava a transação/trava do reconciliador e podia executar notificações/hooks dentro de uma transação externa não confirmada. O checkout anterior coletava PAN/CVV e tinha escrita em campo do cliente; esta PR remove esse caminho sem ler ou apagar dados de produção.

## Objetivo
Receber eventos por integração de conta/ambiente, persistir antes do ACK, validar pagamento/cliente no provedor, preservar o histórico e apresentar revisão e automações pendentes de modo explícito. Usar pagamento por cartão hospedado no Asaas sem trafegar dados de cartão pelo formulário do CRM.

## Escopo incluído e fora do escopo
Incluído: rotas, controller público, autenticação por header, tokens gerados pelo servidor, RBAC/CSRF, gestão administrativa, ledger de eventos, dedup por escopo, retry/backoff, verificação canônica, gravador local silencioso/outbox, escrita concorrente coordenada de pagamentos e créditos do core, proteção de recibos canônicos, revisão assistida, cartão hosted integral, abas do painel, ambiente mutuamente exclusivo, testes/CI/docs.

Fora: deploy, merge, credenciais reais, POST em produção, webhook remoto real, cobranças reais, notificações, estorno, purge de dados antigos, consumidor de automações pós-commit, suporte financeiro multiemitente, múltiplas contas históricas sem credenciais independentes, parcelamento reconciliável, tokenização recorrente, CNAB e saga de troca bancária. Estes recursos não são anunciados como concluídos.

## Decisões técnicas e arquitetura
- URL própria Asaas por integração, construída de APP_BASE_URL HTTPS confiável; public_id não é segredo.
- Header oficial asaas-access-token; comparação constante do hash, sem protocolo HMAC inventado.
- CSPRNG no servidor; apenas hash persistido; token readonly/copiar na resposta protegida de gerar/regenerar, sem recuperação posterior em GET.
- Uma entrada durável por integração/evento, payload mínimo sem cliente/cartão, conflito sem sobrescrever histórico.
- GET canônico de carteira, cobrança e cliente. Origem por provider/carteira/ambiente/ID remoto, sem fallback de ambiente atual.
- Recebimento local integral e outbox na mesma transação InnoDB. Sem chamada ao Payments_model::add; caminhos genéricos passam a confirmar inserção sob a mesma trava antes de seus efeitos posteriores, enquanto o canônico mantém outbox pendente.
- Cartão usa invoiceUrl HTTPS verificada do Asaas, com criação/reuso vinculados à origem; nenhum campo de PAN/CVV/token reutilizável é aceito.

## Alterações realizadas
Correção dos destinos de rota relativos ao HMVC; GET público sem dados e sem inicialização do gateway; POST limitado/autenticado e persist-only; migração 148; integrações/auditoria/outbox; reprocessamento administrativo; estado canônico observado; guardas de recibos/créditos/BRL, data efetiva e forma de pagamento canônicas, prova de origem/outbox para recibo já existente; callbacks fora de ordem sem regressão; ambiente inválido bloqueado; painel agrupado semanticamente; endpoint antigo de cadastro remoto desabilitado; formulário de cartão substituído por redirecionamento seguro.

## Arquivos adicionados, modificados e removidos
Adicionados: WebhookIntegrationSchema/Store, WebhookTransactionGuard, CanonicalReceiptRecorder, InvoicePaymentWriter, WebhookAdminPresentation, HostedCardCheckout, AdminSettingsPolicy, migration 148, JS das abas, testes de segurança/rotas/admin/hosted/settings/MySQL e documentos desta entrega.
Modificados: Payments_model e Credit_notes_model (hunks mínimos sobre o core da PR40, sem fase de emitentes), `modules/asaas/asaas.php`, install.php, routes.php, controllers Asaas/Checkout/Callback, AsaasAdapter, PaymentOriginStore, AsaasSdkProvider, Asaas_gateway, views webhooks/cartao, teste integrado anterior e workflow Asaas.
Removidos: nenhum arquivo. Removidos caminhos de coleta/armazenamento e baixa direta de cartão, e acesso de UI ao segredo legado salvo.

## Banco de dados
Novas tabelas InnoDB: integrações, auditoria administrativa e outbox de automações. Fila existente recebe scope/integration, fingerprint, tentativas/backoff, status canônico e campos de revisão. Índice global event_id é substituído pelo índice composto de escopo. Migração repetível, sem atribuição automática de origem ao legado, sem exclusão de payload histórico nem dados antigos de cartão. Down preserva evidências e recibos. Instalação inicial e upgrade usam App_module_migration nativo; não é um executor alternativo nem schema executado apenas no install. Teste usa o runner real e simula falha em 148 sem marcar a versão concluída.

## APIs, contratos e integrações
Entrada POST pública JSON; gestão POST administrativa com CSRF próprio; consultas GET carteira/pagamento/cliente; checkout hosted usa criação normal da origem quando elegível. Nenhum cadastro/edição remota de webhook é executado pela nova UI. A política publicada de baixa em CONFIRMED/RECEIVED é preservada, com consulta canônica e estado exato persistido, sem equivaler confirmação a saldo disponível. Parcelado e recorrente permanecem explicitamente pendentes de ledger/conciliação completos.

## Dependências, configurações e variáveis de ambiente
Sem nova dependência PHP em produção. Exige PHP >=8.1, DB InnoDB e migrações 146 (origem), 147 (NOOP de rota) e 148 (webhook). APP_BASE_URL deve ser HTTPS. Ambiente salvo estritamente '0' ou '1'; debug/logs são opções separadas. Testes MySQL usam somente DSN/senha descartáveis da CI; teste DOM usa Chrome local do runner, sem navegação externa.

## Segurança e privacidade
Admin obrigatório antes de carregar configurações; POST+CSRF próprio; token não aceito de campo arbitrário, guardado somente como hash e mostrado uma vez sem cache. Rotação/desativação/revisão têm auditoria atômica. Sem tokens na URL, headers ou mensagens de erro. Ingress limita tamanho/schema e remove payloads financeiros desnecessários. Comparação CPF/CNPJ preserva letras. Formulário hosted não coleta cartão e rejeita submissões legadas. Dados de cartão já existentes exigem avaliação/saneamento específico autorizado; não foram inspecionados nem apagados.

## Desempenho e observabilidade
Entrada curta sem rede externa; CRON processa lotes de até 50, com locks e backoff. Queries indexadas por escopo/evento e pendência. Cada conciliação verifica engine/conta/pagamento/cliente. UI mostra último evento válido, rejeições, status local/canônico, revisão e outbox pendente. Registro remoto é indicado como não verificado. Integrações/listagens limitadas, sem exposição de payload.

## Testes implementados
Regressões de origem/cancelamento; schema/auth/dedup/conflicts; conta/ambiente/issuer; documento alfanumérico; status fora de ordem; DB/commit/lock/retry; revisão assistida; geração/rotação/auditoria; endpoint real e HMVC real; admin/CSRF/URL; checkout hosted/controller/gateway; política e DOM das abas. MySQL descartável inclui migração nativa real, duas conexões/processos, gravador de recibo/outbox real, ambas as ordens manual/crédito/canônico, rollback e transação externa. DOM gera screenshot sintético como artefato da CI.

## Testes executados
PHP WASM local 8.1 e 8.5 (775 asserções em cada versão): remoção 59, origem 120, webhook 169, rota/controller 32, regressão da PR42 29, versão nativa 8, admin 89, hosted 82, gateway hosted 39, settings 20, writer 84 e métodos reais do core 44. JavaScript policy 11 verificações. Lint PHP/JS, YAML e diff-check aprovados. DOM Chromium local não executado por limitação do ambiente; CI tem job dedicado. MySQL local indisponível; testes reais ficam na CI. Resultados devem ser confirmados para o SHA final antes de concluir.

## Testes recomendados e validação manual
Homologar aplicação completa e migration com backup, URL final do ambiente, GET público e POST sintético autorizado, tokens gerados localmente, configuração manual no Asaas sandbox, eventos recebidos fora de ordem, cartão hosted e parcela única. Conferir outbox/revisão e ausência de efeitos de e-mail/hooks. Validar dados legados separadamente sem expô-los em logs.

## Impactos, compatibilidade, riscos, mitigações e breaking changes
Eventos sem integração explícita deixam de baixar automaticamente e entram em revisão, inclusive pendências legadas. Ativação exige cadastrar conta/ambiente verificados e configurar URL/token manualmente no Asaas; origem deve ser conciliada antes do retry assistido. A política legada de cancelar/excluir da PR40 não é ampliada.

Somente recebimento integral sem crédito/parcial, em BRL e com paymentDate canônico válido é automático. paymentDate é a data financeira; daterecorded é a data de processamento. Não inferir a data do cliente, crédito ou hoje em caso de ausência. A política atual de CONFIRMED para baixa local é preservada; pagamento pelo cliente e saldo disponível continuam sendo marcos distintos. O recibo atualiza status, preserva total/impostos e deixa automações pendentes. Contabilidade, produtos/serviços, notificações e webhooks de saída não são considerados sincronizados; consumidor seguro ainda precisa ser entregue. Não há garantia exactly-once de efeitos externos nem coordenação absoluta com SQL direto/módulos terceiros. Os caminhos de pagamento manual/lote e aplicação de crédito do core compartilham a trava com a baixa canônica; a nova emissão herdada da PR40 ainda não compartilha a reserva transacional, portanto emissão simultânea x pagamento manual não está coberta. Origem Asaas conhecida, inclusive removida, exige revisão em vez de recebimento/crédito genérico; recibo com outbox não pode ser alterado/excluído genericamente. Lotes conservam resultados por item e não têm atomicidade global. Desativar integração bloqueia também sua fila; reativação/transferência não está implementada. Cartão parcelado/recorrente fica bloqueado para não afirmar suporte sem ledger correspondente. Mudança de credencial para outra carteira falha fechada. Issuer positivo segue bloqueado.

## Procedimento de deploy
Não executado. Revisar PR/CI e impactos, aplicar PR40 e PR42 antes, fazer backup, homologar, atualizar módulo para 1.4.8 e executar migration 148. Verificar InnoDB, APP_BASE_URL, ambiente e revisão legada. Só depois configurar manualmente URL/token Asaas com autorização. Não alternar conta/ambiente em origem histórica. Revisar automações pendentes.

## Procedimento de rollback
Suspender entrada/conciliação antes de voltar código. Preservar tabelas/eventos/recibos/outbox. Não fazer downgrade cego: código anterior pode voltar a usar payload legado/CONFIRMED e coletar cartão. Rotação de token exige coordenação com painel Asaas; não restaurar segredos automaticamente.

## Build, release e distribuição
Versão interna do módulo 1.4.8. Nenhuma imagem/tag/release/deploy é criada manualmente nesta entrega; workflows existentes podem validar a PR conforme seu gatilho.

## Documentação
`docs/asaas-webhook-seguranca.md` detalha arquitetura, legado, efeitos pós-commit, ativação/rollback e fontes primárias Asaas. Inventário de cartão acompanha a entrega. O módulo geral de webhooks foi avaliado e não substitui a entrada específica do provedor.

## Checklist
- [x] Código, testes, docs e escopo isolados em branch nova.
- [x] Heads anteriores preservados para merge pelo usuário.
- [x] Sem operações/segredos reais, merge ou deploy.
- [x] Revisão legada e automações pendentes visíveis.
- [x] Testes locais observados; limitações descritas.
- [x] Política publicada de CONFIRMED/RECEIVED preservada na composição; testes adicionais de sequência e estado canônico.
- [ ] CI do SHA final verificada.
- [ ] Homologação CRM completo/Asaas sandbox/visual completa.

## Commit sugerido
`fix(asaas): isola webhooks e migra cartao para checkout hospedado`

## Merge sugerido
Squash após revisão, CI, homologação e autorização explícita. Não executado.

## Versão sugerida
Patch do módulo Asaas 1.4.8, sem presumir nova versão global do CRM.
