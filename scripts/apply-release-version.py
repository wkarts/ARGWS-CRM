#!/usr/bin/env python3
"""Update product release metadata without changing the database schema version."""
from __future__ import annotations
import argparse
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


def apply_release_version(root, version):
    if parse_semver(version) is None:
        raise ValueError("A versão deve usar SemVer X.Y.Z sem zeros à esquerda.")
    version_path = root / "VERSION"
    constants_path = root / "application/config/constants.php"
    compose_path = root / "compose.yaml"
    version_content = version + chr(10)
    constants_content = replace_constant(constants_path.read_text(encoding="utf-8"), version)
    compose_content = replace_compose_default(compose_path.read_text(encoding="utf-8"), version)
    version_path.write_text(version_content, encoding="utf-8")
    constants_path.write_text(constants_content, encoding="utf-8")
    compose_path.write_text(compose_content, encoding="utf-8")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("version")
    parser.add_argument("--root", type=Path, default=Path(__file__).resolve().parents[1])
    args = parser.parse_args()
    apply_release_version(args.root.resolve(), args.version)


if __name__ == "__main__":
    main()
