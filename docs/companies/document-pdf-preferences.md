# PDF próprio de faturas, créditos e contratos

Este incremento sucede o formato próprio de orçamentos/propostas. Expõe somente três chaves nativas já declaradas em `admin/settings/includes/pdf.php`: `pdf_format_invoice`, `pdf_format_credit_note` e `pdf_format_contract`. As opções exatas continuam A4/Carta, retrato/paisagem, conforme `get_pdf_format()` nativo. Não abre fontes, templates, dimensões arbitrárias, caminhos, imagens remotas ou callbacks de plugins.

## Emissão e compatibilidade

Empresas secundárias usam Definições → PDF de faturas, créditos e contratos; a principal conserva seus seletores no formulário PDF nativo. As três opções têm validação prévia, armazenamento próprio e batch atômico. Falha na terceira opção ou no audit reverte o batch. O batch genérico principal permanece fechado; outros controles legados mantêm seus contratos anteriores e não são declarados como uma transação única do formulário inteiro.

Faturas e créditos novos capturam `pdf_preferences`, com envelope exato `format:1, kind, choice`, no JSON de snapshot já existente. Contratos capturam o envelope na ativação, quando também recebem número e congelam o texto; rascunhos não ganham PDF nem assinatura. Cada fatura nova de conversão ou recorrência captura a opção própria na emissão. A ausência da configuração usa A4 retrato do produto, sem ler a principal ou outra empresa; uma configuração existente vazia/desconhecida exige correção explícita.

Os leitores verificam versão, chaves, tipo do documento e escolha antes de renderizar. Ausência do campo em snapshot histórico conserva A4 retrato e permanece ausente na persistência. Não há rehash, backfill, reemissão nem alteração de templates, identidade fiscal, numeração, valores, eventos financeiros ou estado de provedor. Retry da chave original mantém o mesmo snapshot. O renderizador não consulta configurações correntes ou sessão.

## Guarda financeira na conversão

Uma fatura nova tem um campo de apresentação que pode não existir no contexto de uma fonte antiga. A comparação do recibo canônico com a identidade da fonte valida primeiro o envelope opcional em ambos os lados, exigindo `kind=invoices`; só então exclui exatamente `pdf_preferences` da comparação fiscal. Todos os demais campos e chaves desconhecidas continuam participando do hash. Não são removidas chaves genéricas nem normalizados nomes, documentos fiscais, termos, prefixos, imagens ou destinatários. O hash da origem e os guards existentes de conversão/recorrência continuam intactos.

O teste dirigido altera cada campo da identidade restante e uma chave desconhecida, e prova a recusa pelo método real do recibo canônico. Shapes incompletos/extras, versão, tipo cruzado de crédito/contrato e papel desconhecido falham em ambos os lados antes da comparação.

## Entrega e revalidação do crédito

Na revisão do consumidor foi encontrado um gap anterior: o crédito autorizava/auditava antes de renderizar e devolvia o PDF sem verificar de novo a autorização após o TCPDF. O novo fluxo faz leitura canônica autorizada, renderiza fora da transação e abre uma transação própria final para revalidar ACL e o DTO inteiro, registrar/confirmar o audit e só então devolver bytes. Não há transação aninhada nem callback público que conceda acesso.

A prova RED/GREEN executa o TCPDF real. Um proxy exclusivo do teste revoga o ator imediatamente após o commit da primeira leitura ou registra um refund local canônico nesse intervalo. O código anterior devolvia bytes após a revogação; o novo código recusa revogação e estado financeiro alterado, sem audit de download concluído. Uma nova tentativa válida funciona. O refund desse teste é apenas escrituração local sintética; não houve reembolso externo.

A confirmação pós-render já existente para faturas e contratos permanece nos mesmos serviços/controladores. Formato de papel não confere aceite, assinatura eletrônica ou certificação jurídica.

## Verificação

```sh
php tests/settings/document_pdf.php
php tests/settings/primary_document_pdf.php
php tests/companies/invoice_credit_pdf_preferences.php
php tests/companies/contracts_pdf_preferences.php
php tests/companies/pdf_fiscal_identity.php
php tests/companies/credit_pdf_revalidation.php
python3 tests/settings/document_pdf_extract.py
# Banco MySQL/MariaDB sintético e sessões independentes:
php tests/settings/document_pdf_concurrency.php
# Após fixtures HTTP de onboarding, portal, faturas/vendas:
node tests/settings_document_pdf_browser.cjs
```

O Poppler verifica independentemente 72 arquivos reais: A/B/principal × três famílias × quatro opções × render inicial e após mudar a configuração. As provas de concorrência observam duas sessões aguardando o lock, retry da mesma chave e revogação depois da espera. As regressões de conversão, recorrência, recebimento Asaas e BB usam fontes canônicas e transporte simulado, sem envio real a banco/SMTP.

Ainda ficam fora deste grupo os formatos de recibos e extratos: não foram liberados sem adaptar seus próprios snapshots/consumidores. Não há nova DDL; a versão definitiva e a instalação/upgrade nativa são responsabilidade da integração coordenada. A versão-base 4.2.8 do overlay de desenvolvimento não é uma publicação deste incremento nem homologação bancária.
