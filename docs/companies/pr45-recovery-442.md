# Composição442 — despesas revisadas, AI própria e Seu NúmeroBB

Núcleo4.4.2/442, BB1.2.1/121 e Asaas1.5.3/153. A composição usa o gerador oficial e preserva todas as migrations anteriores. Source produtivo final:18.539arquivos, manifesto SHA25647b35c58b8c2f916d0ef1e8d4aebe0a454494bd77041f68c01f079f03d13143b. A publicação vincula a árvore exata a esse fonte; SHA/CI efetivos ficam na descrição da mesmaPR45.

## Comportamento

A cópia de despesa é uma nova declaração manual revisada, com referências/valores atuais e identidade própria. Usa CompanyExpenses, sem transportar anexos, recorrência, cobrança, tarifa de provedor ou caixa. Confirma os recibos, evento e auditorias nativos depois de suas últimas gravações; retry não ressuscita custo cancelado ou faturado. O driver nativo mysqli devolve números como texto: os campos tipados são normalizados antes da comparação íntegra, inclusive na transição de drivers. Guia expense-copies.md.

AI oferece somente resumo ou sugestão de resposta de chamado próprio, com modelo fixo gpt-4o-mini, configuração criptografada por empresa, preview/consentimento, outbox e resultado privado do solicitante. Não publica resposta nem envia mensagem ao cliente. Preserva uncertain sem reenvio cego; configuração, todas as pendências, fontes, grants e bytes são confirmados nas últimas fronteiras. O SDK/cliente reais foram exercitados por TLS local estrito, com CA/SAN/Host/SNI e endpoint/payload sintéticos delimitados, sem forwarding. Outros modelos, providers e consumidores legados permanecem fechados. Guia docs/COMPANY-AI-RECOVERY.md.

BB121 acrescenta alteração operacional de Seu Número, capacidade própria, pedido/outbox tipado, cadeia de aliases e confirmação posterior por consulta da conta. Revalida identidade, credencial, perfil, origem, posição financeira, grants e consulta recente antes/depois de cada I/O. Resultado incerto não reenvia. Preserva Nosso Número, controle, payload e fatura antigos. O limiteASCII1–15 é um recorte local documentado. HTMLpattern foi corrigido para o modo UnicodeSets dos navegadores, sem relaxar o validadorPHP. Guia docs/banco-brasil-own-number.md.

## Schema e origem dos fontes

Migration442 cria exatamente company_expense_copies e company_ai_requests, mais UNIQUE(issuer_id,id) no pai company_expenses. Sem adoção de dados antigos, permissão ou segredo. BB121 é NOOP com validação dos quatro schemas existentes; install/upgrade/replay usam o runner nativo com readback persistido. Downs conservam recibos e evidências.

Expensecopy foi reconstruído porque só logs do protótipo440 sobreviveram. AI recuperou o gerador/schema pela referência exata e reconstruiu serviços/testes ausentes; não foi alegada equivalência binária ao componente inteiro perdido. PacketsV1/V2 de despesas e S2/S3 deAI e seus RED foram preservados em manifests/deltas verificáveis; somente V3/S4 corrigidos integram esta composição. Os fontes437–441 publicados permanecem preservados no Git e nos registros por incremento.

## Testes e revisões

- Native442body1430 cumulativos, executor195: duas tabelas/índice exatos, metadata/DDL parcial, marcador falho ou não persistido, retry/replay e histórico byte a byte.
- Doze execuções finais compostas em MariaDB, com18.539hashes iguais antes/depois;143testesPython aprovados.
- ExpenseV3:14execuçõesSQLite e16MariaDB, incluindo seis regressões por banco, oito disputas com dois waiters, confirmação tardia163 e tipos nativos171 cumulativos. Revisão independente positiva de replay/privacidade/readback e RED mysqli foram preservados.
- AIS4:7SQLite/8MariaDB; últimas fronteiras155/157, seis corridas com dois waiters mais revogação durante I/O; SDK e limite de corpo/compressão com cURL reais. Todas as pendências são capturadas antes da primeira auditoria de configuração.
- BBV2: sete suítes MariaDB e contrato/SQLite/render, corridas/SIGKILL e regressões de janela/API16. Composto121:694baseline+359atividade, controller752, concorrência742, SIGKILL720, janela1013 e componentes532; ativação/install/120→121/tracking92. Contagens são cumulativas, não devem ser somadas.
- Instalação442 e upgrade441→442 reais, master17 preservado, quinze tabelas históricas byte a byte. RelatórioHTTP442 registra as rodadas finais,18.525hashesruntime, três novos fluxos e baselines, incluindo o SDK porPOST/TLS e a revisão financeiraAsaas real local. Não é homologação externa.

O lote local360comandos de Empresas começou no congelamento anterior e atravessou duas mudanças dirigidas (ExpenseV3 e viewBB). Seu relatório expõe essa transição; não é apresentado como prova de um único fonte imóvel. Os testes afetados finais têm binding47 próprio, e o CI do commit publicado é separado.

## Falhas anteriores e correções progressivas

CI439,440 e441 terminaram cada um com14workflows aprovados e duas falhas; não são16aprovações. Os registros terminais estão nas evidências. Poppler ausente, fixtureAsaas fixa152 contra153 e helper histórico439 sob440 foram corrigidos progressivamente441, preservando o caminho de upgrade. O CI441 encontrou uma expectativa antiga de18scopes;442 exige exatamente os18operacionais anteriores mais três escopos de subrecursos de despesas, mantendo os vetos de token antigo.

O CJS operacional439 também exigia403 no editor de projeto cujo contrato433 já retornava409 e estado vazio para pai estrangeiro. A correção442 verifica esse409 exato e ausência de conteúdo, preserva403 de tarefa avulsa e acrescenta403 dePDF de fatura estrangeira com contextoB fresco. Não altera produção nem aceita qualquer status genérico. Selectors de scope nos CJS apontam especificamente ao checkbox de criação, conservando tokens históricos e todos os asserts financeiros.

O reparo deCI436 já resolvido não foi repetido. Sem merge, deploy, force-push, tag, release manual ou operação bancária real.

## Pendências reais

SalesV4 está em composição futura com revisão/cópia e verificação estrita de FKs; não integra442. O perfilLOCALnão-caixaV1 foi rejeitado por revisão independente: confirmações tardias incompletas e dívida restaurada sem ciclo posterior utilizável. V2 está em implementação; não anunciar V1 como funcionalidade concluída. AuxiliaresCNAB400 são outra composição observacional ainda em revisão, sem habilitar remessa ou financeiro400.

CNAB400 operacional ainda precisa do códigoBRL CBR64185–86, regra contratual inequívoca de isenção/juros herdados por conta/convênio/carteira/variação e correção do totalCBR64326–39 (14posições/picture15). Trailer641 já resolvido. API18/19 exige identidade estável por crédito e significado individual/cumulativo de valorCreditoCedente, sem delta presumido. Nominal/protesto/negativação, outros componentes240/estornos parciais e demais settings/consumidores continuam técnicos, discriminados na matriz. Homologação externa não substitui nenhum código faltante.

Validação HTTP final: instalação nativa442 e upgrade441→442,15 tabelas históricas byte a byte preservadas, três novas GUIs nas duas bases; regressão Ops439 inteira53.33s e baselines Asaas/API16/CNAB240. Relatório `pr45-validation-442.md`; evidência `evidence/http-resume442.json`. SHA publicado será associado externamente para não criar autorreferência no commit.
