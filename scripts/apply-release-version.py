#!/usr/bin/env python3
"""Apply SemVer metadata and advance a sequential DB migration for every release."""
from __future__ import annotations

import argparse
import re
from pathlib import Path


MIGRATION_VERSION_PATTERN = re.compile(
    r"(?m)(\$config\['migration_version'\]\s*=\s*)([0-9]+)(\s*;)"
)


def parse_semver(version):
    parts = version.split(".")
    if len(parts) != 3 or any(not item.isdigit() for item in parts):
        return None
    parsed = tuple(int(item) for item in parts)
    if any(str(number) != item for number, item in zip(parsed, parts)):
        return None
    return parsed


def replace_constant(source, version):
    marker = "define('ARGWS_VERSION', '"
    if source.count(marker) != 1:
        raise ValueError("Esperava exatamente uma constante ARGWS_VERSION.")
    start = source.index(marker) + len(marker)
    end = source.find("');", start)
    if end < 0 or parse_semver(source[start:end]) is None:
        raise ValueError("ARGWS_VERSION atual está inválida.")
    return source[:start] + version + source[end:]


def replace_compose_default(source, version):
    pattern = re.compile(
        r"(?m)^([ \t]*image:[ \t]*ghcr\.io/wkarts/argws-crm:\$\{ARGWS_VERSION:-)([^}\r\n]+)(\})"
    )
    matches = list(pattern.finditer(source))
    if not matches:
        raise ValueError("Esperava pelo menos uma imagem ARGWS CRM com versão padrão no compose.yaml.")
    if any(parse_semver(match.group(2)) is None for match in matches):
        raise ValueError("A versão padrão do compose.yaml está inválida.")
    return pattern.sub(lambda match: match.group(1) + version + match.group(3), source)


def replace_production_compose(source, version):
    pattern = re.compile(r"(ghcr\.io/wkarts/argws-crm:)(\d+\.\d+\.\d+)")
    matches = list(pattern.finditer(source))
    if not matches:
        raise ValueError("Esperava pelo menos uma tag SemVer padrão da imagem no compose de produção.")
    return pattern.sub(lambda match: match.group(1) + version, source)

def replace_production_env_example(source, version):
    pattern = re.compile(r"(?m)^ARGWS_CRM_IMAGE=ghcr\.io/wkarts/argws-crm:\d+\.\d+\.\d+$")
    updated, count = pattern.subn("ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:" + version, source)
    if count != 1:
        raise ValueError("Esperava exatamente uma tag SemVer da imagem no .env.example de produção.")
    return updated


def replace_container_env_example(source, version):
    pattern = re.compile(r"(?m)^ARGWS_VERSION=\d+\.\d+\.\d+$")
    updated, count = pattern.subn("ARGWS_VERSION=" + version, source)
    if count != 1:
        raise ValueError("Esperava exatamente uma versão ARGWS_VERSION no container.env.example.")
    return updated

def migration_version_from_config(source):
    matches = list(MIGRATION_VERSION_PATTERN.finditer(source))
    if len(matches) != 1:
        raise ValueError("Esperava exatamente uma configuração migration_version numérica.")
    return int(matches[0].group(2))


def replace_migration_version(source, version):
    updated, count = MIGRATION_VERSION_PATTERN.subn(
        lambda match: match.group(1) + str(version) + match.group(3), source
    )
    if count != 1:
        raise ValueError("Esperava exatamente uma configuração migration_version.")
    return updated


def semver_migration_candidate(version):
    parsed = parse_semver(version)
    if parsed is None:
        raise ValueError("A versão deve usar SemVer X.Y.Z sem zeros à esquerda.")
    return int("".join(str(part) for part in parsed))


def migration_id_for_release(version, previous_version, current_level):
    current = parse_semver(previous_version)
    target = parse_semver(version)
    if current is None:
        raise ValueError("A versão SemVer atual em VERSION é inválida.")
    if target is None:
        raise ValueError("A versão deve usar SemVer X.Y.Z sem zeros à esquerda.")
    if target < current:
        raise ValueError("Não é permitido reduzir a versão do produto.")
    if target == current:
        return current_level
    candidate = semver_migration_candidate(version)
    # Keep 342 -> 350 for 3.4.2 -> 3.5.0. Large jumps caused by
    # multi-digit components advance one ID at a time to avoid thousands of files.
    if current_level < candidate <= current_level + 10:
        return candidate
    return current_level + 1


def migration_marker_source(version):
    return (
        "<?php\n\n"
        "defined('BASEPATH') or exit('No direct script access allowed');\n\n"
        f"class Migration_Version_{version} extends CI_Migration\n"
        "{\n"
        "    public function up(): void\n"
        "    {\n"
        "        // Release marker: this version does not require a schema change.\n"
        "    }\n\n"
        "    public function down(): void\n"
        "    {\n"
        "        // No schema changes were made by this release marker.\n"
        "    }\n"
        "}\n"
    )


def ensure_migration_markers(migrations_path, current_level, target_level, allow_create):
    pending = []
    first = current_level + 1 if target_level > current_level else target_level
    for migration_version in range(first, target_level + 1):
        path = migrations_path / f"{migration_version}_version_{migration_version}.php"
        expected_class = f"class Migration_Version_{migration_version} "
        if path.exists():
            source = path.read_text(encoding="utf-8")
            if expected_class not in source or "extends CI_Migration" not in source:
                raise ValueError(f"A migration {path.name} existe, mas não corresponde ao identificador calculado.")
            continue
        if not allow_create:
            raise ValueError(f"A migration atual {path.name} não existe; o histórico não será recriado.")
        pending.append((path, migration_marker_source(migration_version)))
    return pending


def apply_release_version(root, version):
    if parse_semver(version) is None:
        raise ValueError("A versão deve usar SemVer X.Y.Z sem zeros à esquerda.")
    version_path = root / "VERSION"
    constants_path = root / "application/config/constants.php"
    migration_config_path = root / "application/config/migration.php"
    compose_path = root / "compose.yaml"
    production_compose_path = root / "deploy/production/compose.yaml"
    production_env_path = root / "deploy/production/.env.example"
    container_env_path = root / "container.env.example"
    migrations_path = root / "application/migrations"

    previous_version = version_path.read_text(encoding="utf-8").strip()
    if parse_semver(previous_version) is None:
        raise ValueError("VERSION atual não usa SemVer X.Y.Z.")
    migration_config = migration_config_path.read_text(encoding="utf-8")
    previous_migration = migration_version_from_config(migration_config)
    migration_version = migration_id_for_release(version, previous_version, previous_migration)
    markers = ensure_migration_markers(
        migrations_path, previous_migration, migration_version, allow_create=version != previous_version
    )

    constants_content = replace_constant(constants_path.read_text(encoding="utf-8"), version)
    migration_content = replace_migration_version(migration_config, migration_version)
    compose_content = replace_compose_default(compose_path.read_text(encoding="utf-8"), version)
    production_compose_content = replace_production_compose(
        production_compose_path.read_text(encoding="utf-8"), version
    )
    production_env_content = replace_production_env_example(
        production_env_path.read_text(encoding="utf-8"), version
    )
    container_env_content = replace_container_env_example(
        container_env_path.read_text(encoding="utf-8"), version
    )

    for marker_path, marker_content in markers:
        with marker_path.open("x", encoding="utf-8") as stream:
            stream.write(marker_content)
    version_path.write_text(version + "\n", encoding="utf-8")
    constants_path.write_text(constants_content, encoding="utf-8")
    migration_config_path.write_text(migration_content, encoding="utf-8")
    compose_path.write_text(compose_content, encoding="utf-8")
    production_compose_path.write_text(production_compose_content, encoding="utf-8")
    production_env_path.write_text(production_env_content, encoding="utf-8")
    container_env_path.write_text(container_env_content, encoding="utf-8")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("version")
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    args = parser.parse_args()
    try:
        apply_release_version(args.root.resolve(), args.version)
    except ValueError as error:
        raise SystemExit(str(error)) from error


if __name__ == "__main__":
    main()
