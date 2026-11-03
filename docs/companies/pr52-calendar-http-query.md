# PR52 — query do calendário no limite HTTP

Base:d87bc7fb304a2b452e67fa978ebef3a0dfa8b839. Mantidos PWAa5ba730/descendentes, rascunho e restrições. Sem merge/deploy ou serviços financeiros externos.

## Evidência e alteração

Stack40425029952/job121928532950 recusou o feed com native_calendar_range_invalid_query_keys. A mesma resposta tinha URL com somente start2026-09-27/end2026-11-08, sem campos extras, registrada pelo navegador. O teste de fontes do mesmo SHA aprovou esse exato intervalo em SQLite. A recusa está na composição da entrada genérica do framework, e não há evidência para ampliar a whitelist das opções. A origem específica das chaves adicionais nesse array ainda não foi identificada; não alegamos que o navegador as enviou.

O endpoint passa a ler a query do REQUEST_URI do pedido HTTP, em formato relativo à origem, pela API server do próprio CI com limpeza textual desativada. Os tipos, datas, flags e nomes continuam validados pelo mesmo NativeCompanyCalendarFeed::filters. Não há fallback para o array GET ambiente, nem remoção silenciosa de campos desconhecidos da URL real. Empresa e ator continuam provenientes do contexto autenticado, antes da leitura dos dados.

Alvo ausente, absoluto/protocol-relative, com controles/espaços não codificados, fragmento, maior que8192 bytes ou query excessiva é recusado. O limite64 evita truncamento silencioso de parse_str/max_input_vars esconder parâmetros posteriores. Encodings normais de query são decodificados uma vez; campos desconhecidos, arrays onde são exigidos escalares e valores inválidos continuam recusados. Nenhuma origem empresarial é aceita da query. Sem query/contexto em logs ou em cache.

## Provas acrescentadas

O fixture dos endpoints, com limites de contexto explicitamente simulados e banco real, executa o alvo exato do navegador mesmo quando o array genérico contém metadados alheios. Também exige rejeição de issuer_id acrescentado à URL real mesmo quando ausente no array genérico, antes de consultar conteúdo de fontes. Alvo ausente/absoluto/excessivo não usa fallback. Permanecem todos os testes anteriores de escrita, revisão, CSRF, troca de empresa, revogação e revalidação do HTML. A prova de navegador autenticado continua exigindo200 e precisa passar no novo SHA. Fixture não é alegado como prova HTTP integral.

Base d87: Focused PR40425030208 passou PHP8.1/8.5; PWA40425029995 e Interface40425030321 aprovados. Stack falhou como descrito. Bancos/Empresas completos ainda precisam resultado final; unitários BB não são homologação bancária. Este executor não solicitou novos cancelamentos.

Local: Node/DateEnv/callback reais, sintaxe JS/bash, dois testes Python e reconciliação de inventários passaram. PHP/lint, novos testes SQL e navegador não executados localmente. Dezesseis diferenças acumuladas de fontes conferidas contra artefato oficial76d/artifact12694339060; não alegada varredura física completa. Os geradores oficiais precisam confirmar novamente.15082 fronteiras/4608 fontes/646 itens, prontidão false.

Ainda48 controllers de prefixo paralelo no censo, incluindo endpoints técnicos que precisam classificação; não houve retirada de interface neste incremento. Company_onboarding foi lido: já é o wizard modal de cadastro autorizado pelo requisito, não um painel de configuração. Não deve ser apagado para reduzir contagem. CNAB400 e demais pendências de unificação/integrações permanecem abertas. Este ajuste não é aceite integral nem liberação para produção.
