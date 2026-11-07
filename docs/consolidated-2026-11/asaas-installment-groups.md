# Parcelamentos Asaas: revisão por empresa e conta

Referência funcional: módulo1.5.7, commit e790c33ade267b73027c167b468f06309d5d61e6,
`AsaasReconciliation::expandInstallments`. Adaptador ARGWS: módulo1.8.1/181.

A API distingue o grupo de suas cobranças individuais:
- [Consultar parcelamento](https://docs.asaas.com/reference/recuperar-um-unico-parcelamento).
- [Listar cobranças do parcelamento](https://docs.asaas.com/reference/listar-cobran%C3%A7as-de-um-parcelamento).

## Implementado

Em Configurações > Meios de pagamento > Asaas > Monitor, informe a fatura,
conta/ambiente e ID de uma cobrança e use **Consultar parcelamento desta cobrança**.
O adaptador resolve `installment` na cobrança, consulta o grupo e seus membros
por GET sem body e sem alterar o Asaas ou o financeiro local.

A conta vem da empresa autorizada. Nenhum fallback para o gateway global ou
exigência de que empresas secundárias compartilhem a conta principal. Wallet,
cliente, referência da fatura, conta/revisão e saldo são verificados. ACL e estado
local são revalidados entre chamadas e antes de retornar o resultado.

O conjunto exige2–100 parcelas, IDs e números únicos, numeração completa, total
exato em centavos, mesmo cliente/meio/grupo, referências locais corretas e
vencimentos válidos. A parcela inicial deve coincidir com a projeção da listagem.
Respostas truncadas, divergência de centavos, estados adversos e saldo insuficiente
são rejeitados. A consulta atual exige a lista completa em uma resposta;
`hasMore=true` não é apresentado como revisão integral.

A grade apresenta cada ID, número, valor, vencimento e estado consultado. O digest
é específico da revisão de grupo e não autoriza o vínculo avulso. Não há botão
para confirmar vínculo ou recebimento nesta revisão. A apresentação escapa os
valores e não expõe credenciais. CSRF/contexto nativos permanecem obrigatórios.

## Lacuna financeira explícita

Esta entrega é consulta/revisão, não associação nem baixa de parcelamentos.
A origem ativa atual e o recibo canônico foram desenhados para cobrança única.
A próxima adaptação precisa de identidade imutável por membro, alocação de
principal e recibo por parcela, total protegido contra concorrência, operações
individuais e histórico de reembolso/recriação. Não reutilizar um ID de grupo
como ID de pagamento nem afrouxar a unicidade para simular suporte.

Grupos financeiros, ciclo das alocações parciais, cartão e paridade integral
R01–R11 continuam abertos. Isso é trabalho de código, não limitação externa
inventada. Nenhum recurso de coleta de cartão ou ampliação do SDK neste delta.

## Banco, operação e validação

Migration181 NOOP. Sem DDL, backfill, seed, cobrança, recebimento ou consentimento.
Futura implantação usa runner nativo e confirma marcador1.8.1; preservar backup e
histórico. Reverter a apresentação não exige apagar dados; não reduzir marcadores.

Runner nativo:345 assertions MySQL com falha de tracking, retry e replay.
A primeira execução de `asaas_installment_review.php` passou em63 verificações
adicionais MySQL após74 da associação,11 do lote,38 da edição e118 da base.
Provedor simulado, sem homologação financeira. A suíte foi ampliada com render
real da view e os guards do controlador; resultados finais registrados na PR.

Resultado final local:65 verificações adicionais MySQL na revisão/grade;
controlador real:8 guards após179 cumulativos das suítes antecedentes. Inclui
CSRF ausente, ID inválido e conta estrangeira. Transporte:12 verificações sem
rede recusam mutações de grupo, body em GET, host/caminho arbitrários e vazamento
do erro do provedor. Callback:13. Nenhuma cobrança ou baixa realizada.

## Associação e recebimento por parcela — módulo1.8.2/182

A confirmação revisa novamente o grupo remoto e compara o digest apresentado.
Dentro de uma única transação nativa, cria uma origem ativa do grupo e identidades
separadas para seus membros. Metadados, prova, auditoria e política manual são
persistidos juntos. Falha da auditoria desfaz toda a associação. Retry com a mesma
aprovação retorna o mesmo grupo, sem nova chamada remota.

São utilizados journals existentes, sem alterar origens anteriores: canal
`installments` no grupo e `installment` nos membros. O grupo guarda o ID real em
`batch_id`; seu `remote_id` é um identificador local determinístico `grp_` + hash,
nunca enviado à API como cobrança. Cada membro guarda o verdadeiro ID de cobrança.
O grupo ocupa a seleção ativa da fatura; membros não são sucessores nem entram em
operações avulsas. Todos conservam `manual_only`. A consulta normal de cobranças
continua restrita ao canal API; há grade própria para grupos no mesmo monitor.

A consulta de uma parcela valida grupo, número, cliente, referência, nominal e
vencimento originais, com revalidação de conta/ACL antes de gravar a prova. A baixa
exige confirmação explícita, estado RECEIVED, prova canônica recente, data válida
no fuso do provedor, ausência de eventos adversos e saldo nativo suficiente.
O escritor financeiro nativo registra apenas o principal da parcela, preservando
um recibo canônico por ID remoto. A tarifa é observacional, não despesa automática.
A chave e o recibo não dependem de um clique novo; concorrência usa o mutex nativo.

O adapter de recebimento é uma classe final expressamente permitida pelo escritor,
sem callback público que conceda autoridade. Ele revalida vínculo/prova e confere
pagamento e journal nativos antes de registrar a ligação da parcela. Acesso cruzado
por empresa, configuração revogada ou histórico divergente falham no servidor.
Perfis sem leitura de faturas/configurações não recebem a lista de grupos; isso
não bloqueia os demais recursos da página de configuração.

Migration182 NOOP; utiliza o schema e constraints existentes. Upgrade confirma o
marcador1.8.2. Backup consistente inclui origens, provas, políticas, auditoria,
pagamentos e recibos. Rollback de código não desfaz associações ou pagamentos:
não apagar membros, baixar marcadores ou repetir cobranças para reconstruir estado.

Permanecem pendentes neste delta: edição/exclusão/reembolso/recriação dos grupos
e alocações parciais, cartão e paridade integral R01–R11. Webhook adverso interrompe
conciliação; não significa reversão financeira automática. Não declarar ciclo
completo de parcelamentos apenas pela associação e baixa implementadas aqui.

Primeira validação local:32 checks MySQL de associação/recebimentos; rodada ampliada
com dois processos concorrentes:36 checks, após65 da revisão e suas dependências.
Runner182:349 assertions MySQL; callback13; migrations históricas154/155:48/101.
Provedor simulado, sem chamada financeira real ou homologação Asaas.

CI649f627d: focused8.1 job128264760835 falhou em
`asaas_post_refund_generations.php:71`. A fixture usava dia UTC no paymentDate,
futuro em America/Sao_Paulo entre00–03UTC. Corrigida a data sintética para o fuso
do provedor; o guard financeiro não foi alterado. Monitor run42326809950 reportou
essa falha. A repetição local e o CI do novo SHA devem confirmar a correção.

Validação final182:39 verificações adicionais MySQL de associação/recebimentos,
após65 de revisão e dependências74/11/38/118; controlador real11 guards após179
cumulativos. Teste Chromium com view PHP e Bootstrap reais: quatro cenários
1280/390px, leitura/escrita, campos CSRF, confirmação por parcela, escape e ausência
de botão de baixa em parcela paga. Binário Chromium do executor; no CI usa o
binário pareado. Esses cenários de view não equivalem a homologação HTTP completa.
Regressão de pós-reembolso corrigida:30 checks adicionais MySQL após69 de gerações
e suas dependências. Gates do novo SHA e CI permanecem verificações separadas.

## Exclusão individual — módulo1.8.3/183

A grade permite excluir explicitamente uma parcela pendente/vencida ainda sem
recibo local. O endpoint é `DELETE payments/{id}`; não é exclusão do grupo nem
cancelamento da recorrência. Contrato oficial consultado em 7/11/2026:
https://docs.asaas.com/reference/excluir-cobranca . Exclusão não é reembolso.

O serviço valida empresa/conta/ambiente, ACL de exclusão, vínculo original,
consulta canônica recente e estado não pago. Recebimentos das outras parcelas
podem coexistir; créditos ou pagamentos nativos sem vínculo revisado impedem a
operação. O mesmo mutex financeiro protege a intenção e a baixa por parcela.
O journal existente grava `sending` antes do DELETE; retry retorna o estado
persistido e nunca envia novamente. Uma perda de resposta permanece `uncertain`.
Conferir exclusão faz GET e exige `deleted=true` com a mesma identidade e nominal.
Resposta contraditória retira a confirmação e exige revisão. Não modifica saldo,
fatura, calendário, política manual, vínculo original ou recibos das demais parcelas.

Um evento PAYMENT_DELETED após a intenção enviada pode ser conferido por GET,
sem apagar o evento nem reescrever a prova de associação. Eventos financeiros
adversos diferentes, conflitos de payload e vínculos corrompidos continuam
bloqueados. A baixa de uma parcela com intenção de exclusão é impedida; não há
ativação automática de novo recebimento após restauração remota.

CSRF e confirmação são independentes para excluir e conciliar. A interface
expõe estado da intenção e consulta de recuperação. A observação técnica da
resposta em trânsito pode ser gravada após revogação de ACL; isso não permite
novas ações ou efeitos financeiros ao ator revogado.

Migration183 NOOP. Banco, constraints e histórico publicados permanecem intactos.
Futura implantação deverá confirmar1.8.3 pelo runner nativo. Reverter código não
restaura cobranças removidas no provedor nem desfaz recebimentos; preserve o
journal, use consulta de recuperação e não repita DELETE para reconstruir estado.
Nenhum deploy, transação real, SDK ou coleta de cartão foi executado neste delta.

Ainda pendentes: edição e remoção do recurso de grupo como conjunto, reembolso,
recriação e ciclo completo das alocações parciais. Exclusão por membro não atende,
sozinha, esses requisitos. Não confundir testes com transporte simulado com
homologação Asaas ou autorização para operações reais.

CI do checkpoint182: run42328340135/job128268789854 falhou porque a fixture de
preço de cartão anunciava1.7.3 sem criar journal170/política157. Reprodução local
confirmou o mesmo erro. Corrigida somente a preparação da fixture, acrescentando
assert de falha fechada antes do schema. Controller de preços:9 checks adicionais
MySQL após179 cumulativos. Nenhum guard de produção foi afrouxado.

Validação183 executada:43 verificações MySQL adicionais na suíte de remoção
(depois65/74/11/38/118 das dependências);16 guards do controlador depois179
cumulativos;353 assertions MySQL do runner nativo até183;13 callback/version;
quatro cenários Chromium de view PHP real,1280/390px, leitura/escrita, confirmação
separada e CSRF. Reentrada concorrente no transporte verifica envio único e veto
à baixa; a suíte182 mantém o teste de dois processos para recibo canônico.
