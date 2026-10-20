# Seu Número operacional por origem API

Implementação isolada sobre a composição440, ainda sem número de release atribuído. A integração deverá acrescentar o próximo núcleo e a próxima versão/migration nativa BB; este overlay não é um pacote distribuível versionado. Não houve comunicação bancária real, merge ou deploy.

## Contrato primário e recorte local

OpenAPI Cobranças3.2.2 capturada em `bb-openapi-440.json`, SHA256 `6767b098b13bc00e42700d80435a73e1f6ff4d31d7a28647926209a58d5f00d3`, origem <https://api.externo.bb.com.br/docs/v1/openapi/17395705/2>:

- `RequisicaoAlterarBoleto.indicadorAlterarSeuNumero=S` e `alteracaoSeuNumero.codigoSeuNumero` descrevem a alteração do identificador controlado pelo beneficiário.
- `RespostaDetalhamentoBoleto.numeroTituloCedenteCobranca` é descrito como equivalente ao Seu Número/numeroTituloBeneficiario enviado no registro. É o campo de confirmação, acompanhado da consulta independente da conta pelo Nosso Número.
- A captura não estabelece tamanho máximo de codigoSeuNumero. Este perfil local aceita exclusivamente1–15 caracteres A–Z,0–9 ou hífen para o novo identificador. Isso é uma restrição de implementação declarada, não um limite bancário universal. Expansão exige revisão explícita.

A operação não altera o nominal. Seu Número é diferente do Nosso Número20 posições, do código de controle do participante e da identidade fiscal da fatura.

## Fluxo e isolamento

`amendOwnNumber(issuer,actor,origin,intent,key)` cria uma intenção tipada `amend_ref` no outbox existente. O contrato exige `previous_reference`, `new_reference`, confirmação expressa, referência independente e motivo. O fingerprint vincula intenção, hash original da origem, estágio e perfil; a origem já vincula empresa, conta, ambiente, convênio, carteira e variação. Nenhuma busca de título/pagamento passa a usar um alias como chave.

A consulta anterior deve ser200 validada, recente até5minutos, NORMAL1, com hash da resposta e prova independente da conta. A referência anterior deve coincidir exatamente com a referência atual comprovada. A cadeia de aliases confirmados começa em `CRM`+ID da fatura; cada novo elo aponta para o anterior. Um identificador antigo não pode ser reutilizado. Histórico adverso, recebimentos/creditamentos locais e outra instrução pendente vetam a alteração.

Apenas `api_amend_reference` da empresa, visualização/edição de faturas e o contexto atual autorizam a ação. `manage`, `api_issue` ou outra alteração não concedem a nova capacidade. O controller aplica POST/CSRF/contexto/ACL; a rota participa da fronteira de contexto anterior à hidratação. A GUI solicita os valores e o contrato explicitamente e escapa todas as projeções.

## Estado durável e recuperação

Antes do PATCH são persistidos intenção, ator, chave, nonce e marcador `sending`, com revalidação de ator/permissões/credencial e posição financeira. OAuth e PATCH ficam fora das transações SQL. Consultas e Seu Número vinculam cada I/O ao ator/grants atuais, à claim, à credencial completa e aos hashes de profile/origem, documentos financeiros e observações bancárias. O guard transacional roda antes de OAuth, após OAuth no marcador sending, antes e após detalhe/PATCH e cada página de conta, e no commit final. Para nova referência, a consulta NORMAL e o histórico adverso são revalidados também depois de OAuth; expirar os 5 minutos ou chegar uma baixa impede o PATCH. Mudança após envio mantém resultado incerto e não autoriza retry cego. Revogação durante detalhe/lista impede nova chamada e a promoção do alias; após autorização atual, nova consulta independente pode recuperar a operação. O corpo liga exclusivamente o indicador de Seu Número; todos os outros indicadores sãoN.

ACK200 válido significa apenas pedido `validated`. O identificador novo só fica `confirmed` após detalhe e listagem da mesma conta comprovarem origem, valor nominal original, datas, identidade, contrato e novo identificador. Observar o identificador antigo deixa a intenção pendente; não prova que uma requisição incerta deixou de ser aplicada. Um valor estranho ou retorno a alias histórico gera revisão. A primeira prova de conta e a prova atual ficam registradas.

Chave idêntica e intenção idêntica retornam a operação; intenção/ator conflitantes não a substituem. Após envio, `unknown` e `sending` nunca reenviam PATCH automaticamente. Após expirar a lease, uma nova consulta independente pode reconciliar inclusive um processo morto porSIGKILL. Uma resposta tardia da claim antiga não pode sobrescrever a confirmação. Falhas antes do envio podem ser retomadas somente com autorização atual e a mesma intenção ainda elegível.

Os aliases e tentativas são preservados nas intenções/resultados e auditoria do outbox. A GUI de cada origem apresenta a referência original ou último alias confirmado, a cadeia completa e os pedidos com estado/ator/data/referência/motivo, exclusivamente na empresa autorizada. Não se reescreve `bb_api_origins.payload_json/snapshot_json`, Nosso Número, perfil, hash, código de controle ou documento fiscal.

## Financeiro e outros canais

A instrução e sua confirmação não criam pagamento, crédito, abatimento, receita ou estorno. O fence financeiro exige que toda alteração esteja resolvida com contrato/fingerprint e consulta própria, incluindo cadeia de aliases e prova da referência atual. O recebimento posterior continua passando pelo writer canônico e pela equação nominal existente; intenção pendente/corrompida mantém revisão financeira. Recibos antigos são preservados e uma fatura já movimentada não aceita nova alteração de referência.

O alias não autoriza relaxar o vínculo do retornoCNAB240 nem reescrever seus campos de origem. Um retorno cujo identificador divergir do contrato240 existente continua exigindo revisão/adaptação específica. O canalCNAB400 e dinheiroAPI18/19 não são habilitados por este trabalho.

## Banco e versão

SemDDL novo. `amend_ref` cabe no varchar12 de `bb_api_operations`; chaves, constraints e contexto existentes são reutilizados. Não há backfill nem promoção de perfil. O integrador deve adicionar versão BB e migrationNOOP nativa que valide os schemas existentes, além do incremento de núcleo contíguo e testes de tracking/replay do SHA final. Migrations históricas permanecem imutáveis.

BB operacional usa InnoDB e valida `information_schema`/collation/constraints. Não há adapterSQLite do módulo. O testeSQLite real comprova recusa antes do transporte e ausência de efeitos; não é uma alegação de integração BB positiva nesse driver.

## Evidências e limites

`banco_brasil_own_number_contract.php`: contrato/flags/ACK e fingerprint. `*_mysql.php`: nativoMariaDB, A/B, consultas, aliases, retries, incerteza, revogação, rollback, original imutável e fence financeiro. `*_activity.php`: reproduções RED→GREEN de webhook mTLS durante OAuth/PATCH, consulta que envelhece de297 para mais de300segundos reais, revogação após detalhe/lista/página, credencial/profile trocados e writer financeiro canônico concorrente. Esse writer recusa crédito de consulta anterior enquanto a nova está em voo; depois da nova validação aplica uma vez e faz replay idempotente. `*_controller.php`: métodos reais com requests/sessão adaptados, contexto, verbo, CSRF, ACL e pósconsulta. `*_concurrency.php`: dois processos e dois waitersInnoDB observados por cenário. `*_interrupted.php`: SIGKILL real após aceitação pelo provider sintético e recuperação somente por consulta. `*_sqlite.php`: recusa real sem efeito. `*_view.php`: render PHP real e escape. `*_browser.cjs`/`*_http_fixture.php`: roteiro A/B com configuração/formulários/projeções reais e transporte sintético, a executar antes da publicação final.

O transporte de testes é injetado e não autentica o banco. A fixture CLI do roteiroGUI chama serviços canônicos; não equivale a um POSTcontroller por rede. O testecontroller separado executa os métodos reais. Logs e contagens estão na evidência do pacote; contagens SQL incluem fixtures e não devem ser somadas. CI e resultados precisam ser vinculados aoSHA efetivamente publicado.

Rollback de aplicação não deve apagar aliases/auditoria nem restaurar silenciosamente o antigo identificador bancário. Um runtime anterior recusa o marcador BB novo. Uma reversão operacional exige nova intenção explicitamente revisada; não reutilize aliases históricos nem edite snapshots.
