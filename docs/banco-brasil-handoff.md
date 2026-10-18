# Banco do Brasil — transferência do trabalho em andamento

## Ponto de partida

- Repositório público: https://github.com/wkarts/ARGWS-CRM
- PR: https://github.com/wkarts/ARGWS-CRM/pull/45
- Branch: `feat/banco-brasil-billing-cnab240`.
- Base revisada: `aa58b832b3c5ed04a599caacba125b056c081ba7` (core 4.0.2 / migration 402).
- Checkpoint de implementação BB: `b799212896756839888a25de4f9f664f271ebb45`, agora composto sobre a base acima.
- Alvo reservado pelo usuário: **core 4.0.3 / migration 403**, módulo **1.0.2 / migrations 101 e 102**. A migration 403 é marcador NOOP de release; o DDL BB fica nas migrations do módulo.
- Esta é uma transferência **incompleta e em rascunho**, não recomendação de merge ou homologação. O próximo commit da branch contém os arquivos descritos aqui. Consulte o SHA atual da PR antes de iniciar alterações concorrentes.

## O que existe

1. Instalação e upgrade nativos `App_module_migration`, validação estrutural InnoDB e tracking somente após sucesso.
2. Cadastro visual de conexões com origem por empresa, conta, ambiente, convênio e carteira; oito guias administrativas.
3. Perfis 240/400 imutáveis, seleção explícita por conexão e nenhum fallback por região/formato.
4. Codec restrito de remessa 240 P/Q e parser de retorno T/U. Parser 400 CBR643 e codificador de candidato CBR641.
5. Importação observacional, deduplicação/conflito de conteúdo, auditoria e proveniência transacionais. Não há baixa financeira, emissão ou envio bancário.
6. Guardas de autorização/contexto que negam operações quando o contrato de Empresas não está disponível. Isso não equivale a integração multiempresa operacional.

## Lacunas obrigatórias

- Adaptar o consumidor ao **núcleo Empresas**, em desenvolvimento separado. O checkpoint ainda usa a dependência histórica `issuers`; sua mera ativação não habilita os novos grants nativos. Não criar outro módulo Empresas nem conceder acesso por `is_admin` global. O adaptador experimental não integra este commit porque não foi validado.
- Obter e verificar a especificação OpenAPI atual do BB; implementar OAuth, proteção de credenciais/configuração visual e os executores de cobrança/consulta/cancelamento suportados pelo contrato. Não inventar endpoints, escopos ou certificados.
- Integrar emissão, origem imutável, reserva de Nosso Número, lotes, download auditado, retry idempotente e reconciliação ao core isolado. Tabelas reservadas não provam funcionalidades conectadas.
- Completar remessa operacional 240 e 400. O candidato 400 está intencionalmente indisponível: código BRL e divergência do picture do trailer exigem confirmação do contrato/manual. Não enviar o candidato sintético ao banco.
- Retorno permanece observação. Definir e testar elegibilidade de baixa, pagamentos parciais, tarifas, estorno, eventos fora de ordem, reimportação e conciliação assistida antes de qualquer escrita financeira.
- Homologar separadamente API, CNAB240 e CNAB400 por conta/convênio/carteira. Sem transações ou dados reais nesta transferência.

## Fontes e aprendizado

Leia `docs/banco-brasil-cnab240.md` e `docs/banco-brasil-cnab400.md`: URLs oficiais BB/FEBRABAN, revisões, hashes dos PDFs e limites de cada codec estão registrados ali. Catálogo: https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/ .

O projeto `wkarts/argws-financial-platform` foi abandonado e nunca homologado pelo usuário. É somente referência de aprendizado; não copiar stubs ou promover suporte presumido.

## Validação neste checkpoint

Reexecução local em PHP 8.5.10 WASM, sem rede bancária: 412 verificações distintas (CNAB240 118; CNAB400 82; store 48; admin 63; migration nativa 36; vínculos de layout 38; upgrade102 27). Há testes que incluem outros testes: não somar novamente os totais repetidos no console.

MySQL 8.4/concorrência/mysqli da **versão102** devem ser confirmados no novo SHA da PR pelo workflow `Banco do Brasil module`. O verde histórico da versão101 não valida102. Browser autenticado, homologação e execução operacional não foram realizados. Os gates de core/módulo também precisam passar no novo SHA.

Com PHP e MySQL de teste disponíveis:

```sh
php tests/banco_brasil_admin.php
php tests/banco_brasil_layouts.php
php tests/banco_brasil_layout_migration.php
# MYSQL_DSN/USER/PASSWORD apontando exclusivamente para banco descartável:
php tests/banco_brasil_mysql.php
php tests/banco_brasil_layout_mysql.php
php tests/banco_brasil_mysql_driver.php
python scripts/check-release-version.py
python scripts/check-module-migration-diff.py --base origin/main --head HEAD
```

## Critérios para encerrar

Configuração visual completa, operações autorizadas entre empresas A/B sem vazamento, origem preservada em jobs/webhooks e documentos, idempotência sob corrida, erros remotos visíveis, retornos reconciliados sem falso pagamento, testes reais de instalação/upgrade/repetição/falha e homologação por capacidade. Cada nova correção funcional do módulo deve incrementar sua versão e acrescentar migration nativa (NOOP quando aplicável); nunca editar migrations publicadas. Coordenar a versão core 403 com a fila de releases e não alterar migrations já aplicadas.
