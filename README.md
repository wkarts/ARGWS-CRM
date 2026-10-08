# ARGWS CRM

CRM da ARGWS para execução em hospedagem PHP tradicional ou em container FrankenPHP. A distribuição mantém configurações e dados de cada instalação fora da imagem.

## Requisitos

- PHP 8.1 ou superior para instalação tradicional.
- MySQL ou MariaDB.
- Extensões PHP: `mysqli`, `pdo_mysql`, `curl`, `openssl`, `mbstring`, `iconv`, `imap`, `gd` e `zip`.
- Docker Engine e Docker Compose para a distribuição em container.

O instalador fica em `install/`. Configure o domínio para servir a raiz do projeto, prepare a base de dados e confirme que as extensões estão habilitadas na mesma versão do PHP usada pelo PHP-FPM ou pelo servidor web. Consulte [Operação PHP e containers](docs/operacao-php-e-containers.md).

## Container FrankenPHP

A imagem genérica publicada no GHCR é `ghcr.io/wkarts/argws-crm`. O arquivo `compose.yaml` aponta para a release estável atual por padrão; defina `ARGWS_VERSION` para escolher outra tag. Configure a conectividade com um serviço MySQL/MariaDB externo e mantenha os volumes persistentes da instalação.

```sh
docker compose pull
docker compose up -d
```

A aplicação escuta na porta interna `8080`. Configure HTTPS no proxy reverso. Não grave configuração de clientes ou uploads na imagem. Veja [a documentação de operação](docs/operacao-php-e-containers.md) para configurar e atualizar a instalação.

## Idioma, suporte e privacidade

Português do Brasil é o idioma disponível nesta versão. A política de terminologia e as configurações do widget de suporte são administradas no painel em **Configurações → Plataforma ARGWS**. O endereço do suporte e o token público são definidos na própria aplicação.

A telemetria de usuários e licenciamento e a validação remota de licença ficam desativadas. Logs operacionais permanecem locais; integrações de negócio configuradas pelo administrador continuam disponíveis para os fluxos que elas atendem.

## Releases e atualizações

O canal `develop` publica uma imagem de desenvolvimento. As promoções para `main` usam SemVer automático e publicam no GHCR as tags de versão, major.minor, major e latest, além de ZIP completo, ZIP incremental e checksums SHA-256. A versão do produto e o nível de migration do banco evoluem independentemente.

Faça backup do banco, da configuração local e dos uploads antes de atualizar. O pacote incremental preserva os dados enviados pelos clientes e lista arquivos de código removidos. Consulte [Distribuição ARGWS](docs/distribuicao-argws.md) para os detalhes do versionamento, build, cache e publicação e [Arquitetura e dados](docs/arquitetura-e-dados.md) para as migrations e os recursos nativos.
