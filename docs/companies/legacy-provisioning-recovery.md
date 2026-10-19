# Recuperação gráfica do responsável legado por prova de provisionamento

## Decisão já tomada e estado verificável

O caminho escolhido é usar uma prova de provisionamento da instalação. Não é necessário
repetir uma escolha entre CLI, IdP ou administrador por ID. O legado continua sem uma
prova fornecida que permita vincular essa autoridade à instalação e ao colaborador.
Por isso, a recuperação inteiramente gráfica permanece bloqueada; o fluxo seguro
atual de autorização no console e confirmação pessoal na GUI continua disponível.

Foram examinadas integralmente as duas revisões fornecidas e o código de provisionamento,
bootstrap, identidade e recuperação. Nenhum `provisioning-state.json` real da instalação,
registro independente de autoridade, chave pública de verificação ou cadeia correspondente
foi fornecido. Fixtures e arquivos produzidos nos testes descartáveis não são prova da
instalação do usuário. A presença do código que escreve um registro não prova que esse
registro exista nem que pertença à instalação legada em recuperação.

## O que existe no código

| Fonte | Evidência real que oferece | Limite para legado |
| --- | --- | --- |
| `docker/provisioner.php::write_provisioning_state` | JSON formato1, estágio `base_ready`, ID efetivamente criado, URL e hash do e-mail; escrita atômica com modo0600 | Registro transitório de retomada: é excluído no sucesso; não é assinado, não tem ID imutável da instalação/banco e nenhum exemplar real foi fornecido |
| Marcador final `provisioned` | Timestamp de conclusão com modo0600; o estado transitório é apagado | Não guarda colaborador, prova de origem, assinatura ou vínculo de recuperação |
| `read_provisioning_state` e retomada do provisionador | Confere formato/estágio, URL, hash do e-mail e ID do administrador criado; cruza configuração na retomada autorizada | Retoma instalação nova/incompleta; não importa um registro enviado por administrador como autoridade histórica |
| `InstallationMigrationBootstrap` e `Argws_provisioning::complete_installation` | Despacho interno de uso único ligado ao colaborador recém-criado | A autoridade só existe no despacho autorizado; não pode ser reproduzida por uma requisição de recuperação |
| `Argws_provisioning::apply_migrations` | Ponte CLI e token interno temporário de migrations | Token de execução não é assinatura de identidade nem registro histórico |
| `IssuerMasterResolution` e `Company_master` | Designação nova autorizada no console, código de uso único/15min/5tentativas, senha, revogação e auditoria transacional | Não afirma recuperar quem foi o administrador original; a primeira etapa ainda usa CLI |

Admin ativo, primeiro usuário, ID1, e-mail atual, senha atual, parâmetro HTTP, posse de um
arquivo enviado pelo candidato ou uma nova chave gerada agora não provam a designação
histórica. Uma identidade já resolvida não pode ser substituída por esse fluxo.

## Insumo externo mínimo específico

O operador responsável pelo provisionamento precisa disponibilizar, por canal de
administração da instalação, o registro original e a forma independente de verificar
sua procedência e vínculo. Não colocar senhas, tokens, chaves privadas nem cópias de
banco em ticket, PR ou repositório.

1. Registro original **arquivado** do provisionador daquela instalação, ou registro
   independente equivalente, com ID criado e vínculo verificável ao site/banco correto.
   O JSON formato1 é normalmente apagado ao concluir; não se presume que exista agora.
   Se houver arquivo original preservado, indicar a evidência de procedência/controle
   do arquivo/volume; uma cópia avulsa fornecida pelo candidato não é suficiente.
2. Âncora de confiança verificável e previamente existente: assinatura/registro do
   provisionador com chave pública e impressão digital confirmadas por esse operador,
   ou controle local independente e comprovado do registro original protegido. A
   autoridade não pode ser derivada da mesma sessão de admin que solicita promoção.
3. Contrato de vínculo que permita rejeitar registro de outra instalação/clone e
   identificar o colaborador correto sem presumir o primeiro ID. O formato1 atual
   só tem URL e hash de e-mail; eventual extensão precisa ser definida a partir da
   prova existente, sem fabricar retroativamente um identificador ou assinatura.

É um bloqueio de autoridade e contrato externo, não falta de uma credencial bancária.
Após obter esse insumo, ainda há implementação técnica a fazer: verificador delimitado,
challenge gráfico com validade/uso único/revogação/limites, consumo transacional e
migrations aditivas, recuperação de falhas, auditoria sem segredo, testes de registro
cruzado/falso/expirado/repetido e prova HTTP/concorrência. Esses componentes não foram
apresentados como implementados apenas por existir um formulário ou um armazenamento.

A ausência dessa autoridade não interrompe configurações e consumidores que já têm
ownership e autorização definidos. O overlay de preferências de orçamentos/propostas
avança independentemente, sem mudar a autoridade de instalação.
