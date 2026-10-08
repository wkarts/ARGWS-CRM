#!/usr/bin/env python3
"""Update product release metadata without changing the database schema version."""
from __future__ import annotations

import argparse
import re
from pathlib import Path


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
    marker = "image: ghcr.io/wkarts/argws-crm:" + "$" + "{ARGWS_VERSION:-"
    if source.count(marker) != 1:
        raise ValueError("Esperava exatamente uma imagem ARGWS CRM com versão padrão no compose.yaml.")
    start = source.index(marker) + len(marker)
    end = source.find("}", start)
    if end < 0 or parse_semver(source[start:end]) is None:
        raise ValueError("A versão padrão do compose.yaml está inválida.")
    return source[:start] + version + source[end:]


def replace_production_compose(source, version):
    pattern = re.compile(r"(ghcr\.io/wkarts/argws-crm:)\d+\.\d+\.\d+")
    updated, count = pattern.subn(r"\g<1>" + version, source)
    if count != 1:
        raise ValueError("Esperava exatamente uma tag SemVer padrão da imagem no compose de produção.")
    return updated


def replace_production_env_example(source, version):
    pattern = re.compile(r"(?m)^ARGWS_CRM_IMAGE=ghcr\.io/wkarts/argws-crm:\d+\.\d+\.\d+$")
    updated, count = pattern.subn("ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:" + version, source)
    if count != 1:
        raise ValueError("Esperava exatamente uma tag SemVer da imagem no .env.example de produção.")
    return updated


def apply_release_version(root, version):
    if parse_semver(version) is None:
        raise ValueError("A versão deve usar SemVer X.Y.Z sem zeros à esquerda.")
    version_path = root / "VERSION"
    constants_path = root / "application/config/constants.php"
    compose_path = root / "compose.yaml"
    production_compose_path = root / "deploy/production/compose.yaml"
    production_env_path = root / "deploy/production/.env.example"
    version_content = version + chr(10)
    constants_content = replace_constant(constants_path.read_text(encoding="utf-8"), version)
    compose_content = replace_compose_default(compose_path.read_text(encoding="utf-8"), version)
    production_compose_content = replace_production_compose(
        production_compose_path.read_text(encoding="utf-8"), version
    )
    production_env_content = replace_production_env_example(
        production_env_path.read_text(encoding="utf-8"), version
    )
    version_path.write_text(version_content, encoding="utf-8")
    constants_path.write_text(constants_content, encoding="utf-8")
    compose_path.write_text(compose_content, encoding="utf-8")
    production_compose_path.write_text(production_compose_content, encoding="utf-8")
    production_env_path.write_text(production_env_content, encoding="utf-8")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("version")
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    args = parser.parse_args()
    apply_release_version(args.root.resolve(), args.version)


if __name__ == "__main__":
    main()
