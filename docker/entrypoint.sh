#!/bin/sh
set -eu

config_dir="/var/lib/argws-crm/config"
config_file="${config_dir}/app-config.php"
app_config="/app/application/config/app-config.php"

mkdir -p "$config_dir"
ln -sfn "$config_file" "$app_config"

# A sample config is deliberately not generated: until an operator provisions
# the empty database and first administrator, the HTTP server only returns 503.
if [ -s "$config_file" ] && ! grep -Eq '\[(base_url|encryption_key|db_hostname|db_username|db_password|db_name)\]' "$config_file"; then
    exec "$@"
fi

if [ "${1:-}" = "frankenphp" ]; then
    exec frankenphp run --config /etc/caddy/Caddyfile.unprovisioned
fi

# Keep CLI commands available for one-time provisioning and diagnostics.
exec "$@"
