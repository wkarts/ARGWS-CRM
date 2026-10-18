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
