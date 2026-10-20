# PR45 — estado da integração BB e Empresas

## Composição436 — fontes operacionais e recuperação

Núcleo4.3.6/436, BB1.1.6/116 e Asaas1.5.2/152 estão em validação sobre435. API16 por componentes integrais e vínculo declarado de retorno240 à mesma origem API usam o writer existente e a mesma identidade canônica; não criam remessa, outro canal ou pagamento automático. Alteração/baixa incerta anterior ou posterior à consulta veta um novo recebimento. Recuperação de processo morto após sending usa consulta independente autorizada, sem reenviar POST/PATCH nem fabricar sucesso pelo vencimento da reserva. A [validação436](companies/pr45-validation-436.md) discrimina fonte congelada, instalação/tracking, testes e CI do próprio SHA.

O canal400 continua indisponível pelas lacunas BRL85–86, juros herdados/contrato e pictureCBR64326–39; trailer641 já foi resolvido. Testes sintéticos e declarações operacionais não demonstram autenticação bancária ou homologação. Nenhum merge, deploy ou transmissão real.


## Incremento435 — perfis API e prova financeira cruzada

Núcleo4.3.5/435 e móduloBB1.1.5/115 (NOOP de schema); os contratos anteriores e hashes não são reclassificados. Registro API agora oferece juros mensais percentuais e primeiro desconto fixo ou percentual até data, com declaração contratual/capacidade explícita, perfil imutável e consulta coerente da conta. Publicar taxa/oferta não lança juros nem reduz a dívida: recebimento API com ajustes continua em revisão neste incremento. Asaas1.5.1/151 preservado. A [validação435](companies/pr45-validation-435.md) separa testes locais, tracking e CI do próprio SHA.

A disputa financeira API20/CNAB17 passou com duas fontes finais reais/two-process InnoDB e controles positivos separados. Colisão de identidade entre faturas é adversarial/sintética; não representa importação GUI de retorno240 sobre origemAPI, cujo adaptador segue como código em implementação. Layout240 e contrato manual mantêm seu significado: download/declaração não são transmissão ou aceite autenticado. CNAB400 segue indisponível por BRL85–86, juros herdados/contrato e pictureCBR64326–39 ainda sem confirmação suficiente; trailer641 não é reaberto. Nenhum canal/API/240/400/Asaas/SMTP foi homologado externamente.


Código atual: núcleo4.1.6/416, BB1.0.8/108, Asaas1.4.8/148. A matriz
[atual](companies/pr45-status.md) e os incrementos406–413 abaixo substituem as
pendências do checkpoint405 preservado como histórico. Conferir o HEAD remoto e
os testes do SHA final; não há homologação externa, merge ou deploy.

## Histórico405: branch e ancestralidade

- PR45: https://github.com/wkarts/ARGWS-CRM/pull/45
- Branch única de escrita: `feat/banco-brasil-billing-cnab240`; destino `main`.
- Base publicada verificada: `aa58b832b3c5ed04a599caacba125b056c081ba7`, 4.0.2/402.
- PR46 já incorporada por merge `cdd6f6f2c312a1aaa5f0f02056ad2e8b23eb3378`.
  Está encerrada; não reabrir nem tratá-la como trabalho independente.
- HEAD conferido antes deste incremento: `44cd7c3815123883d5c3168ae045a75a3347b236`.
- Incremento publicado: `30fb0f10a3ab5eac598d795ef9c712a1187b8364`, núcleo 4.0.5/405,
  responsável da instalação e wizard atômico. Conferir o HEAD remoto antes de escrever;
  correções e evidências posteriores não são certificadas pelo CI de um SHA anterior.
- BB 1.0.4/101–104 e Asaas 1.4.8/148 preservados. Núcleo402→403→404→405;
  nenhuma migration histórica renumerada, removida ou reescrita.

## Histórico405: implementação ligada ao CRM

Empresas é um recurso do núcleo. `modules/issuers` continua fora da carga operacional.
A empresa principal é a identidade persistida da instalação; não se presume ID1.
O instalador captura o administrador realmente criado. O legado ambíguo possui agora
uma designação explícita com autorização de console, confirmação gráfica por senha,
expiração, limite de tentativas, vínculo ao colaborador e auditoria atômica. Isso não
é apresentado como prova do administrador histórico. Ver [onboarding](companies/onboarding.md).

O wizard salva rascunhos por responsável e revisões, permite retomada e aplica empresa,
identidade canônica, preferências, acessos, padrão e auditoria em uma transação.
O master ativo cobre empresas/recursos futuros sem copiar grants, mas continua sujeito
às barreiras de funcionalidades não concluídas. O editor nativo de colaboradores
recusa criação sem empresa/padrão e mantém a mensagem de falha se perfil e grants
não forem confirmados juntos no fluxo legado.

BB usa `IssuerCore`/`IssuerRegistry` e o contexto nativo, com ACL por recurso/empresa.
As quatro rotas permitidas continuam `index`, `register_connection`, `register_layout`
e `import_return`. O cadastro preserva empresa, conta, ambiente, convênio, carteira,
variação e identidade legal; perfis 240/400 são explícitos e imutáveis. Guias funcionam
no shell isolado; tokens não são duplicados. A importação guarda observações,
deduplicação, conflito e proveniência com auditoria. Nenhum pagamento é criado.

## Pendências registradas no405 (histórico; consultar matriz atual)

- Clientes privados: adoção e edição pela interface, compartilhamento explícito apenas
  da identidade, portal e recuperação por empresa; validar leitores e consumidores
  antes de abrir as rotas. As barreiras atuais permanecem.
- Domínios financeiros e operacionais por empresa: faturas, créditos, pagamentos,
  recorrências, projetos/tarefas, despesas, relatórios, exportações, arquivos, APIs,
  módulos, cron e filas. As adaptações existentes não equivalem a rollout completo.
- BB API: OAuth, configuração protegida por conexão, emissão/consulta/alteração/baixa
  e notificações conforme a OpenAPI oficial vigente, sem inventar endpoints/scopes/mTLS.
- Origem de cobrança ligada à fatura, reserva concorrente do Nosso Número, lotes,
  geração e download auditados, reenvio idempotente e política de transmissão.
  As tabelas reservadas ainda não são essas operações implementadas.
- Retorno além da observação: associação comprovada à origem e título, divergências,
  política de baixa parcial, juros/descontos/tarifas, estorno/rejeição/ordenação,
  deduplicação entre canais e escrita no gravador financeiro aprovado.

Esses itens são **pendências técnicas**, não somente homologação externa.

## Contratos externos ainda necessários

Em 19/10/2026 os PDFs canônicos foram baixados e extraídos novamente; os hashes
correspondem aos documentos240/400. CBR641 julho/2026, página 10, define claramente
seu trailer de remessa: 9, brancos 2–394, sequência 395–400. A divergência de picture
é do trailer de retorno CBR643 (26–39 tem 14 posições; picture `9(013) v99`).
BRL no campo 85–86 do CBR641 continua sem código explícito na nota 30; juros podem
herdar a configuração do beneficiário. O candidato 400 permanece indisponível para envio.

A especificação primária foi recuperada pela rota pública do próprio portal em
19/10/2026, conforme a seção de fonte oficial abaixo. A indisponibilidade anterior
não é mais bloqueio documental. O Swagger operacional sem AppKey continua não sendo
fonte pública. Não usar contratos de terceiros como prova.

O usuário informou ter remessas aceitas de outra aplicação, mas elas não estão anexadas.
Quando fornecidas, tratar os originais como privados, anonimizar estrutura antes dos
fixtures e não publicar dados bancários ou pessoais. Não há retornos reais disponíveis.

Homologação é separada por API, CNAB240 e CNAB400 e por empresa/conta/convênio/carteira.
Nenhum canal foi homologado nesta sessão; nenhuma transação bancária real foi autorizada.

## Evidências executadas

No commit `30fb0f1`, todos os 16 workflows terminaram com sucesso. Em particular:

- [Empresas, MySQL 8/PHP 8.1 e 8.5](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107754).
- [Instalação/upgrade, HTTP, Chromium e wizard real](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107803).
- [BB codecs, módulo, MySQL e driver concorrente](https://github.com/wkarts/ARGWS-CRM/actions/runs/39197107753).
- Asaas origem/callback, notificações, privacidade, recorrências, versões/migrations e
  builds de PR também passaram. Isso não valida todos os consumidores do CRM.

Localmente: PHP 8.3, fixtures SQLite, lint e testes de contrato; MariaDB foi preparado,
mas o ambiente recusou socket Unix e a permissão para iniciá-lo. Portanto a evidência
MySQL/HTTP completa vem do CI descartável. Chromium local executou a regressão de abas;
essa regressão sintética não substitui o cenário CRM/HTTP do CI.

Consultar [matriz objetiva](companies/pr45-status.md) e o CI do HEAD exato na entrega.
Inventários lexicais são localizadores de revisão, não contagem de bugs ou cobertura.
Não elevar evidência de consumidor só atualizando hash. As entradas antigas com hashes
divergentes deixam de aparecer como testadas no registro regenerado.

## Release

Não fazer merge na main, deploy, tags, publicação de imagens ou alinhamento de develop
sem autorização correspondente. O caminho normal de release permanece intacto. Uma
PR aberta, CI verde ou cadastro/configuração visual não representa aprovação de merge
nem uma operação bancária homologada.

## Incremento409 / módulo1.0.5: originação local240

A rota nativa isolada usa faturas nomeadas do núcleo. O operador declara perfil240,
faixa exclusiva de Nosso Número e de sequência de arquivo, referência contratual,
isenção de juros habilitada e intercâmbio TESTE no sandbox. Essa declaração é
registrada como `operator_declaration_not_bank_verification`; não autentica contrato.
O perfil permanece carteira17/simples/convênio7, BRL, P/Q, sem juros/multa/desconto/
protesto/pagamento parcial. Endereços e nomes devem caber em ASCII sem truncamento.

A emissão reserva números sob o lock transacional do núcleo, vincula fatura/empresa/
conta/ambiente/convênio/perfil e snapshot, e confirma a reserva antes de persistir os
bytes finais. O namespace ambiente/convênio atravessa troca de conta e conexão. Não
é possível reiniciar a faixa ou consumir duas vezes no retry. Falha após reserva
preserva Nosso Número e lote para retomada. Faturas pagas, créditos, outra origem BB
ou Asaas, moeda diversa ou snapshot fiscal divergente são recusados.

`reserved`/`generation_failed`/`generated` são estados locais. O download exige POST,
CSRF, ACL e auditoria confirmada e muda apenas entrega para `downloaded`. O registro
manual posterior vira `reported_submitted`, com referência imutável do operador.
Nenhum desses estados significa transmissão autenticada, aceite ou liquidação.
Arquivos antigos usam sua origem congelada após troca de conta. Faturas vinculadas
continuam protegidas contra cancelamento local e recebimento manual indevido.

A migration nativa105 é aditiva;101–104 permanecem intactas. Novos testes
`banco_brasil_origination_mysql.php` e `_concurrency.php` compõem o núcleo e o
executor nativo do módulo em MySQL descartável. O resultado só pode ser atribuído
ao SHA que executar esses testes no CI. O teste de abas verifica também o fragmento
usado após POST. Homologação externa240, remessa400/API e baixa por retorno continuam
pendentes; não há transporte bancário nem credenciais neste incremento.

### Correção410 / BB106

A grafia ASCII de beneficiário/pagador é revisada explicitamente pelo operador,
sem sobrescrever identidade fiscal ou transliterar nomes silenciosamente. O campo
Documento bancário usa `CRM{id}`; o número formatado original da fatura é preservado
no snapshot. Isso corrige a composição entre os documentos nativos (com hífen) e
o codec restrito.105 é preservada;106 é marker NOOP. A stack HTTP recebeu um teste
com instalação nativa BB, conexão/perfil/contrato pela UI, geração, download e
declaração manual, sem chamadas ao banco. Atribuir resultado somente ao SHA do run.

### Fonte oficial da API recuperada em19/10/2026

A página pública de apoio carrega a documentação de Cobranças como API IPA5,
catálogo17395705, versão2. O download oficial público foi obtido pela mesma rota
exposta pelo portal:
https://api.externo.bb.com.br/docs/v1/openapi/17395705/2

OpenAPI3.1.1, `info.version=3.2.2`,198948 bytes; SHA256
`6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`.
O documento distingue servidores sandbox/hm/produção, OAuth clientCredentials,
AppKey de query e webhook de baixa operacional com mTLS. A combinação de múltiplos
esquemas OAuth no mesmo Security Requirement não significa exigir os três ambientes
simultaneamente; essa inconsistência formal deve ser considerada no consumidor.
A posse da especificação não implementa o executor nem prova contrato/homologação.
API ainda está indisponível no incremento410. Não houve chamadas bancárias, tokens
ou uso de credenciais do cliente.

## Incremento411 / BB107: conciliação assistida restrita

O vínculo é persistente e exige empresa, conexão, conta/ambiente/convênio, canal,
Nosso Número, controle, documento, vencimento, perfil e snapshot íntegros. Não há
reclassificação por coincidência de nome/valor, nem adoção de títulos externos.
O histórico de revisão armazena classificação, motivo, referência e ator, sem
alterar pagamentos. Código44, cheque pendente, baixa operacional e divergências
permanecem para revisão; registrar “estorno” **não executa estorno financeiro**.

A entrada explícita `InvoicePaymentWriter::recordCompanyProviderReceipt` aceita
somente o adaptador final BB revisado, mantém a rejeição de owner positivo em
`record()`/callbacks legados e possui a transação inteira. O adaptador não executa
rede: lê a origem/evento sob o mesmo lock do núcleo. Exige BB Conciliar + faturas
Visualizar + pagamentos Criar, vínculo prévio e confirmação explícita de crédito
conferido no banco por fonte independente do upload, com motivo/referência.
O arquivo mantém proveniência `authenticity=not_verified`: a confirmação é do
operador, não uma autenticação técnica fictícia.

O recorte financeiro assistido é T/U, ocorrência06, BRL, nominal igual à fatura,
remessa gerada com envio manual declarado, datas de crédito já ocorridas, líquido
igual ao pago e encargos/ajustes todos zero. Créditos parciais confirmados deixam
saldo e status parcial. Retry/reimportação e concorrência usam recibo canônico
único, pagamento/status/ledger/auditoria no mesmo commit; falha reverte todos.
Alteração de evidência após aplicação, excesso de saldo, origem Asaas concorrente,
encargos e eventos adversos em qualquer ordem não recebem bypass manual.
Juros/descontos/tarifas/estorno contábil e reconciliação automática autenticada
continuam pendências técnicas; não são tratados como operações concluídas.

A migration107 só adiciona links/revisões/recibos;101–106 ficam preservadas. Os
novos testes MySQL/concorrência e o navegador real incluem upload multipart,
vínculo e recebimento assistido com dados/evidências sintéticos. Conferir o SHA
final e seus runs; homologação externa e SMTP externo não foram executados.

O teste HTTP410 também expôs o diretório real do roteador MX
(`../../modules/banco_brasil/controllers/`).411 reconhece somente o controlador e
as ações BB revisadas, antes do autoload. A lista fechada e o token de empresa
permanecem; diretórios/métodos não revisados não passam a ser isolados por prefixo.

## Incremento412: renderer nativo isolado

O teste HTTP411 expôs a chamada a `module_supports()`/`app_modules` pelo loader de
views, após a barreira MX ter sido corrigida. O contexto isolado não carrega esse
registro legado.412 recusa overrides `my_` não revisados em views seladas, antes
de consultar suporte de módulo, e preserva o comportamento legado fora desse
contexto. O teste usa a decisão do `App_Loader` real sem módulos/helper carregados.
O BB permanece107; este incremento altera o renderer do núcleo, sem DDL ou mudança
funcional nos arquivos de módulos. Repetir HTTP/Chromium no SHA412.

## Incremento413 / BB108: executor API com origem e outbox

A API usa a OpenAPI oficial3.2.2, com servidores OAuth/API explícitos de sandbox,
homologação e produção, sem fallback de credenciais ou ambiente. Perfis contratuais
imutáveis ligam empresa/conexão/conta/convênio/carteira/faixa e versão da especificação.
O painel nativo declara perfil e salva/rotaciona credenciais com `CI_Encryption`
autenticado e envelope de contexto. A UI não recupera segredos já salvos. Configurar
não faz autenticação nem envia títulos. O namespace de Nosso Número é compartilhado
com CNAB; a troca de conexão ou de canal não reinicia a faixa.

O recorte operacional é carteira17/simples, convênio7, BRL, sem Pix, juros, multa,
desconto, protesto ou emissão com recebimento parcial. A fatura e seu documento
canônico definem identificação fiscal, CPF/CNPJ, valor e datas; endereço/especie/
aceite são informados explicitamente. O JSON preserva valores monetários exatos.
O POST de registro valida número de20 dígitos, agência/conta, carteira/variação,
código de barras44, linha47, DVs, fator de vencimento e campo livre do convênio.
Referência oficial adicional do boleto:
https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc5175Bloqueto.pdf
SHA256 `6da3d3f4cde0cb94b20f957001c10cd683efd6f9c5ec6dfe68193f9585e79902`.

Reserva de número/origem e intenção durável precedem a rede. OAuth ocorre fora da
transação; autorização é revalidada antes do marcador confirmado de envio. Timeout,
5xx ou resposta divergente após POST mantêm resultado incerto, sem reenvio automático.
Falha apenas de autenticação pode retomar a mesma reserva. Claims serializados e
recibo de requisição impedem dois escritores de enviar o mesmo registro. Tokens ficam
somente em memória; diagnósticos não guardam corpo bruto de erro, credenciais ou URL.

A consulta detalhada confere fatura/snapshot/identificadores/contrato e depois cruza
Nosso Número com a listagem oficial filtrada por agência/conta/pagador/vencimento.
Paginação limitada ou ausência da origem exigem revisão; não provam inexistência.
Estado não confirmado pela listagem fica pendente, mesmo com resposta de detalhe.
O pedido POST de baixa exige consulta NORMAL confirmada há menos de5 minutos e usa
sempre a conta/perfil antigos. Sua resposta é somente pedido; a confirmação depende
de consulta coerente com código7. Só então a origem libera o slot, mantendo histórico
para eventual emissão explícita com novo Nosso Número. Código6/16 é observação de
liquidação; **não cria pagamento automaticamente nem cancela a fatura local**.

A migration108 adiciona apenas perfis, credenciais, vínculos API e operações;101–107
permanecem intactas. Novos testes locais de protocolo usam transporte simulado. Os
novos cenários de criptografia nativa, tracking, retomada, A/B, rotação, revogação e
dois escritores reais são executáveis nos jobs MySQL8.4/PHP8.1/8.5 e MariaDB11.4;
atribuir passagem somente ao SHA/run que os executar. O navegador testa cadastro e
criptografia pela UI, sem realizar chamada ao banco. O último baseline completo antes
deste incremento foi7aa56e60 (412),16 workflows concluídos com sucesso, inclusive
HTTP/Chromium e originação/retorno assistido.

Continuam pendências técnicas: alteração de títulos, emissão com encargos, webhook
mTLS (previsto para baixa operacional), aplicação financeira de observações API,
conciliação de ajustes/estornos e os demais consumidores multiempresa da matriz.
Certificados/ingresso mTLS não são substituídos por cabeçalho de origem ou HMAC
inventado. Não houve credenciais reais, OAuth bancário, transmissão, homologação,
merge ou deploy nesta sessão.

## Incremento414: portal de faturas e metadata MariaDB

O contato só vê faturas nomeadas da sua empresa de login e do seu próprio cliente,
com permissão nativa `contact_permissions.permission_id=1`. Login/reset não concedem
acesso financeiro. O editor de contatos possui concessão/revogação explícita; exige
clientes Editar e faturas Visualizar da empresa proprietária. A revisão vincula o
contato e o estado da permissão, sem sobrescrever outras permissões nativas.
Compartilhar cadastro não transfere contato, sessão ou histórico de faturas.

A lista usa predicado empresa/cliente antes do resultado; NULL legado e outra empresa
ficam fora. PDF usa DTO/TCPDF nativos e revalida sessão, permissão e ownership antes
da construção e antes dos bytes. Auditoria de entrega guarda contato/cliente/hash e
recibo único, sem e-mail ou bearer; erro de auditoria e revogação impedem entrega.
Rotas continuam no controlador público isolado `company_portal/index`, com POST/CSRF
para download; não foi aberta a hidratação financeira do tema legado.

No CI413, MySQL/PHP8.1/8.5 passou a API com142 checks acumulados e HTTP/Chromium
passou o cadastro criptografado nativo. A observação do lock MariaDB foi corrigida
em68cd08be: o cache de INNODB_LOCKS precisa de100ms de inatividade; leituras a20ms
mantinham snapshot vazio. O assert de linha/índice/tabela exatos foi preservado e
passou49 verificações mysqli/two-process na reexecução. Isso expôs incompatibilidade
real de metadata do núcleo: MariaDB representa DEFAULT NULL como expressão `NULL`,
enquanto MySQL retorna null.414 aceita as duas representações, mantendo rejeição a
DEFAULT0 e ao literal textual. Não muda ownership, dados ou migrations históricas.

Os novos testes de portal usam SQLite local, MySQL/MariaDB com dois leitores reais
bloqueados durante revogação e HTTP/Chromium com concessão/PDF/IDs A-B/revogação.
Atribuir passagem somente ao SHA final. Ainda não há entrega SMTP externa,
homologação bancária ou rollout completo dos demais domínios.

## Incremento415: padrões NULL de mensagens e barreira legada do contato

A execução MariaDB414 confirmou76 verificações do núcleo,47 de clientes, concorrência
e49 do driver BB. A seguir expôs a mesma representação de DEFAULT NULL em templates.
`IssuerStorage::sqlNullDefault` centraliza a distinção: null do driver ou expressão
não citada `NULL` são válidos; metadado ausente, literal citado e DEFAULT0 não são.
Núcleo, fila e templates usam a mesma validação. Os testes nativos alteram o default
para0 e exigem rejeição, restauram NULL e exigem passagem; nenhum dado é reatribuído.

A permissão nativa do contato1 é consumida somente pelo novo portal com owner e
fatura nomeada. O helper legado `has_contact_permission` agora recusa contatos
privados antes de hidratar catálogo/módulos; uma concessão feita no portal nativo
não libera os consumidores legados ainda não revisados. O controle positivo de
contato legado sem owner permanece. Isso preserva a barreira de rollout, sem
remover a permissão nativa nem criar outra fonte de concessões.

O CI do415 precisa confirmar portal/concorrência, MariaDB e HTTP/Chromium. O414
já passou Empresas em MySQL/PHP8.1/8.5; não atribuir essa evidência ao415 sem seus
runs. A matriz continua parcial e nenhuma homologação externa foi executada.

## Incremento416: faturas emitidas no portal

A permissão de faturas do contato não libera rascunhos. Lista, PDF por ID e a
revalidação antes dos bytes exigem estados emitidos1–5; estado6 e estados inválidos
continuam indisponíveis. As três regressões adicionais levam a suíte local de
portal/PDF a60 verificações acumuladas em SQLite. Faturas emitidas pagas/parciais/
canceladas preservam sua história. O teste de default0 no415 também revelou cache
antigo de tabela na fixture de fila criada por DDL bruto;28e49fd2 invalida o cache
e executa o schema real antes dos asserts. Nenhum assert foi removido.

Conferir o CI do SHA final416, incluindo MySQL/MariaDB e HTTP/Chromium. Persistem
as pendências técnicas e contratuais da matriz; não há homologação externa.


## Continuidade419 / módulo1.0.9 — crédito API confirmado

API agora tem ação explícita de recebimento via `settle_api`, sem rede dentro da
transação. O adaptador final `ApiCreditedReceipt` entra no writer nativo somente
com a última consulta validada, prova da conta, estado16 CREDITADO, valor integral
sem ajustes e data de crédito ocorrida. Consulta isolada permanece observacional;
6 LIQUIDADO não lança pagamento. Repetição/nova consulta equivalente retorna o
mesmo recibo; chave bancária independe da UI. Auditoria/recibo/status/pagamento são
atômicos. Cancelamento/cheque/parcial/cartório em histórico impõem revisão; uma
consulta posterior não limpa `api_conflict_review`. Observação posterior adversa
não estorna nem apaga pagamento. Origem CNAB/Asaas não é contornada.

Migration109 adiciona bb_api_receipts com FK/PK/índices integrais, sem editar108.
Módulo1.0.9 exige core4.1.9/419. Upgrade/tracking/replay, falhas de auditoria e
proveniência, A/B e concorrência estão em `tests/banco_brasil_api_receipt_mysql.php`
e `tests/banco_brasil_api_receipt_concurrency.php`.331 é número acumulado com os
testes de fundação e API, não331 novos cenários. Execução local usa PHP8.3.6 e
MariaDB10.11.14 reais; somente o transporte BB é simulado. O teste do CRM/Chromium
usa fixture restrita ao DB descartável argws_upgrade_ci, serviços reais e transporte
injetado; o endpoint de crédito usa o escritor real sem chamar o banco.

A OpenAPI oficial foi novamente baixada em19/10/2026:3.2.2 e SHA256
6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3, iguais ao contrato.
Nenhuma credencial real, OAuth bancário real, operação de cliente ou homologação.

Pendências técnicas preservadas: alteração API, webhook mTLS, outros perfis,
encargos/parciais/estorno contábil; ampliação CNAB240 e canal operacional400.
Dados/contratos400 faltantes são específicos e separados da homologação externa.
Asaas positivo por origem e os demais consumidores multiempresa continuam pendentes.
Consultar a matriz do HEAD atual; uma execução anterior não valida um novo diff.


## Continuidade421 / módulo1.1.0

Comparação bancária entre API20 e CNAB17 aceita a representação equivalente
000+17 dígitos nos vetos de recibo aplicado e histórico adverso. Conta, ambiente,
convênio e empresa continuam obrigatórios; não há reatribuição de origem ou número.
Migration110 é NOOP funcional, core421 marcador, histórico preservado. Testes
cruzados reais do writer passaram em MariaDB10.11.14/PHP8.3.6:344 checks API
acumulados e131 CNAB acumulados. Provedor simulado; nenhuma homologação externa.

## Continuação111: vencimento API e observação mTLS

O incremento111 acrescenta PATCH oficial de vencimento, com intenção durável,
confirmação por consulta do título+conta, retry sem repetição de escrita incerta e
preservação do snapshot/data da fatura. A GUI mostra pedido e confirmação como
estados distintos. Acrescenta receptor local de baixa operacional com autenticação
por variáveis TLS nativas e pin de certificado por perfil; configuração gráfica,
revogação e lista de observações conservam a origem. Não há registro automático de
webhook remoto. Código10 é histórico adverso, sem estorno financeiro automático.

Detalhes, migrations e testes em [guia de alteração/webhook](banco-brasil-api-amendments-webhook.md).
A variável de infraestrutura, cadeia cliente BB e pin oficial continuam necessários
para aceitar entrega real; os testes sintéticos não comprovam handshake do ingresso
nem homologação. Outros tipos de alteração API, perfis, encargos, parciais/estornos
API, transmissão contratada240 e integração400 continuam técnicos pendentes. As
lacunas documentais400 estão explicitadas no guia400; o trailer CBR641 já está
resolvido e não foi reaberto.

## Incremento112: juros diários explícitos, movimentos e ISO congelado

A GUI permite perfil API distinto com juros BRL fixos por dia (tipo1), contratação
declarada e capability `api_charges`, conservando perfis isentos existentes.
Payload monetário exato e query de tipo/valor são conferidos. Movimentos oficiais
são consultados por conta/origem com janela/cursor explícitos e campos separados;
continuidade não é prova de ausência. Histórico adverso alimenta os vetos do
writer, sem efeito financeiro automático. [Guia112](banco-brasil-api-interest.md).

Originação nova240/API exige ISO congelado compatível com catálogo e BRL; consulta
de moeda histórica não é reclassificada por alteração de `currencies.name`.
Recebimentos com juros, descontos e tarifas ainda exigem componentes financeiros
canônicos; o diário426 é proveniência local e não será descrito como essa
escrituração. A ampliação427 de componentes será separada e não afrouxa o crédito
restrito existente. Homologação externa permanece distinta de código pendente.


## Incremento113 / núcleo427 — componentes no crédito assistido240

O novo perfil `settle_components_event` registra principal efetivamente recebido
em caixa separado de desconto/abatimento e encargos agregados observados. Requer
política explícita, confirmação independente do operador, origem íntegra e o mesmo
writer/recibo canônico, sem duplicidade entre o crédito240 anterior e o novo.
A confirmação não autentica o upload nem executa uma operação bancária.

Saldo, fatura/PDF, portal, API, relatórios, diário e guards de emissão usam
`CompanyInvoiceBalance`. Exemplo100 com pago95, encargos5 e extinções10 resulta
em caixa90 e dívida extinta100; não chama100 de recebimento. O perfil exige
tarifas/IOF/outros zero, líquido igual ao pago e caixa positivo.

[Contrato e consumidores](companies/bank-components-operations.md) e
[validação427](companies/pr45-validation-427.md) distinguem execução local/CI e
homologação externa. BB113 é migration NOOP nova; núcleo427 cria o schema aditivo,
sem modificar histórico. Crédito API parcial/ajustes, reversão própria dos novos
componentes e demais fórmulas/perfis continuam pendências técnicas, separadas dos
bloqueios documentais400 e da homologação externa de todos os canais.


## Incremento114 / núcleo428 — declaração de reversão externa

O operador pode registrar reversão externa integral de um recibo240 composto427,
com comprovação independente, referência, motivo, data válida, valor pago integral
restituído e principal integralmente restabelecido. Requer capacidade própria
external_reverse, conciliação, fatura/view e payments/delete. A fonte final e o
writer canônico confirmam recibo, status, revisãoBB, auditoria e journal juntos.
Native cash e componentes originais não são atualizados nem excluídos; o saldo
projeta valor efetivo e os leitores mostram original/revertido separadamente.

[Guia operacional](banco-brasil-declared-reversal.md) e
[relatórios](companies/declared-reversal-reporting.md) descrevem o perfil.
Nenhuma transação de estorno é enviada ao BB, e declaração não autentica banco.
CNAB44 permanece evento de cheque devolvido em revisão, sem autoridade genérica.
Reversão parcial, recibos API/outros canais e tarifas/IOF/outras equações seguem
pendências técnicas. Não há homologaçãoAPI/240/400. BB114 é NOOP funcional novo;
núcleo428 cria o recibo aditivo, preservando todo o histórico.
