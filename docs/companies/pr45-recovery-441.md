# API de despesas — composição441

Núcleo4.4.1/441, BB1.2.0/120 e Asaas1.5.3/153. A migration441 é o marcador nativo semDDL gerado pelo instrumento oficial; todas migrations anteriores permanecem intactas. Esta composição acrescenta a verticalAPI privada de despesas, categorias e arquivos, sobre os writers nativos existentes. Não implementa ainda cópia de despesa ou ledger não-caixa.

## Implementação e recuperação

Os três patches sobreviventes foram integrados sobre440, resolvendo aditivamente os scopes/labels e as exclusõesCSRF finitas; drafts/cópias/operadores anteriores permanecem.14arquivos de componente e5centrais;10fontes produtivos conferidos com o pacote. Os sete scopes opt-in não ampliam tokens antigos. Revisão/hash/idempotência, grants atuais, pai/filho, quotas e confirmação de bytes usam os serviços próprios. Não há sessão global, adoçãoNULL ou escritor financeiro alternativo.

Um teste novo explicitamente recriado reproduziuRED no440: o download nativo podia liberar bytes após a auditoria ocultar/revogar a fonte. O441 confirma novamente arquivo/origem/ator após a auditoria e reverte o efeito; GREEN65checks emSQLite eMaria. Os demais testes do componente sobreviveram; não se atribui equivalência aos154testes439 perdidos.

## Provas

Componente: cinco suítes nos dois bancos, concorrência sete cenários com dois waiters reais e16regressões. Composição441:11execuções, incluindo controller154/atomic129/billing103/races134/reader65 e recorrência/financeiro/drafts/cópias. Contadores são cumulativos, não somáveis.

Nativebody1251checks, migration441/replay sem tabela nova e históricos completos byte-exatos. Executor real203checks com falha do marcador, falso retorno de gravação sem readback, retry/tracking440→441 e replay. O teste final prepara uma fundação440 já reconciliada e permite apenas CREATE IF NOT EXISTS de tabelas existentes da reconciliação nativa. Erros de autoria das duas primeiras tentativas estão preservados em evidência, sem alteração de produção para satisfazê-los.143testesPython passaram.

Instalação441 e upgrade440→441 reais, commaster17/sourceinstaller/CSRF, oito tabelas históricas inalteradas e18.512arquivosruntime iguais ao fonte congelado18.526. FluxosAPI A/B/principal/restrito, GUI de scopes, cookie/token, valores/limites de arquivo, retries/revisões/rollback, auditoria tardia, bytes/HEAD e pais forjados passaram após fresh e upgrade. Detalhes em pr45-validation-441.md e evidence/http-resume441.json. Os helpers dos executores históricos439/440/441 validam VERSION contra config, fixam explicitamente sua fronteira histórica e passaram com8/10/203checks sob441. O CI439 encontrou duas falhas de preparo: pdftotext ausente no contêiner e fixture Asaas fixo em152 diante de153. Apenas441 recebe poppler-utils no workflow e o helper confiável da versão corrente; SalesPDF192 e os fluxosAsaas originais passaram novamente. O smoke conserva72comandos anteriores e acrescenta somente a prova441, mantendo infraestruturaTLS local440.

Manifesto produtivo SHA256a612e5cc6b35943f18b0d0cfa14179a6963948752f52935b0904ac3b507dbd88. CI do próprioSHA deve ser registrado separadamente da prova local; nenhum resultado anterior é atribuído a esta composição. Sem homologação externa, merge ou deploy. Pendências de banco e demais consumidores/settings permanecem discriminadas, sem chamar código faltante de mera homologação.
