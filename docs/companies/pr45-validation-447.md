# Validação 447 — diário LOCAL, fundos posteriores e tarifa CNAB240

Base: b47d1666f7bb788bf29ead9c9aa03ff3f36221d1. Núcleo 4.4.7/447, BB 1.2.3/123 e Asaas 1.5.3/153.

Nesta retomada foram executados 26 comandos por motor em MySQL 8.0.46 e MariaDB 10.11.14 isolados, InnoDB/binlog habilitado e usuário sem SUPER. Cada comando e resultado estão em `evidence/resume447-current-mysql8.json` e `evidence/resume447-current-mariadb.json`. A execução foi serial dentro de cada motor. Os 18.574 paths produtivos ficaram iguais ao manifesto recuperado final, sem alteração durante cada rodada.

Cobertura efetiva: memória do controller com PHP 8.1.2 e limite de 128 MiB; tarifa integral com líquido distinto e leitores; 56 falhas de auditoria e 23 mutações entre replays; concorrência de tarifa, declaração/restauração/fundos e bearer; esquema LOCAL exato; PDF/portal/CSV; recorrência condicionada à posição canônica; corpos e runner nativos 446→447 com falha parcial de DDL, marcador falho/ignorado, metadados incompatíveis, retry e replay; instalação e upgrades BB120/121/122→123; Asaas e refunds; FKs de vendas; negativo SQLite real; defaults de tarefas, calendário, tickets e subrecursos operacionais.

O teste de memória completou as 255 asserções nos dois motores. A correção libera somente a trace SQL da fase anterior já concluída, preserva consultas subsequentes, todos os cenários e os 128 MiB. Os contadores de testes compostos são cumulativos e não foram somados.

Também passaram os 143 testes Python, 83 lints PHP/CJS e `bash -n tests/issuers_standalone_http_smoke.sh`. O gerador oficial foi reaplicado de forma idempotente. As migrations históricas e o módulo Asaas não foram alterados.

As provas HTTP recuperadas são qualificadas separadamente em `validation-447-http-final-v2.md` e `evidence/http-resume447-final-v2.json`: instalação nova, upgrade real do binário446 para447, histórico preservado, GUI A/B, tarifa e LOCAL com PDF/API/CSV e calendário original. O manifesto produtivo recuperado coincide com a composição desta retomada. Esses resultados locais não são checks remotos nem homologação com o banco.

A falha remota446 do calendário ainda não tem causa reproduzida. O novo diagnóstico preserva resposta/status/URL, screenshot e logs antes da limpeza. Não houve aumento de timeout, troca de seletor, retirada de asserções ou filtragem de histórico. Uma aprovação posterior não será descrita como prova retroativa da causa.

O SHA efetivamente publicado e seus checks são registrados na PR45 depois da confirmação. Permanecem pendências técnicas e contratos externos em `pr45-status.md`, `pr45-resume-20261020.md` e `banco-brasil-remaining-contracts.md`. Nenhum merge, deploy, force-push, tag, release manual ou operação financeira real foi executado.
