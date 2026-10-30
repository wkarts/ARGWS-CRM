# Validação429 — API operacional de subrecursos

Sobre428 publicado0f182e68b936fe53ab43d69cbd1157c4caa574d5, núcleo4.2.9/429,
BB1.1.4/114 e Asaas1.5.1/151 preservados. Migration429 NOOP nativa gerada,
histórico preservado.16 scopes opt-in novos; credenciais antigas não ganham acesso.

Arquivos privados em quatro domínios, marcos, dependências e self-follow usam
writers/ACL/guards canônicos, uso de token e auditoria na mesmaTX. JSON/base64
limitados antes do decode,2MiB/BLOB e quotas originais. Upload não publica no portal;
hide bloqueia bytes e retry não restaura publicação. HEAD não devolve corpo.

MariaDB10.11.14/PHP8.3.6 no composto: native429202, API244/controller167,
concorrência211 em32 cenários de dois processos, guard fatura428163. Contagens
cumulativas; não somar. Provas do protótipo não substituem upgrade/tracking/HTTP e
CI do fonte final. Rodada HTTP do fonte congelado aprovada; gates próprios usam o SHA do commit.
Nenhuma chamada/homologação bancária ou SMTP. Sem merge/deploy.

## Regressão real detectada pelo CI428

O SHA4280f182e68 falhou em Origem e remoção segura Asaas: o novo schema428
carregado pelo writer pressupunha APPPATH no bootstrap isolado. Reproduzido
localmente no teste original antes da correção (exit255); require relativo a
__DIR__ corrige o carregamento sem alterar DDL nem contornar os fixtures. Testes
writer84, entrypoints44, BBAPI24 e invoiceScope146 passaram após a correção. O caminho desse schema também
passa a acionar a suíteAsaas. HTTP42829/29 continua uma prova distinta; o CI428
não foi declarado aprovado. As demais matrizes e o CI429 permanecem próprios.

A tentativa do fixture asaas_payment_writer_mysql.php em MariaDB parou na consulta
MySQL específica performance_schema.data_lock_waits (tabela ausente); não é PASS
desse fixture nem nova falha do aplicativo. A suíte oficial exige MySQL8.4 e sua
execução no CI429 é necessária. Os quatro fixtures anteriores passaram11/24/48/46
checks em MariaDB. Não trocar o servidor observado no relatório.

Reexecução final MariaDB do composto429: native202, subresources244/controller167/
bootstrap95/races211(32 cenários), saídaAPI163 PASS. Os três workflows428 Asaas,
BB unit e scope de faturas apontaram a mesma dependênciaAPPPATH no schema; os
testes originais correspondentes passam localmente após a correção, sem mudar
fixtures. A prova real de MySQL8.4 permanece reservada ao CI429.

## Correção da orquestração CI descoberta no428

O smoke Bash428 adicionara quatro comandosNode sem repetir o ambiente de cada
comando anterior. O CI parou no portal operacional com URL vazia; o harness local
exportava ambiente global e por isso seu29/29 não exercitava o escopo Bash.429
corrige os quatro e o novo comando API. A regressão test_issuer_browser_environment
executa os blocos reais em Bash sem variáveis herdadas e stub apenas do executável
Node: RED5 falhas antes, GREEN todos os29 blocos depois. Não altera runtime nem
relaxa assert de URL/DB. O workflow real também executa essa regressão e todo smoke.

O fonte HTTP429 permanece imutável durante30 execuções; o script final de
orquestração e o novo testePython são exceções explícitas ao vínculo dos testes
com o manifestHTTP, conferidas pela regressão Bash própria/CI. Lista e ordem dos
browsers e todos os seus bytes funcionais são preservados. Não atribuir a correção
de escopo Bash aos cenários HTTP com env global exportado.

## HTTP429 final e vínculo com o commit

**30/30 execuções numa rodada limpa única**:29 comandosNode na ordem do smoke e
suplemento Settings, instalação/admin17, upgrade nativo ao429, replay NOOP2x e
quatro schemas retidos com tracking429 conferido. PHP8.3.6/mysqli, MariaDB10.11.14
e Chromium140; nenhum transporte externo. [JSON](evidence/http-429.json) e
[diff](evidence/http-429-runtime-diff.json). Manifest inicial=final:
`f6fd13439600077eeca37070655f6a861de6a3789b9b03af5bab575844e88693`.
Runtime/distribuição17.921 arquivos, digest
`cdde757ed4bb40b0edea8a1e11bdb6471bae9db055ae6a034a404bbb94b751f5`.
Zero diferenças nos arquivos versionados de produção em execução; três derivados
e adaptações de fixtures são discriminados. Todos os arquivos funcionais do
runtime e testes executados conferidos com os blobs do índice. As únicas exceções
em tests são o smoke de orquestração corrigido e a nova regressãoBash, executada
separadamente; comandos/ordem/bytes de browsers e fixtures são idênticos.

62 testesPython de release/pacote/convenção/inventários e a nova regressãoBash
passaram separadamente (63 testes). Lint19PHP/1JS, schema bootstrap, YAML3, bash e
inventários coerentes. Não inferir o CI429 a partir do HTTP local ou do CI428.

## CI429 terminal

16 workflows distintos no próprio SHA:15 sucesso/1 falha; [evidência](evidence/ci-429.json). Stacks falhou1064: o cliente mysql dividia CREATE TRIGGER composto no delimitador padrão. A prova HTTP local usou PDO e não cobria esse parser. Correção restrita ao helper de teste integra430, com prova realCLI RED/GREEN; nenhuma alegação de CI429 verde.
