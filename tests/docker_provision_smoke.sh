#!/usr/bin/env bash
set -euo pipefail

image="argws-crm-provision-smoke:ci"
suffix="$$"
network="argws-crm-provision-network-${suffix}"
database="argws-crm-provision-db-${suffix}"
web="argws-crm-provision-web-${suffix}"
config_volume="argws-crm-provision-config-${suffix}"
database_name="argws_crm_ci"
database_user="argws_crm_ci"
database_password="$(openssl rand -hex 24)"
root_password="$(openssl rand -hex 24)"
bootstrap_password="$(openssl rand -hex 24)"
host_port=""

cleanup() {
    docker rm -f "$web" "$database" >/dev/null 2>&1 || true
    docker volume rm "$config_volume" >/dev/null 2>&1 || true
    docker network rm "$network" >/dev/null 2>&1 || true
}
trap cleanup EXIT

docker build --tag "$image" --build-arg ARGWS_VERSION=ci .
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
    -e ARGWS_CONFIG_DIR=/var/lib/argws-crm/config \
    -v "$config_volume:/var/lib/argws-crm/config" \
    -p 127.0.0.1::8080 "$image" >/dev/null
host_port="$(docker port "$web" 8080/tcp | awk -F: 'END { print $NF }')"
url="http://127.0.0.1:$host_port"
ready=0
for _ in $(seq 1 45); do
    code="$(curl -sS -o /tmp/argws-crm-unprovisioned-body -w '%{http_code}' "$url/" || true)"
    if [ "$code" = "503" ] && grep -q "provision.php" /tmp/argws-crm-unprovisioned-body; then
        ready=1
        break
    fi
    sleep 1
done
if [ "$ready" -ne 1 ]; then
    echo "O container não manteve o CRM bloqueado antes do provisionamento." >&2
    exit 1
fi
docker exec "$web" test ! -e /app/install
install_code="$(curl -sS -o /dev/null -w '%{http_code}' "$url/install/index.php")"
if [ "$install_code" != "404" ]; then
    echo "O instalador web respondeu com HTTP $install_code; esperado 404." >&2
    exit 1
fi

export ARGWS_CI_BOOTSTRAP_PASSWORD="$bootstrap_password"
python3 -c 'import json,os; print(json.dumps({"base_url":"https://crm.example.invalid/","firstname":"Admin","lastname":"ARGWS","admin_email":"admin-ci@example.invalid","admin_password":os.environ["ARGWS_CI_BOOTSTRAP_PASSWORD"],"timezone":"America/Sao_Paulo"}))' \
    | docker exec -i "$web" php /opt/argws-crm-provisioner/provision.php --input-json

staff_count="$(docker exec -e MYSQL_PWD="$database_password" "$database" mysql \
    --protocol=tcp --host=127.0.0.1 --user="$database_user" "$database_name" \
    --batch --skip-column-names \
    -e "SELECT COUNT(*) FROM tblstaff WHERE email='admin-ci@example.invalid' AND admin=1 AND active=1")"
if [ "$staff_count" != "1" ]; then
    echo "O provisionamento não criou exatamente um administrador ativo." >&2
    exit 1
fi

if printf '{}' | docker exec -i "$web" php /opt/argws-crm-provisioner/provision.php --input-json >/dev/null 2>&1; then
    echo "O provisionador aceitou uma segunda execução." >&2
    exit 1
fi

docker restart "$web" >/dev/null
status=""
for _ in $(seq 1 45); do
    status="$(curl -sS -o /tmp/argws-crm-after-provision-body -w '%{http_code}' "$url/" || true)"
    if [ -n "$status" ] && [ "$status" != "503" ]; then
        break
    fi
    sleep 1
done
if [ -z "$status" ] || [ "$status" = "503" ] || [ "$status" = "500" ]; then
    echo "A aplicação não iniciou após o provisionamento (HTTP ${status:-sem resposta})." >&2
    exit 1
fi
if [ "$(curl -sS -o /dev/null -w '%{http_code}' "$url/install/")" != "404" ]; then
    echo "A rota /install ainda está acessível após o provisionamento." >&2
    exit 1
fi

echo "Smoke test OK: schema MySQL importado, primeiro administrador criado, /install inacessível."
