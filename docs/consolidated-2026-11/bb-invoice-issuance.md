# Emissão CNAB ligada à fatura e à recorrência — BB 1.4.2

A geração local CNAB240/CNAB400 participa da transação canônica de
`CompanyInvoices::create`. Na nova fatura e na nova série, o operador habilita
explicitamente a geração, escolhe a configuração declarada, informa o fuso IANA
empresarial e os campos do pagador exigidos pelo leiaute. Nenhuma conta, carteira,
espécie, aceite ou aprovação bancária é inferida. O meio selecionado é BB CNAB;
uma instrução simultânea para outro provedor online é recusada.

A interface usa os formulários e a proteção de contexto/CSRF existentes. Na série,
os lembretes permanecem no padrão anterior (pausados); o calendário, ciclos e a
escolha de envio continuam independentes. A instrução integra o payload imutável
da série e é aplicada a cada ocorrência pelo mesmo escritor canônico. Rascunhos
continuam sem título bancário até a emissão. Não há conversão automática de séries
anteriores, cópia de instrução de outro cliente, alteração de Produtos ou faturas
já emitidas. Reajustes continuam aplicados somente ao modelo futuro.

O título usa os dados e o saldo da nova fatura dentro do lock financeiro nativo.
Falha de configuração, autorização, saldo, codec ou persistência desfaz fatura,
linhas, sequência, reserva bancária, origem, lote e auditoria da emissão. O boleto
fica disponível na operação nativa Banco do Brasil antes de download/transmissão:
CNAB240 pelo lote e fatura; CNAB400 pela preparação. A disponibilidade no portal
público do cliente ainda não foi implementada neste delta.

CNAB240 acumula títulos emitidos em dias diferentes, conservando a data original
de cada snapshot e usando a maior data compatível no arquivo. Um título vencido
não força os novos a entrar em arquivo incompatível. CNAB400 usa o envelope
imutável da migration141. O primeiro download continua congelando bytes; arquivos
históricos não são regenerados para incorporar novas emissões.

Para CNAB400, as referências e confirmações de moeda, condição de juros e
homologação permanecem explícitas e requerem a permissão bancária administrativa
já exigida pela preparação. O formulário não oferece esse contrato a quem não
pode confirmar. Essas declarações são fornecidas pelo operador: os testes não
comprovam homologação do convênio. Alteração posterior da declaração contratual
exige revisão, em vez de substituir silenciosamente o contrato da série.

## Persistência e atualização

Migration142 acrescenta `bb_invoice_issuances`, com unicidade por fatura e origem,
FK para origem/declaração e instrução imutável com hash. Não há backfill ou alteração
de migrations publicadas. O runner confirma instalação/upgrade/replay/tracking.
Runtime antigo ou marcador incompleto não habilita a operação. Retry da mesma
chave confirma o vínculo existente, sem emitir outro título. Dois escritores
concorrentes usam o mesmo mutex financeiro e geram uma única obrigação.

A tabela contém dados do pagador e a instrução bancária autorizada; não contém
credenciais ou dados de cartão. Nenhum HTTP bancário, envio de remessa, cobrança
real ou implantação foi executado. A data de geração usa o fuso explicitamente
informado; vencimentos incompatíveis são recusados, sem alterar datas da série.

Rollback de código não deve apagar origens, envelopes, instruções ou reduzir
marcadores. Antes de atualização, backup consistente e runner nativo; em falha,
suspender a capacidade e corrigir o avanço. Voltar ao código anterior a141 depois
de exportar grupos400 é inseguro, pois ele não conhece o envelope já entregue.

## Evidência e limites

Testes executam MySQL real descartável, runner nativo e escritores reais, sem
provedor: rollback integral, retry, duas ocorrências por leiaute, mesma chave em
dois processos concorrentes, campos inválidos, canal desabilitado, permissão
revogada e preparação400 sem declaração. Testes do parser original e da view PHP
verificam transporte da instrução e ausência de opt-in pré-marcado. Os caminhos de
upgrade120/121/122 preservam os registros históricos e acrescentam exatamente as
tabelas introduzidas por cada migration.

Regressões executam arquivo240 com datas de emissão diferentes, agrupamento400,
download congelado e boleto400. Contagens e comandos constam de `validation.md`.
HTTP autenticado e navegador do novo formulário ainda dependem do ambiente
completo; parser/view/MySQL não substituem essa evidência. Também continuam
pendentes homologações bancárias separadas de240/400/boleto e portal do cliente.
