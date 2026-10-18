# Pull Request

## Branch

`feat/issuer-client-profiles` (branch local de trabalho; componente para a branch composta de evolução cadastral).

## Título

`feat(issuers): adiciona perfis canônicos com ACL e criação bloqueada`

## Descrição

Adiciona serviço e GUI nativa de perfil cadastral por emitente. Consulta usa projeção explícita; edição é permitida somente ao owner com `manage_clients`; compartilhamento mantém o mesmo cliente e exige autorização na origem e em cada destino. O caminho positivo de criação privada é testado internamente, mas a factory produtiva continua fechada sem toggle HTTP/ENV/configuração.

## Contexto e objetivo

O módulo possuía vínculos, sem caminho próprio de perfil. Reaproveitar o writer/tela de cliente legado carregaria hooks, contatos e outros campos globais. Esta alteração entrega uma fronteira pequena com escrita canônica direta, sem declarar isolamento de todo o CRM.

## Escopo incluído e fora do escopo

Inclui nome, CPF/CNPJ, endereço/cidade/estado/CEP; consulta, edição, criação interna privada, compartilhamento, transações, validação e testes. Exclui contatos/login/portal, anexos, cobrança, documentos financeiros, adoção de legado, dados reais e liberação de rollout.

## Decisões técnicas e arquitetura

- `IssuerClientProfiles` possui a transação inteira e não aceita nesting. `issuers_lock.id=1` ordena o trabalho com as mutações existentes.
- ACL faz leitura bloqueante de staff/membership/emitente; cliente e permissões são revalidados antes da persistência.
- Criação inicial tem owner explícito e somente um vínculo. `shareClient` do Registry delega ao serviço para manter um writer transacional.
- Duplicidade core/registro é rejeitada antes da primeira escrita, com mensagem genérica sem revelar cliente oculto.
- A factory real tem prontidão fixa `false`. Somente os testes injetam prontidão interna positiva.

## Alterações e arquivos

Modificados: controller `Issuers.php`, model `Issuers_model.php`, delegação em `IssuerRegistry.php`, view `manage.php`, traduções PT-BR/English.

Adicionados: serviço `IssuerClientProfiles.php`, view `client.php`, quatro suítes e um suporte compartilhado `issuer_client_profile*` e `issuer_client_profiles*`, documentação de comportamento e esta descrição de PR. Nenhum arquivo removido. Nenhum core, schema, migration histórica ou teste existente alterado.

## Banco de dados

Sem DDL ou cópia de dados. Escritas utilizam tabelas core/issuer existentes e só campos do perfil. MySQL exige InnoDB em todos os participantes. A checagem de duplicidade varre CPF/CNPJ core sob trava, com custo a medir em homologação. Owner e vínculo são persistidos no mesmo commit do cadastro.

## APIs, contratos e integrações

Novas rotas administrativas nativas: `issuers/client/{issuer}[/{client}]` para renderização e `issuers/save_client` para POST com CSRF próprio. Serviço não expõe nova API pública. A assinatura de `IssuerRegistry::shareClient` foi preservada. Gate financeiro continua fechado.

## Dependências, configurações e variáveis de ambiente

Nenhuma nova dependência de execução ou parâmetro funcional. Permissões e perfis estão na GUI nativa. Variáveis `ISSUERS_TEST_*` são exclusivamente infraestrutura opcional de testes em banco descartável.

## Segurança e privacidade

Administradores não ganham membership implícita. Destinatário compartilhado não pode editar o perfil canônico. Projeção não inclui novas colunas automaticamente; payload rejeita campos fora da whitelist; views escapam conteúdo. Logs/auditoria não registram os valores do perfil. Não há chamadas ao writer legado ou hooks globais.

O gate de criação permanece bloqueado devido aos leitores globais restantes. Esta PR não garante privacidade end-to-end nem libera criação/adoção privada ou financeiro em produção. Consulte o checklist concreto em `issuer-client-profiles.md`.

## Desempenho e observabilidade

Trava global do módulo prioriza consistência; duplicidade lê os campos mínimos dos clientes e pode bloquear ranges em MySQL. Eventos de auditoria usam ator, emitente, ID e tipo de operação. Métricas/benchmark de base grande ainda não executados.

## Testes implementados

- 74 checks de serviço/perfil/ACL/identidade/projeção.
- 60 checks de transação, rollback, nesting, prontidão, falhas e probes MySQL simulados.
- 31 checks de controller/model/views reais com HTTP controlado, CSRF, escaping, gate e GUI owner/shared.
- Concorrência MySQL com dois processos para criação duplicada e revogação de ACL, implementada mas não executada localmente.

## Testes executados

PHP 8.1 e 8.5 WASM/SQLite: as três suites novas passaram, assim como `issuers_registry.php` (138) e `issuers_controller.php` (29) sem modificá-las. Total de 332 checks por versão nessas cinco suites; os testes transacionais não provam locking real MySQL. Lint dos 13 PHP alterados/novos e `git diff --check` passaram. Logs finais foram conferidos, sem confiar só no exit code WASM.

## Testes recomendados

PHP nativo 8.1/8.5, MySQL/MariaDB descartável, teste de concorrência, stack final composto e sessão/browser reais. CI remoto deste commit não foi executado nesta tarefa.

## Validação manual

1. Abrir Emitentes com owner/manage e consultar perfil vinculado; editar nome/endereço.
2. Compartilhar com destino autorizado; confirmar o mesmo ID e perfil somente leitura nesse destino.
3. Revogar vínculo/ACL e confirmar perda imediata de leitura/edição.
4. Abrir “Novo cliente privado”; confirmar aviso sem botão de envio em produção.
5. Tentar POST forjado, ID legado, campo não permitido e documento duplicado; nenhuma escrita parcial ou identidade oculta deve aparecer.
6. Repetir envio, voltar/cancelar e atualizar página com sessão real.

Roteiro manual browser ainda não executado; há renderização real automatizada das views.

## Impactos, compatibilidade, riscos e breaking changes

Compartilhamento agora exige transação independente e InnoDB, falhando fechado onde wrappers/engines antigos não ofereçam essa garantia. Isso é uma mudança intencional de pré-condição. Assinatura e política de ACL de compartilhamento/revogação foram preservadas nos testes existentes. Perfis compartilhados têm um único valor canônico; a GUI informa esse efeito. Nenhuma compatibilidade geral/end-to-end é alegada.

## Deploy e versão

Não implantar isoladamente. Compor na evolução cadastral coordenada com a nova versão/migration nativa do módulo; este componente não altera header/migration por divisão de responsabilidade. Preservar migrations publicadas. Manter ambos os gates fechados até conclusão do checklist e revisão. Merge/deploy não autorizados nem executados.

## Rollback

Reverter somente o componente de código após revisão da composição. Não apagar clientes/vínculos nem remover as barreiras de privacidade de registros já privados. Dados reais exigiriam plano consistente e aprovação; nenhum rollback foi executado.

## Build, release e distribuição

Não se aplica a esta alteração local PHP sem novo artefato de build. Sem push, tag, release ou PR remota.

## Documentação

`docs/issuer-client-profiles.md` contém contrato, limite de rollout, checklist de leitores/fluxos restantes e comandos reprodutíveis.

## Checklist

- [x] Criação produtiva bloqueada sem contorno configurável.
- [x] Consulta explícita e edição owner-only com membership viva.
- [x] Compartilhamento preserva client_id e revogação de destino inacessível.
- [x] CPF/CNPJ alfanumérico e conflitos ocultos antes da escrita.
- [x] Rollback/nesting/CSRF e UI cobertos por fixtures sintéticos.
- [x] Árvores/core/testes existentes do parent preservados.
- [ ] MySQL real, navegador/sessão e CI do commit composto.
- [ ] Versionamento/migration do módulo incorporados pela composição.
- [ ] Checklist de rollout concluído; não habilitar criação agora.

## Commit sugerido

`feat(issuers): add guarded canonical customer profiles`

## Merge sugerido

Somente após revisão, composição de versão/migration e CI; não executar nesta tarefa.

## Versão sugerida

Usar o incremento nativo coordenado na branch composta; nenhuma nova versão isolada é criada por este componente.
