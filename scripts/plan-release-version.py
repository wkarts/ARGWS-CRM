#!/usr/bin/env python3
"""Plan an already reviewed native release; labels and SemVer ordering are irrelevant."""
from __future__ import annotations
import argparse
import json
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parent))
from release_policy import git_metadata, release_tags, target_levels, git_reader, migration_path, validate_marker


def plan_release(root, head='HEAD'):
    current = git_metadata(root, head, allow_legacy=False)
    releases = release_tags(root, head)
    prior = [item for item in releases if item['migration'] < current['migration']]
    same = [item for item in releases if item['migration'] == current['migration']]
    if any(item['migration'] > current['migration'] for item in releases):
        raise ValueError('Código está atrás da última migration publicada.')
    if same:
        if same[0]['commit'] != current['commit']:
            raise ValueError('Tag já existe em outro commit. Gere o próximo ID; não sobrescreva o histórico.')
        bump = 'resume'
    else:
        if prior:
            for level in target_levels(prior[-1], current['migration'], allow_contiguous=True):
                validate_marker(git_reader(root, current['commit'])(migration_path(level)), level)
        bump = 'native' if prior else 'initial'
    previous = prior[-1] if prior else None
    return {'version': current['version'], 'migration': current['migration'],
            'tag': 'v' + current['version'], 'bump': bump,
            'previous': previous['version'] if previous else None,
            'previous_tag': previous['tag'] if previous else None}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--head', default='HEAD')
    args = parser.parse_args()
    try:
        print(json.dumps(plan_release(args.root, args.head), ensure_ascii=False))
    except ValueError as error:
        raise SystemExit(str(error)) from error


if __name__ == '__main__':
    main()
