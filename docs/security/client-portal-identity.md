# Identidade explícita no portal do cliente

O filtro do diretório administrativo não é uma identidade de autenticação. Contatos de um cliente privado não devem ser desconectados apenas porque o cliente saiu do diretório legado.

`PortalClientIdentity` recebe somente a sessão autenticada do servidor; valida os dois IDs e consulta o vínculo vivo contato→cliente, ambos ativos. Não aceita issuer, contato ou cliente de parâmetros HTTP. A segunda consulta repete o vínculo para detectar revogação entre as leituras. Sem sessão válida, com IDs incoerentes, contato movido ou desativação, não produz identidade. Falha de armazenamento é explícita.

As projeções são listas fechadas. Não retornam hash de senha, chaves de redefinição/verificação, IDs dos provedores ou colunas futuras. O construtor da área cliente usa essa identidade própria; o comportamento de exibição legado e a opção de ocultar VAT permanecem no caminho legado. Não muda a sessão nem cria direitos de emitente, permissão financeira, autorização de diretório administrativo ou regra de compartilhamento.

## Limites de composição

Este ajuste resolve somente a identidade de entrada. Cadastro/perfil privado positivo, documentos e demais leitores do portal ainda exigem revisão e testes integrados. Não desbloqueia adoção nem o gate operacional. Para clientes privados com company vazio, a projeção não inventa razão social a partir de outro contato; o fallback de exibição precisa ser decidido no fluxo privado completo.

## Evidência local

- tests/portal_client_identity.php:45 verificações SQLite, dados sintéticos.
- tests/portal_client_constructor.php:55 verificações combinadas incluindo construtor, logout/mismatch e diretório preservado.
- tests/client_issuer_scope.php:26 verificações de regressão.

Executado em PHP8.5 WASM. Sem MySQL, browser real, autenticação completa ou chamadas financeiras nesta etapa. Ainda não publicado.
