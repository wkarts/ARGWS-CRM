#!/bin/sh
set -eu

config_dir="/var/lib/argws-crm/config"
config_file="${config_dir}/app-config.php"
app_config="/app/application/config/app-config.php"

mkdir -p "$config_dir"
if [ ! -f "$config_file" ]; then
    cp /app/application/config/app-config-sample.php "$config_file"
fi

ln -sfn "$config_file" "$app_config"
exec "$@"
