#!/usr/bin/env python3
"""Opt-in, read-only local Docker/upstream diagnostics; never collect credentials."""
import argparse
import json
import re
import subprocess


def run(command, timeout=10):
    try:
        result = subprocess.run(command, capture_output=True, text=True, timeout=timeout)
        if result.returncode:
            # Docker/curl errors may contain deployment details. Do not export stderr.
            return {"available": False, "exit_code": result.returncode}
        return {"available": True, "value": result.stdout.strip()}
    except FileNotFoundError:
        return {"available": False, "reason": "command_missing"}
    except subprocess.TimeoutExpired:
        return {"available": False, "reason": "probe_timeout"}


def container_name(value):
    if not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_.-]{0,127}", value):
        raise argparse.ArgumentTypeError("Use an explicit container name or ID, not an option/path.")
    return value


def port_number(value):
    try:
        port = int(value)
    except ValueError:
        raise argparse.ArgumentTypeError("Port must be an integer.")
    if not 1 <= port <= 65535:
        raise argparse.ArgumentTypeError("Port must be between 1 and 65535.")
    return port


def collect(container, port=None):
    # Fixed scalar fields only. Never request .Config.Env, full .State (health logs),
    # docker logs, compose config, process arguments, PHP info, or application data.
    fields = {
        "status": "{{.State.Status}}",
        "running": "{{.State.Running}}",
        "oom_killed": "{{.State.OOMKilled}}",
        "exit_code": "{{.State.ExitCode}}",
        "restart_count": "{{.RestartCount}}",
        "health": '{{if .State.Health}}{{.State.Health.Status}}{{else}}not_configured{{end}}',
        "memory_limit_bytes": "{{.HostConfig.Memory}}",
        "nano_cpus": "{{.HostConfig.NanoCpus}}",
    }
    report = {"schema": 1, "scope": "local_snapshot_not_root_cause", "container": {}}
    for key, template in fields.items():
        report["container"][key] = run(["docker", "inspect", "--format", template, container])
    report["resources"] = run([
        "docker", "stats", "--no-stream", "--format",
        'cpu={{.CPUPerc}} memory={{.MemUsage}} memory_percent={{.MemPerc}} pids={{.PIDs}}',
        container,
    ])
    if port is not None:
        # Exactly one anonymous GET, no redirects/cookies/auth/body capture. Disable
        # curl config and proxy inheritance so this remains a local probe.
        report["anonymous_local_http"] = run([
            "curl", "--disable", "--silent", "--noproxy", "*", "--proxy", "",
            "--connect-timeout", "2", "--max-time", "5", "--output", "/dev/null",
            "--write-out", 'status=%{http_code} connect_s=%{time_connect} ttfb_s=%{time_starttransfer} total_s=%{time_total}',
            f"http://127.0.0.1:{port}/admin/",
        ], timeout=7)
    return report


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--container", required=True, type=container_name)
    parser.add_argument("--local-port", type=port_number,
                        help="Optional known published port; sends one anonymous GET /admin/.")
    args = parser.parse_args()
    print(json.dumps(collect(args.container, args.local_port), indent=2))


if __name__ == "__main__":
    main()
