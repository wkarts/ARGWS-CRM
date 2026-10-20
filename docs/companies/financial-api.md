# API financeira por empresa

Os endpoints nativos `company_api` usam somente o cabeçalho `Authorization: Bearer crmco_…`. Empresa e ator vêm da credencial persistida, com grants atuais; cookies, seletor de empresa e token legado não os substituem. Os11 escopos abaixo são opcionais, explícitos e não são adicionados a tokens anteriores ou empresas futuras. Rotação conserva ator/empresa/escopos; a credencial é exibida uma única vez na GUI e somente seu hash é persistido.

| Método e caminho | Escopo | Operação nativa |
|---|---|---|
| GET/HEAD payments/{invoice_id}?after=0 | payments:read | Recibos e posição exata da fatura |
| POST payments/{invoice_id} | payments:create | Recebimento manual |
| POST payment_reverse/{payment_id} | payments:reverse | Estorno manual parcial/integral |
| GET/HEAD credits?after=0 | credits:read | Lista de créditos próprios |
| GET/HEAD credits/{id} | credits:read | Documento, entradas e saldo disponível |
| GET/HEAD credit_pdf/{id} | credits:pdf | PDF privado |
| POST credits | credits:create | Emissão pelo escritor canônico |
| POST credit_apply/{id} | credits:apply | Aplicação em fatura própria compatível |
| POST credit_unapply/{id} | credits:unapply | Desaplicação referenciada |
| POST credit_refund/{id} | credits:refund | Registro manual de reembolso |
| POST credit_refund_reverse/{id} | credits:refund_reverse | Estorno do reembolso referenciado |
| POST credit_void/{id} | credits:void | Cancelamento de crédito sem histórico |

Todos os caminhos acima têm prefixo `/company_api/`. Leitura de pagamentos exige invoices/view; criar exige payments/create, estornar payments/delete. Créditos exigem credit_notes/view e a capacidade create/edit/delete correspondente; emissão também customers/view, movimentos payments/create/delete e aplicação/desaplicação invoices/view. O escopo não concede esses grants. Não há DELETE de histórico ou escritor paralelo.

POST aceita somente objeto JSON UTF-8 com Content-Type application/json, até262144 bytes, profundidade32. Valores monetários, quantidades/preços e taxas de crédito são strings decimais com ponto; `amount: 1.0` numérico é recusado. Emissão recebe o formato nativo CompanyInvoiceAmounts (até100itens/10tributos por item), client_id, currency, date, note e descontos/ajuste opcionais. Pagamento recebe amount/date/reference/note. Movimentos recebem amount/date/reference/reason, invoice_id quando aplicável e parent_key no estorno de aplicação/reembolso. O credit_id/payment_id vem exclusivamente do caminho, sem sobrescrita silenciosa pelo corpo. Cancelamento envia `{}`. Data de movimento e quantidades obedecem às regras do escritor existente.

O cabeçalho Idempotency-Key é obrigatório em POST:64 caracteres hexadecimais minúsculos. Sua identidade é **empresa+ator+operação+chave**, sem incluir o alvo no derivador. Mesmo alvo/corpo retorna o recibo original (201 na primeira chamada,200 no retry); alvo ou valor diferente com a mesma identidade retorna409 antes de efeitos. Ordem das chaves de objetos JSON é normalizada; ordem dos itens continua significativa. Mudanças da representação de valores não são uma nova autorização. Trocar de operação constitui identidade distinta; rotacionar o token com o mesmo ator/empresa preserva a identidade. Estornar um pagamento impede ressuscitá-lo com seu antigo recibo, conforme o escritor canônico.

`CompanyFinancialApiGuard` é final e tipado, veto somente. Os quatro métodos financeiros aceitam-no opcionalmente, preservando todos os consumidores anteriores. Tabelas participantes são unidas antes da TX. O guard valida conexão/prefixo/ator/empresa/operação/alvo/chave depois de lockFinancialIdentity, usa o token/auditoria uma vez e revalida antes do commit. A tabela nova company_financial_api_requests contém chave, empresa, ator, operação, digest, alvo, primeiro token e instante UTC. Ela registra recibo sob a **mesma transação canônica**, inclusive cancelamento; não reutiliza os recibos de emissão de tokens. Falha na última inserção desfaz o pagamento/crédito, projeção, journal, recibos e auditoria. A evidência canônica é conferida de novo após essa última inserção. Upgrade cria a tabela após company_api_tokens/issuers_registry, fora das operações. Ela começa vazia, sem adotar legados. Migração/versão oficiais são responsabilidade da integração; ensure das fixtures é protótipo descartável, sem alegar tracking futuro.

Leituras bloqueiam identidade/proprietário antes de hidratar dados; filtro da empresa precede paginação. Lista de créditos ou recibos retorna até50 itens e next; after=0 (inclusive string HTTP) inicia, negativos/arrays/lixo são recusados. Créditos positivos sem snapshot canônico provocam revisão em vez de adoção. Saldo de pagamentos vem de CompanyInvoiceBalance, com ISO imutável, caixa original/estornado/efetivo, crédito aplicado, abatimento/desconto sem caixa e saldo a receber separados. PDF usa TCPDF e auditoria nativos, depois revalida token e documento antes de retornar bytes; HEAD faz a autorização e suprime bytes.

Erros não incluem IDs/nomes alheios, body ou credencial:401 sem bearer válido,403 sem grant/proprietário,405 para método incorreto,409 para conflito/revisão financeira,422 para formato/saldo a revisar,503 quando não é possível confirmar armazenamento/auditoria. Rotas com segmentos extras e aliases não caem no bootstrap legado. A exceção CSRF enumera apenas endpoints de mutação bearer; formulários administrativos conservam CSRF próprio+nativo. O hint pre-system é apenas veto ao carregamento de módulos opcionais, sem conceder autoridade.

Reembolso significa registro manual local; a API não executa transferência bancária, não autentica estorno externo e não homologa BB/Asaas. Origens desses provedores continuam vetando recebimento manual e correções incompatíveis no escritor existente. A API429 operacional, a API anterior de faturas/relatórios e consumidores GUI/portal permanecem preservados.
