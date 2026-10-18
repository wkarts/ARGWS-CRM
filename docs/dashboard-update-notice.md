# Aviso de atualização do painel

O aviso persistido em `update_info_message` é exibido uma vez ao administrador
pelo hook `before_start_render_dashboard_content`. Um fragmento incompleto como
`<div class="col-md-12"><div class="alert alert-success">OI` consegue envolver os
cards seguintes na mesma classe de alerta quando é impresso sem normalização.
A reprodução é sintética; não demonstra qual conteúdo existiu em uma instalação
real, sua origem ou a ocorrência de exploração.

## Contrato de renderização

- `app_safe_update_message()` usa o HTMLPurifier 4.18.0 já distribuído no CRM,
  com política própria, sem os hooks que podem desabilitar o purificador genérico.
- Preserva texto UTF-8, títulos, parágrafos, listas, ênfase e links HTTP/HTTPS,
  relativos e âncoras. As classes permitidas são a coluna nativa, os quatro tipos
  de alerta e `bold`; tags abertas são normalizadas antes da inserção no painel.
- Remove scripts, eventos, CSS, IDs, imagens, formulários, frames e objetos.
  Conteúdo persistido não é uma fonte autorizada de código executável.
- O administrador recebe o resultado sanitizado e o aviso é consumido. Os demais
  usuários não veem nem consomem o aviso. A sanitização ocorre antes de limpar a
  opção, sem fallback para HTML bruto em caso de erro.
- O gerador atual mantém a estrutura e o texto de atualização, agora em UTF-8,
  com orientação para recarregar manualmente e revisar caches quando necessário.

As migrations antigas armazenavam scripts de recarga imediata ou temporizada.
Esses scripts deixam de executar, inclusive em um aviso antigo ainda pendente;
o texto que os acompanha é preservado. Se o aviso antigo disser que haverá uma
recarga automática, faça a recarga manualmente. Um aviso contendo apenas script
é consumido sem saída visual. Nenhuma migration histórica é reescrita.

A alteração é no helper do core, sem modificação funcional de módulos, schema
ou dados históricos. Não muda menu, tema, CSS ou cores do painel. Antes de
publicar a correção, componha a nova migration NOOP e os metadados de versão do
core conforme a sequência real da base e do processo de release. Este checkpoint
não reserva número nem altera a versão global; a alocação depende da integração
das releases concorrentes.

## Verificação

```sh
php -l application/helpers/misc_helper.php
php -l tests/dashboard_update_notice.php
php tests/dashboard_update_notice.php
# Opcional: exportar fragmentos sintéticos para inspeção em browser isolado.
php tests/dashboard_update_notice.php /tmp/dashboard-notice-fixtures.json
```

Os testes usam opções e administrador em memória. Cobrem o defeito antes/depois
com DOM, hierarquia de irmãos, HTML malformado, classes/links, vetores de conteúdo
ativo, reload legado, aviso gerado e exibição única por administrador. Não carregam
o CRM instalado, não acessam banco e não fazem chamadas externas.

Validação local: 88 verificações e lint passaram em PHP WASM 8.1.34 e 8.5.10.
O workflow `dashboard-update-notice.yml` executa o mesmo teste em PHP CLI 8.1/8.5;
o resultado desse CI deve ser conferido no commit publicado. A tentativa local
de Chromium headless encerrou antes de abrir a página, porque o executor negou a
criação de socket (`Operation not permitted`). Não há validação visual de browser
nem evidência de produção; a reprodução executada usa DOMDocument/libxml.
