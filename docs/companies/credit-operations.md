# Notas de crédito, reembolsos e estornos locais

A tela selada `Company_credits` usa o writer financeiro nativo e o leitor
`CompanyCredits`. Os documentos persistem em `creditnotes` com `issuer_id` explícito
e snapshots próprios em `company_credit_documents`; os lançamentos imutáveis em
`company_credit_entries` reconciliam as projeções nativas `credits` e
`creditnote_refunds`. Nenhum leitor/model legado é carregado pela tela.

## Operações da interface

- Criar nota para cliente autorizado da origem, com moeda explícita, itens,
  múltiplos tributos, desconto fixo/percentual antes/depois de tributos, ajuste e
  observação. Prefixo, condições, identidade e imagens vêm da empresa emitente e
  são capturados no documento. Numeração independente por origem.
- Lista, detalhe e PDF privado. O leitor confere snapshots, recalcula itens e
  totais em centavos e compara os lançamentos originais/estornos ao saldo nativo.
  Divergência impede apresentar saldo como válido. PDF exige auditoria gravada
  antes dos bytes e usa somente dados autorizados; não publica link anônimo.
- Aplicar crédito a fatura da mesma empresa, cliente e moeda, até o saldo de ambos.
  Desaplicar parcial/total conserva o movimento original e insere um estorno com
  referência a ele. Estado e saldo da fatura são recalculados pelo writer.
- Registrar reembolso manual com valor, data, referência única e motivo/evidência.
  Registrar estorno parcial/total de reembolso com vínculo ao lançamento original.
  Essas ações registram fatos locais: não acionam bancos, Asaas ou outro gateway,
  não devolvem dinheiro nem substituem a comprovação da transação externa.
- Cancelar nota sem movimentos preservando documento/número. Uma nota com qualquer
  histórico financeiro, mesmo revertido integralmente, exige análise do histórico.

## Autorização e consistência

Todas as leituras exigem `credit_notes/view` e origem persistida igual à empresa
ativa. Criar exige `credit_notes/create` e `customers/view`. Aplicar/reembolsar
exige `credit_notes/edit` e `payments/create`; estornar exige edit e
`payments/delete`. Aplicar/desaplicar exige também `invoices/view`. Cancelar exige
`credit_notes/delete`. Não inferir essas capacidades de permissões globais.

Mutações têm POST, CSRF do framework e formulário e token de contexto da empresa.
A chave da requisição identifica o payload; replay idêntico não duplica valores.
Referências já utilizadas na mesma operação/origem não podem ser reaproveitadas com
nova chave. Locks do núcleo serializam balanços/revogação e a transação confirma
projeção, lançamento, status e auditoria. A GUI não executa SQL de movimentos.
Datas futuras e valores excedentes são recusados. Registros NULL legados não são
adotados como pertencentes à principal ou a empresa secundária.

Faturas com origem bancária são recusadas pelo writer local: use o fluxo da
integração de origem para que uma ação contábil não simule baixa/devolução bancária.
O seletor de faturas e o de clientes exibem até 100 resultados autorizados.

## Evidências e limites

`tests/companies/credits_reader.php` inclui a suíte do writer e valida os saldos
20,00 aplicados/20,00 reembolsados/60,00 disponíveis após estornos, isolamento A/B,
lista própria, PDF real TCPDF, auditoria impeditiva e divergência de projeção ou
snapshot. SQLite prova serviços/aritmética/rollback com adaptador; MySQL/MariaDB e
Chromium precisam ser executados na suíte de integração para comprovar DDL,
tracking/locks e navegação real.

`tests/companies_credits_browser.cjs` cria notas pela interface em A/B, emite PDF,
registra reembolso parcial e seu estorno, cancela uma nota sem movimentos e verifica
origem obsoleta e consulta direta cruzada. Não efetua transações externas.

Esta entrega não implementa devolução via API bancária/gateway, escrituração fiscal
de nota de crédito, homologação financeira ou acesso público/portal de clientes a
essas notas. Essas frentes precisam de código próprio e contratos externos quando
aplicáveis; testes locais não equivalem a homologação.
