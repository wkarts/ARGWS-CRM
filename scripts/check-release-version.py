#!/usr/bin/env python3
"""Read-only release validation, including the original PR head/merge-base delta."""
from __future__ import annotations
import argparse
from pathlib import Path
from release_policy import check_delta, file_reader, validate_tree, validate_distribution


def check(root, tag=None):
    info = validate_tree(file_reader(root))
    if tag and tag != 'v' + info['version']:
        raise ValueError('Tag não corresponde à versão canônica configurada.')
    validate_distribution(file_reader(root), info['version'])
    return info


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('tag', nargs='?')
    parser.add_argument('--root', type=Path, default=Path.cwd())
    parser.add_argument('--base')
    parser.add_argument('--head')
    args = parser.parse_args()
    try:
        if bool(args.base) != bool(args.head):
            raise ValueError('Informe base e head juntos.')
        info = check_delta(args.root, args.base, args.head) if args.base else check(args.root, args.tag)
        print(f'Release canônica verificada: {info["version"]} = migration {info["migration"]}.')
    except (ValueError, OSError) as error:
        raise SystemExit(str(error)) from error


if __name__ == '__main__':
    main()
