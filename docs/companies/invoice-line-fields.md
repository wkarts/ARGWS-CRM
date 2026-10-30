# Linhas canônicas completas de fatura

O emissor `CompanyInvoices::create` e a edição `change` conservam `long_description`, `unit`, `is_optional` e `is_selected`, além dos campos monetários já existentes. A cópia revisada passa a levar esses atributos da origem persistida para os novos itens, mantendo o documento, os IDs e o histórico da origem. Não se cria outro escritor de invoices, sequências ou snapshots legais.

## Semântica nativa e compatibilidade

`CompanyInvoiceLines` é um adaptador exclusivo de faturas; não amplia o contrato de `CompanyInvoiceAmounts` usado por outros documentos. Validação de quantidade/preço/tributos e cálculo inteiro, inclusive descontos antes/depois dos tributos e distribuição por maior resto, continuam no serviço monetário existente.

O comportamento de opcionais corresponde a `sales_helper.php` linhas638 e1013 da base avaliada: `is_optional=1` e `is_selected=0` preserva a linha, mas exclui sua base/tributos dos totais. É necessário pelo menos um item incluído. Linha obrigatória precisa estar escolhida; flags desconhecidas ou contraditórias são recusadas. Opcionais novos sem escolha explícita começam não escolhidos, como no helper nativo. Flags aceitam somente0/1 inteiros ou strings equivalentes.

Descrição complementar é texto literal UTF-8 de até20.000 bytes; unidade respeita o varchar(40) nativo em caracteres Unicode, até160 bytes. O texto é preservado sem trim e sempre escapado no HTML/PDF. Os limites são um perfil canônico explícito, não autorização para conteúdo histórico ilimitado. Unidade/descrição NULL antigas sem valor significativo viram o default vazio na nova emissão; valores significativos não são apagados.

Campos ausentes e defaults (`''`, `''`,0,1) são omitidos da representação normalizada para manter os hashes canônicos anteriores. Uma chamada antiga pode ser repetida com defaults explícitos sem consumir outra numeração. Novos valores significativos alteram o hash e a revisão; não podem substituir silenciosamente a entrada de uma chave antiga. A compatibilidade de recibos pre417 também é mantida; o fallback antigo não aceita descartar novos metadados significativos.

O JSON capturado da cópia e o DTO autorizado ficam limitados a16 MiB (16.777.215 bytes), compatíveis com a coluna MEDIUMTEXT existente e com as linhas novas dentro de seus limites. Nenhuma tabela ou coluna é adicionada neste incremento.

## Editor e bytes

A projeção `editorItems` verifica empresa/parent e permissões atuais `invoices.view/edit` antes de hidratar as linhas completas. Ela inclui opcionais não escolhidos, que o DTO financeiro/PDF já excluía. O formulário usa essa projeção, preserva descrição/unidade/escolha ao recarregar e mantém os índices/valores defaults corretos ao adicionar linhas. A revisão da cópia mostra todas as linhas e explica quais não entram no total/PDF.

Create e change confirmam as linhas persistidas completas contra a entrada normalizada, inclusive depois de auditoria/journal/guard. Alteração tardia de descrição, unidade ou escolha com total aparentemente correto veta a operação inteira. Edição mantém os mesmos vetos de origem bancária/movimento, cliente/moeda imutáveis, identidade/número emitidos e recibos de criação.

O download PDF pelo controller usa TCPDF em memória e confirma a autorização/auditoria antes dos bytes, retornando `Content-Type: application/pdf` exato, attachment e nosniff. Isso corrige a combinação de vários tipos que `Output(D)` enviava na prova HTTP. O renderer/template PDF continuam os existentes e recebem somente o DTO autorizado; não se abre upload, URL de imagem, HTML executável, iframe ou rota pública.

## Provas e fronteiras

Testes novos `tests/companies/invoice_line_fields.php`, `invoice_line_fields_concurrency.php` e `invoice_line_fields_browser.cjs` cobrem A/B/principal/NULL, defaults/retry/revisão, corpo literal/Unicode, opcionais, editor completo, cópia, TCPDF real, falhas tardias com rollback e concorrência real. As regressões de cópia, valores monetários e recebimento/edição canônica continuam passando.

O navegador executa o link real de revisão, cópia integral das linhas, reabertura do editor com opcional não escolhido, clonagem de novo item com defaults, mudança de seleção e salvamento, além de download com bytes e headers PDF exatos. A imagem começa sem `/app/tests` e recebe apenas fixtures explicitamente copiadas.

A prova HTTP continua em protótipo próprio PHP8.3.6/MariaDB10.11.14/Chromium, runtime437 mais overlays, marcador427 preservado. A coordenação deve compor e executar o incremento nativo oficialmente alocado, sem tratar este protótipo como prova de tracking futuro. Nenhuma chamada bancária ou SMTP foi executada.

Permanece distinto o ciclo canônico de rascunho (`save_as_draft` nativo747) e a recorrência com rascunhos/somente pagas; são capacidades de produto a implementar pelos escritores canônicos correspondentes. Esta extensão resolve os atributos de linha dentro do perfil, sem inventar status6, assinatura legal ou aprovação externa.
