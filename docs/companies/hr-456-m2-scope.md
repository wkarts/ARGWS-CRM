# RH456 — delta M2 de caminho bruto e prova de participante

Este delta complementa o candidato M1 preservado no ZIP de SHA256 `463101a36132e571b525f7a93834b61c7e65da8e2c50656f30d022ef1c0a6aed` e blob Git `8174aa68521bfd033c85f9d8a5271471cbcf84a6`. O ZIP M1 permanece intacto. M2 não altera as 18 tabelas, os serviços de domínio RH ou os 12 deltas globais propostos. A composição e a migração nativa456 ainda pertencem ao gate integrado.

## Problema reproduzido

`CompanyHrAccessScope::prepare()` do M1 aceitava segmentos canônicos entregues pelo router sem comprovar que `REQUEST_URI` e `SCRIPT_NAME` originais correspondiam ao namespace. O teste novo `tests/companies/hr_access_scope.php`, executado contra uma cópia byte a byte do M1, falhou no caso `/recipient-alias`: o router simulado forneceu `company_hr_access/index/hrco_<64 hex>`, e o escopo foi ativado indevidamente. O arquivo M1 tinha SHA256 `b893cf5d38246496639ad34469d45d06154f96263259270a84cfe14bd4da9e7b`. O log RED e seu manifesto foram preservados.

## Correção

O único arquivo de produção alterado é `application/services/CompanyHrAccessScope.php`, agora de SHA256 `03e845adb5f7e885f0c4feea709c74cf904f4537f012cb5a3454e66defb0da1f`. A autorização do namespace exige caminho bruto exatamente igual aos três segmentos permitidos, com o diretório real de instalação derivado de `SCRIPT_NAME`, admitindo o front controller `/index.php` explícito ou omitido. Caixa alternativa, caracteres codificados, barras duplicadas, aliases, fragmento, caminho base diferente, sufixos e segmentos `.`/`..` não são adotados. O segmento extra `0` também é rejeitado explicitamente.

O reconhecimento antecipado continua amplo para impedir que uma tentativa malformada dessa família carregue módulos legados. Ele não concede autoridade: somente `prepare()` pode ativar a superfície, depois de comprovar router e caminho original. O segredo continua sendo validado pelo serviço de participante, com empresa, propósito, destinatário, expiração, revogação e revisão próprios.

## Evidências novas

| Prova | PHP8.1.2 | PHP8.3.6 | Conteúdo |
|---|---:|---:|---|
| Raw scope independente | 43 | 43 | Caminhos legítimos, base e front controller; aliases, codificação, caixa, sufixos, contexto inválido |
| Bootstrap/autoload real | 296 cumulativos | 296 cumulativos | Preserva os 285 anteriores e acrescenta 4 caminhos positivos e 7 vetos antes de qualquer hidratação |
| Lint dos quatro PHP do delta | PASS | PASS | Classe de escopo e três testes/fixtures PHP |

O browser completo `hr_browser.cjs` passou novamente no app descartável `argws_hr_ci`, porta43374, com os 12 grupos de cenários do candidato: A, B e principal; ciência e treinamento de participante com resultado100%; material privado; arquivos e PDF; GUI/portal próprios; importação revisada; API real; revogação de origem; preservação de identidade e dados anteriores; nenhuma exceção JavaScript e layout de390px sem overflow. Os screenshots de ciência e aprovação foram inspecionados visualmente.

O primeiro replay do browser expôs uma limitação do harness: a base reutilizada já tinha mais de uma página de25 avisos, e uma única chamada ao worker não garantia que o aviso recém-criado tivesse sido examinado. A produção permaneceu inalterada. `hr_http_fixture.php` ganhou a ação finita `worker_for`, que percorre no máximo um ciclo delimitado pelo maior ID observado, comprova a seleção do aviso específico e confere seu estado. O browser passou a exigir publicação do aviso correto e, após revogação do token originador, exame real com estado ainda pendente e sem leitura. A asserção anterior de revogação não foi suprimida: ganhou comprovação do objeto examinado.

O manifesto e os logs estão em `docs/companies/evidence/hr-456-m2-scope.json` e no diretório homônimo. Os cinco paths de código do delta são a classe de escopo, `hr_access_scope.php`, `hr_bootstrap.php`, `hr_http_fixture.php` e `hr_browser.cjs`.

## Limites desta prova

A fixture HTTP continua sendo uma instalação nativa453 com as tabelas RH candidatas instaladas explicitamente. O resultado não representa migração nativa456, execução da composição global final ou publicação de commit. As provas SQL de domínio do M1 continuam referenciadas, pois a mudança de produção é restrita ao escopo de roteamento. O gate integrado deve executar novamente a composição completa e seu fluxo CLI conforme a matriz.
