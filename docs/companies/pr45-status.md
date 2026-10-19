# Matriz objetiva da PR45

Escopo integral em andamento. Núcleo atual4.1.0/410; BB1.0.6/101–106;
Asaas1.4.8/148. As migrations publicadas402–408 foram preservadas. Não há
merge/deploy/release nem autorização implícita por um resultado de CI.

Os incrementos405 e406 possuem provas MySQL/HTTP/Chromium publicadas. Para407,
a correção de navegação está em44260b61; consultar os checks desse SHA. O incremento408 está validado em a8b17f9 (MySQL8.1/8.5 e stack HTTP/Chromium).
Para410, consultar o HEAD e seus runs antes de atribuir resultado de SHA anterior.
Inventários atualizados localizam revisão; não medem bugs, cobertura ou conclusão.

| Requisito | Implementação atual | Testes executáveis/evidência | Estado e limites |
|---|---|---|---|
| Empresa principal/master | Identidade do instalador preservada; designação legada autorizada com console+senha/hash/expiração/auditoria | Instalação/upgrade, master e concorrência, HTTP/Chromium | Implementado/testado no recorte; designação não prova identidade histórica e depende de console |
| Nova empresa e funcionários | Wizard persistente/revisão; criação/settings/grants/default atômicos; novo funcionário exige empresa/default | Onboarding, native Staff, MySQL com lock observado e navegador | Implementado no recorte; não libera todos os domínios |
| Seletor e contexto | ACL própria, nonce/CSRF/HMAC,409 de aba obsoleta antes de hidratar | Company context e navegador | Preservado; sem replay de POST antigo |
| Clientes privados/opt-in | Diretório positivo criar/editar/ler/buscar/paginar, compartilhar/revogar identidade; recibos duráveis | Clients PDO, concorrência, CRM/Chromium | Fluxo positivo entregue; outros consumidores continuam com barreiras |
| Contatos e portal/recuperação | Contatos próprios; login por owner; A/B com e-mail igual; template/SMTP/fila por origem; reset transacional, anti-replay e sessões | Portal positivo/concorrência/navegador; regressões legadas | Portal de identidade/recuperação; entrega SMTP externa não realizada; documentos/finanças do portal pendentes |
| Settings/SMTP/templates | Identidade canônica, preferências, próprios segredos sem fallback, templates por origem | Suítes settings, queue, portal e HTTP | Parcial; catálogo integral, logos/assinaturas/uploads/integrações restantes pendentes |
| Faturas nomeadas | UI isolada emitir/listar/consultar/editar sem movimento/cancelar; itens/tributo por item, numeração e snapshots atômicos | Invoices PDO, MySQL concorrente, CRM HTTP/Chromium | Incremento408; perfil restrito documentado, validação remota pertence ao SHA final |
| Recebimentos manuais | Entrada explícita no writer; ACL/owner/saldo sob lock, referência, retry, status e auditoria atômicos | Invoices/concorrência e regressões writer Asaas | Registro manual local; não confirma banco; origens BB/Asaas recusadas para revisão própria |
| PDF de faturas nomeadas | DTO autorizado e renderer TCPDF existente; revalidação/auditoria antes do download | PDF binário real, acesso A/B e legado NULL | Somente rota adaptada; caminhos antigos continuam bloqueados para owner positivo |
| Demais operações financeiras | Créditos/refunds/estornos, descontos/ajustes, recorrência, envio/anexos, relatórios/API restantes | Suítes existentes e inventários | Pendências técnicas; gates preservados |
| Projetos/tarefas/despesas/outros módulos | Barreiras e adaptações parciais existentes | Testes por consumidor | Operação positiva completa pendente |
| BB API Cobrança | Executor/configuração segura/cofre e operações ainda ausentes | OpenAPI oficial3.2.2 obtida em19/10, hash no handoff | Pendente técnico/contratual; não há executor disponível nem homologação |
| CNAB240 | Codec P/Q e parser T/U restritos; originação ligada à fatura, reserva durável, retry e download auditado | Fixtures + novos testes de composição/concorrência MySQL no CI410 | Geração local condicionada a contrato/perfil; declaração manual de envio não é aceite; sem transporte ou homologação |
| CNAB400 | Parser CBR643 e candidato CBR641 | Fixtures e PDFs/hash oficiais | Candidato bloqueado: BRL/juros/contrato e picture CBR643; sem homologação |
| Origem bancária e conciliação | Conexões/perfis imutáveis e retorno observacional com dedup/conflito/proveniência | Store/ACL/auditoria/concorrência BB | Origem240 vinculada à fatura; nenhum pagamento bancário automático; evidência de retorno/writer pendente |
| Asaas/OI/notificações | Funcionalidade existente preservada; positivo Asaas continua bloqueado | Workflows próprios; aviso88 checks sintéticos | Regressão por SHA, sem inferir homologação/produção |
| Homologação API/240/400 | Nenhuma realizada | Sem credenciais/transações/arquivos reais | Externa, separada por canal/conta/convênio/carteira |

Contratos detalhados: [clientes](private-customers.md), [portal](portal-recovery.md),
[faturas/recebimentos](invoice-operations.md), [wizard](onboarding.md).

A falta de homologação não elimina as pendências técnicas. Ocorrência06 não autentica
o banco, download não transmite e cancelamento local não confirma baixa remota.
Não aplicar eventos bancários sem origem, evidência canônica e writer transacional.
