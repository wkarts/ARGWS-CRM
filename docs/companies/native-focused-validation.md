# Validação interna da remoção da área paralela

Este checkpoint é **WIP, não pronto para merge ou implantação**. As rotas ainda
não adaptadas permanecem bloqueadas. A presença dos serviços e testes não significa
que a remoção integral de `company_context` esteja concluída.

O workflow `Native company experience focused` executa o manifesto explícito
`tests/ci/native-company-focused.json`: testes de serviços/controladores em SQLite,
DDL e projeções em MySQL descartável, contratos Node e renderização das views
nativas em Chromium. As versões PHP 8.1 e 8.5 usam imagens públicas por digest.
Nenhuma credencial, instalação, empresa real ou provedor é utilizado.

Cada comando registra resultado e duração; a primeira falha encerra a fase.
Warnings, notices, depreciações e erros PHP também reprovam. Cancelamento ou falha
não equivale a aprovação. A matriz conserva as evidências das duas versões quando
uma delas falha (`fail-fast: false`); cada fase individual continua fail-fast. Os
recibos ficam três dias.

Fixtures SQLite que executam outro teste como seu primeiro `require` estático e
incondicional continuam listadas no manifesto, mas o teste interno não é iniciado
uma segunda vez como processo separado. Includes condicionais/dinâmicos e ciclos
permanecem independentes. Contratos já pertencentes a um workflow de domínio não
são repetidos pelo manifesto focado; um teste unitário mantém essa separação.
Cada inventário possui um único owner: limites e superfícies operacionais em
`issuer-foundation`, superfícies de UI em `company-native-ui` e censo de ações
neste workflow focado. A instalação do Node para recompilar assets e cada censo
executam uma vez por SHA, sem retirar a matriz funcional PHP 8.1/8.5. Uma nova
atualização da mesma PR/ref cancela somente a execução anterior ainda em andamento.

Os gatilhos permanentes são PR e push em `main`/`develop`, além da execução manual.
PR e push pós-merge continuam sendo contextos de código distintos conforme o
contrato da base CI; a otimização remove repetições dentro de cada execução, sem
usar sucesso anterior como aprovação de outro SHA. Este workflow não declara
cobertura integral, fidelidade de todas as telas ou homologação financeira.

O instalador SQL e o workflow Asaas anteriormente bloqueados não foram alterados.
A migration 474 é aditiva e aplica backfills com origem comprovada; qualquer falha
impede confirmar tracking. Não apaga empresas nem cadastros. A CI de instalação,
upgrade, retomada e execução integral continua obrigatória no SHA final.
