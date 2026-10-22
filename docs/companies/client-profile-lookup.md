# Cadastro brasileiro de clientes

O formulário isolado e o formulário nativo da empresa principal reutilizam as consultas explícitas de CNPJ (ReceitaWS, terceiro) e CEP (ViaCEP, terceiro). Nenhum campo é alterado até selecionar e aplicar a prévia editável. Campos preenchidos começam desmarcados; uma edição posterior invalida a aplicação. RG não é enviado aos provedores. CPF não é consultado. Cadastro manual continua disponível em falhas e limites de consulta.

## Persistência e compatibilidade

- `clients.company/vat/address/city/state/zip/country/phonenumber` continuam canônicos. Não se criam campos paralelos para esses dados; país retornado é resolvido pela tabela nativa.
- `company_client_profiles` guarda somente campos suplementares privados por `(issuer_id, client_id)`: tipo PF/PJ, nome fantasia, RG, IE, número, complemento, bairro e código do país. RG e IE são opcionais; IE aceita ISENTO. PF não recebe IE/fantasia; PJ não recebe RG pelo formulário.
- A projeção compartilhada permanece exatamente a existente, sem perfil suplementar. Clientes legados sem proprietário só podem receber perfil no contexto primário. APIs/importações que omitem `profile`/`brazil_profile` preservam comportamento e dados existentes. Campos personalizados antigos não são apagados nem migrados automaticamente para evitar associação ambígua.
- As consultas automáticas antigas por blur/JSONP foram removidas e o asset substituído por máscaras locais. Endereços de cobrança/entrega não são sobrescritos.
- Contratos de PDF/API/exportação e documentos históricos permanecem iguais. RG/IE/nome fantasia e componentes suplementares não são acrescentados automaticamente aos PDFs nem às projeções compartilhadas. O endereço nativo continua sendo o logradouro.

## Migração e segurança

A migração central 458 deve chamar `CompanyClientProfileSchema::ensure($db, db_prefix())`. O helper é aditivo e idempotente, sem migração histórica, backfill ou cópia de RG. O FK issuer é `int unsigned`, client é `int` signed, coerente com as tabelas existentes. A PK é composta; FK client usa ON DELETE CASCADE. A validação MySQL exige InnoDB, tipos, campos, PK e FKs esperados.

FKs independentes não provam propriedade: o serviço verifica proprietário real/legado primário e ACL do ator antes e depois da gravação, exigindo transação possuída com lock global. Criação/edição da identidade nativa e perfil são atômicas. Não há rollback destrutivo de schema; rollback transacional de gravação é testado. UI só habilita a extensão nativa depois do schema disponível. A rota exige contexto autenticado, POST, CSRF e ACL; compartilhamento não autoriza consulta/edição privada. Provedores usam allowlist fixa, TLS, redirects desligados, limites de resposta e timeout do serviço de Empresas.

## Evidências reproduzíveis

- `php tests/companies/client_profiles.php`: normalização PF/PJ, opcionais, armazenamento, replay, ownership A/B, mismatch sob lock, projeção compartilhada, rollback e revisão otimista (SQLite).
- `php tests/companies/client_profiles_legacy_model.php`: model real, criação/edição atômicas e proteção de clientes de outra empresa (SQLite).
- `php tests/companies/client_lookup_controller.php`: controller real com provedor sintético, ACL, contexto, POST, CSRF, limite e mapeamento.
- `STATIC_ONLY=1 node tests/companies/client_identity_browser.cjs`: renderiza parcial PHP real nos modos nativo/isolado e verifica remoção da consulta antiga.
- `node tests/companies/client_identity_browser.cjs`: Playwright desktop/mobile, prévia, não sobrescrita, PF/PJ, falha, XSS textual, CSRF e screenshots sintéticos. Requer navegador permitido.

Testes locais usam fixtures sintéticas, sem provedores reais ou dados de clientes. MySQL/InnoDB e browser completo devem ser executados em CI autorizada; o sandbox local impede sockets desses runtimes. Charset UTF-8 explícito e asserts de acentos foram adicionados às fixtures de Empresas e Clientes após identificação de mojibake nos screenshots sintéticos de CI.
