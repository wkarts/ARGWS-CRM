# Distribuição ARGWS

## Versionamento

`VERSION`, `ARGWS_VERSION` e a migration central seguem o mesmo release: `3.4.2` e `342`. O script `scripts/check-release-version.sh` valida a SemVer, a migration e, quando informado, o nome da tag `vX.Y.Z`.

## Imagem FrankenPHP no GHCR

O workflow `.github/workflows/container-publish.yml` valida a versão, executa os testes e constrói a imagem ARGWS CRM. Em pull requests ele não publica. Em `main` e em tags válidas ele publica no `ghcr.io/wkarts/argws-crm`, incluindo tags versionadas e multi arquitetura (`linux/amd64` e `linux/arm64`). As imagens são genéricas: banco, chave de criptografia, URL, configurações e uploads ficam fora da imagem, em volumes da instalação.

O fluxo segue os princípios observados na distribuição da ARGWS Connect API, especialmente a separação entre build e publicação, tags derivadas da versão, cache de build e retenção protegida. A implementação local foi adaptada ao projeto PHP/FrankenPHP: não importa, executa nem depende da Connect API.

O cache BuildKit usa o escopo `argws-crm-buildx`. `.github/workflows/build-cache-cleanup.yml` limita a limpeza ao cache de build, protege refs de releases e execuções ativas e começa em modo de simulação quando acionada manualmente. Ela não apaga imagens nem tags GHCR.

## Pacotes PHP

Ao criar uma tag `vX.Y.Z`, `.github/workflows/release-packages.yml` usa a tag imediatamente anterior para montar o ZIP completo e o ZIP incremental, valida o overlay e publica ambos com checksums na release ARGWS. Na primeira publicação, quando ainda não há uma tag de versão anterior, o workflow usa o primeiro pai do commit marcado como base do ZIP incremental e informa essa condição no resumo da execução. `scripts/package-release.py` também permite gerar e verificar esses pacotes localmente.

O ZIP completo contém a versão pronta para uma instalação nova ou substituição planejada. O incremental inclui somente arquivos alterados/adicionados e lista os caminhos de código removidos em `REMOVED-FILES.txt`. Aplique o incremental sobre uma cópia de segurança, preserve `application/config/app-config.php` e os uploads e remova manualmente apenas os arquivos de código obsoletos indicados. Depois, execute a migration pelo painel.

Os pacotes não incluem credenciais, `.env`, configuração local, logs, cache ou uploads de clientes presentes na árvore de origem. Amostras genéricas e imagens de placeholder necessárias ao produto são mantidas.
