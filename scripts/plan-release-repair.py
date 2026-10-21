#!/usr/bin/env python3
"""Print provenance for a historical repair candidate without changing Git."""
import argparse
import json
from pathlib import Path
from release_repair import plan_repair


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--root', type=Path, default=Path(__file__).resolve().parents[1])
    parser.add_argument('--tag', required=True)
    args = parser.parse_args()
    try:
        print(json.dumps(plan_repair(args.root, args.tag), ensure_ascii=False))
    except ValueError as error:
        raise SystemExit(str(error)) from error


if __name__ == '__main__':
    main()
