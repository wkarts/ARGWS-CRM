# Versionamento dos módulos consumidores de emitentes

## Escopo e versões

Adequação local da fase 2 de isolamento, baseada em `97eeb24a2aa14eb5b3552578be41a7343d143e72`:

- API: `2.0.1` → `2.0.2`, nova `202_version_202.php`.
- CSV Export Manager: `1.0.0` → `1.0.1`, nova `101_version_101.php`.
- e-Invoice: `1.0.0` → `1.0.1`, nova `101_version_101.php`.

Todas as novas migrations são NOOPs de schema/dados: registram a evolução de código
que aplica escopo de emitente. Não alteram credenciais, dados fiscais, opções de
integração ou serviços externos. O único tracking é o nativo de `modules`.

A regra de contribuição foi consultada no clone local da base atual. Não são
introduzidas migrations `100`, lacunas artificiais `104–199`, mudança de versão
global, mudanças nas guardas core ou alterações adicionais em `API_model`.

## Instalação e reativação

`App_modules::activate()` pregrava o header no primeiro registro, antes do hook.
Confiar nesse valor faria o runner pular a migration nova. Por isso os executores
locais:

1. exigem registro existente e versão de origem/destino suportada;
2. validam que o header corresponde exatamente à release deste executor;
3. rebobinam somente o novo NOOP para o baseline anterior;
4. confirmam por leitura que o baseline foi realmente persistido;
5. executam `new App_module_migration('<módulo>')->to_latest()`;
6. rejeitam erro do runner e confirmam novamente o marcador final persistido.

Há verificação tanto do retorno `false` da gravação quanto de uma gravação que
retorna `true` sem mudar a linha. A ativação falha antes de `active=1` e antes de
`module_activated` quando a confirmação não ocorre. O retry é seguro para os
NOOPs novos. Os executores estão intencionalmente limitados a estas releases:
novas migrations futuras devem rever esse limite em vez de receber replay por
suposição. Módulos já ativos mantêm o comportamento original do core, que retorna
cedo; atualizar um módulo ativo usa o caminho de upgrade, com o limite abaixo.

### API: fronteira com o legado

`modules/api/install.php` permanece byte a byte igual à base, assim como
`101`, `102`, `103`, `200` e `201`. O hook continua executando esse instalador
legado com `require_once` e passa depois por `install_migrations.php`.

O finalizador confirma `user_api`, `permission_enable` e
`user_api_permissions`, estabelece o baseline `2.0.1` e executa somente `202`.
Não reexecuta `201`, cujo `add_column` histórico não é idempotente; não tenta
percorrer o salto `103→200`. A prova de schema é restrita à fronteira existente,
não uma auditoria completa das tabelas legadas. Eventuais outros defeitos do
instalador legado não foram reescritos nesta mudança.

Upgrade suportado nesta entrega: `2.0.1→2.0.2`. Instalações anteriores precisam
de um plano separado para o histórico; este finalizador não declara suporte
para migrá-las. Instalação nova continua usando o DDL legado existente antes da
nova conclusão nativa.

### Exports e e-Invoice

Exports ganha somente um hook de ativação para executar seu primeiro NOOP. Seu
menu continua no mesmo hook `admin_init`. e-Invoice conserva as cinco chamadas
`add_option` existentes e acrescenta o executor ao final do mesmo hook. Opções
personalizadas não são sobrescritas; menus, seção de configurações, capacidades
e hooks de envio continuam nos mesmos lugares. A sincronização local de modelos
de e-mail realizada pelo core após a ativação não é modificada.

## Confirmação do upgrade genérico na composição

O worker de versionamento reproduziu o defeito da base: o runner nativo ignora
falhas de escrita de `installed_version`, e o core anunciava sucesso mesmo com
marcador antigo. A composição corrige `App_modules::upgrade_database()` para
ler o marcador persistido após o runner, exigir igualdade com o header e somente
então atualizar o catálogo em memória e retornar sucesso. Módulo ausente ou não
instalado também é recusado. A rota essencial mantém seu serviço próprio.

`tests/module_upgrade_tracking.php` injeta escrita que retorna `false` e escrita
que retorna `true` sem persistir. Ambos devem retornar erro, não `true`, em API,
exports e e-Invoice. A opção `--expect-current-defect` documenta a reprodução na
base anterior e **não deve ser usada no CI da composição corrigida**.

## Testes implementados e executados

Executado em PHP WASM **8.5.10** e **8.1.34**:

- `tests/module_scope_migrations.php api`: 46 verificações por runtime.
- `tests/module_scope_migrations.php exports`: 42 verificações por runtime.
- `tests/module_scope_migrations.php einvoice`: 45 verificações por runtime.
- Total: 133 verificações de ciclo de vida por runtime, 266 nas duas versões.
- Repro do defeito core: seis casos por runtime, `false`/ignorada em cada módulo.
- Convenção estrutural preexistente: 9 testes Python aprovados.

Os testes carregam os `App_modules` e `App_module_migration` reais, hooks e
migrations reais copiados sem transformação para uma árvore temporária. Os
adapters explícitos são query builder/serviços periféricos, hooks de framework e
autoload de SDK. Usam exclusivamente `sqlite::memory:` e dados sintéticos.
O DDL fresco da API é adaptado somente para o `AUTO_INCREMENT` de SQLite. Não
usam variáveis de conexão, banco externo, tokens reais ou integrações remotas.

Cobrem prewrite real do header, instalação, reativação, upgrade feliz, replay,
falso retorno/gravacão ignorada do baseline e do alvo, falha nativa por migration
ausente, retry, versão inválida, falha de insert e preservação dos registros
locais. API possui uma armadilha de teste que falha caso seu DBForge histórico
seja invocado. Os menus e defaults do e-Invoice são exercitados nos hooks reais.

```sh
# PHP local compatível (cada módulo em processo separado: classes nativas globais).
for module in api exports einvoice; do
  php tests/module_scope_migrations.php "$module"
  php tests/module_upgrade_tracking.php "$module"
done

# Depois da correção do postflight core: exigir rejeição da falha, sem a flag.
for module in api exports einvoice; do
  php tests/module_upgrade_tracking.php "$module"
done

python3 -m unittest discover -s tests -p test_module_migration_convention.py -v
```

Também foram executados lint dos PHPs alterados/adicionados, `git diff --check`,
o gate de diff de módulos da base atual contra o commit local e comparação de
conteúdo/modo dos históricos e do instalador legado API. No CLI WASM disponível,
um `Fatal error` pode ainda produzir exit code zero; a validação conferiu as
mensagens explícitas de aprovação/erro, além do retorno do processo.

## Limites e validação recomendada

- Não foi executado MySQL/MariaDB real; SQLite não prova SQL mode, metadados,
  locks, DDL MySQL ou persistência do driver de produção.
- Não foi instalado Composer nem executado o SDK de e-Invoice/API. O vendor de
  e-Invoice está ausente no checkout, conforme seu contrato de dependências.
- Não há navegação do painel, envio de e-mail, exportação de documentos, provedor
  fiscal, rede, dados reais, homologação financeira, implantação ou CI remoto.
- A sincronização core permaneceu no mesmo caminho e sem erro no fixture; os
  catálogos atuais não possuem modelos destes três módulos. O teste não alega
  cobertura geral de tradução de e-mails de outros módulos.
- Preservação de históricos foi comprovada contra a base local especificada.
  A composição final deve repetir o gate de diff contra seu merge-base atual e
  executar o teste de regressão core sem `--expect-current-defect`.
- Recomenda-se banco MySQL descartável e smoke do painel antes de implantação.

## Pull Request (preparada, não publicada)

**Branch:** `fix/issuer-module-migrations`  
**Título:** `fix(modules): version issuer-scoped API and export changes`

**Descrição / contexto / objetivo:** adequar API, exports e e-Invoice à convenção
de versões/migrations da composição, preservando seus caminhos históricos e
recusando falso sucesso de tracking na ativação.

**Escopo incluído:** três headers, hooks de ativação, três executores locais,
três migrations NOOP, harness/regressões e esta documentação.  
**Fora de escopo:** core, guardas de emitentes, `API_model`, DDL legado, SDKs,
ambientes reais, publicação, merge e deploy.

**Decisões técnicas:** executor nativo; confirmação pré/pós-runner; fronteira
API no baseline `2.0.1`; sem preencher lacunas, sem renumerar migrations.

**Arquivos:** modificados os três arquivos principais dos módulos; adicionados
`api/install_migrations.php`, `exports/install.php`, `einvoice/install.php`,
as migrations `api/202`, `exports/101`, `einvoice/101`, três PHPs de teste e
`docs/issuer-module-versioning.md`. Nenhum arquivo removido.

**Banco de dados:** somente tracking de módulos pelas novas migrations NOOP;
o instalador API legado e opções de ativação de e-Invoice permanecem existentes.

**APIs, contratos e integrações:** sem novo endpoint, payload ou chamada externa.

**Dependências, configurações e ambiente:** sem novas dependências/variáveis.

**Segurança e privacidade:** dados sintéticos e SQLite volátil; nenhum segredo.

**Desempenho e observabilidade:** poucas leituras de tracking no hook; erros
explícitos por módulo e fase. Sem carga no fluxo normal de consultas.

**Testes implementados/executados/recomendados:** descritos acima, separando
aprovação local e defeito core reproduzido. A regressão core padrão passa na composição com postflight confirmado.

**Validação manual:** em ambiente descartável, ativar pela interface e confirmar
`installed_version`; desativar/reativar e verificar opções/menu preservados;
após o postflight core, impedir escrita de tracking e exigir erro de upgrade.
Não executada em painel real nesta tarefa.

**Impacto, compatibilidade, risco e breaking changes:** nenhuma mudança de API
pública. A ativação passa a recusar tracking não confirmado e versões fora da
faixa suportada. O postflight core da composição rejeita falso sucesso de tracking.

**Deploy:** não autorizado/não realizado. Integrar a correção core e executar os
gates no commit composto antes de decidir implantação; backup conforme rotina
operacional e dependências Composer conforme procedimento já existente.

**Rollback:** reverter código sem excluir tabelas/dados. Não rebaixar marcadores
manualmente nem executar downgrades destrutivos; preservar a evidência de versão
e preparar correção forward se for necessário voltar a operar.

**Build, release e distribuição:** não se aplicam novos artefatos; commit e patch
somente locais. Sem tag, release, push ou PR remota.

**Documentação:** este relatório registra versões, fronteiras, comandos e limites.

**Checklist:**
- [x] Headers/migrations correspondentes e históricos preservados.
- [x] Hooks e efeitos locais anteriores preservados.
- [x] Fresh, happy-path upgrade, replay e falhas de tracking testados.
- [x] Sem modificação core ou dados reais.
- [ ] Postflight core e regressão sem flag na composição final.
- [ ] Homologação MySQL/painel e CI remoto no commit final.

**Commit sugerido:** `fix(modules): version issuer-scoped API and export changes`  
**Merge sugerido:** squash da composição somente após revisão e autorização.  
**Versões:** API `2.0.2`, exports/e-Invoice `1.0.1`; versão global inalterada.
