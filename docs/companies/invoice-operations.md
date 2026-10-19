# Faturas por empresa: operação nativa isolada

`admin/company_invoices` opera faturas de proprietário nomeado e persistido. É uma
rota deliberadamente isolada para principal/secundárias, com CSRF e token de aba
validados antes da hidratação. Não carrega Invoices_model, gateways ou hooks legados.
O caminho antigo continua reservado ao histórico NULL; não adota ou reclassifica
faturas antigas. O cadastro Empresas e o shell oferecem links conforme o recurso.

Fluxos implementados: emitir fatura com itens/múltiplos tributos percentuais por item, descontos e ajustes, listar,
consultar, editar itens/datas/observação enquanto não há movimento ou origem bancária,
cancelar sem apagar, baixar PDF autorizado e registrar recebimentos manuais parciais
ou finais. A UI distingue fatura de boleto/remessa e registro manual de confirmação
bancária. Referência de comprovante é obrigatória; não há consulta bancária implícita.

Criação exige invoices/create+view e customers/view. O cliente precisa ser próprio
ou ter identidade explicitamente compartilhada; isso cria somente a nova fatura da
empresa destinatária. Recurso payments/create não é inferido de invoices/view.
Master ainda precisa operar pelo proprietário correto. IDs cruzados não autorizam
leitura, pagamento, edição ou PDF. Colaborador sem membership/recurso é recusado.

A criação possui recibo durável (migration408) e uma só transação para fatura,
itens, impostos, sequência, identidade canônica emitente/destinatário e auditoria.
Retries retornam o mesmo ID/número; chave com conteúdo/ator/empresa divergentes
conflita. O allocator existente ganhou entrada que exige transação proprietária,
sem commit aninhado. Cliente proprietário não depende de um link de compartilhamento
consigo próprio. Perfil/endereço da empresa precisam estar completos nas Definições.
Novas empresas não recebem identidade, prefixo ou termos de outra por fallback.

Valores usam centavos inteiros e operações com limites de overflow; quantidade e
alíquota têm duas casas. Valor de item arredonda antes do tributo, também calculado
com inteiros. O DTO de PDF reconcilia itens e totalizadores. Edição exige revisão
atual sob lock, preserva número, cliente, moeda e snapshots fiscais. Documentos
anteriores à nova criação não são editados incidentalmente. Cancelamento conserva
registro/número e é recusado se houver pagamentos/créditos ou origem BB/Asaas.

`InvoicePaymentWriter::recordCompanyManual` é a entrada explícita para pagamentos
nomeados. Os métodos genéricos anteriores continuam recusando origem positiva.
Revalida ACL/owner/snapshot/estado e saldo sob lock, desconta créditos, impede
sobrepagamento, repete idempotentemente o mesmo recibo e recusa referência repetida
com nova chave. Pagamento, recibo, status e auditoria confirmam juntos. Falha na
auditoria/commit não cria sucesso falso. Não dispara notificações/provedores inline.
Cobrança com origem BB/Asaas exige conciliação própria; uma baixa manual não contorna
esse vínculo. O writer canônico Asaas e seus vetos permanecem preservados.

PDF usa o renderer TCPDF existente e DTO autorizado, sem App_pdf/tema/global options.
Depois do render, revalida owner/recurso e confirma a auditoria antes de liberar bytes.
Snapshot do destinatário permanece histórico mesmo após revogação do compartilhamento.

## Testes e limites

- `tests/companies/invoices.php`: tabelas nativas, CRUD, origem A/B, snapshots,
  reenvio, revisão, centavos/tributos, pagamentos, rollback, barreira bancária,
  recurso/revogação, histórico NULL preservado e geração real TCPDF.
- `tests/companies/invoice_concurrency.php`: MySQL, dois processos observados no lock,
  criação/recebimento concorrentes e revogação enquanto aguardam.
- `tests/companies_invoices_browser.cjs`: CRM HTTP/MySQL/Chromium, configurações,
  emissão A/B, retry, PDF binário, edição, recebimento parcial/final e cancelamento.
- Regressões do writer/entrypoints Asaas, BB, contexto e notificações continuam exigidas.

Resultados executados são os do SHA/run indicados na PR45. SQLite não prova locks.
Não houve provedor, transação bancária, entrega externa, merge ou deploy.

Desde o incremento417, o editor aceita até dez tributos por item, desconto fixo ou
percentual sobre o subtotal (antes/depois dos tributos) e ajuste positivo/negativo.
Antes dos tributos, desconto é distribuído pelos valores de item por maior resto,
com desempate pela ordem dos itens. Cada tributo arredonda meio centavo para cima.
O ajuste não muda bases fiscais. Total precisa permanecer positivo e dentro de
DECIMAL(15,2). O formulário recarrega todos os tributos/modalidade/valor; o PDF e
portal usam os totalizadores persistidos. Recibos de criação anteriores ao417
continuam aceitos para retry idêntico, com ator/empresa/payload conferidos.
`tests/companies/invoice_amounts.php` contém exemplos monetários e limites;
`invoices.php` testa A/B, rollback, edição e recebimento pelo writer canônico;
o navegador exercita o formulário, PDF e409 após troca de empresa. Ver resultados
executados no SHA da matriz, sem atribuir uma execução antiga a código novo.

Portal de faturas emitidas já possui lista/PDF próprio e permissão nativa explícita
(ver [portal](portal-recovery.md)). Permanecem pendentes créditos/refunds/estornos,
recorrência, envio de fatura/e-mail, anexos, relatórios agregados, API pública e
integrações dos demais módulos. Os gates correspondentes não foram removidos.
API BB, originação/remessas e conciliação bancária continuam frentes técnicas próprias;
esta operação manual não as homologa nem transforma retorno observacional em baixa.
