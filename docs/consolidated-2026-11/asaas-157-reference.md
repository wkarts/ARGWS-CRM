# Referência Asaas 1.5.7 e compatibilidade ARGWS

A instrução posterior do usuário substitui1.5.6 como alvo da adaptação pela
referência1.5.7. As entregas e evidências anteriores continuam históricas, sem
substituir migrations ou descartar o ciclo multiempresa já implementado.

- Anexo identificado pelo trabalho independente: `asaas-module-1.5.7.zip`,
  Library `libfile_bd8cda2295588191bec08ce261a30832`.
- SHA256 informado e verificado na identificação do anexo:
  `8b41bcb00ddedd1ccd67e70099201004ede58215c0e0ff7ba118046db8d9dd74`.
- Repositório somente leitura: `wkarts/asaas-payment-module-perfex-crm`.
- Commit correspondente: `e790c33ade267b73027c167b468f06309d5d61e6`.
- Comparação contra1.5.6: `c3f169e7f571d70dc2accf2fce67bf7b7e5749c7`.

A identificação independente encontrou604 arquivos iguais ao HEAD, exceto três
arquivos gerados pelo Composer e a ausência de testes/CI no anexo. O pacote da
release usa outro empacotamento: não se afirma igualdade de hashes dos ZIPs.
Neste executor a referência foi reconstruída a partir da cópia1.5.6 preservada e
dos18 blobs alterados/adicionados do repositório, todos conferidos contra os SHAs
Git retornados pelo conector. Isso não é uma nova verificação do ZIP da Library.

## Delta e destino

| Delta da fonte | Aplicação ao CRM | Situação |
| --- | --- | --- |
| Resumo/cartão/titular em blocos e CSS responsivo | Futuras telas de checkout/adesão dentro do shell nativo, com autorização/conta/empresa próprias | Ainda não portado; fluxo de trânsito de dados de cartão aguarda decisão |
| Aviso Visa/Mastercard apenas para opção disponível selecionada de13–21 parcelas | Oferta validada no servidor, sem aviso de elegibilidade baseado só em parâmetro global | Testes oficiais confirmados; adaptação do checkout pendente |
| Card de recorrência compacto após totais | Documento/portal nativo, consentimento explícito, sem redesenho global | Adaptação pendente, não ativada por esta atualização de referência |
| Migration157 vazia e header1.5.7 | Histórico do CRM avança para1.7.9/179 | Não copiar157: esse ID já pertence ao CRM |
| Backend, contrato e SDK sem delta nesta versão | Serviços por empresa/conta/ambiente já existentes | SDK0.2.64 preservada, sem ampliação |

`AsaasRecurring::scope` da fonte rejeita `issuer_id`/`emitter_id` e contas diferentes
do gateway-base; `AsaasReconciliation::assertDiscoveryIssuer` também rejeita
contextos com emissor. Essas restrições não são contratos do ARGWS e não devem
substituir `CompanyService`, `CompanyLegacyBinding` e seus writers nativos.
Removê-las isoladamente também seria inseguro: transporte, segredo, revisão e
origem precisam vir da conta autorizada da empresa, nunca de fallback global.

A matriz1.5.6 permanece como inventário histórico das capacidades; os requisitos
que já estavam pendentes não se tornam concluídos pela nova versão visual.
Grupos/parcelamentos, sucessores de alocações parciais, alocação de principal, recorrência com
consentimento e paridade R01–R11 continuam trabalhos abertos.

## Testes oficiais da fonte executados

Em cópia isolada, PHP8.3.36, respostas/HTML sintéticos e rede externa bloqueada:

- `tests/card-view-smoke.php`:23 cenários,146 assertions, zero falhas.
- `tests/card-checkout-ui-smoke.js`:15 cenários,103 assertions, zero falhas.
- `tests/card-browser-smoke.cjs`:5 cenários Chromium, incluindo opção estendida
  selecionada, opção indisponível, ausência de JavaScript e operação pendente.
- `tests/recurring-browser-smoke.cjs`:8 cenários, incluindo POST/303/GET loopback,
  card compacto, responsividade, consentimento, revogação e escape.

Arquivos dos testes oficiais intactos. Um preload local selecionou o Chromium
instalado no executor; não se afirma o uso do binário pareado da Playwright.
Nenhuma cobrança, API externa, credencial persistente ou homologação PCI.
Esses resultados são da fonte; não comprovam checkout/recorrência no ARGWS.

## Cobertura ARGWS

O ciclo legado178 tem53 verificações MySQL adicionais, incluindo edição e
reembolso após edição, principal parcial, rollback e concorrência. A suíte
`asaas_legacy_primary_scope.php` acrescenta o cenário da empresa principal com
carteira distinta das secundárias e negação de empréstimo de credenciais/acesso
cruzado. A suíte passou em MySQL8.4 com7 verificações adicionais após74 da associação;
transporte sintético, nenhuma chamada externa. O CI final continua necessário.

Correção de CI após a publicação178: run42316831146/job128231380744 rejeitou a
nova fixture de recebimento após edição porque `gmdate` já indicava7/11 enquanto
o dia em `America/Sao_Paulo` ainda era6/11. Mantido o guard financeiro contra data
futura; a fixture usa a data de pagamento sintética original. Um caso negativo
novo exige rejeição de amanhã no fuso do provedor e zero recibos. A suíte passou
novamente em MySQL com55 checks adicionais. Nenhuma regra financeira foi afrouxada.

## Sucessores legados integrais — módulo 1.7.9/179

Cancelamento e recriação explícitos agora preservam a autoridade `manual_only`
para vínculos legados que cobriam toda a fatura. A preparação cria o sucessor,
perfil e política dentro da mesma transação; falhar a política desfaz a retirada
do antecessor. POST incerto exige recuperação, sem reenvio automático.

Cada conciliação explícita valida a cadeia até a associação revisada original:
empresa, conta, ambiente, identidades, remoções comprovadas, ausência de eventos
adversos e política manual em todas as gerações. Recriar não concede autorização
para baixa automática. Uma cadeia incompleta ou alterada bloqueia o recebimento.

A migration179 é NOOP e seu tracking é obrigatório. Não há DDL, backfill, seed,
credencial, permissão nova nem chamada financeira real. Alocações parciais não
podem ser convertidas em cobranças integrais; grupos e edição de sucessores
continuam pendentes. Implantação futura exige runner nativo e backup consistente;
rollback de código não desfaz cobranças, recebimentos ou gerações registradas.
Preservar journals e marcadores; tratar resultados incertos antes de retomar.

Regressão local: `asaas_origin_generations.php` passou em MySQL8.4, com69 checks
de gerações após118 da base,49 de remoção,22 de cancelamento,6 de concorrência
do cancelamento e16 de intenções/concorrência. Provedor simulado.
Runner179:337 assertions em MySQL, incluindo falha de tracking, retry e replay.

`asaas_legacy_successors.php`:37 checks adicionais passaram em MySQL8.4 após55
do ciclo legado,74 da associação,11 do lote,38 da edição e118 da base. Exercita
 duas gerações, POST incerto/recovery, falha de gravação da política com rollback,
tracking antigo negado, cadeia alterada negada, baixa automática negada, replay
e recusa de conversão de principal parcial em integral. Transportes simulados.
CI anterior8b6b02b7: Asaas contracts8.1/8.5, settings-ui e MySQL passaram no
run42317871022. Ainda havia jobs globais na fila; não é CI do novo commit179.
