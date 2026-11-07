# Agrupamento CNAB400 — módulo 1.4.1 / migration141

Novos títulos gerados entram automaticamente em um grupo aberto compatível, limitado
a100 títulos. A compatibilidade inclui empresa, conexão/conta/ambiente/convênio,
vínculo de leiaute, configuração e declaração de contrato. Datas incompatíveis não
são forçadas no mesmo arquivo. O codec CBR641 existente produz cabeçalho único,
registros consecutivos e trailer; não concatena arquivos completos.

A migration cria apenas bb_cnab_groups e bb_cnab_group_members, com FKs e associação
única por preparação. Não move origens, não altera snapshots nem reatribui lotes
individuais históricos. Esses lotes permanecem fontes validadas dos títulos; o
grupo é o arquivo efetivamente disponibilizado a partir desta versão. Não há
backfill de arquivos gerados anteriormente.

Primeiro download por qualquer preparação do grupo valida todos os membros,
reconstitui o arquivo pelo codec e congela bytes/hash sob o mesmo mutex financeiro
usado pela emissão. Atualiza os estados locais de download de todos os membros.
Falha de auditoria reverte a operação inteira. Uma emissão concorrente pertence
ao grupo antes do congelamento ou a um grupo novo depois dele, nunca aos dois.
Downloads posteriores conferem o arquivo congelado contra fontes imutáveis e
entregam conteúdo e nome idênticos, sem inserir títulos novos.

A declaração explícita de envio manual abrange todos os membros do arquivo,
preservando referências e recusando conflito. Não comprova transmissão, aceite,
registro ou pagamento. A conciliação mantém a origem individual existente; nenhuma
baixa foi adicionada a este fluxo. O boleto continua imprimível antes do download.

A tela operacional mostra até100 grupos recentes, conta/convênio/ambiente,
quantidade, estado de congelamento e download POST protegido. A consulta não
retorna conteúdo do arquivo nem credenciais. Configuração continua no local nativo.

Os contadores históricos de reserva são preservados: números de arquivo reservados
para fontes individuais podem não aparecer como arquivos exportados separados.
Nenhum número é reaproveitado. Aceitação bancária, inclusive regras operacionais de
sequência do convênio, exige homologação real; testes sintéticos não a comprovam.

Rollback: código anterior à141 desconhece os grupos e pode oferecer arquivos
individuais já incluídos numa exportação. Portanto, não retornar ao exportador
antigo após uso. Suspender novas operações e corrigir adiante, preservando origens,
associações, bytes congelados, auditoria e marcadores.

Emissão automática durante a criação da fatura/ocorrência ainda é a próxima etapa;
esta mudança agrupa títulos gerados pelo fluxo CNAB400 já existente.
