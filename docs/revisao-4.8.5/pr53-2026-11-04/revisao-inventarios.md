# Reconciliação dos inventários e da fixture MySQL

Revisão complementar sobre `d453e8e51327ba709f1cff271acba347500aba38`, comparando os arquivos com `origin/main` em `3c35555a94e7ba7eaaad8abd7a78a59c48504d7b`.

O job `inventory` 126624464830 reprovou 13 hashes desatualizados. O monitor cancelou outros workflows por essa falha; os cancelamentos não são aprovações. Os jobs 126626232671 e 126626233135 também identificaram os resumos de fronteiras e superfícies desatualizados. Os scripts oficiais regeneram esses registros sem mudar seus critérios. Os hashes esperados pelo CI coincidem com os gerados localmente.

| Arquivo | Delta revisado e alcance da evidência |
|---|---|
| `CompanyRequestScope.php` | Retorno OAuth/QR e seleção explícita do formulário da instalação; `native_context.php` verifica contexto, verbos e CSRF. Não homologa todos os controladores. |
| `CompanySales.php` | Projeção de pesquisa remove campos privados somente quando presentes; casos SQLite/MariaDB em `sales.php`, sem retirar issuer/kind. |
| `InvoicePaymentWriter.php` | Comparação escalar de request_key preserva issuer/event, identidade financeira e conflito de payload; agregado MySQL de ações nativas e meios manuais. |
| `NativeAdminMenuCatalog.php` | Links adicionais para recursos existentes; não é autorização nem prova de funcionamento dos destinos. |
| `NativeCompanyAdminPresentation.php` | Catálogo da instalação limitado ao administrador da principal, permissão de tarefas antes do timer, correção do nome de sua ação; testes de navegação/contexto. |
| `NativeCompanyContractMail.php` | Recibo mantém issuer/subject/event e duplicidade rejeitada; apenas comparação JSON alterada. Transporte externo não homologado. |
| `NativeCompanyFinancialMail.php` | Recibo mantém issuer/event e duplicidade rejeitada; apenas comparação JSON alterada. Transporte externo não homologado. |
| `NativeCompanyLegacySales.php` | Histórico preserva issuer/subject/event e exige source escalar exata; teste rejeita tipo de documento diferente e caixa diferente. |
| `NativeCompanyRoutes.php` | Admissão de adaptadores existentes para meios, papéis, conhecimento, filtros, lembretes e GDPR, com verbos explícitos. Catálogo não comprova cada subação. |
| `NativeCompanySettingsController.php` | Controles da instalação condicionados à principal; diretório de gateways em secundárias somente GET/HEAD. Testes de Settings não homologam provedores. |
| `native_company_layout_head.php` | Uso do head oficial quando há apresentação nativa ativa; fixture Settings passou no navegador remoto, sem comprovar sessão HTTP completa. |
| `Vendors_portal.php` | Corrige nome da view do perfil; portal de fornecedor e módulo completo de compras não homologados entre empresas. |
| `purchase.php` | Versão 1.5.3 → 1.5.4 e migration 154 já presentes na PR. Não é evidência de isolamento do módulo. |

Todos os 13 registros continuam **unreviewed** no inventário de isolamento: esta leitura do delta e a cobertura delimitada não encerram a auditoria do arquivo inteiro. Permanecem 648 registros unreviewed e 30 partial, com `operational_rollout=blocked`. O inventário amplo mantém 15.173 arquivos unresolved e `operational_ready=false`. Nenhum gate, teste ou requisito de operação foi reduzido.

O censo de ações foi regenerado em arquivo temporário e comparado sem diferença. `matriz-acoes.csv` recebeu somente normalização CRLF → LF, preservando seu conteúdo.

O job MySQL 126626233380 revelou uma falha independente na fixture `native_context.php`: o schema de cabeçalho usa FK para `contacts`, ausente na fixture mínima. Reproduzida em MariaDB no código da PR; a mesma execução da main passou. A correção instala a DDL original de contatos antes do schema, mantendo as FKs e os gates. A limpeza dos dados sintéticos passa a incluir as duas tabelas de cabeçalho, exclusivamente no prefixo aleatório validado. Após a correção, 137 verificações passaram tanto em MariaDB quanto em SQLite. Isso corrige a fixture introduzida na PR, não demonstra uma falha na instalação real.

O workflow Interface nativa multiempresa 41844529810 passou no SHA d453e8e, incluindo o novo teste desktop/mobile da fixture oficial de Settings. O Chromium local permanece impedido por restrição de socket. Navegação autenticada da aplicação completa e PWA instalado continuam pendentes.
