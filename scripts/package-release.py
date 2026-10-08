#!/usr/bin/env python3
"""Create reproducible ARGWS CRM full and overlay release archives."""

from __future__ import annotations

import argparse
import hashlib
import os
import stat
import zipfile
from pathlib import Path, PurePosixPath


ROOT = Path(__file__).resolve().parents[1]
RUNTIME_KEEP_NAMES = {".htaccess", "index.html", "index.php"}
SAMPLE_UPLOAD_DIRS = {
    "modules/accounting/uploads/file_sample",
    "modules/hr_profile/uploads/sample_file",
    "modules/purchase/uploads/file_sample",
}
STATIC_UPLOAD_FILES = {
    "modules/hr_profile/uploads/none_avatar.jpg",
    "modules/hr_profile/uploads/nul_image.jpg",
    "modules/products/uploads/image-not-available.png",
    "modules/products/uploads/no-product.png",
    "modules/purchase/uploads/approval/approved.png",
    "modules/purchase/uploads/approval/rejected.png",
    "modules/purchase/uploads/image_not_available.jpg",
    "modules/purchase/uploads/nul_image.jpg",
    "modules/service_management/uploads/image_not_available.jpg",
    "modules/service_management/uploads/no_image.jpg",
    "modules/service_management/uploads/null_image.jpg",
    "modules/timesheets/uploads/approval/approved.png",
    "modules/timesheets/uploads/approval/rejected.png",
    "modules/timesheets/uploads/timesheets/import_timesheets.xlsx",
}


def should_include(relative: str) -> bool:
    path = PurePosixPath(relative)
    parts = path.parts
    if not parts or relative.startswith((".git/", "source/", "work/", "deliverables/", "data/")):
        return False
    if parts[0] in {"deploy", "docker"} or relative.startswith("tools/argws-crm-deployer/") or relative == "scripts/package-deploy.py":
        return False
    if (
        path.name == ".DS_Store"
        or "__MACOSX" in parts
        or "node_modules" in parts
        or "__pycache__" in parts
        or ".pytest_cache" in parts
        or path.suffix in {".pyc", ".pyo"}
    ):
        return False
    if path.name == ".env" or path.name.startswith(".env."):
        return False
    if relative == "application/config/app-config.php":
        return False
    if relative.startswith(("application/cache/", "application/logs/", "temp/")):
        return path.name in RUNTIME_KEEP_NAMES

    # Upload folders can contain customer documents from the source package.
    # Keep only security sentinels, bundled import templates, and known stock images.
    try:
        uploads_index = parts.index("uploads")
    except ValueError:
        return True
    if path.name in RUNTIME_KEEP_NAMES:
        return True
    if relative in STATIC_UPLOAD_FILES:
        return True
    if any(relative.startswith(directory + "/") for directory in SAMPLE_UPLOAD_DIRS):
        return True
    return False


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def base_hashes(base_dir: Path | None, base_zip: Path | None) -> dict[str, str]:
    hashes: dict[str, str] = {}
    if base_dir is not None:
        for path in base_dir.rglob("*"):
            if not path.is_file():
                continue
            relative = path.relative_to(base_dir).as_posix()
            if should_include(relative):
                hashes[relative] = sha256_file(path)
        return hashes

    if base_zip is not None:
        with zipfile.ZipFile(base_zip) as archive:
            for info in archive.infolist():
                if info.is_dir() or not should_include(info.filename):
                    continue
                digest = hashlib.sha256()
                with archive.open(info) as stream:
                    for chunk in iter(lambda: stream.read(1024 * 1024), b""):
                        digest.update(chunk)
                hashes[info.filename] = digest.hexdigest()
    return hashes


def current_files() -> dict[str, Path]:
    files: dict[str, Path] = {}
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        relative = path.relative_to(ROOT).as_posix()
        if should_include(relative):
            files[relative] = path
    return files


def add_file(archive: zipfile.ZipFile, path: Path, relative: str) -> None:
    metadata = path.stat()
    info = zipfile.ZipInfo(relative, date_time=(1980, 1, 1, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = ((stat.S_IFREG | stat.S_IMODE(metadata.st_mode)) & 0xFFFF) << 16
    with path.open("rb") as stream, archive.open(info, "w", force_zip64=True) as target:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            target.write(chunk)


def write_zip(destination: Path, items: list[tuple[str, Path]]) -> None:
    destination.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(destination, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6, allowZip64=True) as archive:
        for relative, path in sorted(items):
            add_file(archive, path, relative)


def write_text_member(archive: zipfile.ZipFile, name: str, content: str) -> None:
    info = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
    info.compress_type = zipfile.ZIP_DEFLATED
    info.external_attr = (stat.S_IFREG | 0o644) << 16
    archive.writestr(info, content.encode("utf-8"))


def main() -> None:
    parser = argparse.ArgumentParser()
    source = parser.add_mutually_exclusive_group()
    source.add_argument("--base-dir", type=Path)
    source.add_argument("--base-zip", type=Path)
    parser.add_argument("--output-dir", type=Path, required=True)
    args = parser.parse_args()

    version = (ROOT / "VERSION").read_text(encoding="utf-8").strip()
    if not version:
        raise SystemExit("VERSION está vazio.")

    current = current_files()
    base = base_hashes(args.base_dir, args.base_zip)
    changed = [
        (relative, path)
        for relative, path in current.items()
        if base.get(relative) != sha256_file(path)
    ]
    removed = sorted(set(base) - set(current))

    output_dir = args.output_dir.resolve()
    full_path = output_dir / f"ARGWS-CRM-{version}-full.zip"
    update_path = output_dir / f"ARGWS-CRM-{version}-update.zip"
    write_zip(full_path, list(current.items()))

    instructions = [
        f"# Atualização ARGWS CRM {version}",
        "",
        "Este ZIP contém somente arquivos novos ou alterados em relação à versão-base informada no contrato de distribuição.",
        "",
        "1. Faça cópias de segurança dos arquivos, banco de dados e uploads.",
        "2. Preserve `application/config/app-config.php`, os diretórios `uploads/` e todos os diretórios de uploads dos recursos.",
        "3. Extraia o conteúdo deste ZIP sobre a instalação existente, mantendo os caminhos relativos.",
        "4. Revise `REMOVED-FILES.txt` e remova somente os arquivos de código antigos listados, se ainda existirem. Não remova arquivos de dados enviados por clientes.",
        "5. Entre como administrador e aplique a migration incluída na tela de atualização do banco.",
        "6. Limpe o cache de aplicação após concluir a atualização.",
        "",
        "Arquivos de uploads da fonte foram excluídos dos pacotes por conterem dados de instalação. Os arquivos de uploads existentes na instalação permanecem intactos.",
        "",
    ]
    with zipfile.ZipFile(update_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=6, allowZip64=True) as archive:
        for relative, path in sorted(changed):
            add_file(archive, path, relative)
        write_text_member(archive, "UPDATE-MANIFEST.md", "\n".join(instructions))
        write_text_member(archive, "REMOVED-FILES.txt", "\n".join(removed) + ("\n" if removed else ""))

    # Verify the overlay state by reconstructing the expected set of paths and hashes.
    overlay = {name: digest for name, digest in base.items() if name not in removed}
    overlay.update({name: sha256_file(path) for name, path in changed})
    expected = {name: sha256_file(path) for name, path in current.items()}
    if overlay != expected:
        raise SystemExit("A validação do overlay incremental não corresponde à árvore atual.")
    with zipfile.ZipFile(full_path) as archive:
        if archive.testzip() is not None:
            raise SystemExit("ZIP completo inválido.")
    with zipfile.ZipFile(update_path) as archive:
        if archive.testzip() is not None:
            raise SystemExit("ZIP de atualização inválido.")

    sums_path = output_dir / f"ARGWS-CRM-{version}-SHA256SUMS.txt"
    sums_path.write_text(
        f"{sha256_file(full_path)}  {full_path.name}\n{sha256_file(update_path)}  {update_path.name}\n",
        encoding="utf-8",
    )
    print(f"Versão: {version}")
    print(f"Arquivos completos: {len(current)}")
    print(f"Arquivos alterados/adicionados no incremental: {len(changed)}")
    print(f"Arquivos de código removidos listados: {len(removed)}")
    print(f"ZIP completo: {full_path} ({full_path.stat().st_size} bytes)")
    print(f"ZIP incremental: {update_path} ({update_path.stat().st_size} bytes)")
    print(f"Checksums: {sums_path}")


if __name__ == "__main__":
    main()
