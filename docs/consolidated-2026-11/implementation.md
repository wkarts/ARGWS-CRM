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
base **main**, sem dependência de PR62 aberta, e avança **4.9.0/490 → 4.9.2/492**
com o gerador oficial: 491 NOOP e 492 aditiva para reajustes. Nenhuma migration publicada foi editada.

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
- Reajustes: journal imutável de preços futuros, vínculos explícitos revisados, regras por item, quantidade local, inclusão no alvo, simulação e confirmação, agendamento com fuso explícito. Não altera faturas emitidas, catálogo ou calendário. Veja [repricing.md](repricing.md). O vendor SDK continua sem atualização neste incremento.

## Matriz requisito → existente → lacuna → delta → evidência

Os caminhos de serviços core abaixo são relativos a `application/services/`.
A [matriz de 64 AC da PR61](../requirements-r8/acceptance.md) permanece histórica;
suas pendências não foram apagadas pelo merge nem pelo delta desta PR.

| Requisito | Código existente / limite observado | Lacuna atual | Implementação desta PR | Teste/evidência atual |
|---|---|---|---|---|
| R01 Propostas | Adaptadores nativos; matriz AC01–06 | Paridade de ações e comparação 4.0.2 completa não comprovadas | Preservado | Controller portal MySQL:18 verificações; paridade completa ainda pendente |
| R02 Marca/PWA | `NativePwaIdentity`, shell nativo | Browser/PWA de todas as empresas e estados | Preservado | `tests/native_company/presentation.php` |
| R03 Orçamentos | `NativeCompanyEstimateForm`, matriz AC08–13 | Paridade integral e conflitos documentados | Preservado | Formulário MySQL:18 verificações; decisão/conversão SQLite passaram; não integral |
| R04 Faturas | `CompanyInvoices`, `NativeCompanyInvoiceCommands` e snapshots | Edição de emitidas não equivale ao legado; ciclo Asaas completo pendente | Preservado | Testes Asaas financeiros delimitados abaixo |
| R05 Recorrências | CompanyInvoiceRecurring + Modes | Validação autenticada integral continua pendente | Journal futuro consumido pelo emissor e leitor | SQLite/MySQL + corrida de processos |
| R06 Pagamentos | Escritor nativo e contexto | Travamento relatado não reproduzido em instalação autenticada | Preservado | MySQL escritor, 34 asserts; não é reprodução do menu |
| R07 Créditos | `NativeCompanyCreditsController` e ledger | Conflitos de edição/reabertura da matriz anterior | Preservado | Controller MySQL:118 cumulativas; homologação completa pendente |
| R08 Itens | Catálogo próprio | Paridade geral de Itens não encerrada | Vínculo revisado por ID em recorrências; sem equivalência por descrição | Item estrangeiro e ausência de revisão recusados |
| R09 Todo Vendas | Inventário e adaptadores da PR61 | Cobertura integral de módulos/ações não comprovada | Asaas em pagamento nativo | Testes de view/controlador/browser Asaas |
| R10 Outras áreas | Controllers nativos e contratos globais da PR61 | Áreas NA01–NA09 e propriedade das integrações continuam na matriz anterior | Busca Unicode de leads corrigida sem mudar dados; nenhum módulo global aberto em secundária | MySQL:53 verificações de metadados, mais28 de diretório e31 de controlador; navegação autenticada ainda pendente |
| R11 Empresa ativa | `CompanyRequestScope`, `IssuerResourceAccess`, runtime settings | Cobertura universal não comprovada | Seleção Asaas conserva lease/ACL; exportação API bloqueada | Native settings controller + secret controller A/B |
| BB01 Configuração | ModuleStore e ApiService existentes | Homologação HTTP autenticada pendente | Rota settings?group=payment_gateways&gateway=banco_brasil, shell nativo, formulários existentes | View PHP real em Chromium; comandos administrativos PHP |
| BB02 API/240/400 | Perfis e codecs separados | Perfis bancários não suportados continuam recusados | Guias API/CNAB240/CNAB400 com contratos reais e vínculo comum | Chromium: guias, campos e declarações explícitas |
| BB03 Parâmetros reais | Conta, convênio7, carteira17, variação, faixa e perfis existentes | Outros convênios/carteiras exigem contrato/codec verificado; não foram inventados campos | Configuração de conta/layout/faixas/API na mesma tela | Módulo137 tracking/upgrade em MySQL; sem homologação bancária |
| BB04 Operação | Remessa/retorno, arquivos e conciliação existentes | Homologação bancária pendente | Menu operacional preservado; configuração movida e linkada | Admin PHP mantém formulários operacionais e CSRF |
| BB05 Boleto antes transmissão | Origination + CnabBoleto139 + preparação400/140 | Portal do cliente e HTTP autenticado pendentes | Emissão canônica e recorrente240/400 atômica142 com instrução explícita; boleto por snapshot antes da transmissão; [contrato](bb-invoice-issuance.md) | MySQL277, concorrência274, Chromium físico; não homologado |
| BB06 Lote aberto compatível | Origination138 + grupos CNAB400/141 | HTTP autenticado da emissão pendente | CNAB240 e CNAB400 acumulam títulos compatíveis até100, inclusive pela emissão canônica142; origens históricas preservadas | MySQL: datas distintas, retry, conta/convênio distintos e corrida emissão/download; [contrato](bb-cnab400-groups.md) |
| BB07 Primeiro download fecha | Origination::download e Cnab400Groups141 sob mutex financeiro | Homologação bancária pendente | CNAB240 e CNAB400 congelam bytes na primeira retirada; falha de auditoria reverte congelamento | Dois processos MySQL com espera observada no lock |
| BB08 Download idêntico/estados | Conteúdo persistido e hash | Homologação bancária continua necessária | Redownload de lote novo/legado preserva bytes; não marca transmissão/baixa | MySQL: hash congelado, retry e falha do encoder |
| BB09 Retornos | Reconciliation, reversões e componentes existentes | Cenários completos do contrato atual não homologados | Preservado | Parsers/store não equivalem a baixa financeira |
| BB10 Tauri | Etapa posterior expressa | Template/autenticação/plataformas fora deste delta | Não implementado | Não bloqueia CRM |
| BB11 Menus | `NativeCompanyAdminPresentation`, catálogo seguro | Relato não reproduzido em sessão real | Sem correção especulativa | Presentation já testa sequência BB/Painel/Settings e Recursos |
| PIGE01 Configurações | Settings e registro nativo107 | Acesso em empresas secundárias depende do contrato atual | Preservado | Browser PIGE atual, resultado abaixo |
| PIGE02 Múltiplas conexões | Connections::listed/save por chave/escopo | Validar instalação alvo | Preservado | Fixture com12 conexões A/B |
| PIGE03 Modais/guias | views/settings.php e settings.js | Autoconfiguração bilateral continua separada | Seletores de funcionário, moeda, conta e perfil por escopo; revisão e consentimento preservados | MySQL100 verificações cumulativas + Chromium desktop/mobile |
| PIGE04 URL/credencial | APIs reais de instituições/configuração | Configuração local e HMAC existentes ainda necessários | Descoberta109 e preparação remota desativada110, sem sobrescrever configuração existente | Contrato37 adicionais, SQL13 e Chromium; [limites](pige-discovery.md) |
| PIGE05 Bilateral | PIGE GET/PUT configuration e connection-test | Diagnóstico remoto exige habilitação; autenticação antes da ativação não comprovada | PUT de criação desativada com CAS de ausência e confirmação de transferência; nenhum push PIGE | Contrato remoto f47b8916; testes simulados, sem PUT real |
| PIGE06 Empresa/navegação | Administração primária; ACL por empresa | Abrir configuração secundária não autorizado por inferência | Preservado | Browser não prova ACL de servidor |
| PIGE07 Histórico/segredos | Revisão, disable, invalidar consentimento | Testar HTTP autenticado/revogação concorrente | Preservado | Guards PHP e browser token/consentimento |
| ASA01 Configuração unificada | View nativa já contém operações | Equivalência total ao1.4.8 não comprovada | Guias e acesso em Meios de pagamento | PHP+Chromium com Bootstrap real |
| ASA02 Credenciais | Guard/cifra por conta/ambiente | Token webhook permanece recuperável mediante ação protegida | Chave API salva nunca exportada pelo controller | POST com/sem CSRF, A/B, master/secundário |
| ASA03 Cartão obrigatório | Checkout hospedado; SDK já oferece tokenização/cobrança | Checkout rejeitado não satisfaz; seguir documentação e parametrização flexível conforme esclarecimento posterior | Guia de configuração efetiva; SDK ganhou contrato ausente e bloqueio de retry; pagamento direto por token com dois contratos e tentativa única (1.6.2); fluxo completo pendente | [Evidências e limites](asaas-direct-card.md); sem sandbox/cartão aprovado |
| ASA04 Ciclo faturas | Origem, operações remotas e reconciliação | Delete/cancel/reactivate/manual parcial/HTTP incerto exigem matriz ponta a ponta | Cancelamento nativo após exclusão remota comprovada163; demais transições pendentes | MySQL22 adicionais + corrida6; [limites](asaas-invoice-lifecycle.md) |
| ASA05 Observabilidade | Eventos, jobs, provas e revisão existentes | Operações relacionadas a cada evento não homologadas integralmente | Guias para recursos existentes | View/controller; sem efeito financeiro real |
| REA01 Tela/filtros | Rota nativa Faturas recorrentes | Homologação autenticada pendente | Tela única, filtros item/meio, grid e múltiplas regras | View PHP real em Chromium; dados/HTTP sintéticos |
| REA02 Elegibilidade | Estado da série independente da fatura principal | Perfil completo da instalação a validar | Seleção por active/paused, sem filtro por pagamento principal | Prérequisitos reais de modos/quitada preservados |
| REA03 Vínculo item | Linhas legadas sem ID | Revisão manual necessária para legado | Tabela de vínculos com hash/revisão/auditoria e catálogo autorizado | Identidade e escopo testados em SQL |
| REA04 Regras/quantidade/+ | Snapshots originais preservados | Sem aceite visual do usuário ainda | Múltiplas regras, qty por linha e inclusão por série; + exige linhas antigas revisadas | SQLite/MySQL e Chromium |
| REA05 Simulação/revisão | Novo fluxo | HTTP autenticado a executar | Relatório preservado, imprimível, confirmação da revisão exata; campanha atômica | Totais nativos; revisão concorrente invalida toda proposta |
| REA06 Aplicação/agendamento | Worker nativo | Operação do worker na instalação não verificada | Data local/IANA explícita convertida UTC; horário ambíguo recusado; journal futuro | Clock controlado com transação SQL real; nova emissão usa preço futuro |
| REA07 Integridade/ACL | Locks financeiros e ACL existentes | CSRF/HTTP autenticado e revogação concorrente específica ainda pendentes | ACL revalidada sob lock, chaves idempotentes, snapshots auditados | Dois processos MySQL: confirmação/confirmacão e confirmação/emissão; sem duplicação |

## Banco do Brasil — configuração nativa, módulo 1.3.7/137

`NativeCompanyBancoBrasilSettings` lê somente a empresa selada e exige acesso
Settings/BB; os comandos existentes continuam com ACL contextual e CSRF próprios.
Campos API não retornam segredos salvos. Conta, convênio, leiaute e declaração de
faixa foram movidos para os respectivos formulários nativos; remessa, retorno,
conciliação e operações vinculadas à origem continuam no menu BB. Query de retorno
aceita somente o destino fixo de configurações, sem URL fornecida pelo usuário.

Migration137 NOOP e header/ModuleRelease avançam juntos; 136 permanece intacta.
MySQL descartável: `banco_brasil_origination_mysql.php` passou com 233 verificações
cumulativas, incluindo upgrade/erro de tracking/repetição até137. PHP admin98,
module_version54; Chromium renderiza view real de configuração com dados sintéticos.
Os testes autenticados existentes tiveram rotas/selectors adaptados; sua execução
ponta a ponta ainda depende do CI/instalação de teste, e não é alegada como concluída.

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
  createNewPaymentWithCreditCard/payAChargeWithCreditCard. O SDK remoto foi inspecionado em `7a9b3324e7d2365fcbff0b0265ba7f7deecd92be`; não possuía payWithCard e repetia POST após erro. Delta específico em branch `fix/card-payment-contracts`: payAChargeWithCard e nenhuma repetição automática para pagamento/criação com cartão. PR SDK [21](https://github.com/wkarts/asaas-sdk-argws/pull/21) posteriormente integrada pelo usuário; main remota verificada em `170831639665082c3bf50422877942b04a4a5315`. Vendor CRM permanece na versão anterior. A ampliação independente do SDK está pausada; este delta do CRM não altera SDK nem Perfex.
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

Não distribuir como solução integral. O runner nativo aplica 491 NOOP e 492
aditiva (propostas, vínculos revisados e journal de preços); sem backfill.
Migrations anteriores permanecem intactas. Instalação/repetição do schema foi
executada em MySQL descartável; tracking do runner 492 ainda deve ser validado.
Após uso do reajuste, não reverter para um emissor que ignore o journal: poderia
emitir preços antigos. Em incidente, suspender o worker e corrigir adiante,
preservando snapshots, auditoria e marcadores. Nenhuma implantação executada.

## Delta cartão 1.6.2

[Operação direta por token, evidências e limites](asaas-direct-card.md). O SDK remoto
foi integrado pelo usuário; nesta etapa não há alteração de SDK/vendor/Perfex.

## Boleto local CNAB240 /139

[Contrato, implementação e evidências](bb-boleto-contract.md). Impressão no lote
antes do download/transmissão, sem alterar arquivo ou gerar pagamento. BB05 passa
a parcial: emissão automática pelo meio de pagamento/recorrência e publicação ao
cliente ainda não foram integradas; CNAB400 e homologação bancária seguem pendentes.
