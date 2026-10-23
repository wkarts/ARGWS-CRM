# TCPDF: levantamento sem migração de motor

Consulta: 23/10/2026. Escopo desta PR: pesquisa/documentação apenas.

## Estado observado

O CRM distribui `tecnickcom/tcpdf` **6.10.0** em
`application/vendor/tecnickcom/tcpdf/composer.json`, e
`application/libraries/pdf/App_pdf.php` herda diretamente de `TCPDF`.
O contrato PHP do CRM em `application/composer.json` é `^8.1`.

O [repositório oficial TCPDF](https://github.com/tecnickcom/TCPDF) identifica a
biblioteca como deprecated e em manutenção, com desenvolvimento ativo no
sucessor `tecnickcom/tc-lib-pdf`. Isso não equivale a informar que PDFs existentes
pararam de funcionar. O [composer oficial do sucessor](https://github.com/tecnickcom/tc-lib-pdf/blob/main/composer.json)
exige PHP `^8.2`, incompatível com a promessa atual de suportar PHP 8.1.

O README oficial consultado também descreve uma fachada de compatibilidade
TCPDF 7 sobre o novo motor. Portanto há uma alternativa à troca direta de todas
as chamadas, mas não uma atualização sem risco: a documentação alerta para
variações de métricas/paginação, mudanças de fontes e comportamentos não
reproduzidos. A troca direta para `tc-lib-pdf`, com namespace e dependências
próprios, não deve ser tratada como drop-in.

## Decisão desta entrega

Não alterar dependências, vendor, engine, fontes, runtime mínimo ou saída PDF.
Nenhum teste desta PR autoriza declarar a nova engine homologada.

Uma futura proposta separada deve comparar fachada e adaptação direta, revisar
compatibilidade PHP, extensões e empacotamento de fontes; executar o conjunto
real de faturas, propostas, orçamentos e créditos com textos PT-BR, logos,
quebras de página, assinaturas e anexos; comparar PDFs renderizados e texto
extraído e prever rollback de código/artefatos. A escolha exige autorização
própria e não é requisito para corrigir as telas desta PR.
