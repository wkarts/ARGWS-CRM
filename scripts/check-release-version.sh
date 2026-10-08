#!/usr/bin/env bash
set -euo pipefail

version="$(tr -d '[:space:]' < VERSION)"
code_version="$(sed -n "s/.*define('ARGWS_VERSION', '\([^']*\)'.*/\1/p" application/config/constants.php | head -n 1)"
migration_version="$(sed -n "s/.*migration_version'\] = \([0-9][0-9]*\).*/\1/p" application/config/migration.php | head -n 1)"

[[ "$version" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]] || { echo 'VERSION deve usar SemVer X.Y.Z sem zeros à esquerda.' >&2; exit 1; }
[[ "$code_version" == "$version" ]] || { echo "ARGWS_VERSION ($code_version) não corresponde a VERSION ($version)." >&2; exit 1; }
[[ "$migration_version" =~ ^[0-9]+$ ]] || { echo 'migration_version deve ser um identificador numérico.' >&2; exit 1; }

for ((level=101; level<=migration_version; level++)); do
  migration_file="application/migrations/$level"_version_"$level".php
  [[ -f "$migration_file" ]] || {
    echo "Lacuna na sequência de migrations: arquivo ausente para o nível $level (release configurada: $migration_version)." >&2
    exit 1
  }
  grep -Eq "class Migration_Version_$level[[:space:]]+extends CI_Migration" "$migration_file" || {
    echo "A classe Migration_Version_$level não corresponde ao arquivo $migration_file." >&2
    exit 1
  }
done

if (($# > 0)) && [[ "$1" != "v$version" ]]; then
  echo "A tag $1 não corresponde a VERSION v$version." >&2
  exit 1
fi
printf 'Release verificada: ARGWS CRM %s (sequência de migrations 101-%s completa)\n' "$version" "$migration_version"
