# Pull Request

## Branch
fix/email-modelos-ptbr (incremento local para integração à PR48).

## Título
fix(email): concilia modelos PT-BR e preserva idioma e origem dos envios

## Descrição, contexto e objetivo
Corrige rótulo English fixo no editor, descrições/grupos em inglês, criação de conteúdo com metadata de idioma incorreta e fallback divergente. Reconcilia fontes distribuídas reconhecidas, incluindo variantes históricas, preservando personalizações. Diagnóstico/evidências: [auditoria PT-BR](ptbr-audit.md).

## Escopo incluído e fora do escopo
Inclui 82 modelos core, 19 atuais de módulos, 58 variantes históricas/derivadas e apresentação de 268 pares de campos. Exclui reescrita dos 7 modelos MA, produção, SMTP real, tokens, deploy e migrations históricas. Filtro global e campo ausente do Purchase seguem como correção independente, documentada.

## Decisões técnicas e arquitetura
Seleção unificada por idioma e escopo empresarial. Labels são apenas apresentação. Catálogo de fontes exatas sem tradução heurística. Conciliação transacional, idempotente, com falha fechada para duplicidade. Novas variantes não contornam customizações/desativação da variante existente.

## Alterações realizadas
Idioma real no editor; variantes persistidas preservadas; listagem com fallback conservador; metadata da prévia consistente; seleção nativa/legada e por origem; criação/reconciliação de defaults conhecidos; cobertura de fontes históricas; inventário seguro e testes.

## Arquivos adicionados, modificados e removidos
Adicionados: EmailTemplateLocale.php, EmailTemplateReconciler.php, interface.json, legacy_sources.json, phrases_legacy.json, inventário estático, testes de localização/interface e documentação.
Modificados: Emails controller/model, helpers de templates/legado, App_mail_template, CompanyEmailTemplates, EmailTemplatesPtBr, templates_modules.json, views administrativas, labels EN/PT-BR, workflow empresas e testes correlatos.
Removidos: nenhum arquivo. Nenhuma migration histórica alterada.

## Banco de dados
Sem DDL. Próxima migration nativa deve chamar EmailTemplateReconciler::upgrade($db, db_prefix()). Não editar 458/459 publicadas. Atualizações por correspondência exata, inserções de variantes conhecidas, nenhuma exclusão/troca de ownership/idioma existente. Lock/transação conforme infraestrutura de empresas; possível bloqueio curto da tabela durante upgrade.

## APIs, contratos e integrações
Assinaturas públicas preservadas. Nenhuma rota nova. Não altera slugs, merge fields nem payloads externos. Helper original sem parâmetro de idioma conserva fontes desconhecidas como English e reconhece PT-BR validado; não força tudo a português.

## Dependências, configurações e variáveis
Sem dependência ou variável nova. Idioma ativo vem das opções da empresa de origem. Sem alteração automática de configurações.

## Segurança e privacidade
Escopo A/B, etiquetas escapadas, defaults de fonte pública, transações independentes, sem credenciais/dados de produção. Nenhum envio real. Nomes/corpos customizados não são publicados no inventário de fontes.

## Desempenho e observabilidade
Catálogos cacheados por request. Chamadas idempotentes do instalador com variantes existentes são somente leitura. Falhas de armazenamento/duplicidade propagam; logging administrativo existente permanece. Não há nova telemetria.

## Testes implementados, executados e recomendados
Implementados: localização, metadados, seed, replay, customização, idiomas, A/B, fallback, desativação, rollback e inventário visual de labels.
Executados: PHP 682 checks localização SQLite (+78 suporte), 19 de escopo nativo, fábrica/portal existentes; Node 19 testes; lint PHP de 17 arquivos; git diff --check.
Recomendados/pendentes: MySQL no CI integrado, próximo passo nativo e browser real. Não executados neste ambiente: SMTP real, Chromium e MySQL.

## Validação manual
Seguir roteiro em ptbr-audit.md, incluindo as duas telas exemplo, todas categorias, variantes legítimas, A/B, reativação e desativação.

## Impactos, compatibilidade, riscos e mitigação
Sem quebra de identificadores. O fallback passa a respeitar idioma ativo da empresa antes do English, e desativação interrompe a seleção. Pode expor modelos antes ocultos; não muda suas permissões. Catálogos desconhecidos/customizados permanecem intactos. Biblioteca MA e Purchase têm limites documentados. SQLite não prova locking/concorrência InnoDB.

## Deploy
Integrador gera próximo ID contíguo pelo writer oficial, chama helper e executa CI do commit integrado. Backup e janela normal de upgrade. Este commit não autoriza merge/deploy nem afirma publicação remota.

## Rollback
Reverter código se necessário; não desfazer textos por tradução inversa nem apagar modelos. Recuperação de dados somente por backup verificado se explicitamente necessária.

## Build, release e distribuição
Sem artefato binário novo. Versão core definida pelo integrador no próximo passo livre. Migration publicada permanece imutável.

## Documentação
ptbr-audit.md detalha causa comprovada, cobertura, fontes, testes e limitações.

## Checklist
- [x] Preserva dados customizados, estado, remetente e origem
- [x] Sem alteração de migrations existentes ou módulos funcionais
- [x] Lint/testes locais executados e limites registrados
- [ ] CI MySQL do commit integrado
- [ ] Browser real e migration nativa da próxima release

## Commit sugerido
fix(email): localize transactional templates without overwriting custom content

## Merge sugerido
Squash ou merge conforme política da PR48, somente após autorização e checks finais.

## Versão sugerida
Próximo passo core nativo contíguo disponível, gerado pelo integrador; sem bump neste patch local.
