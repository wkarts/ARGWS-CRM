# Smoke HTTP em grupos isolados

O erro de dimensão ocorrido depois de alterar as configurações vinha de exigir um formato congelado da fatura legada. `Invoice_pdf → App_pdf::get_format_array → get_pdf_format` consulta a opção global atual em cada geração. `Issuer_invoice_pdf` consulta `CompanyInvoicePresentation` no snapshot. O teste mantém dimensões exatas em ambos: opção atual no legado, snapshot original nos nativos. Também mantém inalterada a linha legada e os snapshots, e confere explicitamente a opção global A4 após salvar as configurações. Falhas agora informam empresa, documento, etapa e dimensões esperadas/reais.

O teste rápido `legacy_invoice_pdf_format.php` exercita esses métodos reais com quatro formatos, antes dos bancos e navegadores longos. Não instancia a aplicação nem substitui a prova dos bytes PDF no navegador.

## Execução e cobertura

O workflow `deployment-integrity.yml` preserva o check obrigatório `validate` e distribui o trabalho:

1. `prepare`: mesmos contratos rápidos e fixtures de interface; constrói uma imagem da árvore Git exata e a exporta uma vez como artefato de CI com retenção de um dia. Sem publicação da imagem do aplicativo no GHCR.
2. `tooling`: Rust e Compose executam os mesmos comandos originais antes dos workers HTTP, reservando seu slot no orçamento de concorrência.
3. `http`: onze workers com a mesma imagem, PHP do aplicativo inalterado e ferramentas host PHP 8.1. Cada worker cria rede, banco, aplicações, portas e diretórios descartáveis próprios; os testes mantêm a ordem original dentro dessa stack.
4. `validate`: exige sucesso de todos os grupos, preparação e ferramentas. Falha, cancelamento ou grupo inesperadamente pulado não passam. A prova de árvore idêntica já existente continua com suas verificações anteriores.

O manifest `tests/ci/standalone-browser-plan.json` conserva a cobertura das **101 invocações Node originais** em **103 invocações**: o teste de parada automática é dividido por empresa entre três workers, com todas as12 combinações executadas uma vez. Instalação/upgrade e `node --test` sem contexto de banco permanecem. Toda chamada possui um único grupo dono ou papel de preparação comum. O seletor confere argumentos, contexto de banco quando aplicável, ordem e quantidade concluída em runtime. O fechamento reprova execução incompleta. O grupo de provedores não repete os navegadores Banco do Brasil: sua preparação usa os fixtures Asaas próprios; os testes bancários permanecem no grupo dono banking.

| Grupo | Domínio |
|---|---|
| sales-pdf | Vendas, linhas de recuperação, formatos PDF, recorrência de tarefas, equipe e leads |
| projects | Projetos, arquivos, faturamento, recuperação operacional e cópias |
| support | Contratos, suporte e APIs de operações |
| expenses | Despesas, importação, relatórios e livro |
| banking | Portal de horas e sequência completa de navegadores Banco do Brasil |
| providers | Gantt do portal, sequência Asaas, ciclo pós-devolução, IA, provas TLS e RH |
| settings | Marcos do portal, configurações, recuperação, recorrência e compras |
| tasks-domains | Portal de projetos/tarefas, preferências, calendário e timers |
| timers-a | Quatro pares de modalidades em A; recusas entre empresas, CSRF e política desatualizada |
| timers-b | Quatro pares de modalidades em B e snapshots financeiros |
| timers-primary | Quatro pares de modalidades na principal e snapshots financeiros |

Nove invocações comuns e os pré-requisitos explícitos são repetidos **para preparar bancos independentes**. Isso tem custo de preparação; não se presume que duplicar fixtures seja gratuito. Exemplos de dependências preservadas: vendas antes das conversões, portal antes dos escritores de suporte, publicação e escrita de tarefas de portal no próprio grupo tasks-domains antes do calendário sem responsável, compras antes dos testes HTTP e RH antes dos tokens. O grupo settings usa apenas a preparação comum: cada teste cria seu próprio fixture. Os testes 33/34 de publicação/escrita de tarefas pertencem somente a tasks-domains, sem repetição no grupo projects. A recuperação de linhas de vendas (63) pertence a sales-pdf e a recuperação operacional (64) a projects; esses cenários deixam de concentrar trabalho no grupo settings, preservando a ordem e usando seus próprios objetos. Os cenários independentes de marcos (35), horas (36) e Gantt (37) pertencem a settings, banking e providers, respectivamente. Cada um cria seus clientes/contatos e o próprio projeto; não depende de 33/34. Isso distribui três cenários extensos que antes se acumulavam em projects. Compras (96–98) e RH (99–100), com preparação própria, pertencem a settings e providers: suas sequências internas permanecem intactas e deixam de alongar tasks-domains. As provas TLS, fixtures não monetárias e adaptadores CLI têm dono de grupo e continuam no fluxo correto.

Essa divisão reduz o caminho serial do smoke para preparação + tooling + maior duração de grupo, somada às filas do GitHub. Não há previsão numérica de ganho até uma execução real completa. As matrizes de domínio dos outros workflows permanecem. Banco do Brasil e Empresas no núcleo continuam sendo outros possíveis limites do tempo global.

## Diagnóstico e reprodução

- Após falha em um dos quatro workflows de validação com shards (HTTP, núcleo, Banco do Brasil ou suíte focada), o monitor reprova a rodada e deixa os quatro workflows de diagnóstico do mesmo commit terminarem seus shards. Isso evita perder uma segunda causa em uma matriz SQL ainda marcada como queued pelo GitHub. Workflows auxiliares e commits obsoletos são cancelados. Shards SQL têm limite de 30 minutos; a suíte focada conserva seu limite de 20 minutos. Falha ou cancelamento nunca satisfaz o gate.
- A anotação compacta mantém o cabeçalho e o final do stack trace abaixo do limite público de 4 KB; o log sanitizado mais amplo permanece no artefato.
- Falhas também geram anotação pública com o trecho final sanitizado, empresa/documento/etapa quando fornecidos pelo teste, e status original propagado.
- Artefatos: `issuer-native-browser-<grupo>`, incluindo `browser-timings.tsv` e os diagnósticos limitados de falha já existentes.
- Modo original completo: variável ausente ou `ARGWS_BROWSER_GROUP=all`.
- Reprodução de um grupo: `ARGWS_BROWSER_GROUP=sales-pdf ARGWS_ISSUER_BROWSER_SMOKE=1 bash tests/docker_provision_smoke.sh` (mesmas dependências Docker/Chromium/PDF do smoke).
- Conferência rápida: `python3 scripts/ci/standalone-browser-plan.py --check`.
- Casos negativos e simulação do seletor com os **blocos reais de ambiente do shell**: `python3 -m unittest discover -s tests/ci -p 'test_*.py'`, já executados uma vez no preflight compartilhado.
- Conferência conjunta dos quatro inventários: `bash scripts/ci/check-generated-inventories.sh`.

A imagem carregada em cada worker passa novamente pela comparação da label da árvore com o checkout. Não há snapshots de banco compartilhados, serviços externos nem atualização de bases GHCR por alteração apenas de testes.


A concorrência das suítes longas é limitada a quatro workers de núcleo e um por variante SQL bancária, preservando os quatro shards por banco. Com onze grupos HTTP, duas versões PHP focadas e monitor, o patamar longo usa até 20 runners. Tooling termina antes dos HTTP. Os grupos tasks-domains e support aparecem primeiro na matriz para reduzir sua espera. O contrato automatizado confere as dependências entre fases, esse orçamento e a cobertura integral; filas externas do GitHub ainda podem acrescentar tempo.

A rodada 2151777b levou 53min47s e reprovou antes de concluir todos os cenários. O grupo tasks-domains chegou ao caso84 depois de43 minutos. Os fixtures33/34 criam seus próprios clientes/contatos; Calendar cria suas próprias origens por GUI. Foram retirados os pré-requisitos12–16 desse grupo, conservando9 e a cadeia33/34/84/85/86/89/90/91. Os casos independentes92–95 passam para sales-pdf, que havia concluído em16min12s no worker. Todas as101 invocações e dependências verificadas continuam presentes. Duração integral aprovada ainda pendente.

A medição posterior encontrou outro trecho serial:91 tem quatro pares de modalidades em cada uma das três empresas. Esse teste cria todas as próprias origens e não depende de90. As partes `--owner a`, `--owner b` e `--owner primary` têm grupos próprios timers-a, timers-b e timers-primary; tasks-domains conserva a cadeia até90. Os quatro pares positivos deA consumiram cerca de30 minutos localmente: colocá-los depois de outro domínio manteria o gargalo. Os três bancos adicionais repetem a preparação comum para conservar o isolamento, sem repetir os navegadores de calendário e preferências. A soma dos jobs bancários da rodada04 foi37,58min MySQL e28,77min MariaDB; um slot por variante mantém suas coberturas e libera capacidade para os três novos grupos. Esses dados orientam o orçamento, não são promessa de duração da próxima rodada.

O plano exige exatamente as três partes em workers distintos. A parte A mantém a única sequência original de CSRF/recusa entre empresas/política desatualizada. A fotografia imutável é capturada depois da criação intencional da tarefa B e antes da operação negada; o timer estrangeiro também é comparado separadamente. A prova de formulário antigo muda de fato a política de0 para1, pois salvar0 novamente é corretamente idempotente. Nenhuma combinação ou recusa foi retirada; o modo direto sem argumentos continua disponível. Quando a empresa já está selecionada, o seletor pronto é reutilizado. Para trocar, a tela de Settings é aberta antes do clique nativo, evitando um modal sobre o seletor e uma URL de recurso da empresa anterior. A navegação espera DOMContentLoaded e a inicialização real do seletor. A preparação do leitor privado é executada e conferida nos três grupos.
