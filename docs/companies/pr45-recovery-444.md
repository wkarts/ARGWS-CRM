# Recuperação444: ordem das respostas de tickets por empresa

Pai publicado: `57a80667e47e203bcb4e388fa3670824bb4bb4e1` (443). Núcleo4.4.4/444, BB1.2.2/122 (mínimo4.4.3), Asaas1.5.3/153. Os módulos não tiveram mudança funcional neste incremento. A associação ao SHA publicado e o resultado de CI são registrados na PR, sem autorreferência no commit. Não há merge ou deploy.

## Comportamento entregue pela fonte

O grupo próprio de configurações oferece `ticket_replies_order=asc|desc`, consumido pelos leitores administrativos e do portal. A ausência do nó usa `asc`, inclusive na principal, sem herdar a opção global. Mensagens internas continuam privadas até publicação individual explícita. A ordenação não altera autoria, corpo, estados, atribuição ou permissões.

A gravação preserva os demais nós JSON da empresa e exige settings.view/edit, ator e empresa ativos, revisão atual e autoridade da instalação. O serviço confirma o JSON completo e a ACL após a última auditoria. Os leitores reconfirmam preferência, autorização e bytes das respostas após seu último I/O; o portal mantém contato, permissão nativa5, ownership e publicação de pai/filho. O ORDER BY deriva apenas das duas constantes internas.

Não há recibo durável de formulário: revisão antiga continua409; mesmo valor com revisão atual não grava outra auditoria. Contexto obsoleto, CSRF, revisão e mudança tardia de configuração ou autoridade continuam sendo conflitos/negações explícitos.

## Versão e migration

O gerador oficial produziu4.4.4/444 e os metadados de distribuição. Migration444 é um marcador NOOP sem DDL ou DML. Não cria tabelas, colunas, constraints ou backfill; usa o settings_json já existente. As migrations e os schemas437–443/BB117–122/Asaas153 permanecem intactos.

Fonte produtiva:18.548 arquivos, SHA256 do manifesto `7d9a5f3eae930bd609d66aa3ec6073154cd7f79a53c790481f3366139865efba`. Apenas dois caminhos produtivos são acrescentados ao443: o serviço e a migration444. Os seis arquivos do componente conferem com a revisão independente.

## Validação local

O pacote isolado passou5suítesSQLite e3MariaDB, incluindo três cenários com dois processos esperando o lock. Revisão independente:35verificações adicionais em ambos os motores, com12hashes intactos; revogação tardia de master/admin/contato, vínculo do cliente ou publicação impediu bytes/sucesso e reverteu efeitos. Nenhum problema concreto foi encontrado nessa revisão dirigida; isso não certifica o restante do sistema.

A composição444 executou o histórico nativo real até443 e gravou configurações/respostasA/B/principal antes da captura. Body1992 e executor1464 verificações cumulativas passaram: marcador falho, UPDATE ignorado por trigger, readback, retry/replay e metadados/linhas históricas exatos. A preparação da fixture histórica executa o reconciliador real antes da captura, assim como o teste441: a coluna histórica mail_queue.issuer_id não é DDL444. O corpo444 por si não executa SQL.

Cinco execuções finais da composição passaram no manifesto18.548 sem alterações: nativebody, executor, settings187, leitores78 e concorrência205 cumulativos.143testesPython passaram em39,648s. Contadores cumulativos não são somados como casos novos. Relatórios: `evidence/resume444-final-composition.json`, `native444-validation.json` e evidências do componente/revisão. A prova HTTP possui relatório separado quando fechada.

O workflow Empresas conserva os374 comandos anteriores na mesma ordem e acrescenta cinco; BB conserva110. Fixtures de suporte não são suítes independentes. Os gates de versão/migrations devem passar no SHA exato antes de mover a branch.

## Histórico e pendências reais

Após a publicação443, sete suítes críticas repetidas e ligadas ao Gitblob do SHA57a8066 passaram, com18.546 arquivos produtivos intactos. Gates desse SHA passaram antes do CAS. CI443 segue separado dessas provas locais. CI442 registrou falha do BB por fixture440 que exigia runtime120 sob121; o forward443 preservou todos os cenários, pinou a migration120 e passou14unitários e quatro suítesSQL/controller/races.

Ficam fora deste incremento os demais campos de tickets, calendários, defaults de tarefas, demais settings/consumidores, ledger sem caixa e ciclo local após restauração em validação própria. Imagens/SMTP/AI/GoogleMail/Connect já entregues não são reclassificados como ausentes. Prioridade e seguidores de tarefas exigem contrato de domínio positivo e não são obtidos adicionando uma opção global.

CNAB400 financeiro/outbound continua exigindo os documentos de BRL85–86CBR641, isenção/juros herdados por conta/convênio/carteira/variação e explicação da divergência CBR64326–39 (14 posições versus9(013)v99). Auxiliares400 brutos já publicados não resolvem essas lacunas. API18/19 depende da identidade estável do crédito e semântica individual/cumulativa de valorCreditoCedente, componentes/datas/reversões. Demais operações nominais/protesto, componentes240 e consumidores ainda têm pendências técnicas específicas. Não há homologação ou operação externa nesta tarefa.

## Fechamento HTTP

Instalação limpa: suíte completa42,01s. Upgrade443→444:5,14s pelo executor real e27tabelashistóricas byte a byte preservadas; suíte56,32s. A/B/principal, funcionárioB cadastrado/concedido/revogado pelaGUI, ordem administrativa e portal, respostas internas ocultas, CSRF403, enum422 e revisão/contexto409 passaram. Seis falhas reais na última auditoria foram testemunhadas por contador MyISAM temporário:503, rollback completo e retry do mesmo formulário.18.534arquivosruntime conferidos antes/depois, semdiferenças.

O primeiro roteiro detectou inserção de defaults globais por App_gateway::initMode no primeiro GET da tela principal de Settings:134novaslinhas no bancoFA8 e104 no fresh, que já possuía30defaultsAsaas. Nenhuma opção existente foi alterada. Binlog e reconstrução readonly exata dos hashes demonstraram a origem. O roteiro final faz esse GET nativo antes de capturar a referência, sem excluir opções do assert: todas as opções e cinco tabelas financeiras ficaram iguais durante as operações próprias. A produção não foi modificada para corrigir o teste. Diagnósticos e roteiros completos finais estão em `pr45-validation-444.md` e `evidence/http-resume444.json`.

Smoke79 conserva o prefixo78 anterior na mesma ordem; lintsPHP/Node/shell e ambiente2/2passaram. O loteDocker integral depende deCI; os testes locais usaram PHP/MariaDB/Chromium reais. As provas não equivalem a homologação, merge ou deploy.
