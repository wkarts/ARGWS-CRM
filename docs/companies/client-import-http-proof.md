# Cliente import/export — prova HTTP adicional ao incremento preparado

Esta evidência complementa `client-import-operations.md`, cujo registro inicial ainda dizia que a suite Chromium aguardava wiring. Os patches originais de cliente import/export e de cursor de despesas permanecem byte a byte intactos. A presente extensão acrescenta somente o selamento de `company_client_imports/index`, a navegação condicionada a `customers/view`, o bootstrap de teste e a suite HTTP complementar. Não abre importadores legados ou prefixos genéricos.

## Executado

Cópia nova `/tmp/pr45-client-imports-http/app`, PHP8.3.6, CRM/CodeIgniter real com mysqli, MariaDB10.11.14 na porta48324/datadir próprio e servidor HTTP48097. Chromium140.0.7339.186 executado com Playwright. Os testes consultam o banco descartável real por um shim de CLI SQL; não há mock de DOM, modelos ou serviços do aplicativo. Requests do navegador a outros hosts são bloqueados. Root/freeze429 e os ambientes dos demais agentes não foram editados.

O instalador HTTP nativo concluiu a instalação e identificou o administrador efetivamente criado como ID17. Depois disso, o bootstrap exclusivo do protótipo executou `CompanyClientImportSchema::ensure` duas vezes e validou o schema. O marcador nativo existente permaneceu **427** antes/depois. Isso demonstra ensure/replay aditivo, **não demonstra a migration oficial futura nem seu tracking**. A raiz ainda deve gerar a migration oficial sequencial e executar instalação/upgrade/tracking da composição final.

Scripts executados, ambos com exit0:

- `tests/company_client_imports_browser.cjs`, entregue no patch anterior: upload multipart JSON em A e CSV em B, preview sem criação, confirmação de duas identidades privadas, retry durável, CSV próprio, batch cruzado403 e POST com contexto antigo409.
- `tests/companies/client_imports_http_browser.cjs`, adicional: fluxo positivo A/B/**principal**, com download real pelo link da tela, CSRF específico e CSRF nativo do framework forjados403 antes de efeitos, batch de outra empresa403 nas três direções testadas, rota administrativa inexistente404, importador legado403 na empresa nomeada, ausência de erros JavaScript e preservação dos bytes/owner ausente do cliente legado sintético.

O fluxo usa documento vazio em parte das linhas para comprovar que idempotência não depende de deduplicação de CPF/CNPJ. A política de identidades distintas com documento vazio permanece preservada. Nenhuma identidade é compartilhada ou adotada pelo importador.

A evidência estruturada está em `evidence/client-import-http-prototype.json`, com empresas sintéticas, runtime, hashes do wiring e do patch original. Screenshots/CSVs sintéticos ficaram privados em `/tmp/pr45-client-imports-http/evidence`; logs em `results.log` e `http.log`. O runner forneceu somente o caminho do Chromium disponível, preservando todos os asserts. O código do aplicativo executado conserva os hashes do overlay entregue.

## Integração ainda necessária

Aplicar o patch de cliente original, depois este wiring adicional; adicionar o ensure à migration oficial futura escolhida pela raiz e reexecutar o conjunto na composição final. Não reaproveitar a prova do marcador427 como tracking de434 ou de qualquer número ainda não gerado. O bootstrap CLI desta prova aceita somente o banco sintético `argws_upgrade_ci` no host/porta próprios127.0.0.1:48324; não é um provisionador de produção.

Esta validação não utiliza SMTP, credencial bancária, provider ou produção. Não altera a pendência externa de autoridade de provisionamento de uma instalação ambígua e não representa homologação bancária.
