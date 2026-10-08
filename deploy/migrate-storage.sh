#!/bin/sh
set -eu

target_dir=.
if [ "$#" -gt 0 ] && [ -n "$1" ]; then target_dir=$1; fi
stack_dir=$(CDPATH= cd -- "$target_dir" && pwd -P)
compose_file="$stack_dir/compose.yaml"
env_file="$stack_dir/.env"

if [ ! -f "$compose_file" ] || [ ! -f "$env_file" ]; then
  echo "Execute este script na pasta da stack que contém compose.yaml e .env." >&2
  exit 1
fi

env_value() {
  sed -n "s/^$1=//p" "$env_file" | sed -n '1p'
}

storage_root=$(env_value ARGWS_STORAGE_ROOT)
if [ -z "$storage_root" ]; then storage_root=./storage; fi
case "$storage_root" in
  ./*) ;;
  *)
    echo "ARGWS_STORAGE_ROOT deve ser um caminho relativo iniciado por ./." >&2
    exit 1
    ;;
esac
case "/$storage_root/" in
  */../*)
    echo "ARGWS_STORAGE_ROOT não pode sair da pasta da stack." >&2
    exit 1
    ;;
esac

project_name=$(env_value COMPOSE_PROJECT_NAME)
if [ -z "$project_name" ]; then
  case "$(basename "$stack_dir")" in
    develop) project_name=argws-crm-develop ;;
    production) project_name=argws-crm-production ;;
    *) project_name=argws-crm ;;
  esac
fi
case "$project_name" in
  ""|*[!a-zA-Z0-9_-]*)
    echo "COMPOSE_PROJECT_NAME contém caracteres inválidos." >&2
    exit 1
    ;;
esac

migration_image=$(env_value ARGWS_CRM_IMAGE)
if [ -z "$migration_image" ]; then
  version=$(env_value ARGWS_VERSION)
  if [ -z "$version" ]; then version=3.6.0; fi
  migration_image="ghcr.io/wkarts/argws-crm:$version"
fi

storage_subdir=$(printf '%s' "$storage_root" | sed 's|^\./||')
storage_dir="$stack_dir/$storage_subdir"
marker_dir="$storage_dir/.argws-volume-migration"
mkdir -p "$marker_dir"

docker compose --project-directory "$stack_dir" --env-file "$env_file" -f "$compose_file" config --quiet
# Keep old named volumes intact; in particular, do not add "-v" here.
docker compose --project-directory "$stack_dir" --env-file "$env_file" -f "$compose_file" down --remove-orphans

for volume in \
  installation_config \
  uploads \
  temp \
  application_cache \
  application_logs \
  module_accounting_uploads \
  module_finance_uploads \
  module_fleet_uploads \
  module_hr_payroll_uploads \
  module_hr_profile_uploads \
  module_invoices_builder_uploads \
  module_ma_uploads \
  module_products_uploads \
  module_purchase_uploads \
  module_service_management_uploads \
  module_si_custom_theme_uploads \
  module_timesheets_uploads \
  caddy_data \
  caddy_config \
  database_data
do
  done_marker="$marker_dir/$volume.done"
  in_progress_marker="$marker_dir/$volume.in-progress"
  if [ -f "$done_marker" ]; then
    echo "Já migrado: $volume"
    continue
  fi

  legacy_volume=$(printf '%s_%s' "$project_name" "$volume")
  target="$storage_dir/$volume"
  mkdir -p "$target"

  if ! docker volume inspect "$legacy_volume" >/dev/null 2>&1; then
    echo "Volume legado ausente; sem cópia: $legacy_volume"
    : > "$done_marker"
    continue
  fi

  if [ ! -f "$in_progress_marker" ] && [ -n "$(find "$target" -mindepth 1 -maxdepth 1 -print -quit)" ]; then
    echo "Destino já contém dados: $target" >&2
    echo "A cópia foi interrompida sem sobrescrever dados; faça backup e reconcilie os diretórios manualmente." >&2
    exit 1
  fi

  if [ ! -f "$in_progress_marker" ]; then
    : > "$in_progress_marker"
  fi

  docker image inspect "$migration_image" >/dev/null 2>&1 || {
    echo "A imagem $migration_image não está local. Execute docker compose pull antes." >&2
    exit 1
  }
  docker run --rm --user 0:0 \
    --volume "$legacy_volume:/legacy:ro" \
    --volume "$target:/target" \
    --entrypoint /bin/sh "$migration_image" \
    -ec 'cp -an /legacy/. /target/'

  : > "$done_marker"
  rm -f "$in_progress_marker"
  echo "Migrado: $legacy_volume -> $target"
done

echo "Migração concluída. Os volumes Docker legados foram preservados; confira os dados e inicie a stack."
