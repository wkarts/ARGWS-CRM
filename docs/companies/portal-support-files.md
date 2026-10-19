# Anexos de suporte enviados pelo contato — incremento431

O formulário multipart está no detalhe do chamado aberto no portal próprio da
empresa. Usa a permissão nativa `support`5 e identidade persistida de contato e
cliente; `Clients::open_ticket/ticket` e `Tickets_model` são os consumidores nativos
que suportam anexos. Nenhum funcionário é personificado: o BLOB canônico usa actor0,
com origem contato/cliente/empresa/pai e nome snapshot persistidos separadamente.

O envio tem público-alvo explícito na GUI: autor e equipe autorizada da empresa.
Outros contatos do mesmo cadastro, mesmo com permissão5 e ID/chave conhecidos,
não recebem nome, contagem, chave ou bytes. A equipe pode publicar individualmente
um arquivo para ampliar aos demais contatos autorizados do cadastro. Despublicar
retira essa ampliação; o autor mantém seu acesso privado. Ocultar o arquivo bloqueia
todos os contatos, preserva BLOB, hash, origem e recibo e não libera quota histórica.
Compartilhar o cadastro com outra empresa não compartilha arquivo nem contato.

Lista e download resolvem sempre pai persistido, issuer, cliente privado, contato,
grant5, publicação do pai e audiência do filho antes de devolver metadados ou bytes.
Downloads usam o POST autenticado já existente, CSRF nativo e portal_csrf, CSP
sandbox, MIME canônico e attachment. Não há arquivo em diretório público, nova rota
genérica, exceção CSRF, scanner legado, credential/token ou scope API ampliado.
O staff com leitura de tickets na própria empresa mantém o leitor canônico de
arquivos; isso é a equipe explicitamente informada no envio.

Conteúdo e quotas reutilizam as mesmas regras de arquivos operacionais da equipe:
PDF até2 MiB; PNG/JPEG até512 KiB e1600 pixels, normalizados paraPNG;20 uploads e
20 MiB por chamado incluindo ocultos. O parser aceita somente upload HTTP genuíno,
lê tamanho limitado e exige nome/MIME/extensão/conteúdo coerentes. A validação pura
foi extraída para `CompanyOperationFileContent`, usada pelos dois writers; não
possui SQL nem concede autoridade a qualquer recurso.

Upload e ocultação exigem pai publicado, aberto e revisão atual. A origem é derivada
da sessão e revalidada sob lock. BLOB, origem, revisão, auditoria e recibo durável
confirmam ou fazem rollback juntos. Uma mesma chave repete o resultado sem duplicar;
campos mudados conflitam; chave nova com revisão antiga conflita. Recibo antigo
não restaura arquivo ocultado nem contorna revogação. O mesmo recibo usado por outro
contato conflita. Encerrados preservam consulta histórica, sem novos envios/ocultações.

A migration431 acrescenta somente `company_portal_support_files`, com FKs compostas
para arquivo, pai/cliente e contato. Os índices fontes são os criados em428/430.
Nenhum BLOB anterior recebe audiência de contato ou publicação por inferência.
Validadores de engine/colunas/PK/keys/FKs falham fechado; os storages têm participantes
finitos, próprios dos domínios. O armazenamento comum de suporte430 foi extraído para
classe abstrata interna para reutilizar identidade, recibos e revalidação final.

Testes específicos: `portal_support_files.php`, `portal_support_files_controller.php`,
`portal_support_files_concurrency.php`, `portal_support_files_browser.cjs` e
`native_431.php`. Cobrem A/B/principal/NULL, segundo contato do mesmo cliente,
publicação explícita, retiradas, chave/pai trocados, PDF/PNG, quota incluindo ocultos,
receipts, falha tardia/rollback, transação externa recusada e corrupção antes dos bytes.
A concorrência contém18 cenários de dois processos observados esperando no lock real.
Browser usa multipart genuíno, nova conta por GUI+recuperação em fila local e testa
público-alvo, expansão/revogação, ocultação, replay/stale, CSRF e IDs cruzados.
Resultados/ambiente e limites da prova nativa constam no arquivo de integração.

Não há envio SMTP, upload remoto, armazenamento público, assinatura legal ou
homologação bancária. Persistem consumidores nativos específicos de contratos e
portal de tarefas/projetos, que exigem adaptação própria; esta entrega não declara
o legado universalmente concluído.
