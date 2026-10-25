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
não equivale a aprovação. A matriz é fail-fast e os recibos ficam três dias.

O gatilho temporário de push aceita somente `fix/native-company-experience`, além
dos gatilhos permanentes de PR e push nas bases. Deve ser removido após o primeiro
checkpoint focado aprovado; a PR completa mantém todas as validações permanentes
existentes. Este workflow não substitui suites anteriores nem declara cobertura
integral, fidelidade de todas as telas ou homologação financeira.

O instalador SQL e o workflow Asaas anteriormente bloqueados não foram alterados.
A migration 474 é aditiva e aplica backfills com origem comprovada; qualquer falha
impede confirmar tracking. Não apaga empresas nem cadastros. A CI de instalação,
upgrade, retomada e execução integral continua obrigatória no SHA final.
