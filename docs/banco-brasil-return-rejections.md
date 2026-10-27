# BB1.3.3 / migration133 — originais de retornos recusados

## Comportamento

A importação nativa na aba Retorno usa ModuleStore::receiveReturn. Falhas de validação do parser240/400 são tipadas e recebem somente códigos fechados/descrições PT-BR. Mensagens arbitrárias de exceções, SQL, credenciais e conteúdo não são mostrados ou persistidos no diagnóstico.

A nova bb_return_rejections guarda os bytes originais em LONGBLOB privado, SHA-256, tamanho, empresa, conexão, perfil escolhido, motivo e data/ator. Linha física é informada apenas quando a estrutura/tamanho permite determiná-la; não se inventa linha para um erro sem localização. Não é um retorno aceito: não há evento, origem, recibo, crédito, baixa ou alteração de fatura.

O vínculo registra a empresa/conta/perfil explicitamente escolhidos. Cabeçalho de outra conta não seleciona outra empresa nem consulta seus dados. Seu conteúdo já enviado pelo operador permanece privado na empresa solicitante, diagnosticado como incompatível. Isso não comprova origem ou autenticidade bancária.

Reimportações idênticas com mesmo perfil/motivo retornam o mesmo ID sem novo arquivo/auditoria. O arquivo recusado não ocupa a chave de deduplicação dos aceitos: corrigir o perfil e importar o mesmo conteúdo pode produzir uma validação normal, preservando a rejeição anterior. Mudança efetiva de diagnóstico/perfil tem registro próprio.

Lista e download usam a ACL atual. Download mantém o endpoint POST original, com escolha inequívoca entre return_file_id e rejection_id, CSRF/contexto existentes, Content-Disposition attachment/application/octet-stream/nosniff e private/no-store. O histórico continua consultável quando o canal CNAB é desabilitado; habilitação de API/CNAB não muda neste incremento.

A transação própria inclui identidade/ACL/módulo, conexão/perfil, original e auditoria; confirma bytes e escopo após auditoria e reconfirma autorização. O upload nativo válido também usa a autorização transacional já existente para o envelope auxiliar, em vez de somente a autorização inicial do controller. O caminho baixo nível usado pelos fixtures de codecs preserva seu contrato.

Antes de explode, o importador limita a quantidade de CRLF ao máximo físico compatível com16MiB e o tamanho do registro declarado. Um arquivo de linhas vazias não deve consumir memória ilimitada. Limite de arquivo16MiB, limites financeiros, layouts e interpretações de ocorrências não foram ampliados.

## Migração e validação

Version1.3.3, migration133, core mínimo4.7.4. Migração apenas adiciona o novo ledger. Confere metadados completos/índices únicos/engine, recusa transação externa e nunca reconstrói originais nem altera arquivos/eventos/obrigações/recebimentos anteriores. Replay valida sem DDL adicional; schema incompatível não é reparado silenciosamente.

Testes adicionados aos workflows existentes, sem retirar cenários:
- PHP8.1/8.5: lint de todo o módulo, códigos fechados e regressões anteriores.
- MySQL e MariaDB, PHP8.3: novo teste real de upload/importador/ACL/transações; versões131/132 preservadas e novo passo133 com falhaDDL/tracking/replay; driver CI3/mysqli verifica criação e replay do novo ledger.
- Retorno400 recusado sob240 e depois aceito sob400, aceito240, bytes inválidos, reimportação sem duplicação, conta diferente, linha estrutural e limite pré-parser.
- IsolamentoA/B, leitura sem admin global, permissão/import revogada após preparar o upload, ator inativo, corrupção do conteúdo, falha de auditoria e trigger de revogação no último audit com testemunha independente.
- Histórico consultável com CNAB desligado e API habilitada; accepted files/events/origins/invoices/payments/journals imutáveis nas recusas.

Esses casos são código de teste preparado para CI; não são resultados já aprovados. Executor local indisponível, portanto não há lint PHP, MySQL/MariaDB ou navegador local alegados. Revisão disponível: contratos e fluxo completo, hashes individuais e JSONs. Inventários agregados precisam ser reconciliados com os geradores oficiais do novo SHA; readiness continua false.

## Limites preservados

Este incremento não completa a origem/registro/conciliação CNAB400 vinculada às faturas, não resolve ambiguidades contratuais e não constitui homologação de API ou CNAB. Ver docs/banco-brasil-cnab400.md para CBR641 julho2026 e CBR643 julho2023 e dependências precisas. Nenhum campo/código/posição bancária foi inventado ou alterado aqui. Não houve chamada bancária, envio automático de arquivo, SMTP, webhook ou operação financeira real.

Interface única ainda incompleta:48 controllers paralelos/51 views de shell permanecem. PWAa5ba730 e arquivos protegidos intactos. PR52 permanece aberta/em rascunho; sem merge, deploy ou force-push.
