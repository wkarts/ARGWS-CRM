# Formato de PDF de orçamentos e propostas por empresa

Este incremento adapta uma família nativa existente, sem abrir o catálogo de plugins. `get_pdf_format()` em `application/helpers/pdf_helper.php` e os seletores em `admin/settings/includes/pdf.php` já definem exatamente `A4-PORTRAIT`, `A4-LANDSCAPE`, `LETTER-PORTRAIT` e `LETTER-LANDSCAPE`. O consumidor nomeado `CompanySalesPdf` antes usava o A4 padrão do TCPDF.

## Fluxo e persistência

- Empresas secundárias usam Definições → PDF de orçamentos e propostas, com somente `pdf_format_estimate` e `pdf_format_proposal`. A empresa principal mantém os dois seletores nativos em Definições → PDF → Formatos.
- A ausência de uma opção na empresa usa o padrão do produto A4 retrato. Não consulta a opção da principal ou de outra empresa. Uma opção existente vazia/desconhecida é inválida e exige correção explícita.
- Novos orçamentos e propostas capturam a própria escolha em `sales_preferences`, formato 2, com a ordem `format, kind, prefix, terms, pdf`. O registro já existente permanece imutável. A emissão e o batch de configurações usam o lock transacional comum e revalidam a autorização após a espera.
- O leitor aceita o formato 1 exato e a ausência histórica de `sales_preferences`; ambos mantêm A4 retrato. A conversão desses documentos preserva o contrato de hash anterior. Não há migration de documentos, rehash histórico, atualização de identidade, alteração de valores ou reemissão.
- O renderizador recebe somente o DTO previamente autorizado e a preferência capturada. Não consulta sessão, opções globais, hooks de PDF, dimensões arbitrárias, caminhos, fontes ou URLs. A reautorização e o audit de download existentes continuam obrigatórios antes da entrega de bytes nos canais staff e portal que já usam esse renderizador.
- O novo batch principal `updateSalesPdfPreferences` permite apenas estas duas chaves e conserva `options` como armazenamento nativo. Ambas são validadas antes de efeitos; falha SQL na segunda chave ou no audit reverte as duas. O batch genérico principal continua fechado. Os outros controles legados do formulário PDF conservam seu contrato anterior; isto não declara o formulário legado inteiro como uma transação única.
- Atualizações de opções não reescrevem snapshots nem mensagens já enfileiradas. Replay da mesma chave de emissão retorna o documento original. Validação, CSRF, versão de contexto e 409 de aba obsoleta permanecem nos mesmos gates.

## Alcance e limite

A4 mede 210 × 297 mm, Carta mede 215,9 × 279,4 mm; paisagem inverte os eixos. Os testes também verificam os arquivos PDF reais com o Poppler, independentemente da API do TCPDF. O grupo `sales_pdf` aceita somente opções de orçamentos/propostas. A extensão própria para faturas, créditos e contratos está descrita em [document-pdf-preferences.md](document-pdf-preferences.md); recibos e extratos continuam sem novos controles para secundárias. Não oferece templates, fontes customizadas, imagens remotas ou integrações externas.

A investigação do próximo grupo mantém OAuth Google/Microsoft, SMS, Pusher e integrações de IA fechados na GUI secundária até que seus consumidores e callbacks tenham contratos próprios por origem. A existência de campos legados ou de uma chave no storage interno não prova isolamento do consumidor. O charset SMTP nativo também não foi liberado: os consumidores atuais compõem corpo e cabeçalhos UTF-8, e escolher outro charset sem uma conversão coerente produziria conteúdo mal rotulado. Infraestrutura de cron, instalação e atualização continua expressamente global.

A recuperação gráfica de master legado permanece condicionada ao artefato e cadeia confiável descritos em `legacy-provisioning-recovery.md`; esta preferência de PDF não modifica autoridade administrativa nem o fluxo CLI seguro.

## Verificação reproduzível

```sh
php tests/settings/sales_pdf.php
php tests/settings/primary_sales_pdf.php
php tests/companies/sales_pdf_preferences.php
python3 tests/settings/sales_pdf_extract.py
# Exige ISSUERS_TEST_DSN MySQL/MariaDB, usuário e senha de banco sintético isolado:
php tests/settings/sales_pdf_concurrency.php
# Após fixtures HTTP nativas da empresa/portal/vendas, com ambiente ISSUERS_BROWSER_*:
node tests/settings_sales_pdf_browser.cjs
```

Os testes locais de transporte não contactam SMTP, OAuth ou bancos. Esta é funcionalidade interna de PDF; não constitui homologação bancária. A árvore de desenvolvimento do pacote conserva a versão-base 4.2.8 como protótipo: o coordenador precisa integrá-la à próxima versão e à prova nativa dessa versão, sem editar migrations históricas.
