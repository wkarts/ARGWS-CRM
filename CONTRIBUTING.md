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
ao trocar sua branch base. Ele usa os SHAs completos do evento, checkout do **head
da PR** e histórico completo. O baseline é `git merge-base BASE_SHA HEAD_SHA`, não
o tip da base nem o commit de merge sintético gerado pelo GitHub.

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
não são mascarados como documentação. Uma PR só de documentos ou artefatos
comprovados não exige bump. Outros artefatos ambíguos falham conservadoramente.
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
decisão de configuração do repositório. Esta convenção não muda a versão global do
CRM, não executa migrations automaticamente e não autoriza merge ou implantação.
