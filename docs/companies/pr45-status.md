# Matriz objetiva da PR45

Este é um estado parcial verificável, não uma declaração de conclusão do escopo total.
O primeiro incremento de responsabilidade/wizard é `30fb0f10a3ab5eac598d795ef9c712a1187b8364`.
Se o HEAD avançar, consultar o CI dessa revisão antes de atribuir-lhe resultados antigos.

| Requisito | Implementação | Evidência | Situação |
|---|---|---|---|
| Empresa principal e administrador provado pelo instalador | Preservados, sem presumir ID1 | Instalação/upgrade e núcleo MySQL | Implementado/testado no recorte |
| Legado sem prova do original | Designação autorizada; console + senha no CRM; hash/expiração/rate limit/auditoria | `issuers_master_resolution.php`, HTTP/Chromium | Implementado/testado; não afirma identidade histórica |
| Nova empresa no núcleo | Wizard persistente, revisão, criação/config/grants atômicos | `issuers_onboarding.php`, HTTP/Chromium | Implementado/testado; compartilhamento ainda indisponível |
| Colaborador novo com empresa/default | Recusa prévia sem acesso; grants por recurso; falha parcial de perfil/grants explícita | `issuers_staff_native.php`, formulário nativo HTTP | Implementado/testado no fluxo descrito |
| Corridas e revogação | Lock nativo + revisão + verificação ativa de master | Teste MySQL de duas conexões e Performance Schema | Consultar CI do HEAD com `issuers_onboarding_concurrency.php` |
| Configuração/SMTP/modelos por empresa | Adaptações existentes e credenciais sem fallback | Suítes settings/mail/context | Parcial; não prova entrega de e-mail externo |
| Clientes privados/compartilhamento/portal/reset | Serviços e barreiras existentes; rotas positivas incompletas | Testes de barreira e fixtures documentadas | Pendente técnico |
| Financeiro/projetos/tarefas/despesas/relatórios/arquivos | Consumidores parcialmente adaptados; gates preservados | Suítes e inventários conservadores | Rollout completo pendente técnico |
| API/cron/jobs/módulos/webhooks por empresa | Exigem origem explícita em cada consumidor | Testes específicos existentes | Não declarado universalmente concluído |
| BB API Cobrança | Executor e cofre/configuração ainda ausentes | Swagger público 403; OpenAPI atual ainda não obtida | Pendente técnico e contrato oficial |
| CNAB240 | Codec P/Q e parser T/U restritos | Fixtures + MySQL/driver BB | Sem orquestração operacional de emissão/lotes/download |
| CNAB400 | Parser CBR643; candidato CBR641 bloqueado | Fixtures e PDF canônico revalidado | BRL/picture/contrato pendentes; candidato não homologado |
| Origem bancária imutável ligada à fatura | Conexões/perfis imutáveis; tabelas de origens/lotes ainda não ligadas à emissão | Store/ACL/proveniência | Originação/reserva/lotes pendentes técnicos |
| Retorno/baixa financeira | Importação observacional transacional, dedup/conflito/auditoria | Store e corridas BB | Nenhum pagamento/baixa criado; conciliação financeira pendente |
| Asaas/notificações | Preservados; Asaas positivo continua bloqueado | Workflows próprios + aviso 88 checks sintéticos | Regressões aprovadas no SHA de referência |
| Homologação API/240/400 | Nenhuma realizada | Sem credenciais, transações ou arquivos reais | Externa, separada por canal/contrato |
| Release | Branch PR45 atualizada; migrations preservadas | Gates de versão e ancestralidade | Sem merge/deploy/autorização de release |

A homologação externa não substitui a implementação que ainda falta. Uma ocorrência de
retorno 06 não autentica o banco, download não transmite arquivo e exclusão local não
comprova baixa bancária. Não aplicar pagamentos sem origem, evidência e escritor aprovado.
