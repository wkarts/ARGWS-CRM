# Recuperação e validação dos incrementos 437–439

Retomada em20/10/2026 a partir do remoto `e3ffef4a66231562d232d0e6b545c49898d0f548`, núcleo4.3.6/436, BB1.1.6/116 e Asaas1.5.2/152. Base `main` preservada. A correção anterior de CI não foi refeita. O CI436 terminal aprovado pertence ao SHAe3ff, não aos fontes recuperados.

## Fonte recuperada e limites da recuperação

O ambiente atual preservou o checkout publicado e arquivos/patches, mas perdeu os metadadosGit locais, executáveis, diretórios temporários e154 testes novos não publicados da composição439. Um novo clone confirmou novamente o remoto. Todos os arquivos de produção do congelamento439S1 foram recuperados byte a byte mediante seu manifesto. Não foi possível recuperar integralmente o congelamentoS4 posterior; sete fontes de produção divergiam desse manifesto mais novo. Esses fontes não foram silenciosamente substituídos por alegações de testes anteriores.

As suítes publicadas no436 foram conservadas. Novos testes `*resume_439*` recompõem provas funcionais explícitas; não são apresentados como recuperação byte a byte das154 suítes perdidas nem como cobertura equivalente de todos os cenários originais. Dois arquivos de navegador/fixturePusher foram recuperados integralmente de um patch e conferidos com o manifestoS4. Os patches440 permanecem separados até integração, versão, tracking, interface e validação próprios.

## Correções encontradas na retomada

1. **ConnectAPI:** o callback de criptografia que devolvesse o próprio envelopeJSON podia armazenar um token em texto puro. O guard agora rejeita tanto o token bruto quanto seu envelope inalterado. TesteRED anterior eGREEN posterior preservados; reutilização explícita de segredo salvo continua sujeita à mesma empresa/endpoint/instância.
2. **Campos de itens:** a view compartilhada enviava descrição complementar, unidade e escolhas de item que o normalizador de vendas rejeitava. A normalização, criação e PDF de vendas usam agora o mesmo contrato canônico de linhas, preservando defaults omitidos e hashes antigos. Metadados são literais/escapados; item opcional não escolhido não entra no valor nem no PDF. Teste reproduziu o422 real pelo payload do formulário; provas de orçamento/propostaA/B e conversão usam TCPDF/Poppler reais. Não foram retirados campos do formulário para contornar o erro.

Essas correções são novas alterações sobre a recuperaçãoS1 e foram testadas como tal. Não são atribuídas retroativamente ao fonte antigo.

## Funcionalidades da composição

| Área | Código recuperado e validado | Limite |
|---|---|---|
| Recorrência de faturas | Dia/semana/mês/ano, intervalo customizado, ciclos0 ilimitados, worker limitado, pausa/cancelamento/revisão, notificação por origem e retomada antes/depois do recibo canônico | Política de quitação/rascunho e justiça do worker pertencem ao440, não a este incremento |
| Financeiro/API | Cópia de fatura com nova numeração e identidade atual, scopes opt-in para writers financeiros, refund integral declaradoAsaas, cancelamento e novo recebimento manual tipado após refund | Não transmite transferência/refund nem habilita parcialmente os perfis bancários não demonstrados |
| Operações | Discussões e versões privadas de projetos, publicação explícita, respostas/arquivos, faturamento avulso e alocação de horas/lotes no writer nativo | Cancelar/editar a fatura não libera a mesma fonte para cobrar de novo |
| Settings/integrações | MicrosoftSMTP/OIDC, fila documental e portal por origem, ConnectAPI/telefone/pareamento/fila e Pusher privado com recuperação | Identidades/transportes são sintéticos; nenhum e-mail, mensagem ou notificação externa real |
| Banco do Brasil119 | Inclusão/alteração de abatimento, endereço operacional, primeiro desconto, confirmação posterior por consulta e reversão integralAPI16 declarada | PATCH não confirma alteração nem aplica desconto financeiro; declaração não autentica operação bancária |

## Banco, instalação e versões

Núcleo4.3.9/439, BB1.1.9/119 e Asaas1.5.3/153. Os passos437/438/439 e117/118/119/153 são contíguos; migrations já publicadas permanecem imutáveis. As três migrations do núcleo acrescentam exatamente11,15e12 tabelas, respectivamente. Não há adoção automática de dados, credenciais, permissões, autoria, publicação ou movimento financeiro legado.

`tests/companies/native_resume_439.php` executou os corpos reais e replays emMariaDB, conferindo metadados/PK/índices/FKs e as linhas históricas completas. `--tracking` usa outro processo comCI_Migration/App_Migration/App reais: falha parcialDDL, falha de gravação do marcador, metadado incompatível, retry, tracking436→439 e reexecução confirmada. O teste de móduloAsaas conserva todos os casos históricos até152 e acrescenta pré-requisito437/tracking153/replay. O fixtureBB conserva116 e instala117–119 pelo executor nativo.

## Provas e atribuição

PHP8.3.6, SQLite, MariaDB10.11.14/InnoDB com binlog ativo, usuário de teste semSUPER e observação real de lock; Chromium140 para a rodadaHTTP. Os testes usam dados sintéticos e clientes de protocolo injetados. As versões remotasMySQL/PHP8.1/8.5 são verificadas pelo CI do SHA publicado e não são inferidas doMariaDB local.

- Calendário de recorrência:848 verificações; serviço98cumulativas e concorrência123cumulativas emMariaDB, com cinco cenários e dois processos aguardando cada lock.
- BB: contratos169, serviço343cumulativas, concorrência487cumulativas, reversãoAPI16:556cumulativas e metateste47. SIGKILL real de comandos e consultas:286/253cumulativas. Não somar as cadeias incluídas.
- Financeiro: cópias/API/refund e concorrência real; Asaas153:76 verificações pelo executor nativo. Relatórios de cada suíte estão em `evidence/`.
- Operações: faturamento e discussõesA/B, alocações e sete cenários de concorrência; linhas de vendas192cumulativas emSQLite eMariaDB, incluindo PDFs reais.
- Settings: provas reais de OAuth/JWT/PKCE, contexto criptografado, filas/worker, privacidadePusher, pareamento e revogação enquanto escritores aguardam locks. Testes novos e oito regressões de e-mail/Google foram executados separadamente.

Os contadores são os exibidos por cada suíte e incluem seus bootstraps; não são uma contagem de testes independentes nem um certificado universal de isolamento. Logs de tentativas falhas de preparação foram preservados. Diretórios temporários do instalador e do servidorMariaDB foram isolados por processo após uma colisão de infraestrutura. Bancos descartáveis encerrados são removidos após guardar seus logs, sem alterar fontes ou aplicaçõesHTTP.

O manifesto do congelamento produtivo contém18.485 arquivos e SHA256 `ba0016ea94b6bf84a0e9cb0f55389894776ae27a7817db1f687deab0019d22f9`. As provasHTTP ligadas a esses bytes estão em `pr45-validation-439.md` e `evidence/http-resume439.json`: instalação439 e upgrade nativo436→439 separados, sem reset dos dados financeiros na continuação gráfica. Runtime18.471 arquivos, zero diferenças de produção. O CI remoto pertence exclusivamente ao SHA consultado; aprovação436 não é atribuída ao439.

## Pendências técnicas e insumos externos

O escopo integral não é declarado concluído. Os fluxos440 em recuperação incluem quitação/rascunhos da recorrência, ciclos de reversão após refund, cópia de projeto/tarefa, avisos de suporte viaConnect e parâmetros adicionaisBB. Cada composição precisa de sua prova; somente existir localmente não a publica.

CNAB400 permanece indisponível. São necessários documentos/declarações primárias doBB que resolvam: **códigoBRL nas posições85–86 doCBR641**, **isenção ou parâmetros dos juros herdados para a conta/convênio/carteira/variação**, e **picture do trailerCBR64326–39 (14posições versus9(013)v99)**. O trailerCBR641 já foi resolvido. Amostras de remessa aceita e retorno do contrato específico ainda não foram fornecidas.

API18/19 ainda requer identidade estável de cada crédito e definição de `valorCreditoCedente` cumulativo ou individual antes da implementação financeira parcial correspondente. Tarifas/IOF/outros componentes e reversões parciaisCNAB240 continuam lacunas específicas. Estes pontos não são reduzidos à homologação externa. Consumidores não adaptados continuam com bloqueio explícito, sem adoção por `issuer_id` ou guard genérico.

Nenhum canalAPI/240/400/Asaas/SMTP foi homologado externamente. Não houve merge, deploy, force-push, tag, release manual ou alteração de outro repositório. Implantação futura exige backup consistente, código e migrations contíguos, conferência de tracking e smokeA/B. Não executar downgrade destrutivo; rollback exige restauração coordenada com preservação das intenções e recibos.

## Regressões e fixtures recompostas

A bateria inicial executou360caminhos PHP publicados:344saíram com0 e16 tiveram bloqueios explícitos. Quatro falhas iniciais foram ambiente/expectativa estática da versão, seis foram pré-requisitos ausentes de discussão nos fixtures de portal, uma foi ausência do namespace438 no fixtureCLI de despesas, quatro exigiam a conexãoAsaas dedicada e uma exigia os argumentos de módulo api/exports/einvoice. As correções preservam os asserts originais; os comandos afetados são repetidos com o ambiente e os argumentos completos e seus resultados finais ficam no relatório de regressões. Não se confunde essa tentativa inicial com uma rodada limpa.

O teste adicionalBB executa os métodos reais dos trêsPOSTs de alteração e consulta com requests/sessão adaptados, transporte sintético e writers canônicos, incluindo405/403/contexto/ator da sessão/idempotência. A provaGUI adicional captura os formulários reais; a simulação de confirmação bancária usa fixtureCLI e é descrita separadamente do dispatch do controlador.

Os workflows conservam os comandos já publicados e acrescentam os testes recompostos. O smoke conserva os57comandosNode existentes e adiciona oito provas gráficas439; a execução integralDocker dessa sequência fica para o CI remoto.

Resultado final local:359dos360caminhos PHP publicados passaram, com reexecuções registradas, além das três variantesapi/exports/einvoice do runner de módulos. O único caminho sem prova local conclusiva é `asaas_payment_writer_mysql.php`: exige o inventário de locks doMySQL8, ausente noMariaDB local. O teste original não foi modificado e permanece noCI.143testesPython e lint dos arquivos alterados passaram. Evidência em `evidence/resume439-regressions-final.json`; CI e homologação externa não são inferidos desses resultados.
