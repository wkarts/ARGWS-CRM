#!/usr/bin/env python3
"""Plan the next ARGWS CRM SemVer from promotion metadata."""
from __future__ import annotations
import argparse
import json
import sys


def parse_semver(value):
    value = value.strip()
    if value.startswith("v"):
        value = value[1:]
    parts = value.split(".")
    if len(parts) != 3 or any(not part.isdigit() for part in parts):
        return None
    numbers = tuple(int(part) for part in parts)
    if any(str(number) != part for number, part in zip(numbers, parts)):
        return None
    return numbers


def format_semver(version):
    return ".".join(str(part) for part in version)


def next_version(version, bump):
    major, minor, patch = version
    if bump == "major":
        return major + 1, 0, 0
    if bump == "minor":
        return major, minor + 1, 0
    return major, minor, patch + 1


def choose_bump(labels, title, force):
    if force != "auto":
        return force
    normalized = {item.strip().lower() for item in labels.split(",") if item.strip()}
    requested = normalized.intersection({"version:major", "version:minor", "version:patch"})
    if len(requested) > 1:
        raise ValueError("Use somente um rótulo version:major, version:minor ou version:patch.")
    if requested:
        return next(iter(requested)).split(":", 1)[1]
    if normalized.intersection({"breaking-change", "breaking change"}):
        return "major"

    prefix = title.strip().split(":", 1)[0].lower()
    if prefix.endswith("!") or "breaking change" in title.lower():
        return "major"
    if prefix == "feat" or (prefix.startswith("feat(") and prefix.endswith(")")):
        return "minor"
    return "patch"


def plan_release(latest_tag, baseline, labels="", title="", force="auto", resume=False):
    if force not in {"auto", "patch", "minor", "major"}:
        raise ValueError("force deve ser auto, patch, minor ou major.")
    base = parse_semver(baseline)
    if base is None:
        raise ValueError(f"VERSION inválida: {baseline!r}. Use SemVer X.Y.Z.")
    latest = parse_semver(latest_tag) if latest_tag else None
    if latest_tag and latest is None:
        raise ValueError(f"Última tag inválida: {latest_tag!r}.")
    if resume:
        return {"version": format_semver(base), "bump": "resume", "previous": format_semver(latest) if latest else None}
    if latest is None:
        # Keep the configured first version, 3.4.2, instead of resetting this legacy app to 1.0.0.
        return {"version": format_semver(base), "bump": "initial", "previous": None}
    if base < latest:
        raise ValueError(
            f"VERSION ({format_semver(base)}) está atrás da última release "
            f"({format_semver(latest)}); sincronize os metadados antes da promoção."
        )
    if base > latest:
        # Reuse a version persisted by a prior interrupted release attempt.
        return {"version": format_semver(base), "bump": "resume", "previous": format_semver(latest)}
    bump = choose_bump(labels, title, force)
    return {
        "version": format_semver(next_version(latest, bump)),
        "bump": bump,
        "previous": format_semver(latest),
    }


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--latest", default="")
    parser.add_argument("--baseline", required=True)
    parser.add_argument("--labels", default="")
    parser.add_argument("--title", default="")
    parser.add_argument("--force", choices=("auto", "patch", "minor", "major"), default="auto")
    parser.add_argument("--resume", action="store_true")
    args = parser.parse_args()
    try:
        result = plan_release(args.latest, args.baseline, args.labels, args.title, args.force, args.resume)
    except ValueError as error:
        print(str(error), file=sys.stderr)
        raise SystemExit(1) from error
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
