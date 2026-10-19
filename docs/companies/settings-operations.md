# Definições operacionais por empresa

## Grupos disponíveis

Em uma empresa secundária selecionada, `admin/settings` usa exclusivamente o catálogo
nativo `CompanySettingsCatalog`. A página não consulta o catálogo de módulos, hooks,
coleções de gateways ou callbacks legados.

| Grupo | Dados | Consumidores |
| --- | --- | --- |
| `company` | Nome, documento fiscal e endereço canônicos | Cadastro, wizard e novos snapshots documentais |
| `email` | Host, porta, criptografia, remetente, usuário e senha SMTP | Recuperação do portal e notificações de fatura com origem persistida |
| `email_content` | Cabeçalho, assinatura e rodapé HTML | Conteúdo de novos e-mails do portal e das faturas |
| `documents` | Prefixos e condições de faturas e créditos | Escritores nativos de documentos e seus snapshots |
| `localization` | Formato de data e hora | Formatação com contexto explícito |

Os prefixos têm até 50 bytes; as condições, até 10.000 bytes. Não é possível mudar
por este formulário o próximo número nem reescrever o número, identidade ou condições
de um documento emitido. Crédito disponível depende do escritor e gate financeiro
correspondentes; configurar preferências não concede capacidade financeira.

Os valores de uma empresa secundária ficam em `issuers_registry.settings_json` e
não são herdados da principal. A principal conserva seu armazenamento e interface
legados. O armazenamento lê o banco da origem explícita, sem cache compartilhado;
read-after-write e troca A/B não reusam um snapshot de outra empresa.

## E-mails e segurança

A nova apresentação aceita fragmentos HTML completos, com texto formatado, tabelas,
cores e links HTTPS/mailto. Cada campo é normalizado por HTMLPurifier com política
local, sem hooks. Scripts, eventos, formulários, imagens remotas e estilos de posição
não fazem parte da política. O editor mostra o HTML como texto escapado; não injeta
um preview ativo na página. Placeholders de modelos existentes continuam intactos.
Cabeçalho e rodapé devem ser fragmentos independentes; não abrir uma tabela no
cabeçalho para fechá-la em outro campo.

Salvar prepara o lote inteiro antes de escrever; identidade e opções continuam
unidades separadas. O model isolado aplica a mesma validação da interface em chamadas
diretas para SMTP, apresentação e preferências documentais. A gravação, confirmação
e auditoria pertencem a uma única transação; falha tardia reverte todos os campos.
A ACL é revalidada sob a transação. Valores e nomes de opções não vão para o audit.

A senha SMTP nunca é hidratada no formulário. Campo vazio mantém a senha; apagar
exige ação explícita. Falha de criptografia não grava texto aberto. Host, porta,
remetente e demais campos de cabeçalho/transporte têm limites e rejeitam controles/quebras de
linha antes de efeitos. Salvar não consulta o provedor nem comprova suas credenciais.

Alterações de apresentação valem para e-mails enfileirados posteriormente. A mensagem
já persistida conserva o conteúdo e a origem; reenvio da mesma solicitação retorna
a fila canônica. A edição não introduz CC/BCC nem expõe contato de identidade compartilhada.
O worker continua revalidando ator, empresa, destinatário e revisão da fatura antes
de inicializar o transporte. Ausência de segredo em B não recupera o segredo de A.

O selo de requisição permanece obrigatório. O middleware existente recusa tokens
obsoletos com HTTP409 antes do controller/model. Nenhuma nova operação usa parâmetro
HTTP como prova de autoridade ou altera a empresa corrente em background.

## Responsabilidade da instalação

A instalação nova possui prova do colaborador efetivamente criado: o bootstrap
consome uma autorização interna de despacho e `Argws_provisioning::complete_installation`
vincula esse ID, sem presumir ID1. A prova não é uma escolha de um administrador já
logado nem um ID recebido por HTTP.

No legado ambíguo não foi encontrada outra autoridade de recuperação independente.
Continua obrigatório autorizar a designação pelo console da instalação e confirmá-la
na interface com a senha atual do colaborador. O código tem uso único, 15 minutos,
revogação por reemissão, cinco tentativas e auditoria transacional; identidade já
resolvida não é substituída. Ver `onboarding.md` para o comando operacional existente.
**Esta administração ainda não é inteiramente gráfica.** Para substituir o console,
a decisão externa mínima é indicar uma autoridade independente verificável para a
instalação (por exemplo, provedor de identidade de recuperação previamente configurado).
Uma sessão de administrador, o primeiro usuário ou o ID1 não constituem essa prova.
Nenhum mecanismo novo de autopromoção foi introduzido.

## Evidência executada neste incremento

PHP8.3.6/PDO SQLite, dados sintéticos, sem SMTP real:

- `python3 /tmp/run-crm-php-tests.py tests/settings/company_presentation.php`:
  71 verificações adicionais, 205 cumulativas. Executa controller/model/view reais,
  A/B, HTML seguro, segredo ausente da view, preferências de crédito, lote inválido,
  injeção de cabeçalhos e rollback de auditoria. Inclui as 134 verificações anteriores.
- `tests/settings/company_presentation_delivery.php`: 21 adicionais, 106 cumulativas.
  Integra o catálogo com os serviços reais de fatura e fila: A/B enquanto B está
  selecionada, fragmentos próprios, nenhuma cópia extra, conteúdo persistido,
  revalidação worker e retry canônico após edição das preferências.
- Regressões executadas: `company_identity_transaction.php`, `company_mail_config.php`,
  `company_options_helpers.php` e `tests/issuers_master_resolution.php`.

Não somar números cumulativos como casos independentes. SQLite não comprova locks
InnoDB nem transporte SMTP. As provas HTTP/Chromium, MySQL/MariaDB e concorrência são
complementares e precisam ser registradas no SHA integrado pelo executor da PR.

Seções de gateways genéricos, OAuth visual, SMS, Connect e demais grupos ainda sem
consumidor adaptado continuam indisponíveis no catálogo secundário. Não confundir
armazenamento privado de uma opção com implementação operacional da integração.
Não houve merge, deploy ou transação externa.
