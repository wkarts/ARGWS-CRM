# PR45 — estado da integração BB e Empresas

Código atual: núcleo4.1.2/412, BB1.0.7/107, Asaas1.4.8/148. A matriz
[atual](companies/pr45-status.md) e os incrementos406–411 abaixo substituem as
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

A página oficial aponta para `https://apoio.developers.bb.com.br/apis/5?versaoApi=2`,
sem OpenAPI utilizável nesta consulta. `https://api.bb.com.br/cobrancas/v2/swagger?`
retornou 403 sem AppKey. É necessária uma exportação oficial atual, sem segredos,
ou acesso público funcional à especificação. Não usar contratos de terceiros como prova.

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
