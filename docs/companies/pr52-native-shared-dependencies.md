# Views nativas e retirada do aviso legado

Incremento sobre9ceec2e, ainda incompleto para aceite geral.

## Retirada concreta

Removidos do Git `application/controllers/admin/Company_master.php` e
`application/views/admin/issuers/master_resolution.php`. Não há redirect ou
controller substituto de responsabilidade. A página remanescente só emitia um
aviso estático do procedimento já aposentado; não executava criação/edição.
Os testes HTTP passam a exigir404 nessa rota e a abrir Empresas pelo endereço
original, com criação disponível ao administrador de instalação persistido.

Serviços de identidade, ACL, auditoria e manutenção histórica não foram apagados
nem substituídos por inferência do menor ID/e-mail. A resolução de instalação
legada sem evidência suficiente continua sendo uma pendência do objetivo geral;
essa retirada não é alegação de conclusão da regra do primeiro administrador.

O censo cai de49 para48 controllers paralelos pela exclusão efetiva de um arquivo.
Ainda há51 views usando o shell paralelo; não há promoção artificial de prontidão.
O inventário UI passa a100 views/55 controllers. A conferência é feita em checkout
limpo da árvore candidata, pois o diretório anterior reapresentou cópias antigas
como untracked após o git rm. Nenhuma restrição de permissão foi contornada.

## Dependências do fluxo completo

O helper de campos não era carregado em Todo/anúncios/catálogos, embora as views
oficiais o utilizem. O bootstrap nativo agora carrega para todos os controllers
admitidos o mesmo conjunto de definições puras de campos, tabelas, campos
personalizados, texto, template e idioma já necessário ao Staff. Não carrega
models legados ou hooks de providers para preencher essas dependências.

O body preserva classes de controller, método e view para os seletores originais
do CSS/JavaScript, incluindo dashboard e calendário, com validação estrita e escape
na saída. Não usa o hook global de classes. Os testes PHP verificam preservação
e recusa de injeção de atributo; o teste autenticado mantém a sequência de rotas
nativas e salva somente categoria/nome do helper, sem HTML/valores, se uma falha
de rota ocorrer.

## CI reconciliado do9ceec2e

- Focused PR40407126723 e push40407118748: aprovados, PHP8.1/8.5, serviços reais,
  SQLite/MySQL e templates Chromium. Interface40407127248 e PWA40407127169 aprovados.
- Stack40407127239: passou dashboard móvel (390px/scrollWidth390px), parou na
  abertura de `/admin/todo`. A revisão das views identifica a dependência de
  campos ausente nesse caminho. Não foi alegada aprovação de Staff HTTP posterior.
  Artefato12691384074 foi conferido: SHA256
  `6b5c8a7d25a17e0944da4fc11ecfeb590a70d82f6315a94eab2925d0d4bae429`.
- BB40407126988: unitários/lint8.1/8.5 e checkout aprovados. MariaDB passou
  instalação/upgrade, originais240/400 e driver/concorrência; parou na expectativa
  do erro da auditoria do download. Empresas40407127027 chegou ao mesmo cenário.
  O core sanitiza a RuntimeException como `issuer_storage_failed`; a prova agora
  usa esse contrato e exige auditoria byte-identical após rollback. O guard de
  produção foi preservado. MySQL não tinha terminado na última consulta.

O workflow BB continua com todos os comandos e timeouts; trace dos nomes dos
testes foi acrescentado para localizar uma execução interrompida sem registrar
credenciais/payloads. Isso não substitui nem encurta os validadores.

PHP/banco/navegador do incremento novo ainda precisam ser executados no CI.
PWA, dois canais BB, originais privados, arquivos protegidos e históricos são
preservados. Nenhuma cobrança, SMTP ou integração externa real foi executada.
