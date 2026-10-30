# Portal por origem, contatos e recuperação

`admin/clients?source=company` administra contatos próprios sem carregar Clients_model.
Nome, sobrenome e e-mail têm projeção explícita; senha/chaves e permissões financeiras
não são retornadas. Reenvio idêntico da inclusão retorna o contato existente. E-mail
repetido em outra empresa é permitido; na mesma empresa há conflito explícito sem
expor o cadastro. Edição exige fingerprint e proprietário sob transação. Alterar
endereço de e-mail invalida tokens de recuperação e sessões deste portal.
Compartilhamento da identidade de cliente não compartilha contatos ou autenticação.
O formulário informa o link do portal e a necessidade de configurar SMTP próprio.

`company_portal/index/{empresa}` é uma rota pública nativa isolada antes do autoload
legado. Não enumera empresas, não muda CompanySettingsRuntime, não carrega gateways,
Clients_model, tema legado ou módulos. O ID da rota delimita a busca, mas não concede
acesso: login requer e-mail/senha do contato ativo, cliente ativo e owner persistido.
Legado sem owner resolve exclusivamente a principal persistida, sem adotar o cliente.
O login global antigo recusa e-mail ambíguo em vez de escolher arbitrariamente.

A recuperação usa `CompanyPortal`, templates da origem, remetente/assinatura próprios
e `CompanyMailConfig` com descriptografia sem fallback. Gera bearer aleatório256bits,
armazena SHA256 no registro de tokens e vincula contato, cliente, empresa e HMAC de
e-mail/senha. Token/queue/auditoria são confirmados juntos; falha não deixa convite
órfão ou sucesso falso. Janela de60s por token pendente e limite persistente por
IP/empresa/ação reduzem reenvio e tentativas (5 recuperações ou20 logins/resets em15min).
Não há resposta pública que confirme existência da conta ou configuração SMTP.

O link carrega o bearer no fragmento, removido pelo navegador antes do formulário.
O token só é submetido no POST com CSRF próprio e o CSRF nativo quando habilitado;
não vai em query/referrer nem é reexibido após POST. CSP nonce, no-store e no-referrer
protegem a página. Registro de token contém somente digest; o corpo da fila contém
o link de uso único, como qualquer e-mail de recuperação, e deve ser protegido pelo
acesso operacional ao banco. A UI legada da fila não lista origens nomeadas.

O reset exige senha mínima12, origem e identidade ainda coerentes, expiração1h e
lock transacional. Consumo e senha são atômicos; auditoria/commit falhos permitem
retentar com a senha antiga preservada. Replay, mudança de owner/e-mail/senha ou
inatividade recusam o token. Apaga cookies remember-me do contato sem afetar staff
com mesmo ID. Sessão dedicada armazena origem/IDs/HMAC e revalida o estado a cada
página; senha/e-mail alterados ou revogação invalidam a sessão. Não cria sessão
administrativa nem transfere sessão para outra empresa.

Migration407 aditiva, InnoDB, tipos, PK integral/collation e FK de empresa validados,
com replay e recusa de downgrade destrutivo. Preserva 402–406 e módulos publicados.

## Evidência executável e limites

- `tests/companies/portal_positive.php`: contatos A/B com e-mail igual, share sem
  contatos, queue/template/remetente/assinatura, tokens, login/sessão/reset, rollback,
  revogação de cookie e limites persistentes; PDO SQLite ou MySQL.
- `tests/companies/portal_concurrency.php`: duas conexões MySQL observadas no lock,
  recuperação concorrente, único reset vencedor e revogação durante espera.
- `tests/companies_portal_browser.cjs`: CRM real, cadastro e SMTP por GUI, contatos,
  CSRF, recuperação/queue, fragmento, reset, login A/B, replay e revogação.
- Suites legadas de recuperação/autologin e SMTP/worker continuam executadas.

O estado efetivamente testado pertence ao SHA e aos runs na PR45. Fixtures não
contêm credenciais ou clientes reais. Browser testa fila; não entrega e-mail externo.
O worker nativo consome a origem durável e suas regressões de transporte permanecem
separadas. Entrega real pelo SMTP contratado ainda requer configuração e teste
controlado autorizado. Não foi feita entrega externa nesta implementação.

Este portal oferece identidade e recuperação. Documentos, finanças, anexos, projetos,
relatórios e demais serviços precisam de adaptação positiva própria; não foram
abertos por este endpoint. Nenhuma autenticação deste portal libera operações BB.
Não há homologação bancária, merge ou deploy associado a estes testes.

## Portal de faturas no incremento414

Após autenticar, o contato vê lista e baixa o PDF somente quando possui a permissão
nativa de faturas1. O cadastro de contatos permite conceder/revogar esse acesso com
clientes Editar e faturas Visualizar do proprietário. Contatos novos continuam sem
acesso financeiro. A permissão não se transfere por identidade compartilhada.

A consulta exige empresa de login, cliente autenticado e fatura nomeada; não adota
fatura NULL legado nem expõe documento de outro cliente da mesma empresa. PDFs usam
DTO/TCPDF nativos, sem tema/modelos financeiros legados. O download requer POST e
CSRF, revalida após renderização e confirma auditoria antes dos bytes. Mudar senha,
e-mail, owner, ativação ou permissão revoga os acessos correspondentes.

`tests/companies/portal_invoices.php` cobre nativamente permissões, A/B, outro
cliente, identidade compartilhada, PDF, auditoria e falha. `_invoice_concurrency.php`
observa dois leitores no lock real e revoga antes de liberar. O navegador exercita
as rotas/UI reais. Consultar os runs do SHA final; créditos, outros documentos,
pagamentos no portal e entrega SMTP externa continuam fora deste recorte.

No415, o helper de permissões do tema legado continua negando contatos privados.
A concessão nativa1 só abre a lista/PDF do portal isolado com owner e cliente
comprovados; não abre automaticamente as antigas páginas de faturas/outros módulos.

No416, rascunhos e estados inválidos não aparecem na lista e não podem ser baixados
por ID. A condição de fatura emitida também é revalidada antes de liberar os bytes.
