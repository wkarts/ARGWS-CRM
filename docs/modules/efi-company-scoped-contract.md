# Efí por empresa — extensão isolada em implementação

Checkpoint legado `c5c87c0b` permanece issuer NULL. A extensão não abre suas
rotas nem utiliza opções globais. Não está homologada nem validada em PHP/MySQL.

## Armazenamento aprovado

Quatro tabelas InnoDB, criadas apenas pelo núcleo 473, com seed equivalente:
`efi_company_accounts`, `efi_company_origins`, `efi_company_proofs` e
`efi_company_receipts`. `EfiCompanySchema::FIELDS/KEYS` definem tipos e índices;
execução normal apenas verifica metadados, sem DDL na migration modular 515.

A identidade de conta (empresa, ambiente e SHA256 de client_id) é imutável.
Uma mesma identidade/ambiente não pode pertencer a duas empresas. Credenciais
são cifradas pela infraestrutura nativa com envelope ligado à empresa/ambiente,
incluindo hash do certificado cifrado. Rotação é explícita e muda revisão;
revogação preserva histórico, invalida operações novas e impede aplicação de
prova antiga. Nenhuma chave ou identidade é gerada automaticamente.

A origem é também reserva durável UNIQUE(invoice_id), confirmada antes de POST.
Retorno incerto nunca autoriza novo POST. Prova sanitizada, vinculada à origem e
revisão, é append-only no serviço. Respostas de tentativas cuja autorização foi
revogada ficam em quarentena, não são autorização financeira. Baixa usa fonte
concreta allowlisted no escritor financeiro nativo e revalida ACL/owner/empresa/
conta/ambiente/revisão/valor/moeda e prova; callback não fornece esses valores.

## Limites explícitos e provas pendentes

A/B, cross-environment, revogação durante rede, replay, concorrência real,
rollback e paridade instalação/upgrade devem ser executados antes de declarar
suporte empresarial operacional. O trabalho local inicial é implementação,
não resultado desses testes. Cartão não confunde `paid` com liquidação; sem
prova de recebível liquidado fica em revisão. Datas financeiras vêm de consulta
autenticada, jamais da data atual ou de texto livre do histórico. Tokenização
scoped não pode herdar JavaScript/configuração global.

Fences reversos BB/Asaas/manual são obrigatórios antes de liberar emissão,
para impedir origens concorrentes. Bumps reservados pela integração: BB 126,
Asaas 159; patches desses módulos dependem da ponte 125/quarentena 158.
