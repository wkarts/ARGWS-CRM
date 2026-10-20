# API privada de despesas

Componente441, ainda não publicado, sobre os writers existentes de despesas e comprovantes. Custos, categorias e arquivos são privados à empresa e ao ator gravados na credencial; cookies e empresa selecionada na sessão não conferem autoridade. O master existente conserva suas permissões; colaboradores precisam de vínculo e capacidades explícitas. Vínculos opcionais com clientes/projetos continuam exigindo as capacidades correspondentes.

A interface **Definições → Credenciais de API da empresa** oferece sete escopos novos, sempre opt-in. Tokens existentes não recebem nenhum deles: `expenses:read`, `expenses:create`, `expenses:edit`, `expenses:cancel`, `expenses:categories:write`, `expenses:files:read`, `expenses:files:write`. Categorias exigem criação e edição de despesas; leitura de custos não autoriza bytes de arquivos. Alteração/cancelamento de faturas pertence a contrato separado.

| Rota exata | Métodos | Contrato |
|---|---|---|
| `company_api/expenses` | GET, HEAD, POST | Página própria; criação com `action=create,input` |
| `company_api/expenses/{id}` | GET, HEAD, POST | Detalhe; `action=update,revision,input` ou `action=cancel,revision` |
| `company_api/expense_categories` | GET, HEAD, POST | Categorias; criação com nome e `active` booleano opcional |
| `company_api/expense_categories/{id}` | GET, HEAD, POST | Detalhe; atualização com revisão atual; `active=false` arquiva |
| `company_api/expense_files/{expense}` | GET, HEAD, POST | Lista própria; upload `filename,content_base64` ou hide `file_key,content_hash` |
| `company_api/expense_files/{expense}/{file_key}` | GET, HEAD | Download autenticado, auditado e como anexo; HEAD não envia conteúdo |

POST exige Bearer, `Content-Type: application/json`, objeto JSON e `Idempotency-Key` hexadecimal de 64 caracteres. A revisão de custo/categoria é o `revision_token` retornado pela leitura; não é um contador escolhido pelo cliente. Não há DELETE físico. Listas usam cursor `after` e custos aceitam busca própria. Cada resposta declara `issuer_id`; mutações retornam o ID canônico e `replayed`.

O envelope JSON comum é limitado a 256 KiB antes de decodificar; arquivos usam até 3.000.000 bytes de envelope, base64 canônico e limite decodificado de 2 MiB. O validador nativo preserva PDF e normaliza PNG/JPEG (imagens até 512 KiB, dimensão de até1600 por lado e até2.000.000 pixels) em PNG. Até 20 uploads/20 MiB históricos por custo. Ocultar preserva bytes e histórico; retry de upload não reativa arquivo oculto. Pai forjado, inclusive da mesma empresa, não autoriza o filho.

Uma chave estável deriva de empresa, ator, operação e chave do cliente. O ledger financeiro API 439 já publicado e os receipts nativos comprovam a mesma gravação dentro da transação canônica. Não existe schema novo desta funcionalidade, nem escrita SQL alternativa do custo/blob. Token usado, auditoria, journal, receipt e custo/arquivo confirmam ou sofrem rollback juntos. Receipts canônicos arbitrários não são adotados como origem de API. Retry exige autoridade atual e não repete uma revisão ou numeração. Custos vinculados a conversão em andamento/concluída ou a tarifa Asaas preservam os vetos nativos; cancelar a fatura de destino não libera o custo faturado.

Os writers agora também revalidam conteúdo/referências depois da auditoria/receipt: isso fecha o caso comprovado no código anterior em que o download retornava bytes após seu próprio audit ocultar o arquivo. Falha tardia não libera bytes ou deixa efeito parcial.

As exceções CSRF abrangem somente os cinco padrões exatos de mutação da API, sem prefixo genérico, GUI ou URL filha de download. Os endpoints e escopos operacionais, inclusive cópias de projeto e rascunhos440, permanecem intactos.

Provas de componente executadas: SQLite e MariaDB real para custos/categorias/arquivos, controlador/protocolo, rollback tardio e vínculo real de despesa→fatura; MariaDB com dois waiters observados por cenário para retries, editar×cancelar, upload e revogação/parent/categoria durante espera. Fixtures usam dados sintéticos e nenhum transporte bancário. O teste Chromium está preparado para a integração final441 e não foi executado neste pacote. Instalação/upgrade nativos441, HTTP integrado e CI final dependem da integração serial da PR. Nenhuma evidência de componente é homologação bancária externa.


## Proveniência da retomada441

Os três patches históricos440 de componente, domínio e ligação central foram recuperados; seus testes de API/controlador/atomicidade/faturamento/concorrência permanecem sem alteração dos asserts. A aplicação sobre a composição440 precisou somente preservar as adições de drafts/cópias na lista CSRF e nos rótulos de scopes. Não foram necessários novos pré-requisitos compartilhados nem foram editadas migrations, versões ou workflows neste componente.

O teste novo `expense_api_resume_441_native_file.php` foi recriado nesta retomada. O mesmo arquivo falhou sobre o reader440 congelado emSQLite/MariaDB (bytes retornados após ocultação pelo audit) e passou sobre o reader441, com positivosA/B, pai cruzado recusado e rollback exato de custo/arquivo/recibo/diário/auditoria. Essa é evidência nova; não se atribuem logs anteriores ao fonte atual.

Resultados de componente da retomada:102checks cumulativos de API,154de controlador/protocolo,129de atomicidade,103de vínculo de faturamento e65do novo teste de arquivo emSQLite eMariaDB10.11.14;134na suíteMariaDB com sete cenários e dois waiters observados em cada. Contadores incluem fixtures e não devem ser somados. O manifesto de integração441 registra hashes, comandos, resultados e regressões efetivamente executadas. HTTP/Chromium, upgrade/tracking eCI doSHA publicado permanecem evidências separadas da raiz.

A fixtureHTTP exige núcleo441 realmente instalado, verifica schemas existentes e não modifica o marcador. O CJS configura os novos scopes pelaGUI e executa aAPI no CRM real, incluindo PDF/HEAD, rollback e conta restrita. Sintaxe aprovada não é execução desse roteiro. Para suspender a capacidade no futuro, fechar as entradas novas e preservar custos, arquivos, recibos, auditorias e journal; não apagar histórico para fazer retry. Sem novoDDL, a futura migration441 pode serNOOP documentada conforme política do núcleo e decisão da integração.
