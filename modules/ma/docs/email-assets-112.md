# Imagens PT-BR da biblioteca MA 1.1.2

## Resultado e limites

Sete imagens com texto recebem variantes locais, usadas em seis dos sete modelos
da biblioteca: Fitness/Logotipo; Para você; Logotipo da empresa; Superpromoção;
Google/Sua opinião importa; Seu logotipo; Análise. O outro modelo contém a marca
Black Friday, preservada. Brand Specialist também permanece como marca, assim como
os ícones sociais. O vídeo externo não foi reproduzido nem traduzido. Conteúdo de
campanhas, personalizações e idiomas explícitos existentes não é reescrito.

Quatro placeholders genéricos foram desenhados originalmente em SVG e renderizados
em PNG transparente, com as dimensões exatas dos slots anteriores. Não são cópias
pixel a pixel dos logotipos genéricos antigos. Valentine, Analysis e Google foram
localizados com imagegen. O primeiro Google gerado foi rejeitado por diferenças de
layout; somente o refinamento aprovado é distribuído. Nenhum raster gerado foi
retocado, recortado, recomposto ou redimensionado por script.

O [manifesto](ptbr-assets-112.json) registra URL e SHA-256 da origem, dimensões,
texto, método, hash e tamanho do PNG final, fonte SVG quando aplicável e os caminhos
do JSON Unlayer. O [plano anterior](ptbr-image-plan.json), catálogo textual 111,
SQL histórico e migration 111 permanecem byte a byte. A referência original comprova
proveniência; não se presume domínio público. Não foram localizadas restrições
explícitas nem créditos adicionais nos arquivos inspecionados; o caption IPTC
encontrado em Valentine foi conservado no manifesto. Nenhuma marca-d'água ou aviso
visível foi removido. Inglês continua usando os assets originais.

## Contrato de atualização

`MaEmailTemplateAssets::upgrade()` é chamado apenas pela nova migration 112 e pelo
seed inicial, depois do catálogo 111 e antes de marcar o seed concluído. Usa
`base_url()` da instalação, incluindo eventual subdiretório, para construir URLs
absolutas públicas. Rejeita credenciais, query, fragmento, traversal, caracteres
inseguros e arquivos ausentes/symlinks. Não realiza downloads em produção.

- Exige pai de fábrica reconhecido por nome, descrição, idioma/corpos legados,
  autor e data, além de um único design inglês exatamente original.
- Exige HTML **e** JSON PT-BR exatamente iguais ao target textual 111. Uma diferença,
  inclusive string vazia ou reserialização, preserva a variante inteira.
- Troca somente os tokens das sete URLs aprovadas, nos dois campos na mesma
  escrita. Mantém bytes de markup, CSS, links clicáveis, IDs, chaves, números,
  dimensões de exibição e as duas camadas JSON do editor.
- Não cria pais ou variantes ausentes. Duplicidade de idioma ou pais indistinguíveis
  falha fechada. IDs, estado publicado/despublicado, categoria, cor e autor não mudam.
- Usa lock global do núcleo quando presente; sem ele, exige transação independente
  e InnoDB. Lê as tabelas com `FOR UPDATE`, confirma a escrita por releitura e reverte
  o lote em falha. Não confirma a transação de outro chamador.
- Reexecução é idempotente. Falha de tracking nativo mantém 111; retry registra 112
  sem alterar novamente os pares já migrados. Nunca escreve tabelas de campanhas.

A instalação continua sendo duas transações independentes: seed textual e assets.
Se a segunda falhar, o primeiro seed fica recuperável e a flag continua 0. O helper
de instalação agora retorna sem reconciliar qualquer biblioteca já ocupada, dentro
do lock: mesmo uma variante removida pelo usuário entre falha e retry não reaparece.
O upgrade textual explícito mantém seu contrato; a migration 111 publicada não muda.
Reativação com flag 1 não executa reconciliação. Um e-mail futuro copiado da biblioteca
recebe a variante escolhida pelo idioma; e-mails e campanhas anteriores ficam iguais.

## Revisão visual

Os sete PNGs foram inspecionados individualmente. Google refinado foi comparado ao
original em 240×110 e fundos branco, #202530 e #f9f9f9: cinco estrelas, letras,
margens, legenda esquerda e transparência aprovadas. O RGB oculto sob alfa 0 não
foi confundido com pixels visíveis; composições de QA não entram no produto.
Valentine mantém carro/balões e Analysis mantém oito pessoas, enquadramento e tom
azul visualmente; imagens generativas não são declaradas pixel-idênticas.
Os quatro SVGs foram também renderizados em fundo escuro para verificar o texto
branco e bordas. Não houve corte ou sobreposição bloqueante.

Os rasters imagegen preservam a proporção, com tolerância inferior a 0,2%; os atributos
HTML/JSON de tamanho original não mudam. O custo é maior download: aproximadamente
1,6 MB para Valentine, 1,5 MB para Analysis e 672 KB para Google. São URLs remotas do
próprio CRM, não anexos embutidos no corpo. Otimização futura deve ser uma operação
autorizada e visualmente validada, sem alterar silenciosamente estes arquivos.

## Solicitações de imagem e fontes editáveis

Ferramenta: imagegen integrada, sem API externa própria ou chave fornecida pelo
usuário. Especificações de edição: Valentine, substituir somente “For You” por
“Para você”, mantendo carro, balões, paleta, desenho e proporção quadrada; Analysis,
substituir somente “Analysis” por “Análise”, mantendo oito pessoas, poses, rostos,
roupas, composição, quadro e sobreposição azul. As versões finais foram copiadas
sem alteração para `assets/email-templates/ptbr/`.

Prompt final do refinamento Google:

> Use case: text-localization. Image 1 is the RGBA edit target. Image 2 is ONLY its
> correct appearance composited on white to help interpret alpha; do not copy the
> white background. Localize ONLY the tiny gray caption at the bottom from 'Your
> opinion matters' to exact Brazilian Portuguese 'Sua opinião importa'. Everything
> else is locked: preserve the original Google brand lettering shapes, stroke
> weight, original blue/red/yellow/green flat fills, exact five small gold stars,
> their spacing and position, margins, composition and true transparent background.
> Keep the 240:110 canvas aspect ratio and original layout; the right red e must
> retain its 3-pixel-equivalent right margin. Do not redraw, embolden, enlarge,
> recolor or shift the Google mark or stars. Caption remains left aligned at
> x=4-equivalent, y=94-equivalent, small original gray bold sans-serif, same
> approximate height 10 pixels relative to a 240x110 canvas. It must fit within the
> original caption line width, not be centered or enlarged. Preserve all content
> outside the narrow bottom-left caption region exactly. No new background, no
> shadows, no extra padding, no checkerboard, no glow, no gradients or textures.

As fontes vetoriais originais estão em `assets/email-templates/ptbr/source/`, com
texto acessível em title/desc. Foram renderizadas pelo Inkscape já disponível;
somente os PNGs são referenciados nos e-mails para compatibilidade.

## Validação e reprodução

- `php tests/ma_email_assets.php`: 365 checks SQLite aprovados; PDO real, pares/URLs/encodings, proveniência,
  clones, idiomas, estado, custom, snapshots de campanhas, transações/locks,
  rollback, instalação/retry e App_module_migration 111→112/tracking/replay.
- `php tests/ma_email_template_localization.php`: 93 checks SQLite de regressão textual 111.
- `python3 scripts/check-ma-email-assets.py`: sete PNGs, hashes, dimensões,
  proporções, transparência dos SVGs, proveniência e presença no pacote, offline.
- `python3 scripts/build-ma-email-templates-ptbr.py --check`: catálogo 111 íntegro.
- `node --test tests/ma_email_interface.test.cjs`: 5 testes aprovados de interface e ordem/flag do seed.
- CI adiciona a suíte PHP ao job MySQL existente. SQLite local não comprova locks
  concorrentes InnoDB. O bloco real de seed foi testado, não toda a DDL do instalador
  legado. Browser/Unlayer e clientes reais de e-mail continuam sem homologação local.
- Nenhum envio SMTP, token, dado real ou instalação de produção foi usado.

## Pull Request

- Objetivo: completar o texto visível nos sete assets detectados, conservando idioma
  e conteúdo de campanhas. MA 1.1.2 com nova migration 112; núcleo coordenado à parte.
- Arquitetura: allowlist fixa, helper transacional pós-111, URL local pareada,
  manifestos/fontes/PNGs versionados. Nenhuma nova dependência de runtime ou DDL.
- Segurança: parâmetros SQL, reconhecimento estrito, estado/IDs e idiomas
  preservados, sem downloads no upgrade, sem mudança de permissão ou envio.
- Desempenho: scan de biblioteca sob lock somente na migration/seed, não no cron;
  tamanho maior dos três PNGs generativos explicitado acima.
- Rollback operacional: não fazer downgrade de marcador nem substituir campos
  customizados; restauração seletiva exige pares e backups correspondentes, em nova
  migration revisada. Erros de escrita antes do commit revertem o lote automaticamente.
- Checklist: histórico preservado, provas locais e gate de módulo no commit final;
  MySQL/CI do commit publicado e homologação dos clientes de e-mail ainda necessários.
