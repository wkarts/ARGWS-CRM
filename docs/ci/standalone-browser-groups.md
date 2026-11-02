# Smoke HTTP em grupos isolados

O erro de dimensão ocorrido depois de alterar as configurações vinha de exigir um formato congelado da fatura legada. `Invoice_pdf → App_pdf::get_format_array → get_pdf_format` consulta a opção global atual em cada geração. `Issuer_invoice_pdf` consulta `CompanyInvoicePresentation` no snapshot. O teste mantém dimensões exatas em ambos: opção atual no legado, snapshot original nos nativos. Também mantém inalterada a linha legada e os snapshots, e confere explicitamente a opção global A4 após salvar as configurações. Falhas agora informam empresa, documento, etapa e dimensões esperadas/reais.

O teste rápido `legacy_invoice_pdf_format.php` exercita esses métodos reais com quatro formatos, antes dos bancos e navegadores longos. Não instancia a aplicação nem substitui a prova dos bytes PDF no navegador.

## Execução e cobertura

O workflow `deployment-integrity.yml` preserva o check obrigatório `validate` e distribui o trabalho:

1. `prepare`: mesmos contratos rápidos e fixtures de interface; constrói uma imagem da árvore Git exata e a exporta uma vez como artefato de CI com retenção de um dia. Sem publicação da imagem do aplicativo no GHCR.
2. `http`: oito workers com a mesma imagem, PHP do aplicativo inalterado e ferramentas host PHP 8.1. Cada worker cria rede, banco, aplicações, portas e diretórios descartáveis próprios; os testes mantêm a ordem original dentro dessa stack.
3. `tooling`: Rust e Compose executam ao lado do HTTP, com os mesmos comandos originais.
4. `validate`: exige sucesso de todos os grupos, preparação e ferramentas. Falha, cancelamento ou grupo inesperadamente pulado não passam. A prova de árvore idêntica já existente continua com suas verificações anteriores.

O manifest `tests/ci/standalone-browser-plan.json` contém as **101 invocações Node originais**, incluindo os modos de instalação/upgrade e o `node --test` sem contexto de banco. Toda chamada possui um único grupo dono ou papel de preparação comum. Nenhuma chamada foi excluída. O seletor confere argumentos, contexto de banco quando aplicável, ordem e quantidade concluída em runtime. O fechamento do script reprova execução incompleta.

| Grupo | Domínio |
|---|---|
| sales-pdf | Vendas, formatos PDF e snapshots |
| projects | Projetos, horas, faturamento e cópias |
| support | Contratos, suporte e APIs de operações |
| expenses | Despesas, importação, relatórios e livro |
| banking | Sequência completa de navegadores Banco do Brasil |
| providers | Sequência Asaas, ciclo pós-devolução, IA e provas TLS |
| settings | Configurações, recuperação e modos de recorrência |
| tasks-domains | Tarefas, calendário, timers, equipe, leads, compras e RH |

Nove invocações comuns e os pré-requisitos explícitos são repetidos **para preparar bancos independentes**. Isso tem custo de preparação; não se presume que duplicar fixtures seja gratuito. Exemplos de dependências preservadas: vendas antes das conversões, portal antes dos escritores de suporte, tarefas de portal antes do calendário sem responsável, compras antes dos testes HTTP e RH antes dos tokens. As provas TLS, fixtures não monetárias e adaptadores CLI têm dono de grupo e continuam no fluxo correto.

Essa divisão reduz o caminho serial do smoke para preparação + maior duração de grupo/ferramentas, somada às filas do GitHub. Não há previsão numérica de ganho até uma execução real completa. As matrizes de domínio dos outros workflows permanecem. Banco do Brasil e Empresas no núcleo continuam sendo outros possíveis limites do tempo global.

## Diagnóstico e reprodução

- Falhas também geram anotação pública com o trecho final sanitizado, empresa/documento/etapa quando fornecidos pelo teste, e status original propagado.
- Artefatos: `issuer-native-browser-<grupo>`, incluindo `browser-timings.tsv` e os diagnósticos limitados de falha já existentes.
- Modo original completo: variável ausente ou `ARGWS_BROWSER_GROUP=all`.
- Reprodução de um grupo: `ARGWS_BROWSER_GROUP=sales-pdf ARGWS_ISSUER_BROWSER_SMOKE=1 bash tests/docker_provision_smoke.sh` (mesmas dependências Docker/Chromium/PDF do smoke).
- Conferência rápida: `python3 scripts/ci/standalone-browser-plan.py --check`.
- Casos negativos e simulação do seletor com os **blocos reais de ambiente do shell**: `python3 -m unittest discover -s tests/ci -p 'test_*.py'`, já executados uma vez no preflight compartilhado.
- Conferência conjunta dos quatro inventários: `bash scripts/ci/check-generated-inventories.sh`.

A imagem carregada em cada worker passa novamente pela comparação da label da árvore com o checkout. Não há snapshots de banco compartilhados, serviços externos nem atualização de bases GHCR por alteração apenas de testes.
