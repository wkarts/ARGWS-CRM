# Contrato de retomada local — ARGWS-CRM

## Objetivo
Retomar o checkpoint local da PR 52 e continuar a convergência dos fluxos de empresa para os controllers, views e integrações oficiais, sem publicar no GitHub.

## Restrições
- Trabalhar somente no workspace local.
- Preservar isolamento por empresa, permissões, CSRF, idempotência e compatibilidade histórica.
- Não alegar homologação externa do Banco do Brasil sem evidência documental e ambiente bancário.

## Critérios de aceite do incremento
- O cadastro de cliente próprio da empresa principal usa `/admin/clients/client?source=company` e o adapter `NativeCompanyClients`, sem `clients_model`.
- A ação fica vinculada ao contexto atestado no servidor e ao CSRF nativo.
- Formulário, retorno e abas preservam o marcador oficial.
- Testes estáticos atualizados e executados; limitações de runtime registradas.
- Novo checkpoint local persistido com evidências e próxima ação objetiva.
