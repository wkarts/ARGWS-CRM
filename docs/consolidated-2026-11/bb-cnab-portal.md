# Boleto CNAB no portal — módulo143

A publicação é explícita, seguindo o contrato já existente do checkout API.
Em Banco do Brasil > Remessa, informar a fatura CNAB e revisar o instrumento;
publicar ou ocultar na revisão. A preparação/emissão não publica automaticamente.
Essa é uma escolha de implementação para conservar o controle já existente de
divulgação ao cliente, não uma preferência atribuída ao usuário.

O portal usa o contato autenticado, empresa, cliente e permissão nativa1 para
faturas. Revalida também o responsável pela publicação, seu acesso ao banco e
às faturas, a origem ativa, o snapshot, o saldo e a revisão do instrumento.
Não transforma o contato em funcionário nem disponibiliza a rota administrativa.
Desativar o contato, revogar a permissão ou o responsável, ocultar a publicação,
cancelar ou modificar a fatura pode impedir a entrega até nova revisão.

CNAB240 e CNAB400 compartilham a view de impressão e o código I25 existentes.
A resposta preserva no-store, no-referrer e CSP com nonce. O botão de impressão
funciona sem handler inline. O formulário do portal permanece POST com CSRF.
As consultas e impressões não congelam lote, alteram bytes, reservam número,
transmitem arquivo ou registram pagamento. O documento informa explicitamente
que foi gerado e não tem registro bancário confirmado; homologação permanece
necessária. Não foram ampliados convênios, carteiras, juros ou leiautes.

## Persistência e compatibilidade

Reutiliza `bb_checkout_publications`, criada pela migration124: origem, empresa,
revisão do instrumento, responsável, habilitação e datas. A auditoria registra a
revisão publicada e exige readback; falha reverte a publicação. Nenhum backfill,
publicação ou criação de credencial ocorre na migration143 NOOP.

Módulo1.4.3 exige núcleo4.9.2, que contém o leitor do portal. Migrations101–142
permanecem intactas. Não fazer downgrade cego depois de publicar CNAB: primeiro
ocultar as publicações afetadas e preservar seu histórico; rollback de código
não altera o estado bancário nem desfaz pagamentos.

## Evidências delimitadas

- MySQL8.4/PHP8.3:30 verificações novas após354 da cadeia400. Incluem publicação
  ausente, revisão incorreta, falha de auditoria/rollback, isolamento, ocultação,
  permissão revogada e dois leitores reais aguardando o lock antes da revogação.
  Lotes, bytes, números, faturas e pagamentos permanecem iguais.
- A cadeia de regressão do checkout API passou, incluindo679 checks anteriores
  de emissão, desconto, componentes e portal/PDF, mais os checks de publicação.
  Transporte bancário simulado, sem homologação bancária.
- Chromium com HTML gerado pela view PHP após SQL: resposta HTTP com a CSP do
  portal, nonce e clique de impressão passaram. Esse teste não autentica no CRM.
- O teste autenticado existente `banco_brasil_origination_browser.cjs` foi
  ampliado para revisão/publicação administrativa, login independente do contato,
  impressão sob CSP, isolamento, ocultação e lote ainda aberto. Sua execução
  depende da rodada HTTP do CI; inclusão do teste não significa aprovação.

O build local oficial por fonte voltou a falhar na instalação de extensões PHP
por espaço insuficiente, mesmo usando contexto temporário sem node_modules.
Somente caches de fontes dessas tentativas foram removidos. Checkout, banco,
imagens de testes e logs foram preservados. Nenhum merge ou deploy.
