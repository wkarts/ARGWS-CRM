# Validação438 — horas, discussões, recorrência e conectores

Composição posterior à437, ainda sem publicação ou resultadoHTTP/CI final. Núcleo4.3.8/438 gerado pela ferramenta oficial; BB1.1.8/118 pela alteração de endereço operacional; Asaas1.5.3/153 preservado. As migrations436/437/116/117/152/153 e anteriores são mantidas. A438 adiciona15tabelas por5helpers;118 é uma nova migrationNOOP que valida o contrato anterior, sem reescrever seu DDL.

Inclui edição/respostas/anexos privados das discussões, seleção de timers fechados e faturamento de horas de um projeto, recorrência de faturas válidas com ciclos explícitos, ConnectAPI próprio para fila de avisos e reinício com observação autenticada, e endereço operacional BB confirmado por consulta posterior. As horas confirmadas permanecem alocadas após edição/cancelamento da fatura; retirada de reserva só existe antes do recibo canônico. O recebimento financeiro e o endereço fiscal imutável não são modificados peloPATCH de endereço.

Os pacotes de origem possuem testesSQLite/MariaDB/Chromium sintéticos próprios. Esses protótipos não comprovam tracking438/118, a composição final, entregaWhatsApp/SMTP nem homologação bancária. A rodada composta e o executor nativo são registrados separadamente antes da publicação. Operações incertas não são reenviadas automaticamente; uma observação anterior à tentativa não autoriza outra intenção de reinício.

Ainda são incrementos separados: timers parciais de tarefas avulsas/lotes entre projetos, cópia integral dos campos nativos de faturas e rascunho, política recorrente condicionada à quitação, integraçãoPusher privada, pareamentoQR e ciclo financeiro após reembolso. Falta de código nesses fluxos não é classificada como homologação externa. Os contratos bancários sem definição suficiente permanecem documentados por canal, sem inventar layoutCNAB400 ou IDs de movimentos.

Não houve merge, deploy, mensagemWhatsApp/SMTP real, cobrança ou remessa real. Implantação futura exige backup consistente, núcleo antes do módulo118 e confirmação do tracking nativo; rollback deve restaurar banco e código coerentes e preservar evidências financeiras.
