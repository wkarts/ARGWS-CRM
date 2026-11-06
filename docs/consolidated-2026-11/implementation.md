# Consolidação de 6/11/2026 — implementação e lacunas

**Entrega parcial em draft. Não atende integralmente o consolidado e não está pronta para merge/deploy.**

## Fonte e bases verificadas

O contrato integral está em [contract.md](contract.md): 153446 bytes, 1713 linhas,
SHA-256 `3379878eeab991a7a0fb87849859dfffec5b163e39b22694ddf1bb5710a76de5`.
Inclui atualizações e anexos A–H. As atualizações prevalecem sobre os checkpoints.
Nenhuma afirmação histórica de testes foi convertida em teste desta entrega.

Main revalidada após a mensagem de integração: `769840ad` (merge PR62).
PR61 e PR62 estão integradas; o head62 permanece `93f9ed8734c48bf88dfa3b7d27ee01aa355fbc38`.
A branch foi sincronizada com a main sem descartar o delta local. A nova PR tem
base **main**, sem dependência de PR62 aberta, e avança **4.9.0/490 → 4.9.1/491**
com o gerador oficial e migration NOOP. Nenhuma migration publicada foi editada.

## Delta implementado

- `NativeCompanySettingsController`: seleção GET/HEAD do Asaas em
  `settings?group=payment_gateways&gateway=asaas`, após autorização e contexto
  nativos. Seleção desconhecida falha; POST continua nos endpoints guardados.
- `native_payment_gateways.php`: links compactos, sem cards redundantes;
  gateways e meios manuais continuam disponíveis segundo ativação/ACL.
- `native_asaas.php` e `native-asaas-settings.js`: guias Contas, Configuração,
  Cartão, Credenciais, Webhook e logs, Operações, Transações, Conciliação, Eventos e
  revisão e Reembolsos quando habilitados. São as operações existentes;
  rótulos não adicionam capacidades financeiras. Deep links, teclado e campo
  inválido ativam a guia; submit preserva destino, CSRF e contexto. O teste
  Chromium detectou e levou à correção de `form.action` sombreado pelo input
  oculto `name=action`; usam-se atributos explícitos.
- `NativeCompanySpecializedOperations`: nenhuma recuperação da chave API salva
  para o navegador, mesmo em POST administrativo. Troca explícita de chave
  continua disponível. A recuperação autorizada do token de webhook existente
  permanece separada; não se afirma que nenhum segredo de webhook chega ao DOM.
- `NativeCompanyAsaasCardPreferences` e `native_asaas_card.php`: guia própria de
  cartão, disponibilidade por empresa/ambiente usando o perfil efetivamente
  consumido pela emissão. Save preserva Pix, boleto, rótulo e descrição, com
  CAS e ACL sob lock. Não adiciona opções sem consumidor funcional. O fluxo
  direto completo e outros parâmetros de cartão permanecem pendentes.
- Nenhum escritor financeiro, regra de preço, calendário, produto, contrato
  bancário ou calendário foi alterado no CRM. Há delta restrito do SDK em PR própria; o vendor do CRM não foi atualizado silenciosamente.

## Matriz requisito → existente → lacuna → delta → evidência

Os caminhos de serviços core abaixo são relativos a `application/services/`.
A [matriz de 64 AC da PR61](../requirements-r8/acceptance.md) permanece histórica;
suas pendências não foram apagadas pelo merge nem pelo delta desta PR.

| Requisito | Código existente / limite observado | Lacuna atual | Implementação desta PR | Teste/evidência atual |
|---|---|---|---|---|
| R01 Propostas | Adaptadores nativos; matriz AC01–06 | Paridade de ações e comparação 4.0.2 completa não comprovadas | Preservado | Sem nova homologação de propostas |
| R02 Marca/PWA | `NativePwaIdentity`, shell nativo | Browser/PWA de todas as empresas e estados | Preservado | `tests/native_company/presentation.php` |
| R03 Orçamentos | `NativeCompanyEstimateForm`, matriz AC08–13 | Paridade integral e conflitos documentados | Preservado | Não revalidado integralmente |
| R04 Faturas | `CompanyInvoices`, `NativeCompanyInvoiceCommands` e snapshots | Edição de emitidas não equivale ao legado; ciclo Asaas completo pendente | Preservado | Testes Asaas financeiros delimitados abaixo |
| R05 Recorrências | `CompanyInvoiceRecurring` + Modes + Origin | Reajuste futuro não existe; não editar snapshot base | Preservado | Inspeção dos hashes/auditoria; sem aceite de reajustes |
| R06 Pagamentos | Escritor nativo e contexto | Travamento relatado não reproduzido em instalação autenticada | Preservado | MySQL escritor, 34 asserts; não é reprodução do menu |
| R07 Créditos | `NativeCompanyCreditsController` e ledger | Conflitos de edição/reabertura da matriz anterior | Preservado | Sem nova homologação completa |
| R08 Itens | Catálogo próprio e união autorizada | Identidade do item na linha de recorrência não está persistida | Preservado; Produtos não unido a Itens | Inspeção `CompanyInvoiceLines::normalize` |
| R09 Todo Vendas | Inventário e adaptadores da PR61 | Cobertura integral de módulos/ações não comprovada | Asaas em pagamento nativo | Testes de view/controlador/browser Asaas |
| R10 Outras áreas | Controllers nativos e contratos globais da PR61 | Áreas NA01–NA09 e propriedade das integrações continuam na matriz anterior | Não se abriu módulo global em secundária | Navegação/presentation limitada |
| R11 Empresa ativa | `CompanyRequestScope`, `IssuerResourceAccess`, runtime settings | Cobertura universal não comprovada | Seleção Asaas conserva lease/ACL; exportação API bloqueada | Native settings controller + secret controller A/B |
| BB01 Configuração | `modules/banco_brasil/controllers/Banco_brasil.php`, view index | Configuração ainda mistura operação no menu BB | Link existente preservado, sem alegar reorganização concluída | Teste administrativo BB |
| BB02 API/240/400 | Perfis e layouts próprios | Guias específicas no local exigido faltam | Sem alteração | Parsers 240/400 separados |
| BB03 Parâmetros reais | Conexão, layout bindings, perfis origination/API | Validar convênio/conta/layout de destino e desenho das guias | Nenhum parâmetro inventado | Sem homologação contratual bancária |
| BB04 Operação | Menu e arquivos de remessa/retorno | Separar configuração mantendo operação | Preservado | Testes administrativos/store |
| BB05 Boleto antes transmissão | Origination/artefatos vinculados | Integração automática com emissão/recorrência não demonstrada | Não implementado | Não homologado |
| BB06 Lote aberto compatível | `Origination::generate` cria lote e `finish` congela | Agrupamento incremental automático até download ausente | Não implementado | Inspeção; não declarar teste de agrupamento |
| BB07 Primeiro download fecha | `Origination::download` altera entrega de bytes já congelados | Fechamento deve passar a ocorrer atomicamente no primeiro download | Não implementado | Corrida emissão/download nova não testada |
| BB08 Download idêntico/estados | Bytes e estado de entrega separados | Depende do novo ciclo de lote aberto | Preservado | Não validado contra novo contrato de lotes |
| BB09 Retornos | Reconciliation, reversões e componentes existentes | Cenários completos do contrato atual não homologados | Preservado | Parsers/store não equivalem a baixa financeira |
| BB10 Tauri | Etapa posterior expressa | Template/autenticação/plataformas fora deste delta | Não implementado | Não bloqueia CRM |
| BB11 Menus | `NativeCompanyAdminPresentation`, catálogo seguro | Relato não reproduzido em sessão real | Sem correção especulativa | Presentation já testa sequência BB/Painel/Settings e Recursos |
| PIGE01 Configurações | Settings e registro nativo107 | Acesso em empresas secundárias depende do contrato atual | Preservado | Browser PIGE atual, resultado abaixo |
| PIGE02 Múltiplas conexões | Connections::listed/save por chave/escopo | Validar instalação alvo | Preservado | Fixture com12 conexões A/B |
| PIGE03 Modais/guias | `views/settings.php`, `assets/settings.js` | Seletores administrativos ainda são IDs | Preservado | Browser desktop/mobile e formulário real |
| PIGE04 URL/credencial | Não existe descoberta bilateral | URL/token não autoconfigura | Não implementado | Inspeção do contrato remoto, não conexão real |
| PIGE05 Bilateral | PIGE GET/PUT configuration e connection-test | Contrato mínimo de descoberta e credencial específica faltam | Não inventado; nenhum push PIGE | Referência remota abaixo |
| PIGE06 Empresa/navegação | Administração primária; ACL por empresa | Abrir configuração secundária não autorizado por inferência | Preservado | Browser não prova ACL de servidor |
| PIGE07 Histórico/segredos | Revisão, disable, invalidar consentimento | Testar HTTP autenticado/revogação concorrente | Preservado | Guards PHP e browser token/consentimento |
| ASA01 Configuração unificada | View nativa já contém operações | Equivalência total ao1.4.8 não comprovada | Guias e acesso em Meios de pagamento | PHP+Chromium com Bootstrap real |
| ASA02 Credenciais | Guard/cifra por conta/ambiente | Token webhook permanece recuperável mediante ação protegida | Chave API salva nunca exportada pelo controller | POST com/sem CSRF, A/B, master/secundário |
| ASA03 Cartão obrigatório | Checkout hospedado; SDK já oferece tokenização/cobrança | Checkout rejeitado não satisfaz; seguir documentação e parametrização flexível conforme esclarecimento posterior | Guia de configuração efetiva; SDK ganhou contrato ausente e bloqueio de retry; pagamento direto CRM pendente | Inventário abaixo; sem sandbox/cartão aprovado |
| ASA04 Ciclo faturas | Origem, operações remotas e reconciliação | Delete/cancel/reactivate/manual parcial/HTTP incerto exigem matriz ponta a ponta | Preservado, não reescrito | Escritor MySQL e suites delimitadas; não aceite integral |
| ASA05 Observabilidade | Eventos, jobs, provas e revisão existentes | Operações relacionadas a cada evento não homologadas integralmente | Guias para recursos existentes | View/controller; sem efeito financeiro real |
| REA01 Tela/filtros | Diretório `CompanyInvoiceRecurring::directory` | Tela única item/meio/regras/grid ausente | Não implementado | Fonte consolidada§4/AnexoB; gap explícito |
| REA02 Elegibilidade | `principalPaid` em Modes; estado de recorrência distinto da fatura | Modelo futuro com original paga precisa entrar no novo seletor | Não implementado | Inspeção; não confundir fatura paga com recorrência encerrada |
| REA03 Vínculo item | Payload normaliza descrição/qty/rate, sem ID de catálogo | Vínculo legado revisado e identidade persistente faltam | Não implementado | Não houve equivalência automática por descrição |
| REA04 Regras/quantidade/+ | Snapshots de linhas existentes | Múltiplos preços/itens, qty local e inclusão alvo faltam | Não implementado | Nenhuma alteração de Produtos/faturas |
| REA05 Simulação/revisão | Nenhum fluxo de reajuste | Preview imprimível, confirmação, revisão e resultado parcial faltam | Não implementado | Não chamar HTML de simulação financeira |
| REA06 Aplicação/agendamento | Schedule/Origin/Modes validam hashes e auditoria de criação | Revisões futuras precisam de journal imutável e fuso empresarial | Não implementado | Mutar payload_json quebraria validação; calendário não alterado |
| REA07 Integridade/ACL | Transações e locks nos serviços existentes | Atomicidade da campanha, corrida com emissor, idempotência, ACL/CSRF próprios faltam | Não implementado | Nenhum teste de reajuste executado porque o recurso não existe |

## Contratos externos inspecionados — somente leitura

- PIGE360-self main `f47b8916b92231ac29b56862b133c9d2b2eec851`,
  `backend/app/crm_bridge.py`: configuration GET/PUT e connection-test exigem
  JWT administrativo/permissão escolar. Diagnóstico HMAC requer escopo completo;
  não há contrato de descoberta URL/token. Segredo HMAC não é Bearer.
- Módulo referência Asaas `999e8e4cba5142259c20e4d65e6d79f2406928b7`,
  header1.4.8 e `libraries/Asaas_gateway.php`: captura direta e holder existem
  na referência. Sua existência não autoriza copiá-los para este CRM.
- SDK já incluído `modules/asaas/vendor/argws/asaas-sdk-php` possui
  CreditCardService::creditCardTokenization e PaymentService::
  createNewPaymentWithCreditCard/payAChargeWithCreditCard. O SDK remoto foi inspecionado em `7a9b3324e7d2365fcbff0b0265ba7f7deecd92be`; não possuía payWithCard e repetia POST após erro. Delta específico em branch `fix/card-payment-contracts`: payAChargeWithCard e nenhuma repetição automática para pagamento/criação com cartão. PR SDK [21](https://github.com/wkarts/asaas-sdk-argws/pull/21), head `0a1180b2f5a27c6348ee7d686e78e875951d9d14`, draft. Vendor CRM permanece na versão anterior.
- Documentação oficial consultada: [PCI](https://docs.asaas.com/docs/pci-dss),
  [tokenização](https://docs.asaas.com/reference/credit-card-tokenization),
  [pagamento de cobrança](https://docs.asaas.com/reference/pay-a-charge-with-credit-card).
  Endpoints/modelos novos e legados não devem ser misturados. O usuário
  esclareceu que deseja guia específica de cartão e parametrização segundo a
  API, sem modelo engessado; a pergunta binária anterior não é bloqueio nem
  decisão aceita. A implementação direta no CRM, 3DS, tokenização e outros
  parâmetros documentados ainda não está concluída. Nenhuma cobrança enviada.


## Validação e limites

Consulte [validation.md](validation.md) para comandos e resultados locais.
O CI precisa ser vinculado ao SHA final da PR; a presença de um workflow não
comprova execução. O navegador usa view real com framework/dados sintéticos,
não uma instalação autenticada. MySQL usa banco descartável. Não houve
transmissão bancária, cobrança, baixa em produção ou alteração de credenciais.

## Operação, risco e rollback

Não distribuir este checkpoint como solução integral. Após completar/revisar
as lacunas, usar runner nativo para491 e confirmar
tracking;491 é NOOP. Migrations anteriores permanecem intactas. Este delta
não muda DDL nem dados financeiros. Código anterior pode ser restaurado sem
reduzir marcadores; manter backup operacional consistente. Reverter o código
reabre a exportação administrativa da chave API antiga, portanto preferir
correção adiante para erros de apresentação. Nenhuma implantação executada.
