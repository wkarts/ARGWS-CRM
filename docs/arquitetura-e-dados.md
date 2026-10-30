# Arquitetura e dados — ARGWS CRM

## Base existente

A aplicação mantém CodeIgniter 3 e PHP 8.1 ou superior. `application/` contém o núcleo MVC, configurações, bibliotecas e migrations centrais; `modules/` contém os 35 recursos já distribuídos; `install/` contém o instalador e o dump inicial; `system/` contém o framework. O banco é MySQL/MariaDB, com prefixo configurado por instalação.

O estado dos recursos permanece em `tblmodules` (`module_name`, `active`, `installed_version`). A tela **Recursos ARGWS** usa os identificadores originais e os hooks existentes, mantém rotas e dados e não oferece upload, desinstalação ou atualização por licença. O Chat requer o Connector; o Connector pode usar o Manager opcionalmente e continua operando sem ele. Ativação, desativação e atualização local do banco usam POST e as permissões administrativas existentes.

As configurações globais permanecem em `tbloptions`. A migration central `342` adiciona as opções de suporte e terminologia, restringe o idioma a `portuguese_br`, salva valores anteriores para rollback seguro e ajusta os idiomas registrados de funcionários, clientes e leads. Não foi criada tabela de suporte ou glossário. A migration `145` do recurso Asaas introduz o nome neutro `client_id`, copia os vínculos da coluna legada e guarda metadados para um rollback que preserve identificadores existentes.

## Compatibilidade e dados

O histórico de migrations já distribuídas permanece intacto. Alterações de schema são aditivas e estão nas migrations novas. A migration 342 não exclui dados de negócio; seu rollback restaura apenas valores que ainda correspondam ao estado aplicado por ela. Os campos `perfex_client_id`, o tema legado `perfex`, opções de SaaS antigas e metadados Stripe `pcrm-*` permanecem como compatibilidade de banco/integração e não são usados como identidade visível.

O conteúdo enviado por clientes em pastas de upload não faz parte dos pacotes de distribuição. Os pacotes preservam diretórios de segurança, modelos genéricos e imagens de placeholder, e deixam intactos os uploads já existentes durante a atualização. O controle anti-bots opcional lê somente listas locais em cache; não busca mais arquivos de terceiros em cada expiração.

## Atualização

O código é distribuído pelo canal de releases ARGWS, com ZIP completo, ZIP incremental e checksums. O painel informa a migration central aplicada e a incluída no código; não envia identificadores da instalação nem baixa código automaticamente. Recursos mantêm suas migrations locais e recebem atualizações dentro dos pacotes ARGWS, sem um atualizador de marketplace.

## Evidências e limites

- O teste existente `node tests/custom_links_security.test.cjs` cobre os Links personalizados.
- O novo teste `node tests/argws_platform_contract.test.cjs` verifica invariantes de recursos, idioma, suporte, privacidade e empacotamento.
- O Dockerfile executa `php -l` durante o build e o workflow publica `linux/amd64` e `linux/arm64` em tags versionadas.
- A validação de runtime PHP, build Docker e migrations em banco limpo/atualizado depende de PHP, Docker e MySQL/MariaDB disponíveis; a execução desta entrega registra exatamente quais deles puderam rodar.
