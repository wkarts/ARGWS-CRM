# Validação do incremento428

Núcleo4.2.8/428, BB1.1.4/114 e Asaas1.5.1/151, sobre
11026cdbbfecbd1a2a557f2bee09f451fdd18506. Migrations históricas preservadas.
Não atribuir provas de protótipos ao HEAD publicado. Sem merge ou deploy.

## Composição e execução local observada

PHP8.3.6, MariaDB10.11.14/InnoDB, SQLite e Chromium140. Dados sintéticos;
transportesBB/Asaas/SMTP simulados. Contadores cumulativos não se somam.

| Requisito | Execução | Resultado e limite |
|---|---|---|
| Upgrade/replay428 | native_428.php no composto |189 checks MariaDB; schemas/índices/FKs, publicação inicialmente vazia, originais/BLOBs/NULL preservados e falhaDDL propagada; cacheCI invalidado depoisDDL no fixture |
| Portal operacional | portal_operations.php/controller/concurrency |218/106/167 em MariaDB isolado;18 cenários com waiters, browser realA/B de protótipo |
| ReversãoBB | banco_brasil_external_reversal_mysql/concurrency |154/182 MariaDB; final source, dois waiters, revogação/conflito/corrupção/crédito concorrente; nenhuma operação bancária |
| Leitores/PDF/relatórios/guards | bank_reversal_readers/provider_guards/report_views |177/193/197 MariaDB no composto; DTO, TCPDF, original/net/CSV/journal e corrupção |
| Preferências vendas | sales_documents/sales_preferences/concurrency |170/295 e concorrência nativa no composto; cópia isolada inicial294/156;4PDFs reais por extração |
| Principal | primary_sales_documents/concurrency |163/156 MariaDB em cópia isolada; falha na segunda opção e audit sem parcial; GUI503/retryA/B/principal |
| RevalidaçãoAPI | api_invoice_output_guard.php |RED/GREEN SQLite162/MariaDB163; token/ator/owner após audit e pós-TCPDF; remover só confirmação pós-render reproduz a falha |

A correçãoAPI reusa InvoiceIssuerDocuments para conferir origem/ACL/posição e DTO
antes da resposta; não duplica usedreceipt. Há uma releitura adicional JSON e duas
PDF. O teste usa o TCPDF real, incluindo revogação no limite final, sem rendererfake.

## HTTP e bytes do composto

**29/29 execuções em uma rodada limpa**, na ordem exata dos28 browsers do smoke
mais o suplemento Settings. Instalador HTTP real com admin17, schema legado do
bundle elevado ao428 pelo runner nativo e replay do corpo428 duas vezes com
readback do tracking428 e dos schemas. Tracking de falha tem fixture independente.
Provas: [HTTP428](evidence/http-428.json) e [diff](evidence/http-428-runtime-diff.json).

Manifest completo inicial=final:
`f054365b9127b8c802f415f8bb57fbe25e0aba8d924b1470cc8773632cf88713`.
Runtime/distribuição:17.918 arquivos, digest
`33940ba1c0a7f5e44a1e410dd62b0e2a91b8dfc3178a04ce9d73d1cfd3350737`.
Zero diferenças nos17.904 arquivos versionados application/modules/assets em execução;
instalador removido normalmente depois do sucesso. Todos os arquivos congelados de
runtime, distribuição e testes conferidos contra os blobs do índice Git antes do commit.
Docs/evidências/CI finais ficam fora do manifest, sem substituir runtime/testes.

Adaptações limitadas ao transporte do fixturePDO host:port, caminho do executável
Chromium e seed sintético AUTO_INCREMENT17. Gerados declarados separadamente:
app-config sintético, my_hooks idêntico ao recurso webhooks já versionado e cache
HTMLPurifier. O aplicativo real usa mysqli; as adaptações não alteram a produção.

Antes da rodada limpa, dois ajustes de composição nos testes: ambosCSRF e sessão
explícita no portal; restauração do cursor/journal427 que o overlayBB114 perdera.
Não foram bugs novos de produção. A correção real da revalidaçãoAPI tem prova
RED/GREEN independente, descrita acima. Nenhuma execução exploratória entrou29/29.

Checks locais:62 testesPython de release/pacote/convenções/inventários; lint98PHP,
5JS, YAML e bash; release canônica428 e três inventários coerentes. O rollout
operacional genérico continua bloqueado. Contadores cumulativos não se somam.
Os gates por diff usam os SHAs completos após commit. O CI427 teve16 workflows
aprovados; não substitui os checks próprios do428, acompanhados na descrição da PR.

## Limites

Reversão é declaração independente e integral do recibo, sem transmissão ao banco.
API parcial/ajustes e identidades de crédito, tarifas/IOF/outras equações e contratos400
continuam técnicos/documentais pendentes; não são só falta de homologação. Nenhum
canal bancário/SMTP foi homologado. Portal428 só publica/leitura; escrita de suporte
e API de subrecursos estão preparadas para incrementos posteriores. Recuperação
legada totalmente gráfica exige prova original e cadeia de autoridade ausentes;
console+senha preservado. Não confundir provisão transitória apagada com prova.
