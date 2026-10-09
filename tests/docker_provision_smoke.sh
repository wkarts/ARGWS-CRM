#!/usr/bin/env bash
set -euo pipefail

image="argws-crm-provision-smoke:ci"
suffix="$$"
network="argws-crm-provision-network-$suffix"
database="argws-crm-provision-db-$suffix"
web="argws-crm-provision-web-$suffix"
config_volume="argws-crm-provision-config-$suffix"
database_name="argws_crm_ci"
database_user="argws_crm_ci"
database_password="$(openssl rand -hex 24)"
root_password="$(openssl rand -hex 24)"
setup_token="$(openssl rand -hex 32)"
admin_password="abc123"
host_port=""
storage_compose_dir=""

cleanup() {
    if [ -n "$storage_compose_dir" ]; then
        docker compose --project-directory "$storage_compose_dir" --env-file "$storage_compose_dir/.env" -f "$storage_compose_dir/compose.yaml" down --remove-orphans >/dev/null 2>&1 || true
        if [ -d "$storage_compose_dir/storage" ]; then
            docker run --rm --user 0:0 -e HOST_UID="$(id -u)" -e HOST_GID="$(id -g)" \
                --volume "$storage_compose_dir/storage:/storage" --entrypoint /bin/sh "$image" \
                -ec 'chown -R "$HOST_UID:$HOST_GID" /storage' >/dev/null 2>&1 || true
        fi
        rm -rf "$storage_compose_dir"
    fi
    docker rm -f "$web" "$database" >/dev/null 2>&1 || true
    docker volume rm "$config_volume" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
    docker image rm ghcr.io/wkarts/argws-crm:ci >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker build --tag "$image" --build-arg ARGWS_VERSION=ci .
docker run --rm --volume "$PWD:/source:ro" --entrypoint php "$image" /source/tests/brand_assets_smoke.php
docker run --rm --volume "$PWD:/source:ro" --entrypoint php "$image" /source/tests/email_templates_php_smoke.php
docker tag "$image" ghcr.io/wkarts/argws-crm:ci
storage_compose_dir="$(mktemp -d)"
cp compose.yaml "$storage_compose_dir/compose.yaml"
cat > "$storage_compose_dir/.env" <<EOF
COMPOSE_PROJECT_NAME=argws-crm-storage-smoke-$suffix
ARGWS_VERSION=ci
ARGWS_STORAGE_ROOT=./storage
ARGWS_HTTP_BIND=127.0.0.1
ARGWS_HTTP_PORT=8080
EOF
docker compose --project-directory "$storage_compose_dir" --env-file "$storage_compose_dir/.env" \
    -f "$storage_compose_dir/compose.yaml" run --rm web true
for directory in installation_config uploads temp application_cache application_logs \
    module_accounting_uploads module_finance_uploads module_fleet_uploads \
    module_hr_payroll_uploads module_hr_profile_uploads module_invoices_builder_uploads \
    module_ma_uploads module_products_uploads module_purchase_uploads \
    module_service_management_uploads module_si_custom_theme_uploads \
    module_timesheets_uploads caddy_data caddy_config
do
    owner="$(docker run --rm --user 0:0 \
        --volume "$storage_compose_dir/storage/$directory:/data:ro" \
        --entrypoint /bin/sh "$image" -ec 'stat -c "%u:%g" /data')"
    if [ "$owner" != "33:33" ]; then
        echo "O entrypoint web não preparou $directory para www-data (proprietário $owner)." >&2
        exit 1
    fi
done
echo "Compose entrypoint storage OK: diretórios relativos e graváveis por www-data."

docker network create "$network" >/dev/null
docker volume create "$config_volume" >/dev/null
docker run -d --name "$database" --network "$network" \
    -e MYSQL_ROOT_PASSWORD="$root_password" \
    -e MYSQL_DATABASE="$database_name" \
    -e MYSQL_USER="$database_user" \
    -e MYSQL_PASSWORD="$database_password" mysql:8.0 >/dev/null

healthy=0
for _ in $(seq 1 90); do
    if docker exec "$database" mysqladmin ping --protocol=tcp --host=127.0.0.1 \
        --user=root --password="$root_password" --silent >/dev/null 2>&1; then
        healthy=1
        break
    fi
    sleep 2
done
if [ "$healthy" -ne 1 ]; then
    echo "MySQL não ficou pronto para o teste de provisionamento." >&2
    exit 1
fi

docker run -d --name "$web" --network "$network" \
    -e ARGWS_DB_HOST="$database" \
    -e ARGWS_DB_NAME="$database_name" \
    -e ARGWS_DB_USER="$database_user" \
    -e ARGWS_DB_PASSWORD="$database_password" \
    -e ARGWS_SETUP_TOKEN="$setup_token" \
    -e ARGWS_CONFIG_DIR=/var/lib/argws-crm/config \
    -v "$config_volume:/var/lib/argws-crm/config" \
    -p 127.0.0.1::8080 "$image" >/dev/null
host_port="$(docker port "$web" 8080/tcp | awk -F: 'END { print $NF }')"
url="http://127.0.0.1:$host_port"
# A raiz deve responder com "Location: /setup" sem expor instruções do deploy.
status=""
for _ in $(seq 1 45); do
    status="$(curl --connect-timeout 2 --max-time 5 -sS -D /tmp/argws-crm-unprovisioned-headers -o /tmp/argws-crm-unprovisioned-body -w '%{http_code}' "$url/" 2>/dev/null || true)"
    if [ "$status" = "302" ]; then break; fi
    sleep 1
done
root_location="$(sed -n 's/^[Ll]ocation: //p' /tmp/argws-crm-unprovisioned-headers | tr -d '\r' | tail -n 1)"
if [ "$status" != "302" ] || [ "$root_location" != "/setup" ]; then
    echo "O primeiro acesso não redirecionou automaticamente para /setup (HTTP $status, Location $root_location)." >&2
    echo "URL=$url mapeamento=$(docker port "$web" 8080/tcp 2>&1 || true)" >&2
    docker inspect --format 'estado={{.State.Status}} saída={{.State.ExitCode}} erro={{.State.Error}} portas={{json .NetworkSettings.Ports}} IP={{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}' "$web" >&2 || true
    docker exec "$web" php -r '$stream = @fsockopen("127.0.0.1", 8080, $errno, $errstr, 3); if (!$stream) { fwrite(STDERR, "socket interno indisponível: $errno $errstr" . PHP_EOL); exit(1); } fwrite(STDERR, "socket interno conectado" . PHP_EOL); fclose($stream);' >&2 || true
    curl -v --connect-timeout 3 --max-time 5 "$url/" -o /dev/null >&2 || true
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi
if grep -Eiq 'ARGWS_SETUP_TOKEN|aguarda o primeiro acesso|docker compose exec' /tmp/argws-crm-unprovisioned-body; then
    echo "A resposta inicial expôs instruções internas de implantação." >&2
    exit 1
fi
docker exec "$web" test ! -e /app/install
docker exec "$web" test ! -e /var/lib/argws-crm/config/provisioned

setup_code="$(curl --connect-timeout 2 --max-time 5 -sS -c /tmp/argws-crm-setup-cookie -o /tmp/argws-crm-setup-page -w '%{http_code}' "$url/setup")"
if [ "$setup_code" != "200" ] || ! grep -q "Seu ambiente começa aqui." /tmp/argws-crm-setup-page; then
    echo "O assistente web não foi servido (HTTP $setup_code)." >&2
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi
brand_code="$(curl --connect-timeout 2 --max-time 5 -sS -o /tmp/argws-crm-brand-image -w '%{http_code}' "$url/assets/images/argws/logo-light.png")"
if [ "$brand_code" != "200" ] || ! cmp -s /tmp/argws-crm-brand-image assets/images/argws/logo-light.png; then
    echo "O logo do assistente não foi servido como arquivo público antes do provisionamento (HTTP $brand_code)." >&2
    exit 1
fi
csrf_token="$(sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' /tmp/argws-crm-setup-page | head -n 1)"
if [ -z "$csrf_token" ]; then
    echo "O formulário web não forneceu token CSRF." >&2
    exit 1
fi

bad_key_status="$(curl --connect-timeout 2 --max-time 5 -sS -b /tmp/argws-crm-setup-cookie -c /tmp/argws-crm-setup-cookie \
    -o /tmp/argws-crm-setup-denied -w '%{http_code}' \
    --data-urlencode "csrf_token=$csrf_token" \
    --data-urlencode "setup_token=incorrect" \
    --data-urlencode "base_url=$url/" \
    --data-urlencode "firstname=Admin" \
    --data-urlencode "lastname=ARGWS" \
    --data-urlencode "admin_email=admin-ci@example.invalid" \
    --data-urlencode "admin_password=$admin_password" \
    --data-urlencode "admin_password_repeat=$admin_password" \
    --data-urlencode "timezone=America/Sao_Paulo" "$url/setup")"
if [ "$bad_key_status" != "403" ]; then
    echo "O assistente aceitou uma chave inválida (HTTP $bad_key_status)." >&2
    exit 1
fi

setup_code="$(curl --connect-timeout 2 --max-time 5 -sS -b /tmp/argws-crm-setup-cookie -c /tmp/argws-crm-setup-cookie \
    -o /tmp/argws-crm-setup-page -w '%{http_code}' "$url/setup")"
csrf_token="$(sed -n 's/.*name="csrf_token" value="\([a-f0-9]*\)".*/\1/p' /tmp/argws-crm-setup-page | head -n 1)"
if [ "$setup_code" != "200" ] || [ -z "$csrf_token" ]; then
    echo "O formulário não foi restaurado após a tentativa recusada." >&2
    exit 1
fi

setup_code="$(curl --connect-timeout 2 --max-time 180 -sS -D /tmp/argws-crm-setup-result-headers -b /tmp/argws-crm-setup-cookie -c /tmp/argws-crm-setup-cookie \
    -o /tmp/argws-crm-setup-result -w '%{http_code}' \
    --data-urlencode "csrf_token=$csrf_token" \
    --data-urlencode "setup_token=$setup_token" \
    --data-urlencode "base_url=$url/" \
    --data-urlencode "firstname=Admin" \
    --data-urlencode "lastname=ARGWS" \
    --data-urlencode "admin_email=admin-ci@example.invalid" \
    --data-urlencode "admin_password=$admin_password" \
    --data-urlencode "admin_password_repeat=$admin_password" \
    --data-urlencode "timezone=America/Sao_Paulo" "$url/setup")"
setup_location="$(sed -n 's/^[Ll]ocation: //p' /tmp/argws-crm-setup-result-headers | tr -d '\r' | tail -n 1)"
if [ "$setup_code" != "303" ] || [ "$setup_location" != "/admin/authentication" ]; then
    echo "O assistente web não concluiu o setup nem encaminhou para o acesso (HTTP $setup_code, Location $setup_location)." >&2
    cat /tmp/argws-crm-setup-result >&2 || true
    docker logs --tail 100 "$web" >&2 || true
    docker exec "$web" /bin/sh -c 'for file in /app/application/logs/log-*.php; do [ -f "$file" ] && tail -n 80 "$file"; done' >&2 || true
    exit 1
fi

status=""
for _ in $(seq 1 45); do
    status="$(curl --connect-timeout 2 --max-time 5 -sS -o /tmp/argws-crm-after-provision-body -w '%{http_code}' "$url/" 2>/dev/null || true)"
    if [[ "$status" =~ ^[1-5][0-9][0-9]$ ]] && [ "$status" != "503" ]; then break; fi
    sleep 1
done
if [[ ! "$status" =~ ^[1-5][0-9][0-9]$ ]] || [ "$status" = "503" ] || [ "$status" = "500" ]; then
    echo "O CRM não abriu automaticamente após o assistente (HTTP $status)." >&2
    docker inspect --format 'container={{.State.Status}} exit={{.State.ExitCode}} error={{.State.Error}} ports={{json .NetworkSettings.Ports}}' "$web" >&2 || true
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi

admin_count="$(docker exec -e MYSQL_PWD="$database_password" "$database" mysql \
    --protocol=tcp --host=127.0.0.1 --user="$database_user" "$database_name" \
    --batch --skip-column-names \
    -e "SELECT COUNT(*) FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1")"
if [ "$admin_count" != "1" ]; then
    echo "O formulário não criou exatamente um administrador ativo." >&2
    exit 1
fi
docker exec "$web" test -s /var/lib/argws-crm/config/provisioned
expected_migration="$(sed -n "s/.*migration_version.*= *\\([0-9][0-9]*\\).*/\\1/p" application/config/migration.php | head -n 1)"
current_migration="$(docker exec -e MYSQL_PWD="$database_password" "$database" mysql \
    --protocol=tcp --host=127.0.0.1 --user="$database_user" "$database_name" \
    --batch --skip-column-names -e 'SELECT version FROM tblmigrations LIMIT 1')"
if [ -z "$expected_migration" ] || [ "$current_migration" != "$expected_migration" ]; then
    echo "As migrations não foram aplicadas antes de liberar o CRM (esperada=$expected_migration, aplicada=$current_migration)." >&2
    exit 1
fi
admin_status="$(curl --connect-timeout 2 --max-time 10 -sS -o /tmp/argws-crm-admin-page -w '%{http_code}' "$url/admin" 2>/dev/null || true)"
if [ "$admin_status" = "500" ] || grep -Eiq 'Unable to load requested language file: language/portuguese_br/migration_lang.php|migration included in files is' /tmp/argws-crm-admin-page; then
    echo "A área administrativa ainda acusa migration pendente ou arquivo de idioma ausente." >&2
    exit 1
fi

# O primeiro POST de autenticação executa App_Form_validation e precisa carregar
# form_validation_lang.php. Acesse o painel com o administrador recém-criado.
login_status="$(curl --connect-timeout 2 --max-time 10 -sS -b /tmp/argws-crm-setup-cookie -c /tmp/argws-crm-admin-cookie \
    -o /tmp/argws-crm-login-page -w '%{http_code}' "$url/admin/authentication" 2>/dev/null || true)"
login_csrf="$(sed -n 's/.*name="csrf_token_name" value="\([a-f0-9]*\)".*/\1/p' /tmp/argws-crm-login-page 2>/dev/null | head -n 1 || true)"
if [ "$login_status" != "200" ] || [ -z "$login_csrf" ] || ! grep -q 'name="email"' /tmp/argws-crm-login-page; then
    echo "A página de login não carregou com o formulário e CSRF (HTTP $login_status)." >&2
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi
login_status="$(curl --connect-timeout 2 --max-time 15 -sS -D /tmp/argws-crm-login-result-headers \
    -b /tmp/argws-crm-admin-cookie -c /tmp/argws-crm-admin-cookie \
    -o /tmp/argws-crm-login-result -w '%{http_code}' \
    --data-urlencode "csrf_token_name=$login_csrf" \
    --data-urlencode "email=admin-ci@example.invalid" \
    --data-urlencode "password=$admin_password" "$url/admin/authentication" 2>/dev/null || true)"
if [ "$login_status" != "302" ] && [ "$login_status" != "303" ] || \
    grep -Eiq 'Unable to load the requested language file|An Error Was Encountered' /tmp/argws-crm-login-result; then
    echo "O primeiro login do administrador falhou (HTTP $login_status)." >&2
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi
dashboard_result="$(curl --connect-timeout 2 --max-time 15 -sS -L -b /tmp/argws-crm-admin-cookie \
    -o /tmp/argws-crm-dashboard -w '%{http_code}|%{url_effective}' "$url/admin" 2>/dev/null || true)"
dashboard_status="${dashboard_result%%|*}"
dashboard_url="${dashboard_result#*|}"
if [ "$dashboard_status" != "200" ] || [[ "$dashboard_url" == *"/authentication"* ]] || \
    grep -Eiq 'Unable to load the requested language file|An Error Was Encountered|<body[^>]*login_admin' /tmp/argws-crm-dashboard; then
    echo "A sessão do administrador não abriu o painel (HTTP $dashboard_status, URL $dashboard_url)." >&2
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi

setup_after_code="$(curl --connect-timeout 2 --max-time 5 -sS -o /dev/null -w '%{http_code}' "$url/setup")"
install_code="$(curl --connect-timeout 2 --max-time 5 -sS -o /dev/null -w '%{http_code}' "$url/install/")"
if [ "$setup_after_code" != "404" ] || [ "$install_code" != "404" ]; then
    echo "As rotas de instalação não foram fechadas: /setup=$setup_after_code /install=$install_code" >&2
    exit 1
fi
if docker logs "$web" 2>&1 | grep -Fq "$setup_token"; then
    echo "A chave temporária apareceu nos logs do container." >&2
    exit 1
fi
if docker logs "$web" 2>&1 | grep -Fq "$admin_password"; then
    echo "A senha do administrador apareceu nos logs do container." >&2
    exit 1
fi

docker restart "$web" >/dev/null
host_port="$(docker port "$web" 8080/tcp | awk -F: 'END { print $NF }')"
if [ -z "$host_port" ]; then
    echo "A porta publicada pelo container não foi encontrada após o restart." >&2
    docker inspect --format 'container={{.State.Status}} exit={{.State.ExitCode}} error={{.State.Error}} ports={{json .NetworkSettings.Ports}}' "$web" >&2 || true
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi
url="http://127.0.0.1:$host_port"
status=""
for _ in $(seq 1 45); do
    status="$(curl --connect-timeout 2 --max-time 5 -sS -o /tmp/argws-crm-after-restart-body -w '%{http_code}' "$url/" 2>/dev/null || true)"
    if [[ "$status" =~ ^[1-5][0-9][0-9]$ ]] && [ "$status" != "503" ]; then break; fi
    sleep 1
done
if [[ ! "$status" =~ ^[1-5][0-9][0-9]$ ]] || [ "$status" = "503" ] || [ "$status" = "500" ]; then
    echo "A aplicação não iniciou após reiniciar (HTTP $status)." >&2
    docker inspect --format 'container={{.State.Status}} exit={{.State.ExitCode}} error={{.State.Error}} ports={{json .NetworkSettings.Ports}}' "$web" >&2 || true
    docker logs --tail 100 "$web" >&2 || true
    exit 1
fi
admin_count_after_restart="$(docker exec -e MYSQL_PWD="$database_password" "$database" mysql \
    --protocol=tcp --host=127.0.0.1 --user="$database_user" "$database_name" \
    --batch --skip-column-names \
    -e "SELECT COUNT(*) FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1")"
if [ "$admin_count_after_restart" != "1" ]; then
    echo "O restart repetiu a criação do administrador." >&2
    exit 1
fi


# Verificar a atualização 370 -> 371 preservando os modelos de módulos.
mysql_web() {
    docker exec -e MYSQL_PWD="$database_password" "$database" mysql \
        --protocol=tcp --host=127.0.0.1 --user="$database_user" "$database_name" \
        --batch --skip-column-names "$@"
}
mysql_web -e "
INSERT INTO tblemailtemplates (type,slug,language,name,subject,message,fromname,fromemail,plaintext,active,\`order\`)
SELECT 'order','order-to-admin','english','Success Order For Admin','Order Paid Successfully',
'<em>You received a new order {order_id} with a total amount of {total} {currency}  {invoice_number}{invoice_link}</em>',
fromname,fromemail,plaintext,0,\`order\`
FROM tblemailtemplates WHERE slug='invoice-send-to-client' AND language='english' LIMIT 1;
INSERT INTO tblemailtemplates (type,slug,language,name,subject,message,fromname,fromemail,plaintext,active,\`order\`)
SELECT 'webhooks','webhook-failed','portuguese_br','Webhook failed','Webhook failed','',
fromname,fromemail,plaintext,0,\`order\`
FROM tblemailtemplates WHERE slug='invoice-send-to-client' AND language='english' LIMIT 1;
UPDATE tblemailtemplates SET subject='Assunto personalizado',
message='<p>Conteúdo personalizado {invoice_number}</p>'
WHERE slug='invoice-send-to-client' AND language='portuguese_br';
"

# Em PRs a base é 370; em releases 371 já está aplicada desde a instalação.
if [ "$expected_migration" = "370" ]; then
    docker exec --user root "$web" sed -i \
        "s/migration_version'] = 370;/migration_version'] = 371;/" \
        /app/application/config/migration.php
    upgrade_token="$(openssl rand -hex 32)"
    output="$(docker exec -e ARGWS_SETUP_MIGRATION_TOKEN="$upgrade_token" "$web" \
        php /opt/argws-crm-provisioner/migration-cli.php)"
    if ! grep -q '"success":true' <<< "$output"; then
        echo "A migration 371 falhou: $output" >&2
        exit 1
    fi
    if [ "$(mysql_web -e 'SELECT version FROM tblmigrations LIMIT 1')" != "371" ]; then
        echo "O banco não alcançou a migration 371." >&2
        exit 1
    fi
fi

# A abertura da lista também reconcilia os módulos previamente ativados.
page_status="$(curl --connect-timeout 2 --max-time 30 -sS -L \
    -b /tmp/argws-crm-admin-cookie -o /tmp/argws-crm-mail-upgrade \
    -w '%{http_code}' "$url/admin/emails" 2>/dev/null || true)"
if [ "$page_status" != "200" ] || \
    grep -Eiq 'Unable to load the requested language file|An Error Was Encountered' /tmp/argws-crm-mail-upgrade; then
    echo "A lista de e-mails não abriu após upgrade: HTTP $page_status." >&2
    exit 1
fi

translated="$(mysql_web -e "SELECT CONCAT(name,'|',subject,'|',active,'|',message) FROM tblemailtemplates WHERE slug='order-to-admin' AND language='portuguese_br' LIMIT 1")"
webhook="$(mysql_web -e "SELECT CONCAT(name,'|',subject,'|',active,'|',message) FROM tblemailtemplates WHERE slug='webhook-failed' AND language='portuguese_br' LIMIT 1")"
customized="$(mysql_web -e "SELECT CONCAT(subject,'|',message) FROM tblemailtemplates WHERE slug='invoice-send-to-client' AND language='portuguese_br' LIMIT 1")"
count_models="$(mysql_web -e "SELECT COUNT(*) FROM tblemailtemplates WHERE slug='order-to-admin' AND language='portuguese_br'")"
if [[ "$translated" != "Pagamento de pedido (equipe)|Pagamento de pedido confirmado|0|"* ]] || \
    [[ "$translated" != *'{order_id}'* ]] || \
    [ "$webhook" != 'Falha na entrega do webhook|Falha na entrega de um webhook|0|' ] || \
    [ "$customized" != 'Assunto personalizado|<p>Conteúdo personalizado {invoice_number}</p>' ] || \
    [ "$count_models" != "1" ]; then
    echo "O upgrade alterou personalizações ou não traduziu os modelos corretamente." >&2
    exit 1
fi
curl --connect-timeout 2 --max-time 30 -sS -L \
    -b /tmp/argws-crm-admin-cookie -o /dev/null "$url/admin/emails"
if [ "$(mysql_web -e "SELECT COUNT(*) FROM tblemailtemplates WHERE slug='order-to-admin' AND language='portuguese_br'")" != "1" ]; then
    echo "A sincronização não é idempotente." >&2
    exit 1
fi
echo "Smoke OK: migration 371, modelos legados, personalização e idempotência."

echo "Smoke test OK: assistente protegido, administrador autenticado e painel aberto; /setup e /install bloqueados após setup."
