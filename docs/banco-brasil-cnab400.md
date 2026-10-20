# Banco do Brasil 1.0.2: perfis explícitos CNAB 240 e 400

## Composição436 — fontes operacionais e recuperação

Núcleo4.3.6/436, BB1.1.6/116 e Asaas1.5.2/152 estão em validação sobre435. API16 por componentes integrais e vínculo declarado de retorno240 à mesma origem API usam o writer existente e a mesma identidade canônica; não criam remessa, outro canal ou pagamento automático. Alteração/baixa incerta anterior ou posterior à consulta veta um novo recebimento. Recuperação de processo morto após sending usa consulta independente autorizada, sem reenviar POST/PATCH nem fabricar sucesso pelo vencimento da reserva. A [validação436](companies/pr45-validation-436.md) discrimina fonte congelada, instalação/tracking, testes e CI do próprio SHA.

O canal400 continua indisponível pelas lacunas BRL85–86, juros herdados/contrato e pictureCBR64326–39; trailer641 já foi resolvido. Testes sintéticos e declarações operacionais não demonstram autenticação bancária ou homologação. Nenhum merge, deploy ou transmissão real.


## Incremento435 — perfis API e prova financeira cruzada

Núcleo4.3.5/435 e móduloBB1.1.5/115 (NOOP de schema); os contratos anteriores e hashes não são reclassificados. Registro API agora oferece juros mensais percentuais e primeiro desconto fixo ou percentual até data, com declaração contratual/capacidade explícita, perfil imutável e consulta coerente da conta. Publicar taxa/oferta não lança juros nem reduz a dívida: recebimento API com ajustes continua em revisão neste incremento. Asaas1.5.1/151 preservado. A [validação435](companies/pr45-validation-435.md) separa testes locais, tracking e CI do próprio SHA.

A disputa financeira API20/CNAB17 passou com duas fontes finais reais/two-process InnoDB e controles positivos separados. Colisão de identidade entre faturas é adversarial/sintética; não representa importação GUI de retorno240 sobre origemAPI, cujo adaptador segue como código em implementação. Layout240 e contrato manual mantêm seu significado: download/declaração não são transmissão ou aceite autenticado. CNAB400 segue indisponível por BRL85–86, juros herdados/contrato e pictureCBR64326–39 ainda sem confirmação suficiente; trailer641 não é reaberto. Nenhum canal/API/240/400/Asaas/SMTP foi homologado externamente.


## Escopo e estado real

A versão 102 acrescenta o retorno observacional CBR643 e um **codificador de candidato** CBR641. Também exige seleção explícita de perfil para novas importações 240/400. Não representa homologação do BB nem uma integração bancária completa. Este parágrafo descreve o incremento102. Para originação240, retorno assistido e executor API108, consulte a [matriz atual](companies/pr45-status.md). Nenhum desses incrementos libera a remessa400 nem demonstra homologação.

A remessa 400 não fica disponível na interface. O método de teste `Cnab400Generator::candidate` retorna `operational=false` e as pendências, além do conteúdo sintético. Não existe código monetário BRL presumido: o chamador de testes precisa informar os dois caracteres candidatos. O manual atual só enumera 02/dólar e 14/euro; ambos são recusados no recorte BRL. **Esse candidato não deve ser enviado ao banco.** A confirmação escrita do banco sobre o campo, os juros da carteira e a homologação por conta/convênio continuam obrigatórias antes da entrega operacional.

## Fontes primárias verificadas

Catálogo oficial: <https://www.bb.com.br/site/pro-seu-negocio/aplicativos-leiautes-de-arquivos/>.

- CBR641, convênio de sete posições, julho/2026: <https://www.bb.com.br/docs/portal/dimep/CBR641_Pos7.pdf>. SHA-256 `ef77ecbf067ef1eb3d29da52a2d2cc8501a181cdfa141ccca62fb4c6290252a8`. Download público e inspeção em 18/10/2026, 24 páginas. Este é o PDF ligado pelo catálogo atual; não se adotou silenciosamente a revisão mais antiga indexada em outro endereço do BB.
- CBR643, convênio de sete posições, julho/2023: <https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc2628CBR643Pos7.pdf>. SHA-256 `da3a81b60df9ddfaaad22d3fa1889c45133a9d849fc950c8a78ce08d542303a1`. Download público e inspeção em 18/10/2026, 29 páginas.
- Particularidades 240 permanecem na revisão junho/2026 documentada em [banco-brasil-cnab240.md](banco-brasil-cnab240.md). Não se transpõem códigos ou alinhamentos de um formato para outro.

Os PDFs completos não são incluídos no repositório. As fixtures foram construídas para os testes e não constituem arquivos homologados fornecidos pelo banco. O repositório Financial Platform foi referência de investigação, não fonte de homologação nem de implementação copiada.

## Rechecagem histórica em 18/10/2026

O catálogo oficial continua listando CBR641 e CBR643 para convênio de sete posições. A busca oficial desta revisão também retornou o PDF [CBR641 de sete posições](https://www.bb.com.br/docs/pub/emp/empl/dwn/Doc2627CBR641Pos7.pdf), cuja folha de rosto informa versão junho/2024. O endereço e SHA-256 de julho/2026 registrados acima não puderam ser abertos ou recalculados nesta verificação. São referências oficiais diferentes; a revisão canônica precisa ser comparada por download binário e SHA-256 antes de liberar a remessa.

No PDF de junho/2024, o trailer de remessa está definido sem ambiguidade: registro `9`, posições 2–394 em branco e sequência nas posições 395–400. O campo de moeda nas posições 85–86 enumera 02 (dólar) e 14 (euro), sem documentar código BRL neste leiaute. O candidato CBR641 continua bloqueado para cobrança BRL até o BB confirmar o campo e a revisão aplicável ao convênio.

## Rechecagem binária em 19/10/2026

Os PDFs canônicos de julho/2026 (CBR641), julho/2023 (CBR643) e junho/2026
(CNAB240) foram baixados integralmente novamente, extraídos e seus hashes
coincidem com os registrados. Isso resolve a indisponibilidade de download
mencionada na verificação histórica acima.

No CBR641 de julho/2026, página 10, o trailer da **remessa** é inequívoco:
registro 9; posições 2–394 em branco; sequência 395–400. Esse é o contrato já
codificado pelo candidato. A nota 30 continua listando apenas 02/dólar e 14/euro;
não autoriza inferir código BRL. A nota 10 confirma que juros zerados/omitidos
podem herdar a taxa do beneficiário no banco.

A divergência de picture é do trailer de **retorno CBR643**: posições 26–39
compreendem 14 caracteres, mas a tabela descreve `9(013) v99`. O parser preserva
os 14 caracteres brutos e declara a interpretação não verificada. Não converter
essa inconsistência em valor financeiro nem confundi-la com o trailer CBR641.
Permanece necessária confirmação do banco sobre o picture e o campo BRL.

Histórico da busca anterior (superado para a API pelo contrato3.2.2 no handoff):
a página de Cobrança apontava para `https://apoio.developers.bb.com.br/apis/5?versaoApi=2`,
sem OpenAPI utilizável nesta consulta; `https://api.bb.com.br/cobrancas/v2/swagger?`
retornou 403 sem AppKey. Não foram inferidos endpoints, escopos ou certificados.

## Configuração visual e autorização

Menu **Banco do Brasil → Conta**:

1. Escolher um emitente autorizado e cadastrar a conexão (conta, ambiente, convênio, carteira e variação). A identidade legal vem do cadastro autorizado do emitente; não pode ser substituída neste formulário.
2. Registrar um perfil 240 ou 400 na conexão. A escolha deve vir do contrato do convênio, nunca da região. Um vínculo novo recebe um ID e a revisão/hashes dos manuais. Não há atualização de vínculo existente para reinterpretar arquivos.
3. Em **Retorno**, escolher explicitamente conexão e perfil. O backend verifica que pertencem ao mesmo emitente/conta/ambiente/convênio. Não há reconhecimento automático com fallback quando o formato selecionado falha.
4. Rever as observações registradas. Nenhuma delas cria pagamento ou altera fatura.

Cada ação usa `issuer_resource_can(empresa, staff, 'banco_brasil', capability) === true` como autorização contextual completa, com capabilities nativas `view`, `manage` e `import`. A lista de empresas e a visibilidade do menu também são filtradas por `view` do recurso. O papel global não concede nem veta esse grant contextual. O catálogo nativo de capabilities continua registrado. Não existe fallback para `manage_integrations`. Administrador comum não herda grants por empresa. O administrador principal só é reconhecido pelo helper de Empresas emitentes através de registro explícito persistido, com staff ainda ativo/admin; BB não presume ID1 nem resolve esse papel por conta própria. O helper pertence ao núcleo Empresas na PR45 unificada. As concessões são explícitas; ausência de autorização permanece bloqueada. O módulo histórico issuers não é carregado operacionalmente.

POST, CSRF próprio, escapamento, auditoria e escopo são preservados. Consultas e transações desabilitam `db_debug/save_queries` durante o acesso e restauram o estado do chamador. Falha de consulta mostra aviso genérico de armazenamento e desabilita ações, sem se passar por ausência de dados ou falta de autorização. Esse boundary não substitui a política de logs do driver CI3 nem afirma redigir logs internos do framework; não há coleta de credenciais nesta versão. Parâmetros funcionais ficam na interface nativa, não em ENV/app-config. Nenhuma credencial é solicitada nesta fase. No histórico102, a API futura deveria ter configuração visual própria, protegida, mascarada e auditada, sem presumir requisitos ainda não validados.

## Proveniência e limites do retorno

A seleção do ambiente é uma **declaração local**, não prova do ambiente remoto. O CBR643 não contém marcador inequívoco de sandbox/produção, nem documento fiscal do emitente ou campo de moeda. O registro conserva separadamente ambiente/moeda declarados e evidência ausente no arquivo; não inventa validação de CNPJ, BRL, titularidade, autenticidade ou homologação.

O parser valida conta/agência/DVs, convênio, carteira 17 simples, variação, Nosso Número 7+10, controle, largura de 400 bytes, sequência integral, cabeçalho/trailer, campos numéricos e datas válidas. Aceita ASCII com CRLF, no máximo 16 MiB, registros 0/7/9. Auxiliares 2/3/5 (partilha, vendor, Pix, cheque detalhado, descontos e outros) são recusados integralmente até haver decoder específico; não são ignorados.

- Campos alfanuméricos 400 são alinhados à esquerda; identificadores fiscais preservam letras. Agência e conta usam quatro/oito dígitos e não sofrem truncamento.
- Datas DDMMAA usam política explícita **2000–2099**, registrada no perfil. Não há pivot baseado na data do processamento. Arquivos históricos cuja interpretação exige outro século não pertencem a este perfil.
- Pago, nominal, tarifas, juros, abatimento, desconto, IOF, outras despesas e recebimentos são separados. Zero pago continua zero. O valor do lançamento é preservado com seu indicador débito/crédito, sem ser chamado de líquido.
- Códigos 93 e cheque pendente não são liquidação. No 400, 46 é cheque pendente; não se reutiliza a interpretação do código 50 do 240. Naturezas 04/10/11 de recebimento mantêm a pendência de compensação. Comando 20 é instrução de débito, não comprovação automática de pagamento.
- Comandos 39/85/86 são recusados porque sobrecarregam um campo monetário com prazo/agente.
- O trailer CBR643 página 16 descreve posições 26–39 (14 caracteres), mas indica picture 9(013)v99 (15). A implementação conserva os 14 caracteres **brutos**, quantidade e aviso, assinalando a divergência; não fabrica um total financeiro ou uma igualdade entre esse total e a soma dos pagamentos. A sequência final e os limites dos campos são validados; a interpretação contábil do total permanece pendente de confirmação do BB.

A deduplicação distingue canal/leiaute; reapresentação do mesmo evento 400 em outro arquivo mantém um evento com todas as proveniências. Conteúdo financeiro divergente para a mesma identidade de evento aborta toda a transação. Ter uma observação em 240 e outra em 400 não autoriza contabilizá-las duas vezes: esta etapa não possui executor financeiro.

## Migração e compatibilidade

`101_version_101.php` permanece byte a byte igual ao publicado na PR45. A nova `102_version_102.php` usa `App_module_migration`, cria `bb_layout_bindings` e acrescenta colunas de vínculo/proveniência **nullable** a tabelas existentes. O executor nativo realiza 101→102 tanto na ativação inicial quanto no upgrade. A validação estrutural exige InnoDB, tipos, nullability, PK e UNIQUE completos, sem reparar silenciosamente tabelas incompatíveis.

Não há backfill de perfis: registros 101 conservam vínculo NULL e continuam visíveis. Reimportar exatamente o arquivo antigo não o reatribui ao perfil recém-selecionado. Novas importações exigem seleção, inclusive no 240. Isso é uma mudança de fluxo visível; o operador precisa registrar o perfil antes de importar novos arquivos. Nenhum recibo financeiro antigo é reclassificado.

Falha de DDL/validação impede avançar o tracking. DDL parcialmente concluído é aditivo e pode ser retomado depois da correção administrativa do schema. Não existe downgrade destrutivo. Para suspender a importação, retirar a permissão apropriada ou desativar o módulo; manter as tabelas para preservar histórico. Não voltar ao código 101 para contornar a seleção explícita.

## Testes

PHP 8.1 e 8.5, local: 412 verificações distintas (240 118; store 48; admin 63; 400 82; vínculos 38; migration101 36; migration102 27). Lint de 33 arquivos PHP e `git diff --check` passaram.

Workflow atualizado executa também MySQL 8.4 com driver PDO/CI3 mysqli, upgrade real com histórico 101, constraints e dois processos concorrentes para ambos os formatos. A espera no lock da conexão é comprovada pelo Performance Schema. **A execução CI da versão 102 ainda está pendente; o verde da versão 101 não comprova a 102.** Nenhum teste contata endpoints bancários nem usa dados de clientes.

Atualização413: a especificação primária API foi recuperada e o executor é separado
do candidato400. Consulte o [handoff](banco-brasil-handoff.md). Os bloqueios BRL/juros
CBR641 e a divergência de picture CBR643 continuam válidos.

## Continuação111: insumos específicos ainda necessários

A busca primária desta continuação reabriu o retorno CBR643 julho/2023 no endereço
oficial acima: a página16 continua apresentando26–39 (14 posições) com picture
`9(013)v99` (15). A revisão julho/2026 de CBR641 não abriu nesta ferramenta;
o endereço oficial alternativo `Doc2627CBR641Pos7.pdf` abriu junho/2024, cujas notas
10/30 continuam documentando herança de juros do beneficiário e apenas02/dólar e
14/euro. Isso não substitui a revisão canônica julho/2026 nem resolve BRL.

O desbloqueio400 requer confirmação primária específica: conteúdo BRL85–86;
isenção ou parâmetros efetivos de juros do beneficiário para o convênio/carteira/
variação; e correção oficial do total CBR64326–39. Remessa aceita de outra
aplicação, quando fornecida privadamente e com origem verificável, pode orientar
uma fixture anonimizada, mas não resolve por si só o picture do retorno ou
homologa o novo canal. O trailer de remessa CBR641 permanece resolvido e não é
pendência. O candidato continua indisponível; isso é bloqueio documental para
escrita correta e trabalho técnico de integração posterior, separado da
homologação bancária externa.


## Situação no núcleo427 / BB113

A composição financeira427 pertence somente ao perfil assistido CNAB240. Não
transforma o candidato400 em canal operacional, não resolve BRL85–86 ou picture
CBR64326–39 e não presume isenção de juros do beneficiário. Faltam os documentos
específicos e, depois, integração/testes próprios; homologação externa é outra etapa.
