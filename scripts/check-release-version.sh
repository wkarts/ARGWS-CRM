#!/usr/bin/env bash
set -euo pipefail

version="$(tr -d '[:space:]' < VERSION)"
code_version="$(sed -n "s/.*define('ARGWS_VERSION', '\([^']*\)'.*/\1/p" application/config/constants.php | head -n 1)"
migration_version="$(sed -n "s/.*migration_version'\] = \([0-9][0-9]*\).*/\1/p" application/config/migration.php | head -n 1)"
expected_migration="${version//./}"

[[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || { echo 'VERSION deve usar SemVer X.Y.Z.' >&2; exit 1; }
[[ "$code_version" == "$version" ]] || { echo "ARGWS_VERSION ($code_version) não corresponde a VERSION ($version)." >&2; exit 1; }
[[ "$migration_version" == "$expected_migration" ]] || { echo "migration_version ($migration_version) deve corresponder a $expected_migration." >&2; exit 1; }

if [[ "${1:-}" != '' && "$1" != "v$version" ]]; then
  echo "A tag $1 não corresponde a VERSION v$version." >&2
  exit 1
fi

printf 'Release verificada: ARGWS CRM %s (migration %s)\n' "$version" "$migration_version"
