# HTTP447: fresh e upgrade real446→447

Fresh447 e upgrade do SHA publicado `b47d1666f7bb788bf29ead9c9aa03ff3f36221d1` aprovados em PHP8.3.6/MariaDB10.11.14. Fonte final:18574 caminhos, manifesto SHA256 `f8439b879c51f932b82ca275d7594f72dc0dc397fd80a3cf4df977b655024b6f`. A publicação447 e seus testes por SHA são registrados separadamente pelo responsável pela integração.

- Instalador nativo: CSRF inválido403 antes de DDL; identidade master17 por instalador. Upgrade real446→447 e BB122→123,310 tabelas históricas preservadas com apenas metadados nativos qualificados;3 tabelas LOCAL novas vazias antes dos fluxos.
- Tarifa A/B: cliente/fatura/remessa/upload/importação/vínculo pelo GUI; bruto105,53, tarifa2,53 e líquido103,00, com principal integral, replay/contexto/CSRF e PDF real.
- LOCAL A/B: protocolo sintético qualificado por writers canônicos com provider injetado apenas no setup. Consentimentos, declaração/restauração, recebimentos/créditos/estornos GUI, bearer dedicado, replay, isolamento A/B, TCPDF/pdftotext e JSON/CSV passaram no CJS original sem retirar asserções.
- Probe de bootstrap: bearer inválido/ausente401 JSON; GUI e identificador público não numérico continuam403 CSRF.
- Verificação estreita Task/Calendar no upgrade: leitura A/B e uma tarefa criada com default próprio, sem alterar opções globais ou dados financeiros.

REDs reais de CSRF bearer, apresentação restaurada no PDF/GUI e mapeamento HTTP de escopo foram preservados, corrigidos por deltas mínimos e seguidos de testes completos com novos títulos. Títulos parcialmente exercitados4/5,6/7 e8/9 do fresh permaneceram históricos; nenhum rollback SQL ou reinstalação. Também foram preservadas as falhas do teste de tarifa e dos auxiliares externos, qualificadas no JSON.

O tráfego ficou restrito ao CRM local. Nenhuma chamada bancária, homologação, merge ou deploy.

Corte remoto novo do SHA446:14 workflows aprovados e2 falhas (Calendar mês e Empresas8.1/memória), investigadas pelo responsável pelo CI. O Calendar original completo adicional passou77,009s no banco447 preservado, com21 fontes GUI,23 respostas capturadas200 e verificação imutável. GETs de novembro após opt-in hidratam dois títulosLOCAL/Tariff por empresa e passam; fresh permanece opt-out0. Isso não encerra a falha do histórico remoto maior.

Evidências consolidadas: `http-resume447.json` (SHA256 `7d9b4406f505b0785b49df4099343df4403ea661674e140709a568d68f3bd6c9`).
