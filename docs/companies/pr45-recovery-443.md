# Recuperação 443: revisões de vendas e CNAB 400 observacional

O pai publicado é `7dd715fc371c66d4c95ebc342d02c4391b50869f` (442). Este documento descreve a composição candidata 443; a associação ao SHA publicado e o CI ficam na descrição da PR e nas evidências posteriores, sem autorreferência no commit. Não há merge ou deploy.

## Versões e banco

Núcleo 4.4.3/443, Banco do Brasil 1.2.2/122, mínimo de núcleo 4.4.3 e Asaas 1.5.3/153. Migration443 cria exatamente `company_sales_revisions` e `company_sales_copy_requests`, com cinco FKs explícitas. BB122 é NOOP de validação dos quatro participantes já existentes; não acrescenta tabelas, colunas ou backfill. Todas as migrations anteriores são preservadas.

A fonte produtiva final contém18.546 arquivos, manifesto SHA256 `ad7e932e5efcd5479a90c31773ad9fbb43a514d0dd610f8aa3caba4b292fb15f`. O incremento acrescenta sete caminhos produtivos e não elimina nenhum do442.

## Vendas próprias

Edição revisada de orçamento/proposta em estado permitido, cópia com revisão explícita, proveniência e recibo idempotente. Não adota documento estrangeiro ou visibilidade legada; não duplica pagamento, arquivo ou lançamento financeiro. Linhas, tributos, numeração, cliente, registro fiscal e PDF continuam no contrato canônico. Revisões e cópias revalidam atores e imagens após a última auditoria.

V1/V2 não foram publicados: a revisão independente mostrou alteração do número na última auditoria, com sucesso indevido e documento inválido. V3 fechou essas fronteiras, incluindo54 variantes reais de auditoria. O teste nativo443 encontrou outra falha: FK adicional com DELETE CASCADE era aceita. V4 exige conjunto exato de FKs simples e ações RESTRICT/NO ACTION; rejeita constraints extras, ausentes, duplicadas, compostas, pais ou schemas incorretos. A definição legítima das duas tabelas não mudou entre V3/V4.

Pacote V4:22 arquivos, sete produtivos. Matriz própria15SQLite/16MariaDB e composição15SQLite/18MariaDB aprovadas, incluindo regressão canônica192, concorrência278 cumulativos/oito cenários com dois waiters e teste nativo1626/executor202. Contadores cumulativos não são somados. A composição final completa e a GUI possuem evidências separadas; esses resultados não equivalem a CI remoto.

## Auxiliares CNAB 400

Importação observacional de registros5/01 e5/10, vinculada a perfil de retorno explícito e a perfil400 básico previamente declarado para a mesma conexão. Eventos7 preexistentes conservam JSON, chave, hash e binding originais. Auxiliares recebem identidade semântica própria e referência ao pai; sequência física/arquivo não inventa nova identidade financeira. Envelope, linhas, links e proveniência são conferidos atomicamente, inclusive no replay. Conteúdo desconhecido ou órfão rejeita o arquivo completo.

A V1 não foi integrada: seis repros independentes mostraram alteração de perfil/conexão na última auditoria, adoção de JSON corrompido, links extras e replay com proveniência falsa. A V2 rejeitou os seis casos; o retry após rollback e remoção do trigger foi positivo, sem fingir que o primeiro import havia sido concluído. Os15 hashes revisados permaneceram exatos até a integração. Matriz própria7MariaDB/7puras e14lints aprovados.

E-mail, ocorrência Pix, URL e TXID permanecem texto bruto escapado. A aplicação não segue URLs, envia e-mail/Pix ou atribui liquidação. O manual CBR643 julho2023 apresenta137 posições para e-mail versus picture135 e ocorrência de dois caracteres versus nota com códigos de três; nenhuma das contradições foi normalizada por suposição.

## Provas de integração e limites

O executor real443 testa DDL parcial, índices/FKs/metadados inválidos, marker falho ou ignorado, readback, retry/replay e linhas históricas A/B. O entrypoint BB122 instala de verdade120 e121 em processos novos antes de avançar122, além da ativação fresh; preserva retorno400 real e verifica schemas/linhas completos e NOOP semDDL. Provas finais ligadas ao manifesto estão em `evidence/resume443-final-composition.json` e `evidence/bb122-final-composition.json` quando fechadas.

O workflow Empresas conserva os361 comandos anteriores na mesma ordem e acrescenta13. O workflow BB conserva seus100 comandos anteriores e acrescenta dez, distribuídos pelos dois bancos. O smoke HTTP conserva os76 comandos anteriores; novas suítes são aditivas. Fixtures de suporte não são executadas isoladamente como testes.

Permanecem fora deste incremento: ledger sem caixa e recebimento/crédito posterior à restauração, ordenação de respostas por empresa e demais consumidores/configurações ainda não adaptados. Seus protótipos/revisões não são resultado publicado.

CNAB400 financeiro/outbound continua bloqueado pelo código BRL de CBR64185–86, contrato de isenção/juros herdados e esclarecimento de CBR64326–39 (14 posições versus9(013)v99). API18/19 continua dependendo de identidade estável do crédito e definição individual/cumulativa de `valorCreditoCedente`, componentes/datas/reversões. Tarifas/IOF/outros e reversões parciais240, operações nominais/protesto e outros consumidores ainda exigem implementação/provas próprias. Nenhuma homologação externa foi realizada.

CI442 também revelou a fixture `banco_brasil_resume_440_contracts.php` ainda exigindo runtime120 sob121. A correção progressiva443 valida header/runtime/mínimo atuais contra uma lista explícita120/121/122, preserva todos os cenários de protocolo e fixa o SHA256 da migration120 publicada. RED122 reproduzido; GREEN174 verificações. As14 suites unitárias originais do workflow BB foram reexecutadas localmente, sem modificar código produtivo.

A fixture SQL440 também tinha restaurado marcador120 após os vetos de versão. O forward443 exige a instalação nativa atual no início, testa tracking antigo e futuro derivados do release, e restaura apenas o marcador original comprovado após a corrupção deliberada da fixture. As quatro suítes completas passaram: protocolo174, SQL360, controller457 e concorrência504 cumulativos/nove cenários com dois waiters. Nenhum guard produtivo foi flexibilizado.

Lote442 local encerrado:360/360 comandos passaram, mas o batch terminou com exit1 na asserção de imutabilidade porque dois arquivos foram corrigidos durante sua execução. O relatório qualificado preserva esse limite;12suítes afetadas repetidas após a publicação de442 passaram no SHA7dd715f com fonte18539 intacta.

Fechamento HTTP: Sales completo92,28s/90,56s eBB400aux28,36s/28,48s nasduasbases;21tabelashistóricas e18.532arquivosruntime semdiferenças, master17, CSRF, ator restritoB, PDF/TCPDF/Poppler eúltimaauditoria comtestemunhareal. Ciclosfinanceiros/TLS32,13s/31,47s, AIfresh eAsaas aprovados. Smoke78 conserva76anteriores; `pr45-validation-443.md` e `evidence/http-resume443.json` são asprovasfinais.143PythonPASS39,602s.
