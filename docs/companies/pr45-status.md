# Matriz objetiva da PR45

Escopo integral em andamento. Núcleo atual4.1.9/419; BB1.0.9/101–109;
Asaas1.4.8/148. As migrations publicadas402–412 foram preservadas. Não há
merge/deploy/release nem autorização implícita por um resultado de CI.

Os incrementos405 e406 possuem provas MySQL/HTTP/Chromium publicadas. Para407,
a correção de navegação está em44260b61; consultar os checks desse SHA. O incremento408 está validado em a8b17f9 (MySQL8.1/8.5 e stack HTTP/Chromium).
O baseline412 (7aa56e60) passou nos16 workflows, incluindo originação e retorno
assistido por HTTP/Chromium. 413/API passou em MySQL/PHP8.1/8.5 e HTTP/Chromium. O job MariaDB detectou
cache de locks no teste e SQL NULL no metadata do núcleo/templates/fila, tratados nos incrementos
seguintes. Para416/portal/MariaDB, consultar o HEAD e seus runs;
não atribuir os resultados412 aos novos testes.
Inventários atualizados localizam revisão; não medem bugs, cobertura ou conclusão.

| Requisito | Implementação atual | Testes executáveis/evidência | Estado e limites |
|---|---|---|---|
| Empresa principal/master | Identidade do instalador preservada; designação legada autorizada com console+senha/hash/expiração/auditoria | Instalação/upgrade, master e concorrência, HTTP/Chromium | Implementado/testado no recorte; designação não prova identidade histórica e depende de console |
| Nova empresa e funcionários | Wizard persistente/revisão; criação/settings/grants/default atômicos; novo funcionário exige empresa/default | Onboarding, native Staff, MySQL com lock observado e navegador | Implementado no recorte; não libera todos os domínios |
| Seletor e contexto | ACL própria, nonce/CSRF/HMAC,409 de aba obsoleta antes de hidratar | Company context e navegador | Preservado; sem replay de POST antigo |
| Clientes privados/opt-in | Diretório positivo criar/editar/ler/buscar/paginar, compartilhar/revogar identidade; recibos duráveis | Clients PDO, concorrência, CRM/Chromium | Fluxo positivo entregue; outros consumidores continuam com barreiras |
| Contatos e portal/recuperação | Contatos próprios; login por owner; A/B com e-mail igual; template/SMTP/fila por origem; reset transacional, anti-replay e sessões | Portal positivo/concorrência/navegador; regressões legadas | Portal de identidade/recuperação; entrega SMTP externa não realizada; 414 adiciona lista/PDF de faturas próprias com permissão nativa explícita; demais documentos/operações do portal pendentes |
| Settings/SMTP/templates | Identidade canônica, preferências, próprios segredos sem fallback, templates por origem | Suítes settings, queue, portal e HTTP | Identidade/SMTP/templates/data-hora, preferências de fatura e imagens próprias; catálogo/integrações restantes pendentes |
| Faturas nomeadas | UI isolada emitir/listar/consultar/editar sem movimento/cancelar; itens/múltiplos tributos/descontos/ajustes, numeração e snapshots atômicos | Invoices PDO, MySQL concorrente, CRM HTTP/Chromium | Incremento408; perfil restrito documentado, validação remota pertence ao SHA final |
| Recebimentos manuais | Entrada explícita no writer; ACL/owner/saldo sob lock, referência, retry, status e auditoria atômicos | Invoices/concorrência e regressões writer Asaas | Registro manual local; não confirma banco; origens BB/Asaas recusadas para revisão própria |
| PDF de faturas nomeadas | DTO autorizado e TCPDF; staff e portal privado com permissão nativa explícita; revalidação/auditoria antes dos bytes | PDF real, A/B, legado NULL e testes414 de revogação/HTTP | Lista/PDF de faturas emitidas do cliente e empresa próprios; rascunhos ocultos; sem conceder demais operações ou histórico compartilhado |
| Demais operações financeiras | Créditos/refunds/estornos, recorrência, envio/anexos, relatórios/API restantes | Suítes existentes e inventários | Pendências técnicas; gates preservados |
| Projetos/tarefas/despesas/outros módulos | Barreiras e adaptações parciais existentes | Testes por consumidor | Operação positiva completa pendente |
| BB API Cobrança | Perfis/credenciais criptografadas nativas; OAuth, registro, consulta com prova de conta e pedido/confirmação de baixa; outbox e origem imutável | Cliente HTTP simulado24 checks; composição MySQL/concorrência e MariaDB adicionadas no413, resultado depende do SHA final | Perfil17/simples/BRL sem ajustes/Pix; sem pagamento API automático.419 adiciona crédito explícito16 integral sem ajustes; alteração, webhook mTLS e demais perfis financeiros ainda pendentes; nenhuma homologação |
| CNAB240 | Codec P/Q e parser T/U restritos; originação ligada à fatura, reserva durável, retry e download auditado | Fixtures + novos testes de composição/concorrência MySQL/HTTP no baseline412 | Geração local condicionada a contrato/perfil; declaração manual de envio não é aceite; sem transporte ou homologação |
| CNAB400 | Parser CBR643 e candidato CBR641 | Fixtures e PDFs/hash oficiais | Candidato bloqueado: BRL/juros/contrato e picture CBR643; sem homologação |
| Origem e conciliação | Conexões/perfis imutáveis, upload observacional; vínculo exato e revisão; crédito240 restrito confirmado pelo operador via writer nativo | Store/ACL e novos testes MySQL/concorrência/HTTP412 | Assistido, sem autenticação automática do arquivo. Parcial sem ajustes suporta saldo; juros/desconto/tarifa/estorno financeiro e automação permanecem pendentes |
| Asaas/OI/notificações | Funcionalidade existente preservada; positivo Asaas continua bloqueado | Workflows próprios; aviso88 checks sintéticos | Regressões do writer e workflows por SHA; sem inferir homologação/produção |
| Homologação API/240/400 | Nenhuma realizada | Sem credenciais/transações/arquivos reais | Externa, separada por canal/conta/convênio/carteira |

Contratos detalhados: [clientes](private-customers.md), [portal](portal-recovery.md),
[faturas/recebimentos](invoice-operations.md), [wizard](onboarding.md).

A falta de homologação não elimina as pendências técnicas. Ocorrência06 não autentica
o banco, download não transmite e cancelamento local não confirma baixa remota.
Não aplicar eventos bancários sem origem, evidência canônica e writer transacional.

## Incremento417 — editor monetário completo no recorte de faturas

Código: `CompanyInvoiceAmounts`, `CompanyInvoices`, controlador/formulário nativo.
Campos existentes em invoices/item_tax; migration417 é marcador NOOP, sem DDL e
sem alterar migrations históricas. Gates de outros consumidores permanecem fechados.

| Requisito | Teste e resultado local | Limite |
|---|---|---|
| Múltiplos tributos, desconto fixo/percentual antes/depois dos tributos, ajuste com sinal | PHP8.3.6: `tests/companies/invoice_amounts.php`,27 checks; exemplos de centavos/overflow/inválidos | Regras descritas no guia de faturas; não é cálculo fiscal nacional completo |
| Persistência/edição/recebimento A/B, snapshot, replay pré417 e rollback | `tests/companies/invoices.php`,59 checks acumulados em SQLite | SQLite não prova locks/engine |
| Portal/PDF após ampliação | `tests/companies/portal_invoices.php`,85 checks acumulados, PDF TCPDF real | Regressão local sem SMTP/banco externo |
| Tracking nativo | `tests/native_migration_tracking.php`,12 checks em SQLite | Prova de tracking, não instalação universal |
| Políticas de release/inventário |43 testes Python aprovados | Gate estático não substitui operação |
| Editor monetário concorrente/revogação e GUI A/B/PDF/aba obsoleta | Testes ampliados em invoice_concurrency.php e companies_invoices_browser.cjs | MariaDB10.11.14 local:83 checks acumulados de fatura/edição/recebimento/concorrência e89 de portal/revogação; MySQL8/Chromium aguardam CI do novo HEAD |

Nenhuma chamada real de banco/SMTP, merge ou deploy neste incremento.

## Incremento418 — imagens e preferências de faturas por empresa

`admin/company_assets` usa renderer isolado, contexto/CSRF, settings/view+edit,
revisão concorrente e ownership pela empresa atual. PNG/JPEG até512 KiB são
validados por dimensão/pixels e convertidos para PNG, removendo metadados/conteúdo
adicional. Registro privado no JSON da empresa, sem URL pública ou caminho físico.
Download confirma ACL e auditoria transacional antes dos bytes. Não há fallback
para imagens de outra empresa ou uploads globais. Assinatura é imagem, não A1/GovBR.
Novas faturas capturam logo/assinatura; documentos antigos conservam seu snapshot.
Definições/Faturas oferece prefixo e condições próprias, validados e escapados.
Migration418 é NOOP; não altera schema, tracking ou migrations históricas.

| Requisito | Execução local PHP8.3.6 | Limitação |
|---|---|---|
| Upload, preview, remoção, download, A/B/principal, ACL, auditoria/falha, imagem inválida/oversized | company_assets.php:24 checks em SQLite e MariaDB10.11.14 | Sem HTTP local; o novo teste Chromium pertence ao novo HEAD |
| Escritores concorrentes e revogação aguardando lock | asset_concurrency.php:32 checks acumulados em MariaDB10.11.14 | MySQL8/PHP8.1/8.5 no CI |
| Snapshot/PDF real após troca/remoção de imagens | invoices.php:63 checks acumulados; invoice_concurrency.php:87 em MariaDB | Sem certificado digital |
| Portal/PDF e revogação durante lock | portal_invoice_concurrency.php:93 checks acumulados em MariaDB | Sem banco/SMTP externos |
| Prefixo/condições A/B, batch inválido e escape | settings_model_controller.php:134 checks acumulados em SQLite | Demais configurações não declaradas continuam bloqueadas |

CI do417 (`5d0f41a3`): fluxos MySQL8/PHP8.1/8.5, BB MySQL/MariaDB e stack
HTTP/Chromium aprovados. Dois gates falharam por inventário desatualizado;418
regenera os inventários conservando entradas alteradas como pendentes. Não reutiliza
essa execução como prova do código418 nem muda rollout global para liberado.

## Incremento419 / BB109 — crédito API explícito e canônico

`ApiCreditedReceipt` é a segunda fonte fechada autorizada no writer nativo; uma
implementação arbitrária da interface continua negada. `settle_api` exige contexto,
POST/CSRF, BB/view+reconcile, invoices/view e payments/create. Usa somente origem
imutável e a última consulta validada com prova da conta, até5 minutos no primeiro
lançamento, estado16 CREDITADO, natureza normal/apresentação, liquidação elegível,
data de crédito já ocorrida, nominal/pago/líquido integrais e todos os ajustes zero.
6 LIQUIDADO não basta; parcial/cheque/cartório/ajustes/dados ausentes ficam em revisão.
Recibos usam identidade bancária estável, independentemente de ator/chave da UI ou
nova consulta idêntica; pagamento, status, recibo e auditorias confirmam juntos.
Contradição materializada não é apagada por consulta posterior; nenhum estorno é
executado por uma observação adversa. Provas financeiras cruzadas API/CNAB impedem
nova aplicação do mesmo título. SQL/JSON ambíguos não são normalizados para sucesso.

Nova tabela aditiva bb_api_receipts: PK por origem, unicidades de recibo/pagamento,
FK para origem/consulta, InnoDB/utf8mb4_bin, tipos/nullability/defaults conferidos.
Migration109 idempotente; falha de tracking preserva108 e retry completa109.419 é
marcador core NOOP. Migrations históricas não foram alteradas. Módulo requer4.1.9.
A correção de `imagedestroy` substitui a chamada obsoleta por liberação do objeto,
sem reduzir os asserts de conteúdo/concorrência no PHP8.5.

| Prova local PHP8.3.6/MariaDB10.11.14 | Resultado | Limite |
|---|---|---|
| Native module101–109/instalação/upgrade/replay/falha tracking, API/outbox/criptografia |160 checks acumulados | Transporte BB simulado |
| Crédito A/B, repetição/nova consulta, revisão por juros/parcial/cheque, data/valor/prova ausente, auditoria/proveniência e conflito imutável |301 checks acumulados incluindo suítes anteriores | Não somar como301 cenários novos |
| Concorrência de dois créditos, crédito versus manual e revogação aguardando lock |331 checks acumulados incluindo as suítes anteriores | Banco local real, provedor simulado |
| Regressão da conciliação CNAB240 assistida/concorrência |129 checks acumulados | Evidência sintética, sem autenticação bancária real |
| Asaas writer/OI |84/88 checks separados | Sem homologação Asaas/produção |
| Novo fluxo Chromium |Fixture usa serviços reais com transporte injetado; GUI real confirma/retry via writer | Resultado apenas após CI do novo HEAD |

OpenAPI oficial baixada novamente em19/10/2026, versão3.2.2, SHA256 permanece
6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3.
A semântica documenta6 LIQUIDADO e16 CREDITADO; somente16 é elegível neste recorte.
Alterações de título, webhook mTLS, encargos/parciais/estornos e demais perfis são
pendências técnicas. CNAB400 continua candidato bloqueado pelos insumos contratuais
listados no guia400. Não há homologação API/240/400 nem autorização de merge/deploy.
