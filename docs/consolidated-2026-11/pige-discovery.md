# Descoberta e preparação PIGE — módulo1.1.0

Contrato verificado no PIGE360-self main `f47b8916b92231ac29b56862b133c9d2b2eec851`
(revalidado via remoto em6/11/2026). Fontes: `backend/app/registry.py:schools` e
`backend/app/crm_bridge.py:get_configuration`/`connection_output`.

- GET `/api/v1/schools`: JWT administrativo, instituições ativas com SchoolAccess
  ativo. O CRM devolve somente UUID e nome; dados adicionais não chegam à view.
- GET `/api/v1/schools/{school_id}/crm-bridge/configuration`: JWT, vínculo escolar
  e `integrations.manage`. O servidor PIGE decide essa autorização; a listagem de
  instituições não é tratada como autorização para configurar a ponte.

A nova ação POST administrativa `pige_bridge/settings/discover` requer os guards
nativos, CSRF próprio, administrador e `settings.edit` na empresa escolhida.
Exige HTTPS também na entrada do CRM, sem confiar em X-Forwarded-Proto enviado pelo cliente. Revalida ACL e módulo ativo/atual antes/depois do transporte. Não grava conexão,
credencial, auditoria financeira, consentimento, fatura ou efeito acadêmico.

O modal de nova conexão pode preencher escola, conta e ambiente de uma configuração
existente. A empresa remota e a URL do CRM precisam coincidir com o contexto escolhido
e a configuração confiável local. Revisão remota é exibida como informação; não é
substituta da revisão local do writer. Conexões existentes mantêm escopo imutável e
não exibem o importador. Cancelar/fechar ou mudar destino/empresa invalida respostas
em andamento. Nenhum `enabled` remoto é importado.

Continuam explícitos/manuais: identificador local da conexão, HMAC compartilhado,
funcionário, moeda, conta/perfil financeiro e consentimentos. O GET remoto não retorna
o segredo, e o CRM não pode recuperá-lo por inferência. PIGE sem configuração responde
null: não é apresentado como autoconfiguração completa. O novo fluxo110 pode preparar uma configuração remota ausente e desativada a
partir de uma conexão local desativada já cadastrada, com confirmação explícita
do destino e transferência do HMAC. Não houve alteração no repositório PIGE360.

Transporte: HTTPS na porta443, URL sem usuário/senha/caminho/query/fragmento,
hostname DNS, IPv4 público validado e fixado por CURLOPT_RESOLVE com verificação TLS
do hostname, sem proxy de ambiente e sem redirecionamento. IPs privados, link-local,
CGNAT, documentação, multicast e IPv6 são recusados; DNS com qualquer resposta
não pública é recusado. Conexão5s, HTTP15s, resposta até256KiB e sem retry automático.
Ambientes que exigem proxy, IPv6 ou instalação em subcaminho não são suportados por
este transporte. Isso é limite explícito, não autorização para contornar rede.

A credencial fornecida deve ser JWT administrativo, nunca o HMAC financeiro. Ela
existe somente no campo password, requisição POST e memória do processo/transporte;
não é devolvida pelo endpoint nem persistida em banco, sessão, cookies, localStorage
ou sessionStorage pelo recurso. Erros não ecoam bodies, URLs ou mensagens remotas.
Fechar o modal apaga a credencial; sucesso no preenchimento ou erro também a apaga.
Logs de proxy/APM externos ao recurso não foram homologados. Nenhuma credencial real
foi utilizada nos testes.

Evidências: `pige_bridge_discovery.php` verifica contrato, minimização, destinos,
DNS misto, HMAC/CRLF recusados, respostas parciais,401/403, redirecionamentos, timeout,
escopo e revogação após HTTP, com DNS/transporte explicitamente simulados. Chromium
usa view/JS reais e respostas de transporte simuladas; verifica preenchimento,
credencial limpa, flags financeiras intactas e ausência de adoção do ID remoto.
Instalação/upgrade109 é NOOP: migrations anteriores preservadas. Execução HTTP/TLS
bilateral autenticada com credencial de teste PIGE ainda pendente; não é homologação.

## Revalidação do contrato bilateral

Remoto `wkarts/PIGE360-self` main novamente conferido em6/11/2026: permanece
`f47b8916b92231ac29b56862b133c9d2b2eec851`. O PUT `/configuration` aceita `version`,
`enabled`, `base_url`, `issuer_id`, `account_id`, `environment` e `secret`.
Criação exige segredo; atualização exige revisão; segredo vazio preserva o salvo.
Uma conexão com solicitações não permite mudar destino/escopo. A resposta é
`connection_output(row)`, diferente do GET que envolve `configuration`.

O ID remoto é usado internamente no PIGE para selecionar a conexão. **Não precisa
ser igual à chave local CRM:** o envelope assinado contém o escopo e o CRM escolhe
exatamente uma conexão por escopo/HMAC (`PigeBridgeProtocol::authenticate`). Não
renomear chaves locais nem históricos para igualar IDs sem necessidade.

Limite comprovado para preparar/verificar sem habilitação: o endpoint remoto
`POST /connection-test` exige `row.enabled` e consulta `configured`, sujeito ao
master switch. GET retorna apenas `secret_configured`, não prova igualdade do
segredo. Assim, após PUT incerto, um GET de campos iguais não pode ser apresentado
como prova da autenticação bilateral. Também não se pode ativar a conexão apenas
para obter um teste positivo por inferência.

Preparação remota desativada é tecnicamente possível, mas precisa de comando
administrativo explícito, revisão local/remota, estado de resultado desconhecido,
recuperação e indicação de que a autenticação continua não comprovada. Autoconfiguração
com verificação antes da habilitação requer contrato adicional de diagnóstico
sem consentimento financeiro. O fluxo110 abaixo implementa somente essa preparação desativada. Não foi
solicitado segredo real, gerada credencial, alterado PIGE360 ou feito PUT real.


## Preparação remota110 entregue

Editar → Preparar PIGE usa o modal nativo. Exige conexão local desativada, revisão
atual, settings.edit e permissões do ator de faturas/clientes, administrador, CSRF/contexto e entrada HTTPS. O servidor lê
somente o HMAC cifrado da conexão autorizada; não o envia ao navegador. A interface
pede confirmação explícita do destino e da transferência. Não gera novo segredo.

GET confirma ausência de configuração remota. Somente `configuration:null` permite
um PUT, com `version:null`, `enabled:false`, URL confiável do CRM e escopo local.
Configuração existente nunca é sobrescrita, mesmo quando seus campos coincidem.
O PIGE exige CAS para conexão existente; criação concorrente resulta em conflito.
ACL, módulo, revisão, estado desativado e segredo local são revalidados antes/depois
de cada transporte. DNS é validado novamente e fixado por requisição, sem redirects.

Não há retry automático de PUT. Timeout, resposta inválida, mudança local ou
revogação depois do envio são resultado não confirmado: consultar a outra ponta
antes de tentar de novo. Um GET posterior de configuração existente bloqueia novo
PUT; não é convertido em prova de igualdade de segredo. Não existe compensação
por exclusão ou habilitação. Resultado positivo significa somente
`prepared_disabled`, `authenticated:false`; nenhuma delegação ou permissão financeira
é habilitada. A lista local e seus segredos/revisões/históricos ficam intactos.

JWT trafega formulário HTTPS→memória CRM→Authorization Bearer PIGE; HMAC salvo é
decifrado somente no servidor e enviado no JSON PUT ao PIGE autorizado. O próprio
PIGE cifra o HMAC conforme seu contrato. Essa é uma transferência explícita de
credencial existente, sem exportação ao navegador, logs, sessão ou cookies locais.
O PIGE persiste a configuração remota desativada apenas quando o operador executar
o comando; os testes usam exclusivamente segredos sintéticos e transporte simulado.

Evidências110:37 verificações adicionais de contrato após71 da descoberta;13 do
controller com MySQL após as cadeias de configuração/descoberta. Chromium em desktop
e mobile verifica formulário, escopo/revisão/CSRF, confirmação inicialmente vazia,
bloqueio para conexão habilitada, HMAC ausente do formulário e limpeza do JWT.
Runner nativo com metadados simulados:37 checks. Migration110 NOOP preserva109.
Verificação HMAC com conexão desativada, ativação bilateral coordenada e homologação
HTTP/TLS continuam pendentes; preparação não é autoconfiguração financeira completa.

O contrato remoto atual mantém uma configuração administrativa por escola. Várias
conexões locais na mesma empresa continuam possíveis, mas preparar outra conexão
para uma escola já configurada será recusado; não se sobrescreve sua conta/ambiente
nem se afirma que o PIGE oferece várias configurações para aquela mesma escola.
