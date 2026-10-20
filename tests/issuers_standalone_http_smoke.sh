#!/usr/bin/env bash
set -euo pipefail
# Disposable standalone-web installation, with shell/process functions disabled.
# Called from docker_provision_smoke.sh; does not touch public hosts or real data.
: "${ISSUER_SMOKE_IMAGE:?}" "${ISSUER_SMOKE_NETWORK:?}" "${ISSUER_SMOKE_DATABASE:?}"
: "${ISSUER_SMOKE_DB_USER:?}" "${ISSUER_SMOKE_DB_PASSWORD:?}" "${ISSUER_SMOKE_ROOT_PASSWORD:?}"
[[ "$ISSUER_SMOKE_DATABASE" =~ ^argws-crm-provision-db-[0-9]+$ ]]
trap 'status=$?; echo "Standalone HTTP smoke failed at line $LINENO (exit $status)." >&2' ERR
suffix="$$"
legacy="argws-crm-legacy-$suffix"
upgrade="argws-crm-upgrade-$suffix"
work="$(mktemp -d)"
provider_proxy_pid=
cleanup() { if [ -n "$provider_proxy_pid" ]; then kill "$provider_proxy_pid" 2>/dev/null || true; wait "$provider_proxy_pid" 2>/dev/null || true; fi; docker rm -f "$legacy" "$upgrade" >/dev/null 2>&1 || true; rm -rf "$work"; }
trap cleanup EXIT
mysql_root() { docker exec -i -e MYSQL_PWD="$ISSUER_SMOKE_ROOT_PASSWORD" "$ISSUER_SMOKE_DATABASE" mysql --user=root --batch --skip-column-names "$@"; }
mysql_root -e "CREATE DATABASE argws_legacy_ci; CREATE DATABASE argws_upgrade_ci; GRANT ALL ON argws_legacy_ci.* TO '$ISSUER_SMOKE_DB_USER'@'%'; GRANT ALL ON argws_upgrade_ci.* TO '$ISSUER_SMOKE_DB_USER'@'%';"
cp -a install "$work/install"
printf '\nALTER TABLE tblstaff AUTO_INCREMENT=17;\n' >> "$work/install/database.sql"
docker create --name "$legacy" --network "$ISSUER_SMOKE_NETWORK" -p 127.0.0.1::8080 \
    "$ISSUER_SMOKE_IMAGE" php -d disable_functions=exec,proc_open,shell_exec,system,passthru -S 0.0.0.0:8080 -t /app /tmp/issuer-router.php >/dev/null
docker cp "$work/install" "$legacy:/app/install"
cat > "$work/router.php" <<'PHP'
<?php
$root = '/app';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
$file = $root . $requestPath;
if ($requestPath !== '/' && is_file($file)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
$_SERVER['PHP_SELF'] = '/index.php' . $requestPath;
if (strpos($requestPath, '/argws_provisioning/') === 0) {
    error_log('[issuer smoke router] begin uri=' . ($_SERVER['REQUEST_URI'] ?? '')
        . ' script=' . ($_SERVER['SCRIPT_NAME'] ?? '')
        . ' path=' . $requestPath);
}
require $root . '/index.php';
if (strpos($requestPath, '/argws_provisioning/') === 0) {
    $class = isset($RTR) && method_exists($RTR, 'fetch_class') ? $RTR->fetch_class() : 'unknown';
    $method = isset($RTR) && method_exists($RTR, 'fetch_method') ? $RTR->fetch_method() : 'unknown';
    error_log('[issuer smoke router] dispatched=' . $class . '/' . $method
        . ' status=' . (string) http_response_code());
}
PHP
docker cp "$work/router.php" "$legacy:/tmp/issuer-router.php"
cat > "$work/my_hooks.php" <<'PHP'
<?php
// The installed webhooks module adds this helper to my_hooks.php. Keep it
// present in the synthetic fixture so its first-run refresh doesn't mask the
// HTTP route guard being tested below.
if (!function_exists('sprintsf')) {
    function sprintsf($content)
    {
        $tmp = tmpfile();
        $tmpf = stream_get_meta_data($tmp)['uri'];
        fwrite($tmp, "<?php " . $content . " ?>");
        $ret = include $tmpf;
        fclose($tmp);
        return $ret;
    }
}
$hook['pre_controller'][] = function () {
    if (strpos((string) ($_SERVER['REQUEST_URI'] ?? ''), '/argws_provisioning/') === 0) {
        $ci = &get_instance();
        $class = isset($ci->router) && method_exists($ci->router, 'fetch_class') ? $ci->router->fetch_class() : 'unknown';
        $method = isset($ci->router) && method_exists($ci->router, 'fetch_method') ? $ci->router->fetch_method() : 'unknown';
        $running = class_exists('InstallationMigrationBootstrap', false)
            ? (InstallationMigrationBootstrap::isRunning() ? '1' : '0')
            : 'missing';
        error_log('[issuer smoke hook] route=' . $class . '/' . $method . ' running=' . $running);
    }
    if (class_exists('InstallationMigrationBootstrap', false) && InstallationMigrationBootstrap::isRunning()) {
        file_put_contents('/tmp/issuer-installer-hook-input.json', json_encode([array_keys($_POST),array_keys($_REQUEST),file_get_contents('php://input')]));
    }
};
PHP
docker cp "$work/my_hooks.php" "$legacy:/app/application/config/my_hooks.php"
printf '%s\n' '<?php file_put_contents("/tmp/issuer-custom-autoload-ran", "unexpected");' > "$work/my_autoload.php"
printf '%s\n' '<?php file_put_contents("/tmp/issuer-custom-helper-ran", "unexpected");' > "$work/my_functions_helper.php"
docker cp "$work/my_autoload.php" "$legacy:/app/application/config/my_autoload.php"
docker cp "$work/my_functions_helper.php" "$legacy:/app/application/helpers/my_functions_helper.php"
docker start "$legacy" >/dev/null
port="$(docker port "$legacy" 8080/tcp | awk -F: 'END {print $NF}')"
url="http://127.0.0.1:$port"
for _ in $(seq 1 45); do
    code="$(curl -sS --max-time 3 -c "$work/cookies" -o "$work/form" -w '%{http_code}' "$url/install/index.php" 2>/dev/null || true)"
    [ "$code" = 200 ] && break
    sleep 1
done
[ "$code" = 200 ]
token="$(sed -n 's/.*name="installer_csrf" value="\([a-f0-9]*\)".*/\1/p' "$work/form" | head -1)"
[ -n "$token" ]
code="$(curl -sS --max-time 10 -b "$work/cookies" -o "$work/denied" -w '%{http_code}' --data 'step=4&installer_csrf=invalid' "$url/install/index.php")"
[ "$code" = 403 ]
[ "$(mysql_root -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='argws_legacy_ci'")" = 0 ]
code="$(curl -sS --max-time 30 -b "$work/cookies" -c "$work/cookies" -o "$work/database-form" -w '%{http_code}' \
    --data-urlencode "installer_csrf=$token" --data-urlencode 'step=3' \
    --data-urlencode "hostname=$ISSUER_SMOKE_DATABASE" --data-urlencode 'database=argws_legacy_ci' \
    --data-urlencode "username=$ISSUER_SMOKE_DB_USER" --data-urlencode "password=$ISSUER_SMOKE_DB_PASSWORD" "$url/install/index.php")"
[ "$code" = 200 ]
code="$(curl -sS --max-time 240 -b "$work/cookies" -c "$work/cookies" -o "$work/result" -w '%{http_code}' \
    --data-urlencode "installer_csrf=$token" --data-urlencode 'step=4' \
    --data-urlencode "hostname=$ISSUER_SMOKE_DATABASE" --data-urlencode 'database=argws_legacy_ci' \
    --data-urlencode "username=$ISSUER_SMOKE_DB_USER" --data-urlencode "password=$ISSUER_SMOKE_DB_PASSWORD" \
    --data-urlencode "base_url=$url/" --data-urlencode 'firstname=Admin' --data-urlencode 'lastname=Synthetic' \
    --data-urlencode 'admin_email=admin-ci@example.invalid' --data-urlencode 'admin_password=synthetic-browser-17' \
    --data-urlencode 'admin_passwordr=synthetic-browser-17' --data-urlencode 'timezone=America/Sao_Paulo' "$url/install/index.php")"
if [ "$code" != 200 ] || ! grep -q 'Instalação concluída!' "$work/result"; then
    echo 'O instalador standalone não confirmou a conclusão no mesmo POST.' >&2
    docker logs --tail 80 "$legacy" >&2
    exit 1
fi
! grep -Fq 'synthetic-browser-17' "$work/result"
! grep -Fq "$ISSUER_SMOKE_DB_PASSWORD" "$work/result"
docker exec "$legacy" test ! -e /tmp/issuer-installer-hook-input.json
docker exec "$legacy" test ! -e /tmp/issuer-custom-autoload-ran
docker exec "$legacy" test ! -e /tmp/issuer-custom-helper-ran
[ "$(mysql_root argws_legacy_ci -e 'SELECT staffid FROM tblstaff LIMIT 1')" = 17 ]
[ "$(mysql_root argws_legacy_ci -e "SELECT CONCAT(master_staff_id,':',source) FROM tblissuers_installation_identity WHERE id=1")" = '17:installer' ]
# PHP's built-in server ignores .htaccess; preserve CodeIgniter's query-string URI rewrite.
for action in complete_installation apply_migrations; do
    response="$work/route-$action"
    headers="$work/headers-$action"
    code="$(curl -sS --max-time 10 -D "$headers" -o "$response" -w '%{http_code}' "$url/argws_provisioning/$action")"
    if [ "$code" != 404 ]; then
        printf 'Unexpected HTTP status %s for %s; headers and response follow:\n' "$code" "$action" >&2
        cat "$headers" >&2 || true
        head -c 2048 "$response" >&2 || true
        docker logs --tail 80 "$legacy" >&2 || true
        exit 1
    fi
done
# The installer directory is removed as directed after success, only in this fixture.
docker exec --user root "$legacy" rm -rf /app/install /app/application/config/my_hooks.php /app/application/config/my_autoload.php /app/application/helpers/my_functions_helper.php
ISSUERS_BROWSER_URL="$url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_legacy_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/issuers_browser_smoke.cjs

# Import the genuinely older bundled schema, not only an older migration marker.
mysql_root argws_upgrade_ci < install/database.sql
mysql_root -e "INSERT INTO argws_upgrade_ci.tblstaff (staffid,firstname,lastname,email,password,admin,active,datecreated) SELECT staffid,firstname,lastname,email,password,admin,active,datecreated FROM argws_legacy_ci.tblstaff WHERE staffid=17;"
# A local CONNECT sink preserves real cURL TLS verification for synthetic refund review.
# Python/OpenSSL run on the CI host; the application image does not need Python.
provider_gateway="$(docker network inspect "$ISSUER_SMOKE_NETWORK" --format '{{(index .IPAM.Config 0).Gateway}}')"
provider_state_command="$(python3 - "$upgrade" <<'PY_STATE'
import json,sys
print(json.dumps(['docker','exec','-e','ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci',sys.argv[1],'php','/app/tests/companies/post_refund_manual_cycle_tls_state.php']))
PY_STATE
)"
python3 tests/companies/provider442_tls_proxy.py --bind "$provider_gateway" \
    --cert-dir "$work/provider-ca" --ready-file "$work/provider-ready.json" \
    --events "$work/provider-events.jsonl" --state-command "$provider_state_command" \
    --ai-owners-file "$work/ai-owners.json" --fail-reply-once \
    >"$work/provider-proxy.log" 2>&1 &
provider_proxy_pid=$!
for _ in $(seq 1 200); do
    [ -s "$work/provider-ready.json" ] && break
    if ! kill -0 "$provider_proxy_pid" 2>/dev/null; then cat "$work/provider-proxy.log" >&2; exit 1; fi
    sleep 0.1
done
[ -s "$work/provider-ready.json" ]
provider_proxy_url="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))["proxy"])' "$work/provider-ready.json")"
docker create --name "$upgrade" --network "$ISSUER_SMOKE_NETWORK" -p 127.0.0.1::8080 \
    -e HTTPS_PROXY="$provider_proxy_url" -e https_proxy="$provider_proxy_url" \
    -e NO_PROXY=127.0.0.1,localhost -e no_proxy=127.0.0.1,localhost \
    "$ISSUER_SMOKE_IMAGE" php -d curl.cainfo=/tmp/finance-provider-ca.pem -d openssl.cafile=/tmp/finance-provider-ca.pem \
    -S 0.0.0.0:8080 -t /app /tmp/issuer-router.php >/dev/null
docker cp "$work/provider-ca/ca.pem" "$upgrade:/tmp/finance-provider-ca.pem"
docker cp "$legacy:/var/lib/argws-crm/config/app-config.php" "$work/app-config.php"
sed -i 's/argws_legacy_ci/argws_upgrade_ci/g' "$work/app-config.php"
# This trusted callback is server test infrastructure, never a company secret or request-derived URL.
# Keep the synthetic HTTPS authority explicit even though the local browser uses HTTP.
[[ "$upgrade" =~ ^argws-crm-upgrade-[0-9]+$ ]]
grep -Fq "define('APP_DB_NAME', 'argws_upgrade_ci');" "$work/app-config.php"
printf '\n%s\n' "define('COMPANY_GOOGLE_MAIL_REDIRECT_URI', 'https://crm.example.invalid/admin/company_google_mail_oauth');" >> "$work/app-config.php"
printf '\n%s\n' "define('COMPANY_MICROSOFT_MAIL_REDIRECT_URI', 'https://crm.example.invalid/admin/company_microsoft_mail_oauth');" >> "$work/app-config.php"
printf '\n%s\n' "define('COMPANY_CONNECT_API_ENDPOINTS', ['synthetic'=>['label'=>'Synthetic approved','url'=>'https://connect.example.invalid']]);" >> "$work/app-config.php"
docker cp "$work/app-config.php" "$upgrade:/var/lib/argws-crm/config/app-config.php"
docker cp "$work/router.php" "$upgrade:/tmp/issuer-router.php"
docker start "$upgrade" >/dev/null
docker exec "$upgrade" mkdir -p /app/tests/companies
for fixture in post_refund_manual_cycle_tls_state.php post_refund_manual_cycle_synthetic_protocol.php post_refund_manual_cycle_tls_probe.php company_ai_tls_probe.php; do
    docker cp "tests/companies/$fixture" "$upgrade:/app/tests/companies/$fixture"
done
upgrade_port="$(docker port "$upgrade" 8080/tcp | awk -F: 'END {print $NF}')"
upgrade_url="http://127.0.0.1:$upgrade_port"
sed -i "s|$url/|$upgrade_url/|g" "$work/app-config.php"
docker cp "$work/app-config.php" "$upgrade:/var/lib/argws-crm/config/app-config.php"
for _ in $(seq 1 45); do
    code="$(curl -sS --max-time 3 -o /dev/null -w '%{http_code}' "$upgrade_url/admin/authentication" 2>/dev/null || true)"
    [ "$code" = 200 ] && break
    sleep 1
done
[ "$code" = 200 ]
ISSUERS_BROWSER_MODE=upgrade ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/issuers_browser_smoke.cjs
echo 'Standalone HTTP installation/native old-schema upgrade passed, with no exec/proc_open dependency.'
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_onboarding_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_portal_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_invoices_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_credits_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/projects_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_expenses_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_imports_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/contracts_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/operation_files_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/portal_operations_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/projects_extras_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_sales_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_sales_extras_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_sales_documents_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_primary_sales_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_sales_pdf_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_document_pdf_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_google_mail_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_google_portal_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_project_files_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_pdf_typography_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_reports_api_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_ledger_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/operations_api_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/operations_subresources_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/portal_support_write_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/portal_support_files_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/contract_discussions_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_portal_tasks_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/portal_task_write_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_milestones_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_timesheets_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_gantt_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_contact_files_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/task_billing_http_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/task_timesheets_http_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_billing_rate_http_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_fixed_billing_http_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/client_imports_http_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/expense_billing_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/expense_recurring_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_origination_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_components_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_external_reversal_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_callback_http.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_api_monthly_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_api_fixed_discount_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_api_percentage_discount_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_api16_components_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_api_origin_cnab240_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_asaas_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_asaas_remote_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies_asaas_checkout_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/asaas_refund_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/asaas_checkout_legacy_scope_http.cjs

# Recovered439 real forms and scoped local protocol doubles; no external provider calls.
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/resume_439_native_staff_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/operations_resume_439_sales_lines_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/operations_resume_439_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings/resume_439_microsoft_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings/resume_439_connect_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_pusher_recovery_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/recurrence_resume_439_browser.cjs
ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_resume_439_amend_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/invoice_drafts_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/invoice_recurring_modes_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/project_copies_http_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/task_copies_http_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/settings_ticket_connect_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_api_window_discount_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/post_refund_manual_cycle_browser.cjs

# Untrusted CA must fail in the production client; the generated CA enables the same verified client.
provider_before_gets="$(python3 -c 'import json,sys;print(sum(json.loads(x).get("kind")=="synthetic_get" for x in open(sys.argv[1])))' "$work/provider-events.jsonl")"
provider_probe_status=0
docker exec -e ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci "$upgrade" php -d curl.cainfo= \
    /app/tests/companies/post_refund_manual_cycle_tls_probe.php >"$work/untrusted-provider-probe.log" 2>&1 || provider_probe_status=$?
[ "$provider_probe_status" = 1 ]
python3 - "$work/provider-events.jsonl" "$provider_before_gets" <<'PY_UNTRUSTED'
import json,sys
assert sum(json.loads(x).get('kind')=='synthetic_get' for x in open(sys.argv[1]))==int(sys.argv[2]),'untrusted TLS reached a provider GET'
PY_UNTRUSTED
docker exec -e ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci "$upgrade" php \
    -d curl.cainfo=/tmp/finance-provider-ca.pem /app/tests/companies/post_refund_manual_cycle_tls_probe.php
python3 - "$work/provider-events.jsonl" <<'PY_TLS'
import json,sys
rows=[json.loads(x) for x in open(sys.argv[1])]
gets=[x for x in rows if x.get('kind')=='synthetic_get']
assert {x['letter'] for x in gets if '/payments/' in x['path']}=={'a','b'}
assert all(x['tls_version'] in ('TLSv1.2','TLSv1.3') for x in gets)
assert any(x.get('kind')=='tls_rejected' for x in rows)
print('Real HTTP refund review used verified local provider TLS A/B; untrusted CA rejected before GET. No forwarding.')
PY_TLS
provider_evidence="${ISSUERS_BROWSER_ARTIFACTS:-/tmp/issuer-browser-evidence}"
mkdir -p "$provider_evidence"
cp "$work/provider-events.jsonl" "$provider_evidence/refund-provider-local-tls.jsonl"

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/expense_api_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/companies/expense_copies_resume_442_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
ISSUERS_BROWSER_AI_OWNERS_FILE="$work/ai-owners.json" ISSUERS_BROWSER_PROVIDER_EVENTS="$work/provider-events.jsonl" \
node tests/settings_company_ai_browser.cjs

ISSUERS_BROWSER_URL="$upgrade_url" ISSUERS_BROWSER_PASSWORD=synthetic-browser-17 \
ISSUERS_BROWSER_APP_CONTAINER="$upgrade" ISSUERS_BROWSER_DB_CONTAINER="$ISSUER_SMOKE_DATABASE" ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci \
ISSUERS_BROWSER_DB_USER="$ISSUER_SMOKE_DB_USER" ISSUERS_BROWSER_DB_PASSWORD="$ISSUER_SMOKE_DB_PASSWORD" \
node tests/banco_brasil_own_number_browser.cjs

# Both provider clients keep certificate/hostname verification under the one strict local sink.
ai_probe_owner="$(python3 -c 'import json,sys;print(json.load(open(sys.argv[1]))[0])' "$work/ai-owners.json")"
ai_before_calls="$(python3 -c 'import json,sys;print(sum(json.loads(x).get("kind")=="synthetic_completion" for x in open(sys.argv[1])))' "$work/provider-events.jsonl")"
[ "$ai_before_calls" = 3 ]
ai_probe_status=0
docker exec -e ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci "$upgrade" php -d curl.cainfo= -d openssl.cafile= \
    /app/tests/companies/company_ai_tls_probe.php "$ai_probe_owner" >"$work/untrusted-ai-probe.log" 2>&1 || ai_probe_status=$?
[ "$ai_probe_status" = 1 ]
python3 - "$work/provider-events.jsonl" "$ai_before_calls" <<'PY_AI_UNTRUSTED'
import json,sys
assert sum(json.loads(x).get('kind')=='synthetic_completion' for x in open(sys.argv[1]))==int(sys.argv[2]),'untrusted TLS reached an AI completion'
PY_AI_UNTRUSTED
docker exec -e ISSUERS_BROWSER_DB_NAME=argws_upgrade_ci "$upgrade" php \
    -d curl.cainfo=/tmp/finance-provider-ca.pem -d openssl.cafile=/tmp/finance-provider-ca.pem \
    /app/tests/companies/company_ai_tls_probe.php "$ai_probe_owner"
python3 - "$work/provider-events.jsonl" <<'PY_DUAL_TLS'
import json,sys
rows=[json.loads(x) for x in open(sys.argv[1])]
ai=[x for x in rows if x.get('kind')=='synthetic_completion']
drops=[x for x in rows if x.get('kind')=='simulated_disconnect']
assert len(ai)==4 and len({x['owner'] for x in ai})==3,'three GUI owners and one explicit SDK probe required'
assert len(drops)==1,'one interrupted SDK response; uncertain GUI retry must never resend'
assert all(x['tls_version'] in ('TLSv1.2','TLSv1.3') for x in ai+drops)
print('Real GUI/SDK AI A/B/principal dispatch used verified local TLS; untrusted CA rejected and uncertain request not resent. No external forwarding.')
PY_DUAL_TLS
cp "$work/provider-events.jsonl" "$provider_evidence/dual-provider-local-tls.jsonl"
