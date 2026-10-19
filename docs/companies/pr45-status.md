# Matriz objetiva da PR45

Escopo integral em andamento. Núcleo atual4.1.5/415; BB1.0.8/101–108;
Asaas1.4.8/148. As migrations publicadas402–412 foram preservadas. Não há
merge/deploy/release nem autorização implícita por um resultado de CI.

Os incrementos405 e406 possuem provas MySQL/HTTP/Chromium publicadas. Para407,
a correção de navegação está em44260b61; consultar os checks desse SHA. O incremento408 está validado em a8b17f9 (MySQL8.1/8.5 e stack HTTP/Chromium).
O baseline412 (7aa56e60) passou nos16 workflows, incluindo originação e retorno
assistido por HTTP/Chromium. 413/API passou em MySQL/PHP8.1/8.5 e HTTP/Chromium. O job MariaDB detectou
cache de locks no teste e SQL NULL no metadata do núcleo/templates/fila, tratados nos incrementos
seguintes. Para415/portal/MariaDB, consultar o HEAD e seus runs;
não atribuir os resultados412 aos novos testes.
Inventários atualizados localizam revisão; não medem bugs, cobertura ou conclusão.

| Requisito | Implementação atual | Testes executáveis/evidência | Estado e limites |
|---|---|---|---|
| Empresa principal/master | Identidade do instalador preservada; designação legada autorizada com console+senha/hash/expiração/auditoria | Instalação/upgrade, master e concorrência, HTTP/Chromium | Implementado/testado no recorte; designação não prova identidade histórica e depende de console |
| Nova empresa e funcionários | Wizard persistente/revisão; criação/settings/grants/default atômicos; novo funcionário exige empresa/default | Onboarding, native Staff, MySQL com lock observado e navegador | Implementado no recorte; não libera todos os domínios |
| Seletor e contexto | ACL própria, nonce/CSRF/HMAC,409 de aba obsoleta antes de hidratar | Company context e navegador | Preservado; sem replay de POST antigo |
| Clientes privados/opt-in | Diretório positivo criar/editar/ler/buscar/paginar, compartilhar/revogar identidade; recibos duráveis | Clients PDO, concorrência, CRM/Chromium | Fluxo positivo entregue; outros consumidores continuam com barreiras |
| Contatos e portal/recuperação | Contatos próprios; login por owner; A/B com e-mail igual; template/SMTP/fila por origem; reset transacional, anti-replay e sessões | Portal positivo/concorrência/navegador; regressões legadas | Portal de identidade/recuperação; entrega SMTP externa não realizada; 414 adiciona lista/PDF de faturas próprias com permissão nativa explícita; demais documentos/operações do portal pendentes |
| Settings/SMTP/templates | Identidade canônica, preferências, próprios segredos sem fallback, templates por origem | Suítes settings, queue, portal e HTTP | Parcial; catálogo integral, logos/assinaturas/uploads/integrações restantes pendentes |
| Faturas nomeadas | UI isolada emitir/listar/consultar/editar sem movimento/cancelar; itens/tributo por item, numeração e snapshots atômicos | Invoices PDO, MySQL concorrente, CRM HTTP/Chromium | Incremento408; perfil restrito documentado, validação remota pertence ao SHA final |
| Recebimentos manuais | Entrada explícita no writer; ACL/owner/saldo sob lock, referência, retry, status e auditoria atômicos | Invoices/concorrência e regressões writer Asaas | Registro manual local; não confirma banco; origens BB/Asaas recusadas para revisão própria |
| PDF de faturas nomeadas | DTO autorizado e TCPDF; staff e portal privado com permissão nativa explícita; revalidação/auditoria antes dos bytes | PDF real, A/B, legado NULL e testes414 de revogação/HTTP | Lista/PDF de faturas do cliente e empresa próprios; sem conceder demais operações ou histórico compartilhado |
| Demais operações financeiras | Créditos/refunds/estornos, descontos/ajustes, recorrência, envio/anexos, relatórios/API restantes | Suítes existentes e inventários | Pendências técnicas; gates preservados |
| Projetos/tarefas/despesas/outros módulos | Barreiras e adaptações parciais existentes | Testes por consumidor | Operação positiva completa pendente |
| BB API Cobrança | Perfis/credenciais criptografadas nativas; OAuth, registro, consulta com prova de conta e pedido/confirmação de baixa; outbox e origem imutável | Cliente HTTP simulado24 checks; composição MySQL/concorrência e MariaDB adicionadas no413, resultado depende do SHA final | Perfil17/simples/BRL sem ajustes/Pix; sem pagamento API automático. Alteração, webhook mTLS e aplicação financeira ainda pendentes; nenhuma homologação |
| CNAB240 | Codec P/Q e parser T/U restritos; originação ligada à fatura, reserva durável, retry e download auditado | Fixtures + novos testes de composição/concorrência MySQL/HTTP no baseline412 | Geração local condicionada a contrato/perfil; declaração manual de envio não é aceite; sem transporte ou homologação |
| CNAB400 | Parser CBR643 e candidato CBR641 | Fixtures e PDFs/hash oficiais | Candidato bloqueado: BRL/juros/contrato e picture CBR643; sem homologação |
| Origem e conciliação | Conexões/perfis imutáveis, upload observacional; vínculo exato e revisão; crédito240 restrito confirmado pelo operador via writer nativo | Store/ACL e novos testes MySQL/concorrência/HTTP412 | Assistido, sem autenticação automática do arquivo. Parcial sem ajustes suporta saldo; juros/desconto/tarifa/estorno financeiro e automação permanecem pendentes |
| Asaas/OI/notificações | Funcionalidade existente preservada; positivo Asaas continua bloqueado | Workflows próprios; aviso88 checks sintéticos | Regressões do writer e workflows por SHA; sem inferir homologação/produção |
| Homologação API/240/400 | Nenhuma realizada | Sem credenciais/transações/arquivos reais | Externa, separada por canal/conta/convênio/carteira |

Contratos detalhados: [clientes](private-customers.md), [portal](portal-recovery.md),
[faturas/recebimentos](invoice-operations.md), [wizard](onboarding.md).

A falta de homologação não elimina as pendências técnicas. Ocorrência06 não autentica
o banco, download não transmite e cancelamento local não confirma baixa remota.
Não aplicar eventos bancários sem origem, evidência canônica e writer transacional.
