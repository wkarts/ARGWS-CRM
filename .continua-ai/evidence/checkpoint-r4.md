# Checkpoint local — ARGWS-CRM — 2026-10-27 r4

## Escopo

Entrega exclusivamente local. Nenhuma alteração foi enviada ao GitHub, nenhum merge ou deploy foi executado.

Este checkpoint consolida a convergência verificável da PR 52 e registra claramente o que ainda depende do ambiente oficial de CI, PHP/DB/Chromium e homologação bancária.

## Implementado neste ciclo

- Rotas de Relatórios, Calendário, Assets e Certificados foram convergidas para os controladores/Settings oficiais; os shards paralelos correspondentes estão inertes como tombstones.
- O menu principal do shell empresarial agora usa rotas canônicas para Vendas (`invoices`, `drafts`, `recurring`, `list_credit_notes`), Despesas (`expenses`), Projetos (`projects`), Suporte (`tickets`), Relatórios (`reports`), Calendário (`utilities/calendar`) e Configurações (`settings`).
- O fixture de navegação foi corrigido para não manter afirmações contraditórias e passou a verificar os destinos oficiais.
- A propriedade do teste de overflow do header permanece em um único workflow; o workflow de experiência não repete essa validação.
- Os testes que exigem PHP ausente fazem skip explícito, sem transformar indisponibilidade de runtime em aprovação falsa.
- O inventário estático foi reconciliado para 88 views e 47 controllers/endpoints ativos; 40 controllers `Company_*` executáveis ainda são especializados e permanecem pendentes de adapter nativo equivalente.
- O validador local passou a registrar evidência em `.continua-ai/evidence/local-validation-r4`.

## Validação executada

| Gate | Resultado |
|---|---|
| `node --test tests/*.test.cjs` | 219 pass, 3 skip por PHP ausente, 0 fail |
| `python3 tests/company_ui_surface_inventory.py` | PASS — 88 views, 47 controllers/endpoints |
| `python3 tests/ci/test_native_company_focused.py` | PASS — 10 testes |
| ownership do workflow de header | PASS — somente `native-header-layout.yml` |
| sintaxe Node | PASS — 232 arquivos |
| PHP runtime / HTTP / DB | BLOQUEADO — PHP e Docker indisponíveis |
| build Mix | BLOQUEADO — `node_modules/.bin/mix` indisponível |
| validação YAML Ruby | BLOQUEADO — Ruby indisponível |

Evidência detalhada: `.continua-ai/evidence/local-validation-r4/local-validation.json`.

## Limitações que permanecem

1. A remoção total do sistema paralelo ainda não está concluída: 40 controllers `Company_*` e suas operações especializadas precisam ser migrados, com testes positivos/negativos, para adapters oficiais antes de serem tombstonados.
2. CNAB 400 do Banco do Brasil ainda depende de confirmação documental do layout, geração vinculada a faturas, conciliação de retorno e homologação bancária real. Os gates locais não autorizam declarar liquidação operacional.
3. Não foi possível provar HTTP autenticado, isolamento entre duas empresas, troca principal/secundária/principal, acesso direto, formulários antigos, anexos, permissões, responsividade, upgrade histórico ou build final sem PHP, banco, Chromium e dependências do projeto.

## Próximo passo obrigatório

Executar este mesmo conjunto no CI pinado com PHP 8.1/8.5, MySQL/MariaDB, Chromium/Playwright, Ruby e dependências Mix; depois migrar cada família restante e reabrir os gates de CNAB 400 com documentação/homologação do Banco do Brasil.
