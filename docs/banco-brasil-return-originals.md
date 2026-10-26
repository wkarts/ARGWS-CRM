# Originais de retorno Banco do Brasil —1.3.0 / migration130

O importador atual valida o perfil240/400 declarado antes de registrar o arquivo.
Além do hash e das observações existentes, passa a reter os bytes exatos, incluindo
CRLF, em `bb_return_originals`, vinculados a arquivo, empresa, conexão e escopo.
O conteúdo privado não entra na projeção da grade, em logs de consultas ou em
cache HTTP/PWA. Não contém credenciais API e não é uma prova de autenticidade
bancária.

## Atomicidade e histórico

A migration130 cria somente uma tabela InnoDB adicional, com chave única completa
por arquivo. Valida tipos, nulabilidade e constraints existentes; não corrige
silenciosamente uma tabela incompatível. Repetição é permitida. DDL dentro de
transação alheia ou com configuração incompatível é recusado. Migrations101–129
permanecem intactas; a versão exata1.3.0 só autoriza serviços após tracking nativo.

Original, arquivo, eventos, vínculos e auditoria são gravados na mesma transação
de importação. Falha ou INSERT ignorado do original aborta o conjunto. Hash,
tamanho, escopo e conteúdo são conferidos após a gravação e antes do commit;
original corrompido ou duplicado não é aceito. Reimportação não recria eventos
nem pagamentos. Arquivo distinto que repacote o mesmo evento conserva seu próprio
original, mantendo a deduplicação semântica já existente.

Históricos anteriores são mostrados como original não retido. Não há backfill de
conteúdo a partir de observações. Uma reimportação dos bytes exatos, validada pelo
parser e pelo hash já registrado, pode recuperar o original ausente; o cadastro
histórico e seus eventos não são reatribuídos nem recriados.

## Download nativo

`POST /admin/banco_brasil/download_return` usa formulário oficial, CSRF do módulo,
CSRF/contexto nativo e vínculo da empresa ativa. O serviço revalida módulo/core,
view+download, ator ativo, empresa, conexão e hash. Audita o acesso em transação
própria e confirma a persistência antes de responder. A permissão não depende da
ativação atual de geração CNAB/API: desligar um canal preserva o histórico.

A resposta é attachment de nome gerado pelo servidor, `application/octet-stream`,
`private, no-store`, `nosniff`; não existe URL pública ou GET para os bytes.
Arquivo de outra empresa, original ausente/corrompido e formulário aberto antes
da troca de empresa são recusados.

## Validação adicionada, ainda dependente do CI do commit

- Unitário do store: bytes/hash, corrupção, duplicidade de linhas, gravação
  recusada/ignorada e rollback integral; a grade não projeta conteúdo.
- MySQL/MariaDB e driver nativo: esquema aditivo repetível, tipos/unique,
  original240/400, reimportação, recuperação por bytes exatos e engine incompatível.
- Fluxo do core: download autorizado, empresa diferente, corrupção e ator revogado.
- Chromium autenticado: upload240, download byte a byte, headers privados e409 no
  formulário anterior à troca de empresa. Os demais cenários financeiros continuam.

O executor local não dispõe de PHP, Docker ou banco. Verificações Node/Python e
revisão de código não são aprovação desses testes PHP/banco/navegador.

## Limites que permanecem

Arquivos que falham na validação estrutural não são retidos por este incremento;
diagnóstico persistente de linhas rejeitadas ainda precisa ser concluído. O
arquivo original validado não torna remessa400 operacional nem implementa novas
ocorrências bancárias. A integração400 com faturas e a conciliação completa
continuam bloqueadas pelas pendências internas e documentais registradas na PR.
Não foi enviado arquivo ao banco, registrada cobrança real ou criada liquidação
externa para testar. PR permanece em rascunho.
