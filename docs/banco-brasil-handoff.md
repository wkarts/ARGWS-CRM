# Banco do Brasil — transferência do trabalho em andamento

## Ponto de partida

- Repositório público: https://github.com/wkarts/ARGWS-CRM
- PR: https://github.com/wkarts/ARGWS-CRM/pull/45
- Branch: `feat/banco-brasil-billing-cnab240`.
- Base da #45: `aa58b832b3c5ed04a599caacba125b056c081ba7` (core 4.0.2 / migration 402); head revisado `a0097dd99e5213b71c9ff6853a679caaeb975f09` (core 4.0.3 / migration 403; BB 1.0.2 / migrations 101 e 102).
- A #46 `wip/companies-core-checkpoint-5833c02e` tem base na branch da #45. Nela, BB foi adaptado ao núcleo Empresas: BB 1.0.3 / migration nativa 103 NOOP, após core 4.0.4 / migration 404. A #46 segue WIP e em rascunho; o SHA vigente está no cabeçalho do PR.
- Este handoff descreve implementação parcial, não recomendação de merge ou homologação. Confira os SHAs atuais das duas PRs antes de iniciar alterações concorrentes.

## O que existe

1. Instalação e upgrade nativos `App_module_migration`, validação estrutural InnoDB e tracking somente após sucesso.
2. Cadastro visual de conexões com origem por empresa, conta, ambiente, convênio e carteira; oito guias administrativas.
3. Perfis 240/400 imutáveis, seleção explícita por conexão e nenhum fallback por região/formato.
4. Codec restrito de remessa 240 P/Q e parser de retorno T/U. Parser 400 CBR643 e codificador de candidato CBR641.
5. Importação observacional, deduplicação/conflito de conteúdo, auditoria e proveniência transacionais. Não há baixa financeira, emissão ou envio bancário.
6. Na composição da #46, o controlador usa `IssuerCore`/`IssuerRegistry` nativos e o `CompanyRequestScope` selecionado. A allowlist limita o contexto BB aos métodos `index`, `register_connection`, `register_layout` e `import_return`; IDs de outra empresa são recusados, e formulários POST levam o token de contexto. Os testes A/B são sintéticos; isso não prova o rollout multiempresa de todo o CRM.

## Lacunas obrigatórias

- A integração BB→núcleo Empresas está na #46, limitada às quatro rotas listadas acima. Permanecem pendentes o rollout global do contexto, validação MySQL/HTTP/navegador e consumidores de jobs, webhooks e demais módulos. Não criar outro módulo Empresas nem conceder acesso por `is_admin` global.
- Obter e verificar a especificação OpenAPI atual do BB; implementar OAuth, proteção de credenciais/configuração visual e os executores de cobrança/consulta/cancelamento suportados pelo contrato. Não inventar endpoints, escopos ou certificados.
- Integrar emissão, origem imutável, reserva de Nosso Número, lotes, download auditado, retry idempotente e reconciliação ao core isolado. Tabelas reservadas não provam funcionalidades conectadas.
- Completar remessa operacional 240 e 400. O candidato 400 está intencionalmente indisponível: código BRL e divergência do picture do trailer exigem confirmação do contrato/manual. Não enviar o candidato sintético ao banco.
- Retorno permanece observação. Definir e testar elegibilidade de baixa, pagamentos parciais, tarifas, estorno, eventos fora de ordem, reimportação e conciliação assistida antes de qualquer escrita financeira.
- Homologar separadamente API, CNAB240 e CNAB400 por conta/convênio/carteira. Sem transações ou dados reais nesta transferência.

## Fontes e aprendizado

Leia `docs/banco-brasil-cnab240.md` e `docs/banco-brasil-cnab400.md`: URLs oficiais BB/FEBRABAN, revisões, hashes dos PDFs e limites de cada codec estão registrados ali. Catálogo: https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/ .

O projeto `wkarts/argws-financial-platform` foi abandonado e nunca homologado pelo usuário. É somente referência de aprendizado; não copiar stubs ou promover suporte presumido.

## Validação neste checkpoint

O total de 412 verificações PHP WASM pertence a um checkpoint anterior e não certifica os heads atuais. Use os workflows da cabeça exata das PRs para validar código e banco descartável; resultados de SHA anterior são apenas diagnóstico. Nenhum browser autenticado, credencial BB, processamento real ou homologação bancária foi realizado.

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

Completar o contrato API oficial e capacidades CNAB240/400, emissão/origem/reserva/lotes e reconciliação sem falsos pagamentos; testar isolamento A/B também em jobs/webhooks/documentos, concorrência e instalações MySQL/MariaDB limpas e upgrade; homologar cada canal por conta/convênio. Cada correção funcional BB exige novo header e migration nativa (NOOP quando aplicável); migrations publicadas não são reescritas. Manter sequência 402→403 (#45)→404 (#46); integrar main em develop por merge normal somente depois de main realmente atualizada e validada.
