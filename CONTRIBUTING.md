# Contribuindo com o CRM

## Correções de módulos sempre recebem versão e migration

Qualquer correção ou evolução funcional em `modules/<nome>/` deve trazer, na mesma
PR, um incremento de `Version:` no arquivo principal `modules/<nome>/<nome>.php`
e uma **nova migration nativa** correspondente. A regra vale também para interface,
traduções, JS/CSS, integrações, configuração de execução e dependências/SDK usados
pelo módulo. Correção sem alteração de schema continua exigindo versão e migration:
nesse caso, documente um NOOP em `up()`.

Exemplo de correção de `1.0.1` para `1.0.2`:

```php
<?php
// modules/exemplo/migrations/102_version_102.php

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_102 extends App_module_migration
{
    public function up()
    {
        // NOOP: correção de apresentação; sem alteração de schema ou dados.
        // O executor nativo registra a nova versão instalada.
    }
}
```

Não altere migrations já publicadas/aplicadas para corrigir uma instalação antiga.
Acrescente a próxima migration e preserve o upgrade das versões suportadas.
Uma migration pode precisar de idempotência e reexecução segura, mas isso deve ser
testado no módulo; o gate de diff não prova o comportamento SQL.

### Módulo novo

- Arquivo principal: `modules/<nome>/<nome>.php`, com header `Version: 1.0.1`.
- Primeira migration: `migrations/101_version_101.php`, classe
  `Migration_Version_101 extends App_module_migration`, método `public function up()`.
- O `install.php` chama `new App_module_migration('<nome>')` e `->to_latest()`.
  DDL fica nas migrations, não duplicado no instalador.
- Trate falha do executor e confirme o marcador persistido `installed_version`.
  O framework pode pregravar o header antes do hook de ativação e seu atual runner
  não propaga todas as falhas de escrita do marcador. Teste instalação, upgrade,
  reativação/replay e falha de tracking; nunca declare sucesso só pelo retorno.
- Não crie `100_version_100.php`. O baseline anterior à primeira migration é
  histórico, não uma migration artificial.
- Se a PR contiver mais de uma evolução do novo módulo, conserve a sequência
  iniciada em 101 e termine na migration correspondente ao header atual.

### Versões compatíveis com o executor existente

O [executor nativo](application/libraries/App_module_migration.php) remove os pontos
da versão (`str_replace('.', '', ...)`), procura identificadores com **três dígitos**
e grava o marcador com um ponto entre cada dígito (`wordwrap(..., 1, '.', true)`).
Por isso a convenção atual exige um dígito por componente: `X.Y.Z`.

- `1.0.9` → `109`; o próximo passo nativo é `1.1.0` → `110`.
- `1.9.9` → `199`; o próximo passo é `2.0.0` → `200`.
- `1.0.10` e `10.0.0` não cabem no identificador de três dígitos.
- `1.1.10` e `1.11.0` colidem após remover os pontos (`1110`). Não as converta
  silenciosamente nem use SemVer genérico para selecionar o arquivo.

Os novos passos precisam ser contíguos acima da versão base e terminar no header.
Um salto de versão requer um plano compatível com o executor, não uma renumeração
de migrations aplicadas ou uma coleção de NOOPs artificiais para simular SemVer.
Se o módulo exige identificadores maiores que 999 ou tem header ambíguo, pare e revise a evolução
do contrato do framework em mudança separada.

### Compatibilidade com módulos legados

A política compara somente módulos alterados funcionalmente na PR. Não exige
retrofit de módulos intocados, preenchimento de lacunas históricas ou renomeação
de arquivos antigos. Exemplos: o legado API com 101–103 e 200/201 pode receber 202;
um Asaas cujo histórico começa em 110 não precisa ganhar migrations 101–109.
Um módulo histórico 1.0.0 sem migrations pode receber sua primeira 101.

Para módulos existentes, o gate exige a nova sequência **acima do header da base**;
instalação legada intocada não é reescrita incidentalmente. Módulo alterado com
header não canônico, como texto adicional ou componentes ambíguos, falha de forma
explícita e exige revisão do baseline. Não normalize seu histórico por suposição.

Todo arquivo PHP histórico dentro de `migrations/` é imutável em relação à base da
PR, inclusive quando o banco local ainda não o aplicou. Uma migration já existente
com número futuro também não satisfaz a exigência de **novo** arquivo da correção.
Excluir/renomear o módulo não é uma correção comum: o gate bloqueia e pede um plano
de descontinuação revisado, sem apagar dados de instalações existentes.

## Gate de CI por diff

O workflow [Versão e migrations dos módulos](.github/workflows/module-migration-policy.yml)
executa os fixtures e `scripts/check-module-migration-diff.py` em cada PR, inclusive
ao trocar sua branch base. O checkout usa a **árvore composta da PR** (`github.sha`
no evento `pull_request`) e histórico completo. Assim, o verificador e seus testes
também existem para branches abertas antes da adoção do gate. A comparação recebe
separadamente os SHAs completos e originais de base/head do evento: o baseline é
`git merge-base BASE_SHA HEAD_SHA`, e o alvo é o head original, nunca o tip da base
nem o commit de merge sintético gerado pelo GitHub.

Assim, alterações independentes que só chegaram à base não são atribuídas à PR.
Ao rebasear ou retargetar, confira o novo baseline: uma versão já incluída na nova
base deixa de servir como incremento para correções posteriores.

O gate verifica:

1. módulos com arquivos funcionais adicionados, alterados ou excluídos;
2. incremento do header principal e mapeamento não ambíguo para três dígitos;
3. novas migrations contíguas, nome/classe/base nativa e `public up()` coerentes;
4. preservação byte a byte e modo dos PHPs históricos em `migrations/`;
5. em módulos novos, primeira 101 e instalador que chama o executor sem DDL;
6. falha fechada para SHAs/histórico ausentes, links simbólicos e submódulos alterados.

Exceções não funcionais são estreitas:

- documentos reconhecidos na raiz do módulo (`README`, `CHANGELOG`, `LICENSE`,
  `CONTRIBUTING`, `AGENTS`, etc., com extensões textuais apropriadas) e `.md/.rst/.txt`
  dentro de `docs/`;
- source map v3 com bundle local inalterado e `sourcesContent` exatamente igual às
  fontes locais inalteradas. Sem essa prova, `.map` conta como funcional.

Não há exclusão genérica para `vendor`, `SDK`, `dist`, `build`, arquivos minificados
ou arquivos executáveis dentro de `docs`. JS/PHP/HTML, views, traduções e configs
não são mascarados como documentação. Para o header de módulo, uma PR só de documentos ou artefatos
comprovados não exige bump. O incremento do núcleo continua obrigatório para toda release. Outros artefatos ambíguos falham conservadoramente.
Edições apenas de comentários em arquivos de código também são conservadoramente
tratadas como funcionais; mova documentação independente para os caminhos próprios.

Os testes de contrato estrutural `tests/test_module_migration_convention.py`, quando
presentes na composição, continuam executando. Eles validam módulos adotantes de
ponta a ponta; este gate acrescenta a comparação histórica e não substitui aqueles
testes nem exige que PRs independentes importem módulos umas das outras.

### Executar localmente

Use commits reais e completos, com a base atual disponível no clone:

```sh
python3 -m unittest discover -s tests -p 'test_module_migration_diff.py' -v
python3 scripts/check-module-migration-diff.py --base "$BASE_SHA" --head "$HEAD_SHA"
```

Arquivos não commitados não entram na comparação. O teste cria repositórios Git
sintéticos temporários, sem acessar provedores ou dados reais. O verificador apenas
lê blobs; não carrega PHP do módulo, não executa SQL e não migra uma instalação.

## Checklist da PR

- [ ] Header e migration nova correspondem ao delta da base atual.
- [ ] Migrations históricas e caminhos de upgrade preservados.
- [ ] Sem DDL: NOOP documentado; com DDL: rollback, locks e compatibilidade revisados.
- [ ] Instalação limpa, upgrade suportado, replay e falha de tracking testados.
- [ ] Lint e testes funcionais específicos do módulo executados.
- [ ] CI do commit final e baseline conferidos; limites de validação registrados.
- [ ] Nenhum segredo, dado real ou alteração de produção no diff.

O gate é uma verificação estática de convenção, não um parser completo de PHP nem
prova de segurança ou correção financeira. Continue com lint, testes reais do
executor, banco descartável e homologação apropriados à mudança. O arquivo de
workflow não ativa branch protection sozinho; tornar o check obrigatório é uma
decisão de configuração do repositório. A convenção de módulos não substitui a política de release do núcleo abaixo,
não executa migrations automaticamente e não autoriza merge ou implantação.


## Versão canônica do núcleo em toda release

A versão do CRM é derivada do identificador nativo, com um ponto entre cada
algarismo: `393 = 3.9.3`, `399 = 3.9.9`, `400 = 4.0.0`, `401 = 4.0.1`.
Não há incremento separado por SemVer, título `feat/fix` ou rótulos da PR.
O limite atual é 999; excedê-lo exige revisão explícita do executor.

Toda PR destinada a uma release deve conter, no próprio diff:

1. exatamente o próximo ID core, mesmo para mudanças só de módulos, docs ou CI;
2. `VERSION`, `ARGWS_VERSION`, config de migration e imagens de distribuição iguais;
3. um novo arquivo nativo com a classe correspondente e `public up()`; sem schema,
   mantenha o NOOP documentado gerado pela ferramenta oficial;
4. preservação byte a byte e de modo de todas as migrations históricas.

O único gerador é `python3 scripts/apply-release-version.py`. Sem argumento,
prepara o próximo ID; também aceita o alvo explícito para reexecução idempotente.
O pipeline reaplica o alvo já revisado e testa coerência; nunca decide uma nova
versão pelo título e nunca corrige uma PR silenciosamente.

### Transição revisada na PR41

Os nomes antigos publicados são preservados: 3.8.1/382, 3.8.2/383,
3.10.0/391, 3.10.1/392, 3.11.0/393 e 3.11.1/394. A lista de evidências em
`scripts/legacy-release-aliases.json` fixa commit, árvore, objeto de tag e SHA-256
exatos de VERSION, constantes, configuração e migration alvo. Um texto de versão
parecido ou um ref substituído não serve como evidência.

A única exceção de sequência autorizada é **394 → 401 / 4.0.1**, preservando a DDL
395 de recorrências e acrescentando 396–401 NOOP. Não há release intermediária
3.9.5 nem downgrade genérico autorizado. Depois da publicação efetiva: PR42 usa
402 / 4.0.2; PR45 usa 403 / 4.0.3. Outras PRs devem integrar a base publicada e
recalcular seu próximo ID antes de publicar.

O planner ordena tags verificadas pelo ID de migration e ancestralidade, usando
v3.11.1/394 como base incremental de v4.0.1/401. Colisões de ID, tags divergentes,
histórico incompleto e aliases desconhecidos falham. Reexecução só reutiliza a
mesma tag quando aponta para o mesmo commit. GitHub recebe `--latest` explícito;
a tag GHCR versionada e `latest` são criadas do mesmo conjunto de digests.

A sincronização antiga que copiava somente metadados para develop foi retirada:
um teste reproduz a perda de ancestralidade. O canal develop continua existindo;
integre a main por merge/rebase normal para transportar código e histórico juntos.

### Análise e validação

O workflow `core-release-policy.yml` usa ferramentas da árvore composta, mas
compara os SHAs originais base/head do evento e seu merge-base. Se a base atual
já avançou o núcleo depois desse merge-base, exige sincronização e o próximo ID,
mesmo que o merge fosse limpo e os NOOPs fossem idênticos. Preserva também as
migrations da base atual. Todas as PRs são
analisadas, sem filtros de paths. O gate é somente leitura e não cria migrations.

```sh
python3 -m unittest tests/test_release_version.py tests/test_package_release.py
bash scripts/check-release-version.sh --base "$BASE_SHA" --head "$HEAD_SHA"
bash scripts/check-release-version.sh v4.0.1
php tests/native_migration_tracking.php
```

Os testes cobrem aliases adulterados, ancestralidade, colisão, replay, sequência,
399→400, marcador ausente, falha de escrita no tracking real e ZIP completo/overlay.
O empacotador valida os bytes de VERSION/config/classe dentro de ambos os ZIPs,
inclusive a árvore reconstruída do incremental. Esses checks não substituem os
testes funcionais e de banco do recurso. Branch protection requer configuração
separada; este workflow não muda permissões do repositório.
