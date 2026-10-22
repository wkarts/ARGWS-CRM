# Cadastro de empresas, consulta e identidade canônica

## Contexto e objetivo
O onboarding já possuía transação e seis estados persistentes, mas o cadastro e a edição apresentavam campos extensos, sem busca assistida ou identificação visual clara. Esta alteração mantém o fluxo existente e sua autorização, destacando etapas e agrupando identificação, endereço e contato. A criação pela interface passa pelo wizard; os endpoints existentes são preservados.

## Escopo
- Razão social e CPF/CNPJ obrigatórios; nome fantasia e inscrição estadual opcionais. ISENTO é uma informação declarada pelo usuário, nunca inferida pelo provedor.
- CEP, logradouro/endereço, número, complemento, bairro, cidade, UF/estado, país e telefone.
- Formulário compartilhado nas duas edições (principal/isolada) e na primeira etapa do wizard; labels também nas Definições.
- Navegação progressiva em três seções; sem JavaScript todos os campos continuam disponíveis. Validação nativa revela a seção de campo inválido.
- Etapas persistentes existentes: Identidade, Preferências, Colaboradores, Privacidade, Revisão, Conclusão. Sem alterar masterauth, grants ou semântica transacional.
- Seletor: adapter displayName entrega nome fantasia e fallback razão social. A integração visual do seletor pertence à entrega cumulativa de UI.

## Armazenamento e banco
CompanySettingsIdentity permanece a origem canônica. Campos adicionais: company_trade_name, company_state_registration, company_address_number, company_address_complement, company_address_district. Empresa principal utiliza options pelo commit guard; secundárias utilizam settings_json.company_profile. Não há tabela nem DDL nova. Nenhuma migração histórica é alterada. A versão/migration NOOP da entrega cumulativa cabe ao coordenador de integração.

Os writers e auditoria existentes preservam atomicidade, readback, isolamento e rollback. Endereço legado agregado permanece intacto quando componentes adicionais estão vazios. Ao separar endereço antigo manualmente, confira se número/bairro já estavam incluídos para evitar duplicidade.

## Consulta e contrato HTTP
POST admin/issuers/lookup; requer sessão autenticada, token de contexto da empresa, CSRF nativo quando habilitado e token dedicado lookup_csrf do formulário (issuers_csrf ou company_onboarding_csrf). lookup_mode seleciona registry/onboarding; issuer_id identifica empresa existente. Consulta de empresa nova exige canCreate; edição exige canManage do ID informado. Entradas: kind=cnpj|cep e value.

CNPJ: mesmo provedor terceiro já utilizado pelo plugin masks-cnpj-cep, ReceitaWS, URL fixa https://www.receitaws.com.br/v1/cnpj/{CNPJ}. CNPJ numérico validado por dígitos verificadores; CPF e CNPJ alfanumérico seguem disponíveis no cadastro manual, sem consulta neste provedor. CEP: ViaCEP, também terceiro, URL fixa https://viacep.com.br/ws/{CEP}/json/. Nenhum provedor é apresentado como órgão oficial. A consulta não fornece IE nem comprova isenção.

TLS verificado, somente HTTPS, sem redirects, conexão até 3s e operação até 8s, resposta limitada a 128 KiB. Não aceita URL/host fornecidos pelo cliente. Limite de uma consulta a cada três segundos por sessão; provedor pode aplicar limites adicionais. Respostas parciais só incluem valores válidos disponíveis; ID retornado deve corresponder ao solicitado. Não consulta dados/credenciais reais em testes.

Resposta JSON: fields e provider, mais csrf com novo token nativo. Erros: 405 método, 403 CSRF/ACL, 422 entrada, 429 frequência, 503 provedor/falha. Contexto inválido é negado previamente pelo middleware. Sem logs de valores consultados e Cache-Control no-store.

## Preview e privacidade
Botão explícito e aviso informam o compartilhamento do CNPJ com ReceitaWS ou CEP com ViaCEP. Dados voltam em preview editável: campos preenchidos ficam desmarcados; aplicar é uma ação explícita. Campos alterados durante a consulta são protegidos; trocar o documento/CEP invalida o resultado. Descartar e falhas não mudam os campos. Aplicar não salva: exige Salvar ou Salvar e continuar. Nada é copiado por blur nem por JSONP. Valores do provedor são nós de texto ou input.value, nunca HTML.

## Documentos/PDF
Razão social e documento fiscal continuam sendo a identidade legal. Nome fantasia não os substitui. Um mapper canônico reúne endereço, número, complemento e bairro somente ao criar novos snapshots de faturas, cópias, créditos e vendas; contrato interno de identidade permanece igual. Snapshots históricos não são regravados. IE/nome fantasia persistem no cadastro, mas não são novos campos fiscais do PDF nesta entrega. O renderer legado global de sales_helper mantém o endereço agregado antigo; não passa a concatenar componentes de cadastros atuais em documentos históricos renderizados dinamicamente.

## Testes implementados e executados localmente
Runtime PHP 8.4 disponibilizado em ambiente isolado, SQLite; sem rede de provedores:
- tests/company_registry_lookup.php: 21 checks de normalização, validação, resposta parcial, erro, mismatch e limites.
- tests/company_registry_lookup_controller.php: 10 checks novos de verbo, CSRF, ACL, criação, rate-limit, token e ausência de mutação (mais 100 checks de base).
- tests/settings/company_registry_profile.php: 29 checks novos de principal/secundária, displayName, identidade fiscal, ACL e rollback (mais 100 base).
- tests/companies/company_identity_documents.php: 5 checks novos de snapshot/endereço/PDF real/histórico (mais 63 base).
- Regressões: issuers_onboarding (35), company_context (88), invoices (63), invoice_copies (88), sales (139), primary_sales_documents (163), settings_model_controller (134). Contagens cumulativas não representam soma de testes independentes.
- PHP lint dos arquivos alterados, Node syntax check, git diff --check.
- company_identity_browser STATIC_ONLY: render real PHP, nomes canônicos, dois modos, aviso de terceiros.

## Testes implementados ainda não executados
company_identity_browser.cjs: preview/apply/cancel, proteção de edições posteriores, token renovado, erro, XSS como texto, viewport 1440/390 e screenshots. Chromium e MariaDB estavam bloqueados pelas restrições de socket do sandbox; não contornados. A integração deve executar browser e fixtures MySQL no CI. Três cenários browser existentes agora abrem a seção Endereço antes de preencher cidade.

## Validação manual recomendada
Criar empresa, revisar campos de CNPJ e CEP, preservar valores existentes, editar preview, sair/retomar wizard, voltar etapas, repetir submissão, concluir, editar principal/secundária e verificar nome fantasia no seletor integrado. Conferir IE opcional, endereço legado, PDF novo e preservação do antigo. Testar teclado, viewport móvel, credenciais expiradas, contexto trocado e indisponibilidade dos provedores. Não utilizar dados reais nos artefatos de CI.

## Implantação, rollback e riscos
Sem deploy executado. A integração deve aplicar a versão nativa contígua e gates habituais. Sem novas dependências de pacote; cURL PHP é necessário apenas para consulta, com fallback manual quando ausente. Sem novas chaves/API secrets.
Rollback de código remove a UI/adapters novos; manter campos já gravados em options/JSON sem apagá-los. Não reverter migrations históricas nem regravar snapshots. Valores já emitidos ficam nos respectivos snapshots. Principais riscos: disponibilidade/limites do provedor, endereços antigos agregados e QA visual ainda pendente.

# Pull Request (componente da PR cumulativa de UI)
Branch local: feat/company-wizard-cnpj
Título sugerido: feat(companies): organiza cadastro e edição com consulta CNPJ e CEP revisável
Descrição: integra formulário canônico, novos campos persistentes, consulta segura com preview e projeção de endereço em novos documentos, mantendo autorização e rollback existentes.
Arquivos: controllers/Issuers; CompanyRegistryLookup/CompanySettingsIdentity/CompanyRequestScope; quatro produtores de snapshot; views companies/issuers/settings; CSS/JS próprios; fixtures e workflow issuer-foundation; este documento. Nenhum arquivo removido.
Configurações/dependências: cURL já usado no projeto, sem segredo novo. Performance: lookup limitado; displayName consulta somente a opção relevante no caso principal, fallback canônico quando necessário.
Breaking changes: sem mudança de contratos persistidos ou endpoints existentes; navegação UI de criação usa wizard. Browser tests ajustados à seção Endereço.
Build/release: pendentes da integração cumulativa; sem publicação, tag, merge ou deploy nesta etapa.
Checklist: implementação, fixtures SQLite, lint e diff verificados; MariaDB, browser/screenshots e CI final pendentes.
Commit sugerido: feat(companies): add canonical profile and reviewed registry lookups
Merge sugerido: somente após autorização e checks da PR cumulativa; nenhum merge realizado.
Versão sugerida: número nativo definido pelo coordenador após integrar a base, sem bump neste componente.
