# Painel Asaas 1.5.6

## Organização e compatibilidade

- **Geral** apresenta regras de pagamento. Os valores já persistidos continuam
  separados por Produção/Homologação, inclusive quando diferem: esta alteração
  não escolhe silenciosamente um vencedor, não migra dados nem unifica opções.
- **Autenticação** reúne conexão, recursos, diagnósticos e endereços de recebimento
  em subguias Produção/Homologação. O único campo operacional é **Ambiente ativo**;
  navegar nas subguias não altera esse campo. A mudança só persiste ao salvar.
- **Webhook e operações** consulta os mesmos controllers protegidos existentes,
  mostrando eventos, automações pendentes e origens/conciliação dentro do painel.
  A primeira abertura só faz GET local; não consulta o Asaas nem gera tokens.
- Chaves, tokens, nomes de opções, origem histórica de cobranças, ACL e validação
  de `asaas-access-token` não foram alterados. Não existe modo aberto sem token.
- O link de retorno utiliza `payment_gateways`, não o grupo inexistente
  `online_payment_modes`. No painel integrado o retorno preserva campos não salvos.

## Formulários e ações administrativas

Os controllers podem renderizar somente o conteúdo com `panel=1`. O transporte
AJAX aceita somente origem e rotas administrativas Asaas conhecidas. Cada form
recebe um proprietário independente, vazio e fora do formulário de configurações;
seus controles mantêm associação via atributo HTML `form`. Assim, tokens CSRF,
IDs e ações de conciliação não entram no POST de configurações. Os POSTs só são
feitos após clique explícito; falha de rede não dispara retry automático.

Os endpoints e proteções existentes continuam responsáveis pela autorização,
CSRF, leitura de conta e efeitos de cada ação. Recuperar token exige ação explícita;
a resposta mantém o segredo mascarado. Sair da guia ou ocultar o documento mascara
o token novamente. Não é feita criação/rotação de token durante GET ou navegação.

## Migração e verificação

Migration156 é NOOP e apenas registra a versão da mudança de apresentação.
Não modifica schema, opções, contas, credenciais ou histórico. O incremento do
núcleo é coordenado pela PR de integração, não por este componente isolado.

Testes:

```sh
node tests/asaas_settings_tabs.test.js
node --test tests/asaas_settings_real_browser.test.cjs
node tests/asaas_settings_tabs.browser.cjs
node tests/asaas_operations_panel.browser.cjs
php tests/asaas_settings_presentation.php
php tests/asaas_settings_policy.php
```

Os dois testes Playwright usam dados sintéticos e bloqueiam acesso externo.
O teste de operações substitui fetch por respostas locais sintéticas e cobre
CSRF/form ownership, navegação, ausência de POST automático e mascaramento.
A validação real existente continua restrita ao ambiente CI descartável.

Não confundir testes de apresentação com homologação financeira, comprovação de
cadastro remoto de webhook ou execução da migration em produção. PHP/browser
não estavam executáveis no ambiente de autoria; exigir CI antes de aprovar.
