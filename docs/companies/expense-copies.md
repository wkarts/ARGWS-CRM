# Cópia revisada de despesas

A interface própria permite revisar a origem e confirmar um custo novo, com identidade e numeração próprias. A seleção inclui todos os campos atuais da despesa; referência ausente precisa ser substituída ou retirada explicitamente. Exige acesso atual de consulta e criação à empresa. Fonte cancelada pode ser revisada, sem restaurá-la. A cópia não transporta arquivos, cobrança, recorrência, tarifa Asaas, recebimentos ou origem bancária. Faturar a nova despesa é outra ação canônica explícita.

O serviço usa exclusivamente o writer CompanyExpenses na mesma transação, com recibo próprio e o recibo nativo. Retry exato retorna o mesmo ID, inclusive após edição, cancelamento ou faturamento legítimos. Contexto, grants, referência, origem e destino são confirmados após as últimas gravações. O recibo também confirma tudo após sua auditoria de consulta, antes de retornar dados.

## Schema e recuperação

Um novo company_expense_copies e índice UNIQUE expense_copy_parent(issuer_id,id) em company_expenses, necessários aos pais compostos por empresa. FKs ligam empresa, ator, fonte, destino, recibo nativo e auditoria exata. Não há adoção de registro antigo ou NULL. Replay verifica metadados e a falha parcial de DDL é retomável sem avançar tracking indevidamente. O código foi reconstruído: os logs de protótipo440 não recuperavam fontes e não são prova desta implementação.

## Confirmação final V2

A revisão reproduziu no V1 que a última auditoria da cópia podia alterar o valor do evento financeiro canônico: a operação confirmava e o leitor do diário rejeitava a evidência corrompida. O V1 e o RED foram preservados. O V2 captura e confirma o evento original pelo decoder canônico, campos monetários/data/identidade, auditoria de criação e auditoria do diário; confere novamente após todas as gravações do adaptador e após a última auditoria de leitura. Não introduz um segundo writer ou evento fictício. Retry após edição/cancelamento legítimos continua retornando a identidade já criada.

O novo entrypoint expense_copies_resume_442_effects.php verifica alterações tardias de valor, hashes, sequência, exclusão de evento, autoria/metadados das auditorias e leitura após auditoria. São163checks cumulativos em SQLite e MariaDB. V2 congelado:13execuçõesSQLite e15MariaDB aprovadas, incluindo oito corridas com dois waiters e seis regressões em cada banco. A primeira autoria V2 usou source_id inteiro no metadata esperado; o writer normaliza como string, corrigido no adaptador antes do freeze. Nenhuma fonte canônica histórica foi alterada para acomodar a prova.

O teste de faturamento posterior gera PDF real da nova fatura e extrai o texto com Poppler. Não existe uma rota própria de PDF isolado de despesa; o relatório não atribui essa funcionalidade ao produto. Native442, HTTP/Chromium e CI do SHA publicado são provas separadas. Evidências de componente em docs/companies/evidence/expense-copy-v2-*.json; o resultado final depende da composição publicada.

## Driver nativo e V3

O HTTP real encontrou uma diferença entre os drivers: mysqli devolve IDs numéricos como texto, enquanto o fixturePDO normalmente os devolve como inteiros. V2 rejeitava esses IDs antes de confirmar o efeito. V3 normaliza os IDs, sequência e valores monetários por seus tipos canônicos, preservando a comparação completa das evidências. O teste native_types usa fetch como texto e alterna inteiro→texto→inteiro, com retry, consulta e cancelamento sem recriar custos. São171checks cumulativos nos dois bancos. A matriz V3 final é14execuçõesSQLite e16MariaDB, sem avisos nem mudança do fonte durante a execução. O primeiro teste da mudança de driver também manteve corretamente o veto de uma revisão de referências capturada com outra representação; a nova cópia recebe nova revisão explícita. Não foi relaxado o controle de staleness para acomodar o teste.
