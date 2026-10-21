# Modelos transacionais em PT-BR: diagnóstico e correção

## Diagnóstico verificado

A tela `application/views/admin/emails/template.php` imprimia o título `English` literalmente para o primeiro editor, qualquer que fosse o campo `language` do registro. Os dois prints são exemplos dessa interface; não demonstram por si só que os registros do banco estejam marcados com idioma errado. Nenhum banco de produção foi inspecionado.

Também havia nomes de grupos/descrições de campos em inglês, avisos literais em inglês e divergências de seleção entre envio nativo, legado e prévia. O helper de criação marcava qualquer entrada como `portuguese_br`; a tradução dependia de `source_*`, ausentes no catálogo core. Assim, chamadas core de migrations podiam criar conteúdo inglês com metadata PT-BR.

## Cobertura e limites

- 82 modelos atuais do núcleo e 19 de módulos: nomes, assuntos, corpos, type, slug e campos de mesclagem.
- 41 chamadas estáticas `create_email_template`, 26 slugs e 30 combinações exatas de nome/assunto/corpo; nenhuma expressão não resolvida no snapshot auditado.
- 68 linhas históricas SQL, 67 slugs; 3 slugs aposentados pela migration197 não são recriados.
- Catálogo suplementar: 54 variantes literais históricas e 4 derivações exatas da migration111. Total de 159 definições conhecidas em 102 slugs (101 atuais + alias histórico plural de apontamentos).
- Sufixo de nome `[portuguese_br]` gerado pela migration124 é reconhecido; conteúdo intencionalmente vazio é preservado.
- 55 frases históricas adicionais. Cada tradução conserva literalmente HTML, URLs, atributos e a sequência de merge fields. Assuntos históricos conservam seus próprios marcadores, mesmo quando diferem do assunto atual.
- Apresentação: 268 pares exatos de chave/nome de campos, inclusive 3 campos condicionais LGPD e o campo de telefone acrescentado por webhook. Nomes personalizados não coincidentes são preservados.
- Biblioteca separada de Marketing Automation: 7 campanhas em inglês (Fitness, Valentine Day Sale, Black Friday Offer, Black Friday Deal, Thanks Subscriber, Happy Birthday, Branding), tabela `ma_email_templates`. Apenas inventariada; não reescrita.

O inventário estático não é censo dos registros personalizados ou módulos externos de uma instalação. Os 101 modelos atuais têm classes de envio, mas isso não comprova entrega SMTP nem preenchimento correto de todo dado em produção.

## Contrato de dados

`EmailTemplateReconciler::upgrade($db, db_prefix())` é o ponto de entrada da próxima migration core, a ser criada pelo integrador. Este patch não modifica migrations históricas nem escolhe um número de release.

- Transação independente e bloqueio compartilhado com as edições nativas; falha causa rollback.
- Identidade por empresa, slug e idioma. Duplicidades em registros reconhecidos falham de forma fechada.
- Atualização somente de campos exatamente iguais a fontes distribuídas conhecidas. Assunto/corpo customizados, conteúdo vazio, estado, remetente, plaintext, ordem, IDs e empresa são preservados.
- Sem exclusão, renomeação de slug, troca de idioma de linha existente, mudança de opções globais ou cópia de personalizações entre empresas.
- PT-BR ausente é derivado apenas de conteúdo inglês reconhecido. Inglês customizado não é clonado e falsamente rotulado como português. Também não se completa inglês ausente sobre PT-BR customizado, nem EN/PT sobre modelos existentes somente em outro idioma.
- Reativação de módulo conserva configurações da variante existente. Não pode criar uma nova variante ativa para contornar uma variante desativada.
- Uma fonte desconhecida no helper sem parâmetro de idioma mantém o contrato original em inglês; não há adivinhação do idioma por heurística. Fonte PT-BR conhecida é reconhecida como PT-BR.
- Reexecução é idempotente. Nenhum modelo de módulo não instalado é inventado durante a conciliação.
- O caminho frequente de inicialização de módulos é somente leitura quando as variantes esperadas já existem.

## Idioma e interface

A listagem prioriza PT-BR, mantendo acesso a modelos existentes em outros idiomas. O editor usa as variantes persistidas do mesmo slug/empresa e mostra seu idioma real. Grupos, descrições e nomes originais conhecidos são apresentados em português sem alterar identificadores nem conteúdo armazenado. Modelos de módulos desativados continuam acessíveis em Outros.

Envio nativo/legado: idioma solicitado → idioma configurado da empresa → PT-BR → inglês, removendo repetições. O primeiro modelo desativado encontrado interrompe a busca, inclusive se seu corpo estiver vazio. Idioma explícito disponível não é substituído. Prévia usa a mesma linha escolhida para a mensagem. Serviços de envio por origem leem a configuração da empresa persistida no evento, nunca a empresa interativa de outra aba.

## Limitações independentes identificadas

- Resolvido no incremento Purchase 1.5.1: o filtro global deixou de forçar `portuguese_br`; somente os oito modelos nativos de fornecedor consultam preferência inequívoca. Prévia e envio seguem o mesmo contrato. Veja [detalhes e testes](../../modules/purchase/docs/email-language-151.md).
- Resolvido no incremento Purchase 1.5.1: `{staff_name}` recebe o destinatário já fornecido pelo model, separado do ator; três avisos internos declaram `for=staff` para respeitar sua preferência.
- As telas e os corpos de campanha de Marketing Automation possuem contrato separado.
- Browser/Chromium e MySQL locais indisponíveis neste ambiente. Não houve envio real, teste SMTP, acesso a tokens ou produção.

## Verificação

Implementados:
- `tests/settings/email_template_localization.php`: seed core, upgrade histórico, replay, customização, vazio, metadata, criação/reativação, rollback, duplicidade, empresas A/B, idioma explícito e fallback desativado.
- `tests/email_template_interface.test.cjs`: cobertura dos rótulos/categorias/idiomas e presença de caminhos de renderização corretos.
- Ampliação de `tests/companies/email_template_scope.php` para preferência explícita, desativado vazio e catálogo multilíngue isolado.
- `scripts/inventory-email-template-sources.php`: extração estática com `token_get_all`; nunca executa instalador, migration ou envio.
- CI existente de empresas inclui os novos testes, usando banco MySQL descartável.

Executados localmente:
- PHP: 682 verificações de localização (SQLite), mais 78 do suporte de configurações.
- PHP: 19 verificações de escopo nativo.
- Node: 19 testes de catálogos/interface/contratos correlatos.
- Suítes existentes de fábrica/portal executadas sem falha.
- Lint PHP e `git diff --check` antes da integração.

Ainda pendentes: execução MySQL no CI do commit integrado, migration nativa da próxima release e inspeção visual real em browser. Não confundir a suíte SQLite com prova de MySQL/InnoDB, nem teste de seleção de modelo com envio de e-mail.

## Roteiro manual

1. Abrir um modelo PT-BR e confirmar Português (Brasil); abrir sua variante inglesa e confirmar Inglês, com conteúdo preservado.
2. Conferir nota de crédito/chamado e demais categorias, descrições de campos e aviso de URL pública de chamado.
3. Editar assunto/corpo customizados, atualizar e executar a nova conciliação; confirmar preservação.
4. Alternar empresas A/B e verificar IDs/valores independentes, incluindo idioma padrão distinto.
5. Desativar uma variante e confirmar que a seleção não procura outra ativa para enviar.
6. Reativar módulo com modelo inglês customizado/desativado e confirmar que não é substituído por fallback ativo.
7. Confirmar módulos desativados em Outros e variantes não PT-BR no editor.

## Implantação e rollback

Backup normal do banco antes do upgrade. O integrador cria o próximo passo nativo contíguo e chama o helper após as estruturas de empresas. Não reaplicar nem editar a migration458 já publicada. Sem DDL nova neste patch; a tabela de modelos e o lock existente devem ser transacionais. A conciliação bloqueia os modelos durante a leitura/alteração, portanto usar a janela habitual de upgrade.

Rollback de código não deve apagar traduções/customizações. Se necessária reversão dos dados, usar backup verificado da janela apropriada; não executar tradução inversa, exclusão por idioma ou substituição global de conteúdo.
