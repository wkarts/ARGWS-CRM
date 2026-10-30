# Biblioteca de e-mails PT-BR em MA 1.1.1

## Resultado e limite

Os sete modelos de biblioteca têm variantes textuais completas em PT-BR: Condicionamento físico; Promoção de Dia dos Namorados; Oferta de Black Friday; Promoção de Black Friday; Agradecimento ao assinante; Feliz aniversário; Gestão de marca. O catálogo localiza 132 dos 139 segmentos de texto HTML distintos e mantém sete nomes/endereço/domínio/termos correntes. Também traduz Image/Video para Imagem/Vídeo, totalizando 134 segmentos traduzidos. As descrições originais são vazias e continuam vazias. Não existe assunto nesses defaults: o assunto é conteúdo de campanha em `ma_emails.subject` e não é reescrito.

**Não é tradução integral dos pixels.** Foram baixados e inspecionados visualmente 40 assets de conteúdo e backgrounds, além do inventário separado de 17 ícones sociais. Imagens ainda contêm texto em inglês. O vídeo externo não foi reproduzido. URLs e arquivos originais permanecem iguais; nenhuma imagem foi gerada ou publicada neste incremento. O [plano de imagens](ptbr-image-plan.json) registra os oito assets com texto a avaliar, incluindo marcas e termos que não devem ser traduzidos automaticamente.

## Inventário e armazenamento

Fonte: `database/email_template_default.sql`, sete tuplas, 602.525 bytes, nomes Fitness, Valentine Day Sale, Black Friday Offer, Black Friday Deal, Thanks Subscriber, Happy Birthday e Branding. O primeiro registro fica na linha do INSERT. Não há mergefields literais nos defaults; identificadores e a capacidade de usar mergefields continuam intactos.

O conteúdo antigo usa uma string JSON em `data_html` (um decode para HTML) e uma string JSON que contém o JSON Unlayer em `data_design` (dois decodes para o objeto). O catálogo preserva exatamente os bytes originais para reconhecimento; target mantém as mesmas camadas de codificação. Somente texto, alt/title e idioma HTML mudam. Layout, CSS, comentários, links, imagens, números, datas, descontos, nomes próprios, IDs e chaves técnicas não mudam.

Desde v104, os designs reais da biblioteca são `ma_email_template_designs`. Ao criar um e-mail, `Ma_model::add_email()` copia seus designs para `ma_email_designs`; essas cópias pertencem às campanhas. Alterar somente o SQL/colunas legadas não corrigiria a biblioteca atual. A biblioteca MA é global legada, sem campo issuer: este incremento não cria compartilhamento, não altera permissões, audiências ou escopo por empresa.

## Atualização conservadora

`MaEmailTemplateDefaults::upgrade()` roda na migration nativa 111. Exige nome original/traduzido conhecido, descrição/idioma/corpos legados exatos, autor e data do seed; exige um único design inglês com HTML **e** JSON exatamente de fábrica. Customizações, clones, strings vazias, reserializações diferentes ou ausência do design inglês são preservados. Duplicidade EN/PTBR em um candidato elegível falha fechada e reverte a transação.

- Acrescenta PT-BR ausente ao mesmo ID de biblioteca; não cria outros sete itens na lista.
- Corrige PT-BR contendo o par original inglês somente por match exato dos dois campos.
- Não modifica inglês nem outros idiomas; PT-BR customizado permanece byte a byte.
- Traduz o nome conhecido apenas quando a variante PT-BR é segura.
- Mantém published, cor, categoria, IDs, autor, data e campos legados; um default despublicado continua despublicado.
- Não recria pais/defaults ausentes no upgrade. Não escreve `ma_emails`, `ma_email_designs`, campanhas, logs ou vínculos.
- Reativação com marcador de seed já presente não reconcilia novamente; uma variante apagada depois pelo usuário não reaparece por reativar o módulo.

Na instalação nova, o mesmo catálogo é usado com `db_prefix()`, após ambas as tabelas existirem. Somente uma biblioteca totalmente vazia recebe sete pais e quatorze designs EN/PTBR. Biblioteca ocupada não é repovoada; designs órfãos falham fechados. O marcador de seed é gravado após sucesso. A cache de tabelas CI3 é invalidada para reconhecer DDL recém-executada. Os campos legados permanecem ingleses com metadata english, compatíveis com o conversor histórico, sem falsificar o idioma.

As escritas são transacionais e usam locks; em instalações com o núcleo de empresas, usam o lock global existente. Sem ele, exigem conexão independente e InnoDB. O helper nunca confirma uma transação do chamador. Falha de escrita reverte o lote; falha no tracking nativo mantém a versão anterior, e o replay não duplica conteúdo.

## Interface

Os dois editores e a prévia usam os rótulos compartilhados do núcleo para grupos, mergefields e idiomas. As chaves técnicas continuam iguais. A aba inicialmente ativa corresponde ao idioma configurado da empresa quando disponível, com fallback visual para a primeira variante existente; não há reorder ou escrita de dados.

A interface Unlayer usa `locale: 'pt-BR'`, sem mudar o idioma do conteúdo editado. Contrato conferido na [documentação oficial de localização](https://docs.unlayer.com/builder/1.468.0/localization). O locale PT-BR é oferecido pelo editor; sua renderização efetiva ainda precisa de homologação em browser.

## Validação e reprodução

- `php tests/ma_email_template_localization.php`: 93 checks locais SQLite; helper real, runner `App_module_migration` real e métodos reais do model. Instalação vazia com prefixo aleatório/cache obsoleta, estado despublicado, customizações, EN/PTBR/FR, novos e-mails sem SMTP, snapshots de campanhas intactos, versão/replay/falha/rollback e ramo de lock do núcleo.
- `python3 scripts/build-ma-email-templates-ptbr.py --check`: parser estático do SQL, sem executá-lo; sete origens/metadados exatos; cobertura textual; estrutura HTML/Unlayer; URLs, números e mergefields invariantes. `--write` regenera localmente após revisão do mapa.
- `node --test tests/ma_email_interface.test.cjs`: contratos de interface, preview, locale, instalação e catálogo.
- CI usa MySQL descartável com a mesma suíte PHP. SQLite não comprova InnoDB/concorrência. O `install.php` legado inteiro não foi executado localmente; o novo helper e seu contrato de cache/seed foram testados.
- Chromium local está bloqueado. Não houve homologação visual da página, teste de clientes de e-mail, envio real, SMTP, token ou produção.

## Plano dos assets, separado

`ptbr-image-plan.json` contém URL original verificada, hash SHA-256, tamanho, texto observado, proposta e caminhos do design. A referência no seed comprova proveniência; nenhuma licença específica nem restrição explícita dos assets foi localizada no repositório. A tradução solicitada dos assets existentes foi autorizada pelo responsável, mantendo avisos, créditos e marcas-d'água. Uma restrição explícita eventualmente encontrada deve ser reportada antes de prosseguir.

No incremento separado autorizado, usar edição por imagegen mantendo transparência, proporções, fotos, cores e design. Preservar marcas Google/Black Friday e deixar Brand Specialist sem alteração até confirmar se é marca ou placeholder. A implementação futura deve ter versão/migration própria, assets locais versionados e troca de URL limitada à variante PT-BR ainda exatamente de fábrica, em HTML e JSON. Não alterar originais/EN/campanhas nem inferir domínio público: resolver a URL pública pela configuração da instalação. Validar hash/dimensões/transparência e inspeção visual antes de integrar. O lote textual 111 não depende dessa geração.

## Pull Request

- Branch: fix/email-modelos-ptbr; incremento MA separado.
- Título: fix(ma): adiciona biblioteca textual PT-BR preservando campanhas.
- Contexto/objetivo: biblioteca de sete defaults continuava em inglês, fora do catálogo transacional do núcleo.
- Escopo/arquitetura: catálogo source/target imutável, serviço transacional, migration111, seed prefix-safe e apresentação; sem transporte ou mudança de público.
- Arquivos: resources/email_templates_ptbr.json, services/MaEmailTemplateDefaults.php, migrations/111_version_111.php, install.php, ma.php, helper, controller/duas views de detalhe/duas views e scripts de editor, gerador, testes, CI e documentação; nenhum arquivo histórico removido.
- Banco/APIs/contratos: nenhuma DDL nova; apenas variante conhecida na biblioteca; mesmas chaves, IDs e camadas JSON; campanhas existentes invariáveis.
- Dependências/configuração: nenhuma biblioteca/runtime nova; Python é apenas ferramenta de geração/CI. Locale Unlayer já suportado.
- Segurança/privacidade: bindings, comparação byte a byte, locks, estado preservado, sem dados de produção, credenciais ou envio.
- Desempenho/observabilidade: catálogo de aproximadamente 1,2 MB lido uma vez por processo de migration; library scan com locks durante o upgrade; não roda no cron/envio.
- Testes executados: suites acima, regressões core/Purchase, lint e diff/gate no commit final. Recomendados: MySQL integrado, instalação completa, concorrência e browser/clientes de e-mail.
- Impacto/risco/breaking: título conhecido muda na biblioteca e relatórios que consultam esse pai; corpo de campanha/assunto não muda. Defaults alterados pelo usuário podem permanecer no idioma escolhido por ele.
- Deploy: MA1.1.1 pelo runner nativo e próximo core contíguo coordenado na PR48; sem merge/deploy automático.
- Rollback: reverter código se necessário; não excluir variantes que possam ter sido editadas após atualização, nem remover migration aplicada. Reversão de conteúdo requer análise exata de origem/uso.
- Build/release/distribuição: sem binário separado; imagens ainda remotas e inalteradas.
- Documentação: este arquivo, auditoria core e plano de assets.
- Checklist: [x] sete defaults; [x] custom/campanhas/estado preservados; [x] testes locais; [x] migration nova; [ ] CI MySQL final; [ ] instalação integral/browser; [ ] revisão visual/geração de assets.
- Commit sugerido: fix(ma): localize factory library without changing campaigns.
- Merge sugerido: incremento da PR48 após checks/autorização; núcleo definido pelo integrador, sem renumerar releases publicadas.
