# Descoberta PIGE — módulo1.0.9

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
null: não é apresentado como autoconfiguração completa. Criar a outra ponta ainda
exige o PUT administrativo e uma estratégia bilateral de revisão/segredo; não foi
implementado nem simulado como sucesso nesta etapa. Não houve alteração no PIGE360.

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
