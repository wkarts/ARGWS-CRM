# Recuperação do portal com transporte próprio por empresa

Este incremento amplia a conexão Google já entregue com uma escolha explícita para os novos pedidos de recuperação do portal. A preferência inicial é `native`: conserva o transporte próprio existente. Selecionar `google` exige uma conexão própria ativa, remetente verificado e permissão atual dos funcionários que salvaram a preferência e conectaram a conta. Não copia segredos, refresh tokens ou configuração de outra empresa/principal.

A tela Google da empresa apresenta o seletor separado de recuperação. Salvar A não muda B ou principal. A preferência possui revisão própria e autorização `settings.edit`; a página exige `settings.view` e contexto selado. Desativar a empresa, revogar o conector, perder permissão ou mudar o transporte impede as filas antigas de adotarem outra conta. Renovação automática do token conserva a época Google e não invalida o pedido.

## Emissão, token e recibo

`CompanyPortalRecoveryMail` é um emissor fechado que implementa exatamente o protocolo nativo de recuperação: proprietário do contato/cliente, identidade HMAC sobre id/cliente/empresa/e-mail/hash de senha, token aleatório cujo registro contém apenas SHA256, validade de uma hora e link com fragmento `#token=…`. Compartilhar cadastro não compartilha contato ou conta. A atribuição explícita da empresa principal aos contatos legados sem mapeamento conserva a regra nativa; não infere uma nova empresa por e-mail ou empresa administrativa selecionada.

O controller público conserva CSRF, `CompanyPortal::allowAttempt`, limite por IP/origem, intervalo de 60 segundos por contato e resposta idêntica para endereço desconhecido, conta conhecida ou transporte indisponível. O wiring substitui somente a chamada ao emissor após esses controles. `CompanyPortal` e suas funções de login/reset não são reescritos.

Uma transação própria, sob lock nativo, cria fila, substitui token anterior, grava recibo e auditoria. Verifica todos os campos persistidos antes de confirmar. Falha tardia desfaz as quatro operações e conserva o token anterior. Não existe adaptação posterior ao commit nem envio durante a transação.

O recibo contém ids de origem/contato/cliente/fila, token hash, identidade HMAC, impressão do destinatário/configuração/payload, espécie de transporte, época e expiração. Não armazena o token de recuperação em claro no recibo. O corpo da fila contém o link necessário para o e-mail, conforme a fila nativa; acesso a essa tabela continua sendo acesso sensível. Nenhum token, corpo ou destinatário aparece na tela de configuração, auditoria ou DTO público.

`CompanyPortal::reset` continua consumindo o token novo, trocando a senha e revogando cookies de contato. A prova exercita o login nativo com a senha alterada. Token consumido, substituído, expirado, contato/cliente/empresa desativados ou mudança de proprietário/identidade impedem um envio pendente obsoleto.

## Worker e fontes fechadas

`CompanyPortalMailQueueConsumer::assertQueueRouting` consulta o recibo persistido por queue_id **antes** de escolher qualquer transporte, mesmo que alguém retire o marcador do payload ou torne issuer_id NULL. Só admite o marcador exato, empresa e fila do recibo. Fila legada sem recibo não adota Google automaticamente. Um marcador sem recibo também é negado.

`state`/`assertDispatch` revalidam documento de recuperação, identidade atual, validade/consumo do token, destinatário sem cópias/anexos, payload e configuração atual. `CompanyPortalMailConfig::forQueue` escolhe exclusivamente o transporte já provado pelo recibo. O adaptador final `CompanyPortalMailDispatchSource` revalida fora da transação antes e depois de uma renovação Google. A união tipada no token provider admite somente os dois adaptadores finais conhecidos (documento e portal); não há callback arbitrário de autorização.

O worker retém os guards Google documental e de recorrência financeira. Remove o cabeçalho interno do portal antes do SMTP. A escolha `native` preserva o transporte próprio existente e sua compatibilidade legada; a opção Google usa a nova conta cifrada e o novo provedor de token canônico. Não há fallback de Google revogado para SMTP antigo.

## Schema e wiring

`CompanyPortalMailSchema::ensure($db, $prefix)` instala duas tabelas InnoDB aditivas: `company_portal_mail_preferences` e `company_portal_mail_deliveries`, com tipos, nulabilidade, defaults, collation, PK, índice exclusivo de fila e FK de origem validados. Não migra mensagens antigas ou tokens existentes. O recibo sobrevive à limpeza da fila como histórico e não comprova entrega.

A integração nativa futura deve aplicar esse schema, os arquivos próprios e os hunks separados de `Company_portal`/`App_Email`. A proposta do worker tem como base Google + envelope + adapters de recorrência financeira; conserva todos os guards. A proposta do controller público tem como base o controller434 e troca somente a emissão de recuperação. Não altera upload, contratos, tarefas, faturamento ou outras rotas do portal.

A correção separada `google-mail-fingerprint-portability.patch` deve preceder este incremento: os quatro inteiros conhecidos da configuração Google são normalizados antes do hash, evitando que representação numérica PDO e mysqli produza revisões diferentes para a mesma conta. Não normaliza campos fiscais, remove chaves ou muda épocas; não modifica hashes históricos de documentos.

## Provas e limites

SQLite e MariaDB reais comprovam A/B, opt-in privado, cooldown, filas canônicas, interoperabilidade com reset/login nativos, token consumido, rollback tardio, ausência de fallback e autorização antes/depois do refresh. Worker real `CI_Email`/`App_Email`/`App_mailer`/PHPMailer é executado; o último transporte externo é capturado. Contato desativado ou token consumido durante refresh impede liberar a credencial ao SMTP. Token tecnicamente renovado conserva o vínculo da fila.

Sessões MariaDB independentes observam dois waiters reais: pedidos simultâneos geram um único token/fila/recibo; preferência concorrente mantém transporte e motor coerentes; revogação do configurador sob lock nega ambos os pedidos. Chromium comprova GUI A/B/principal, resposta pública sem enumeração, fila Google por origem validada por PDO, fragmento limpo, reset/login nativos, consumo bloqueando fila, stale 409 e rollback 503/retry por trigger via mysqlCLI real. Comparação PDO/mysqli identificou e provou a correção de portabilidade do fingerprint.

O harness conserva versão 4.2.8 da base, instala o schema por ensure explícito e testa apenas os hunks novos em QA própria. Não simula marker de upgrade. Instalação/upgrade/tracking nativos436, MySQL8 e HTTP do composto final pertencem à prova de integração do root. Consentimento/verification Google, SMTP real, entrega real e homologação bancária não foram realizados. Criar fila ou capturar send não comprova entrega, e não há promessa de exactly-once externo. O contrato e as fontes primárias Google/League são os registrados no documento e manifesto do primeiro incremento.
