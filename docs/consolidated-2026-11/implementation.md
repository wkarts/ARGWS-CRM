# Consolidação de 6/11/2026 — implementação e lacunas

**Entrega parcial em draft. Não atende integralmente o consolidado e não está pronta para merge/deploy.**

Estado atual: a referência funcional é Asaas **1.5.8**, adaptada ao CRM multiempresa; o marcador próprio do CRM avança para **2.0.6/206**, sem renumerar migrations anteriores. Núcleo4.9.2/492. Os relatos abaixo preservam a evolução histórica e não substituem os testes do SHA final.

A sucessão agora mantém o principal pago no grupo anterior e recria somente o saldo elegível. Recibos, vínculos e provas originais permanecem imutáveis; o novo grupo não recebe cópias desses pagamentos. A revisão exibe separadamente valor retido e saldo. Somente parcelas excluídas ou integralmente revertidas entram novamente na obrigação, com conservação exata dos centavos e uma única tentativa de envio. Acesso ao reembolso explícito da parcela paga histórica permanece sujeito às permissões atuais.

Continuam incompletos: reversões parciais sucessivas e redistribuição revisada do saldo reaberto por reembolso posterior de principal retido; aceites funcionais/visuais integrais R01–R11; homologação externa. Cartão com trânsito de dados continua aguardando decisão específica. Não houve expansão da SDK, alteração do módulo Perfex, merge, deploy ou transação real.

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
| R01 Propostas | Adaptadores nativos; matriz AC01–06 | Paridade de ações e comparação 4.0.2 completa não comprovadas | Formulário usa apresentação de status nativa, sem helper legado ausente | Views reais MySQL:75 verificações de Orçamentos/Propostas; criação/replay/listagem/preview autenticados locais passaram; paridade completa ainda pendente |
| R02 Marca/PWA | `NativePwaIdentity`, shell nativo | Browser/PWA de todas as empresas e estados | Preservado | `tests/native_company/presentation.php` |
| R03 Orçamentos | `NativeCompanyEstimateForm`, matriz AC08–13 | Paridade integral e conflitos documentados | Corrigidas chamadas a helpers de status ausentes no formulário e no resumo da listagem | MySQL: formulário36, views75, resumo8; HTTP local autenticado: criação, replay, preview, aceite e conversão100.00 passaram; suíte CI completa e paridade integral ainda pendentes |
| R04 Faturas | `CompanyInvoices`, `NativeCompanyInvoiceCommands` e snapshots | Edição de emitidas não equivale ao legado; ciclo Asaas completo pendente | Preservado | Testes Asaas financeiros delimitados abaixo |
| R05 Recorrências | CompanyInvoiceRecurring + Modes | Validação autenticada integral continua pendente | Journal futuro consumido pelo emissor e leitor | SQLite/MySQL + corrida de processos; HTTP local criou/emitiu sem envio/pausou/retomou série sintética |
| R06 Pagamentos | Escritor nativo e contexto | Causa do travamento original não reproduzida; paridade integral pendente | Preservado; driver autenticado agora exige tabela/AJAX/busca sem processamento preso | HTTP local principal e empresa2: página, tabela POST e busca200, zero erros JS; não reproduziu o travamento. Mesmo driver integrado à suíte A/B, CI pendente |
| R07 Créditos | `NativeCompanyCreditsController` e ledger | Conflitos de edição/reabertura da matriz anterior | Preservado | Controller MySQL:118 cumulativas; checkbox de linha corrigido com reprodução PHP/CSS/Chromium desktop/mobile; homologação completa pendente |
| R08 Itens | Catálogo próprio | Paridade geral de Itens não encerrada | Vínculo revisado por ID em recorrências; sem equivalência por descrição | Item estrangeiro e ausência de revisão recusados |
| R09 Todo Vendas | Inventário e adaptadores da PR61 | Cobertura integral de módulos/ações não comprovada | Asaas em pagamento nativo | Testes de view/controlador/browser Asaas |
| R10 Outras áreas | Controllers nativos e contratos globais da PR61 | Áreas NA01–NA09 e propriedade das integrações continuam na matriz anterior | Busca Unicode de leads corrigida sem mudar dados; nenhum módulo global aberto em secundária | MySQL:53 verificações de metadados, mais28 de diretório e31 de controlador; seletor/modal de Contratos corrigido e validado com view/CSS/Chromium; navegação autenticada integral ainda pendente |
| R11 Empresa ativa | `CompanyRequestScope`, `IssuerResourceAccess`, runtime settings | Cobertura universal não comprovada | Seleção Asaas conserva lease/ACL; exportação API bloqueada | Native settings controller + secret controller A/B |
| BB01 Configuração | ModuleStore e ApiService existentes | Homologação HTTP autenticada pendente | Rota settings?group=payment_gateways&gateway=banco_brasil, shell nativo, formulários existentes | View PHP real em Chromium; comandos administrativos PHP |
| BB02 API/240/400 | Perfis e codecs separados | Perfis bancários não suportados continuam recusados | Guias API/CNAB240/CNAB400 com contratos reais e vínculo comum | Chromium: guias, campos e declarações explícitas |
| BB03 Parâmetros reais | Conta, convênio7, carteira17, variação, faixa e perfis existentes | Outros convênios/carteiras exigem contrato/codec verificado; não foram inventados campos | Configuração de conta/layout/faixas/API na mesma tela | Módulo137 tracking/upgrade em MySQL; sem homologação bancária |
| BB04 Operação | Remessa/retorno, arquivos e conciliação existentes | Homologação bancária pendente | Menu operacional preservado; configuração movida e linkada | Admin PHP mantém formulários operacionais e CSRF |
| BB05 Boleto antes transmissão | Origination + CnabBoleto139 + preparação400/140 | HTTP autenticado e homologação bancária pendentes | Emissão canônica e recorrente240/400 atômica142 com instrução explícita; boleto por snapshot antes da transmissão e publicação explícita no portal143; [contrato](bb-invoice-issuance.md) | MySQL277, concorrência274, Chromium físico; não homologado |
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
| ASA04 Ciclo faturas | Origem, operações remotas e reconciliação | Delete/cancel/reactivate/manual parcial/HTTP incerto exigem matriz ponta a ponta | Cancelamento163, recriação PIX/BOLETO165 e composição pós-reembolso167 com prova completa dos predecessores; parcial manual168 com consulta canônica e recibo nativo; integral manual169 por política explícita e comando de envio único; edição nativa170 de PIX/BOLETO elegível e exclusão lógica com remoção remota; cartão/delete físico continuam pendentes | MySQL22 adicionais + corrida6; gerações/fluxo166:69 + pós-reembolso167:30 checks sob128MiB; parcial168:30; integral169:35 + controlador8; edição170:38 + controlador8; runner302 com171; [limites](asaas-invoice-lifecycle.md) |
| ASA05 Observabilidade | Eventos, jobs, provas e revisão existentes | Operações relacionadas a cada evento não homologadas integralmente | Guias para recursos existentes | View/controller; sem efeito financeiro real |
| REA01 Tela/filtros | Rota nativa Faturas recorrentes | Homologação autenticada pendente | Tela única, filtros item/meio, grid e múltiplas regras; busca usa o nome canônico do catálogo | View PHP/Chromium; handler com SQL real; HTTP local autenticado de vínculo/simulação/aplicação |
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
antes do download/transmissão, sem alterar arquivo ou gerar pagamento. O checkpoint139
foi ampliado por140–142: CNAB400 e emissão canônica/recorrente agora estão
implementados e testados em MySQL, conforme as linhas BB05–BB07 acima.
Publicação explícita no portal acrescentada por143, com30 verificações SQL novas
e impressão sob CSP em Chromium; ver [contrato e limites](bb-cnab-portal.md).
Validação HTTP autenticada dessa emissão/publicação e homologação bancária continuam pendentes.

PIGE111 acrescenta diagnóstico explícito de conexão já habilitada pelo contrato remoto existente, com revisão/ACL antes e depois do HTTP. Não ativa conexão desativada nem substitui homologação bilateral real. Ver [contrato](pige-discovery.md).

### Correções verificadas após75fb7ac4

Banco do Brasil1.4.4/144 preserva143 e trata a espécie CNAB não suportada como rejeição422 com rollback integral, em vez de erro503. MySQL real:32 checks de emissão automática após276 de origem e35 de retorno; HTTP autenticado local: rejeição sem consumir número e emissão válida com replay. Aceite de orçamento usa o menu nativo no driver; guia Transações é aberta antes da remoção Asaas. CI A/B e demais falhas de tarefas/contratos continuam pendentes.

### Correções verificadas após4ec09a47

Portal de orçamento/proposta carrega explicitamente o validador de campos nativos, com renderer TCPDF em processo novo (3 checks) e suítes de portal SQLite/MySQL (171 cumulativos por motor). Impressão CNAB240/400 admitida somente por POST protegido, com172 checks de contexto e boleto240 aberto pelo botão original no navegador autenticado. Categoria inline de Despesas usa formulário serializado no transporte protegido;12 testes Node e POST200 autenticado com campos preservados. Reembolsos no driver abre a guia nativa antes das ações. Estas evidências locais não substituem o CI final A/B nem homologação bancária. A matriz de navegação local e seus limites está em [navigation-evidence.md](navigation-evidence.md).

### R01 — composição do conteúdo da Proposta no PDF

`CompanySalesPdf` passa a incluir assunto e HTML do snapshot nativo, com tabela na posição de `{proposal_items}` ou ao final quando ausente. `NativeCompanyContentHtml::forPdf` aplica o sanitizador existente com imagens/recursos externos proibidos no PDF. Regressão de processo independente usa TCPDF e extração real de texto: reproduziu a omissão e passou após a correção. Imagens do corpo, autosave e demais conflitos de paridade continuam explicitamente fora deste delta; nenhuma alteração financeira ou de histórico.

### Continuidade 178 — ciclo de vínculos legados

Ver `asaas-156-adaptation.md`, seção1.7.8. Remoção com recuperação de DELETE
incerto, reembolso do principal revisado e edição de cobrança que cobre toda a
fatura preservam a evidência inicial e a revisão explícita. Grupos/parcelamentos,
alocações parciais editáveis e recriação legada ainda exigem implementação.
Não há merge, deploy, chamadas financeiras reais ou ampliação do SDK neste delta.

### Continuidade179 — sucessores de vínculos legados integrais

Ver `asaas-157-reference.md`. Recriação preserva política manual, origem revisada
imutável e cadeia completa entre gerações. Conciliação explícita valida essa
cadeia; não habilita baixa automática. Alocações parciais e grupos permanecem
pendentes. Migration179 NOOP; histórico anterior intacto.

### Continuidade180 — edição de sucessor legado integral

A edição explícita valida a cadeia revisada e conserva autoridade manual.
Journal de edição permite recuperar PUT incerto sem repetir envio e mantém
snapshots originais. Testes cobrem edição seguida de outra recriação/recebimento.
Migration180 NOOP; alocações parciais e grupos continuam pendentes.

### Continuidade181 — consulta de parcelamentos por conta

O monitor nativo consulta grupo e parcelas por GET, valida o conjunto completo e
apresenta os IDs individuais. Não aplica vínculo nem baixa. Matriz, limites,
contratos oficiais e resultados: `asaas-installment-groups.md`. Associação e ciclo
financeiro dos grupos continuam pendentes; não equivaler consulta a conciliação.

### Continuidade182 — associação e baixa por parcela

Identidades imutáveis do grupo/membros, confirmação por digest e recebimento
nominal pelo escritor nativo. Política manual, rollback e idempotência preservados.
Ver `asaas-installment-groups.md` para formato, evidências e ciclo ainda pendente.

### Continuação183 — ciclo dos membros de parcelamento

| Requisito | Existente | Lacuna/implementação | Evidência |
|---|---|---|---|
| Exclusão de parcela sem perder recebimentos irmãos | Associação e recibo individual182 | Serviço e grade183; intenção única, DELETE individual, GET de confirmação | `tests/companies/asaas_installment_removal.php`, transporte simulado |
| Concorrência e resultado incerto | Mutex e journals nativos | Intenção impede baixa concorrente; retry nunca repete DELETE; revogação bloqueia novas ações | MySQL, reentrada durante transporte, perda de resposta |
| Isolamento e confirmação administrativa | Contexto/ACL/CSRF nativos | Ações remove/refresh no controlador; confirmação individual | Controller real e Chromium desktop/mobile |
| Edição/reembolso/recriação de grupos e alocações parciais | Ciclo avulso anterior | Continua pendente; exclusão individual não equivale a ciclo integral | Sem alegação de conclusão |

Migration183 NOOP; core4.9.2 preservado. Cartão com trânsito de dados aguarda decisão;
SDK sem ampliação. Sem merge/deploy/operações financeiras reais.

### Continuação184 — remoção conjunta com revisão e retomada

| Requisito | Código existente | Implementação | Evidência/limite |
|---|---|---|---|
| Remoção conjunta preservando parcelas recebidas | Exclusão individual183 | Seleção canônica, digest revisado, intenção auditada antes de dispatch | MySQL: preview sem DELETE, pagamento novo invalida revisão |
| Resultado parcial e idempotência | Journal por membro | Conjunto imutável, retomada por GET, resultado persistido por parcela | Falha de resposta, reentrada concorrente, recibo irmão preservado |
| Configuração e operação nativas | Grade de parcelas | Revisão e confirmação no mesmo monitor, CSRF/ACL/contexto | Controller21 guards; Chromium10 cenários |
| Edição/reembolso/recriação de grupos e ciclo das alocações | Fontes avulsas e snapshots anteriores | Ainda pendente; não liberar por remoção de guards | Nenhuma conclusão integral alegada |

Migration184 NOOP. Remoção conjunta por membros não equivale a DELETE do recurso
installment nem a operação remota atômica. Sem alterações de SDK ou fluxo de cartão.

## Atualização de referência1.5.8 / módulo CRM1.8.5

A nova referência funcional é a release Asaas1.5.8, inventariada por arquivo em `asaas-158-source-manifest.json`. A matriz de adaptação e os limites estão em `asaas-158-adaptation.md`. Primeira capacidade adaptada nesta etapa: exportação paginada das cobranças da conta/ambiente verificados, pela tela nativa, com ACL/CSRF, arquivo privado e descarte em falha. Não representa adaptação completa dos relatórios, Pix Automático, SDK0.3.0, grupos ou R01–R11. Migrations e isolamento anteriores preservados; migration185 NOOP.

### Dependência da referência1.5.8 —186
SDK0.3.0 e dependências exatas do lock oficial incorporadas sem alterar o repositório SDK. Compatibilidade de534 assinaturas preservada;23 suites PHP do workflow passaram, mais365 assertions do runner e regressões históricas48/101. Adaptação Pix/relatórios/grupos continua pendente; instalar a SDK não concede autoridade financeira. Ver matriz `asaas-158-adaptation.md`.

### Permissões específicas de relatórios187

Catálogo nativo inclui `asaas_reports.view/export` por empresa. Exportação não exige nem concede settings.edit; exige grants de leitura/exportação e os acessos de consulta atuais. Nenhum grant automático ou seed. Isolamento, ator restrito, admin global sem concessões e revogação durante HTTP cobertos no MySQL; demais relatórios/monitor/Pix e R01–R11 não declarados completos.

### Pix Automático188 — consultas nativas com isolamento

A guia Pix Automático consulta autorizações existentes por página ou ID sob empresa, conta, ambiente e revisão explícitos. Exige permissão de relatórios da empresa além das permissões de configuração/leitura e revalida durante a chamada. O DTO exclui cliente, QR e dados bancários. Nenhuma adesão, emissão ou baixa é ativada por observar `ACTIVE`. Migration188 NOOP; etapas de consentimento, jobs, cron e webhook Pix permanecem trabalho pendente, assim como a paridade completa da referência1.5.8.

### Relatórios da conta189

Catálogo completo de20 relatórios da fonte1.5.8 adaptado na configuração nativa, com consulta e CSV, permissão específica de conta inteira, verificação de carteira/ACL/revisão em cada página e auditoria da empresa. Sem gateway/log global, concessão automática ou escrita financeira. CSV inclui empresa/conta/ambiente; consulta não produz snapshot remoto. Rota delegada fora de Configurações e homologação de disponibilidade dos20 recursos continuam pendentes.

### Asaas190 — eventos Pix autenticados e fila de observação

Referência funcional1.5.8; adaptação do contrato de eventos e GET de instruções documentados pelo Asaas. Callback autentica a rota/token da conta antes de interpretar Pix; registra somente IDs mínimos, empresa, conta, carteira, ambiente e digest. Evento repetido é idempotente; conteúdo conflitante exige revisão. Não usa externalReference ausente para inferir fatura.

Migration190 aditiva cria inbox, jobs, observações e configuração explícita do observador. Nenhuma adesão ou concessão é criada. Worker por conta/ator, desabilitado por padrão, revalida permissões e revisão antes/depois de HTTP; lease120s, até5 tentativas GET, intervalo60s e limite por rodada. Credencial é conferida por carteira; instrução deve confirmar autorização/paymentId. Provas canônicas são mínimas e append-only. Estado ACTIVE/DONE nunca emite cobrança nem baixa fatura. A interface nativa permite habilitar/desabilitar somente observação e consultar evento pendente com contexto/CSRF.

**Pendências preservadas:** adesão/consentimento Pix no portal, intenção durável de criação, jobs financeiros e vínculo do primeiro Pix ainda não implementados. A fila190 é observacional, não comprova esses requisitos. Reembolso/edição/recriação de grupos e ciclo integral de alocações continuam pendentes; prova imutável do grupo ainda precisa ser integrada ao reembolso. Não há homologação Asaas/bancária. Cartão com trânsito de dados permanece aguardando decisão.

Deploy futuro pelo runner nativo após backup, sem executar nesta tarefa. Falha de DDL/tracking não promove marcador; retry valida esquema parcial. Rollback de código não deve remover tabelas, provas ou reduzir marcador; suspender observador e corrigir aditivamente.

### Asaas191 — reembolso explícito por parcela Pix com identidade histórica

`CompanyInstallmentIdentity` valida, sob transação nativa, raiz e todos os membros do grupo, empresa/conta/ambiente, snapshots, políticas manuais, provas de associação e respectivas auditorias. Não depende de conta habilitada nem da permissão atual do autor para ler histórico; não concede autoridade para operar. A intenção de reembolso inclui essa identidade imutável, nominal/vencimento da parcela e vínculo com o recibo original. Toda operação nova continua exigindo ACL atual, conta válida, revisão e concessão explícita de reembolso.

O normalizador só aceita parcela Pix quando `installment` e `installmentNumber` correspondem à prova capturada. Usa o endpoint por cobrança `payments/{id}/refund`, nunca o estorno do recurso de parcelamento de cartão. O escritor de declaração integral confere novamente o grupo/provas/auditorias, o recibo e a observação canônica. Integral refere-se ao recibo da parcela: não altera total da fatura nem apaga recebimentos irmãos. Retry não repete POST; a declaração local permanece explícita e exige evidência independente. Histórico permanece verificável após desativação da conta.

Página nativa de grupos permite consultar histórico sem tratar revogação posterior como corrupção da prova. Parcela Pix recebida possui acesso ao painel de reembolso por ID local exato, condicionado à permissão. Ações mutantes continuam com as verificações anteriores de estado/autoridade.

Referência oficial: [estornar cobrança](https://docs.asaas.com/reference/estornar-cobranca). Sem operação real, alteração no SDK ou repositório Perfex. Migration191 NOOP, sem backfill/grants. Ainda pendentes: edição/recriação de grupos e ciclo integral de alocações/reembolsos parciais. Observar agregado parcial não inventa identidade de um estorno individual.

### Asaas192 — contrato estrito de adesão Pix (integração financeira ainda pendente)

Validação tipada do pedido MANUAL e da resposta de autorização, vinculando cliente, contrato, frequência, data, ID remoto e conciliação. Estados terminais não ressuscitam, ACTIVE não retorna a CREATED, datas inválidas não são normalizadas silenciosamente, e somente CREATED entrega QR no DTO interno. Nenhum QR é persistido por este contrato. Transporte limitado ao endpoint fixo, sem retry automático, mantém valores decimais exatos incluindo originalValue.

O contrato de identidade do primeiro Pix exige cobrança, transação CREDIT, cliente, valor e conciliação correspondentes. Não exige externalReference inexistente; quando presente, exige correspondência. Essa prova não equivale a recebimento nem a consentimento recorrente: o primeiro pagamento pode existir mesmo com adesão REFUSED. O chamador ainda deve autenticar conta/empresa/ambiente antes de qualquer consulta.

Não há rota pública ou cron chamando o novo POST. Intenção durável, consentimento, reserva da fatura, recuperação de resultado incerto, cifragem do QR e jobs financeiros permanecem trabalho de implementação. A migration192 é NOOP, sem dados/grants. Este incremento não completa a adesão Pix nem sua homologação.

### Asaas193 — reserva real da fatura e recuperação canônica

`CompanyService::reservePixAuthorization` grava atomicamente origem ativa `pix_mandate`, snapshot, chave idempotente, contrato determinístico, política manual, prova e auditoria. Usa o mesmo lock nativo e índice de origem ativa dos escritores de fatura. Retry com dados iguais retorna a mesma origem; outro pedido ou emissão comum não pode assumir essa reserva. Recebimento manual e edição/exclusão comuns continuam sujeitos ao bloqueio de origem de provedor. Falha tardia de auditoria desfaz a reserva inteira.

`recoverPixAuthorization` consulta a carteira, o cliente canônico e uma autorização pelo ID informado, usando somente a credencial da conta/ambiente reservados. Revalida ACL, empresa ativa, revisão, snapshot, contrato e histórico antes/depois do HTTP. Recuperação repetida não duplica prova; resposta divergente ou fora de ordem é recusada. Guarda somente identificação/status/conciliação/expiração, descartando o QR. Não repete POST nem converte ACTIVE em consentimento, job financeiro ou recebimento. A reserva continua bloqueando emissão concorrente.

Migration193 NOOP; utiliza journals e constraints existentes sem editar migrations anteriores. Não há rota pública ou botão expondo a reserva ainda: consentimento no portal, claim de envio, entrega privada do QR, recuperação automática por busca do contrato, jobs futuros e escritor financeiro do primeiro Pix continuam pendentes. Não existe liberação automática da reserva em timeout. Não habilitar esse backend diretamente na interface enquanto o ciclo não estiver integrado. Isso é implementação de reserva/recuperação, não adesão completa nem homologação.

### Asaas194 — descoberta automática e consentimento privado com claim único

A recuperação aceita ID vazio para buscar o contrato reservado na conta autenticada. Faz no máximo cinco páginas de50, verifica paginação/total estável/IDs únicos, revalida autoridade antes/depois de cada consulta e confirma o candidato por GET individual. Ausência, conflito, limite e timeout nunca liberam a reserva nem autorizam novo POST. Identidade já conhecida usa GET por ID, sem nova descoberta. Prazo lógico45s é verificado antes/depois das chamadas, que mantêm o timeout limitado do transporte.

`CompanyPixConsent` compõe a identidade real do portal com o mesmo banco/prefixo. Exige contato privado e verificado, permissão financeira explícita, conta/revisão válidas e autoridade atual do ator que reservou a fatura. Revisão inclui contrato/snapshot, empresa, fatura, contato, valor, frequência, data e versão/texto do consentimento. Apenas booleano true é aceito; strings não são convertidas em consentimento. Prova e auditoria gravam atomicamente o aceite explícito, sem chamada bancária ou baixa.

`claim` exige o consentimento exato sob lock e grava uma única intenção pix_send antes de mudar a reserva para sending. Retry devolve recovery_required e não concede outro envio. Autorização já observada devolve authorization_observed sem novo claim. Falha de auditoria desfaz o claim. Revogação de contato, permissão, ator ou mudança de revisão impede o uso do consentimento anterior.

`dispatch` verifica carteira e cliente canônico, revalida a identidade/revisão, obtém claim único e envia o pedido tipado. Retorno incerto fica registrado sem corpo/exception/QR do provedor; mesmo revogação durante HTTP não apaga a evidência da operação já autorizada. Antes de entregar qualquer resultado, a autoridade do portal é revalidada. Resposta positiva é confirmada por GET; retry consulta o estado e nunca repete POST. A busca automática recupera resultado incerto pelo contrato. Nenhum QR é retornado por este executor e nenhuma fatura é baixada.

**Integração ainda parcial:** os serviços de consentimento, claim, envio e recuperação não estão expostos em formulário/rota pública de adesão. Claimed não significa sucesso bancário. Ainda faltam UI/HTTP, QR privado, revogação/cancelamento completos, jobs financeiros e escrita do primeiro Pix; permanecem grupos/alocações e R01–R11. Migration194 NOOP, sem backfill, grants ou chamadas externas de migration. Testes do executor usam exclusivamente transporte sintético.

## Incremento 195 — consentimento nativo e recebimento inicial Pix

A rota nativa da fatura apresenta os termos da reserva Pix para o contato verificado com permissão financeira. O POST exige CSRF, checkbox literal, revisão atual e vínculo privado; registra um único aceite e redireciona com 303. GET não concede consentimento. A apresentação distingue aceite, confirmação bancária e pagamento; não envia solicitação ao provedor. `CompanyPixConsent::instrument` recupera o payload por GET autenticado, revalida contato/conta/revisão e validade temporal, sem persistir QR nem incorporar imagem arbitrária. Esse método ainda não foi exposto como ação de pagamento no portal.

`CompanyPixInitialPayments` consulta carteira e cliente antes de buscar cobrança e transação Pix; exige identidade por conciliação, cliente, valor e transação CREDIT, com segunda leitura da cobrança. Não inventa externalReference quando ausente. Somente RECEIVED, sem sinais de estorno/split/antecipação/ajuste, gera prova candidata. ACTIVE/DONE/CONFIRMED não são usados como liquidação. A autorização pode estar REFUSED e o pagamento inicial comprovado continuar elegível.

`CompanyPixInitialReceipt` compõe o escritor financeiro nativo com prova recente, ACL atual, conta/ambiente, snapshot, bloqueio de eventos adversos e chave canônica idempotente. Falha tardia desfaz pagamento e vínculo; a origem pix_mandate e os históricos são preservados. A tarifa observada não é lançada como despesa automaticamente. Migration195 é NOOP, usa journals existentes, sem backfill ou permissões novas.

Continuam pendentes: ligar adesão/envio/QR e conciliação inicial aos fluxos operacionais completos; jobs financeiros futuros com calendário/consentimento/recuperação; substituição segura de cobrança anterior; ciclo completo de grupos/alocações e aceites R01–R11. O método de recuperação de QR e o adaptador de recebimento não demonstram, isoladamente, conclusão ponta a ponta. Cartão continua aguardando decisão de trânsito de dados; SDK sem ampliação. Sem merge/deploy/operações reais.

Contrato consultado: https://docs.asaas.com/reference/recuperar-uma-unica-transacao — GET `/v3/pix/transactions/{id}`, além da fonte funcional1.5.8. A lista de caminhos do transporte foi ampliada apenas para esse GET com ID limitado.

## Incremento 196 — operação nativa Pix e fila financeira inicial

A tela nativa Asaas permite reservar uma fatura com conta/revisão, cliente, frequência e data explícitos. O portal do contato verificado conecta o aceite existente ao envio único, recuperação canônica e QR privado. O QR é gerado em memória pelo TCPDF já distribuído; não incorpora SVG/imagem externa, não grava payload em sessão, cookie ou journal. Timeout mantém intenção incerta e permite apenas recuperação por GET. Cache privado, CSRF e revalidação de identidade permanecem obrigatórios.

A operação administrativa separa consulta canônica do primeiro pagamento de sua confirmação financeira. A nova política de jobs é opt-in por empresa/conta/ambiente e ator, independente do worker observacional. Ativação começa após o último evento existente, sem backfill. Apenas PAYMENT_RECEIVED autenticado entra na fila; carteira é autenticada antes do GET da cobrança. A correspondência exige contrato, transação, cliente e valor exatos, não identificador global. Até100 reservas candidatas por conta são examinadas; excesso exige revisão. Workers são percorridos por atualização para evitar retenção permanente nas primeiras20 contas.

Lease, revisão da política/conta, ACL e origem são revalidados dentro do escritor nativo, inclusive após inserção. Dois processos não duplicam pagamento; falha tardia desfaz a escrita e retry preserva a chave canônica. Erros de autoridade/identidade exigem revisão; leitura/armazenamento incertos têm tentativas limitadas. A fila não envia cobranças futuras nem lança tarifas como despesa.

Migration196 adiciona somente tabelas de política/fila, com constraints validadas; preserva todas as migrations anteriores. Desabilitar a política impede novas baixas, mas não desfaz recebimentos confirmados. Rollback operacional exige suspender worker e corrigir para frente; não reduzir marcador nem apagar journals. Backup consistente e validação de instalação continuam requisitos de implantação, não executada aqui.

Corrigida regressão de compatibilidade: o painel opcional verifica versão194 antes de consultar colunas recentes numa instalação legada1.5.1. Continuam pendentes geração financeira recorrente Pix, revogação/cancelamento completos e substituição de vínculo anterior; ciclos restantes de grupos/alocações, paridade integral1.5.8 e aceites R01–R11. Cartão continua aguardando decisão sobre trânsito de dados. Isso não constitui conclusão do consolidado ou homologação bancária.

## Incremento197 — revogação local e cancelamento Pix

O pagador que aceitou os termos pode revogar pelo portal nativo, com contato verificado, permissão financeira, revisão exata, confirmação e CSRF. A retirada continua disponível após pagamento, sem depender de funcionário antigo ativo ou conta habilitada. Grava prova/auditoria imutáveis; repetição converge no mesmo registro. O aceite anterior não pode reativar nem autorizar novo envio/QR. Pagamentos anteriores continuam preservados.

O operador com permissões de exclusão remota e emissão pode revogar e cancelar pela configuração nativa. Antes de qualquer HTTP grava o bloqueio local. Consulta carteira, cliente e contrato exatos; somente autorização ACTIVE pode receber DELETE neste fluxo. Intenção sob lock concede um único envio. Timeout ou estado ainda ativo nunca autoriza repetição do DELETE; novas tentativas fazem recuperação GET. Somente CANCELLED canônico é apresentado como cancelamento confirmado. Uma autorização CREATED, ausente ou inconclusiva permanece bloqueada localmente e exige nova consulta/revisão; não há cron de cancelamento automático nesta etapa.

A revogação local não estorna valores nem afirma cancelar instruções já agendadas. O recebimento inicial comprovado pode continuar sendo conciliado. A retomada exige novo consentimento/autorização; a substituição do vínculo ainda não foi implementada. Migration197 NOOP, sem DDL, backfill, grants ou chamadas externas. Preservar journals e marcador em rollback; suspender operações e corrigir aditivamente.

Contratos verificados: [cancelamento](https://docs.asaas.com/reference/cancelar-uma-autorizacao-pix-automatico) e [implementação](https://docs.asaas.com/docs/pix-automatico-implementacao). O segundo exige pixAutomaticAuthorizationId para cobranças manuais futuras e janela de2–10 dias úteis. O CRM ainda precisa de vínculo revisado autorização↔série, reserva única de ciclo, calendário por conta e recuperação financeira específica; essa é lacuna de implementação, não bloqueio externo presumido. Grupos/alocações e R01–R11 permanecem parciais.

## Incremento198 — cobranças Pix de ocorrências nativas

O vínculo revisado une autorização ACTIVE, consentimento vigente, primeiro recebimento confirmado e recorrência nativa da mesma empresa/cliente/moeda/valor/periodicidade. A revisão congela conta, revisão, calendário bancário e corte das faturas elegíveis. Confirmação explícita não emite e não habilita cron. Uma alteração do contrato da recorrência ou do valor bloqueia nova emissão até revisão; não altera Produtos, faturas emitidas ou calendário nativo.

A configuração Asaas oferece revisão/confirmação, consulta do vínculo, emissão explícita de ocorrência, consulta/recuperação e baixa nativa confirmada. Só aceita fatura canônica de uma execução concluída após o corte, entre2 e10 dias úteis antes do vencimento. Reserva de ciclo, origem financeira e prova histórica são atômicas sob os locks nativos. O POST usa pixAutomaticAuthorizationId; resposta sem esse campo exige instrução canônica única vinculada ao pagamento e autorização exatos. Timeout nunca repete POST: recuperação consulta referência imutável, carteira, cliente, fatura e autorização. Cada recebimento passa pelo escritor nativo e é idempotente.

Interromper o vínculo bloqueia novas emissões; não cancela cobranças já enviadas nem revoga o contrato bancário. Retirada do consentimento durante POST impede falso sucesso, preservando resultado técnico recuperável. A consulta de cobranças históricas permanece possível após interrupção/retirada. Migration198 NOOP; journals existentes, sem backfill, permissões ou chamadas externas. Rollback não apaga provas nem reduz marcador; suspender uso e corrigir aditivamente.

Ainda pendentes: worker automático das cobranças futuras com ativação própria, substituição completa de autorização, fluxos restantes de grupos/alocações, paridade integral1.5.8 e R01–R11. Esta etapa implementa emissão operacional explícita, não automatização total nem homologação financeira. Cartão continua aguardando decisão específica; SDK sem ampliação.

## Incremento199 — processamento automático por vínculo Pix

Política separada, desabilitada por ausência de registro, exige confirmação explícita de emissão e recebimento sob o responsável da recorrência. Confere vínculo imutável e permissões atuais settings.edit/invoices/customers/payments. O cron não usa gateway global nem empresa de sessão para escolher contas: cada registro carrega empresa, origem e responsável; escopos são novamente verificados nos serviços. Seleção rotativa por vínculo e cursor de fatura, limite20/45s por chamada, próxima tentativa e lease de300s evitam sobreposição e repetição imediata.

Cada ocorrência utiliza a reserva/POST único198. Origens já enviadas são somente consultadas, incluindo resultados incertos. RECEIVED canônico passa pelo escritor nativo, com lease/política/revisão/ACL revalidados dentro da transação financeira antes e depois da inserção. Desabilitar durante HTTP invalida o lease; resultado técnico permanece recuperável e o fechamento da tentativa não sobrescreve a política nova. Pausa, retirada ou mudança do contrato da série bloqueiam automação; consultas/baixas históricas explícitas continuam disponíveis198.

Migration199 adiciona uma tabela de políticas/leases por origem, com FK e índice de próximas tentativas. Sem seed, ativação, backfill ou grants. Implantação futura exige runner/backup/tracking e revisão explícita de cada vínculo. Suspender política não estorna recebimentos; preservar tabela/journals e corrigir aditivamente, sem reduzir marcadores.

Substituição completa de autorização, ciclos restantes de grupos/alocações e aceites integrais R01–R11 ainda pendentes. Cartão aguarda decisão, SDK sem ampliação. Sem merge, deploy ou operações financeiras reais.

## Incremento200 — substituição revisada da autorização Pix

A configuração nativa permite revisar e confirmar a substituição do vínculo de uma recorrência. Antes de qualquer consulta da nova autorização, valida ambas as origens na empresa, vínculo anterior e prova canônica CANCELLED, com retirada local. Exige nova adesão, consentimento próprio, ACTIVE e primeiro recebimento novo; não reaproveita consentimento ou baixa antiga. A revisão informa origem/conta anterior e sucessora, recorrência, valor, periodicidade e calendário. O novo vínculo conserva o corte de faturas futuras.

Confirmação usa uma única transação/mutex nativo para interromper vínculo antigo, confirmar novo, invalidar/desabilitar worker antigo e gravar provas pareadas de sucessão/auditoria. Falha tardia reverte todas essas alterações. Dois confirmadores convergem no mesmo sucessor; revisão/pares diferentes falham. Nenhuma chamada bancária ocorre na confirmação, nenhuma fatura/recebimento antigo é reescrito, e a nova automação continua exigindo ativação separada. Consultas históricas preservam as origens anteriores.

Asaas2.0.0 é a sequência nativa200 do CRM, não versão nova do módulo upstream de referência1.5.8. Migration200 NOOP, journals existentes; sem seed/backfill/grants ou ativação. Para implantação futura: backup/runner/marcador200 e revisão das autorizações. Rollback não desfaz cancelamento bancário nem pagamentos; preservar histórico e corrigir aditivamente. Sem merge/deploy/operações reais.

Este fluxo não dispensa a adesão e o pagamento inicial da nova autorização, não cancela cobranças antigas por inferência e não transfere automaticamente políticas. Ciclos restantes de grupos/alocações, paridade integral1.5.8 e R01–R11 seguem pendentes. Cartão aguarda decisão específica.

## Incremento201 — edição revisada de parcelas e alocações

A guia Monitor permite editar valores e vencimentos das parcelas pendentes PIX/BOLETO de um grupo já vinculado. A soma exata em centavos permanece igual; a fatura emitida, seu total, calendário, snapshots e vínculos originais não são reescritos. Parcelas recebidas ou com intenção de remoção/reembolso não podem ser alteradas. Créditos e pagamentos sem vínculo com o grupo impedem a operação. Isso implementa redistribuição do principal dentro do grupo, não alteração irrestrita do total da fatura ou edição de cartão.

A revisão consulta as cobranças canônicas e congela antes/depois, empresa, ator e revisão da conta. A confirmação grava plano e auditoria antes de qualquer PUT. Cada parcela tem claim de envio único sob mutex nativo. Timeout não autoriza novo PUT: a retomada consulta GET e só envia membros ainda sem claim. O grupo permanece bloqueado para conciliação/remoção enquanto houver resultado parcial. Somente confirmações canônicas recentes de todos os membros permitem aplicar a revisão efetiva. Não há atomicidade remota presumida nem compensação financeira automática.

O journal append-only valida hashes, auditorias e sequência sobre os vínculos originais. Baixa usa o nominal aprovado; reembolso captura a prova das edições específicas da parcela, preservando a identidade de irmãos já recebidos. A edição continua sujeita às permissões atuais e ao contexto/CSRF. O formulário apresenta revisão e confirmação desmarcada; um resultado incerto não aparece como sucesso.

Contrato: [PUT de cobrança existente](https://docs.asaas.com/reference/atualizar-cobranca-existente), consultado em7/11/2026. Alterar valor/vencimento pode gerar webhook e notificações do provedor. Não muda o cliente nem envia dados de cartão. Asaas2.0.1/201, migration NOOP: provas/auditorias existentes, sem DDL/backfill/ativação. Implantação futura usa backup consistente e runner nativo. Rollback de código não desfaz PUTs já aceitos: preservar plano/claims e recuperar por consulta; não apagar histórico nem reduzir marcadores.

Ainda pendentes: criação remota de grupos sucessores e seu vínculo histórico, ciclos de alocações parciais fora de grupos e reversão parcial canônica; paridade integral1.5.8 e R01–R11. O total fixo e os membros elegíveis são limites explícitos desta edição, não conclusão dessas pendências.

## Incremento202 — recriação de grupos excluídos sem recebimentos

A configuração nativa revisa quantidade e primeiro vencimento de um novo parcelamento PIX/BOLETO. Exige exclusão canônica de todos os membros anteriores, nenhuma baixa/crédito nativo e nenhuma intenção de reembolso. O total original permanece exato. A confirmação explícita reserva intenção e claim duráveis antes de um único POST. Resposta perdida ou rejeitada não autoriza repetir a criação; a recuperação procura exclusivamente a referência exclusiva derivada da intenção e valida conta/carteira, cliente, grupo, cada parcela, valores e datas. Estado não confirmado continua pendente, sem falso sucesso.

Contrato utilizado: [criar cobrança parcelada](https://docs.asaas.com/docs/criar-uma-cobranca-parcelada), POST payments com installmentCount e totalValue. A divisão em centavos vem da resposta canônica; não se envia installmentValue junto nem se inventa restauração de grupo excluído. Consultas completas e coerentes são obrigatórias. Nenhum dado de cartão transita neste fluxo.

Só após confirmação canônica de todas as parcelas uma transação nativa retira a seleção ativa anterior, cria vínculos novos e grava provas pareadas de sucessão. Falha tardia reverte toda a troca local; o claim remoto permanece recuperável. O journal valida a cadeia inteira, auditorias e provas de exclusão imutáveis. Origens, baixas e snapshots anteriores não são reescritos. O histórico aparece na grade sem botões financeiros de grupo ativo. Recebimento ocorrido remotamente antes do vínculo não gera baixa automática: exige consulta/recebimento nativo separado. Reembolso distingue a referência remota exclusiva da identidade nativa da fatura.

Limites: recriação de grupos parcialmente pagos, reaproveitamento de parcelas pagas, alteração irrestrita do total e reversão parcial canônica não estão concluídos. Os bloqueios são de implementação explícita, não indisponibilidade externa. A paridade integral Asaas1.5.8 e R01–R11 continua aberta; cartão aguarda decisão específica. Sem alterações no SDK ou no repositório Perfex.

Asaas2.0.2/202 é sequência nativa do CRM, não versão upstream. Migration NOOP, sem DDL, backfill, grant ou ativação. Implantação futura requer backup consistente e runner nativo com marcador202. Reverter código não desfaz criação remota: preservar claims/provas/vínculos e recuperar por consulta, sem baixar marcador ou apagar histórico. Nenhum merge, deploy ou operação real foi executado.

## Incremento203 — autoridade de reembolso e sucessão

Antes de reservar, enviar ou confirmar reembolso de uma parcela, a composição privada valida também a cadeia do grupo ativo e suas provas pareadas de sucessão/exclusão. A identidade histórica do membro permanece imutável e a leitura histórica continua disponível; nenhuma prova202 é reescrita. Uma cadeia adulterada falha antes de reservar intenção ou chamar o provedor. Isso fecha uma diferença entre a autoridade de conciliação e a de reembolso dos grupos sucessores.

Asaas2.0.3/203, migration NOOP, sem DDL, seed, backfill ou grants. Preservar journals e marcadores em implantação futura; rollback não desfaz operações financeiras e deve manter o guard de autoridade. Recriação de grupos parcialmente pagos/reembolsados, reversão parcial e paridade integral1.5.8/R01–R11 permanecem pendentes de implementação/validação; não são bloqueios externos presumidos. Cartão continua aguardando decisão.

## Incremento204 — sucessão após devolução integral

A recriação também admite grupo com parcelas integralmente reembolsadas e outras excluídas sem recebimento. Toda parcela recebida precisa de devolução integral canônica e declaração de reversão já registrada pelo escritor financeiro nativo, com recibo/prova próprios. Todos os pagamentos da fatura devem estar integralmente revertidos e o saldo inteiro reaberto. A criação do novo grupo não registra devolução, crédito, recebimento ou despesa por inferência; reembolso parcial ou principal ainda recebido continuam impedindo a operação.

A sucessão congela separadamente a prova de exclusão ou a prova de reembolso junto ao hash do lançamento nativo. A cadeia valida essas provas nas operações posteriores. Eventos novos ou conflitantes no predecessor bloqueiam a continuação para revisão; uma marca de evento adverso sem evento de reembolso correspondente não é aceita. O retry da declaração financeira antiga permanece idempotente após retirar a seleção ativa do grupo. Os recibos e reversões anteriores conservam todos os bytes.

Parcelas da nova geração podem ser recebidas e as pendentes editadas/removidas, sem confundir recibos históricos integralmente revertidos com recebimentos ativos. A interface explica o saldo totalmente reaberto e a diferença entre exclusão e reembolso. Não altera a fatura emitida, calendário, Produtos, conta/ambiente ou dados de cartão.

Asaas2.0.4/204, migration NOOP, sem DDL/backfill/grants. Backup, runner nativo e marcador204 são pré-requisitos de futura implantação. Rollback não apaga devoluções ou cobranças: preservar os pares de sucessão, provas, claims e lançamentos; suspender uso e corrigir aditivamente. Nenhum merge/deploy ou operação financeira real.

Ainda pendentes: sucessão mantendo parcelas efetivamente pagas, reversão parcial canônica e ciclo integral de alocações avulsas, paridade integral Asaas1.5.8/R01–R11 e homologação financeira. O suporte ao grupo com devolução integral não encerra essas lacunas. Cartão aguarda decisão específica.


## Incremento205 — sucessão mantendo principal pago

Recibos históricos permanecem byte a byte; provas de origem e valores ligam as gerações. O grupo sucessor contém somente o saldo elegível. Reembolso explícito da parcela histórica permanece possível e idempotente; não realoca automaticamente o saldo reaberto. Eventos adversos posteriores bloqueiam operações para revisão. [Matriz, evidências e limitações atuais](asaas-158-adaptation.md#205--sucessão-com-principal-pago-preservado).

MySQL:31 verificações novas de sucessão,7 de reembolso histórico,53 de remoção incluindo correção da expectativa de CI204. Runner444; Chromium14 grupos/8 recriação. Provedor exclusivamente simulado. Migration205 NOOP, sem alteração das anteriores. CI do SHA publicado deve ser consultado separadamente; não há homologação nem conclusão integral do consolidado.


## Incremento206 — prova da consulta que confirmou o principal retido

A prova original do recibo continua imutável. A sucessão também congela ID/hash da consulta canônica que confirmou a retenção. Isso permite atualizações legítimas observadas entre recebimento e sucessão sem tratá-las depois como eventos inexplicados; eventos adversos ou posteriores à consulta continuam bloqueados. Links205 existentes conservam sua validação original, sem backfill. Migration206 NOOP. O teste de sucessão inclui evento de atualização posterior ao recibo e anterior à nova consulta; o reembolso histórico continua validado separadamente.
