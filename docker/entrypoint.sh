#!/bin/sh
set -eu

config_dir="/var/lib/argws-crm/config"
config_file="$config_dir/app-config.php"
provisioned_file="$config_dir/provisioned"
app_config="/app/application/config/app-config.php"

# Only declared writable directories are repaired; never traverse symlinks or
# recursively change ownership of uploaded data or application source files.
prepare_storage_directory() {
    directory="$1"
    parent="$directory"
    while [ "$parent" != / ]; do
        if [ -L "$parent" ]; then
            echo "Link simbólico não permitido no armazenamento gravável: $directory" >&2
            return 1
        fi
        parent="$(dirname "$parent")"
    done
    if [ -e "$directory" ] && [ ! -d "$directory" ]; then
        echo "O caminho de armazenamento não é um diretório: $directory" >&2
        return 1
    fi
    mkdir -p -m 0755 "$directory" || return 1
    chown 33:33 "$directory" || return 1
    chmod u+rwx "$directory" || return 1
}

prepare_storage_directories() {
    for directory in \
        "$config_dir" \
        /app/uploads \
        /app/uploads/estimates \
        /app/uploads/proposals \
        /app/uploads/ticket_attachments \
        /app/uploads/tasks \
        /app/uploads/staff_profile_images \
        /app/uploads/projects \
        /app/uploads/newsfeed \
        /app/uploads/leads \
        /app/uploads/invoices \
        /app/uploads/expenses \
        /app/uploads/discussions \
        /app/uploads/contracts \
        /app/uploads/company \
        /app/uploads/clients \
        /app/uploads/credit_notes \
        /app/uploads/client_profile_images \
        /app/temp \
        /app/application/cache \
        /app/application/logs \
        /app/modules/accounting/uploads \
        /app/modules/finance/uploads \
        /app/modules/fleet/uploads \
        /app/modules/hr_payroll/uploads \
        /app/modules/hr_profile/uploads \
        /app/modules/invoices_builder/uploads \
        /app/modules/ma/uploads \
        /app/modules/products/uploads \
        /app/modules/purchase/uploads \
        /app/modules/service_management/uploads \
        /app/modules/si_custom_theme/uploads \
        /app/modules/timesheets/uploads \
        /data \
        /config
    do
        prepare_storage_directory "$directory" || {
            echo "Não foi possível preparar o armazenamento; confira proprietário e volumes somente leitura: $directory" >&2
            return 1
        }
    done
}

run_as_web_user() {
    if [ "$(id -u)" -eq 0 ]; then
        exec gosu www-data "$@"
    fi
    exec "$@"
}

if [ "$(id -u)" -eq 0 ]; then
    prepare_storage_directories
fi

mkdir -p "$(dirname "$app_config")"
ln -sfn "$config_file" "$app_config"

if [ -s "$config_file" ] \
    && [ -s "$provisioned_file" ] \
    && ! grep -Eq '\[(base_url|encryption_key|db_hostname|db_username|db_password|db_name)\]' "$config_file"; then
    run_as_web_user "$@"
fi

if [ "$1" = "frankenphp" ]; then
    # Until setup finishes its database migrations, serve only the one-time setup route.
    run_as_web_user frankenphp run --config /etc/caddy/Caddyfile.unprovisioned --adapter caddyfile
fi

# Preserve CLI commands for diagnostics and the optional interactive provisioner.
run_as_web_user "$@"
