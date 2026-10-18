# Calendário de faturas recorrentes

## Causa e comportamento

O CRON somava meses com `strtotime`, que transborda datas inexistentes. Por exemplo, 31/01/2025 + um mês resulta em 03/03/2025. Além disso, o vencimento recorrente repetia a quantidade de dias entre emissão e vencimento da matriz; isso é um prazo em dias, não um dia fixo.

A emissão mensal agora mantém o dia da fatura matriz (28, 29, 30 ou 31), usando o último dia do mês quando necessário. Fevereiro não substitui a âncora: janeiro/31 → fevereiro/28 → março/31. A emissão anual preserva mês e dia, incluindo recuperação de 29/02 em ano bissexto. Intervalos diários e semanais continuam contando dias/semanas.

## Opções de vencimento

- **Manter prazo original em dias**: padrão de todas as faturas existentes e novas. Mantém o comportamento anterior. Uma matriz 26/01 → 05/02 tem dez dias de prazo; a renovação 26/02/2025 vence em 08/03.
- **Manter dia fixo do vencimento**: opção explícita na fatura matriz. Mantém o dia do vencimento e a diferença de meses entre emissão e vencimento originais. Emitir em 26/01 e vencer em 05/02 resulta em 26/02 → 05/03, 26/04 → 05/05 e 26/12 → 05/01. O vencimento tem sua própria âncora e seu próprio ajuste de fim de mês.

O modo dia fixo aplica-se aos ciclos mensais/anuais. Em ciclos diários/semanais, permanece o prazo em dias. Sem vencimento na matriz, continua valendo `invoice_due_after`. Não há ajuste automático por fins de semana ou feriados.

A opção usa as datas da matriz como configuração. Alterar apenas a opção não altera datas ou cobranças já emitidas. Escolher dia fixo em matrizes existentes exige revisão das datas originais; não há conversão automática.

## Histórico, retomada e segurança

- Não são regravadas faturas existentes, `last_recurring_date` ou cobranças do provedor.
- Histórico já deslocado não é recalculado retroativamente. Exemplo: matriz janeiro/31 cuja última emissão foi março/03 segue para abril/30 e maio/31. Não gera uma segunda fatura em março nem recupera fevereiro automaticamente.
- O CRON continua criando no máximo uma renovação por matriz em cada execução elegível, usando a data programada e respeitando ciclos, status e horário já existentes.
- Datas inválidas são isoladas por matriz com log de ID; não interrompem as outras faturas.
- Os cálculos usam datas civis UTC internamente para evitar deslocamento por timezone; a decisão de quando rodar continua usando o fuso configurado da instalação. Nenhum fuso de produção foi modificado.
- O bloqueio de arquivo existente do CRON continua preservado. Esta alteração não implementa idempotência distribuída nem elimina a janela de falha entre criação da fatura e atualização da matriz. Isso exigiria uma mudança transacional separada, especialmente porque hooks podem emitir cobranças externas.
- Nenhuma modificação de SDK, webhook, cancelamento ou exclusão Asaas faz parte desta correção. O boleto continua recebendo o `duedate` calculado pelo CRM.

## Banco e implantação

Migration 381 acrescenta `invoices.recurring_due_mode`, `VARCHAR(16) NOT NULL DEFAULT 'days'`. O instalador limpo contém a mesma coluna. A migration verifica sua existência antes de adicionar; não atualiza datas, valores nem estados de faturas.

Faça backup, aplique a atualização pelo fluxo normal do CRM e conclua a migration antes de liberar edição/emissão. O `ALTER TABLE` pode exigir lock conforme tamanho do banco e versão do MySQL/MariaDB; planeje janela apropriada. Para rollback de código, preserve a coluna aditiva e as escolhas registradas. `down()` deliberadamente não apaga dados; o código anterior ignora a coluna.

Sem deploy, merge, migração real ou cobrança de teste em produção nesta entrega.

## Verificação

`php tests/recurring_invoice_dates.php` executa testes sem banco/rede, usa o autoloader real, demonstra o overflow antigo e verifica as regras novas. Executado localmente em PHP 8.1.34 e 8.5.10 via WebAssembly: 4023 verificações aprovadas em cada versão. `php tests/recurring_invoice_cron.php` passou 30 verificações do método real com dependências simuladas; `php tests/recurring_invoice_schema.php` validou migration aditiva/repetível e default do instalador com banco simulado. A CI remota deve repetir os testes em PHP nativo.

A cobertura inclui 480 ciclos para cada âncora 28–31, fevereiro comum/bissexto, século não bissexto, dezembro/janeiro, ciclos trimestrais/anuais, fusos, invalidez, compatibilidade do prazo em dias e vencimento independente.

Homologação manual recomendada, somente em ambiente isolado com integrações desabilitadas: criar matriz mensal com emissão31 e conferir prévias de fevereiro/abril; selecionar dia fixo para emissão26/vencimento5 do próximo mês; confirmar que uma matriz antiga mantém o modo dias até escolha explícita; verificar que nenhuma fatura previamente emitida muda. Não executar o CRON de produção para validar esses cenários.
