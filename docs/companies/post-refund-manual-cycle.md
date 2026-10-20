# Encerrar o ciclo devolvido e corrigido, e emitir outra fatura

Depois de declarar o reembolso integral externo Asaas e de registrar um novo
recebimento manual independente439, a correção desse novo recebimento é uma
reversão LOCAL integral440. Ela preserva os dois recebimentos e seus históricos.
Esse estado pode agora ser encerrado por **outra revisão e confirmação explícitas**.
Não é necessário apagar recebimentos, limpar a origem Asaas ou liberar o writer
genérico para uma terceira liquidação na mesma fatura.

O link **Revisar encerramento local após a reversão do novo manual** abre a revisão
nativa do pós-reembolso. O formulário específico de dois históricos exige contexto,
CSRF nativo, CSRF próprio, revisão atual, motivo e consentimento próprio. A operação
exige a autorização atual para revisar o provedor e editar a fatura. Uma consulta
GET final autenticada do recebimento ORIGINAL, na mesma empresa, conta, ambiente e
remote_id, deve ter observado REFUNDED nos últimos cinco minutos, sem evento adverso
posterior. O writer local não envia nenhuma chamada ao provedor.

A fonte final anterior `CompanyPostRefundAsaasSource` somente aceita os dois
históricos quando recebe a nova prova final
`CompanyPostRefundManualReversalCancellationProof`. A prova reconhece exatamente o
recebimento Asaas original integralmente devolvido e o único manual439 inteiramente
revertido440, com vínculos, entradas, auditorias e journal válidos. Não admite
pagamento extra, reversão parcial, crédito aplicado ou redução sem caixa. Os guards
genéricos por origem e os quatro guards da API financeira permanecem em vigor.
O caminho antigo, sem essa prova, continua recusando dois recebimentos.

O método canônico `CompanyInvoices::cancelAfterAsaasFullRefund` grava estado5,
auditoria, journal e recibo idempotente na mesma transação. A mesma chave e os mesmos
dados já confirmados não exigem nova consulta remota; chave diferente ou dados
conflitantes não produzem outra baixa. Revogação durante espera e falha de escrita
impedem confirmação parcial. Nenhum valor do Asaas original, do manual, dos refunds
ou das tarifas é alterado pelo encerramento.

Em seguida, **Revisar cópia para emitir outra fatura independente** abre o fluxo
existente de cópia, com revisão explícita de itens, valores e datas. A nova fatura
tem outro ID, número próprio, estado inicial em aberto e nenhum recebimento, origem
Asaas, refund, crédito ou declaração anterior. Receber essa nova fatura pelo fluxo
canônico normal é permitido. Não há emissão ou cobrança automática pelo link.

As leituras de equipe, contato, PDF privado, API e relatório conservam as magnitudes
originais e as duas reversões. O principal residual do documento cancelado é
rotulado como histórico, sem cobrança vigente. O recibo já gravado do novo
encerramento é validado contra a prova, auditoria e journal antes da liberação.
Adulterar ou remover a prova mesmo recalculando seus hashes não a converte numa
operação genérica válida. A leitura histórica não exige um grant Asaas ainda ativo;
a sessão, permissão de fatura ou contato e o bearer atuais continuam obrigatórios.

Este incremento reutiliza a tabela de cancelamento438/439 e o schema aditivo de
reversão440. Não acrescenta outra DDL e não modifica migrations históricas. A
integração440 do núcleo deve incluir o helper de reversão depois do schema manual439.

Os testes usam duas empresas, colaborador restrito, master da empresa principal,
dados sintéticos, transporte simulado, SQLite e MariaDB com esperas reais. Incluem
rollback, retry, expiração, revogação, corrupção de provas e liberação privada de
PDF real. Os arquivos `_browser.cjs` descrevem também a sequência HTTP/Chromium;
a execução integrada final deve registrar seu resultado. Lint não comprova GUI.
Não houve homologação Asaas ou bancária externa. Reversão parcial e uma terceira
liquidação na origem imutável não pertencem a este perfil implementado.
