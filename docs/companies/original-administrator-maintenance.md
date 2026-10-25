# Administrador original em instalação legada

Novas instalações continuam vinculando o ID real retornado pelo instalador. O
upgrade **não** infere o primeiro usuário pelo menor ID, data, único admin ou
primeiro login; tampouco concede poder geral a todos os administradores.

O assistente HTTP de código temporário/senha foi retirado. Sua URL antiga exibe
somente informação, sem formulário; POST não altera responsabilidade. A grade
informa uma permissão ainda ausente, sem ocultar a pendência. Depois do vínculo
confirmado, a ACL normal libera Nova empresa e administração geral ao original.

## Manutenção excepcional por operador autorizado

Não é uma etapa de uso normal do produto, nem uma tarefa de console exigida do
usuário. O operador precisa de **autorização externa explícita para esta
instalação e este usuário**, com referência auditável à confirmação do dono.
Uma referência não é senha, token nem mecanismo que verifica a autorização.
A posse do processo CLI é privilegiada; não publicar este comando em endpoints,
crons, auto-upgrade ou formulários. Não registrar credenciais na referência.

A partir da raiz da instalação correta (números abaixo são exemplos sintéticos):

```sh
# Leitura somente; não descobre ou escolhe o usuário histórico.
php index.php argws_provisioning original_administrator 17 inspect
# Somente após revisar a saída e obter autorização para a alteração nesta instalação.
# 3 é o primary_issuer_id retornado pela inspeção, não um valor fixo do produto.
php index.php argws_provisioning original_administrator 17 apply 3 ticket:owner-confirmation-example
```

A inspeção consulta apenas identidade e estado ativo/admin do usuário exato.
Antes de aplicar, confirmar ambiente/alvo, ID, empresa principal e autorização;
ter backup e procedimento operacional de recuperação. O comando não aceita nome,
senha, código temporário, seleção automática ou troca de responsável existente.

Aplicação: transação própria InnoDB; lock global de emitentes, identidade e staff;
revalidação do candidato ativo/admin e empresa principal esperada; preenchimento
somente de master NULL; `source=owner_attestation`; auditoria
`original_admin_attested`, ator técnico 0 e sujeito explícito, com referência da
confirmação. Falha de auditoria ou commit não confirmado impede sucesso. Mantém
empresa principal, snapshot legado, memberships e dados financeiros.

Repetir o mesmo vínculo é idempotente e não muda a origem anterior. Outro sujeito
é recusado inclusive se o original estiver inativo/excluído. Conflito exige
investigação autorizada, nunca sucessão automática. Não há comando automático de
rollback/desvinculação: correção de identidade equivocada é incidente separado.
O HTTP recusa o endpoint de manutenção. Códigos antigos não possuem fluxo HTTP
para conclusão; seu histórico de schema/auditoria permanece preservado.

## Validação

- `php tests/issuers_original_administrator.php`: dry run, ID arbitrário, candidato
  ausente/inativo/não admin, referência obrigatória, contexto alterado, rollback
  de auditoria, replay, origem installer preservada e ACL de outros admins.
- `php tests/issuers_onboarding_concurrency.php`: MySQL/MariaDB isolado obrigatório,
  dois escritores observados no lock, revogação durante espera, replay e criação
  de empresa concorrente; não substituir por SQLite como prova de concorrência.
- `tests/companies_onboarding_browser.cjs`: fixture descartável real, ausência de
  código/senha, endpoint HTTP negado, manutenção explícita do usuário sintético17,
  replay e botão Nova empresa; depois segue onboarding normal e isolamento.

Nenhuma execução destes comandos em produção está implícita na publicação desta
correção. A identidade informada pelo dono deve ser aplicada separadamente, sob
controle operacional, e verificada antes de declarar o bloqueio resolvido.
