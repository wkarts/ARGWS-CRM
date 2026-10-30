# Cópia revisada para nova emissão numerada

No detalhe de uma fatura própria, **Revisar cópia para nova emissão numerada** abre uma leitura sem emitir. A revisão mostra a origem, seu estado (incluindo cancelada), o destinatário atualmente autorizado, itens, tributos, desconto, ajuste, total e a identidade/termos atuais da empresa. As datas da nova emissão começam vazias e são obrigatórias; o funcionário confirma os valores antes do POST. A conclusão abre um recibo privado com o número novo e links para a nova fatura e a origem.

O serviço `CompanyInvoiceCopies` chama exclusivamente `CompanyInvoices::create`, com `originGuard` e participantes explícitos. Não grava invoices, documentos legais, sequências, itens ou tributos por SQL próprio. O documento novo usa a identidade, os ativos de apresentação, os termos e o contador atuais da empresa. Não copia snapshots legais da origem, pagamentos, créditos, estado pago/cancelado, cobranças/provedores, recorrência, arquivos, publicação, autorização de envio ou filas. Não dispara HTTP bancário, e-mail ou automações.

A rota exata é `admin/invoices/copy`, método `index`, sem segmentos adicionais. GET/HEAD recebem exatamente uma origem (`source`) ou recibo (`receipt`); não há emissão por GET. O único POST é `issue_reviewed_copy`, com CSRF nativo, contexto selado e CSRF próprio. O serviço aceita somente revisão da origem, datas, observação e confirmação; cliente, moeda, itens, preço, total, número e estado não vêm do formulário.

## Autoridade e integridade

- Empresa positiva persistida (principal incluída), funcionário ativo e grants atuais `invoices.view`, `invoices.create` e `customers.view`. A origem precisa unir sua própria fatura e documento numerado; NULL, órfãos e outra empresa não são adotados.
- O destinatário precisa estar ativo e atualmente pertencente/compartilhado com esta empresa. Compartilhamento não transfere documentos financeiros, contatos ou recibos de outra empresa.
- A revisão inclui origem nativa, documento histórico, linhas e tributos, dados atuais do destinatário, unidade monetária e perfil emissor atual. Mudanças antes da criação ou após a espera do lock vetam uma emissão fresca.
- A preparação somente lê em transação própria. O registro de origem, a emissão canônica, a conclusão do recibo, o evento financeiro e a auditoria pertencem à mesma transação do emissor canônico. Falhas tardias restauram inclusive sequência, snapshots e linhas. Não existe claim incompleto durável a adotar.
- A chave de recibo é opaca e vinculada a empresa/funcionário/origem/revisão/formulário. Uma chave canônica pré-existente sem esta proveniência é recusada. Não expande credenciais de API ou permissões legadas.
- Retry exato de uma emissão já concluída reconhece o documento original usando a entrada capturada, mesmo quando a origem foi posteriormente editada/cancelada. Não reemite nem ressuscita o destino. O recibo registra o valor/estado da criação e orienta consultar a fatura para mudanças posteriores.
- Leitura e retry de recibos continuam revalidando funcionário, empresa, origem, destinatário e destino atuais sob lock. JSON/hash/recibo canônico, metadados de origem e evidência de auditoria precisam coincidir; chaves de outro ator não hidratam o corpo do recibo.

## Perfil suportado e lacunas reais

Este incremento é uma **nova emissão numerada explicitamente revisada**, não uma cópia em rascunho. O consumidor nativo `Invoices_model::copy` (linha747 na base avaliada) aceita `save_as_draft`; o núcleo isolado atual ainda não oferece aquele ciclo de vida. Não se fabrica status6 nem um segundo escritor SQL. O ciclo canônico de rascunho continua sendo capacidade funcional distinta a implementar.

O perfil suporta de1 a100 itens canônicos, até10 tributos por item, desconto percentual/fixo antes/depois dos tributos e ajuste assinado. A extensão canônica `CompanyInvoiceLines` conserva descrição complementar literal de até20.000 bytes, unidade nativa de até40 caracteres UTF-8 e flags opcionais. Uma linha opcional não escolhida permanece no registro, na revisão e no editor completo, fora dos valores e do PDF, conforme o consumidor nativo. O cálculo monetário continua em `CompanyInvoiceAmounts`, sem alterar seu contrato para outros documentos. Campos ausentes/default preservam hashes e retries antigos. Tributos órfãos, flags desconhecidas, limites excedidos ou totais divergentes são recusados; nenhum atributo significativo é apagado silenciosamente. Veja `invoice-line-fields.md`.

Nenhum teste deste fluxo equivale a homologação bancária: não há comunicação com Banco do Brasil/Asaas, geração ou transmissão de remessa, nem validação de credenciais reais.

## Integração e provas

`CompanyInvoiceCopiesSchema` adiciona somente `company_invoice_copy_requests`. A migration futura deve chamar `ensure` fora de transação de escritor, depois dos schemas canônicos de faturas. Não há versão ou número de migration escolhido neste pacote. O helper nativo executa ensure/replay/verificação de engine/PK/FK/tipos/collations, propagação de falha DDL e preservação histórica; não prova tracking de uma migration futura ainda não composta.

Suites próprias: `invoice_copies.php`, `invoice_copies_controller.php`, `invoice_copies_atomic.php`, `invoice_copies_concurrency.php`, `invoice_copies_native.php` e `invoice_copies_browser.cjs`, em `tests/companies`. As dependências CLI do navegador são copiadas explicitamente mesmo quando a imagem não contém `/app/tests`. O navegador recusa hosts públicos e bloqueia outras origens.

A prova HTTP deste pacote usa PHP8.3.6, MariaDB10.11.14 e Chromium reais em ambiente próprio, com runtime canônico437 e schemas aditivos necessários. O marcador nativo427 do protótipo permanece preservado. Instalação/upgrade/tracking e CI do incremento oficialmente alocado precisam ser executados na composição final pela coordenação, sem confundir estas provas.
