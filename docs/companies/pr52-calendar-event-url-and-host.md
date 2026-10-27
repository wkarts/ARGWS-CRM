# PR52 — URL do evento e montagem do modal original

Base75c5633177c85303e02f2a1352aa56d1777970cd. PWA preservado; PR em rascunho e aplicação única ainda incompleta.

## Prova HTTP da composição de transporte

Stack [40435698771](https://github.com/wkarts/ARGWS-CRM/actions/runs/40435698771), job121963728660, ultrapassou a criação no widget, feed HTTP200, igualdade exata do header com o contexto do documento e as assertions do calendário original. Falhou depois em waitForResponse/view_event, linha296, sem aumentar o limite30s. Logo, o bloqueio anterior de header duplicado foi ultrapassado; edição/exclusão ainda não aprovadas.

Artefato issuer-native-browser12704606335 baixado/conferido: ZIP SHA256 `4630b1c132f15b40ab4c7f1d47bce6759a16c0ae87cd12820bbbba03e23aca23`. Não houve restauração de código. O timeout anterior podia escapar do coletor por uma promessa não aguardada conjuntamente ao clique; o smoke agora usa Promise.all e exige que o próprio feed contenha o evento criado antes de tentar abri-lo.

## Duas dependências corrigidas conjuntamente

1. O mapper sempre adicionava url, mesmo undefined. O FullCalendar5.6.0 publicado declara url:String e refineProps transforma uma propriedade existente undefined no texto literal "undefined". O evento passa a ser um link e o handler original de modal não é ligado. O mapper agora omite URL ausente/vazia/null e preserva URLs reais e caminhos nativos. O teste executa o refineProps real, o mapper e o eventDidMount real; reproduz a conversão antiga, confirma ausência de link e disparo do eventid correto, e mantém a navegação de documentos.
2. O rodapé nativo retorna antes de helpers_bottom.php, onde existia o host #event. O mesmo div original foi extraído para utilities/event_mount.php e reutilizado pelos rodapés nativo/legado, sem duplicar markup ou incluir hooks/consultas/modais não adaptados. O teste PHP exige host único e reutilização; o smoke exige um #event real e captura o calendário com campos mascarados antes da abertura. Não é outra tela, rota ou aplicação.

ACL, CSRF, revisão, tokens antigos, fonte/empresa e reautorização do modal permanecem. Não se aceita header concatenado nem se libera fonte desconhecida. O handler de clique original foi preservado; não foi contornado pela chamada direta da API no teste.

## Memória do fixture PDF, mantendo os casos

Empresas [40435698896](https://github.com/wkarts/ARGWS-CRM/actions/runs/40435698896), job121964332367/PHP8.1/shard4, falhou no registry342, post_refund_manual_cycle_principal.php, ao carregar dejavusansb com128MiB. Após as234 verificações acumuladas do pai e a confirmação do ciclo da principal, o fixture libera somente os registros diagnósticos de consultas antes do PDF real. Não muda banco, DTO, histórico financeiro, fontes, assertions, casos seguintes ou limite. O teste continua gerando o PDF e conferindo a nova fatura independente e os históricos. A nova execução ainda deve comprovar esse ajuste; não é anunciado defeito de runtime da aplicação nem CI aprovado.

## Banco do Brasil: resultado concreto da base e0

BB [40433128257](https://github.com/wkarts/ARGWS-CRM/actions/runs/40433128257), job121955367969/MariaDB **aprovado**:52 verificações com mysqli original/dois processos; API e concorrência SQL com transporte simulado; tracking/upgrade reais1.2.0/1.2.1/1.2.2→1.3.2 (254 verificações adicionais por filho histórico,55 no compositor). Unitários PHP8.1/8.5 e checkout aprovados. MySQL ainda executava na última consulta. Isso é prova de componente nessa base, não homologação externa, CNAB400 integral ou aprovação do novo SHA.

## Verificação local e pendências

Executados: Node/FullCalendar DateEnv/refineProps/mapper/mount, transporte e casos de recusa, sintaxe JS/bash, dois testes Python e reconciliação de27 fontes existentes +131/132/event_mount contra artefato completo76d9ba4, com hashes-base conferidos.15085 fronteiras/4611 fontes operacionais/647 itens no auditor; operacional_ready=false. Fonte do FullCalendar não alterada. Espaço27GiB disponível na última consulta. PHP/Docker/banco ausentes localmente: lint PHP, novo host/PDF e fluxo autenticado dependem do CI. Os testes focados75 passaram em PHP8.1/8.5; não validam as novas alterações.

Permanecem48 controllers paralelos/51 views com shell, retirada física restante, Staff/configurações/financeiro/anexos completos, CNAB400 por fatura, rejeitados e demais integrações, matriz integral e instalação PWA real/standalone. Sem alteração de arquivos protegidos, migrations antigas, PIGE360, serviços externos, merge, deploy ou force-push.
