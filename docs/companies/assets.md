# Logotipo, assinatura e preferências por empresa

Selecione a empresa e abra **Logotipo e assinatura** no menu nativo, ou em Definições.
São aceitos PNG/JPEG até512 KiB,1600 pixels por dimensão e2 milhões de pixels.
O serviço decodifica e regrava PNG sem metadados ou conteúdo adicional; SVG, URLs,
nomes de arquivo e caminhos físicos não entram na persistência. Cada empresa,
inclusive a principal, possui registro próprio em `issuers_registry.settings_json`.
Nenhuma imagem é herdada de options ou de diretórios globais. Infraestrutura global
não foi duplicada. Cada imagem ocupa no máximo700 mil caracteres em base64; duas
imagens limitam essa parcela do JSON a aproximadamente1,4 MB por empresa.

Configurar imagem exige settings/view e edit; download exige view e grava auditoria
confirmada antes de entregar os bytes. Revogação é revalidada sob lock. O formulário
usa revisão de conteúdo e CSRF/contexto: uma edição obsoleta retorna409. Upload
malformado retorna422, falha de persistência503, acesso indevido403. Não há URL pública
para a imagem. O preview é inline no documento já autorizado e tem cache no-store.

Novas faturas guardam logo e assinatura no snapshot emitido. Trocar/remover as imagens
atuais não modifica documentos anteriores, inclusive os que nunca tiveram imagem.
O PDF usa somente o snapshot autorizado e continua revalidando acesso antes dos bytes.
A assinatura é uma imagem de apresentação; não é assinatura digital com certificado.

Definições → Faturas nas empresas secundárias permite prefixo (até50 bytes UTF-8)
e condições padrão (até10 mil bytes). As configurações são próprias; documento já
emitido preserva número e condições capturados. O editor e o PDF escapam o texto.
A principal conserva suas configurações nativas existentes.

Validação: `tests/settings/company_assets.php`, `tests/companies/asset_concurrency.php`,
`tests/companies/invoices.php`, `tests/settings/settings_model_controller.php` e
`tests/companies_invoices_browser.cjs`. Os testes de navegador usam upload multipart,
preview/download, edição obsoleta, mudança de empresa e PDF no CRM descartável.
Resultados por SHA em pr45-status.md; execução sintética não comprova SMTP ou banco.

Sem DDL:418 é marcador NOOP nativo. Futuro deploy pelo fluxo normal somente após
autorização, backup e checks do HEAD. Rollback de código pode manter os campos JSON
aditivos; não apagar imagens/snapshots nem voltar os marcadores de versão no banco.
