# Entrada necessária para assinatura ou aceite de contrato

A conversa operacional não cria evidência de assinatura, aceite ou poderes de representação. Para implementar esse próximo consumidor com significado verificável, é necessário um contrato de produto/autoridade com os seguintes campos concretos:

| Entrada | Decisão verificável necessária |
| --- | --- |
| Ato permitido | Definir se o ato é ciência, aceite operacional, assinatura eletrônica ou solicitação de assinatura externa. |
| Autoridade do signatário | Fonte de poderes do contato para esse cliente/contrato, quem concede/revoga, validade e regra para troca de representante; o grant3 atual permite ler e comentar. |
| Texto e versão | Hash dos bytes do snapshot exibido, identificação inequívoca da versão e política para cancelamento ou alteração antes/depois do ato. |
| Consentimento | Texto exato apresentado, ação afirmativa distinta de comentar, finalidade e consentimentos adicionais exigidos. |
| Identificação/prova | Autenticação e eventual verificação reforçada exigida, comprovante e dados necessários, sem inventar certificação ou validade jurídica. |
| Estado e concorrência | Estados permitidos do contrato, atomicidade com revogação/expiração/cancelamento, unicidade do ato, retry e retificação. |
| Evidência e retenção | Conteúdo imutável do recibo, quem pode baixar, prazo/retificação/exclusão e política aprovada de dados pessoais. |
| Integração externa | Provedor e ambiente, contrato oficial, callback autenticado, estado consultável, retry e critérios de homologação quando houver. |

Com essas definições aprovadas, o escritor poderá revalidar identidade, autoridade específica, versão e consentimento sob lock antes de registrar um ato auditável. Até lá o portal não possui botão ou endpoint que atribua significado jurídico a uma mensagem. Isso é uma entrada de autoridade ainda ausente, não homologação bancária nem limitação do armazenamento de comentários já implementado.
