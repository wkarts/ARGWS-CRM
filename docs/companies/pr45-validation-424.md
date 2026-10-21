# Validação do incremento nativo424 (4.2.4)

Base preservada: `7ff6a149bcb6b0a39e5ddb7be20a153a7c279632`, branch
`feat/banco-brasil-billing-cnab240`, PR45. Este incremento não conclui sozinho
o escopo integral do documento de continuidade revisão2.

| Fluxo | Implementação | Evidência executada neste incremento |
|---|---|---|
| Créditos e estornos | Emissão/snapshot, itens/tributos/desconto/ajuste, PDF privado, aplicar/desaplicar, registro de reembolso/reversão e estorno parcial/total manual no writer nativo | PDO SQLite; MariaDB10.11: writer86 cumulativos, leitor/PDF97, concorrência101; duas sessões observadas aguardando lock real, replay, excesso e revogação |
| Projetos/tarefas | Cadastro/revisão, responsáveis, checklist/comentário, timer, estados, origem e recibos | SQLite e MariaDB: serviços32, concorrência47, controller e renderer54 na rodada inicial; ampliação posterior nos próprios testes |
| Despesas | Categorias, lançamento/edição/cancelamento, centavos, vínculo cliente/projeto e comprovantes privados | SQLite e MariaDB: operações47, arquivos68, concorrência80 cumulativos |
| Configurações | Catálogo próprio, apresentação HTML sanitizada de e-mails, preferências de crédito e SMTP validado | SQLite/MariaDB: apresentação205 cumulativos, conteúdo consumido pela fila106/108; nenhum SMTP externo |
| Versão/migração |424 aditiva com replay, ownership NULL preservado, schemas de créditos/projetos/despesas | MariaDB real em créditos; 62 testes Python de release/empacotamento/inventários aprovados; lint PHP e diff sem erros |
| Navegador | Novos testes Playwright para créditos, estornos, projetos/tarefas e despesas ligados ao runner de instalação/upgrade descartável | Preparados; execução HTTP/MySQL/Chromium ocorre no CI do commit publicado, sem alegação antecipada de aprovação |

Os números são cumulativos por arquivo, com fixtures compartilhadas; não somar
como casos independentes. SQLite não comprova InnoDB nem concorrência. A rodada
local usa PHP8.3.6/MariaDB10.11.14; as matrizes remotas permanecem no workflow.

Todos os movimentos de reembolso/estorno descritos são registros contábeis locais
com evidência, não ordens financeiras externas. A origem bancária impede o writer
manual de substituir o fluxo do provedor. Não houve cobrança, devolução, SMTP,
certificação ou homologação bancária real.

Permanecem em implementação independente documentos/conversões e recorrências,
Asaas positivo, contratos/tickets, demais consumidores e expansão BB. Os inventários
conservam `operational_ready=false` e rollout bloqueado. Nenhum gate genérico foi
aberto só por existir esta vertical. Merge e deploy não foram executados.
