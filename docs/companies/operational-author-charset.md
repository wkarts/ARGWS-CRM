# Compatibilidade de autores em base com default utf8mb3 ou latin1

O install nativo com utf8mb4 não é tratado como defeituoso. Este ajuste aditivo cobre bases antigas cujo default permaneceu utf8mb3 ou latin1: contatos nativos têm nomes191+191caracteres utf8mb4 e o funcionário nativo50+50, enquanto os snapshots de autor criados430–432 herdavam o default da base.

`CompanyOperationalAuthorCharset::ensure` é executado somente pela migration434, fora de transação de writer. Ele prevalida as famílias e as quatro colunas `author_name varchar(383) NOT NULL`, então converte apenas charset/collation para utf8mb4_bin:

- **430:** `company_portal_support_origins`, `company_portal_support_replies`;
- **431:** `company_portal_support_files`;
- **432:** `company_contract_discussions`.

As definições e migrations históricas430–432 não mudam, mantendo o bootstrap pré434. As colunas novas434 já declaram utf8mb4_bin. O helper aceita os charsets legados latin1/UTF8 conhecidos, preserva largura, obrigatoriedade e valores. Metadado estranho falha fechado. Replay validado não repete ALTER. MariaDB não garante rollback de vários DDLs: uma falha intermediária pode converter colunas anteriores, mas é propagada ao runner e o replay continua sem adotar ou reescrever dados. O marcador nativo só deve avançar depois do passo completo.

Prova MariaDB10.11/PHP8.3: fixture real muda temporariamente o default da base descartável para utf8mb3 e depois latin1 em processo filho serializado, usa contatos/staff utf8mb4, registra origens antigas, injeta falha no terceiro ALTER e verifica preservação de valores/hash; completa/reaplica o upgrade sem novo DDL e executa writers reais430/431/432 com contato191+191Unicode/emoji e funcionário50+50. Também recusa largura inesperada e transação externa. O default original da base é restaurado por shutdown em todas as saídas. Esse teste precisa rodar serialmente e não deve compartilhar o mesmo banco com outro teste/servidor ativo.

Não há backfill de autoria, mudança de texto, publicação, estado ou BLOB. Não houve transporte externo, assinatura, dados reais ou homologação bancária.

No caso latin1, ALTER respeita a codificação declarada: os valores Unicode lidos em conexão utf8mb4 e seus hashes permanecem iguais. HEX físico do nome acentuado muda de latin1 para UTF8, como esperado; snapshots JSON, hashes de conteúdo, recibos e BLOBs de outras colunas permanecem byte a byte iguais. O helper não tenta reinterpretar mojibake ou corrigir o significado de dados históricos.
