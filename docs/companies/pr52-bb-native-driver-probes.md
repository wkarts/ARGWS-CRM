# PR52 — consultas de schema no driver real BB

Base remota92901a8690a54359e0394403c3764431aa6ca758; PWA e histórico preservados; PR em rascunho. Sem operações bancárias externas.

## Causa confirmada e correção conjunta

BB [40431561412](https://github.com/wkarts/ARGWS-CRM/actions/runs/40431561412), jobs121950213174/MySQL e121950213473/MariaDB: falharam no primeiro ReturnArchiveSchema::upgrade do teste com o mysqli original. O código de929 assumia que row_array() devolve[] na ausência. O fonte real system/database/DB_result.php devolveNULL; o adapter PDO devolve[]. Foi erro desta implementação, sem relação com capacidade local.

Foram lidas as76 bibliotecas PHP do módulo, no SHA929. Encontradas exatamente três classes com comparações row_array()===[]/!==[]: ReturnArchiveSchema, CheckoutSchema e ApiOriginPolicy. As três usam agora result_array() para distinguir ausência; tanto CI3 quanto PDO devolvem[] para uma lista vazia. Isso corrige também a criação de checkout/vínculo da API sob o driver original, sem liberar tabelas incompatíveis nem refazer DDL em replay.

O teste de mysqli usa a classe CI_DB_mysqli_driver original e agora exige explicitamente NULL da linha vazia, três criações aditivas exatas e zero DDL na repetição das três classes. Um contador registra somente o número de comandos DDL, sem SQL/credenciais/payloads. Todos os testes anteriores de concorrência por dois processos, duplicidade, conflito e transação externa permanecem. SQL novo ainda depende da execução do CI.

Conforme AGENTS.md, BB1.3.2 e nova migration132 NOOP com validação dos três contratos existentes. Migrations101–131 preservadas. API e CNAB continuam meios independentes; esta correção não anuncia CNAB400 operacional ou homologação e não altera contas/origens/eventos/pagamentos.

## Calendário: falha ainda aberta

Stack [40431561339](https://github.com/wkarts/ARGWS-CRM/actions/runs/40431561339), job121950212515, ainda recusou409 o feed depois da alteração de metadado. Não houve marcador de rejeição do controller no log filtrado. Não se declara o feed corrigido.

Acrescentado diagnóstico de limite anterior ao controller para as quatro ações finitas de Utilities, somente web e com enum fechado de motivos, sem valores/tokens/IDs. O smoke classifica a resposta por formatos conhecidos (JSON de recusa/contexto alterado/contexto negado/outro) e presença booleana do header de contexto; não persiste corpo, headers ou valores de tokens. O coletor limitado aceita também esse marcador. A assertion HTTP200 e todos os guards permanecem.

## Verificações e pendências

Na base929, BB unitários8.1/8.5 e checkout passaram; bancos falharam conforme acima. Interface nativa passou; Focused8.1 passou e8.5 ainda executava na última consulta. Esses resultados não comprovam o incremento novo.

Verificações locais disponíveis: Node/DateEnv/callback, sintaxe JS/bash, dois testes Python e hashes dos três assets oficiais. PHP/Docker/banco ausentes; lint PHP, novas provas SQL/HTTP/browser não executados localmente. Inventários reconciliados por23 fontes existentes +131/132 contra o artefato completo76d9ba4, com hashes-base conferidos:15084 fronteiras,4610 fontes operacionais,647 itens no auditor, prontidão false. Geradores oficiais do CI devem confirmar; não é alegada varredura física completa.

Retirada integral da aplicação paralela continua incompleta (48 controllers/51 views com shell). Configurações/calendário/Staff completos, demais integrações, CNAB400 vinculado às faturas, retenção de rejeitados, homologação e PWA instalado/standalone continuam sem aceite integral. Nenhum arquivo protegido, migration antiga, repositório PIGE360 ou serviço externo foi alterado. Sem merge, deploy ou force-push.
