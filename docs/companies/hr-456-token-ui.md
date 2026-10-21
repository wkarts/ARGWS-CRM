# Credenciais nativas de RH — complemento candidato 456

## Problema e correção proposta

`CompanyHrApi::SCOPES` declara 16 permissões distintas. O catálogo candidato já as disponibilizava, mas a view nativa `api_tokens.php` não possuía seus rótulos. A criação acessa `$labels[$scope]` diretamente: a página respondia HTTP 200 e apresentava caixas de seleção com texto vazio. A ajuda também não descrevia os recursos de RH.

O teste real reproduziu o primeiro rótulo vazio (`hr:read`) antes da correção. O delta acrescenta os 16 rótulos explícitos e uma seção de ajuda com os 13 endpoints do trait, seus limites de método, revisão, finalidade de arquivo, importação confirmada e origem bearer. A correção não concede permissões adicionais, não altera tokens anteriores e não modifica os serviços de RH.

Foi alterada somente a view do clone HTTP candidato. A view global principal permanece sob composição da raiz. A proposta para ela está em `runtime/evidence/hr456-token-ui-global/main-proposal.patch`, preservando os rótulos de Leads e Purchase já presentes no ponto de partida. Alterações posteriores na view devem ser compostas; o arquivo completo antigo do candidato não deve substituir a view principal.

## Prova executada

`tests/companies/hr_tokens_browser.cjs` passou **221 verificações** usando Chromium, PHP 8.3 e MariaDB reais. Os bytes do CJS são os mesmos no RED e no GREEN: SHA256 `1fa16c379eb235d178237d32b92f554d4a23af689b615ee6c3768f85080750af`.

Foram exercitadas A, B e principal (IDs 2, 3 e 1), pelo master nativo 17:

- Presença e texto dos 16 rótulos, além da ajuda de todos os 13 endpoints.
- Emissão pela GUI, persistindo exatamente os scopes selecionados, empresa e ator.
- Replay da emissão sem reexibir o segredo.
- Rotação pela GUI, preservando empresa/ator/scopes e revogando a credencial anterior.
- Bearer anterior recusado com 401 e novo bearer lendo positivamente os funcionários da própria empresa.
- Revogação pela GUI e recusa posterior de cada bearer com 401.
- Ausência de erros JavaScript; bytes dos produtos carregados inalterados durante a prova.

Cada request bearer abriu um contexto vazio, enviou `Cookie` vazio e descartou o contexto. O cookie CSRF nativo legítimo foi distinguido de cookie de sessão. A GUI foi fechada antes das duas medições: 49 sessões conservaram SHA256 `faf1bab4126ebc52d160d6c375e065613d8d08028db71a5b77a3695f8a8e3b65` durante as leituras; 50 sessões conservaram SHA256 `9437c72dec46a56fc75fe6a38bc20ddd06598e9755383aab57466588847ac669` depois da revogação pela GUI.

O helper `hr_tokens_http_fixture.php` é somente leitura. Conferiu 34 testemunhas de preservação, incluindo identidade, registros financeiros, RH legado e todas as tabelas próprias de RH presentes. Nenhum desses dados mudou. Tokens, recibos de emissão e auditorias legítimas da operação são efeitos esperados. Os segredos não foram salvos em logs, provas ou imagens.

O primeiro ensaio do helper falhou antes do navegador porque esperava uma atribuição literal de nome de cookie, enquanto a configuração nativa usa uma expressão explícita de constante/default. Esse ensaio foi preservado em `hr456-token-ui-harness-1` como falha de preparação. Ele não é o RED do produto, que está em `hr456-token-ui-red`.

## Proveniência e gate restante

O clone em `43374 / argws_hr_ci` continua uma instalação nativa **453** com o candidato RH M3. Este complemento não a converte em instalação456 nem substitui o gate oficial de migration/fresh/upgrade. Entre o RED e o GREEN, a única fonte de produto alterada foi `application/views/admin/companies/api_tokens.php`.

A view candidata passou de `e839bc822a39b41a35ebc4a29a864565dcbfc3888ef6bc12e13529103697bce2` para `1a4c82fe3a5d547f18c6b46e07f417d97babe8b2c706ff4bfb31c44e56140d0e`. A proposta sobre a view principal parte de `2492e1338abde8a9eac8f6f404379c2872270155f4ea08ff85bdd312f121475f` e produz `be8cc4fae8ddad4d73f7ea0570f913ec28e050aca9c64e05bebd0d9a6d31db3c`.

Nenhum commit, publicação ou ação externa foi realizado por este complemento. Não existe dependência externa para a interface local de credenciais. A evidência detalhada e os hashes estão em [hr-456-token-ui.json](evidence/hr-456-token-ui.json).
