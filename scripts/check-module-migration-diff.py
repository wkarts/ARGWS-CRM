#!/usr/bin/env python3
"""Check module release deltas without rewriting historical migration baselines."""
import argparse
import dataclasses
import hashlib
import json
import posixpath
import re
import subprocess
import sys
from pathlib import Path


REGULAR_MODES = {"100644", "100755"}
DOCUMENT_NAMES = {"README", "CHANGELOG", "LICENSE", "LICENCE", "COPYING", "NOTICE", "CONTRIBUTING", "AGENTS"}
DOCUMENT_SUFFIXES = {".md", ".rst", ".txt"}
SOURCE_SUFFIXES = {".js", ".mjs", ".cjs", ".ts", ".tsx", ".jsx", ".css", ".scss", ".sass", ".less"}
MIGRATION_NAME = re.compile(r"([0-9]{3})_version_\1\.php\Z")


@dataclasses.dataclass(frozen=True)
class Entry:
    oid: str
    mode: str = "100644"


class MemoryTree:
    """Deterministic fixture tree; production reads immutable Git blobs instead."""
    def __init__(self, files):
        self.data = {p: v.encode() if isinstance(v, str) else v for p, v in files.items()}
        self.entries = {p: Entry(hashlib.sha256(v).hexdigest()) for p, v in self.data.items()}

    def read(self, path):
        return self.data[path].decode("utf-8")


def git(root, *args):
    result = subprocess.run(["git", "-C", str(root), *args], capture_output=True, check=False)
    if result.returncode:
        # Do not echo arbitrary repository content or command output into CI logs.
        raise ValueError("Git could not read the requested commits; fetch the complete base/head history")
    return result.stdout


class GitTree:
    def __init__(self, root, commit):
        self.root, self.entries = root, {}
        for item in git(root, "ls-tree", "-rz", "--full-tree", commit, "--", "modules/").split(b"\0"):
            if not item:
                continue
            metadata, path = item.split(b"\t", 1)
            mode, kind, oid = metadata.decode("ascii").split()
            self.entries[path.decode("utf-8")] = Entry(oid, mode if kind == "blob" else kind)

    def read(self, path):
        entry = self.entries[path]
        if entry.mode not in REGULAR_MODES:
            raise ValueError("module policy inputs must be regular tracked files")
        return git(self.root, "cat-file", "blob", entry.oid).decode("utf-8")


def is_document(path):
    parts = path.split("/")
    relative = parts[2:]
    name = relative[-1]
    stem, suffix = posixpath.splitext(name)
    if len(relative) == 1 and (name in DOCUMENT_NAMES or (stem in DOCUMENT_NAMES and suffix.lower() in DOCUMENT_SUFFIXES)):
        return True
    # PHP/JS/HTML/views/translations do not become documentation by directory name.
    return relative[0] == "docs" and suffix.lower() in DOCUMENT_SUFFIXES


def unchanged_regular(before, after, path):
    return path in before.entries and before.entries.get(path) == after.entries.get(path) and before.entries[path].mode in REGULAR_MODES


def proven_source_map(path, before, after):
    """Exclude only debug metadata whose unchanged local sources/bundle are proven."""
    if not path.endswith(".map"):
        return False
    tree = after if path in after.entries else before
    prefix = "/".join(path.split("/")[:2]) + "/"
    try:
        obj = json.loads(tree.read(path))
        if not isinstance(obj, dict) or obj.get("version") != 3 or obj.get("sourceRoot", "") != "":
            return False
        sources, contents = obj.get("sources"), obj.get("sourcesContent")
        if not isinstance(sources, list) or not sources or not isinstance(contents, list) or len(sources) != len(contents):
            return False
        if not isinstance(obj.get("mappings"), str):
            return False

        def resolve(value):
            if not isinstance(value, str) or not value or "\\" in value or ":" in value or value.startswith("/"):
                raise ValueError("not a local source")
            result = posixpath.normpath(posixpath.join(posixpath.dirname(path), value))
            if not result.startswith(prefix) or not unchanged_regular(before, after, result):
                raise ValueError("unproven generated input")
            return result

        bundle = resolve(obj.get("file"))
        if Path(bundle).suffix not in {".js", ".mjs", ".cjs", ".css"}:
            return False
        for source, content in zip(sources, contents):
            source_path = resolve(source)
            if Path(source_path).suffix not in SOURCE_SUFFIXES or not isinstance(content, str) or after.read(source_path) != content:
                return False
        return True
    except (ValueError, KeyError, UnicodeError, TypeError):
        return False


def php_without_comments(code, strings=False):
    # Preserve quoted SQL when inspecting install DDL; remove literals for class/call checks.
    pattern = r"'(?:\\.|[^'\\])*'|\"(?:\\.|[^\"\\])*\"|/\*[\s\S]*?\*/|//[^\n]*|\#[^\n]*"
    return re.sub(pattern, lambda m: " " if strings or m[0].startswith(("/*", "//", "#")) else m[0], code)


def module_version(tree, path):
    headers = re.findall(r"^[ \t/*#@]*Version:[ \t]*(.*?)[ \t]*$", tree.read(path)[:8192], re.M)
    if len(headers) != 1 or not re.fullmatch(r"[0-9]\.[0-9]\.[0-9]", headers[0]):
        raise ValueError("Version must be one unambiguous X.Y.Z header with one digit per component; native executor removes dots and accepts three-digit migrations")
    return headers[0], int(headers[0].replace(".", ""))


def validate_changes(before, after):
    changed = sorted(p for p in before.entries.keys() | after.entries.keys() if before.entries.get(p) != after.entries.get(p))
    functional = {}
    ignored = []
    for path in changed:
        parts = path.split("/")
        if parts[0] != "modules" or len(parts) < 2:
            continue
        if len(parts) == 2:
            if any(tree.entries[path].mode not in REGULAR_MODES for tree in (before, after) if path in tree.entries):
                functional.setdefault(parts[1], []).append(path)
            continue
        regular = all(tree.entries[path].mode in REGULAR_MODES for tree in (before, after) if path in tree.entries)
        if regular and (is_document(path) or proven_source_map(path, before, after)):
            ignored.append(path)
        else:
            functional.setdefault(parts[1], []).append(path)
    errors = []
    for name, paths in sorted(functional.items()):
        prefix = "modules/" + name + "/"
        main = prefix + name + ".php"
        if main not in after.entries:
            errors.append(name + ": missing main entrypoint; module deletion/rename requires a separately reviewed retirement plan")
            continue
        if any(after.entries[p].mode not in REGULAR_MODES for p in paths if p in after.entries):
            errors.append(name + ": changed module files must be regular files, not symlinks/submodules")
            continue
        try:
            version, level = module_version(after, main)
            existing = main in before.entries
            adoption = (name == "gerencianet_gateway" and existing and version == "5.1.5"
                        and hashlib.sha256(before.read(main).encode()).hexdigest()
                        == "5688625c931504c9b21c33efdb72caa62369e7230542ddc856ee340829a2033c")
            if adoption:
                # Reviewed adoption, NOT a claim that migration 514 ever existed/applied.
                old_version, baseline = "5.1.4 sdk final", None
            else:
                old_version, baseline = module_version(before, main) if existing else ("1.0.0", 100)
        except (ValueError, UnicodeError) as exc:
            errors.append(name + ": " + str(exc))
            continue
        if baseline is not None and level <= baseline:
            errors.append(name + ": functional changes require Version above " + old_version)

        migration_prefix = prefix + "migrations/"
        historical = {p for p in before.entries if p.startswith(migration_prefix) and p.lower().endswith(".php")}
        if adoption and historical:
            errors.append(name + ": audited adoption requires the proven legacy tree without migration history")
        for path in sorted(historical):
            if before.entries[path] != after.entries.get(path):
                errors.append(name + ": historical migration is immutable: " + path)
        added = sorted(p for p in after.entries if p.startswith(migration_prefix) and p.lower().endswith(".php") and p not in before.entries)
        numbers = []
        for path in added:
            filename = path[len(migration_prefix):]
            match = MIGRATION_NAME.fullmatch(filename)
            if not match:
                errors.append(name + ": new migration needs NNN_version_NNN.php: " + path)
                continue
            number = int(match[1])
            numbers.append(number)
            try:
                code = php_without_comments(after.read(path), strings=True)
            except (ValueError, UnicodeError):
                errors.append(name + ": unreadable new migration: " + path)
                continue
            if not re.search(r"\bclass\s+Migration_Version_" + match[1] + r"\s+extends\s+App_module_migration\b", code):
                errors.append(name + ": new migration must extend App_module_migration with the matching class: " + path)
            if not re.search(r"\bpublic\s+function\s+up\s*\(\s*\)", code):
                errors.append(name + ": new migration needs public up(), including documented NOOP: " + path)
        expected = [515] if adoption else (list(range(baseline + 1, level + 1)) if level > baseline else [])
        if sorted(numbers) != expected or not numbers:
            errors.append(name + ": add every new native step from " + str(515 if adoption else baseline + 1) + " through " + str(level) + "; keep the historical baseline unchanged")
        if not existing:
            install = prefix + "install.php"
            try:
                raw = after.read(install)
                code = php_without_comments(raw, strings=True)
                sql = php_without_comments(raw)
                if not re.search(r"\bnew\s+App_module_migration\s*\(", code) or not re.search(r"->\s*to_latest\s*\(\s*\)", code):
                    errors.append(name + ": new module install.php must invoke the native migration executor")
                if re.search(r"\b(?:CREATE|ALTER|DROP|TRUNCATE|RENAME)\s+TABLE\b|\bSchema\s*::\s*(?:statements|documentStatements)\b|->\s*(?:create_table|add_column|modify_column|drop_table|drop_column)\s*\(", sql, re.I):
                    errors.append(name + ": new module install.php must not own DDL; use migrations")
            except (KeyError, ValueError, UnicodeError):
                errors.append(name + ": new module needs a regular install.php using the native migration executor")
    return errors, functional, ignored


def compare_repository(root, base, head):
    # SHA-only arguments avoid options/revision-expression injection and retarget ambiguity.
    if not all(re.fullmatch(r"[0-9a-fA-F]{40}", sha) and set(sha) != {"0"} for sha in (base, head)):
        raise ValueError("base and head must be complete nonzero commit SHAs")
    for sha in (base, head):
        git(root, "cat-file", "-e", sha + "^{commit}")
    merge_base = git(root, "merge-base", "--all", base, head).decode().strip()
    if not re.fullmatch(r"[0-9a-f]{40}", merge_base):
        raise ValueError("a unique merge base is required")
    errors, functional, ignored = validate_changes(GitTree(root, merge_base), GitTree(root, head))
    return merge_base, errors, functional, ignored


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base", required=True, help="Current PR base commit SHA; compare from its merge base with head")
    parser.add_argument("--head", required=True, help="PR head commit SHA, not the synthetic merge commit")
    parser.add_argument("--repo", type=Path, default=Path(__file__).resolve().parents[1])
    args = parser.parse_args()
    try:
        baseline, errors, modules, ignored = compare_repository(args.repo, args.base, args.head)
    except (ValueError, UnicodeError) as exc:
        print("Module migration policy: " + str(exc), file=sys.stderr)
        return 2
    print("Module migration baseline: " + baseline)
    print("Functional modules: " + json.dumps(sorted(modules), ensure_ascii=True))
    print("Proven nonfunctional files: " + str(len(ignored)))
    for error in errors:
        # JSON escaping prevents repository paths from injecting workflow commands/new lines.
        print("FAIL " + json.dumps(error, ensure_ascii=True), file=sys.stderr)
    return 1 if errors else 0


if __name__ == "__main__":
    sys.exit(main())
