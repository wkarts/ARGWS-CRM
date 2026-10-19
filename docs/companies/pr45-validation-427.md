# Validação do incremento427

Núcleo4.2.7/427, BB1.1.3/113, Asaas1.5.1/151, sobre
`4c5dd23b31836f2bd562980c4e075e9017910c29`. Migrations históricas preservadas.
O fonte de execução foi exportado do índice; preparações428/429 estão isoladas.

## Testes executados localmente

PHP8.3.6, MariaDB10.11.14/InnoDB e SQLite. Contadores cumulativos não se somam.
Protocolo dos provedores e SMTP simulados; sem credenciais ou dados reais.

| Requisito | Código e teste | Resultado observado |
|---|---|---|
| Fonte final/componentes240 | banco_brasil_components_mysql.php / components_concurrency.php |122/139 checks MariaDB; composição imutável, replay, A/B e lock/revogação |
| Saldo e recibo pai | CompanyInvoiceBalance, bank_receipt_parent_integrity.php |143 MariaDB/142 SQLite; recibo ligado ao pai errado bloqueia posição e mutação |
| Guard de emissão | provider_balance_guards.php |160 SQLite/MariaDB; componentes, órfãos/corrupção, reserva e envio |
| Créditos/escritor/leitores | bank_components_writer_core.php, invoices.php, relatórios/PDF/portal |138 checks centrais MariaDB, storage123 SQLite e regressões de créditos/API/portal |
| Schema e upgrade427 | native_427.php, module_version e callbacks |134 MariaDB; schema/replay e bytes originais preservados; Asaas15148/callback46 |
| Arquivos de contratos/chamados | operation_files.php / controller / concurrency |129 SQLite,103 controller e89 MariaDB; arquivos privados, quotas, integridade, auditoria e locks |
| Pagador/checkout/portal | asaas_checkout_boundaries.php / portal.php / concurrency.php |170/164 SQLite e165 MariaDB; POST único, recuperação, publicação e revogação |
| Cartão recebido e tarifa | asaas_card_settlement.php / concurrency.php |230/261 checks MariaDB; data efetiva, worker, despesa separada e contenção |
| Antecipação persistente | asaas_card_anticipation.php |248 cumulativos MariaDB; callback adverso seguido de GET limpo continua em revisão |
| Checkout legado | asaas_checkout_preflight.php / legacy_scope.php / legacy_http.py |113 checks kernel/autoload; named404 antes de providers e NULL legado preservado |
| Regressões financeiras | Asaas jobs/fees, BB API receipt e reconciliação240 |177/344/131 MariaDB; chaves/provas e comportamento anterior preservados |
| Diário e portal | ledger controller, portal revogação, sales worker |162 MariaDB, portal96 MariaDB e worker241 SQLite |
| Release/inventários |6 módulos unittest Python |62 testes aprovados; políticas estáticas não comprovam operação bancária |
| Sintaxe | Fonte congelado: PHP92 arquivos, JavaScript4, bash e diff |PASS; fixture BB corrigida para esperar versão113 exige nova rodada HTTP |

## HTTP e Chromium

**25 de25 execuções aprovadas em uma única rodada limpa**, na ordem exata das24
invocações do smoke e settings adicional. Evidência em [JSON](evidence/http-427.json). Ambiente
descartável com aplicação real mysqli, PHP8.3.6, MariaDB10.11.14 e Chromium140.
Não usa Docker; adaptação estrita somente de comandos fixture/SQL. A exploração
encontrou cinco falhas de teste e uma precondição não comprovada da suíte anterior: seletor ambíguo input/submit de upload, fixtureBB
que ainda esperava112 após o runner instalar113 e endereço sintético em minúsculas
recusado pela validação CNAB ASCII maiúscula e cursor do diário sem watermark/through.
O browser agora usa o permalink real para fixar o corte. O formulário de pagador
Asaas exigiu navegação para Emissão e abertura do details antes de preenchimento;
o teste passou a operar essas ações reais de interface. A suíte de faturas agora
localiza o formulário de permissão pelo contato exato, concede pela GUI quando
necessário e exige a permissão persistida antes de testar a notificação; o antigo
if(count()) podia pular essa precondição. Corrigidos sem alterar a aplicação
ou os asserts financeiros/ACL. O adaptador local de logs também precisou aceitar
`--tail`; esse erro diagnóstico não era a causa da rejeição CNAB. A rodada final
foi repetida desde instalação limpa após os ajustes das fixtures e passou integralmente.

Manifest inicial e final idêntico:
`8086725e710604fdafa7c89a338b48d94c93b8b67b6df5e42262bdcb825a0100`.
Nenhum byte do fonte mudou durante a rodada. Os17.900 arquivos indexados em
application/modules/assets/install e os testes executados coincidem com o freeze;
os arquivos de aplicação/distribuição conferidos também não apresentaram diferença.
Docs finais e a ligação da suíte de componentes aos jobsBB MySQL8.4/MariaDB11.4
foram preparados separadamente no índice, fora desse manifest, sem alteração de
código executado. Transporte de providers/SMTP simulado, sem homologação.

## CI e limites

CI deste incremento será consultado no SHA remoto publicado. O CI426 terminou
18 execuções/16 workflows com sucesso; não é prova do novo427.

A composição240 é restrita a tarifas/IOF/outros zero e líquido igual ao pago;
encargos são agregados, sem inventar multa/juros individuais. A aplicação API
parcial/ajustes e a reversão própria dos novos componentes continuam técnicas
pendentes. CNAB400 requer os campos/documentos específicos do seu guia e posterior
integração; nenhuma homologaçãoAPI/240/400 foi realizada. Asaas151 é cartão
avulso hospedado, sem parcelas/assinaturas/antecipação/custódia/refund/chargeback.
Portal operacional e API de subrecursos estão em preparação separada. Recuperação
gráfica legada depende da prova de provisionamento e cadeia ainda não fornecidas.

Sem merge, deploy, force-push, tag, release ou publicação manual de imagens.
