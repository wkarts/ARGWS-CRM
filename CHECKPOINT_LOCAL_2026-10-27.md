# Checkpoint local — 2026-10-27

Este pacote registra o estado local solicitado. Ele não foi enviado ao GitHub e não representa a conclusão integral do contrato de convergência da PR 52.

## Entregas presentes

- CI reorganizado com preflight único, cancelamento de execuções superadas, ownership único dos inventários e redução das duplicidades entre matrizes.
- Rotas oficiais reconciliadas para Clientes, Staff, Contratos, Projetos, Tarefas, Chamados, Faturas, Orçamentos, Propostas, Configurações, Calendário, Assets e Certificados.
- Contratos oficiais preservam a história da empresa principal e usam IDs tipados para contratos próprios; o portal oficial cobre consulta, PDF, anexos, discussões e revogação.
- Portal oficial de projetos/chamados e portal oficial de propostas foram conectados aos controllers e temas Perfex existentes.
- Settings oficial recebeu assets, calendário, certificados e sete políticas por empresa; o método `Settings::isolated_index()` foi removido.
- Fluxos financeiros oficiais receberam adapters e views para créditos, despesas, cópias, reembolsos e relatórios.
- Banco do Brasil contém os fluxos locais API/CNAB e a implementação CNAB 400 vinculada às faturas, com geração, download privado, declaração de transmissão, importação e conciliação assistida.
- Consulta CNPJ/CEP de Clientes usa `Clients::lookup` e CSRF nativo; compartilhamento de identidade e acesso de contato a faturas estão nas rotas oficiais.

## Validação executada neste congelamento

- 453 arquivos PHP alterados: lint aprovado em PHP 8.1 e PHP 8.5.
- 48 arquivos JavaScript/CJS alterados: `node --check` aprovado.
- 8 JSON e 6 YAML alterados: parse aprovado.
- `tests/native_company_routes.php`: 1.668 asserções aprovadas em PHP 8.1 e 8.5.
- Clientes lookup oficial: 10 verificações aprovadas em ambas as versões.
- União de Contratos principal/owned: 68 verificações aprovadas em ambas as versões.
- Portal oficial de Contratos: 20 verificações SQL aprovadas em ambas as versões.
- Portal oficial de Projetos/Chamados: 29 verificações SQL aprovadas em ambas as versões.
- Settings oficial e formulários nativos: aprovados em PHP 8.1 e 8.5.
- Os logs desta rodada acompanham o pacote delta.

## Limites e pendências preservados

- O censo deste estado ainda encontra 46 controllers `Company_*`; vários são tombstones restaurados pelo sincronizador e outros permanecem porque seus gates, referências ou testes ainda não foram concluídos. Portanto, o sistema paralelo ainda não foi integralmente removido neste checkpoint.
- O adapter explícito de criação de cliente próprio na empresa principal via `/admin/clients/client?source=company` ainda precisa ser finalizado. Três browsers HTTP já foram migrados para rotas oficiais, mas dependem dessa conclusão.
- Adapters oficiais de rascunhos, cópias e recorrência de faturas passaram lint/rotas, mas ainda precisam de testes controller em PHP 8.1/8.5; browsers de Faturas/BB ainda contêm referências antigas.
- Financeiro oficial ainda precisa do gate exato em `CompanyRequestScope`, migração das referências remanescentes e suítes funcionais antes da remoção dos seis controllers paralelos correspondentes.
- Os subgrupos profundos do portal de Projetos estão ligados e lintados, mas os fixtures HTTP específicos precisam ser migrados/executados antes de apagar seus paralelos.
- Integrações, RH, Leads e Compras ainda não foram convergidos integralmente para controllers/views oficiais.
- A homologação externa real do Banco do Brasil, incluindo confirmação documental de detalhes do CNAB 400, não é comprovada por este pacote local.
- Docker não está instalado neste ambiente. Assim, a rodada final HTTP/MySQL/MariaDB/Playwright da aplicação completa não foi executada. Chromium local passou nos fixtures oficiais de Settings e identidade de Clientes; os demais browsers tiveram validação sintática.

## Conteúdo dos pacotes

- Pacote `full`: árvore-fonte local completa, sem logs de runtime, uploads, arquivos temporários ou configuração local secreta.
- Pacote `delta`: somente arquivos alterados em relação à extração local `staged`, mais `CHANGED_FILES.txt`, `DELETED_FILES.txt`, hashes e logs de validação.
- Não há deleções registradas no delta atual porque o sincronizador restaurou os tombstones; a lista de pendências acima registra essa situação explicitamente.
