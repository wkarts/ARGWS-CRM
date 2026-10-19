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
cleanup() { docker rm -f "$legacy" "$upgrade" >/dev/null 2>&1 || true; rm -rf "$work"; }
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
docker create --name "$upgrade" --network "$ISSUER_SMOKE_NETWORK" -p 127.0.0.1::8080 \
    "$ISSUER_SMOKE_IMAGE" php -S 0.0.0.0:8080 -t /app /tmp/issuer-router.php >/dev/null
docker cp "$legacy:/var/lib/argws-crm/config/app-config.php" "$work/app-config.php"
sed -i 's/argws_legacy_ci/argws_upgrade_ci/g' "$work/app-config.php"
docker cp "$work/app-config.php" "$upgrade:/var/lib/argws-crm/config/app-config.php"
docker cp "$work/router.php" "$upgrade:/tmp/issuer-router.php"
docker start "$upgrade" >/dev/null
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
node tests/banco_brasil_origination_browser.cjs
