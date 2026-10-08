#!/usr/bin/env python3
"""Build the standalone ARGWS CRM deployment kit distributed with releases."""
from __future__ import annotations

import argparse
import re
import stat
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REQUIRED = {
    "deploy/README.md",
    "deploy/develop/compose.yaml",
    "deploy/develop/.env.example",
    "deploy/production/compose.yaml",
    "deploy/production/.env.example",
}
SEMVER = re.compile(r"^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$")


def replace_production_tag(path: str, contents: bytes, version: str) -> bytes:
    text = contents.decode("utf-8")
    if path.endswith("compose.yaml"):
        pattern = re.compile(r"(ghcr\.io/wkarts/argws-crm:)\d+\.\d+\.\d+")
        replacement = r"\g<1>" + version
    else:
        pattern = re.compile(r"(?m)^ARGWS_CRM_IMAGE=ghcr\.io/wkarts/argws-crm:\d+\.\d+\.\d+$")
        replacement = "ARGWS_CRM_IMAGE=ghcr.io/wkarts/argws-crm:" + version
    updated, count = pattern.subn(replacement, text)
    if count != 1:
        raise ValueError(f"{path}: esperada exatamente uma tag SemVer de produção, encontradas {count}.")
    return updated.encode("utf-8")


def package_deploy(root: Path, output_dir: Path, version: str | None = None, develop: bool = False) -> Path:
    if (version is not None and develop) or (version is None and not develop):
        raise ValueError("Informe exatamente uma versão SemVer ou selecione o canal develop.")
    if version is not None and SEMVER.fullmatch(version) is None:
        raise ValueError("A versão de produção deve usar SemVer X.Y.Z sem zeros à esquerda.")

    source = root / "deploy"
    if not source.is_dir():
        raise ValueError("Diretório deploy/ ausente.")
    output_dir = output_dir.resolve()
    if source.resolve() == output_dir or source.resolve() in output_dir.parents:
        raise ValueError("O pacote de saída não pode ser criado dentro de deploy/.")

    items: list[tuple[str, Path]] = []
    for path in source.rglob("*"):
        if not path.is_file():
            continue
        relative = path.relative_to(root).as_posix()
        if path.name == ".env" or (path.name.startswith(".env.") and path.name != ".env.example"):
            continue
        items.append((relative, path))

    included = {relative for relative, _ in items}
    missing = sorted(REQUIRED - included)
    if missing:
        raise ValueError("Arquivos operacionais obrigatórios ausentes: " + ", ".join(missing))

    output_dir.mkdir(parents=True, exist_ok=True)
    filename = f"ARGWS-CRM-deploy-{version}.zip" if version is not None else "ARGWS-CRM-deploy-develop.zip"
    destination = output_dir / filename
    with zipfile.ZipFile(destination, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for relative, path in sorted(items):
            data = path.read_bytes()
            if version is not None and relative in {
                "deploy/production/compose.yaml",
                "deploy/production/.env.example",
            }:
                data = replace_production_tag(relative, data, version)
            info = zipfile.ZipInfo(relative, date_time=(1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = (stat.S_IFREG | 0o644) << 16
            archive.writestr(info, data)
    with zipfile.ZipFile(destination) as archive:
        bad = archive.testzip()
        if bad is not None or not set(archive.namelist()).issubset(included):
            destination.unlink(missing_ok=True)
            raise ValueError(f"Pacote de implantação inválido: {bad or 'caminho inesperado'}.")
        if any(Path(name).name == ".env" for name in archive.namelist()):
            destination.unlink(missing_ok=True)
            raise ValueError("O pacote de implantação não pode conter segredos .env.")
    return destination


def main() -> None:
    parser = argparse.ArgumentParser()
    channel = parser.add_mutually_exclusive_group(required=True)
    channel.add_argument("--version", help="Versão SemVer de produção para fixar a imagem GHCR.")
    channel.add_argument("--develop", action="store_true", help="Empacota o canal de desenvolvimento.")
    parser.add_argument("--output-dir", type=Path, required=True)
    args = parser.parse_args()
    result = package_deploy(ROOT, args.output_dir.resolve(), version=args.version, develop=args.develop)
    print(result)


if __name__ == "__main__":
    main()
