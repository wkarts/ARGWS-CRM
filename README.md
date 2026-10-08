# ARGWS CRM

CRM da ARGWS para execução em hospedagem PHP tradicional ou em container FrankenPHP. A distribuição mantém configurações e dados de cada instalação fora da imagem.

## Requisitos

- PHP 8.1 ou superior para instalação tradicional.
- MySQL ou MariaDB.
- Extensões PHP: `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `iconv`, `imap`, `gd` e `zip`.
- Docker Engine e Docker Compose para a distribuição em container.

O instalador fica em `install/`. Configure o domínio para servir a raiz do projeto, prepare a base de dados e confirme que as extensões estão habilitadas na mesma versão do PHP usada pelo PHP-FPM ou pelo servidor web. Consulte [Operação PHP e containers](docs/operacao-php-e-containers.md).

## Container FrankenPHP

A implantação recomendada usa os Compose por ambiente e o binário `argws-crm-deployer` documentados em [deploy/README.md](deploy/README.md). O deployer prepara `compose.yaml` e `.env`; a configuração, o banco, as senhas e os uploads ficam fora da imagem.

A imagem GHCR não inclui o diretório web `install/`. O primeiro provisionamento ocorre uma vez pelo terminal, com um comando CLI que importa o schema em banco vazio e solicita os dados do primeiro administrador sem exibir a senha. Antes da conclusão, o endpoint web responde 503; não há rota de instalação pública nem credenciais master padrão.

O Compose de raiz permanece disponível para um banco MySQL/MariaDB externo. Copie `container.env.example` para `.env`, configure as credenciais locais e siga [Operação PHP e containers](docs/operacao-php-e-containers.md). Para instalações PHP tradicionais, o instalador web continua em `install/`.

## Idioma, suporte e privacidade

Português do Brasil é o idioma disponível nesta versão. A política de terminologia e as configurações do widget de suporte são administradas no painel em **Configurações → Plataforma ARGWS**. O endereço do suporte e o token público são definidos na própria aplicação.

A telemetria de usuários e licenciamento e a validação remota de licença ficam desativadas. Logs operacionais permanecem locais; integrações de negócio configuradas pelo administrador continuam disponíveis para os fluxos que elas atendem.

## Releases e atualizações

O canal `develop` publica uma imagem de desenvolvimento. As promoções para `main` usam SemVer automático e publicam no GHCR as tags de versão, major.minor, major e latest, além de ZIP completo, ZIP incremental e checksums SHA-256. A versão do produto e o nível de migration do banco evoluem independentemente.

Faça backup do banco, da configuração local e dos uploads antes de atualizar. O pacote incremental preserva os dados enviados pelos clientes e lista arquivos de código removidos. Consulte [Distribuição ARGWS](docs/distribuicao-argws.md) para os detalhes do versionamento, build, cache e publicação e [Arquitetura e dados](docs/arquitetura-e-dados.md) para as migrations e os recursos nativos.
