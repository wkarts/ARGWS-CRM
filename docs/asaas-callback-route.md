# Correção isolada da rota de webhook Asaas

## Causa e escopo

O roteador HMVC `Modules::parse_routes` acrescenta o nome do módulo ao destino. A regra antiga `asaas/gateways/callback/index` acrescentava `asaas` uma segunda vez e não localizava o controller de callback correto. A regra relativa `gateways/callback/index` resolve `/asaas/gateways/callback` em `modules/asaas/controllers/gateways/Callback.php::index`.

A imagem enviada da instalação mostrava uma página404 sem dados pessoais ou stack trace. Isso não comprova exposição, sucesso do POST nem que o código desta correção esteja implantado. Não houve POST, token, consulta de dados ou alteração na instalação real.

## Resposta pública

GET/HEAD/OPTIONS/PUT/DELETE recebem405 com Allow:POST, corpo fixo, Cache-Control:no-store e nosniff. A biblioteca de pagamentos só é inicializada após o método ser validado. Falhas de inicialização retornam503 genérico; nomes de exceções e mensagens do gateway não entram na resposta pública. O corpo já era limitado a1MiB+1byte e esse limite foi preservado.

POST continua encaminhando headers normalizados e corpo ao mesmo `handle_webhook`. Token, deduplicação, origem, filas, regras de recebimento/CONFIRMED/RECEIVED e lançamentos NÃO são modificados nesta correção. Status HTTP de sucesso/rejeição mantêm a semântica anterior; o texto passa a ser genérico. O handler financeiro e o SDK não mudaram.

## Testes e limites

`tests/asaas_callback_route.php`:29 verificações com MX_Router real do repositório, localização do controller e chamadas sintéticas de método/header/resultado. Controller usa doubles para o container/gateway; não é homologação HTTP de uma instalaçãoCRM completa. CI PHP8.1/8.5 executa esse teste. Compatibilidade com o ramo de origem imutável também foi verificada localmente pelos testes59+102, sem rede.

Esta PR depende da PR40 e incrementa o módulo de 1.4.6 para 1.4.7. A migration nativa 147 é NOOP: registra a correção sem novo DDL. O install executa App_module_migration, incluindo a migration146 de origem da PR40 em instalações novas; não reescreve migrations aplicadas. Ordem: PR40 (146), esta PR42 (147), segurança ampliada (148, em preparação). Não altera configurações, credenciais, webhook remoto ou política financeira. Segurança ampliada, URL/token na UI, cartão hosted e coordenação transacional seguem em entrega separada.

## Aplicação e reversão

Fazer revisão e homologação antes de implantação pelo responsável. Após atualizar arquivos, GET da URL deve produzir405; isso apenas demonstra rejeição de método, não autenticação/entrega de eventos. Testar POST autenticado somente em homologação autorizada. Não cadastrar tokens via URL. Não reduzir o marcador de versão nem apagar o histórico para rollback. Uma reversão deve ser uma nova versão/migration revisada; restaurar a rota anterior reintroduz o defeito. Não houve merge/deploy.

## Instalação e upgrade versionados

O framework grava o header antes do hook de primeira ativação. O install estabelece o baseline1.4.5 somente nesse caso ou na reativação e executa146→147 nativamente; instalações anteriores conservam o marcador anterior e seguem o executor. A146 é idempotente e a147 não altera tabelas. Falha de DDL impede avanço de versão.

Além dos29 testes de rota,8 verificações locais usam o App_module_migration real com doubles de banco. A matriz CI PHP8.1/8.5 com MySQL8 testa instalação, upgrade146→147, reativação, preservação de histórico, prefixo, falha de146 e retomada idempotente. MySQL não foi executado localmente; resultado deve ser conferido no head publicado.
