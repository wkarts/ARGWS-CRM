# Correção de idioma e destinatário em 1.5.1

O filtro global `email_template_language` do Purchase retornava PT-BR para toda mensagem, inclusive clientes, equipe e leads do núcleo. Agora a preferência do fornecedor só é consultada para as oito classes/slugs nativas de mensagens a fornecedores. E-mails de aprovação e todos os demais preservam o idioma recebido. A seleção core continua responsável por fallback e estado desativado.

Uma escolha de idioma já diferente do idioma padrão recebido não é substituída. Quando aplicável ao fornecedor, a consulta exige um único contato ativo ligado a fornecedor ativo; endereço inexistente/duplicado, idioma vazio/inválido ou contexto não reconhecido preservam o idioma anterior. Não há escolha arbitrária do primeiro registro. Identificadores, templates, customizações e preferências gravadas não são alterados.

O preview nativo passa a classe/slug refletidos no contexto do filtro, mantendo as chaves antigas; a prévia e o envio do fornecedor usam a mesma preferência. Os parâmetros de classe seguem do helper até prepare.

Os três avisos de aprovação declaram `for=staff`, permitindo o resolver nativo usar a preferência do destinatário. `{staff_name}` é preenchido exclusivamente com `data.staff_name`, já montado pelo `Purchase_model` a partir do colaborador destinatário. `{by_staff_name}` continua sendo o autor da aprovação. Na ausência do nome do destinatário, usa-se vazio, sem procurar usuário logado ou assumir que o aprovador é o destinatário.

Migration151 é NOOP: registra versão1.5.1 pelo executor nativo sem reescrever dados. Migrations até150 ficam byte a byte intactas.

Validação local: 75 verificações PHP de serviço e preparação nativa de mensagens, incluindo os oito modelos, idiomas EN/FR/PT-BR, preferências explícitas do núcleo/equipe/lead, destinatários ambíguos, rótulos e replay NOOP. Não houve SMTP, e-mail real, token ou dados de produção. Testes core de localização continuam sendo executados separadamente. Consulta real MySQL e visualização em browser dependem da CI/homologação integrada.

Rollback: reverter código se necessário, sem alterar dados do usuário ou renumerar migrations já aplicadas. Publicação/merge/deploy dependem da integração autorizada da PR48.

# Pull Request

- Branch: fix/email-modelos-ptbr (incremento Purchase separado)
- Título: fix(purchase): limita idioma ao fornecedor e preenche destinatário das aprovações
- Descrição/contexto/objetivo: remover contaminação global de idioma e completar o campo de destinatário já existente
- Escopo: filtro, formatter, três classes de envio, versão1.5.1, migration151, testes; sem tradução/regravação de campanhas ou envios reais
- Arquitetura: serviço puro de seleção com consulta injetada e allowlist de classes/slugs; wrapper no hook existente
- Arquivos: purchase.php, services/PurchaseMailLanguage.php, Purchase_approve_merge_fields.php, três mails de aprovação, migrations/151_version_151.php, testes/workflow/documentação; nenhum removido
- Banco: NOOP, sem DDL/DML; nenhuma migration histórica alterada
- APIs/contratos: mesmas assinaturas, slugs e chaves; novo preenchimento de chave documentada; avisos internos classificados como staff
- Dependências/configuração: nenhuma nova
- Segurança/privacidade: sem credenciais; contato ativo inequívoco; não infere pessoa por nome; não transmite dados
- Desempenho/observabilidade: consulta limitada a dois resultados apenas no escopo fornecedor elegível; demais mensagens não consultam fornecedores
- Testes implementados/executados: 75 checks PHP e regressões Node; lint e gate de diff do módulo no commit final
- Testes recomendados/manual: envio em ambiente de homologação com fornecedor EN/FR, staff PT/EN e endereço compartilhado; sem chamar SMTP real neste patch
- Impacto/risco/breaking changes: idioma legítimo deixa de ser forçado; sem mudança de identificadores ou persistência; mensagens que dependiam da coerção global passam a seguir sua configuração
- Deploy: integrar com próximo core contíguo coordenado pelo responsável, executar CI, atualizar módulo pelo runner nativo
- Rollback: código apenas; não inverter dados nem remover migration aplicada
- Build/release/distribuição: módulo1.5.1; sem artefato binário independente
- Documentação: este arquivo e auditoria core
- Checklist: [x] dados preservados; [x] testes locais; [x] migration inédita; [ ] CI integrado/MySQL; [ ] homologação visual
- Commit sugerido: fix(purchase): preserve recipient language and approval names
- Merge sugerido: incremento da PR48 após checks/autorização, sem merge automático
- Versão sugerida: Purchase1.5.1 e próximo core livre pelo integrador
