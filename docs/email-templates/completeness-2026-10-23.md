# Censo estático dos modelos — 2026-10-23

Base: `3833fa4`. Não é um censo do banco de produção.

- 101 slugs literais de classes de envio: 82 núcleo + 19 módulos. Todos têm catálogo PT-BR; nenhuma chamada literal `send_mail_template` examinada aponta para classe ausente.
- Os 82 defaults do núcleo têm assunto/corpo preenchidos, com tradução e preservação de merge fields verificadas por testes Node. Catálogos de módulos têm assuntos PT-BR e frases necessárias.
- Lacuna encontrada: `webhook-failed` era criado com corpo vazio. A seleção ignora variantes ativas vazias e pode retornar “Template Not Found”; a classe só fornece merge fields, não corpo dinâmico.
- Novos defaults: corpo informativo EN/PT-BR orienta consultar registros no CRM. Não inclui diagnóstico bruto, URLs, payload ou credenciais do webhook.
- Migration do módulo Webhooks 127 (1.2.7): preenche corpo vazio e variante EN/PT-BR faltante somente em grupos ativos reconhecidos como conteúdo de fábrica. Qualquer personalização, desativação ou terceiro idioma preserva o grupo inteiro. Remetente, IDs existentes, plaintext, ordem, empresa e estado são conservados. Replay é idempotente. Nenhuma migration histórica/core foi alterada.
- O backfill explícito de fábrica foi solicitado pelo usuário. Um vazio de fábrica não pode ser distinguido de um vazio intencional sem histórico; por isso só o caminho de migration explícita trata esses registros, nunca o bootstrap comum. Grupos customizados/desativados continuam exigindo revisão administrativa. Slugs totalmente ausentes seguem a criação de bootstrap no escopo atual; não são copiados entre empresas.

## Limites e validação

O reconciliador trabalha sobre slugs persistidos e não recompõe um slug totalmente ausente da empresa primária. Portanto a existência de catálogo/seed não comprova registro real em cada empresa. Verificar produção requer apenas metadados: empresa, slug, idioma, estado e indicadores de assunto/corpo vazio; não é necessário extrair conteúdo.

Testes Node de núcleo/interface/completude executados localmente. Testes PHP adicionados à suíte `tests/settings/email_template_localization.php` para novo default, replay, personalização, estado desativado e corpo vazio preservados. PHP CLI indisponível neste ambiente; executar suíte SQLite/MySQL no CI antes da integração/publicação. Nenhum envio SMTP nem mudança em produção foi realizado.
