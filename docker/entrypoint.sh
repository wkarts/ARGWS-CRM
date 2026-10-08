#!/bin/sh
set -eu

config_dir="/var/lib/argws-crm/config"
config_file="$config_dir/app-config.php"
app_config="/app/application/config/app-config.php"

mkdir -p "$config_dir"
ln -sfn "$config_file" "$app_config"

if [ -s "$config_file" ] && ! grep -Eq '\[(base_url|encryption_key|db_hostname|db_username|db_password|db_name)\]' "$config_file"; then
    exec "$@"
fi

if [ "$1" = "frankenphp" ]; then
    # The setup route remains available until the persistent app-config.php is written.
    # Caddy's file matcher switches traffic to the CRM on the next request.
    exec frankenphp run --config /etc/caddy/Caddyfile.unprovisioned
fi

# Preserve CLI commands for diagnostics and the optional interactive provisioner.
exec "$@"
