# Orientações de contribuição no CRM

Leia [CONTRIBUTING.md](CONTRIBUTING.md) antes de alterar módulos ou preparar uma PR.

- Toda correção funcional em `modules/<nome>/` incrementa `Version:` no arquivo
  principal `<nome>.php` e adiciona uma **nova** migration nativa correspondente.
  Sem alteração de banco, use uma migration NOOP documentada.
- Módulos novos começam em `1.0.1`, com `101_version_101.php`, classe
  `Migration_Version_101 extends App_module_migration` e método `public up()`.
  `install.php` chama o executor nativo, sem possuir DDL.
- Nunca edite, renomeie ou exclua migrations já presentes na base da PR.
  Preserve o caminho de upgrade. Não invente migration `100` para completar legado.
- JS, CSS, views, traduções, SDK e dependências de execução também são mudanças
  funcionais. Nomes como `docs`, `vendor`, `dist` ou `build` não são exceções gerais.
- Confira a versão efetiva do framework, o diff contra o merge-base da base atual,
  os testes de instalação/upgrade e o CI do commit final. Não confunda esse gate
  estático com prova de instalação, tracking no banco ou homologação financeira.
- Não altere versão global, dados, credenciais ou ambiente de produção apenas
  para satisfazer a convenção de um módulo. Merge e implantação exigem autorização.

## Núcleo e publicação

- Toda PR que será distribuída, inclusive somente módulos, docs ou CI, avança
  também o núcleo exatamente um ID nativo. Use `scripts/apply-release-version.py`;
  `VERSION`, `ARGWS_VERSION`, config, migration e imagens devem concordar.
- O produto usa a versão nativa: 399 = 3.9.9, 400 = 4.0.0, 401 = 4.0.1.
  Títulos/labels e SemVer genérico não escolhem o próximo número.
- Única transição histórica autorizada: alias publicado 3.11.1/394 → 4.0.1/401,
  com 395–401 completas; 395 mantém a DDL de recorrências, 396–401 são NOOPs.
  Depois: PR42 usa 402/4.0.2, PR45 usa 403/4.0.3; rebaseie na base efetiva.
- O gate do núcleo é somente leitura, compara o head original ao merge-base e
  reprova ausência de incremento, inconsistências e alteração do histórico.
- Preserve tags antigas. Aliases só são aceitos com prova exata de commit, árvore,
  objeto da tag e hashes de metadados; não autorizam downgrade genérico.
- Integre main normalmente antes de preparar a próxima release; não copie apenas
  versão/migration para develop. Isso não autoriza merge ou implantação.

## Ciclo beta 4.0.3 — infraestrutura

Por decisão explícita, PRs de infraestrutura com base `develop-beta` podem manter
4.0.3 durante este ciclo. O gate exige versão/metadados/distribuição e migrations
inalterados, arquivos regulares e delta limitado à allowlist `BETA_INFRA_PATHS` em
`scripts/release_policy.py`. Não é exceção genérica por extensão ou pasta: arquivos
de produto, módulos, schemas, assets, mudanças mistas, exclusões, symlinks, outra
base ou outra versão continuam sujeitos ao incremento nativo normal. A CLI sem
`--base-ref develop-beta` e o planner de release permanecem estritos. Esta regra
não autoriza publicar nem habilita o canal estável; ao encerrar o ciclo, revisar
a regra e a versão com o responsável. GHCR :4.0.3 é mutável neste ciclo beta, com
digest/SHA rastreados; :latest e :develop ficam preservadas.

### Complemento V2 autorizado no beta

A conclusão da identidade V2 mantém 4.0.3 somente para a restauração exata de
`install/logo.png` da 4.8.7. `BETA_V2_RESTORATIONS` fixa SHA-256 dos bytes antigos
e aprovados, exige arquivo regular modo 100644 na base, ancestral e head, e não
permite outros assets, código de produto, exclusões ou mudanças de modo. A regra
vale somente para develop-beta/4.0.3; metadados e migrations ficam congelados.
Não autoriza merge, release, publicação ou alteração de tags estáveis.
